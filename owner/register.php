<?php
require __DIR__ . '/_boot.php';
require __DIR__ . '/_layout.php';
if (owner_current()) {
    header('Location: index.php');
    exit;
}
$v = ['name' => '', 'email' => '', 'phone' => ''];
$err = '';
$done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = 'reg:' . client_ip();
    foreach ($v as $k => $_) {
        $v[$k] = trim((string)($_POST[$k] ?? ''));
    }
    $pass = (string)($_POST['password'] ?? '');
    if (!csrf_check()) {
        $err = 'সেশন মেয়াদোত্তীর্ণ, পেজ রিলোড করে আবার চেষ্টা করুন।';
    } elseif (!empty($_POST['website'])) {
        $done = true; // honeypot filled: pretend success, store nothing
    } elseif (attempts_exceeded($ip, 3)) {
        $err = 'অনেকবার চেষ্টা হয়েছে। কিছুক্ষণ পরে আবার চেষ্টা করুন।';
    } elseif (mb_strlen($v['name']) < 2 || mb_strlen($v['name']) > 100) {
        $err = 'আপনার নাম লিখুন।';
    } elseif (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || strlen($v['email']) > 190) {
        $err = 'সঠিক ইমেইল দিন।';
    } elseif (!preg_match('/^(?:\+?88)?01[3-9]\d{8}$/', preg_replace('/[\s-]/', '', $v['phone']))) {
        $err = 'সঠিক মোবাইল নম্বর দিন (01XXXXXXXXX)।';
    } elseif (strlen($pass) < 8 || $pass !== (string)($_POST['confirm'] ?? '')) {
        $err = 'পাসওয়ার্ড কমপক্ষে ৮ অক্ষর এবং কনফার্মের সাথে মিলতে হবে।';
    } else {
        attempts_add($ip);
        $has = db()->prepare('SELECT 1 FROM owners WHERE email = ?');
        $has->execute([strtolower($v['email'])]);
        if ($has->fetch()) {
            $err = 'এই ইমেইল দিয়ে আগেই রেজিস্ট্রেশন করা হয়েছে।';
        } else {
            db()->prepare("INSERT INTO owners (name, email, phone, password_hash, status) VALUES (?,?,?,?, 'pending')")
                ->execute([$v['name'], strtolower($v['email']), preg_replace('/[\s-]/', '', $v['phone']), password_hash($pass, PASSWORD_DEFAULT)]);
            $done = true;
        }
    }
}
owner_head('হোটেল মালিক রেজিস্ট্রেশন');
if ($done): ?>
  <p class="msg msg--ok">রেজিস্ট্রেশন সম্পন্ন! অ্যাডমিন অনুমোদন করলে আপনি লগইন করতে পারবেন।</p>
  <p><a href="../">← সাইটে ফিরে যান</a></p>
<?php else: ?>
<form method="post" class="form">
  <?php if ($err): ?><p class="msg msg--err"><?= e($err) ?></p><?php endif; ?>
  <?= csrf_field() ?>
  <label>আপনার নাম<input name="name" value="<?= e($v['name']) ?>" required maxlength="100"></label>
  <label>ইমেইল<input type="email" name="email" value="<?= e($v['email']) ?>" required autocomplete="email"></label>
  <label>মোবাইল নম্বর<input type="tel" name="phone" value="<?= e($v['phone']) ?>" placeholder="01XXXXXXXXX" required></label>
  <label>পাসওয়ার্ড (কমপক্ষে ৮)<input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
  <label>পাসওয়ার্ড আবার<input type="password" name="confirm" required minlength="8" autocomplete="new-password"></label>
  <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
  <button class="btn">রেজিস্ট্রেশন করুন</button>
  <p class="hint">ইতিমধ্যে অ্যাকাউন্ট আছে? <a href="login.php">লগইন করুন</a></p>
</form>
<?php endif;
owner_foot();
