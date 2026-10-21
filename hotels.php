<?php
require __DIR__ . '/includes/site.php';
$all = db()->query("SELECT * FROM hotels WHERE active = 1 AND status = 'approved' ORDER BY sort_order, id")->fetchAll();
$hotel = null;
foreach ($all as $h) {
    if ((int)$h['id'] === (int)($_GET['h'] ?? 0)) {
        $hotel = $h;
    }
}

if (!$hotel) {
    // ----- Hotel list (default view) -----
    $locations = array_values(array_unique(array_filter(array_map(function ($h) { return $h['location']; }, $all))));
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
    <?php if ($all): ?>
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
    <div class="grid grid--3" id="hotelGrid">
      <?php foreach ($all as $h) { hotel_card($h); } ?>
    </div>
    <p class="note" id="hEmpty" hidden>কোনো হোটেল পাওয়া যায়নি। অন্য ফিল্টার চেষ্টা করুন বা কল করুন।</p>
    <?php else: ?>
    <p class="note">শীঘ্রই হোটেল তালিকা যুক্ত হবে। বিস্তারিত জানতে কল করুন।</p>
    <?php endif; ?>

    <div class="cta-band">
      <p>পছন্দের হোটেল খুঁজে পাচ্ছেন না? আমাদের জানান, আপনার বাজেট অনুযায়ী ব্যবস্থা করে দেব।</p>
      <a class="btn btn--wa" href="https://wa.me/<?= e(wa_number()) ?>" target="_blank" rel="noopener"><?= wa_icon() ?> WhatsApp-এ জানান</a>
    </div>
    <div class="cta-band">
      <p><strong>হোটেল মালিক?</strong> বিনামূল্যে রেজিস্ট্রেশন করে আপনার হোটেল আমাদের সাইটে যুক্ত করুন।</p>
      <a class="btn btn--ghost-dark" href="owner/register.php">🏨 হোটেল যুক্ত করুন</a>
    </div>
  </div>
</section>
</main>
<?php
    page_close();
    return;
}

$snap = hotel_snapshot((int)$hotel['id']);
$gallery = $snap['gallery'];
$rooms = $snap['rooms'];
$cover = $gallery[0] ?? '';
$minRoom = 0;
foreach ($rooms as $r) {
    if ($r['price'] > 0 && (!$minRoom || $r['price'] < $minRoom)) {
        $minRoom = (int)$r['price'];
    }
}
$fromPrice = $minRoom ? fmt_price($minRoom) : (string)$hotel['price'];
$amenities = lines_of($hotel['amenities']);
$stars = max(0, min(5, (int)$hotel['stars']));
$tel = tel_href();
$wa = wa_number();
$others = array_values(array_filter($all, function ($h) use ($hotel) { return $h['id'] !== $hotel['id']; }));

// Room data for the popup (rendered with JSON_HEX_* so it is safe inside a <script> tag).
$roomJson = [];
foreach ($rooms as $r) {
    $roomJson[] = [
        'id' => (int)$r['id'], 'label' => room_label($r), 'icon' => room_icon($r), 'price' => fmt_price((int)$r['price']), 'hasPrice' => $r['price'] > 0,
        'persons' => $r['persons'], 'beds' => $r['beds'], 'ac' => (bool)$r['ac'], 'breakfast' => (bool)$r['breakfast'],
        'description' => $r['description'], 'images' => $r['images'],
    ];
}

