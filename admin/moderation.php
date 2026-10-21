<?php
require __DIR__ . '/_boot.php';
require __DIR__ . '/_layout.php';
require_once APP_ROOT . '/includes/hotels.php';
admin_require();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $act = (string)($_POST['act'] ?? '');
    $reason = mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 250);
    if (!csrf_check()) {
        flash('সেশন মেয়াদোত্তীর্ণ।', 'err');
    } elseif ($act === 'approve') {
        $pdo->prepare("UPDATE hotels SET status = 'approved', reject_reason = '' WHERE id = ?")->execute([$id]);
        flash('অনুমোদিত হয়েছে এবং সাইটে লাইভ।');
    } elseif ($act === 'reject') {
        $pdo->prepare("UPDATE hotels SET status = 'rejected', reject_reason = ? WHERE id = ?")->execute([$reason !== '' ? $reason : 'অনুমোদন করা হয়নি।', $id]);
        flash('বাতিল করা হয়েছে; মালিক কারণটি দেখতে পাবেন।');
    }
    header('Location: moderation.php');
    exit;
}

$items = $pdo->query("SELECT h.*, o.name AS owner_name, o.email AS owner_email, o.phone AS owner_phone
    FROM hotels h LEFT JOIN owners o ON o.id = h.owner_id WHERE h.status = 'pending' ORDER BY h.id")->fetchAll();

admin_head('অনুমোদনের অপেক্ষায়');
if (!$items): ?><p>কোনো হোটেল অনুমোদনের অপেক্ষায় নেই। 🎉</p><?php endif;
foreach ($items as $row):
    $h = hotel_load((int)$row['id']); ?>
<section class="card-admin">
  <h2><?= e($h['name']) ?> <small>— নতুন হোটেল</small></h2>
  <p class="hint">মালিক: <?= e($row['owner_name']) ?> · <?= e($row['owner_email']) ?> · <?= e($row['owner_phone']) ?><?= $h['location'] !== '' ? ' · 📍 ' . e($h['location']) : '' ?></p>
  <?php if ($h['summary'] !== ''): ?><p><?= nl2br(e($h['summary'])) ?></p><?php endif; ?>
  <?php foreach ($h['posts'] as $p): ?>
    <div style="border-top:1px dashed #d3dde8;padding:.5rem 0">
      <b><?= e($p['title']) ?></b> <?= $p['price'] !== '' ? '— ' . e($p['price']) : '' ?><br>
      <?= nl2br(e($p['body'])) ?><br>
      <?php foreach ($p['images'] as $img): ?><img class="thumb" src="../<?= e($img) ?>" alt=""> <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
  <?php if (!$h['posts']): ?><p class="hint">(কোনো পোস্ট নেই)</p><?php endif; ?>
  <form method="post" class="acts" style="margin-top:.6rem">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
    <input name="reason" placeholder="বাতিলের কারণ (মালিক দেখতে পাবেন)" style="max-width:320px">
    <button class="btn" name="act" value="approve">✔ অনুমোদন</button>
    <button class="sm danger" name="act" value="reject">✘ বাতিল</button>
  </form>
</section>
<?php endforeach;
admin_foot();
