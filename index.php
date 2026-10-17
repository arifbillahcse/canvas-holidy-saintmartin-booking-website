<?php
require __DIR__ . '/includes/helpers.php';
session_boot(); // start before output so the booking form can carry a CSRF token
$tel = '+' . phone_intl(setting('phone'));
$telText = setting('phone');
$wa = wa_number();
$waLink = 'https://wa.me/' . $wa;
$ships = db()->query('SELECT * FROM ships WHERE active = 1 ORDER BY sort_order, id')->fetchAll();
$packages = db()->query('SELECT * FROM packages WHERE active = 1 ORDER BY sort_order, id')->fetchAll();
$whys = db()->query('SELECT * FROM whys WHERE active = 1 ORDER BY sort_order, id')->fetchAll();
$steps = db()->query('SELECT * FROM steps WHERE active = 1 ORDER BY sort_order, id')->fetchAll();
$faqs = db()->query('SELECT * FROM faqs WHERE active = 1 ORDER BY sort_order, id')->fetchAll();
$typed = array_values(array_filter(array_map('trim', preg_split('/\R/u', setting('typed_words')))));
$lines = function ($t) { return array_values(array_filter(array_map('trim', preg_split('/\R/u', (string)$t)))); };
$brand = setting('site_name_a') . ' ' . setting('site_name_b');
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(setting('meta_title')) ?></title>
<meta name="description" content="<?= e(setting('meta_description')) ?>">
<?php if (setting('favicon')): ?><link rel="icon" href="<?= e(setting('favicon')) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body>

<!-- 1. HEADER -->
<header class="header" id="top">
  <div class="container header__inner">
    <a href="#top" class="logo">
      <?php if (setting('logo')): ?><img class="logo__img" src="<?= e(setting('logo')) ?>" alt="<?= e($brand) ?>"><?php else: ?><?= e(setting('site_name_a')) ?> <span><?= e(setting('site_name_b')) ?></span><?php endif; ?>
    </a>
    <button class="menu-toggle" aria-label="মেনু" aria-expanded="false"><span></span><span></span><span></span></button>
    <nav class="nav" id="nav">
      <a href="#hero">হোম</a>
      <a href="#why">কেন আমরা</a>
      <a href="#ships">সীপ ও টিকেট</a>
      <a href="#packages">প্যাকেজ</a>
      <a href="#faq">জিজ্ঞাসা</a>
      <a href="#contact">যোগাযোগ</a>
    </nav>
    <a href="tel:<?= e($tel) ?>" class="btn btn--call">📞 কল করুন</a>
  </div>
</header>

<main>

<!-- 2. HERO (animated) -->
<section class="hero" id="hero">
  <div class="hero__sky">
    <div class="sun"></div>
    <div class="cloud cloud--1"></div>
    <div class="cloud cloud--2"></div>
    <div class="cloud cloud--3"></div>
    <div class="bird bird--1">🕊️</div>
    <div class="bird bird--2">🕊️</div>
  </div>

  <div class="container hero__content">
    <p class="hero__badge"><?= e(setting('hero_badge')) ?></p>
    <h1><?= e(setting('hero_title_1')) ?><br><?= e(setting('hero_title_2')) ?> <span class="typed" data-words="<?= e(json_encode($typed, JSON_UNESCAPED_UNICODE)) ?>"></span></h1>
    <p class="hero__sub"><?= e(setting('hero_sub')) ?></p>
    <div class="hero__cta">
      <a href="#booking" class="btn btn--primary">টিকেট বুক করুন</a>
      <a href="tel:<?= e($tel) ?>" class="btn btn--ghost">📞 সরাসরি কল</a>
    </div>
    <ul class="hero__stats">
      <li><strong data-count="100">0</strong>% ডাইরেক্ট এজেন্ট</li>
      <li><strong data-count="2026">0</strong> সিজন</li>
      <li><strong>২৪/৭</strong> সাপোর্ট</li>
    </ul>
  </div>

  <!-- Animated scene: ship + waves + floating icons -->
  <div class="hero__scene" aria-hidden="true">
    <div class="float-icon fi--1">⚓</div>
    <div class="float-icon fi--2">🐚</div>
    <div class="float-icon fi--3">🌴</div>
    <div class="float-icon fi--4">🐠</div>
    <div class="float-icon fi--5">🎫</div>
    <div class="ship">🚢</div>
    <svg class="wave wave--1" viewBox="0 0 1440 120" preserveAspectRatio="none"><path d="M0,60 C240,120 480,0 720,60 C960,120 1200,0 1440,60 L1440,120 L0,120 Z"/></svg>
    <svg class="wave wave--2" viewBox="0 0 1440 120" preserveAspectRatio="none"><path d="M0,70 C240,10 480,110 720,70 C960,30 1200,110 1440,70 L1440,120 L0,120 Z"/></svg>
    <svg class="wave wave--3" viewBox="0 0 1440 120" preserveAspectRatio="none"><path d="M0,80 C240,120 480,40 720,80 C960,120 1200,40 1440,80 L1440,120 L0,120 Z"/></svg>
  </div>