page_open($hotel['name'] . ' | ' . brand_name(), $hotel['summary'] !== '' ? $hotel['summary'] : setting('hotels_sub'), false, true, 'hotel-page');
?>
<main>
<section class="hh" id="hotel-top">
  <div class="hh__bg"<?= $cover ? ' style="background-image:url(\'' . e($cover) . '\')"' : '' ?>></div>
  <div class="hh__shade"></div>
  <div class="container hh__in">
    <nav class="pills" aria-label="পেজ নেভিগেশন"><a href="hotels.php">← সব হোটেল</a></nav>
    <div class="hh__title">
      <div>
        <h1><?= e($hotel['name']) ?></h1>
        <p><span class="hh__stars"><?= str_repeat('★', $stars) ?></span>
          <?php if ($hotel['rating'] !== ''): ?> <span class="hh__rate"><?= e($hotel['rating']) ?></span><?php endif; ?>
          <?php if ($hotel['location'] !== ''): ?> · 📍 <?= e($hotel['location']) ?><?php endif; ?>
          <?php if ($hotel['badge'] !== ''): ?> · <?= e($hotel['badge']) ?><?php endif; ?></p>
      </div>
      <?php if ($fromPrice !== ''): ?><div class="hh__price"><small>শুরু</small><strong>৳ <?= e($fromPrice) ?></strong><small>/ রাত</small></div><?php endif; ?>
    </div>
  </div>
</section>

<div class="snav" id="snav">
  <div class="container snav__in">
    <?php if ($rooms): ?><a href="#rooms" class="on">রুম ও ভাড়া</a><?php endif; ?>
    <?php if ($amenities): ?><a href="#amenities">সুবিধা</a><?php endif; ?>
    <?php if (count($gallery) > 0): ?><a href="#gallery">গ্যালারি</a><?php endif; ?>
    <a href="#location">লোকেশন</a>
    <span class="snav__cta">
      <a href="<?= e($tel) ?>" class="snav__call">📞 কল</a>
      <a class="btn btn--wa btn--small-x" href="https://wa.me/<?= e($wa) ?>?text=<?= e(rawurlencode('আসসালামু আলাইকুম, ' . brand_name() . "।\nআমি \"" . $hotel['name'] . '" হোটেল সম্পর্কে জানতে চাই।')) ?>" target="_blank" rel="noopener"><?= wa_icon() ?> বুক করুন</a>
    </span>
  </div>
</div>

<div class="container hotel-sections">
  <?php if ($hotel['summary'] !== ''): ?><p class="hotel-about"><?= nl2br(e($hotel['summary'])) ?></p><?php endif; ?>

  <section id="rooms">
    <h2>রুমের ধরন বেছে নিন</h2>
    <?php if ($rooms): ?>
    <div class="rgrid">
      <?php foreach ($rooms as $r): $img = $r['images'][0] ?? ''; ?>
      <article class="rcard" role="button" tabindex="0" data-room="<?= (int)$r['id'] ?>" aria-label="<?= e(room_label($r)) ?> — বিস্তারিত দেখুন">
        <div class="rcard__img"<?= $img ? ' style="background-image:url(\'' . e($img) . '\')"' : '' ?>><?php if (!$img): ?><span aria-hidden="true"><?= e(room_icon($r)) ?></span><?php endif; ?>
          <?php if (count($r['images']) > 1): ?><em>📷 <?= bn_digits(count($r['images'])) ?></em><?php endif; ?></div>
        <div class="rcard__body">
          <div class="rcard__top"><h3><?= e(room_label($r)) ?></h3><?php if ($r['price'] > 0): ?><b>৳ <?= fmt_price((int)$r['price']) ?></b><?php endif; ?></div>
          <p><?= e(trim($r['persons'] . ($r['persons'] !== '' && $r['beds'] !== '' ? ' · ' : '') . $r['beds'] . ' · ' . ($r['ac'] ? 'এসি' : 'নন-এসি'), ' ·')) ?></p>
          <span class="rcard__more">বিস্তারিত ও ছবি দেখুন →</span>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="note">ভাড়া ও রুমের তথ্যের জন্য আমাদের কল করুন বা WhatsApp-এ মেসেজ দিন।</p>
    <?php endif; ?>
  </section>

  <?php if ($amenities): ?>
  <section id="amenities">
    <h2>সুযোগ-সুবিধা</h2>
    <div class="amgrid"><?php foreach ($amenities as $a): ?><div><b><?= amenity_icon($a) ?></b><?= e($a) ?></div><?php endforeach; ?></div>
  </section>
  <?php endif; ?>

  <?php if ($gallery): ?>
  <section id="gallery">
    <h2>গ্যালারি</h2>
    <div class="ggrid"><?php foreach ($gallery as $i => $img): ?>
      <button type="button" class="ggrid__i" data-lightbox="<?= (int)$i ?>" style="background-image:url('<?= e($img) ?>')" aria-label="ছবি <?= bn_digits($i + 1) ?> বড় করে দেখুন"></button>
    <?php endforeach; ?></div>
  </section>
  <?php endif; ?>

  <section id="location">
    <h2>লোকেশন</h2>
    <iframe class="hmap" title="হোটেলের লোকেশন" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
      src="https://www.google.com/maps?q=<?= e(urlencode($hotel['name'] . ' ' . $hotel['location'])) ?>&output=embed"></iframe>
  </section>

  <?php if ($others): ?>
  <section id="others">
    <h2>আরও হোটেল</h2>
    <div class="grid grid--3"><?php foreach (array_slice($others, 0, 6) as $h) { hotel_card($h); } ?></div>
  </section>
  <?php endif; ?>

  <div class="cta-band">
    <p><strong>হোটেল মালিক?</strong> বিনামূল্যে রেজিস্ট্রেশন করে আপনার হোটেল আমাদের সাইটে যুক্ত করুন।</p>
    <a class="btn btn--ghost-dark" href="owner/register.php">🏨 হোটেল যুক্ত করুন</a>
  </div>
