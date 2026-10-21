<?php
// Hotel data model shared by the public page, owner panel and admin.
// A hotel is edited as a "snapshot" array:
//   ['fields' => [...], 'gallery' => [paths], 'rooms' => [['id','type','name','price',...,'images'=>[paths]]]]
// Owners' edits to a live hotel are staged as a snapshot in hotels.pending_data until an admin approves.
require_once __DIR__ . '/helpers.php';

const ROOM_TYPES = [
    'single' => ['Single Bed', '🛏️'],
    'couple' => ['Couple Bed', '💑'],
    'family' => ['Family Room', '👨‍👩‍👧‍👦'],
    'other'  => ['Other', '🏨'],
];
const MAX_GALLERY = 12;
const MAX_ROOMS = 12;
const MAX_ROOM_IMAGES = 6;

function room_label(array $r): string
{
    if (($r['type'] ?? 'other') === 'other' || trim((string)($r['name'] ?? '')) !== '') {
        $n = trim((string)($r['name'] ?? ''));
        if ($n !== '') {
            return $n;
        }
    }
    return ROOM_TYPES[$r['type'] ?? 'other'][0] ?? 'Room';
}

function room_icon(array $r): string
{
    return ROOM_TYPES[$r['type'] ?? 'other'][1] ?? '🏨';
}

