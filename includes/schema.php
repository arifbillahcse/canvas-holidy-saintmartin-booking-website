<?php
const SCHEMA_VERSION = '5';

// Table definitions + CRUD field definitions for the admin panel.

function schema_sql(string $driver): array
{
    $id = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
    $tail = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $key = $driver === 'sqlite' ? 'TEXT PRIMARY KEY' : 'VARCHAR(64) PRIMARY KEY';
    $str = $driver === 'sqlite' ? 'TEXT' : 'VARCHAR(255)';
    $now = $driver === 'sqlite' ? "TEXT DEFAULT CURRENT_TIMESTAMP" : "TIMESTAMP DEFAULT CURRENT_TIMESTAMP";

    $sql = [];
    $sql[] = "CREATE TABLE IF NOT EXISTS settings (k $key, v TEXT)$tail";
    $sql[] = "CREATE TABLE IF NOT EXISTS admins (id $id, username $str NOT NULL UNIQUE, password_hash $str NOT NULL, created_at $now)$tail";
    $sql[] = "CREATE TABLE IF NOT EXISTS login_attempts (id $id, ip $str NOT NULL, created_at INT NOT NULL)$tail";
    $sql[] = "CREATE TABLE IF NOT EXISTS ships (id $id, sort_order INT NOT NULL DEFAULT 0, active TINYINT NOT NULL DEFAULT 1, tag $str, name $str NOT NULL, depart $str, return_time $str, extra $str, price $str, featured TINYINT NOT NULL DEFAULT 0, image $str NOT NULL DEFAULT '', subtitle $str NOT NULL DEFAULT '', price_round $str NOT NULL DEFAULT '', seats $str NOT NULL DEFAULT '', features TEXT)$tail";
    $sql[] = "CREATE TABLE IF NOT EXISTS packages (id $id, sort_order INT NOT NULL DEFAULT 0, active TINYINT NOT NULL DEFAULT 1, icon $str, name $str NOT NULL, summary $str, features TEXT, badge $str, hot TINYINT NOT NULL DEFAULT 0)$tail";
    $sql[] = "CREATE TABLE IF NOT EXISTS hotels (id $id, sort_order INT NOT NULL DEFAULT 0, active TINYINT NOT NULL DEFAULT 1, name $str NOT NULL, location $str NOT NULL DEFAULT '', summary TEXT, owner_id INT NULL, status VARCHAR(16) NOT NULL DEFAULT 'approved', reject_reason $str NOT NULL DEFAULT '')$tail";
    $sql[] = "CREATE TABLE IF NOT EXISTS owners (id $id, name $str NOT NULL, email $str NOT NULL UNIQUE, phone VARCHAR(32) NOT NULL, password_hash $str NOT NULL, status VARCHAR(16) NOT NULL DEFAULT 'pending', created_at $now)$tail";
    $sql[] = "CREATE TABLE IF NOT EXISTS hotel_posts (id $id, hotel_id INT NOT NULL, sort_order INT NOT NULL DEFAULT 0, title $str NOT NULL DEFAULT '', body TEXT, price VARCHAR(80) NOT NULL DEFAULT '')$tail";
    $sql[] = "CREATE TABLE IF NOT EXISTS post_images (id $id, post_id INT NOT NULL, path $str NOT NULL, sort_order INT NOT NULL DEFAULT 0)$tail";
    $sql[] = "CREATE TABLE IF NOT EXISTS whys (id $id, sort_order INT NOT NULL DEFAULT 0, active TINYINT NOT NULL DEFAULT 1, icon $str, title $str NOT NULL, body TEXT)$tail";
    $sql[] = "CREATE TABLE IF NOT EXISTS steps (id $id, sort_order INT NOT NULL DEFAULT 0, active TINYINT NOT NULL DEFAULT 1, title $str NOT NULL, body TEXT)$tail";
    $sql[] = "CREATE TABLE IF NOT EXISTS faqs (id $id, sort_order INT NOT NULL DEFAULT 0, active TINYINT NOT NULL DEFAULT 1, question $str NOT NULL, answer TEXT)$tail";
    $sql[] = "CREATE TABLE IF NOT EXISTS bookings (id $id, name $str NOT NULL, phone VARCHAR(32) NOT NULL, travel_date VARCHAR(16), persons INT NOT NULL DEFAULT 1, ticket_type $str, message TEXT, status VARCHAR(16) NOT NULL DEFAULT 'new', ip VARCHAR(64), created_at $now)$tail";
    return $sql;
}

// Adds columns introduced after the first release (safe to run repeatedly).
function migrate_schema(PDO $pdo, string $driver): void
{
    $add = ['ships' => [
        'image' => "VARCHAR(255) NOT NULL DEFAULT ''", 'subtitle' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'price_round' => "VARCHAR(255) NOT NULL DEFAULT ''", 'seats' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'features' => 'TEXT',
    ], 'hotels' => [
        'owner_id' => 'INT NULL', 'status' => "VARCHAR(16) NOT NULL DEFAULT 'approved'",
        'reject_reason' => "VARCHAR(255) NOT NULL DEFAULT ''",
    ]];
    foreach ($add as $table => $cols) {
        if ($driver === 'sqlite') {
            $have = array_column($pdo->query("PRAGMA table_info($table)")->fetchAll(), 'name');
        } else {
            $have = array_column($pdo->query("SHOW COLUMNS FROM $table")->fetchAll(), 'Field');
        }
        foreach ($cols as $c => $def) {
            if (!in_array($c, $have, true)) {
                $pdo->exec("ALTER TABLE $table ADD COLUMN $c " . ($driver === 'sqlite' ? str_replace('VARCHAR(255)', 'TEXT', $def) : $def));
            }
        }
    }
}

