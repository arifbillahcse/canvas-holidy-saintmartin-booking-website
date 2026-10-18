<?php
require __DIR__ . '/_boot.php';
require __DIR__ . '/_layout.php';
$owner = owner_require();
$fields = owner_fields();
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
$pending = $hotel ? (json_decode((string)$hotel['pending_data'], true) ?: []) : [];
$shown = $pending ? array_merge($hotel, $pending) : $hotel; // show the owner their proposed edit

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = 'hotel.php' . ($id ? "?id=$id" : '');
    if (!csrf_check()) {
        flash('সেশন মেয়াদোত্তীর্ণ।', 'err');
        header("Location: $back");
        exit;
    }
    $vals = [];
    foreach ($fields as $col => $f) {
        if ($f[1] === 'image') {
            continue;
        }
        if ($f[1] === 'number') {
            $vals[$col] = max(1, min(5, (int)($_POST[$col] ?? 3)));
        } else {
            $vals[$col] = mb_substr(trim((string)($_POST[$col] ?? '')), 0, $f[1] === 'textarea' ? 2000 : 255);
        }
    }
    if ($vals['name'] === '') {
        flash('হোটেলের নাম দিন।', 'err');
        header("Location: $back");
        exit;
    }
    // Image: keep the current (shown) one unless replaced or removed.
    $curImg = (string)($shown['image'] ?? '');
    try {
        $new = save_image_upload($_FILES['image'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
    } catch (RuntimeException $ex) {
        flash($ex->getMessage(), 'err');
        header("Location: $back");
        exit;
    }
    $img = $curImg;
    if ($new) {
        $img = $new;
    } elseif (!empty($_POST['remove_image'])) {
        $img = '';
    }
    $vals['image'] = $img;
    // Free the previous image only if it is not the live one.
    $live = (string)($hotel['image'] ?? '');
    if ($img !== $curImg && $curImg !== '' && $curImg !== $live) {
        delete_upload($curImg);
    }

    if (!$hotel) {
        $cols = array_merge(array_keys($vals), ['owner_id', 'status', 'active', 'sort_order']);
        $order = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0)+1 FROM hotels')->fetchColumn();
        $pdo->prepare('INSERT INTO hotels (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')
            ->execute(array_merge(array_values($vals), [$owner['id'], 'pending', 1, $order]));
        flash('হোটেল জমা হয়েছে। অ্যাডমিন অনুমোদন করলে সাইটে দেখা যাবে।');
    } elseif ($hotel['status'] === 'approved') {
        // Live hotel: stage the edit; the live data stays untouched until the admin approves.
        $pdo->prepare("UPDATE hotels SET pending_data = ?, reject_reason = '' WHERE id = ? AND owner_id = ?")
            ->execute([json_encode($vals, JSON_UNESCAPED_UNICODE), $id, $owner['id']]);
        flash('পরিবর্তন জমা হয়েছে। অ্যাডমিন অনুমোদন না করা পর্যন্ত সাইটে আগের তথ্য দেখাবে।');
    } else {
        $set = implode(', ', array_map(function ($c) { return "$c = ?"; }, array_keys($vals)));
        $pdo->prepare("UPDATE hotels SET $set, status = 'pending', reject_reason = '' WHERE id = ? AND owner_id = ?")
            ->execute(array_merge(array_values($vals), [$id, $owner['id']]));
        flash('হোটেল আবার অনুমোদনের জন্য জমা হয়েছে।');
    }
    header('Location: index.php');
    exit;
}

owner_head($id ? 'হোটেল এডিট' : 'নতুন হোটেল', $owner);
if ($pending): ?><p class="msg msg--ok">আপনার একটি পরিবর্তন অনুমোদনের অপেক্ষায় আছে। নিচে সেই পরিবর্তনগুলো দেখানো হচ্ছে।</p><?php endif; ?>
<form method="post" class="form" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <?php foreach ($fields as $col => $f): $val = $shown[$col] ?? ''; ?>
    <?php if ($f[1] === 'image'): ?>
      <label><?= e($f[0]) ?>
        <?php if ($val): ?><span class="preview"><img src="../<?= e($val) ?>" alt=""> <span class="chk"><input type="checkbox" name="remove_image" value="1"> মুছে ফেলুন</span></span><?php endif; ?>
        <input type="file" name="image" accept="image/*">
      </label>
    <?php elseif ($f[1] === 'number'): ?>
      <label><?= e($f[0]) ?><input type="number" min="1" max="5" name="<?= e($col) ?>" value="<?= e($val !== '' ? $val : 3) ?>"></label>
    <?php elseif ($f[1] === 'textarea'): ?>
      <label><?= e($f[0]) ?><textarea name="<?= e($col) ?>" rows="4"><?= e($val) ?></textarea></label>
    <?php else: ?>
      <label><?= e($f[0]) ?><input name="<?= e($col) ?>" value="<?= e($val) ?>" <?= !empty($f[2]) ? 'required' : '' ?>></label>
    <?php endif; ?>
  <?php endforeach; ?>
  <div><button class="btn">জমা দিন</button> <a href="index.php" class="btn btn--ghost">বাতিল</a></div>
</form>
<?php owner_foot($owner);
