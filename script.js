(function () {
  'use strict';

  /* ===== Config: নম্বর ও নাম অ্যাডমিন প্যানেল থেকে আসে (ফর্মের data-* অ্যাট্রিবিউট) ===== */
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  var bn = function (n) {
    return String(n).replace(/\d/g, function (d) { return '০১২৩৪৫৬৭৮৯'[d]; });
  };

  /* ===== Header: scroll state ===== */
  var header = $('.header');
  function onScroll() {
    header.classList.toggle('scrolled', window.scrollY > 40);
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* ===== Mobile menu ===== */
  var toggle = $('.menu-toggle');
  var nav = $('#nav');
  function setMenu(open) {
    nav.classList.toggle('open', open);
    toggle.classList.toggle('open', open);
    toggle.setAttribute('aria-expanded', String(open));
    if (open) header.classList.add('scrolled');
    else onScroll();
  }
  toggle.addEventListener('click', function () {
    setMenu(!nav.classList.contains('open'));
  });
  $$('a', nav).forEach(function (a) {
    a.addEventListener('click', function () { setMenu(false); });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') setMenu(false);
  });

  /* ===== Typing effect ===== */
  var typed = $('.typed');
  if (typed) {
    var words = JSON.parse(typed.getAttribute('data-words') || '[]');
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduce || !words.length) {
      typed.textContent = words[0] || '';
      typed.style.animation = 'none';
      typed.style.borderRight = '0';
    } else {
      var w = 0, i = 0, deleting = false;
      (function tick() {
        var word = Array.from(words[w]); // বাংলা অক্ষর ঠিকভাবে কাটার জন্য
        i += deleting ? -1 : 1;
        typed.textContent = word.slice(0, i).join('');
        var delay = deleting ? 45 : 95;
        if (!deleting && i === word.length) { deleting = true; delay = 1600; }
        else if (deleting && i === 0) { deleting = false; w = (w + 1) % words.length; delay = 350; }
        setTimeout(tick, delay);
      })();
    }
  }

  /* ===== Counters (হিরো স্ট্যাটস) ===== */
  function runCounter(el) {
    var target = parseInt(el.getAttribute('data-count'), 10);
    var start = null, dur = 1800;
    function step(ts) {
      if (!start) start = ts;
      var p = Math.min((ts - start) / dur, 1);
      var eased = 1 - Math.pow(1 - p, 3);
      el.textContent = bn(Math.round(target * eased));
      if (p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }
  var counters = $$('[data-count]');
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries, obs) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { runCounter(en.target); obs.unobserve(en.target); }
      });
    }, { threshold: 0.4 });
    counters.forEach(function (c) { io.observe(c); });
  } else {
    counters.forEach(runCounter);
  }

  /* ===== FAQ accordion ===== */
  $$('.acc').forEach(function (acc) {
    var btn = $('.acc__q', acc);
    var panel = $('.acc__a', acc);
    btn.addEventListener('click', function () {
      var willOpen = !acc.classList.contains('open');
      $$('.acc.open').forEach(function (o) {
        o.classList.remove('open');
        $('.acc__a', o).style.maxHeight = null;
        $('.acc__q', o).setAttribute('aria-expanded', 'false');
      });
      if (willOpen) {
        acc.classList.add('open');
        panel.style.maxHeight = panel.scrollHeight + 'px';
        btn.setAttribute('aria-expanded', 'true');
      }
    });
  });

  /* ===== Booking form -> WhatsApp ===== */
  var form = $('#bookingForm');
  if (form) {
    var WHATSAPP_NUMBER = form.getAttribute('data-wa') || '';
    var BUSINESS_NAME = form.getAttribute('data-biz') || '';
    var errBox = $('.form__error', form);
    var dateInput = form.elements.date;

    // আজকের আগের তারিখ বন্ধ
    var t = new Date();
    dateInput.min = t.getFullYear() + '-' +
      String(t.getMonth() + 1).padStart(2, '0') + '-' +
      String(t.getDate()).padStart(2, '0');

    function showError(msg, field) {
      errBox.textContent = msg;
      errBox.hidden = false;
      field.classList.add('invalid');
      field.focus();
    }

    $$('input,textarea,select', form).forEach(function (f) {
      f.addEventListener('input', function () {
        f.classList.remove('invalid');
        errBox.hidden = true;
      });
    });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var f = form.elements;
      var name = f.name.value.trim();
      var phone = f.phone.value.replace(/[\s-]/g, '');
      var persons = parseInt(f.persons.value, 10);

      if (name.length < 2) return showError('অনুগ্রহ করে আপনার নাম লিখুন।', f.name);
      if (!/^(?:\+?88)?01[3-9]\d{8}$/.test(phone))
        return showError('সঠিক মোবাইল নম্বর দিন (যেমন: 01XXXXXXXXX)।', f.phone);
      if (!f.date.value) return showError('ভ্রমণের তারিখ নির্বাচন করুন।', f.date);
      if (f.date.value < dateInput.min) return showError('আজকের আগের তারিখ দেওয়া যাবে না।', f.date);
      if (!persons || persons < 1 || persons > 100)
        return showError('যাত্রী সংখ্যা ১ বা তার বেশি দিন।', f.persons);

      var d = new Date(f.date.value + 'T00:00:00');
      var dateText = d.toLocaleDateString('bn-BD', { day: 'numeric', month: 'long', year: 'numeric' });

      var lines = [
        'আসসালামু আলাইকুম, ' + BUSINESS_NAME + '।',
        'আমি সেন্ট মার্টিনের টিকেট বুক করতে চাই।',
        '',
        'নাম: ' + name,
        'মোবাইল: ' + phone,
        'ভ্রমণের তারিখ: ' + dateText,
        'যাত্রী সংখ্যা: ' + bn(persons) + ' জন',
        'টিকেটের ধরন: ' + f.type.value
      ];
      var msg = f.message.value.trim();
      if (msg) lines.push('মেসেজ: ' + msg);

      var url = 'https://wa.me/' + WHATSAPP_NUMBER + '?text=' + encodeURIComponent(lines.join('\n'));
      // ব্রাউজারের পপআপ ব্লক এড়াতে আগে WhatsApp খুলি, তারপর বুকিং সার্ভারে সেভ করি
      window.open(url, '_blank', 'noopener');
      try {
        fetch(form.getAttribute('data-action') || 'book.php', {
          method: 'POST',
          body: new FormData(form),
          keepalive: true
        }).catch(function () {});
      } catch (err) { /* সেভ ব্যর্থ হলেও WhatsApp চালু থাকবে */ }
      form.reset();
    });
  }

  /* ===== Footer year ===== */
  var yr = $('#year');
  if (yr) yr.textContent = bn(new Date().getFullYear());
})();

