<?php
require __DIR__ . '/_boot.php';
require __DIR__ . '/_layout.php';
require_once APP_ROOT . '/includes/hotels.php';
admin_require();
$pdo = db();
$labels = ['pending' => 'অপেক্ষায়', 'active' => 'সক্রিয়', 'suspended' => 'স্থগিত'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $act = (string)($_POST['act'] ?? '');
    if (!csrf_check()) {
        flash('সেশন মেয়াদোত্তীর্ণ।', 'err');
    } elseif (in_array($act, ['active', 'suspended'], true)) {
        $pdo->prepare('UPDATE owners SET status = ? WHERE id = ?')->execute([$act, $id]);
        if ($act === 'suspended') {
            // Suspended owners' hotels go offline until reactivated.
            $pdo->prepare("UPDATE hotels SET active = 0 WHERE owner_id = ?")->execute([$id]);
        } else {
            $pdo->prepare("UPDATE hotels SET active = 1 WHERE owner_id = ?")->execute([$id]);
        }
        flash('আপডেট হয়েছে।');
    } elseif ($act === 'delete') {
        $st = $pdo->prepare('SELECT id FROM hotels WHERE owner_id = ?');
        $st->execute([$id]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $hid) {
            hotel_delete((int)$hid);
        }
        $pdo->prepare('DELETE FROM owners WHERE id = ?')->execute([$id]);
        flash('মালিক ও তার সব হোটেল মুছে ফেলা হয়েছে।');
    }
    header('Location: owners.php');
    exit;
}

$rows = $pdo->query('SELECT o.*, (SELECT COUNT(*) FROM hotels h WHERE h.owner_id = o.id) AS hotel_count FROM owners o ORDER BY (o.status = \'pending\') DESC, o.id DESC')->fetchAll();
admin_head('হোটেল মালিক');
?>
<p class="hint">মালিকরা <code>/owner/register.php</code> থেকে রেজিস্টার করেন। অনুমোদন (সক্রিয়) না করলে লগইন করতে পারেন না।</p>
<div class="scroll"><table class="tbl">
  <tr><th>নাম</th><th>ইমেইল / ফোন</th><th>হোটেল</th><th>অবস্থা</th><th>অ্যাকশন</th></tr>
  <?php foreach ($rows as $o): ?>
  <tr>
    <td><?= e($o['name']) ?><br><small><?= e($o['created_at']) ?></small></td>
    <td><?= e($o['email']) ?><br><?= e($o['phone']) ?></td>
    <td><?= (int)$o['hotel_count'] ?></td>
    <td><?= e($labels[$o['status']] ?? $o['status']) ?></td>
    <td class="acts">
      <?php if ($o['status'] !== 'active'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$o['id'] ?>"><button class="sm" name="act" value="active">✔ সক্রিয় করুন</button></form><?php endif; ?>
      <?php if ($o['status'] === 'active'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$o['id'] ?>"><button class="sm" name="act" value="suspended">স্থগিত</button></form><?php endif; ?>
      <form method="post" onsubmit="return confirm('মালিক ও তার সব হোটেল মুছে যাবে। নিশ্চিত?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$o['id'] ?>"><button class="sm danger" name="act" value="delete">মুছুন</button></form>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="5">কোনো মালিক নেই।</td></tr><?php endif; ?>
</table></div>
<?php admin_foot();
