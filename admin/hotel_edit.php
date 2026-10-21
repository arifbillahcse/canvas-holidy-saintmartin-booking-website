<?php
require __DIR__ . '/_boot.php';
require __DIR__ . '/_layout.php';
require_once APP_ROOT . '/includes/hotel_form.php';
admin_require();
$id = (int)($_GET['id'] ?? 0);

$hotel = null;
if ($id) {
    $hotel = hotel_load($id);
    if (!$hotel) {
        http_response_code(404);
        exit('Not found');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = 'hotel_edit.php' . ($id ? "?id=$id" : '');
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
    if (!$id) {
        $id = hotel_create($data['fields']['name'], null, 'approved');
    }
    hotel_save($id, $data);
    flash('সংরক্ষিত হয়েছে।');
    header('Location: crud.php?t=hotels');
    exit;
}

admin_head($id ? 'হোটেল এডিট' : 'নতুন হোটেল');
?>
<form method="post" class="form" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <?php hotel_form_fields($hotel ?? [], '../'); ?>
  <div><button class="btn">সংরক্ষণ</button> <a href="crud.php?t=hotels" class="btn btn--ghost">বাতিল</a></div>
</form>
<?php admin_foot();
