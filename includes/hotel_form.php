<?php
// Hotel editor fields shared by the owner panel and the admin: hotel info + any number of posts.
// $hotel: ['name','location','summary','posts'=>[...]] ; $assetPrefix: path from the page to the site root.
require_once __DIR__ . '/hotels.php';

function hotel_post_block($i, array $p, string $assetPrefix): string
{
    $p += ['id' => 0, 'title' => '', 'body' => '', 'price' => '', 'images' => []];
    ob_start(); ?>
<fieldset class="post-block">
  <legend>পোস্ট</legend>
  <input type="hidden" name="posts[<?= e($i) ?>][id]" value="<?= (int)$p['id'] ?>">
  <label>শিরোনাম (যেমন: Couple Bed Room)<input name="posts[<?= e($i) ?>][title]" value="<?= e($p['title']) ?>" maxlength="255"></label>
  <label>লেখা / বিবরণ<textarea name="posts[<?= e($i) ?>][body]" rows="4" placeholder="কয়জন থাকতে পারবে, বেড, এসি, সুবিধা…"><?= e($p['body']) ?></textarea></label>
  <label>দাম (যেমন: ৳ ২,৫০০ / রাত)<input name="posts[<?= e($i) ?>][price]" value="<?= e($p['price']) ?>" maxlength="80"></label>
  <div>
    <b>ছবি</b> <small>(সর্বোচ্চ <?= bn_digits(MAX_POST_IMAGES) ?>টি; ৩-৪টি দিলে সবচেয়ে সুন্দর দেখায়)</small>
    <?php if ($p['images']): ?><div class="thumbs"><?php foreach ($p['images'] as $img): ?>
      <label class="th"><img src="<?= e($assetPrefix . $img) ?>" alt=""><span class="chk"><input type="checkbox" name="posts[<?= e($i) ?>][img_remove][]" value="<?= e($img) ?>"> মুছুন</span></label>
    <?php endforeach; ?></div><?php endif; ?>
    <input type="file" name="post_images[<?= e($i) ?>][]" accept="image/*" multiple>
  </div>
  <button type="button" class="sm danger" data-remove-post>✕ এই পোস্ট মুছুন</button>
</fieldset>
<?php
    return ob_get_clean();
}

function hotel_form_fields(array $hotel, string $assetPrefix): void
{
    $hotel += ['name' => '', 'location' => '', 'summary' => '', 'posts' => []];
    ?>
<fieldset><legend>হোটেলের তথ্য</legend>
  <label>হোটেলের নাম<input name="name" value="<?= e($hotel['name']) ?>" required maxlength="255"></label>
  <label>এলাকা/লোকেশন (যেমন: সেন্ট মার্টিন, জেটি ঘাট)<input name="location" value="<?= e($hotel['location']) ?>" maxlength="255"></label>
  <label>সংক্ষিপ্ত পরিচিতি (ঐচ্ছিক)<textarea name="summary" rows="3"><?= e($hotel['summary']) ?></textarea></label>
</fieldset>

<fieldset><legend>পোস্টসমূহ (ছবি + লেখা + দাম)</legend>
  <small>যত খুশি পোস্ট দিন। প্রতিটি পোস্ট হোটেলের পেজে একটি আলাদা ব্লক হিসেবে দেখাবে (উপর থেকে নিচে এই ক্রমে)।</small>
  <div id="posts"><?php foreach (array_values($hotel['posts']) as $i => $p) { echo hotel_post_block($i, $p, $assetPrefix); } ?></div>
  <template id="postTpl"><?= hotel_post_block('__I__', [], $assetPrefix) ?></template>
  <button type="button" class="sm" id="addPost">+ নতুন পোস্ট যোগ করুন</button>
</fieldset>
<script>
(function () {
  var wrap = document.getElementById('posts'), tpl = document.getElementById('postTpl'), n = <?= count($hotel['posts']) ?>;
  function add() {
    var d = document.createElement('div');
    d.innerHTML = tpl.innerHTML.replace(/__I__/g, n++);
    wrap.appendChild(d.firstElementChild);
  }
  document.getElementById('addPost').addEventListener('click', add);
  wrap.addEventListener('click', function (e) {
    var b = e.target.closest('[data-remove-post]');
    if (b && confirm('এই পোস্টটি মুছে ফেলবেন?')) b.closest('.post-block').remove();
  });
  if (!wrap.children.length) add();
})();
</script>
<?php
}
