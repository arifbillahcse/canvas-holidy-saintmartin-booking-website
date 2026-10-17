<?php
// Saves a booking inquiry (called by script.js in the background; WhatsApp opens client-side).
require __DIR__ . '/includes/helpers.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

$name = trim((string)($_POST['name'] ?? ''));
$phone = preg_replace('/[\s-]/', '', (string)($_POST['phone'] ?? ''));
$date = (string)($_POST['date'] ?? '');
$persons = (int)($_POST['persons'] ?? 0);
$type = trim((string)($_POST['type'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

$ok = mb_strlen($name) >= 2 && mb_strlen($name) <= 100
    && preg_match('/^(?:\+?88)?01[3-9]\d{8}$/', $phone)
    && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
    && $persons >= 1 && $persons <= 100;
if (!$ok) {
    http_response_code(422);
    echo json_encode(['ok' => false]);
    exit;
}

// Basic flood control: max 10 inquiries per IP per hour.
$ip = client_ip();
$st = db()->prepare("SELECT COUNT(*) FROM bookings WHERE ip = ? AND created_at > ?");
$st->execute([$ip, date('Y-m-d H:i:s', time() - 3600)]);
if ((int)$st->fetchColumn() >= 10) {
    http_response_code(429);
    echo json_encode(['ok' => false]);
    exit;
}

db()->prepare('INSERT INTO bookings (name, phone, travel_date, persons, ticket_type, message, ip, created_at) VALUES (?,?,?,?,?,?,?,?)')
    ->execute([$name, $phone, $date, $persons, mb_substr($type, 0, 100), mb_substr($message, 0, 1000), $ip, date('Y-m-d H:i:s')]);
echo json_encode(['ok' => true]);
