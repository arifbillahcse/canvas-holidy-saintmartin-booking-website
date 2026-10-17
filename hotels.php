<?php
require __DIR__ . '/includes/site.php';
$hotels = db()->query('SELECT * FROM hotels WHERE active = 1 ORDER BY sort_order, id')->fetchAll();
$locations = array_values(array_unique(array_filter(array_map(function ($h) { return $h['location']; }, $hotels))));
page_open(setting('hotels_page_title') ?: 'হোটেল লিস্টিং', setting('hotels_sub', 'সেন্ট মার্টিনে আপনার বাজেট অনুযায়ী সেরা হোটেল বেছে নিন।'), false);
?>
<main>
<section class="page-hero">
  <div class="container">
    <h1><?= e(setting('hotels_title', 'থাকার হোটেল ও রিসোর্ট')) ?></h1>
    <p><?= e(setting('hotels_sub', 'সেন্ট মার্টিনে আপনার বাজেট অনুযায়ী সেরা হোটেল বেছে নিন।')) ?></p>
  </div>
</section>

<section class="section" id="hotel-list">
  <div class="container">
    <div class="filters" id="hotelFilters">
      <input type="search" id="hSearch" placeholder="🔍 হোটেলের নাম বা এলাকা খুঁজুন" aria-label="হোটেল খুঁজুন">
      <select id="hStars" aria-label="স্টার">
        <option value="0">সব স্টার</option>
        <?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= bn_digits($i) ?> স্টার ও তার বেশি</option><?php endfor; ?>
      </select>
      <?php if (count($locations) > 1): ?>
      <select id="hLoc" aria-label="এলাকা">
        <option value="">সব এলাকা</option>
        <?php foreach ($locations as $l): ?><option><?= e($l) ?></option><?php endforeach; ?>
      </select>
      <?php endif; ?>
    </div>

    <?php if ($hotels): ?>
    <div class="grid grid--3" id="hotelGrid">
      <?php foreach ($hotels as $h) { hotel_card($h); } ?>
    </div>
    <p class="note" id="hEmpty" hidden>কোনো হোটেল পাওয়া যায়নি। অন্য ফিল্টার চেষ্টা করুন বা কল করুন।</p>
    <?php else: ?>
    <p class="note">শীঘ্রই হোটেল তালিকা যুক্ত হবে। বিস্তারিত জানতে কল করুন।</p>
    <?php endif; ?>

    <div class="cta-band">
      <p>পছন্দের হোটেল খুঁজে পাচ্ছেন না? আমাদের জানান, আপনার বাজেট অনুযায়ী ব্যবস্থা করে দেব।</p>
      <a class="btn btn--primary" href="https://wa.me/<?= e(wa_number()) ?>" target="_blank" rel="noopener">💬 WhatsApp-এ জানান</a>
    </div>
  </div>
</section>
</main>
<?php page_close(); ?>
