/* ALMARA Gateway WA — @2026-Alifiandra */
(function () {
  'use strict';

  // ---- Tema: paper -> obsidian -> midnight ----
  var THEMES = ['paper', 'obsidian', 'midnight'];
  function setTheme(t) {
    document.documentElement.setAttribute('data-theme', t);
    try { localStorage.setItem('almara-theme', t); } catch (e) {}
    var lbl = document.querySelector('[data-theme-label]');
    if (lbl) lbl.textContent = t.charAt(0).toUpperCase() + t.slice(1);
  }
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-theme-toggle]');
    if (!btn) return;
    var cur = document.documentElement.getAttribute('data-theme') || 'paper';
    setTheme(THEMES[(THEMES.indexOf(cur) + 1) % THEMES.length]);
  });

  // ---- Salin ke clipboard ----
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-copy]');
    if (!btn) return;
    var el = document.querySelector(btn.getAttribute('data-copy'));
    if (!el || !navigator.clipboard) return;
    navigator.clipboard.writeText(el.textContent.trim()).then(function () {
      var old = btn.textContent; btn.textContent = 'Tersalin';
      setTimeout(function () { btn.textContent = old; }, 1500);
    });
  });

  // ---- Konfirmasi aksi sensitif ----
  document.addEventListener('submit', function (e) {
    var msg = e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) e.preventDefault();
  });

  // ---- Cegah submit ganda ----
  document.addEventListener('submit', function (e) {
    if (e.defaultPrevented) return;
    var b = e.target.querySelector('[data-once]');
    if (b) { setTimeout(function () { b.disabled = true; b.textContent = 'Memproses…'; }, 0); }
  });

  // ---- Penghitung karakter & nomor ----
  function bindCounter(input) {
    var out = document.querySelector(input.getAttribute('data-counter'));
    var mode = input.getAttribute('data-count-mode');
    function upd() {
      if (mode === 'numbers') {
        var n = input.value.split(/[\s,;]+/).filter(function (x) { return x.replace(/\D/g, '').length > 0; }).length;
        out.textContent = n + ' nomor';
      } else {
        out.textContent = input.value.length + ' / ' + input.getAttribute('maxlength');
      }
    }
    input.addEventListener('input', upd); upd();
  }
  document.querySelectorAll('[data-counter]').forEach(bindCounter);

  // ---- Form perangkat: tampilkan field sesuai provider ----
  var provSel = document.querySelector('[data-provider-select]');
  if (provSel) {
    var sync = function () {
      document.querySelectorAll('[data-provider-fields]').forEach(function (box) {
        var on = box.getAttribute('data-provider-fields') === provSel.value;
        box.classList.toggle('hidden', !on);
        box.querySelectorAll('input').forEach(function (i) { i.disabled = !on; });
      });
    };
    provSel.addEventListener('change', sync); sync();
  }

  // ---- Service worker (PWA) ----
  if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('/sw.js').catch(function () {});
    });
  }
})();

/* ---- Fase MVP: menu mobile, pilih semua, pratinjau, polling inbox ---- @2026-Alifiandra */
(function () {
  'use strict';
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-nav-toggle]');
    if (t) { e.preventDefault(); document.body.classList.toggle('nav-open'); return; }
    if (document.body.classList.contains('nav-open') && !e.target.closest('.sidebar')) document.body.classList.remove('nav-open');
  });

  // Pilih semua (checkbox tabel)
  document.querySelectorAll('[data-check-all]').forEach(function (all) {
    all.addEventListener('change', function () {
      document.querySelectorAll(all.getAttribute('data-check-all')).forEach(function (c) { c.checked = all.checked; });
    });
  });

  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content;
  function post(url, data) {
    return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(data) }).then(function (r) { return r.json(); });
  }

  // Pratinjau template (variabel {{name}} dll.)
  document.querySelectorAll('[data-preview-src]').forEach(function (src) {
    var out = document.querySelector(src.getAttribute('data-preview-src'));
    var url = src.getAttribute('data-preview-url');
    var t;
    function run() { post(url, { body: src.value }).then(function (d) { out.textContent = d.text || ''; }).catch(function () {}); }
    src.addEventListener('input', function () { clearTimeout(t); t = setTimeout(run, 350); });
    if (src.value) run();
  });

  // Sisipkan variabel ke textarea aktif
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-insert]');
    if (!b) return;
    var ta = document.querySelector(b.getAttribute('data-target'));
    if (!ta) return;
    var v = b.getAttribute('data-insert'), s = ta.selectionStart || ta.value.length;
    ta.value = ta.value.slice(0, s) + v + ta.value.slice(ta.selectionEnd || s);
    ta.focus(); ta.dispatchEvent(new Event('input'));
  });

  // Isi balasan cepat dari template
  document.querySelectorAll('[data-quick-reply]').forEach(function (sel) {
    sel.addEventListener('change', function () {
      var ta = document.querySelector(sel.getAttribute('data-quick-reply'));
      if (ta && sel.value) { ta.value = sel.value; ta.focus(); }
      sel.selectedIndex = 0;
    });
  });

  // Pratinjau audience broadcast
  var aud = document.querySelector('[data-audience-form]');
  if (aud) {
    var outA = document.querySelector('[data-audience-out]');
    var runA = function () {
      var tags = [], stages = [];
      aud.querySelectorAll('input[name="tags[]"]:checked').forEach(function (i) { tags.push(i.value); });
      aud.querySelectorAll('input[name="stages[]"]:checked').forEach(function (i) { stages.push(i.value); });
      post(aud.getAttribute('data-audience-form'), { tags: tags, stages: stages }).then(function (d) {
        outA.innerHTML = '<b>' + d.eligible + '</b> penerima memenuhi syarat dari ' + d.total + ' kontak · ' +
          d.no_consent + ' belum opt-in · ' + d.opted_out + ' opt-out' + (d.over_limit ? ' · ' + d.over_limit + ' melebihi batas' : '');
      });
    };
    aud.addEventListener('change', runA); runA();
  }

  // Enter = kirim, Shift+Enter = baris baru (composer inbox)
  document.querySelectorAll('[data-enter-submit]').forEach(function (ta) {
    ta.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); if (ta.value.trim()) ta.form.requestSubmit(); }
    });
  });

  // Gulir thread ke bawah
  var th = document.querySelector('.thread');
  if (th) th.scrollTop = th.scrollHeight;

  // Polling ringan: muat ulang bila ada pesan baru & composer kosong
  var poll = document.querySelector('[data-poll]');
  if (poll) {
    var latest = null;
    setInterval(function () {
      if (document.hidden) return;
      fetch(poll.getAttribute('data-poll'), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (latest === null) { latest = d.latest; return; }
          var ta = document.querySelector('.composer textarea');
          if (d.latest !== latest && (!ta || !ta.value.trim())) location.reload();
          latest = d.latest;
        }).catch(function () {});
    }, 8000);
  }
})();
