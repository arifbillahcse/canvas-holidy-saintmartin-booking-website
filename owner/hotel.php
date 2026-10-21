<?php
require __DIR__ . '/_boot.php';
require __DIR__ . '/_layout.php';
require_once APP_ROOT . '/includes/hotel_form.php';
$owner = owner_require();
$id = (int)($_GET['id'] ?? 0);
$pdo = db();

$hotel = [];
if ($id) {
    $st = $pdo->prepare('SELECT * FROM hotels WHERE id = ? AND owner_id = ?'); // ownership enforced here
    $st->execute([$id, $owner['id']]);
    $hotel = $st->fetch();
    if (!$hotel) {
        http_response_code(404);
        exit('Not found');
    }
}
$live = $id ? hotel_snapshot($id) : ['fields' => [], 'gallery' => [], 'rooms' => []];
$pending = $hotel ? snapshot_from_json($hotel['pending_data'], $id) : [];
$base = $pending ?: $live; // the owner keeps editing their staged version if there is one

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = 'hotel.php' . ($id ? "?id=$id" : '');
    if (!csrf_check()) {
        flash('সেশন মেয়াদোত্তীর্ণ।', 'err');
        header("Location: $back");
        exit;
    }
    try {
        $snap = hotel_collect($base, false);
    } catch (RuntimeException $ex) {
        flash($ex->getMessage(), 'err');
        header("Location: $back");
        exit;
    }
    if (!$hotel) {
        $order = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0)+1 FROM hotels')->fetchColumn();
        $pdo->prepare("INSERT INTO hotels (name, owner_id, status, active, sort_order) VALUES (?, ?, 'pending', 1, ?)")
            ->execute([$snap['fields']['name'], $owner['id'], $order]);
        hotel_apply((int)$pdo->lastInsertId(), $snap);
        flash('হোটেল জমা হয়েছে। অ্যাডমিন অনুমোদন করলে সাইটে দেখা যাবে।');
    } elseif ($hotel['status'] === 'approved') {
        // Live hotel: stage the whole edit; live data stays untouched until the admin approves.
        $pdo->prepare("UPDATE hotels SET pending_data = ?, reject_reason = '' WHERE id = ? AND owner_id = ?")
            ->execute([json_encode($snap, JSON_UNESCAPED_UNICODE), $id, $owner['id']]);
        // Files only the previous staged version used (and neither live nor the new one) are now orphans.
        delete_paths(array_diff(snapshot_paths($pending), snapshot_paths($live), snapshot_paths($snap)));
        flash('পরিবর্তন জমা হয়েছে। অ্যাডমিন অনুমোদন না করা পর্যন্ত সাইটে আগের তথ্য দেখাবে।');
    } else {
        hotel_apply($id, $snap);
        $pdo->prepare("UPDATE hotels SET status = 'pending', reject_reason = '' WHERE id = ? AND owner_id = ?")->execute([$id, $owner['id']]);
        flash('হোটেল আবার অনুমোদনের জন্য জমা হয়েছে।');
    }
    header('Location: index.php');
    exit;
}

$base['fields'] = ($base['fields'] ?? []) + ['stars' => 3];
owner_head($id ? 'হোটেল এডিট' : 'নতুন হোটেল', $owner);
if ($pending): ?><p class="msg msg--ok">আপনার একটি পরিবর্তন অনুমোদনের অপেক্ষায় আছে। নিচে সেই পরিবর্তনগুলো দেখানো হচ্ছে।</p><?php endif; ?>
<form method="post" class="form" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <?php hotel_form_fields($base, false, '../'); ?>
  <div><button class="btn">জমা দিন</button> <a href="index.php" class="btn btn--ghost">বাতিল</a></div>
</form>
<?php owner_foot($owner);