</section>

<!-- 3. ANNOUNCEMENT -->
<?php if (setting('announce_on') === '1' && setting('announce_text') !== ''): ?>
<section class="announce">
  <div class="container announce__inner">
    <span class="announce__pulse"></span>
    <p><strong>নোটিশ:</strong> <?= e(setting('announce_text')) ?></p>
    <a href="#booking" class="btn btn--small">আগাম বুক করুন</a>
  </div>
</section>
<?php endif; ?>

<!-- 4. WHY US -->
<section class="section" id="why">
  <div class="container">
    <div class="section__head">
      <h2><?= e(setting('why_title')) ?></h2>
      <p><?= e(setting('why_sub')) ?></p>
    </div>
    <div class="grid grid--4">
      <?php foreach ($whys as $w): ?>
      <article class="card"><div class="card__icon"><?= e($w['icon']) ?></div><h3><?= e($w['title']) ?></h3><p><?= e($w['body']) ?></p></article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- 5. SHIPS & TICKETS -->
<section class="section section--alt" id="ships">
  <div class="container">
    <div class="section__head">
      <h2><?= e(setting('ships_title')) ?></h2>
      <p><?= e(setting('ships_sub')) ?></p>
    </div>
    <div class="grid grid--3">
      <?php foreach ($ships as $sh): ?>
      <article class="ticket<?= $sh['featured'] ? ' ticket--featured' : '' ?>">
        <div class="ticket__top"><?php if ($sh['tag'] !== ''): ?><span class="tag"><?= e($sh['tag']) ?></span><?php endif; ?><h3><?= e($sh['name']) ?></h3></div>
        <ul>
          <?php if ($sh['depart'] !== ''): ?><li>🕖 ছাড়ার সময়: <?= e($sh['depart']) ?></li><?php endif; ?>
          <?php if ($sh['return_time'] !== ''): ?><li>🕓 ফেরার সময়: <?= e($sh['return_time']) ?></li><?php endif; ?>
          <?php if ($sh['extra'] !== ''): ?><li><?= e($sh['extra']) ?></li><?php endif; ?>
        </ul>
        <div class="ticket__price">৳ <?= e($sh['price']) ?> <small>থেকে</small></div>
        <a href="#booking" class="btn btn--primary btn--block">বুক করুন</a>
      </article>
      <?php endforeach; ?>
    </div>
    <?php if (setting('ships_note') !== ''): ?><p class="note"><?= e(setting('ships_note')) ?></p><?php endif; ?>
  </div>
</section>

<!-- 6. PACKAGES -->
<section class="section" id="packages">
  <div class="container">
    <div class="section__head">
      <h2><?= e(setting('packages_title')) ?></h2>
      <p><?= e(setting('packages_sub')) ?></p>
    </div>
    <div class="grid grid--3">
      <?php foreach ($packages as $p): ?>
      <article class="package<?= $p['hot'] ? ' package--hot' : '' ?>">
        <?php if ($p['badge'] !== ''): ?><div class="package__badge"><?= e($p['badge']) ?></div><?php endif; ?>
        <div class="package__icon"><?= e($p['icon']) ?></div><h3><?= e($p['name']) ?></h3><p><?= e($p['summary']) ?></p>
        <ul><?php foreach ($lines($p['features']) as $f): ?><li>✔ <?= e($f) ?></li><?php endforeach; ?></ul>
        <a href="#booking" class="btn <?= $p['hot'] ? 'btn--primary' : 'btn--ghost-dark' ?> btn--block">বিস্তারিত জানুন</a>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- 7. HOW TO BOOK -->
