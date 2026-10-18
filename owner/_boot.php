<?php
define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/includes/helpers.php';
require APP_ROOT . '/includes/schema.php';
session_boot();

// Fields an owner may edit on a hotel (rating, badge, featured and ordering are admin-only).
function owner_fields(): array
{
    $all = crud_defs()['hotels']['fields'];
    return array_intersect_key($all, array_flip(['image', 'name', 'location', 'stars', 'price', 'summary', 'amenities']));
}

// Logged-in owner row or null. Re-checked on every request so suspensions apply immediately.
function owner_current(): ?array
{
    static $row = false;
    if ($row !== false) {
        return $row;
    }
    $row = null;
    if (!empty($_SESSION['owner_id'])) {
        $st = db()->prepare("SELECT * FROM owners WHERE id = ? AND status = 'active'");
        $st->execute([(int)$_SESSION['owner_id']]);
        $row = $st->fetch() ?: null;
        if (!$row) {
            unset($_SESSION['owner_id']);
        }
    }
    return $row;
}

function owner_require(): array
{
    $o = owner_current();
    if (!$o) {
        header('Location: login.php');
        exit;
    }
    return $o;
}

// Per-IP attempt limiter backed by login_attempts (key prefix separates login vs register).
function attempts_exceeded(string $key, int $max, int $window = 900): bool
{
    $pdo = db();
    $pdo->prepare('DELETE FROM login_attempts WHERE created_at < ?')->execute([time() - $window]);
    $st = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ?');
    $st->execute([$key]);
    return (int)$st->fetchColumn() >= $max;
}

function attempts_add(string $key): void
{
    db()->prepare('INSERT INTO login_attempts (ip, created_at) VALUES (?, ?)')->execute([$key, time()]);
}
