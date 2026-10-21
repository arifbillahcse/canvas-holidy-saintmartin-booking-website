<?php
// One-time installer. Delete this file after installation.
define('APP_ROOT', __DIR__);
require_once __DIR__ . '/includes/schema.php';
require __DIR__ . '/includes/seed.php';
require __DIR__ . '/includes/helpers.php';

if (is_file(APP_ROOT . '/config.php')) {
    http_response_code(403);
    exit('Already installed. Delete install.php from the server.');
}

$err = '';
$v = ['driver' => 'mysql', 'host' => 'localhost', 'name' => '', 'user' => '', 'username' => 'admin'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $err = 'Invalid session. Reload and retry.';
    }
    foreach ($v as $k => $_) {
        $v[$k] = trim((string)($_POST[$k] ?? $v[$k]));
    }
    $dbpass = (string)($_POST['pass'] ?? '');
    $apass = (string)($_POST['admin_pass'] ?? '');
    if (!$err && !preg_match('/^[A-Za-z0-9_.-]{3,32}$/', $v['username'])) {
        $err = 'Admin username: 3-32 chars (letters, numbers, . _ -).';
    }
    if (!$err && strlen($apass) < 8) {
        $err = 'Admin password must be at least 8 characters.';
    }
    if (!$err) {
        try {
            if ($v['driver'] === 'sqlite') {
                $cfg = ['driver' => 'sqlite', 'name' => APP_ROOT . '/data-' . bin2hex(random_bytes(6)) . '.sqlite'];
                $pdo = new PDO('sqlite:' . $cfg['name']);
            } else {
                $cfg = ['driver' => 'mysql', 'host' => $v['host'], 'name' => $v['name'], 'user' => $v['user'], 'pass' => $dbpass];
                $pdo = new PDO("mysql:host={$cfg['host']};dbname={$cfg['name']};charset=utf8mb4", $cfg['user'], $cfg['pass']);
            }
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            foreach (schema_sql($cfg['driver']) as $q) {
                $pdo->exec($q);
            }
            if ((int)$pdo->query('SELECT COUNT(*) FROM settings')->fetchColumn() === 0) {
                seed_defaults($pdo);
            }
            $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)')
                ->execute([$v['username'], password_hash($apass, PASSWORD_DEFAULT)]);
            file_put_contents(APP_ROOT . '/config.php', "<?php\nreturn " . var_export($cfg, true) . ";\n");
            @chmod(APP_ROOT . '/config.php', 0640);
            header('Location: admin/login.php?installed=1');
            exit;
        } catch (Throwable $ex) {
            $err = 'Database error: ' . $ex->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install</title>
<link rel="stylesheet" href="admin/admin.css"></head>
<body class="auth">
<form method="post" class="auth__box">
  <h1>Site installer</h1>
  <?php if ($err): ?><p class="msg msg--err"><?= e($err) ?></p><?php endif; ?>
  <?= csrf_field() ?>
  <label>Database
    <select name="driver"><option value="mysql" <?= $v['driver'] === 'mysql' ? 'selected' : '' ?>>MySQL / MariaDB</option><option value="sqlite" <?= $v['driver'] === 'sqlite' ? 'selected' : '' ?>>SQLite (no setup)</option></select>
  </label>
  <label>DB host<input name="host" value="<?= e($v['host']) ?>"></label>
  <label>DB name<input name="name" value="<?= e($v['name']) ?>"></label>
  <label>DB user<input name="user" value="<?= e($v['user']) ?>"></label>
  <label>DB password<input type="password" name="pass" autocomplete="off"></label>
  <hr>
  <label>Admin username<input name="username" value="<?= e($v['username']) ?>" required></label>
  <label>Admin password (min 8)<input type="password" name="admin_pass" required minlength="8" autocomplete="new-password"></label>
  <button class="btn">Install</button>
  <p class="hint">MySQL fields are ignored when SQLite is selected.</p>
</form>
</body></html>
