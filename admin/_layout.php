<?php
function admin_head(string $title): void
{
    $pdo = db();
    $pend = (int)$pdo->query("SELECT COUNT(*) FROM hotels WHERE status = 'pending'")->fetchColumn();
    $newOwners = (int)$pdo->query("SELECT COUNT(*) FROM owners WHERE status = 'pending'")->fetchColumn();
    $nav = ['index.php' => 'ড্যাশবোর্ড', 'settings.php' => 'সাইট সেটিংস', 'bookings.php' => 'বুকিং',
        'moderation.php' => 'অনুমোদন' . ($pend ? " ($pend)" : ''), 'owners.php' => 'হোটেল মালিক' . ($newOwners ? " ($newOwners)" : '')];
    foreach (crud_defs() as $k => $d) {
        $nav['crud.php?t=' . $k] = $d['title'];
    }
    $nav['password.php'] = 'পাসওয়ার্ড';
    $nav['system.php'] = 'সিস্টেম ইনফরমেশন';
    $cur = basename($_SERVER['SCRIPT_NAME']) . (isset($_GET['t']) ? '?t=' . $_GET['t'] : '');
    $f = flash();
    ?><!DOCTYPE html>
<html lang="bn"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> | অ্যাডমিন</title>
<link rel="stylesheet" href="admin.css"></head>
<body>
<aside class="side">
  <strong class="side__brand"><?= e(setting('site_name_a') . ' ' . setting('site_name_b')) ?></strong>
  <nav>
    <?php foreach ($nav as $href => $label): ?><a href="<?= e($href) ?>" class="<?= $href === $cur ? 'on' : '' ?>"><?= e($label) ?></a><?php endforeach; ?>
    <a href="../" target="_blank">সাইট দেখুন ↗</a>
    <a href="logout.php">লগআউট</a>
  </nav>
  <p class="side__credit">Developed by <a href="https://softorio.com" target="_blank" rel="noopener">softorio.com</a></p>
</aside>
<main class="main">
  <h1><?= e($title) ?></h1>
  <?php if ($f): ?><p class="msg msg--<?= $f[1] === 'err' ? 'err' : 'ok' ?>"><?= e($f[0]) ?></p><?php endif; ?>
<?php
}

function admin_foot(): void
{
    echo "</main></body></html>";
}