function normalize_price($v): int
{
    $v = strtr((string)$v, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']);
    return min(10000000, (int)preg_replace('/\D+/', '', $v));
}

function fmt_price(int $n): string
{
    return bn_digits(number_format($n));
}

/* ---------- reading ---------- */
const HOTEL_FIELD_KEYS = ['name', 'location', 'stars', 'summary', 'amenities', 'rating', 'badge', 'featured'];

function hotel_snapshot(int $id): array
{
    $pdo = db();
    $st = $pdo->prepare('SELECT * FROM hotels WHERE id = ?');
    $st->execute([$id]);
    $h = $st->fetch();
    if (!$h) {
        return ['fields' => [], 'gallery' => [], 'rooms' => []];
    }
    $fields = array_intersect_key($h, array_flip(HOTEL_FIELD_KEYS));
    $g = $pdo->prepare('SELECT path FROM hotel_images WHERE hotel_id = ? ORDER BY sort_order, id');
    $g->execute([$id]);
    $gallery = $g->fetchAll(PDO::FETCH_COLUMN);
    if (!$gallery && $h['image'] !== '') {
        $gallery = [$h['image']]; // hotels created before galleries existed
    }
    $rs = $pdo->prepare('SELECT * FROM rooms WHERE hotel_id = ? ORDER BY sort_order, id');
    $rs->execute([$id]);
    $rooms = [];
    $im = $pdo->prepare('SELECT path FROM room_images WHERE room_id = ? ORDER BY sort_order, id');
    foreach ($rs->fetchAll() as $r) {
        $im->execute([$r['id']]);
        $r['images'] = $im->fetchAll(PDO::FETCH_COLUMN);
        $rooms[] = $r;
    }
    return ['fields' => $fields, 'gallery' => $gallery, 'rooms' => $rooms];
}

// Accepts the older flat pending_data format (fields only) as well as full snapshots.
function snapshot_from_json($json, int $hotelId): array
{
    $d = json_decode((string)$json, true);
    if (!is_array($d) || !$d) {
        return [];
    }
    if (isset($d['fields'])) {
        return $d + ['gallery' => [], 'rooms' => []];
    }
    $live = hotel_snapshot($hotelId);
    $fields = array_intersect_key($d, array_flip(['name', 'location', 'stars', 'summary', 'amenities']));
    $gallery = $live['gallery'];
    if (array_key_exists('image', $d)) {
        $gallery = $d['image'] !== '' ? [$d['image']] : [];
    }
    return ['fields' => $fields + $live['fields'], 'gallery' => $gallery, 'rooms' => $live['rooms']];
}

function snapshot_paths(array $snap): array
{
    $p = $snap['gallery'] ?? [];
    foreach ($snap['rooms'] ?? [] as $r) {
        $p = array_merge($p, $r['images'] ?? []);
    }
    return array_values(array_unique(array_filter($p)));
}

function delete_paths(array $paths): void
{
    foreach ($paths as $p) {
        delete_upload($p);
    }
}

/* ---------- writing ---------- */
// Writes a snapshot to the live tables and removes image files it no longer references.
function hotel_apply(int $id, array $snap): void
{
    $pdo = db();
    $before = snapshot_paths(hotel_snapshot($id));
    $f = $snap['fields'] ?? [];
    $gallery = array_values($snap['gallery'] ?? []);
    $pdo->beginTransaction();
    try {
        $cols = array_intersect_key($f, array_flip(HOTEL_FIELD_KEYS));
        $cols['image'] = $gallery[0] ?? '';
        $set = implode(', ', array_map(function ($c) { return "$c = ?"; }, array_keys($cols)));
        $pdo->prepare("UPDATE hotels SET $set WHERE id = ?")->execute(array_merge(array_values($cols), [$id]));

        $pdo->prepare('DELETE FROM hotel_images WHERE hotel_id = ?')->execute([$id]);
        $ins = $pdo->prepare('INSERT INTO hotel_images (hotel_id, path, sort_order) VALUES (?,?,?)');
        foreach ($gallery as $i => $path) {
            $ins->execute([$id, $path, $i]);
        }

        $own = $pdo->prepare('SELECT id FROM rooms WHERE hotel_id = ?');
        $own->execute([$id]);
        $existing = array_map('intval', $own->fetchAll(PDO::FETCH_COLUMN));
        $keep = [];
        foreach (array_values($snap['rooms'] ?? []) as $i => $r) {
            $vals = [$r['type'], $r['name'], (int)$r['price'], $r['persons'], $r['beds'], (int)$r['ac'], (int)$r['breakfast'], $r['description'], $i];
            $rid = (int)($r['id'] ?? 0);
            if ($rid && in_array($rid, $existing, true)) {
                $pdo->prepare('UPDATE rooms SET type=?, name=?, price=?, persons=?, beds=?, ac=?, breakfast=?, description=?, sort_order=? WHERE id=? AND hotel_id=?')
                    ->execute(array_merge($vals, [$rid, $id]));
            } else {
                $pdo->prepare('INSERT INTO rooms (type, name, price, persons, beds, ac, breakfast, description, sort_order, hotel_id) VALUES (?,?,?,?,?,?,?,?,?,?)')
                    ->execute(array_merge($vals, [$id]));
                $rid = (int)$pdo->lastInsertId();
            }
            $keep[] = $rid;
            $pdo->prepare('DELETE FROM room_images WHERE room_id = ?')->execute([$rid]);
            $ri = $pdo->prepare('INSERT INTO room_images (room_id, path, sort_order) VALUES (?,?,?)');
            foreach (array_values($r['images'] ?? []) as $j => $path) {
                $ri->execute([$rid, $path, $j]);
            }
        }
        foreach (array_diff($existing, $keep) as $gone) {
            $pdo->prepare('DELETE FROM room_images WHERE room_id = ?')->execute([$gone]);
            $pdo->prepare('DELETE FROM rooms WHERE id = ?')->execute([$gone]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    delete_paths(array_diff($before, snapshot_paths($snap)));
}

function hotel_delete(int $id): void
{
    $pdo = db();
    $st = $pdo->prepare('SELECT pending_data FROM hotels WHERE id = ?');
    $st->execute([$id]);
    $pd = snapshot_from_json($st->fetchColumn(), $id);
    $paths = array_merge(snapshot_paths(hotel_snapshot($id)), snapshot_paths($pd));
    $pdo->prepare('DELETE FROM room_images WHERE room_id IN (SELECT id FROM rooms WHERE hotel_id = ?)')->execute([$id]);
    $pdo->prepare('DELETE FROM rooms WHERE hotel_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM hotel_images WHERE hotel_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM hotels WHERE id = ?')->execute([$id]);
    delete_paths(array_unique($paths));
}

/* ---------- request -> snapshot ---------- */
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

function clean_text($v, int $max): string
{
    return mb_substr(trim((string)$v), 0, $max);
}

// Builds the new snapshot from the submitted form on top of $base (the snapshot being edited).
// Newly uploaded files are deleted again if validation fails. Throws RuntimeException with a user message.
function hotel_collect(array $base, bool $admin): array
{
    $uploaded = [];
    try {
        $f = [
            'name' => clean_text($_POST['name'] ?? '', 255),
            'location' => clean_text($_POST['location'] ?? '', 255),
            'stars' => max(1, min(5, (int)($_POST['stars'] ?? 3))),
            'summary' => clean_text($_POST['summary'] ?? '', 2000),
            'amenities' => clean_text($_POST['amenities'] ?? '', 2000),
        ];
        if ($f['name'] === '') {
            throw new RuntimeException('হোটেলের নাম দিন।');
        }
        if ($admin) {
            $f['rating'] = clean_text($_POST['rating'] ?? '', 40);
            $f['badge'] = clean_text($_POST['badge'] ?? '', 60);
            $f['featured'] = empty($_POST['featured']) ? 0 : 1;
        } else {
            $f += array_intersect_key($base['fields'] ?? [], array_flip(['rating', 'badge', 'featured']));
        }

        // gallery
        $remove = array_map('strval', (array)($_POST['gallery_remove'] ?? []));
        $gallery = array_values(array_diff($base['gallery'] ?? [], $remove));
        foreach (uploaded_list('gallery') as $file) {
            if (count($gallery) >= MAX_GALLERY) {
                throw new RuntimeException('গ্যালারিতে সর্বোচ্চ ' . bn_digits(MAX_GALLERY) . 'টি ছবি রাখা যাবে।');
            }
            $path = save_image_upload($file);
            if ($path) {
                $uploaded[] = $path;
                $gallery[] = $path;
            }
        }

        // rooms
        $baseRooms = [];
        foreach ($base['rooms'] ?? [] as $r) {
            $baseRooms[(int)$r['id']] = $r;
        }
        $rooms = [];
        foreach ((array)($_POST['rooms'] ?? []) as $i => $r) {
            if (!is_array($r)) {
                continue;
            }
            $rid = (int)($r['id'] ?? 0);
            $old = $baseRooms[$rid] ?? null;
            $type = isset(ROOM_TYPES[$r['type'] ?? '']) ? $r['type'] : 'other';
            $name = clean_text($r['name'] ?? '', 120);
            $price = normalize_price($r['price'] ?? '');
            $rm = array_map('strval', (array)($r['img_remove'] ?? []));
            $images = $old ? array_values(array_diff($old['images'] ?? [], $rm)) : [];
            $files = uploaded_list('room_images', $i);
            if ($name === '' && $price === 0 && !$files && !$images && clean_text($r['description'] ?? '', 5) === '') {
                continue; // blank row
            }
            if ($type === 'other' && $name === '') {
                throw new RuntimeException('"Other" রুমের একটি নাম দিন (যেমন: Budget Room)।');
            }
            foreach ($files as $file) {
                if (count($images) >= MAX_ROOM_IMAGES) {
                    throw new RuntimeException('প্রতি রুমে সর্বোচ্চ ' . bn_digits(MAX_ROOM_IMAGES) . 'টি ছবি রাখা যাবে।');
                }
                $path = save_image_upload($file);
                if ($path) {
                    $uploaded[] = $path;
                    $images[] = $path;
                }
            }
            $rooms[] = [
                'id' => $old ? $rid : 0, 'type' => $type, 'name' => $name, 'price' => $price,
                'persons' => clean_text($r['persons'] ?? '', 60), 'beds' => clean_text($r['beds'] ?? '', 120),
                'ac' => ($r['ac'] ?? '1') === '0' ? 0 : 1, 'breakfast' => empty($r['breakfast']) ? 0 : 1,
                'description' => clean_text($r['description'] ?? '', 1000), 'images' => $images,
            ];
            if (count($rooms) > MAX_ROOMS) {
                throw new RuntimeException('সর্বোচ্চ ' . bn_digits(MAX_ROOMS) . 'টি রুম যোগ করা যাবে।');
            }
        }
        return ['fields' => $f, 'gallery' => $gallery, 'rooms' => $rooms];
    } catch (Throwable $e) {
        delete_paths($uploaded);
        throw $e;
    }
}

/* ---------- public helpers ---------- */
function hotel_min_prices(): array
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (db()->query('SELECT hotel_id, MIN(price) AS m FROM rooms WHERE price > 0 GROUP BY hotel_id') as $r) {
            $map[(int)$r['hotel_id']] = (int)$r['m'];
        }
    }
    return $map;
}

function amenity_icon(string $t): string
{
    $l = mb_strtolower($t);
    $map = [['wifi', '📶'], ['ওয়াই', '📶'], ['এসি', '❄️'], ['ac', '❄️'], ['নাস্তা', '🍳'], ['breakfast', '🍳'], ['সী', '🌊'], ['sea', '🌊'],
        ['জেনারেটর', '⚡'], ['generator', '⚡'], ['পানি', '🚿'], ['water', '🚿'], ['নিরাপত্তা', '🛡️'], ['security', '🛡️'],
        ['রেস্টুরেন্ট', '🍽️'], ['restaurant', '🍽️'], ['পার্কিং', '🅿️'], ['parking', '🅿️'], ['বিচ', '🏖️'], ['beach', '🏖️'], ['টিভি', '📺'], ['tv', '📺']];
    foreach ($map as [$k, $i]) {
        if (mb_strpos($l, $k) !== false) {
            return $i;
        }
    }
    return '✔';
}
