<?php
// Shared hotel editor fields (hotel info, gallery, rooms). Used by the owner panel and the admin.
// Expects a snapshot (see includes/hotels.php). $assetPrefix is the path from the page to the site root.
require_once __DIR__ . '/hotels.php';

function hotel_room_block($i, array $r, string $assetPrefix): string
{
    $r += ['id' => 0, 'type' => 'single', 'name' => '', 'price' => '', 'persons' => '', 'beds' => '', 'ac' => 1, 'breakfast' => 0, 'description' => '', 'images' => []];
    ob_start(); ?>
<fieldset class="room-block">
  <legend>রুম</legend>
  <input type="hidden" name="rooms[<?= e($i) ?>][id]" value="<?= (int)$r['id'] ?>">
  <div class="two-col">
    <label>রুমের ধরন
      <select name="rooms[<?= e($i) ?>][type]" data-room-type>
        <?php foreach (ROOM_TYPES as $k => [$lbl, $ico, $hint]): ?><option value="<?= e($k) ?>" <?= $r['type'] === $k ? 'selected' : '' ?>><?= e($lbl . ' — ' . $hint) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>নিজের নাম (ঐচ্ছিক; "Other" হলে আবশ্যক, যেমন: Honeymoon Suite)<input name="rooms[<?= e($i) ?>][name]" value="<?= e($r['name']) ?>" maxlength="120"></label>
    <label>প্রতি রাতের ভাড়া (৳, শুধু সংখ্যা)<input name="rooms[<?= e($i) ?>][price]" value="<?= e($r['price'] ? $r['price'] : '') ?>" inputmode="numeric" placeholder="2500"></label>
    <label>কয়জন থাকতে পারবে<input name="rooms[<?= e($i) ?>][persons]" value="<?= e($r['persons']) ?>" placeholder="২ জন"></label>
    <label>বেড<input name="rooms[<?= e($i) ?>][beds]" value="<?= e($r['beds']) ?>" placeholder="১টি কুইন বেড"></label>
    <label>এসি / নন-এসি
      <select name="rooms[<?= e($i) ?>][ac]"><option value="1" <?= $r['ac'] ? 'selected' : '' ?>>এসি</option><option value="0" <?= !$r['ac'] ? 'selected' : '' ?>>নন-এসি</option></select>
    </label>
  </div>
  <label class="chk"><input type="checkbox" name="rooms[<?= e($i) ?>][breakfast]" value="1" <?= $r['breakfast'] ? 'checked' : '' ?>> নাস্তা ফ্রি</label>
  <label>বিবরণ<textarea name="rooms[<?= e($i) ?>][description]" rows="2"><?= e($r['description']) ?></textarea></label>
  <div>
    <b>রুমের ছবি</b> <small>(প্রতি রুমে সর্বোচ্চ <?= bn_digits(MAX_ROOM_IMAGES) ?>টি, প্রথমটি কভার)</small>
    <?php if ($r['images']): ?><div class="thumbs"><?php foreach ($r['images'] as $img): ?>
      <label class="th"><img src="<?= e($assetPrefix . $img) ?>" alt=""><span class="chk"><input type="checkbox" name="rooms[<?= e($i) ?>][img_remove][]" value="<?= e($img) ?>"> মুছুন</span></label>
    <?php endforeach; ?></div><?php endif; ?>
    <input type="file" name="room_images[<?= e($i) ?>][]" accept="image/*" multiple>
  </div>
  <button type="button" class="sm danger" data-remove-room>✕ এই রুম মুছুন</button>
</fieldset>
<?php
    return ob_get_clean();
}

