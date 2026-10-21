<?php
// Dummy/sample data helpers for the admin. They only fill what is empty, so they are safe to run repeatedly.
require_once __DIR__ . '/hotels.php';

// Ships: fills empty fields and leftover form-label text. Returns the number of ships changed.
function ships_fill_dummy(PDO $pdo): int
{
    // Fill empty fields (and leftover form-label text such as "সাব-টাইটেল" / "...(যেমন ৩,৫০০)") with sample values.
            $isEmpty = function ($v) {
                $v = trim((string)$v);
                return $v === '' || mb_strpos($v, 'যেমন') !== false || $v === 'সাব-টাইটেল' || preg_match('/^[০0]{2}:[০0]{2}/u', $v);
            };
            $samples = [
                ['VIP', '১ম তলা VIP সিট', '৩,৫০০', '১,৮০০', '০৭:৩০ AM', '০৩:০০ PM', '৭০', "এসি কেবিন\nপ্রিমিয়াম সিট\nনাস্তা ও পানীয়\nপ্রার্থনা কক্ষ"],
                ['ডিলাক্স', '২য় তলা এসি সিট', '২,৮০০', '১,৫০০', '০৮:০০ AM', '০৩:৩০ PM', '১২০', "এসি সিট\nকার্পেটেড ফ্লোর\nখোলা ডেক"],
                ['সাধারণ', 'সাধারণ সিট', '২,২০০', '১,২০০', '০৯:০০ AM', '০৪:০০ PM', '২০০', "সাধারণ সিট\nখোলা ডেক\nখাবার পানি"],
            ];
            $cols = ['tag', 'subtitle', 'price_round', 'price', 'depart', 'return_time', 'seats', 'features'];
            $n = 0;
            foreach ($pdo->query('SELECT * FROM ships ORDER BY sort_order, id')->fetchAll() as $i => $row) {
                $set = [];
                foreach ($cols as $k => $c) {
                    $cur = $row[$c] ?? '';
                    if ($isEmpty($cur) || ($c === 'price' && preg_match('/^[০0][,০0]*$/u', trim((string)$cur)))) {
                        $set[$c] = $samples[$i % 3][$k];
                    }
                }
                if ($set) {
                    $sql = 'UPDATE ships SET ' . implode(', ', array_map(function ($c) { return "$c = ?"; }, array_keys($set))) . ' WHERE id = ?';
                    $pdo->prepare($sql)->execute(array_merge(array_values($set), [$row['id']]));
                    $n++;
                }
            }
    return $n;
}
