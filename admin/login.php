<?php
require __DIR__ . '/_boot.php';
if (admin_logged_in()) {
    header('Location: index.php');
    exit;
}
$err = '';
$ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = db();
    $pdo->prepare('DELETE FROM login_attempts WHERE created_at < ?')->execute([time() - 900]);
    $st = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ?');
    $st->execute([$ip]);
    if (!csrf_check()) {
        $err = 'সেশন মেয়াদোত্তীর্ণ, আবার চেষ্টা করুন।';
    } elseif ((int)$st->fetchColumn() >= 5) {
        $err = 'অনেকবার ভুল হয়েছে। ১৫ মিনিট পরে চেষ্টা করুন।';
    } else {
        $st = $pdo->prepare('SELECT * FROM admins WHERE username = ?');
        $st->execute([trim((string)($_POST['username'] ?? ''))]);
        $u = $st->fetch();
        if ($u && password_verify((string)($_POST['password'] ?? ''), $u['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$u['id'];
            $pdo->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
            header('Location: index.php');
            exit;
        }
        $pdo->prepare('INSERT INTO login_attempts (ip, created_at) VALUES (?, ?)')->execute([$ip, time()]);
        $err = 'ইউজারনেম বা পাসওয়ার্ড ভুল।';
    }
}
?>
<!DOCTYPE html>
<html lang="bn"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>অ্যাডমিন লগইন</title><link rel="stylesheet" href="admin.css"><meta name="robots" content="noindex"></head>
<body class="auth">
<form method="post" class="auth__box">
  <h1>অ্যাডমিন লগইন</h1>
  <?php if (isset($_GET['installed'])): ?><p class="msg msg--ok">ইনস্টল সম্পন্ন। এখন লগইন করুন এবং সার্ভার থেকে install.php মুছে ফেলুন।</p><?php endif; ?>
  <?php if ($err): ?><p class="msg msg--err"><?= e($err) ?></p><?php endif; ?>
  <?= csrf_field() ?>
  <label>ইউজারনেম<input name="username" required autofocus autocomplete="username"></label>
  <label>পাসওয়ার্ড<input type="password" name="password" required autocomplete="current-password"></label>
  <button class="btn">লগইন</button>
</form>
</body></html>
