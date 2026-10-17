<?php
require __DIR__ . '/_boot.php';
require __DIR__ . '/_layout.php';
admin_require();

$cfg = require APP_ROOT . '/config.php';
$driver = $cfg['driver'] ?? 'mysql';
try {
    $dbVersion = $driver === 'sqlite'
        ? 'SQLite ' . db()->query('select sqlite_version()')->fetchColumn()
        : 'MySQL/MariaDB ' . db()->query('select version()')->fetchColumn();
} catch (Throwable $ex) {
    $dbVersion = 'অজানা';
}
$bytes = function ($v) {
    $v = (float)$v;
    foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $u) {
        if ($v < 1024 || $u === 'TB') {
            return round($v, 1) . ' ' . $u;
        }
        $v /= 1024;
    }
};
$yn = function ($b) { return $b ? '✔ হ্যাঁ' : '✘ না'; };
$free = @disk_free_space(APP_ROOT);
$total = @disk_total_space(APP_ROOT);
$exts = ['pdo_mysql', 'pdo_sqlite', 'mbstring', 'gd', 'fileinfo', 'json', 'openssl'];

$rows = [
    'অ্যাপ্লিকেশন' => [
        'PHP ভার্সন' => PHP_VERSION . (version_compare(PHP_VERSION, '8.0.0', '>=') ? '' : ' (⚠ PHP 8.0+ প্রয়োজন)'),
        'PHP SAPI' => PHP_SAPI,
        'ডাটাবেস ড্রাইভার' => $driver,
        'ডাটাবেস ভার্সন' => $dbVersion,
        'সাইটে ছবি/বুকিং/হোটেল' => (function () {
            $c = function ($t) { return (int)db()->query("SELECT COUNT(*) FROM $t")->fetchColumn(); };
            return $c('hotels') . ' হোটেল, ' . $c('ships') . ' সীপ, ' . $c('bookings') . ' বুকিং';
        })(),
    ],
    'সার্ভার' => [
        'সার্ভার সফটওয়্যার' => $_SERVER['SERVER_SOFTWARE'] ?? '—',
        'অপারেটিং সিস্টেম' => php_uname('s') . ' ' . php_uname('r'),
        'HTTPS' => $yn(!empty($_SERVER['HTTPS']) || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')),
        'সার্ভার সময়' => date('Y-m-d H:i:s T'),
        'টাইমজোন (PHP)' => date_default_timezone_get(),
        'ডিস্ক ফাঁকা' => $free !== false ? $bytes($free) . ($total ? ' / ' . $bytes($total) : '') : '—',
    ],
    'PHP লিমিট' => [
        'memory_limit' => ini_get('memory_limit'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'max_execution_time' => ini_get('max_execution_time') . 's',
    ],
    'ফাইল পারমিশন' => [
        'uploads/ লেখা যায়' => $yn(is_writable(APP_ROOT . '/uploads')),
        'config.php সুরক্ষিত (লেখা যায় না)' => $yn(!is_writable(APP_ROOT . '/config.php')),
        'install.php মুছে ফেলা হয়েছে' => is_file(APP_ROOT . '/install.php') ? '✘ না — এখনই মুছে ফেলুন' : '✔ হ্যাঁ',
    ],
    'PHP এক্সটেনশন' => [],
];
foreach ($exts as $x) {
    $rows['PHP এক্সটেনশন'][$x] = $yn(extension_loaded($x));
}

admin_head('সিস্টেম ইনফরমেশন');
?>
<section class="brand">
  <div>
    <strong>Softorio</strong>
    <p>ওয়েব ডেভেলপমেন্ট, কাস্টম সফটওয়্যার, AI ইন্টিগ্রেশন ও অটোমেশন।</p>
    <a class="btn" href="https://softorio.com" target="_blank" rel="noopener">softorio.com</a>
  </div>
  <div>
    <strong>Hostorio</strong>
    <p>ওয়েব হোস্টিং ও ইনফ্রাস্ট্রাকচার।</p>
    <a class="btn" href="https://hostorio.com" target="_blank" rel="noopener">hostorio.com</a>
  </div>
</section>

<?php foreach ($rows as $group => $items): ?>
<h2 class="sys-h"><?= e($group) ?></h2>
<table class="tbl sys">
  <?php foreach ($items as $k => $v): ?>
  <tr><th><?= e($k) ?></th><td><?= e($v) ?></td></tr>
  <?php endforeach; ?>
</table>
<?php endforeach; ?>

<p class="hint">Developed by <a href="https://softorio.com" target="_blank" rel="noopener">softorio.com</a> · Hosting by <a href="https://hostorio.com" target="_blank" rel="noopener">hostorio.com</a></p>
<?php admin_foot();
