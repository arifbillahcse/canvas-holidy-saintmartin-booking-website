<?php
require __DIR__ . '/_boot.php';
require __DIR__ . '/_layout.php';
admin_require();
$statuses = ['new' => 'নতুন', 'contacted' => 'যোগাযোগ হয়েছে', 'confirmed' => 'কনফার্ম', 'cancelled' => 'বাতিল'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if (!csrf_check()) {
        flash('সেশন মেয়াদোত্তীর্ণ।', 'err');
    } elseif (($_POST['act'] ?? '') === 'status' && isset($statuses[$_POST['status'] ?? ''])) {
        db()->prepare('UPDATE bookings SET status = ? WHERE id = ?')->execute([$_POST['status'], $id]);
        flash('স্ট্যাটাস আপডেট হয়েছে।');
    } elseif (($_POST['act'] ?? '') === 'delete') {
        db()->prepare('DELETE FROM bookings WHERE id = ?')->execute([$id]);
        flash('মুছে ফেলা হয়েছে।');
    }
    header('Location: bookings.php' . (isset($_GET['s']) ? '?s=' . urlencode($_GET['s']) : ''));
    exit;
}

$filter = isset($statuses[$_GET['s'] ?? '']) ? $_GET['s'] : '';
$q = 'SELECT * FROM bookings' . ($filter ? ' WHERE status = ?' : '') . ' ORDER BY id DESC LIMIT 300';
$st = db()->prepare($q);
$st->execute($filter ? [$filter] : []);
$rows = $st->fetchAll();
admin_head('বুকিং ইনকোয়ারি');
?>
<p class="filters"><a href="bookings.php" class="<?= $filter ? '' : 'on' ?>">সব</a>
<?php foreach ($statuses as $k => $l): ?><a href="?s=<?= $k ?>" class="<?= $filter === $k ? 'on' : '' ?>"><?= e($l) ?></a><?php endforeach; ?></p>
<div class="scroll"><table class="tbl">
  <tr><th>সময়</th><th>নাম</th><th>মোবাইল</th><th>তারিখ</th><th>জন</th><th>ধরন</th><th>মেসেজ</th><th>স্ট্যাটাস</th><th></th></tr>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?= e($r['created_at']) ?></td>
    <td><?= e($r['name']) ?></td>
    <td><a href="tel:<?= e($r['phone']) ?>"><?= e($r['phone']) ?></a></td>
    <td><?= e($r['travel_date']) ?></td>
    <td><?= (int)$r['persons'] ?></td>
    <td><?= e($r['ticket_type']) ?></td>
    <td><?= e($r['message']) ?></td>
    <td>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="status"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <select name="status" onchange="this.form.submit()"><?php foreach ($statuses as $k => $l): ?><option value="<?= $k ?>" <?= $r['status'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></form>
    </td>
    <td><form method="post" onsubmit="return confirm('মুছে ফেলবেন?')"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="sm danger">মুছুন</button></form></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="9">কোনো বুকিং নেই।</td></tr><?php endif; ?>
</table></div>
<?php admin_foot();
