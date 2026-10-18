<?php
function owner_head(string $title, ?array $owner = null): void
{
    $f = flash();
    ?><!DOCTYPE html>
<html lang="bn"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> | হোটেল মালিক প্যানেল</title>
<link rel="stylesheet" href="../admin/admin.css"><meta name="robots" content="noindex"></head>
<body<?= $owner ? '' : ' class="auth"' ?>>
<?php if ($owner): ?>
<aside class="side">
  <strong class="side__brand"><?= e(setting('site_name_a') . ' ' . setting('site_name_b')) ?></strong>
  <p class="side__credit">স্বাগতম, <?= e($owner['name']) ?></p>
  <nav>
    <a href="index.php">আমার হোটেল</a>
    <a href="hotel.php">+ নতুন হোটেল</a>
    <a href="../hotels.php" target="_blank">হোটেল পেজ ↗</a>
    <a href="logout.php">লগআউট</a>
  </nav>
</aside>
<main class="main">
<?php else: ?>
<div class="auth__box">
<?php endif; ?>
  <h1><?= e($title) ?></h1>
  <?php if ($f): ?><p class="msg msg--<?= $f[1] === 'err' ? 'err' : 'ok' ?>"><?= e($f[0]) ?></p><?php endif; ?>
<?php
}

function owner_foot(?array $owner = null): void
{
    echo $owner ? '</main>' : '</div>';
    echo '</body></html>';
}
