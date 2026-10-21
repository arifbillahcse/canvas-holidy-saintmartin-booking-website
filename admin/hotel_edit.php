<?php
require __DIR__ . '/_boot.php';
require __DIR__ . '/_layout.php';
require_once APP_ROOT . '/includes/hotel_form.php';
admin_require();
$id = (int)($_GET['id'] ?? 0);
$pdo = db();

$hotel = [];
if ($id) {
    $st = $pdo->prepare('SELECT * FROM hotels WHERE id = ?');
    $st->execute([$id]);
    $hotel = $st->fetch();
    if (!$hotel) {
        http_response_code(404);
        exit('Not found');
    }
}
$snap = $id ? hotel_snapshot($id) : ['fields' => ['stars' => 3], 'gallery' => [], 'rooms' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = 'hotel_edit.php' . ($id ? "?id=$id" : '');
    if (!csrf_check()) {
        flash('সেশন মেয়াদোত্তীর্ণ।', 'err');
        header("Location: $back");
        exit;
    }
    try {
        $new = hotel_collect($snap, true); // admin edits go live immediately
    } catch (RuntimeException $ex) {
        flash($ex->getMessage(), 'err');
        header("Location: $back");
        exit;
    }
    if (!$id) {
        $order = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0)+1 FROM hotels')->fetchColumn();
        $pdo->prepare("INSERT INTO hotels (name, status, active, sort_order) VALUES (?, 'approved', 1, ?)")->execute([$new['fields']['name'], $order]);
        $id = (int)$pdo->lastInsertId();
    }
    hotel_apply($id, $new);
    flash('সংরক্ষিত হয়েছে।');
    header('Location: crud.php?t=hotels');
    exit;
}

admin_head($id ? 'হোটেল এডিট' : 'নতুন হোটেল');
if ($hotel && trim((string)$hotel['pending_data']) !== ''): ?><p class="msg msg--err">এই হোটেলের মালিকের একটি পরিবর্তন অনুমোদনের অপেক্ষায় আছে (অনুমোদন পেজে দেখুন)। এখানে সেভ করলে সরাসরি লাইভ ডাটা বদলাবে।</p><?php endif; ?>
<form method="post" class="form" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <?php hotel_form_fields($snap, true, '../'); ?>
  <div><button class="btn">সংরক্ষণ</button> <a href="crud.php?t=hotels" class="btn btn--ghost">বাতিল</a></div>
</form>
<?php admin_foot();