// One-time conversion of the older room/gallery model into posts (old tables are left untouched).
function migrate_posts(PDO $pdo, string $driver): void
{
    $has = $driver === 'sqlite'
        ? (bool)$pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='rooms'")->fetch()
        : (bool)$pdo->query("SHOW TABLES LIKE 'rooms'")->fetch();
    if (!$has || (int)$pdo->query('SELECT COUNT(*) FROM hotel_posts')->fetchColumn() > 0) {
        return;
    }
    $types = ['single' => 'Single Bed', 'couple' => 'Couple Bed', 'family' => 'Family Room'];
    $im = $pdo->prepare('SELECT path FROM room_images WHERE room_id = ? ORDER BY sort_order, id');
    $ins = $pdo->prepare('INSERT INTO hotel_posts (hotel_id, sort_order, title, body, price) VALUES (?,?,?,?,?)');
    $img = $pdo->prepare('INSERT INTO post_images (post_id, path, sort_order) VALUES (?,?,?)');
    foreach ($pdo->query('SELECT * FROM rooms ORDER BY hotel_id, sort_order, id')->fetchAll() as $n => $r) {
        $title = trim((string)$r['name']) !== '' ? $r['name'] : ($types[$r['type']] ?? 'Room');
        $parts = array_filter([$r['persons'], $r['beds'], $r['ac'] ? 'এসি' : 'নন-এসি', $r['breakfast'] ? 'নাস্তা ফ্রি' : '']);
        $body = trim(implode(' · ', $parts) . "\n" . $r['description']);
        $ins->execute([$r['hotel_id'], $n, $title, $body, $r['price'] > 0 ? '৳ ' . number_format((int)$r['price']) . ' / রাত' : '']);
        $pid = (int)$pdo->lastInsertId();
        $im->execute([$r['id']]);
        foreach ($im->fetchAll(PDO::FETCH_COLUMN) as $j => $path) {
            $img->execute([$pid, $path, $j]);
        }
    }
}

// Admin CRUD definitions. type: text | textarea | check | number | image
function crud_defs(): array
{
    return [
        'ships' => [
            'title' => 'সীপ ও টিকেট', 'label' => 'name',
            'fields' => [
                'image' => ['সীপ/কেবিনের ছবি (PNG/JPG/WebP, ২MB পর্যন্ত)', 'image'],
                'tag' => ['টাইপ ট্যাগ (যেমন VIP)', 'text'],
                'name' => ['সীপ/ক্লাসের নাম (যেমন: Lavender (01 Person))', 'text', true],
                'subtitle' => ['সাব-টাইটেল (যেমন: 1st Floor VIP Seats)', 'text'],
                'price_round' => ['রাউন্ড ট্রিপ ভাড়া (যেমন ৩,৫০০)', 'text'],
                'price' => ['সিঙ্গেল ট্রিপ ভাড়া (যেমন ১,৮০০)', 'text'],
                'depart' => ['ছাড়ার সময়', 'text'],
                'return_time' => ['ফেরার সময়', 'text'],
                'seats' => ['সিট সংখ্যা (যেমন: 70)', 'text'],
                'features' => ['ফিচারসমূহ (প্রতি লাইনে একটি)', 'textarea'],
                'featured' => ['হাইলাইট কার্ড', 'check'],
            ],
        ],
        'hotels' => ['title' => 'হোটেল লিস্টিং', 'label' => 'name', 'fields' => []],
        'packages' => [
            'title' => 'রাত্রিযাপন প্যাকেজ', 'label' => 'name',
            'fields' => [
                'icon' => ['আইকন (ইমোজি)', 'text'],
                'name' => ['প্যাকেজের নাম', 'text', true],
                'summary' => ['সংক্ষিপ্ত বিবরণ', 'text'],
                'features' => ['সুবিধাসমূহ (প্রতি লাইনে একটি)', 'textarea'],
                'badge' => ['ব্যাজ (যেমন জনপ্রিয়)', 'text'],
                'hot' => ['হাইলাইট কার্ড', 'check'],
            ],
        ],
        'whys' => [
            'title' => 'কেন আমরা (কার্ড)', 'label' => 'title',
            'fields' => [
                'icon' => ['আইকন (ইমোজি)', 'text'],
                'title' => ['শিরোনাম', 'text', true],
                'body' => ['বিবরণ', 'textarea'],
            ],
        ],
        'steps' => [
            'title' => 'বুকিং ধাপ', 'label' => 'title',
            'fields' => [
                'title' => ['শিরোনাম', 'text', true],
                'body' => ['বিবরণ', 'textarea'],
            ],
        ],
        'faqs' => [
            'title' => 'সাধারণ জিজ্ঞাসা (FAQ)', 'label' => 'question',
            'fields' => [
                'question' => ['প্রশ্ন', 'text', true],
                'answer' => ['উত্তর', 'textarea'],
            ],
        ],
    ];
}
