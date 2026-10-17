<?php
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
    $sql[] = "CREATE TABLE IF NOT EXISTS ships (id $id, sort_order INT NOT NULL DEFAULT 0, active TINYINT NOT NULL DEFAULT 1, tag $str, name $str NOT NULL, depart $str, return_time $str, extra $str, price $str, featured TINYINT NOT NULL DEFAULT 0)$tail";
    $sql[] = "CREATE TABLE IF NOT EXISTS packages (id $id, sort_order INT NOT NULL DEFAULT 0, active TINYINT NOT NULL DEFAULT 1, icon $str, name $str NOT NULL, summary $str, features TEXT, badge $str, hot TINYINT NOT NULL DEFAULT 0)$tail";
    $sql[] = "CREATE TABLE IF NOT EXISTS whys (id $id, sort_order INT NOT NULL DEFAULT 0, active TINYINT NOT NULL DEFAULT 1, icon $str, title $str NOT NULL, body TEXT)$tail";
    $sql[] = "CREATE TABLE IF NOT EXISTS steps (id $id, sort_order INT NOT NULL DEFAULT 0, active TINYINT NOT NULL DEFAULT 1, title $str NOT NULL, body TEXT)$tail";
    $sql[] = "CREATE TABLE IF NOT EXISTS faqs (id $id, sort_order INT NOT NULL DEFAULT 0, active TINYINT NOT NULL DEFAULT 1, question $str NOT NULL, answer TEXT)$tail";
    $sql[] = "CREATE TABLE IF NOT EXISTS bookings (id $id, name $str NOT NULL, phone VARCHAR(32) NOT NULL, travel_date VARCHAR(16), persons INT NOT NULL DEFAULT 1, ticket_type $str, message TEXT, status VARCHAR(16) NOT NULL DEFAULT 'new', ip VARCHAR(64), created_at $now)$tail";
    return $sql;
}

// Admin CRUD definitions. type: text | textarea | check | number
function crud_defs(): array
{
    return [
        'ships' => [
            'title' => 'সীপ ও টিকেট', 'label' => 'name',
            'fields' => [
                'tag' => ['টাইপ ট্যাগ (যেমন VIP)', 'text'],
                'name' => ['সীপের নাম', 'text', true],
                'depart' => ['ছাড়ার সময়', 'text'],
                'return_time' => ['ফেরার সময়', 'text'],
                'extra' => ['অতিরিক্ত সুবিধা (ইমোজি সহ)', 'text'],
                'price' => ['ভাড়া (যেমন ১,২০০)', 'text'],
                'featured' => ['হাইলাইট কার্ড', 'check'],
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
