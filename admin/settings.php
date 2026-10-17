<?php
require __DIR__ . '/_boot.php';
require __DIR__ . '/_layout.php';
admin_require();

// key => [label, type]   types: text | textarea | check | image
$groups = [
    'ব্র্যান্ড ও লোগো' => [
        'logo' => ['লোগো ছবি (PNG/JPG/WebP, ২MB পর্যন্ত; না দিলে নিচের নাম দেখাবে)', 'image'],
        'favicon' => ['ফেভিকন (PNG/ICO)', 'image'],
        'site_name_a' => ['সাইটের নাম (প্রথম অংশ)', 'text'],
        'site_name_b' => ['সাইটের নাম (রঙিন অংশ)', 'text'],
        'meta_title' => ['পেজ টাইটেল (SEO)', 'text'],
        'meta_description' => ['মেটা ডিসক্রিপশন (SEO)', 'textarea'],
    ],
    'যোগাযোগ' => [
        'phone' => ['ফোন নম্বর (01XXXXXXXXX)', 'text'],
        'whatsapp' => ['WhatsApp নম্বর (খালি রাখলে ফোন নম্বর)', 'text'],
        'contact_person' => ['যোগাযোগকারীর নাম', 'text'],
        'address' => ['ঠিকানা', 'textarea'],
        'map_query' => ['গুগল ম্যাপ সার্চ টেক্সট (ইংরেজিতে ভালো)', 'text'],
        'facebook' => ['Facebook লিংক', 'text'],
        'instagram' => ['Instagram লিংক', 'text'],
        'youtube' => ['YouTube লিংক', 'text'],
    ],
    'হিরো ও নোটিশ' => [
        'hero_badge' => ['ব্যাজ টেক্সট', 'text'],
        'hero_title_1' => ['শিরোনাম লাইন ১', 'text'],
        'hero_title_2' => ['শিরোনাম লাইন ২', 'text'],
        'typed_words' => ['টাইপিং শব্দ (প্রতি লাইনে একটি)', 'textarea'],
        'hero_sub' => ['সাব টেক্সট', 'textarea'],
        'announce_on' => ['নোটিশ বার দেখান', 'check'],
        'announce_text' => ['নোটিশ টেক্সট', 'textarea'],
    ],
    'সেকশন শিরোনাম' => [
        'why_title' => ['কেন আমরা — শিরোনাম', 'text'],
        'why_sub' => ['কেন আমরা — সাব টেক্সট', 'text'],
        'ships_title' => ['সীপ — শিরোনাম', 'text'],
        'ships_sub' => ['সীপ — সাব টেক্সট', 'text'],
        'ships_note' => ['সীপ — নোট', 'text'],
        'hotels_title' => ['হোটেল — শিরোনাম (হোমপেজ ও হোটেল পেজ)', 'text'],
        'hotels_sub' => ['হোটেল — সাব টেক্সট', 'text'],
        'hotels_page_title' => ['হোটেল পেজের ব্রাউজার টাইটেল (SEO)', 'text'],
        'packages_title' => ['প্যাকেজ — শিরোনাম', 'text'],
        'packages_sub' => ['প্যাকেজ — সাব টেক্সট', 'text'],
        'how_title' => ['বুকিং ধাপ — শিরোনাম', 'text'],
        'how_sub' => ['বুকিং ধাপ — সাব টেক্সট', 'text'],
        'booking_title' => ['বুকিং ফর্ম — শিরোনাম', 'text'],
        'booking_text' => ['বুকিং ফর্ম — টেক্সট', 'textarea'],
        'faq_title' => ['FAQ — শিরোনাম', 'text'],
        'footer_text' => ['ফুটার কপিরাইট টেক্সট (© বছর এর পরে)', 'text'],
        'footer_tagline' => ['ফুটার ট্যাগলাইন', 'text'],
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_check()) {
            throw new RuntimeException('সেশন মেয়াদোত্তীর্ণ, আবার চেষ্টা করুন।');
        }
        $phone = trim((string)($_POST['phone'] ?? ''));
        if ($phone !== '' && strlen(phone_digits($phone)) < 10) {
            throw new RuntimeException('সঠিক ফোন নম্বর দিন।');
        }
        foreach (['facebook', 'instagram', 'youtube'] as $k) {
            $u = trim((string)($_POST[$k] ?? ''));
            if ($u !== '' && !preg_match('#^https?://#i', $u)) {
                throw new RuntimeException(strtoupper($k) . ' লিংক http:// বা https:// দিয়ে শুরু হতে হবে।');
            }
        }
        $pdo = db();
        $pdo->beginTransaction();
        foreach ($groups as $fields) {
            foreach ($fields as $key => [$label, $type]) {
                if ($type === 'image') {
                    $old = setting($key);
                    if (!empty($_POST['remove_' . $key])) {
                        if ($old) {
                            delete_upload($old);
                        }
                        setting_save($key, '');
                    }
                    $exts = $key === 'favicon' ? ['png', 'jpg', 'jpeg', 'webp', 'gif', 'ico'] : ['png', 'jpg', 'jpeg', 'webp', 'gif'];
                    $path = save_image_upload($_FILES[$key] ?? ['error' => UPLOAD_ERR_NO_FILE], $exts);
                    if ($path) {
                        if ($old) {
                            delete_upload($old);
                        }
                        setting_save($key, $path);
                    }
                } elseif ($type === 'check') {
                    setting_save($key, empty($_POST[$key]) ? '0' : '1');
                } else {
                    setting_save($key, trim((string)($_POST[$key] ?? '')));
                }
            }
        }
        $pdo->commit();
        flash('সেটিংস সংরক্ষিত হয়েছে।');
    } catch (Throwable $ex) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        flash($ex instanceof RuntimeException ? $ex->getMessage() : 'সংরক্ষণ ব্যর্থ হয়েছে।', 'err');
    }
    header('Location: settings.php');
    exit;
}

admin_head('সাইট সেটিংস');
?>
<form method="post" enctype="multipart/form-data" class="form">
  <?= csrf_field() ?>
  <?php foreach ($groups as $gname => $fields): ?>
  <fieldset><legend><?= e($gname) ?></legend>
    <?php foreach ($fields as $key => [$label, $type]): ?>
      <?php if ($type === 'image'): $cur = setting($key); ?>
        <label><?= e($label) ?>
          <?php if ($cur): ?><span class="preview"><img src="../<?= e($cur) ?>" alt=""> <span class="chk"><input type="checkbox" name="remove_<?= e($key) ?>" value="1"> মুছে ফেলুন</span></span><?php endif; ?>
          <input type="file" name="<?= e($key) ?>" accept="image/*">
        </label>
      <?php elseif ($type === 'check'): ?>
        <label class="chk"><input type="checkbox" name="<?= e($key) ?>" value="1" <?= setting($key) === '1' ? 'checked' : '' ?>> <?= e($label) ?></label>
      <?php elseif ($type === 'textarea'): ?>
        <label><?= e($label) ?><textarea name="<?= e($key) ?>" rows="3"><?= e(setting($key)) ?></textarea></label>
      <?php else: ?>
        <label><?= e($label) ?><input name="<?= e($key) ?>" value="<?= e(setting($key)) ?>"></label>
      <?php endif; ?>
    <?php endforeach; ?>
  </fieldset>
  <?php endforeach; ?>
  <button class="btn">সংরক্ষণ করুন</button>
</form>
<?php admin_foot();