<section class="section section--alt" id="how">
  <div class="container">
    <div class="section__head">
      <h2><?= e(setting('how_title')) ?></h2>
      <p><?= e(setting('how_sub')) ?></p>
    </div>
    <ol class="steps">
      <?php foreach ($steps as $i => $st): ?>
      <li class="step"><span class="step__no"><?= bn_digits($i + 1) ?></span><h3><?= e($st['title']) ?></h3><p><?= e($st['body']) ?></p></li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>

<!-- 8. BOOKING FORM -->
<section class="section" id="booking">
  <div class="container booking">
    <div class="booking__info">
      <h2><?= e(setting('booking_title')) ?></h2>
      <p><?= e(setting('booking_text')) ?></p>
      <ul>
        <li>📞 ফোন: <a href="tel:<?= e($tel) ?>"><?= e($telText) ?></a></li>
        <li>💬 WhatsApp: <a href="<?= e($waLink) ?>"><?= e(setting('whatsapp') ?: $telText) ?></a></li>
        <li>👤 <?= e(setting('contact_person')) ?>, <?= e($brand) ?></li>
      </ul>
    </div>
    <form class="form" id="bookingForm" novalidate data-wa="<?= e($wa) ?>" data-biz="<?= e($brand) ?>" data-action="book.php">
      <?= csrf_field() ?>
      <label>আপনার নাম<input type="text" name="name" placeholder="যেমন: রাকিব হাসান" required></label>
      <label>মোবাইল নম্বর<input type="tel" name="phone" placeholder="01XXXXXXXXX" required></label>
      <div class="form__row">
        <label>ভ্রমণের তারিখ<input type="date" name="date" required></label>
        <label>যাত্রী সংখ্যা<input type="number" name="persons" min="1" value="1" required></label>
      </div>
      <label>টিকেটের ধরন
        <select name="type"><?php foreach ($ships as $sh): ?><option value="<?= e($sh['tag'] ?: $sh['name']) ?>"><?= e($sh['tag'] ?: $sh['name']) ?></option><?php endforeach; ?><option>টিকেট + রাত্রিযাপন প্যাকেজ</option></select>
      </label>
      <label>মেসেজ (ঐচ্ছিক)<textarea name="message" rows="3" placeholder="কোনো বিশেষ প্রয়োজন থাকলে লিখুন"></textarea></label>
      <p class="form__error" role="alert" hidden></p>
      <button type="submit" class="btn btn--primary btn--block">WhatsApp-এ পাঠান</button>
    </form>
  </div>
</section>

<!-- 9. FAQ -->
<section class="section section--alt" id="faq">
  <div class="container container--narrow">
    <div class="section__head"><h2><?= e(setting('faq_title')) ?></h2></div>
    <div class="accordion">
      <?php foreach ($faqs as $f): ?>
      <div class="acc"><button class="acc__q" aria-expanded="false"><?= e($f['question']) ?></button><div class="acc__a"><p><?= e($f['answer']) ?></p></div></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- 10. CONTACT -->
<section class="section" id="contact">
  <div class="container contact">
    <div class="contact__info">
      <h2>যোগাযোগ</h2>
      <p><strong><?= e(setting('contact_person')) ?></strong><br><?= e($brand) ?></p>
      <p>📍 <?= e(setting('address')) ?></p>
      <p>📞 <a href="tel:<?= e($tel) ?>"><?= e($telText) ?></a></p>
      <p>💬 <a href="<?= e($waLink) ?>">WhatsApp-এ মেসেজ</a></p>
      <div class="social">
        <?php foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube'] as $k => $label): if (setting($k) === '') continue; ?>
        <a href="<?= e(setting($k)) ?>" aria-label="<?= $label ?>" target="_blank" rel="noopener"><?= $label ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="contact__map">
      <iframe title="অফিসের লোকেশন" src="https://www.google.com/maps?q=<?= e(urlencode(setting('map_query') ?: setting('address'))) ?>&output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
    </div>
  </div>
</section>

</main>

<footer class="footer">
  <div class="container footer__inner">
    <p>© <span id="year"><?= bn_digits(date('Y')) ?></span> <?= e(setting('footer_text')) ?></p>
    <p><?= e(setting('footer_tagline')) ?></p>
  </div>
</footer>

<!-- Floating WhatsApp -->
<a href="<?= e($waLink) ?>" class="wa-float" aria-label="WhatsApp-এ মেসেজ করুন" target="_blank" rel="noopener">💬</a>

<script src="script.js"></script>
</body>
</html>
