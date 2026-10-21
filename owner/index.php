<?php
require __DIR__ . '/_boot.php';
require __DIR__ . '/_layout.php';
require_once APP_ROOT . '/includes/hotels.php';
$owner = owner_require();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if (csrf_check()) {
        $st = db()->prepare('SELECT * FROM hotels WHERE id = ? AND owner_id = ?');
        $st->execute([$id, $owner['id']]);
        if ($h = $st->fetch()) {
            hotel_delete((int)$h['id']);
            flash('হোটেল মুছে ফেলা হয়েছে।');
        }
    }
    header('Location: index.php');
    exit;
}

$st = db()->prepare('SELECT * FROM hotels WHERE owner_id = ? ORDER BY id DESC');
$st->execute([$owner['id']]);
$rows = $st->fetchAll();
$label = ['pending' => 'অনুমোদনের অপেক্ষায়', 'approved' => 'লাইভ', 'rejected' => 'বাতিল'];

owner_head('আমার হোটেল', $owner);
?>
<p><a class="btn" href="hotel.php">+ নতুন হোটেল যোগ করুন</a></p>
<p class="hint">নতুন হোটেল অ্যাডমিন অনুমোদন করলে সাইটে দেখা যাবে। অনুমোদিত হোটেলে নতুন পোস্ট যোগ বা এডিট করলে সাথে সাথে সাইটে দেখাবে।</p>
<table class="tbl">
  <tr><th>হোটেল</th><th>অবস্থা</th><th>অ্যাকশন</th></tr>
  <?php foreach ($rows as $h): ?>
  <tr>
    <td><strong><?= e($h['name']) ?></strong><br><small><?= e($h['location']) ?></small>
      <?php if ($h['reject_reason'] !== ''): ?><br><small style="color:#9a1c0e">⚠ অ্যাডমিনের মন্তব্য: <?= e($h['reject_reason']) ?></small><?php endif; ?></td>
    <td><?= e($label[$h['status']] ?? $h['status']) ?></td>
    <td class="acts">
      <a class="sm" href="hotel.php?id=<?= (int)$h['id'] ?>">এডিট</a>
      <form method="post" onsubmit="return confirm('হোটেলটি মুছে ফেলবেন?')"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int)$h['id'] ?>"><button class="sm danger">মুছুন</button></form>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="3">এখনো কোনো হোটেল যোগ করেননি।</td></tr><?php endif; ?>
</table>
<?php owner_foot($owner);
