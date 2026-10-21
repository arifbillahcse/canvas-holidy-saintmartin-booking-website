<?php
require __DIR__ . '/_boot.php';
require __DIR__ . '/_layout.php';
admin_require();
require_once APP_ROOT . '/includes/dummy.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['act'] ?? '', ['dummy_ships', 'dummy_hotels'], true)) {
    if (!csrf_check()) {
        flash('সেশন মেয়াদোত্তীর্ণ, আবার চেষ্টা করুন।', 'err');
    } elseif ($_POST['act'] === 'dummy_ships') {
        $n = ships_fill_dummy(db());
        flash($n ? "$n টি সীপে ডামি ডাটা যোগ হয়েছে (শুধু ফাঁকা বা নমুনা-লেখা ঘর)। এখন সীপ এডিট করে আসল তথ্য ও ছবি দিন।" : 'সব সীপে আগে থেকেই তথ্য আছে, তাই কিছু যোগ হয়নি।');
    } else {
        $n = 0;
        foreach (db()->query('SELECT id FROM hotels ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) as $hid) {
            $n += hotel_fill_dummy((int)$hid) ? 1 : 0;
        }
        flash($n ? "$n টি হোটেলে ডামি ডাটা যোগ হয়েছে (শুধু ফাঁকা জায়গা)। এখন হোটেল এডিট করে আসল তথ্য ও ছবি দিন।" : 'সব হোটেলে আগে থেকেই তথ্য, ছবি ও রুম আছে, তাই কিছু যোগ হয়নি।');
    }
    header('Location: system.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'clear_cache') {
    if (!csrf_check()) {
        flash('সেশন মেয়াদোত্তীর্ণ, আবার চেষ্টা করুন।', 'err');
    } else {
        $done = [];
        if (function_exists('opcache_reset') && @opcache_reset()) {
            $done[] = 'PHP OPcache রিসেট';
        }
        clearstatcache(true);
        $done[] = 'ফাইল স্ট্যাটাস ক্যাশ';
        // New version string => browsers re-download style.css and script.js.
        setting_save('asset_v', (string)time());
        $done[] = 'ব্রাউজার CSS/JS ক্যাশ ভার্সন আপডেট';
        db()->prepare('DELETE FROM login_attempts WHERE created_at < ?')->execute([time() - 900]);
        $done[] = 'মেয়াদোত্তীর্ণ লগইন/রেজিস্ট্রেশন অ্যাটেম্পট মুছে ফেলা';
        flash('ক্যাশ ক্লিয়ার হয়েছে: ' . implode(', ', $done) . '।');
    }
    header('Location: system.php');
    exit;
}

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
        'OPcache' => (function_exists('opcache_get_status') && ($o = @opcache_get_status(false)) && !empty($o['opcache_enabled'])) ? 'চালু (' . $o['opcache_statistics']['num_cached_scripts'] . ' স্ক্রিপ্ট ক্যাশড)' : 'বন্ধ / উপলব্ধ নয়',
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

<form method="post" class="cache-box" onsubmit="return confirm('ক্যাশ ক্লিয়ার করবেন?')">
  <?= csrf_field() ?><input type="hidden" name="act" value="clear_cache">
  <div><strong>ক্যাশ ক্লিয়ার</strong><p>PHP OPcache রিসেট, ব্রাউজারে CSS/JS নতুন করে লোড করানো এবং পুরোনো অ্যাটেম্পট রেকর্ড মোছা। সাইটের কনটেন্ট বা সেটিংস মুছবে না।</p></div>
  <button class="btn">🧹 ক্যাশ ক্লিয়ার করুন</button>
</form>
<div class="cache-box">
  <div><strong>ডামি ডাটা যোগ করুন</strong><p>নতুন সাজানো ডিজাইনে সীপ ও হোটেল সুন্দর দেখানোর জন্য ফাঁকা ঘরে নমুনা তথ্য, ভাড়া ও রুম বসাবে। যা আগে থেকে দেওয়া আছে তা বদলাবে না; বারবার চাপলেও ক্ষতি নেই। পরে এডিট করে আসল তথ্য ও ছবি দিন।</p></div>
  <div style="display:flex;gap:.6rem;flex-wrap:wrap">
    <form method="post" onsubmit="return confirm('সব সীপের ফাঁকা ঘরে ডামি তথ্য ও ভাড়া বসবে। চালিয়ে যাবেন?')"><?= csrf_field() ?><input type="hidden" name="act" value="dummy_ships"><button class="btn">🚢 সীপে ডামি ডাটা</button></form>
    <form method="post" onsubmit="return confirm('সব হোটেলের ফাঁকা জায়গায় ডামি তথ্য, Sample Photo ছবি ও ৪টি নমুনা রুম বসবে। চালিয়ে যাবেন?')"><?= csrf_field() ?><input type="hidden" name="act" value="dummy_hotels"><button class="btn">🏨 হোটেলে ডামি ডাটা</button></form>
  </div>
</div>
<p class="hint">Cloudflare ব্যবহার করলে তার ড্যাশবোর্ড থেকে আলাদা করে "Purge Cache" দিতে হবে।</p>

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
