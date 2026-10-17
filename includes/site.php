<?php
// Shared public layout (header/footer) used by index.php and hotels.php.
require_once __DIR__ . '/helpers.php';

function lines_of($t): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\R/u', (string)$t))));
}

function brand_name(): string
{
    return setting('site_name_a') . ' ' . setting('site_name_b');
}

function tel_href(): string
{
    return 'tel:+' . phone_intl(setting('phone'));
}

function page_open(string $title, string $desc, bool $home): void
{
    $p = $home ? '' : 'index.php';
    $links = [
        [$p . '#hero', 'হোম'], [$p . '#why', 'কেন আমরা'], [$p . '#ships', 'সীপ ও টিকেট'],
        [$home ? '#hotels' : 'hotels.php', 'হোটেল'], [$p . '#packages', 'প্যাকেজ'],
        [$p . '#faq', 'জিজ্ঞাসা'], [$p . '#contact', 'যোগাযোগ'],
    ];
    ?><!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<?php if (setting('favicon')): ?><link rel="icon" href="<?= e(setting('favicon')) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body<?= $home ? '' : ' class="inner"' ?>>

<header class="header<?= $home ? '' : ' header--solid' ?>" id="top">
  <div class="container header__inner">
    <a href="<?= $home ? '#top' : 'index.php' ?>" class="logo">
      <?php if (setting('logo')): ?><img class="logo__img" src="<?= e(setting('logo')) ?>" alt="<?= e(brand_name()) ?>"><?php else: ?><?= e(setting('site_name_a')) ?> <span><?= e(setting('site_name_b')) ?></span><?php endif; ?>
    </a>
    <button class="menu-toggle" aria-label="মেনু" aria-expanded="false"><span></span><span></span><span></span></button>
    <nav class="nav" id="nav">
      <?php foreach ($links as [$href, $label]): ?><a href="<?= e($href) ?>"><?= e($label) ?></a><?php endforeach; ?>
    </nav>
    <a href="<?= e(tel_href()) ?>" class="btn btn--call">📞 কল করুন</a>
  </div>
</header>
<?php
}

function page_close(): void
{
    ?>

<footer class="footer">
  <div class="container footer__inner">
    <p>© <span id="year"><?= bn_digits(date('Y')) ?></span> <?= e(setting('footer_text')) ?></p>
    <p><?= e(setting('footer_tagline')) ?></p>
  </div>
</footer>

<a href="https://wa.me/<?= e(wa_number()) ?>" class="wa-float" aria-label="WhatsApp-এ মেসেজ করুন" target="_blank" rel="noopener">💬</a>

<script src="script.js"></script>
</body>
</html>
<?php
}

// One hotel card (shared by the home section and the hotels page).
function hotel_card(array $h): void
{
    $stars = max(0, min(5, (int)$h['stars']));
    $msg = 'আসসালামু আলাইকুম, ' . brand_name() . "।\nআমি \"" . $h['name'] . '" হোটেলে থাকার বিষয়ে জানতে চাই।';
    ?>
<article class="hotel" data-name="<?= e(mb_strtolower($h['name'] . ' ' . $h['location'])) ?>" data-stars="<?= $stars ?>" data-loc="<?= e($h['location']) ?>">
  <div class="hotel__img"<?= $h['image'] ? ' style="background-image:url(\'' . e($h['image']) . '\')"' : '' ?>>
    <?php if (!$h['image']): ?><span aria-hidden="true">🏨</span><?php endif; ?>
    <?php if ($h['badge'] !== ''): ?><em class="hotel__badge"><?= e($h['badge']) ?></em><?php endif; ?>
  </div>
  <div class="hotel__body">
    <div class="hotel__meta">
      <span class="hotel__stars" aria-label="<?= $stars ?> স্টার"><?= str_repeat('★', $stars) ?><i><?= str_repeat('★', 5 - $stars) ?></i></span>
      <?php if ($h['rating'] !== ''): ?><span class="hotel__rating"><?= e($h['rating']) ?></span><?php endif; ?>
    </div>
    <h3><?= e($h['name']) ?></h3>
    <?php if ($h['location'] !== ''): ?><p class="hotel__loc">📍 <?= e($h['location']) ?></p><?php endif; ?>
    <?php if ($h['summary'] !== ''): ?><p class="hotel__sum"><?= e($h['summary']) ?></p><?php endif; ?>
    <?php if ($h['amenities'] !== ''): ?><ul class="hotel__am"><?php foreach (lines_of($h['amenities']) as $a): ?><li><?= e($a) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <div class="hotel__foot">
      <div class="hotel__price"><?php if ($h['price'] !== ''): ?>৳ <?= e($h['price']) ?> <small>/ রাত থেকে</small><?php endif; ?></div>
      <div class="hotel__cta">
        <a class="btn btn--primary btn--small-x" href="https://wa.me/<?= e(wa_number()) ?>?text=<?= e(rawurlencode($msg)) ?>" target="_blank" rel="noopener">বুক / জানুন</a>
        <a class="btn btn--ghost-dark btn--small-x" href="<?= e(tel_href()) ?>" aria-label="কল করুন">📞</a>
      </div>
    </div>
  </div>
</article>
<?php
}
