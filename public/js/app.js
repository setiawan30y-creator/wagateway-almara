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
