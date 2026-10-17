<?php
require __DIR__ . '/_boot.php';
require __DIR__ . '/_layout.php';
admin_require();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $st = db()->prepare('SELECT * FROM admins WHERE id = ?');
    $st->execute([$_SESSION['admin_id']]);
    $u = $st->fetch();
    $new = (string)($_POST['new'] ?? '');
    if (!csrf_check()) {
        flash('সেশন মেয়াদোত্তীর্ণ।', 'err');
    } elseif (!$u || !password_verify((string)($_POST['current'] ?? ''), $u['password_hash'])) {
        flash('বর্তমান পাসওয়ার্ড ভুল।', 'err');
    } elseif (strlen($new) < 8 || $new !== ($_POST['confirm'] ?? '')) {
        flash('নতুন পাসওয়ার্ড কমপক্ষে ৮ অক্ষর এবং কনফার্মের সাথে মিলতে হবে।', 'err');
    } else {
        db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
        session_regenerate_id(true);
        flash('পাসওয়ার্ড পরিবর্তন হয়েছে।');
    }
    header('Location: password.php');
    exit;
}
admin_head('পাসওয়ার্ড পরিবর্তন');
?>
<form method="post" class="form">
  <?= csrf_field() ?>
  <label>বর্তমান পাসওয়ার্ড<input type="password" name="current" required autocomplete="current-password"></label>
  <label>নতুন পাসওয়ার্ড<input type="password" name="new" required minlength="8" autocomplete="new-password"></label>
  <label>নতুন পাসওয়ার্ড (আবার)<input type="password" name="confirm" required minlength="8" autocomplete="new-password"></label>
  <button class="btn">সংরক্ষণ</button>
</form>
<?php admin_foot();
