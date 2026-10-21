<?php
// Hotels and their "posts". A post is one block on the hotel's page: images + text + price.
// Owners and admins edit a hotel with one form; posts are added/removed freely.
require_once __DIR__ . '/helpers.php';

const MAX_POSTS = 50;
const MAX_POST_IMAGES = 6;

function clean_text($v, int $max): string
{
    return mb_substr(trim((string)$v), 0, $max);
}

function delete_paths(array $paths): void
{
    foreach ($paths as $p) {
        delete_upload($p);
    }
}

/* ---------- reading ---------- */
// Hotel row plus ['posts' => [['id','title','body','price','images'=>[paths]]]], or null.
function hotel_load(int $id): ?array
{
    $pdo = db();
    $st = $pdo->prepare('SELECT * FROM hotels WHERE id = ?');
    $st->execute([$id]);
    $h = $st->fetch();
    if (!$h) {
        return null;
    }
    $ps = $pdo->prepare('SELECT * FROM hotel_posts WHERE hotel_id = ? ORDER BY sort_order, id');
    $ps->execute([$id]);
    $im = $pdo->prepare('SELECT path FROM post_images WHERE post_id = ? ORDER BY sort_order, id');
    $h['posts'] = [];
    foreach ($ps->fetchAll() as $p) {
        $im->execute([$p['id']]);
        $p['images'] = $im->fetchAll(PDO::FETCH_COLUMN);
        $h['posts'][] = $p;
    }
    return $h;
}

function hotel_paths(array $hotel): array
{
    $paths = [];
    foreach ($hotel['posts'] ?? [] as $p) {
        $paths = array_merge($paths, $p['images']);
    }
    return array_values(array_unique($paths));
}

// First image of the hotel's first post that has one (used as a thumbnail on the home page).
function hotel_cover(int $id): string
{
    $st = db()->prepare('SELECT pi.path FROM post_images pi JOIN hotel_posts p ON p.id = pi.post_id WHERE p.hotel_id = ? ORDER BY p.sort_order, p.id, pi.sort_order, pi.id LIMIT 1');
    $st->execute([$id]);
    return (string)$st->fetchColumn();
}

