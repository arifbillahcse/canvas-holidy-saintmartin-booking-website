<?php
require __DIR__ . '/_boot.php';
require __DIR__ . '/_layout.php';
require_once APP_ROOT . '/includes/hotel_form.php';
$owner = owner_require();
$id = (int)($_GET['id'] ?? 0);

$hotel = null;
if ($id) {
    $hotel = hotel_load($id);
    if (!$hotel || (int)$hotel['owner_id'] !== (int)$owner['id']) { // ownership enforced here
        http_response_code(404);
        exit('Not found');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = 'hotel.php' . ($id ? "?id=$id" : '');
    if (!csrf_check()) {
        flash('সেশন মেয়াদোত্তীর্ণ।', 'err');
        header("Location: $back");
        exit;
    }
    try {
        $data = hotel_collect($hotel);
    } catch (RuntimeException $ex) {
        flash($ex->getMessage(), 'err');
        header("Location: $back");
        exit;
    }
    if (!$hotel) {
        // A new hotel waits for admin approval before it appears on the site.
        $id = hotel_create($data['fields']['name'], (int)$owner['id'], 'pending');
        hotel_save($id, $data);
        flash('হোটেল জমা হয়েছে। অ্যাডমিন অনুমোদন করলে সাইটে দেখা যাবে।');
    } else {
        hotel_save($id, $data);
        if ($hotel['status'] === 'rejected') {
            db()->prepare("UPDATE hotels SET status = 'pending', reject_reason = '' WHERE id = ? AND owner_id = ?")->execute([$id, $owner['id']]);
            flash('হোটেল আবার অনুমোদনের জন্য জমা হয়েছে।');
        } else {
            flash('সংরক্ষিত হয়েছে।');
        }
    }
    header('Location: index.php');
    exit;
}

owner_head($id ? 'হোটেল এডিট' : 'নতুন হোটেল', $owner);
?>
<form method="post" class="form" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <?php hotel_form_fields($hotel ?? [], '../'); ?>
  <div><button class="btn">সংরক্ষণ করুন</button> <a href="index.php" class="btn btn--ghost">বাতিল</a></div>
</form>
<?php owner_foot($owner);
