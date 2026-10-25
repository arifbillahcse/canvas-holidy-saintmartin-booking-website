<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/schema.php';

function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function bn_digits($n): string
{
    // Array form: strtr() with two strings maps single bytes and would corrupt multibyte digits.
    return strtr((string)$n, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']);
}

/* ---------- Settings ---------- */
function settings_all(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT k, v FROM settings') as $r) {
            $cache[$r['k']] = $r['v'];
        }
        if (($cache['schema_v'] ?? '') !== SCHEMA_VERSION) {
            ensure_schema();
            $cache['schema_v'] = SCHEMA_VERSION;
        }
    }
    return $cache;
}

// Creates tables/columns added in later versions, once per schema version.
function ensure_schema(): void
{
    require_once __DIR__ . '/schema.php';
    $pdo = db();
    $driver = $GLOBALS['db_driver'] ?? 'mysql';
    foreach (schema_sql($driver) as $q) {
        $pdo->exec($q);
    }
    migrate_schema($pdo, $driver);
    $upd = $pdo->prepare('UPDATE settings SET v = ? WHERE k = ?');
    $upd->execute([SCHEMA_VERSION, 'schema_v']);
    if ($upd->rowCount() === 0) {
        $has = $pdo->prepare('SELECT 1 FROM settings WHERE k = ?');
        $has->execute(['schema_v']);
        if (!$has->fetch()) {
            $pdo->prepare('INSERT INTO settings (k, v) VALUES (?, ?)')->execute(['schema_v', SCHEMA_VERSION]);
        }
    }
}

// Admin on/off switch: when on, new owner accounts and owners' hotels/edits need admin approval.
function owner_approval_on(): bool
{
    return setting('owner_approval', '0') === '1';
}

function setting(string $key, string $default = ''): string
{
    $s = settings_all();
    return isset($s[$key]) && $s[$key] !== null ? (string)$s[$key] : $default;
}

function setting_save(string $key, string $value): void
{
    $st = db()->prepare('UPDATE settings SET v = ? WHERE k = ?');
    $st->execute([$value, $key]);
    if ($st->rowCount() === 0) {
        $chk = db()->prepare('SELECT 1 FROM settings WHERE k = ?');
        $chk->execute([$key]);
        if (!$chk->fetch()) {
            db()->prepare('INSERT INTO settings (k, v) VALUES (?, ?)')->execute([$key, $value]);
        }
    }
}

/* ---------- Phone helpers ---------- */
function phone_digits(string $p): string
{
    return preg_replace('/\D+/', '', $p);
}

// 01779440297 -> 8801779440297 (Bangladesh), already-international numbers pass through.
function phone_intl(string $p): string
{
    $d = phone_digits($p);
    if (strpos($d, '00') === 0) {
        return substr($d, 2);
    }
    if (strpos($d, '0') === 0) {
        return '88' . $d;
    }
    return $d;
}

function wa_number(): string
{
    $w = setting('whatsapp');
    return phone_intl($w !== '' ? $w : setting('phone'));
}

/* ---------- Client IP ---------- */
// Behind Cloudflare, REMOTE_ADDR is Cloudflare's IP, so every visitor would share one
// login-lockout/flood counter. Set 'trust_cloudflare' => true in config.php to use
// CF-Connecting-IP (only enable if the site is reachable solely through Cloudflare).
function client_ip(): string
{
    static $trust = null;
    if ($trust === null) {
        $cfg = is_file(APP_ROOT . '/config.php') ? require APP_ROOT . '/config.php' : [];
        $trust = !empty($cfg['trust_cloudflare']);
    }
    $ip = $trust ? ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '') : '';
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    }
    return substr($ip, 0, 64);
}

/* ---------- CSRF ---------- */
function session_boot(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => !empty($_SERVER['HTTPS']),
            'path' => '/',
        ]);
        session_start();
    }
}

function csrf_token(): string
{
    session_boot();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(?string $t = null): bool
{
    session_boot();
    $t = $t ?? ($_POST['_csrf'] ?? '');
    return !empty($_SESSION['csrf']) && is_string($t) && hash_equals($_SESSION['csrf'], $t);
}

/* ---------- Auth ---------- */
function admin_logged_in(): bool
{
    session_boot();
    return !empty($_SESSION['admin_id']);
}

function admin_require(): void
{
    if (!admin_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function flash(?string $msg = null, string $type = 'ok')
{
    session_boot();
    if ($msg !== null) {
        $_SESSION['flash'] = [$msg, $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- Uploads ---------- */
// Returns relative path (uploads/xxx.ext) or null. Throws RuntimeException on invalid file.
function save_image_upload(array $file, array $exts = ['png', 'jpg', 'jpeg', 'webp', 'gif']): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('ফাইল আপলোড ব্যর্থ হয়েছে।');
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('ফাইল ২ MB এর বেশি হতে পারবে না।');
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $exts, true)) {
        throw new RuntimeException('অনুমোদিত ফরম্যাট: ' . implode(', ', $exts));
    }
    if ($ext === 'ico') {
        $head = file_get_contents($file['tmp_name'], false, null, 0, 4);
        if ($head !== "\0\0\1\0") {
            throw new RuntimeException('অবৈধ ICO ফাইল।');
        }
    } elseif (@getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('এটি বৈধ ছবি নয়।');
    }
    $dir = APP_ROOT . '/uploads';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $name = 'img_' . bin2hex(random_bytes(8)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('ফাইল সংরক্ষণ করা যায়নি।');
    }
    resize_image($dir . '/' . $name);
    return 'uploads/' . $name;
}

function delete_upload(string $rel): void
{
    if (preg_match('#^uploads/img_[a-f0-9]+\.[a-z]+$#', $rel)) {
        @unlink(APP_ROOT . '/' . $rel);
    }
}

// Downscale very large photos (max 1600px wide) so pages stay light. Needs GD; silently skipped otherwise.
function resize_image(string $path, int $max = 1600): void
{
    $info = @getimagesize($path);
    if (!$info || $info[0] <= $max || !function_exists('imagescale')) {
        return;
    }
    $type = $info[2];
    $img = $type === IMAGETYPE_JPEG && function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path)
        : ($type === IMAGETYPE_PNG && function_exists('imagecreatefrompng') ? @imagecreatefrompng($path)
        : ($type === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false));
    if (!$img) {
        return;
    }
    $small = imagescale($img, $max);
    if ($small) {
        if ($type === IMAGETYPE_PNG) {
            imagesavealpha($small, true);
            imagepng($small, $path, 6);
        } elseif ($type === IMAGETYPE_WEBP) {
            imagewebp($small, $path, 82);
        } else {
            imagejpeg($small, $path, 85);
        }
    }
}