/* ---------- request parsing ---------- */
function uploaded_list(string $key, $idx = null): array
{
    if (!isset($_FILES[$key]) || !is_array($_FILES[$key]['name'] ?? null)) {
        return [];
    }
    $f = $_FILES[$key];
    $names = $idx === null ? $f['name'] : ($f['name'][$idx] ?? []);
    if (!is_array($names)) {
        return [];
    }
    $out = [];
    foreach ($names as $j => $n) {
        $get = function ($field) use ($f, $idx, $j) {
            return $idx === null ? $f[$field][$j] : $f[$field][$idx][$j];
        };
        if ($get('error') === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $out[] = ['name' => $n, 'type' => $get('type'), 'tmp_name' => $get('tmp_name'), 'error' => $get('error'), 'size' => $get('size')];
    }
    return $out;
}

// Reads the submitted form against the hotel as it currently is ($base, or null for a new hotel).
// Returns ['fields' => [...], 'posts' => [...]] with image paths; new uploads are removed again on error.
function hotel_collect(?array $base): array
{
    $uploaded = [];
    try {
        $fields = [
            'name' => clean_text($_POST['name'] ?? '', 255),
            'location' => clean_text($_POST['location'] ?? '', 255),
            'summary' => clean_text($_POST['summary'] ?? '', 2000),
        ];
        if ($fields['name'] === '') {
            throw new RuntimeException('হোটেলের নাম দিন।');
        }
        $old = [];
        foreach ($base['posts'] ?? [] as $p) {
            $old[(int)$p['id']] = $p;
        }
        $posts = [];
        foreach ((array)($_POST['posts'] ?? []) as $i => $p) {
            if (!is_array($p)) {
                continue;
            }
            $pid = (int)($p['id'] ?? 0);
            $prev = $old[$pid] ?? null;
            $rm = array_map('strval', (array)($p['img_remove'] ?? []));
            $images = $prev ? array_values(array_diff($prev['images'], $rm)) : [];
            $files = uploaded_list('post_images', $i);
            $title = clean_text($p['title'] ?? '', 255);
            $body = clean_text($p['body'] ?? '', 4000);
            $price = clean_text($p['price'] ?? '', 80);
            if ($title === '' && $body === '' && $price === '' && !$files && !$images) {
                continue; // blank block
            }
            foreach ($files as $file) {
                if (count($images) >= MAX_POST_IMAGES) {
                    throw new RuntimeException('প্রতি পোস্টে সর্বোচ্চ ' . bn_digits(MAX_POST_IMAGES) . 'টি ছবি দেওয়া যাবে।');
                }
                $path = save_image_upload($file);
                if ($path) {
                    $uploaded[] = $path;
                    $images[] = $path;
                }
            }
            $posts[] = ['id' => $prev ? $pid : 0, 'title' => $title, 'body' => $body, 'price' => $price, 'images' => $images];
            if (count($posts) > MAX_POSTS) {
                throw new RuntimeException('সর্বোচ্চ ' . bn_digits(MAX_POSTS) . 'টি পোস্ট দেওয়া যাবে।');
            }
        }
        return ['fields' => $fields, 'posts' => $posts];
    } catch (Throwable $e) {
        delete_paths($uploaded);
        throw $e;
    }
}

/* ---------- writing ---------- */
// Saves fields + posts for an existing hotel and removes image files nothing references any more.
function hotel_save(int $id, array $data): void
{
    $pdo = db();
    $before = hotel_paths(hotel_load($id) ?? []);
    $pdo->beginTransaction();
    try {
        $f = $data['fields'];
        $pdo->prepare('UPDATE hotels SET name = ?, location = ?, summary = ? WHERE id = ?')->execute([$f['name'], $f['location'], $f['summary'], $id]);
        $own = $pdo->prepare('SELECT id FROM hotel_posts WHERE hotel_id = ?');
        $own->execute([$id]);
        $existing = array_map('intval', $own->fetchAll(PDO::FETCH_COLUMN));
        $keep = [];
        $img = $pdo->prepare('INSERT INTO post_images (post_id, path, sort_order) VALUES (?,?,?)');
        foreach (array_values($data['posts']) as $i => $p) {
            $pid = (int)$p['id'];
            if ($pid && in_array($pid, $existing, true)) {
                $pdo->prepare('UPDATE hotel_posts SET title = ?, body = ?, price = ?, sort_order = ? WHERE id = ? AND hotel_id = ?')
                    ->execute([$p['title'], $p['body'], $p['price'], $i, $pid, $id]);
            } else {
                $pdo->prepare('INSERT INTO hotel_posts (hotel_id, sort_order, title, body, price) VALUES (?,?,?,?,?)')
                    ->execute([$id, $i, $p['title'], $p['body'], $p['price']]);
                $pid = (int)$pdo->lastInsertId();
            }
            $keep[] = $pid;
            $pdo->prepare('DELETE FROM post_images WHERE post_id = ?')->execute([$pid]);
            foreach (array_values($p['images']) as $j => $path) {
                $img->execute([$pid, $path, $j]);
            }
        }
        foreach (array_diff($existing, $keep) as $gone) {
            $pdo->prepare('DELETE FROM post_images WHERE post_id = ?')->execute([$gone]);
            $pdo->prepare('DELETE FROM hotel_posts WHERE id = ?')->execute([$gone]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    $after = [];
    foreach ($data['posts'] as $p) {
        $after = array_merge($after, $p['images']);
    }
    delete_paths(array_diff($before, $after));
}

// Creates an empty hotel row and returns its id.
function hotel_create(string $name, ?int $ownerId, string $status): int
{
    $pdo = db();
    $order = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0)+1 FROM hotels')->fetchColumn();
    $pdo->prepare('INSERT INTO hotels (name, owner_id, status, active, sort_order) VALUES (?,?,?,1,?)')->execute([$name, $ownerId, $status, $order]);
    return (int)$pdo->lastInsertId();
}

function hotel_delete(int $id): void
{
    $pdo = db();
    $paths = hotel_paths(hotel_load($id) ?? []);
    $pdo->prepare('DELETE FROM post_images WHERE post_id IN (SELECT id FROM hotel_posts WHERE hotel_id = ?)')->execute([$id]);
    $pdo->prepare('DELETE FROM hotel_posts WHERE hotel_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM hotels WHERE id = ?')->execute([$id]);
    delete_paths($paths);
}
