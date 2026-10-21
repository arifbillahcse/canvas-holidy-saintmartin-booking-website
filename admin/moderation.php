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
    $st = $pdo->prepare('SELECT * FROM hotels WHERE id = ?');
    $st->execute([$id]);
    $h = $st->fetch();
    if (!csrf_check()) {
        flash('সেশন মেয়াদোত্তীর্ণ।', 'err');
    } elseif ($h) {
        $pd = snapshot_from_json($h['pending_data'], $id);
        $isEdit = $h['status'] === 'approved' && $pd;
        if ($act === 'approve') {
            if ($isEdit) {
                hotel_apply($id, $pd);
                $pdo->prepare("UPDATE hotels SET pending_data = NULL, reject_reason = '' WHERE id = ?")->execute([$id]);
            } else {
                $pdo->prepare("UPDATE hotels SET status = 'approved', reject_reason = '' WHERE id = ?")->execute([$id]);
            }
            flash('অনুমোদিত হয়েছে এবং সাইটে লাইভ।');
        } elseif ($act === 'reject') {
            $reason = $reason !== '' ? $reason : 'অনুমোদন করা হয়নি।';
            if ($isEdit) {
                delete_paths(array_diff(snapshot_paths($pd), snapshot_paths(hotel_snapshot($id))));
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
$labels = ['name' => 'নাম', 'location' => 'লোকেশন', 'stars' => 'স্টার', 'summary' => 'বিবরণ', 'amenities' => 'সুযোগ-সুবিধা'];

function room_sig(array $r): string
{
    return json_encode([$r['type'], $r['name'], (int)$r['price'], $r['persons'], $r['beds'], (int)$r['ac'], (int)$r['breakfast'], $r['description'], array_values($r['images'])]);
}

function room_line(array $r): string
{
    return room_label($r) . ' — ৳' . $r['price'] . ' · ' . $r['persons'] . ' · ' . $r['beds'] . ' · ' . ($r['ac'] ? 'এসি' : 'নন-এসি') . ' · ছবি ' . count($r['images']);
}

admin_head('অনুমোদনের অপেক্ষায়');
if (!$items): ?><p>কোনো হোটেল অনুমোদনের অপেক্ষায় নেই। 🎉</p><?php endif;
foreach ($items as $h):
    $pd = snapshot_from_json($h['pending_data'], (int)$h['id']);
    $isEdit = $h['status'] === 'approved' && $pd;
    $live = hotel_snapshot((int)$h['id']);
    $new = $isEdit ? $pd : $live;
    $liveRooms = [];
    foreach ($live['rooms'] as $r) {
        $liveRooms[(int)$r['id']] = $r;
    }
    ?>
<section class="card-admin">
  <h2><?= e($new['fields']['name'] ?? $h['name']) ?> <small><?= $isEdit ? '— এডিট অনুমোদন' : '— নতুন হোটেল' ?></small></h2>
  <p class="hint">মালিক: <?= e($h['owner_name']) ?> · <?= e($h['owner_email']) ?> · <?= e($h['owner_phone']) ?></p>
  <table class="tbl sys">
    <?php foreach ($labels as $col => $lbl):
        $old = (string)($live['fields'][$col] ?? ''); $nv = (string)($new['fields'][$col] ?? '');
        if ($isEdit && $old === $nv) continue; ?>
    <tr><th><?= e($lbl) ?></th><td><?php if ($isEdit): ?><del><?= nl2br(e($old)) ?></del> → <ins><?= nl2br(e($nv)) ?></ins><?php else: ?><?= nl2br(e($nv)) ?><?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$isEdit || $live['gallery'] !== $new['gallery']): ?>
    <tr><th>গ্যালারি ছবি</th><td><?php foreach ($new['gallery'] as $img): ?><img class="thumb" src="../<?= e($img) ?>" alt=""> <?php endforeach; ?><?= $new['gallery'] ? '' : '(ছবি নেই)' ?>
      <?php if ($isEdit): ?><br><small>আগে ছিল <?= count($live['gallery']) ?>টি, এখন <?= count($new['gallery']) ?>টি</small><?php endif; ?></td></tr>
    <?php endif; ?>
    <tr><th>রুম</th><td>
      <?php foreach ($new['rooms'] as $r):
          $rid = (int)($r['id'] ?? 0); $o = $liveRooms[$rid] ?? null;
          $tag = !$isEdit ? '' : (!$o ? '🆕 নতুন' : (room_sig($o) === room_sig($r) ? '' : '✎ পরিবর্তিত')); ?>
        <div style="margin-bottom:.5rem"><?= $tag ? '<b>' . e($tag) . '</b> ' : '' ?><?= e(room_line($r)) ?><br>
          <?php foreach ($r['images'] as $img): ?><img class="thumb" src="../<?= e($img) ?>" alt=""> <?php endforeach; ?></div>
      <?php endforeach; ?>
      <?php if ($isEdit): foreach ($liveRooms as $rid => $o): if (!in_array($rid, array_map(function ($r) { return (int)($r['id'] ?? 0); }, $new['rooms']), true)): ?>
        <div><b>🗑 মুছে ফেলা হবে:</b> <del><?= e(room_line($o)) ?></del></div>
      <?php endif; endforeach; endif; ?>
      <?= $new['rooms'] ? '' : '(কোনো রুম নেই)' ?>
    </td></tr>
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
