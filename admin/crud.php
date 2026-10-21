<?php
require __DIR__ . '/_boot.php';
require __DIR__ . '/_layout.php';
admin_require();

$defs = crud_defs();
$t = (string)($_GET['t'] ?? '');
if (!isset($defs[$t])) {
    http_response_code(404);
    exit('Not found');
}
$def = $defs[$t]; // $t is whitelisted, safe to interpolate as table name
require_once APP_ROOT . '/includes/dummy.php';
if ($t === 'hotels') {
    require_once APP_ROOT . '/includes/hotels.php';
    if (isset($_GET['edit'])) {
        header('Location: hotel_edit.php' . ($_GET['edit'] === 'new' ? '' : '?id=' . (int)$_GET['edit']));
        exit;
    }
}
$pdo = db();
$self = 'crud.php?t=' . urlencode($t);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = (string)($_POST['act'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    if (!csrf_check()) {
        flash('সেশন মেয়াদোত্তীর্ণ।', 'err');
        header("Location: $self");
        exit;
    }
    if ($act === 'dummy_ships' && $t === 'ships') {
        $n = ships_fill_dummy($pdo);
        flash($n ? "$n টি সীপে ডামি ডাটা যোগ হয়েছে (শুধু ফাঁকা বা নমুনা-লেখা ঘরগুলো)। এখন প্রতিটি সীপ এডিট করে আসল তথ্য ও ছবি দিন।" : 'সব সীপে আগে থেকেই তথ্য আছে, তাই কিছু যোগ হয়নি।');
    } elseif ($act === 'dummy_data' && $t === 'hotels') {
        $n = 0;
        foreach ($pdo->query('SELECT id FROM hotels ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) as $hid) {
            $n += hotel_fill_dummy((int)$hid) ? 1 : 0;
        }
        flash($n ? "$n টি হোটেলে ডামি ডাটা যোগ হয়েছে (শুধু ফাঁকা জায়গাগুলো ভরা হয়েছে)। এখন প্রতিটি হোটেল এডিট করে আসল তথ্য ও ছবি দিন।" : 'সব হোটেলে আগে থেকেই তথ্য, ছবি ও রুম আছে, তাই কিছু যোগ হয়নি।');
    } elseif ($act === 'delete' && $id && $t === 'hotels') {
        hotel_delete($id);
        flash('মুছে ফেলা হয়েছে।');
    } elseif ($act === 'delete' && $id) {
        foreach ($def['fields'] as $col => $f) {
            if ($f[1] === 'image') {
                $o = $pdo->prepare("SELECT $col FROM $t WHERE id = ?");
                $o->execute([$id]);
                if ($old = $o->fetchColumn()) {
                    delete_upload($old);
                }
            }
        }
        $pdo->prepare("DELETE FROM $t WHERE id = ?")->execute([$id]);
        flash('মুছে ফেলা হয়েছে।');
    } elseif ($act === 'toggle' && $id) {
        $pdo->prepare("UPDATE $t SET active = 1 - active WHERE id = ?")->execute([$id]);
    } elseif ($act === 'move' && $id) {
        $rows = $pdo->query("SELECT id FROM $t ORDER BY sort_order, id")->fetchAll(PDO::FETCH_COLUMN);
        $i = array_search($id, array_map('intval', $rows), true);
        $j = ($_POST['dir'] ?? '') === 'up' ? $i - 1 : $i + 1;
        if ($i !== false && isset($rows[$j])) {
            [$rows[$i], $rows[$j]] = [$rows[$j], $rows[$i]];
            $up = $pdo->prepare("UPDATE $t SET sort_order = ? WHERE id = ?");
            foreach ($rows as $n => $rid) {
                $up->execute([$n + 1, $rid]);
            }
        }
    } elseif ($act === 'save') {
        $vals = [];
        $bad = false;
        $oldRow = [];
        if ($id) {
            $o = $pdo->prepare("SELECT * FROM $t WHERE id = ?");
            $o->execute([$id]);
            $oldRow = $o->fetch() ?: [];
        }
        foreach ($def['fields'] as $col => $f) {
            if ($f[1] === 'check') {
                $vals[$col] = empty($_POST[$col]) ? 0 : 1;
            } elseif ($f[1] === 'number') {
                $vals[$col] = max(1, min(5, (int)($_POST[$col] ?? 3)));
            } elseif ($f[1] === 'image') {
                $cur = (string)($oldRow[$col] ?? '');
                try {
                    $new = save_image_upload($_FILES[$col] ?? ['error' => UPLOAD_ERR_NO_FILE]);
                } catch (RuntimeException $ex) {
                    flash($ex->getMessage(), 'err');
                    header('Location: ' . $self . ($id ? "&edit=$id" : '&edit=new'));
                    exit;
                }
                if ($new) {
                    if ($cur) {
                        delete_upload($cur);
                    }
                    $cur = $new;
                } elseif (!empty($_POST['remove_' . $col]) && $cur) {
                    delete_upload($cur);
                    $cur = '';
                }
                $vals[$col] = $cur;
            } else {
                $vals[$col] = mb_substr(trim((string)($_POST[$col] ?? '')), 0, $f[1] === 'textarea' ? 2000 : 255);
                if (!empty($f[2]) && $vals[$col] === '') {
                    $bad = true;
                }
            }
        }
        if ($bad) {
            flash('প্রয়োজনীয় ঘর পূরণ করুন।', 'err');
            header('Location: ' . $self . ($id ? "&edit=$id" : '&edit=new'));
            exit;
        }
        if ($id) {
            $set = implode(', ', array_map(function ($c) { return "$c = ?"; }, array_keys($vals)));
            $pdo->prepare("UPDATE $t SET $set WHERE id = ?")->execute(array_merge(array_values($vals), [$id]));
        } else {
            $vals['sort_order'] = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM $t")->fetchColumn();
            $cols = array_keys($vals);
            $pdo->prepare("INSERT INTO $t (" . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')
                ->execute(array_values($vals));
        }
        flash('সংরক্ষিত হয়েছে।');
    }
    header("Location: $self");
    exit;
}

admin_head($def['title']);

if (isset($_GET['edit'])) {
    $id = $_GET['edit'] === 'new' ? 0 : (int)$_GET['edit'];
    $row = [];
    if ($id) {
        $st = $pdo->prepare("SELECT * FROM $t WHERE id = ?");
        $st->execute([$id]);
        $row = $st->fetch() ?: [];
    }
    ?>
<form method="post" class="form" enctype="multipart/form-data">
  <?= csrf_field() ?><input type="hidden" name="act" value="save"><input type="hidden" name="id" value="<?= $id ?>">
  <?php foreach ($def['fields'] as $col => $f): $val = $row[$col] ?? ''; ?>
    <?php if ($f[1] === 'check'): ?>
      <label class="chk"><input type="checkbox" name="<?= e($col) ?>" value="1" <?= $val ? 'checked' : '' ?>> <?= e($f[0]) ?></label>
    <?php elseif ($f[1] === 'image'): ?>
      <label><?= e($f[0]) ?>
        <?php if ($val): ?><span class="preview"><img src="../<?= e($val) ?>" alt=""> <span class="chk"><input type="checkbox" name="remove_<?= e($col) ?>" value="1"> মুছে ফেলুন</span></span><?php endif; ?>
        <input type="file" name="<?= e($col) ?>" accept="image/*">
      </label>
    <?php elseif ($f[1] === 'number'): ?>
      <label><?= e($f[0]) ?><input type="number" min="1" max="5" name="<?= e($col) ?>" value="<?= e($val !== '' ? $val : 3) ?>"></label>
    <?php elseif ($f[1] === 'textarea'): ?>
      <label><?= e($f[0]) ?><textarea name="<?= e($col) ?>" rows="4"><?= e($val) ?></textarea></label>
    <?php else: ?>
      <label><?= e($f[0]) ?><input name="<?= e($col) ?>" value="<?= e($val) ?>" <?= !empty($f[2]) ? 'required' : '' ?>></label>
    <?php endif; ?>
  <?php endforeach; ?>
  <button class="btn">সংরক্ষণ</button> <a href="<?= e($self) ?>" class="btn btn--ghost">বাতিল</a>
</form>
<?php
} else {
    $rows = $pdo->query("SELECT * FROM $t ORDER BY sort_order, id")->fetchAll();
    ?>
<p><a class="btn" href="<?= e($self) ?>&edit=new">+ নতুন যোগ করুন</a>
<?php if ($t === 'hotels'): ?>
  <form method="post" style="display:inline" onsubmit="return confirm('সব হোটেলের ফাঁকা জায়গায় ডামি তথ্য, \'Sample Photo\' ছবি ও ৪টি নমুনা রুম (নমুনা ভাড়াসহ) যোগ হবে। যা আগে থেকে দেওয়া আছে তা বদলাবে না। চালিয়ে যাবেন?')"><?= csrf_field() ?><input type="hidden" name="act" value="dummy_data"><button class="sm">🧪 সব হোটেলে ডামি ডাটা যোগ করুন</button></form>
<?php endif; ?>
<?php if ($t === 'ships'): ?>
  <form method="post" style="display:inline" onsubmit="return confirm('সব সীপের ফাঁকা ঘরে (এবং ভুলে সেভ হওয়া নমুনা-লেখায়) ডামি তথ্য ও ভাড়া বসবে। আসল তথ্য বদলাবে না। চালিয়ে যাবেন?')"><?= csrf_field() ?><input type="hidden" name="act" value="dummy_ships"><button class="sm">🧪 সব সীপে ডামি ডাটা যোগ করুন</button></form>
<?php endif; ?></p>
<table class="tbl">
  <tr><th>#</th><th>শিরোনাম</th><th>অবস্থা</th><th>অ্যাকশন</th></tr>
  <?php foreach ($rows as $i => $r): ?>
  <tr class="<?= $r['active'] ? '' : 'off' ?>">
    <td><?= $i + 1 ?></td>
    <td><?= e($r[$def['label']]) ?></td>
    <td><?= $r['active'] ? 'সক্রিয়' : 'লুকানো' ?></td>
    <td class="acts">
      <?php foreach ([['move', 'up', '↑'], ['move', 'down', '↓'], ['toggle', '', $r['active'] ? 'লুকান' : 'দেখান']] as [$a, $dir, $lbl]): ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="<?= $a ?>"><input type="hidden" name="dir" value="<?= $dir ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="sm"><?= e($lbl) ?></button></form>
      <?php endforeach; ?>
      <a class="sm" href="<?= e($self) ?>&edit=<?= (int)$r['id'] ?>">এডিট</a>
      <form method="post" onsubmit="return confirm('মুছে ফেলবেন?')"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="sm danger">মুছুন</button></form>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="4">কিছু নেই।</td></tr><?php endif; ?>
</table>
<?php
}
admin_foot();
