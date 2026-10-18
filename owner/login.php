<?php
require __DIR__ . '/_boot.php';
require __DIR__ . '/_layout.php';
if (owner_current()) {
    header('Location: index.php');
    exit;
}
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $key = 'own:' . client_ip();
    if (!csrf_check()) {
        $err = 'সেশন মেয়াদোত্তীর্ণ, আবার চেষ্টা করুন।';
    } elseif (attempts_exceeded($key, 5)) {
        $err = 'অনেকবার ভুল হয়েছে। ১৫ মিনিট পরে চেষ্টা করুন।';
    } else {
        $st = db()->prepare('SELECT * FROM owners WHERE email = ?');
        $st->execute([strtolower(trim((string)($_POST['email'] ?? '')))]);
        $o = $st->fetch();
        if ($o && password_verify((string)($_POST['password'] ?? ''), $o['password_hash'])) {
            if ($o['status'] === 'pending') {
                $err = 'আপনার অ্যাকাউন্ট এখনো অনুমোদিত হয়নি। অ্যাডমিন অনুমোদন করলে লগইন করতে পারবেন।';
            } elseif ($o['status'] !== 'active') {
                $err = 'এই অ্যাকাউন্টটি স্থগিত করা আছে। যোগাযোগ করুন।';
            } else {
                session_regenerate_id(true);
                $_SESSION['owner_id'] = (int)$o['id'];
                db()->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$key]);
                header('Location: index.php');
                exit;
            }
        } else {
            attempts_add($key);
            $err = 'ইমেইল বা পাসওয়ার্ড ভুল।';
        }
    }
}
owner_head('হোটেল মালিক লগইন');
?>
<form method="post" class="form">
  <?php if ($err): ?><p class="msg msg--err"><?= e($err) ?></p><?php endif; ?>
  <?= csrf_field() ?>
  <label>ইমেইল<input type="email" name="email" required autofocus autocomplete="username"></label>
  <label>পাসওয়ার্ড<input type="password" name="password" required autocomplete="current-password"></label>
  <button class="btn">লগইন</button>
  <p class="hint">নতুন? <a href="register.php">রেজিস্ট্রেশন করুন</a> · <a href="../">সাইটে ফিরুন</a></p>
</form>
<?php owner_foot();