/* ===== Hotels page filter ===== */
(function () {
  var grid = document.getElementById('hotelGrid');
  if (!grid) return;
  var q = document.getElementById('hSearch'), st = document.getElementById('hStars'), loc = document.getElementById('hLoc');
  var empty = document.getElementById('hEmpty');
  var cards = Array.prototype.slice.call(grid.querySelectorAll('.hotel'));
  function apply() {
    var term = q.value.trim().toLowerCase();
    var minStars = parseInt(st.value, 10) || 0;
    var where = loc ? loc.value : '';
    var shown = 0;
    cards.forEach(function (c) {
      var ok = (!term || c.getAttribute('data-name').indexOf(term) > -1) &&
        parseInt(c.getAttribute('data-stars'), 10) >= minStars &&
        (!where || c.getAttribute('data-loc') === where);
      c.hidden = !ok;
      if (ok) shown++;
    });
    empty.hidden = shown > 0;
  }
  [q, st, loc].forEach(function (el) { if (el) el.addEventListener('input', apply); });
})();

/* ===== Hotel detail: room popup, gallery lightbox, section highlight ===== */
(function () {
  var dataEl = document.getElementById('roomData');
  if (!dataEl) return;
  var data = JSON.parse(dataEl.textContent);
  var rooms = data.rooms, gallery = data.gallery;

  /* --- Room popup (shows only the clicked room) --- */
  var modal = document.getElementById('roomModal');
  var big = document.getElementById('rmBig'), thumbs = document.getElementById('rmThumbs');
  var lastFocus = null;

  function bg(node, path) { node.style.backgroundImage = path ? 'url("' + path + '")' : ''; }
  function row(dl, k, v) {
    var d = document.createElement('div'), dt = document.createElement('dt'), dd = document.createElement('dd');
    dt.textContent = k; dd.textContent = v; d.appendChild(dt); d.appendChild(dd); dl.appendChild(d);
  }
  function setPhoto(r, i) {
    bg(big, r.images[i] || '');
    big.textContent = r.images.length ? '' : r.icon;
    Array.prototype.forEach.call(thumbs.children, function (b, n) { b.classList.toggle('on', n === i); });
  }
  function setUrl(id) {
    try {
      var u = new URL(window.location.href);
      if (id) u.searchParams.set('room', id); else u.searchParams.delete('room');
      history.replaceState(null, '', u.toString());
    } catch (e) {}
  }
  function openRoom(id) {
    var r = rooms.filter(function (x) { return x.id === id; })[0];
    if (!r) return;
    lastFocus = document.activeElement;
    document.getElementById('rmTitle').textContent = r.label;
    document.getElementById('rmCrumb').textContent = r.label;
    document.getElementById('rmDesc').textContent = r.description;
    document.getElementById('rmPrice').textContent = r.hasPrice ? '৳ ' + r.price : 'কল করুন';
    var spec = document.getElementById('rmSpec');
    spec.textContent = '';
    if (r.persons) row(spec, '👥 থাকতে পারবে', r.persons);
    if (r.beds) row(spec, '🛏️ বেড', r.beds);
    row(spec, '❄️ এসি / নন-এসি', r.ac ? 'এসি' : 'নন-এসি');
    row(spec, '🍳 নাস্তা', r.breakfast ? 'ফ্রি' : 'অন্তর্ভুক্ত নয়');
    thumbs.textContent = '';
    if (r.images.length > 1) {
      r.images.forEach(function (p, i) {
        var b = document.createElement('button');
        b.type = 'button'; bg(b, p); b.setAttribute('aria-label', 'ছবি ' + (i + 1));
        b.addEventListener('click', function () { setPhoto(r, i); });
        thumbs.appendChild(b);
      });
    }
    setPhoto(r, 0);
    var msg = 'আসসালামু আলাইকুম, ' + modal.getAttribute('data-brand') + '।\nআমি "' + modal.getAttribute('data-hotel') +
      '" হোটেলের "' + r.label + '" রুমের ব্যাপারে জানতে চাই।';
    document.getElementById('rmWa').href = 'https://wa.me/' + modal.getAttribute('data-wa') + '?text=' + encodeURIComponent(msg);
    modal.hidden = false;
    document.body.classList.add('rm-open');
    modal.querySelector('.rm__x').focus();
    setUrl(id);
  }
  function closeRoom() {
    if (modal.hidden) return;
    modal.hidden = true;
    document.body.classList.remove('rm-open');
    setUrl(null);
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }
  Array.prototype.forEach.call(document.querySelectorAll('.rcard'), function (c) {
    var id = parseInt(c.getAttribute('data-room'), 10);
    c.addEventListener('click', function () { openRoom(id); });
    c.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openRoom(id); }
    });
  });
  Array.prototype.forEach.call(modal.querySelectorAll('[data-close]'), function (b) { b.addEventListener('click', closeRoom); });
  var want = parseInt(new URLSearchParams(window.location.search).get('room'), 10);
  if (want) openRoom(want);

  /* --- Gallery lightbox --- */
  var lb = document.getElementById('lightbox'), lbImg = document.getElementById('lbImg'), cur = 0;
  function showLb(i) {
    cur = (i + gallery.length) % gallery.length;
    lbImg.src = gallery[cur];
  }
  function openLb(i) { lb.hidden = false; document.body.classList.add('rm-open'); showLb(i); }
  function closeLb() { lb.hidden = true; if (modal.hidden) document.body.classList.remove('rm-open'); }
  Array.prototype.forEach.call(document.querySelectorAll('[data-lightbox]'), function (b) {
    b.addEventListener('click', function () { openLb(parseInt(b.getAttribute('data-lightbox'), 10)); });
  });
  lb.querySelector('[data-lbclose]').addEventListener('click', closeLb);
  lb.querySelector('[data-lbprev]').addEventListener('click', function () { showLb(cur - 1); });
  lb.querySelector('[data-lbnext]').addEventListener('click', function () { showLb(cur + 1); });
  lb.addEventListener('click', function (e) { if (e.target === lb) closeLb(); });

  document.addEventListener('keydown', function (e) {
    if (!lb.hidden) {
      if (e.key === 'Escape') closeLb();
      else if (e.key === 'ArrowLeft') showLb(cur - 1);
      else if (e.key === 'ArrowRight') showLb(cur + 1);
    } else if (!modal.hidden) {
      if (e.key === 'Escape') closeRoom();
      else if (e.key === 'Tab') { // keep focus inside the popup
        var f = modal.querySelectorAll('button, a[href]');
        if (!f.length) return;
        var first = f[0], last = f[f.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    }
  });

  /* --- Highlight the section menu item while scrolling --- */
  var links = Array.prototype.slice.call(document.querySelectorAll('.snav__in > a[href^="#"]'));
  function spy() {
    var active = links[0];
    links.forEach(function (a) {
      var s = document.querySelector(a.getAttribute('href'));
      if (s && s.getBoundingClientRect().top < 170) active = a;
    });
    links.forEach(function (a) { a.classList.toggle('on', a === active); });
  }
  window.addEventListener('scroll', spy, { passive: true });
  spy();
})();
