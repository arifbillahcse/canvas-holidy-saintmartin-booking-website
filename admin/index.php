<?php
require __DIR__ . '/_boot.php';
require __DIR__ . '/_layout.php';
admin_require();
$pdo = db();
// Upgrade path: create tables added in later versions (IF NOT EXISTS, safe to repeat).
$driver = (require APP_ROOT . '/config.php')['driver'] ?? 'mysql';
foreach (schema_sql($driver) as $q) {
    $pdo->exec($q);
}
migrate_schema($pdo, $driver);
$count = function ($sql) use ($pdo) { return (int)$pdo->query($sql)->fetchColumn(); };
admin_head('ড্যাশবোর্ড');
?>
<div class="stats">
  <div class="stat"><b><?= $count("SELECT COUNT(*) FROM bookings WHERE status='new'") ?></b>নতুন বুকিং</div>
  <div class="stat"><b><?= $count('SELECT COUNT(*) FROM bookings') ?></b>মোট বুকিং</div>
  <div class="stat"><b><?= $count('SELECT COUNT(*) FROM ships WHERE active=1') ?></b>সক্রিয় সীপ</div>
  <div class="stat"><b><?= $count('SELECT COUNT(*) FROM hotels WHERE active=1') ?></b>সক্রিয় হোটেল</div>
  <div class="stat"><b><?= $count('SELECT COUNT(*) FROM packages WHERE active=1') ?></b>সক্রিয় প্যাকেজ</div>
</div>
<p>বাম মেনু থেকে লোগো, ফোন নম্বর, ঠিকানা, সীপ/প্যাকেজ, FAQ ইত্যাদি পরিবর্তন করুন।</p>
<?php if (is_file(APP_ROOT . '/install.php')): ?><p class="msg msg--err">নিরাপত্তার জন্য সার্ভার থেকে <code>install.php</code> মুছে ফেলুন।</p><?php endif; ?>
<?php admin_foot();
