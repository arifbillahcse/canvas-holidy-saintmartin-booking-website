<?php
// Shared public layout (header/footer) used by index.php and hotels.php.
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/hotels.php';
settings_all(); // loads settings and runs any pending schema upgrade before pages query the DB

// Cache-busting version for a static asset: file modification time + the admin "clear cache" counter.
function asset_ver(string $file): string
{
    $m = @filemtime(APP_ROOT . '/' . $file);
    return ($m ? $m : '0') . '-' . setting('asset_v', '1');
}

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

// WhatsApp logo glyph (inline SVG, inherits text colour).
function wa_icon(string $class = 'wa-ico'): string
{
    return '<svg class="' . $class . '" viewBox="0 0 24 24" width="1.2em" height="1.2em" fill="currentColor" aria-hidden="true" focusable="false"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>';
}

function page_open(string $title, string $desc, bool $home, bool $overlay = false, string $bodyClass = ''): void
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
<link rel="stylesheet" href="style.css?v=<?= e(asset_ver('style.css')) ?>">
</head>
<body<?= $home ? '' : ' class="inner ' . e($bodyClass) . '"' ?>>

<header class="header<?= ($home || $overlay) ? '' : ' header--solid' ?>" id="top">
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
  <div class="container footer__credit">© <?= date('Y') ?> <a href="https://softorio.com" target="_blank" rel="noopener">softorio.com</a></div>
</footer>

<a href="https://wa.me/<?= e(wa_number()) ?>" class="wa-float" aria-label="WhatsApp-এ মেসেজ করুন" target="_blank" rel="noopener"><?= wa_icon('wa-float__ico') ?></a>

<script src="script.js?v=<?= e(asset_ver('script.js')) ?>"></script>
</body>
</html>
<?php
}

// One hotel card (shared by the home section and the hotels page); links to the hotel detail page.
function hotel_card(array $h): void
{
    $stars = max(0, min(5, (int)$h['stars']));
    $min = hotel_min_prices()[(int)$h['id']] ?? 0;
    $price = $min ? fmt_price($min) : (string)$h['price'];
    $href = 'hotels.php?h=' . (int)$h['id'];
    ?>
<article class="hotel" data-name="<?= e(mb_strtolower($h['name'] . ' ' . $h['location'])) ?>" data-stars="<?= $stars ?>" data-loc="<?= e($h['location']) ?>">
  <a class="hotel__img"<?= $h['image'] ? ' style="background-image:url(\'' . e($h['image']) . '\')"' : '' ?> href="<?= e($href) ?>" aria-label="<?= e($h['name']) ?>">
    <?php if (!$h['image']): ?><span aria-hidden="true">🏨</span><?php endif; ?>
    <?php if ($h['badge'] !== ''): ?><em class="hotel__badge"><?= e($h['badge']) ?></em><?php endif; ?>
  </a>
  <div class="hotel__body">
    <div class="hotel__meta">
      <span class="hotel__stars" aria-label="<?= $stars ?> স্টার"><?= str_repeat('★', $stars) ?><i><?= str_repeat('★', 5 - $stars) ?></i></span>
      <?php if ($h['rating'] !== ''): ?><span class="hotel__rating"><?= e($h['rating']) ?></span><?php endif; ?>
    </div>
    <h3><a href="<?= e($href) ?>"><?= e($h['name']) ?></a></h3>
    <?php if ($h['location'] !== ''): ?><p class="hotel__loc">📍 <?= e($h['location']) ?></p><?php endif; ?>
    <?php if ($h['summary'] !== ''): ?><p class="hotel__sum"><?= e($h['summary']) ?></p><?php endif; ?>
    <div class="hotel__foot">
      <div class="hotel__price"><?php if ($price !== ''): ?>৳ <?= e($price) ?> <small>/ রাত থেকে</small><?php endif; ?></div>
      <div class="hotel__cta">
        <a class="btn btn--primary btn--small-x" href="<?= e($href) ?>">রুম ও ভাড়া দেখুন</a>
        <a class="btn btn--ghost-dark btn--small-x" href="<?= e(tel_href()) ?>" aria-label="কল করুন">📞</a>
      </div>
    </div>
  </div>
</article>
<?php
}

// One ship/ticket card.
function ship_card(array $sh): void
{
    $img = $sh['image'] ?? '';
    $feat = lines_of($sh['features'] ?? '');
    if (!$feat && ($sh['extra'] ?? '') !== '') {
        $feat = [$sh['extra']];
    }
    $round = $sh['price_round'] ?? '';
    $msg = 'আসসালামু আলাইকুম, ' . brand_name() . "।\nআমি \"" . $sh['name'] . '" সীপের টিকেট বুক করতে চাই।';
    ?>
<article class="ticket<?= $sh['featured'] ? ' ticket--featured' : '' ?><?= $img ? ' ticket--img' : '' ?>">
  <?php if ($img): ?><div class="ticket__img" style="background-image:url('<?= e($img) ?>')" role="img" aria-label="<?= e($sh['name']) ?>"><?php if ($sh['tag'] !== ''): ?><span class="tag"><?= e($sh['tag']) ?></span><?php endif; ?></div><?php endif; ?>
  <div class="ticket__body">
    <div class="ticket__top"><?php if (!$img && $sh['tag'] !== ''): ?><span class="tag"><?= e($sh['tag']) ?></span><?php endif; ?><h3><?= e($sh['name']) ?></h3><?php if (($sh['subtitle'] ?? '') !== ''): ?><p class="ticket__sub"><?= e($sh['subtitle']) ?></p><?php endif; ?></div>
    <div class="ticket__prices">
      <?php if ($round !== ''): ?><div><strong>৳ <?= e($round) ?></strong><span>রাউন্ড ট্রিপ</span></div><?php endif; ?>
      <?php if ($sh['price'] !== ''): ?><div><strong>৳ <?= e($sh['price']) ?></strong><span>সিঙ্গেল ট্রিপ</span></div><?php endif; ?>
    </div>
    <ul>
      <?php if ($sh['depart'] !== ''): ?><li>🕖 ছাড়ার সময়: <?= e($sh['depart']) ?></li><?php endif; ?>
      <?php if ($sh['return_time'] !== ''): ?><li>🕓 ফেরার সময়: <?= e($sh['return_time']) ?></li><?php endif; ?>
      <?php if (($sh['seats'] ?? '') !== ''): ?><li>💺 সিট: <?= e($sh['seats']) ?></li><?php endif; ?>
    </ul>
    <?php if ($feat): ?><div class="ticket__feat"><b>ফিচারসমূহ</b><ul><?php foreach ($feat as $f): ?><li>✔ <?= e($f) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <div class="ticket__cta">
      <a class="btn btn--ghost-dark" href="<?= e(tel_href()) ?>">📞 কল করুন</a>
      <a class="btn btn--primary" href="https://wa.me/<?= e(wa_number()) ?>?text=<?= e(rawurlencode($msg)) ?>" target="_blank" rel="noopener"><?= wa_icon() ?> টিকেট বুক</a>
    </div>
  </div>
</article>
<?php
}
