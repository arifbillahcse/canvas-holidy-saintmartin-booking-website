<?php
require __DIR__ . '/includes/site.php';
$all = db()->query("SELECT * FROM hotels WHERE active = 1 AND status = 'approved' ORDER BY sort_order, id")->fetchAll();
$sel = null;
foreach ($all as $h) {
    if ((int)$h['id'] === (int)($_GET['h'] ?? 0)) {
        $sel = $h;
    }
}
$sel = $sel ?: ($all[0] ?? null); // first hotel is open by default
$hotel = $sel ? hotel_load((int)$sel['id']) : null;
$wa = wa_number();
$tel = tel_href();

page_open(($hotel ? $hotel['name'] . ' | ' : '') . (setting('hotels_title', 'থাকার হোটেল ও রিসোর্ট')), $hotel && $hotel['summary'] !== '' ? $hotel['summary'] : setting('hotels_sub'), false);
?>
<main>
<section class="page-hero page-hero--slim">
  <div class="container">
    <h1><?= e(setting('hotels_title', 'থাকার হোটেল ও রিসোর্ট')) ?></h1>
    <p><?= e(setting('hotels_sub', 'সেন্ট মার্টিনে আপনার বাজেট অনুযায়ী সেরা হোটেল বেছে নিন।')) ?></p>
  </div>
</section>

<section class="section hotels-sec">
  <div class="container">
<?php if (!$hotel): ?>
    <p class="note">শীঘ্রই হোটেল তালিকা যুক্ত হবে। বিস্তারিত জানতে কল করুন।</p>
<?php else: ?>
    <div class="hlayout">
      <aside class="hlist">
        <h2>সব হোটেল</h2>
        <nav aria-label="হোটেল তালিকা">
          <?php foreach ($all as $h): ?>
          <a class="hlist__i<?= (int)$h['id'] === (int)$hotel['id'] ? ' on' : '' ?>" href="hotels.php?h=<?= (int)$h['id'] ?>"<?= (int)$h['id'] === (int)$hotel['id'] ? ' aria-current="page"' : '' ?>>
            <span><?= e($h['name']) ?><?php if ($h['location'] !== ''): ?><small><?= e($h['location']) ?></small><?php endif; ?></span><i aria-hidden="true">→</i>
          </a>
          <?php endforeach; ?>
        </nav>
        <p class="hlist__own">হোটেল মালিক? <a href="owner/register.php">এখানে যুক্ত করুন</a></p>
      </aside>

      <article class="hmain">
        <header class="hmain__head">
          <h2><?= e($hotel['name']) ?></h2>
          <?php if ($hotel['location'] !== ''): ?><p class="hmain__loc">📍 <?= e($hotel['location']) ?></p><?php endif; ?>
          <?php if ($hotel['summary'] !== ''): ?><p class="hmain__sum"><?= nl2br(e($hotel['summary'])) ?></p><?php endif; ?>
        </header>

        <?php foreach ($hotel['posts'] as $p):
            $n = count($p['images']);
            $msg = 'আসসালামু আলাইকুম, ' . brand_name() . "।\nআমি \"" . $hotel['name'] . '" হোটেলের ' . ($p['title'] !== '' ? '"' . $p['title'] . '" ' : '') . 'সম্পর্কে জানতে চাই।'; ?>
        <section class="post">
          <?php if ($n): ?>
          <div class="pgal pgal--<?= min($n, 4) ?>">
            <?php foreach ($p['images'] as $j => $img): ?>
            <button type="button" class="pgal__i" data-group="p<?= (int)$p['id'] ?>" data-src="<?= e($img) ?>" style="background-image:url('<?= e($img) ?>')"<?= $j >= 4 ? ' hidden' : '' ?> aria-label="ছবি <?= bn_digits($j + 1) ?> বড় করে দেখুন"><?php if ($j === 3 && $n > 4): ?><span class="pgal__more">+<?= bn_digits($n - 4) ?></span><?php endif; ?></button>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <div class="post__body">
            <div class="post__top">
              <?php if ($p['title'] !== ''): ?><h3><?= e($p['title']) ?></h3><?php endif; ?>
              <?php if ($p['price'] !== ''): ?><span class="post__price"><?= e($p['price']) ?></span><?php endif; ?>
            </div>
            <?php if ($p['body'] !== ''): ?><p class="post__text"><?= nl2br(e($p['body'])) ?></p><?php endif; ?>
            <div class="post__cta">
              <a class="btn btn--wa btn--small-x" href="https://wa.me/<?= e($wa) ?>?text=<?= e(rawurlencode($msg)) ?>" target="_blank" rel="noopener"><?= wa_icon() ?> WhatsApp-এ জানুন</a>
              <a class="btn btn--ghost-dark btn--small-x" href="<?= e($tel) ?>">📞 কল করুন</a>
            </div>
          </div>
        </section>
        <?php endforeach; ?>

        <?php if (!$hotel['posts']): ?>
        <p class="note">এই হোটেলের বিস্তারিত শীঘ্রই যুক্ত হবে। জানতে কল করুন বা WhatsApp-এ মেসেজ দিন।</p>
        <?php endif; ?>
      </article>
    </div>
<?php endif; ?>
  </div>
</section>
</main>

<div class="lb" id="lightbox" hidden role="dialog" aria-modal="true" aria-label="ছবি">
  <button type="button" class="lb__x" data-lbclose aria-label="বন্ধ করুন">✕</button>
  <button type="button" class="lb__n lb__n--p" data-lbprev aria-label="আগের ছবি">‹</button>
  <img id="lbImg" alt="">
  <button type="button" class="lb__n lb__n--n" data-lbnext aria-label="পরের ছবি">›</button>
</div>
<?php page_close(); ?>