</div>
</main>

<!-- Room popup: shows only the room that was clicked -->
<div class="rm" id="roomModal" hidden role="dialog" aria-modal="true" aria-labelledby="rmTitle"
     data-wa="<?= e($wa) ?>" data-tel="<?= e($tel) ?>" data-hotel="<?= e($hotel['name']) ?>" data-brand="<?= e(brand_name()) ?>">
  <div class="rm__back" data-close></div>
  <div class="rm__box">
    <button type="button" class="rm__x" data-close aria-label="বন্ধ করুন">✕</button>
    <p class="rm__crumb"><?= e($hotel['name']) ?> › <span id="rmCrumb"></span></p>
    <div class="rm__grid">
      <div class="rm__media">
        <div class="rm__big" id="rmBig"></div>
        <div class="rm__thumbs" id="rmThumbs"></div>
      </div>
      <div class="rm__info">
        <h3 id="rmTitle"></h3>
        <p class="rm__desc" id="rmDesc"></p>
        <dl class="rm__spec" id="rmSpec"></dl>
        <div class="rm__price"><span>প্রতি রাতের ভাড়া</span><strong id="rmPrice"></strong></div>
        <a class="btn btn--wa btn--block" id="rmWa" target="_blank" rel="noopener"><?= wa_icon() ?> এই রুমের জন্য WhatsApp-এ জানুন</a>
        <a class="btn btn--ghost-dark btn--block" id="rmCall" href="<?= e($tel) ?>">📞 কল করুন</a>
      </div>
    </div>
  </div>
</div>

<!-- Gallery lightbox -->
<div class="lb" id="lightbox" hidden role="dialog" aria-modal="true" aria-label="গ্যালারি">
  <button type="button" class="lb__x" data-lbclose aria-label="বন্ধ করুন">✕</button>
  <button type="button" class="lb__n lb__n--p" data-lbprev aria-label="আগের ছবি">‹</button>
  <img id="lbImg" alt="">
  <button type="button" class="lb__n lb__n--n" data-lbnext aria-label="পরের ছবি">›</button>
</div>

<div class="mbar">
  <a class="btn btn--ghost-dark" href="<?= e($tel) ?>">📞 কল করুন</a>
  <a class="btn btn--wa" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener"><?= wa_icon() ?> WhatsApp</a>
</div>

<script type="application/json" id="roomData"><?= json_encode(['rooms' => $roomJson, 'gallery' => $gallery], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php page_close(); ?>