function hotel_form_fields(array $snap, bool $admin, string $assetPrefix): void
{
    $f = $snap['fields'] + ['name' => '', 'location' => '', 'stars' => 3, 'summary' => '', 'amenities' => '', 'rating' => '', 'badge' => '', 'featured' => 0];
    ?>
<fieldset><legend>হোটেলের তথ্য</legend>
  <label>হোটেলের নাম<input name="name" value="<?= e($f['name']) ?>" required maxlength="255"></label>
  <label>এলাকা/লোকেশন (যেমন: সেন্ট মার্টিন, জেটি ঘাট)<input name="location" value="<?= e($f['location']) ?>" maxlength="255"></label>
  <label>স্টার (১-৫)<input type="number" min="1" max="5" name="stars" value="<?= (int)$f['stars'] ?>"></label>
  <label>সংক্ষিপ্ত বিবরণ<textarea name="summary" rows="4"><?= e($f['summary']) ?></textarea></label>
  <label>সুযোগ-সুবিধা (প্রতি লাইনে একটি; যেমন: ফ্রি ওয়াইফাই, সী-ভিউ, নাস্তা ফ্রি)<textarea name="amenities" rows="5"><?= e($f['amenities']) ?></textarea></label>
  <?php if ($admin): ?>
  <div class="two-col">
    <label>রেটিং (যেমন: ৪.৮)<input name="rating" value="<?= e($f['rating']) ?>" maxlength="40"></label>
    <label>ব্যাজ (যেমন: সী-ভিউ)<input name="badge" value="<?= e($f['badge']) ?>" maxlength="60"></label>
  </div>
  <label class="chk"><input type="checkbox" name="featured" value="1" <?= $f['featured'] ? 'checked' : '' ?>> হোমপেজে দেখান</label>
  <?php endif; ?>
</fieldset>

<fieldset><legend>হোটেলের গ্যালারি ছবি</legend>
  <small>সর্বোচ্চ <?= bn_digits(MAX_GALLERY) ?>টি ছবি। প্রথম ছবিটি কভার/হিরো ছবি হিসেবে দেখাবে। প্রতিটি ২ MB পর্যন্ত (JPG/PNG/WebP)।</small>
  <?php if ($snap['gallery']): ?><div class="thumbs"><?php foreach ($snap['gallery'] as $img): ?>
    <label class="th"><img src="<?= e($assetPrefix . $img) ?>" alt=""><span class="chk"><input type="checkbox" name="gallery_remove[]" value="<?= e($img) ?>"> মুছুন</span></label>
  <?php endforeach; ?></div><?php endif; ?>
  <input type="file" name="gallery[]" accept="image/*" multiple>
</fieldset>

<fieldset><legend>রুমের ধরন, ছবি ও ভাড়া</legend>
  <?php if (!$snap['rooms']): ?><p class="hint">স্যাম্পল রুম দেওয়া আছে (ধরন, ভাড়া ও তথ্য নমুনা মাত্র)। আপনার হোটেল অনুযায়ী ভাড়া/তথ্য বদলান, ছবি দিন, যে রুম নেই সেটি "✕ মুছুন" করুন, আর দরকারে "+ রুম যোগ করুন" দিয়ে আরও রুম যোগ করুন।</p><?php endif; ?>
  <div id="rooms"><?php
    // A hotel without rooms starts with editable sample rooms.
    $rows = $snap['rooms'] ?: sample_rooms();
    $max = -1;
    foreach (array_values($rows) as $i => $r) {
        echo hotel_room_block($i, $r, $assetPrefix);
        $max = $i;
    } ?></div>
  <template id="roomTpl"><?= hotel_room_block('__I__', ['ac' => 1], $assetPrefix) ?></template>
  <button type="button" class="sm" id="addRoom">+ রুম যোগ করুন</button>
</fieldset>
<script>
(function () {
  var wrap = document.getElementById('rooms'), tpl = document.getElementById('roomTpl'), n = <?= $max + 1 ?>;
  function add() {
    var d = document.createElement('div');
    d.innerHTML = tpl.innerHTML.replace(/__I__/g, n++);
    wrap.appendChild(d.firstElementChild);
  }
  document.getElementById('addRoom').addEventListener('click', add);
  // Picking "AC Room" / "Non-AC Room" also sets the AC field of that room.
  wrap.addEventListener('change', function (e) {
    var t = e.target.closest('[data-room-type]');
    if (!t) return;
    var ac = t.closest('.room-block').querySelector('select[name$="[ac]"]');
    if (ac && t.value === 'ac') ac.value = '1';
    if (ac && t.value === 'nonac') ac.value = '0';
  });
  wrap.addEventListener('click', function (e) {
    var b = e.target.closest('[data-remove-room]');
    if (b && confirm('এই রুমটি মুছে ফেলবেন?')) b.closest('.room-block').remove();
  });
  if (!wrap.children.length) add();
})();
</script>
<?php
}
