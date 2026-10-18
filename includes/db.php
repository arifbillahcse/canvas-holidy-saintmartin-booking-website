<?php
// Database connection. Reads config.php (created by install.php).
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    $file = APP_ROOT . '/config.php';
    if (!is_file($file)) {
        header('Location: ' . base_url() . 'install.php');
        exit;
    }
    $c = require $file;
    $GLOBALS['db_driver'] = $c['driver'] ?? 'mysql';
    if (($c['driver'] ?? 'mysql') === 'sqlite') {
        $pdo = new PDO('sqlite:' . $c['name']);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } else {
        $dsn = 'mysql:host=' . $c['host'] . ';dbname=' . $c['name'] . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $c['user'], $c['pass']);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    return $pdo;
}

function base_url(): string
{
    // Directory of the public site root, with trailing slash (works in a subfolder too).
    $docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: ''), '/');
    $root = str_replace('\\', '/', APP_ROOT);
    $path = $docRoot !== '' && strpos($root, $docRoot) === 0 ? substr($root, strlen($docRoot)) : '';
    return rtrim($path, '/') . '/';
}
