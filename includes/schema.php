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
    $sql[] = "CREATE TABLE IF NOT EXISTS ships (id $id, sort_order INT NOT NULL DEFAULT 0, active TINYINT NOT NULL DEFAULT 1, tag $str, name $str NOT NULL, depart $str, return_time $str, extra $str, price $str, featured TINYINT NOT NULL DEFAULT 0, image $str NOT NULL DEFAULT '', subtitle $str NOT NULL DEFAULT '', price_round $str NOT NULL DEFAULT '', seats $str NOT NULL DEFAULT '', features TEXT, ship_name $str NOT NULL DEFAULT '')$tail";
    $sql[] = "CREATE TABLE IF NOT EXISTS packages (id $id, sort_order INT NOT NULL DEFAULT 0, active TINYINT NOT NULL DEFAULT 1, icon $str, name $str NOT NULL, summary $str, features TEXT, badge $str, hot TINYINT NOT NULL DEFAULT 0)$tail";
    $sql[] = "CREATE TABLE IF NOT EXISTS hotels (id $id, sort_order INT NOT NULL DEFAULT 0, active TINYINT NOT NULL DEFAULT 1, image $str NOT NULL DEFAULT '', name $str NOT NULL, location $str NOT NULL DEFAULT '', stars INT NOT NULL DEFAULT 3, rating $str NOT NULL DEFAULT '', price $str NOT NULL DEFAULT '', summary TEXT, amenities TEXT, badge $str NOT NULL DEFAULT '', featured TINYINT NOT NULL DEFAULT 0, owner_id INT NULL, status VARCHAR(16) NOT NULL DEFAULT 'approved', reject_reason $str NOT NULL DEFAULT '', pending_data TEXT)$tail";
    $sql[] = "CREATE TABLE IF NOT EXISTS owners (id $id, name $str NOT NULL, email $str NOT NULL UNIQUE, phone VARCHAR(32) NOT NULL, password_hash $str NOT NULL, status VARCHAR(16) NOT NULL DEFAULT 'pending', created_at $now)$tail";
    $sql[] = "CREATE TABLE IF NOT EXISTS hotel_images (id $id, hotel_id INT NOT NULL, path $str NOT NULL, sort_order INT NOT NULL DEFAULT 0)$tail";
    $sql[] = "CREATE TABLE IF NOT EXISTS rooms (id $id, hotel_id INT NOT NULL, sort_order INT NOT NULL DEFAULT 0, type VARCHAR(16) NOT NULL DEFAULT 'other', name $str NOT NULL DEFAULT '', price INT NOT NULL DEFAULT 0, persons $str NOT NULL DEFAULT '', beds $str NOT NULL DEFAULT '', ac TINYINT NOT NULL DEFAULT 1, breakfast TINYINT NOT NULL DEFAULT 0, description TEXT)$tail";
    $sql[] = "CREATE TABLE IF NOT EXISTS room_images (id $id, room_id INT NOT NULL, path $str NOT NULL, sort_order INT NOT NULL DEFAULT 0)$tail";
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
        'features' => 'TEXT', 'ship_name' => "VARCHAR(255) NOT NULL DEFAULT ''",
    ], 'hotels' => [
        'owner_id' => 'INT NULL', 'status' => "VARCHAR(16) NOT NULL DEFAULT 'approved'",
        'reject_reason' => "VARCHAR(255) NOT NULL DEFAULT ''", 'pending_data' => 'TEXT',
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

// Admin CRUD definitions. type: text | textarea | check | number | image
function crud_defs(): array
{
    return [
        'ships' => [
            'title' => 'সীপ ও টিকেট', 'label' => 'name',
            'list_cols' => ['ship_name' => 'সীপের নাম'], // extra columns in the admin list
            'fields' => [
                'image' => ['সীপ/কেবিনের ছবি (PNG/JPG/WebP, ২MB পর্যন্ত)', 'image'],
                'tag' => ['টাইপ ট্যাগ (যেমন VIP)', 'text'],
                'ship_name' => ['সীপের নাম (যেমন: MV Karnafuly Express) — শিরোনামের উপরে বড় করে দেখাবে', 'text'],
                'name' => ['শিরোনাম (যেমন: Lavender (01 Person))', 'text', true],
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
        'hotels' => [
            'title' => 'হোটেল লিস্টিং', 'label' => 'name',
            'fields' => [
                'image' => ['হোটেলের ছবি (PNG/JPG/WebP, ২MB পর্যন্ত)', 'image'],
                'name' => ['হোটেলের নাম', 'text', true],
                'location' => ['এলাকা/লোকেশন (যেমন: সেন্ট মার্টিন, জেটি ঘাট)', 'text'],
                'stars' => ['স্টার (১-৫)', 'number'],
                'rating' => ['রেটিং (যেমন: ৪.৫/৫)', 'text'],
                'price' => ['প্রতি রাতের ভাড়া (যেমন: ২,৫০০)', 'text'],
                'summary' => ['সংক্ষিপ্ত বিবরণ', 'textarea'],
                'amenities' => ['সুবিধাসমূহ (প্রতি লাইনে একটি)', 'textarea'],
                'badge' => ['ব্যাজ (যেমন: সী-ভিউ)', 'text'],
                'featured' => ['হোমপেজে দেখান', 'check'],
            ],
        ],
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
