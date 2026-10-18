<?php
require __DIR__ . '/_boot.php';
require __DIR__ . '/_layout.php';
admin_require();
$pdo = db();
$fields = crud_defs()['hotels']['fields'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $act = (string)($_POST['act'] ?? '');
    $reason = mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 250);
    $st = $pdo->prepare('SELECT * FROM hotels WHERE id = ?');
    $st->execute([$id]);
    $h = $st->fetch();
    if (!csrf_check()) {
        flash('সেশন মেয়াদোত্তীর্ণ।', 'err');
    } elseif ($h) {
        $pd = json_decode((string)$h['pending_data'], true) ?: [];
        $isEdit = $h['status'] === 'approved' && $pd;
        if ($act === 'approve') {
            if ($isEdit) {
                $allowed = array_intersect_key($pd, array_flip(['image', 'name', 'location', 'stars', 'price', 'summary', 'amenities']));
                if (($allowed['image'] ?? $h['image']) !== $h['image'] && $h['image']) {
                    delete_upload($h['image']); // old live image replaced
                }
                $set = implode(', ', array_map(function ($c) { return "$c = ?"; }, array_keys($allowed)));
                $pdo->prepare("UPDATE hotels SET $set, pending_data = NULL, reject_reason = '' WHERE id = ?")
                    ->execute(array_merge(array_values($allowed), [$id]));
            } else {
                $pdo->prepare("UPDATE hotels SET status = 'approved', reject_reason = '' WHERE id = ?")->execute([$id]);
            }
            flash('অনুমোদিত হয়েছে এবং সাইটে লাইভ।');
        } elseif ($act === 'reject') {
            $reason = $reason !== '' ? $reason : 'অনুমোদন করা হয়নি।';
            if ($isEdit) {
                if (!empty($pd['image']) && $pd['image'] !== $h['image']) {
                    delete_upload($pd['image']);
                }
                $pdo->prepare('UPDATE hotels SET pending_data = NULL, reject_reason = ? WHERE id = ?')->execute([$reason, $id]);
            } else {
                $pdo->prepare("UPDATE hotels SET status = 'rejected', reject_reason = ? WHERE id = ?")->execute([$reason, $id]);
            }
            flash('বাতিল করা হয়েছে; মালিক কারণটি দেখতে পাবেন।');
        }
    }
    header('Location: moderation.php');
    exit;
}

$sql = "SELECT h.*, o.name AS owner_name, o.email AS owner_email, o.phone AS owner_phone
        FROM hotels h LEFT JOIN owners o ON o.id = h.owner_id
        WHERE h.status = 'pending' OR (h.status = 'approved' AND h.pending_data IS NOT NULL AND h.pending_data <> '')
        ORDER BY h.id";
$items = $pdo->query($sql)->fetchAll();

admin_head('অনুমোদনের অপেক্ষায়');
if (!$items): ?><p>কোনো হোটেল অনুমোদনের অপেক্ষায় নেই। 🎉</p><?php endif;
foreach ($items as $h):
    $pd = json_decode((string)$h['pending_data'], true) ?: [];
    $isEdit = $h['status'] === 'approved' && $pd;
    $new = $isEdit ? array_merge($h, $pd) : $h;
    ?>
<section class="card-admin">
  <h2><?= e($new['name']) ?> <small><?= $isEdit ? '— এডিট অনুমোদন' : '— নতুন হোটেল' ?></small></h2>
  <p class="hint">মালিক: <?= e($h['owner_name']) ?> · <?= e($h['owner_email']) ?> · <?= e($h['owner_phone']) ?></p>
  <table class="tbl sys">
    <?php foreach ($fields as $col => $f):
        if (!in_array($col, ['image', 'name', 'location', 'stars', 'price', 'summary', 'amenities'], true)) continue;
        $old = (string)$h[$col]; $nv = (string)$new[$col];
        if ($isEdit && $old === $nv) continue; ?>
    <tr><th><?= e($f[0]) ?></th><td>
      <?php if ($col === 'image'): ?>
        <?php if ($isEdit && $old): ?><img class="thumb" src="../<?= e($old) ?>" alt=""> →<?php endif; ?>
        <?php if ($nv): ?><img class="thumb" src="../<?= e($nv) ?>" alt=""><?php else: ?>(ছবি নেই)<?php endif; ?>
      <?php elseif ($isEdit): ?>
        <del><?= nl2br(e($old)) ?></del> → <ins><?= nl2br(e($nv)) ?></ins>
      <?php else: ?><?= nl2br(e($nv)) ?><?php endif; ?>
    </td></tr>
    <?php endforeach; ?>
  </table>
  <form method="post" class="acts" style="margin-top:.6rem">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
    <input name="reason" placeholder="বাতিলের কারণ (মালিক দেখতে পাবেন)" style="max-width:320px">
    <button class="btn" name="act" value="approve">✔ অনুমোদন</button>
    <button class="sm danger" name="act" value="reject">✘ বাতিল</button>
  </form>
</section>
<?php endforeach;
admin_foot();
