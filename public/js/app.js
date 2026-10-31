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
      var tags = [], stages = [], lists = [];
      aud.querySelectorAll('input[name="lists[]"]:checked').forEach(function (i) { lists.push(i.value); });
      aud.querySelectorAll('input[name="tags[]"]:checked').forEach(function (i) { tags.push(i.value); });
      aud.querySelectorAll('input[name="stages[]"]:checked').forEach(function (i) { stages.push(i.value); });
      post(aud.getAttribute('data-audience-form'), { lists: lists, tags: tags, stages: stages }).then(function (d) {
        outA.innerHTML = '<b>' + d.eligible + '</b> penerima memenuhi syarat dari ' + d.total + ' kontak · ' +
          d.no_consent + ' belum opt-in · ' + d.opted_out + ' opt-out' + (d.over_limit ? ' · ' + d.over_limit + ' melebihi batas' : '');
      });
    };
    aud.addEventListener('change', function (e) { if (e.target && /^(lists|tags|stages)\[\]$/.test(e.target.name)) runA(); }); runA();

    // Template: hitung varian terpilih + tombol pilih semua
    var tplOut = document.querySelector('[data-template-count]');
    var countT = function () {
      var t = 0, v = 0;
      aud.querySelectorAll('input[name="template_ids[]"]:checked').forEach(function (i) { t++; v += +(i.getAttribute('data-variants') || 1); });
      if (tplOut) tplOut.textContent = t ? t + ' template · ' + v + ' variasi pesan diacak per penerima' : 'Pilih minimal satu template';
    };
    aud.addEventListener('change', function (e) { if (e.target && e.target.name === 'template_ids[]') countT(); });
    var allBtn = document.querySelector('[data-template-all]');
    if (allBtn) allBtn.addEventListener('click', function () {
      var boxes = aud.querySelectorAll('input[name="template_ids[]"]');
      var all = Array.prototype.every.call(boxes, function (b) { return b.checked; });
      boxes.forEach(function (b) { b.checked = !all; }); countT();
    });
    countT();
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

/* ---- Responsif: tabel jadi kartu di ponsel ---- @2026-Alifiandra */
(function () {
  'use strict';
  document.querySelectorAll('table.dense').forEach(function (table) {
    var head = table.querySelector('thead tr');
    if (!head || table.classList.contains('no-cards')) return;
    var labels = Array.prototype.map.call(head.children, function (th) { return th.textContent.trim(); });
    table.classList.add('rcards');
    table.querySelectorAll('tbody tr').forEach(function (tr) {
      var i = 0;
      Array.prototype.forEach.call(tr.children, function (td) {
        var span = parseInt(td.getAttribute('colspan') || '1', 10);
        var label = labels[i] || '';
        if (td.querySelector('input[type=checkbox]') && td.children.length === 1) td.classList.add('r-check');
        else if (!label && (td.querySelector('.btn, form') )) td.classList.add('r-actions');
        else if (label === 'Aksi' || label === 'AKSI') td.classList.add('r-actions');
        if (label && span === 1) td.setAttribute('data-label', label);
        i += span;
      });
    });
  });
})();

/* ---- Lampiran: tampilkan nama berkas terpilih ---- @2026-Alifiandra */
(function () {
  document.querySelectorAll('[data-file-input]').forEach(function (input) {
    input.addEventListener('change', function () {
      var label = input.closest('label');
      var span = label && label.querySelector('[data-file-label]');
      var f = input.files && input.files[0];
      if (span) span.textContent = f ? (f.name.length > 22 ? f.name.slice(0, 20) + '…' : f.name) : 'Lampiran';
      if (label) label.classList.toggle('has-file', !!f);
      if (f && f.size > 16 * 1024 * 1024) { alert('Berkas maksimal 16 MB.'); input.value = ''; if (span) span.textContent = 'Lampiran'; label.classList.remove('has-file'); }
    });
  });
})();

/* ---- Copilot: saran balasan AI di inbox ---- @2026-Alifiandra */
(function () {
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-ai-suggest]');
    if (!b) return;
    var ta = document.querySelector('#reply-body');
    var old = b.textContent; b.disabled = true; b.textContent = '✨ Menyusun…';
    fetch(b.getAttribute('data-ai-suggest'), { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content } })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d.ok && ta) { ta.value = d.text; ta.focus(); } else { alert(d.text || 'Gagal membuat saran.'); } })
      .catch(function () { alert('Gagal menghubungi server.'); })
      .finally(function () { b.disabled = false; b.textContent = old; });
  });
})();

/* ---- Bantuan per halaman: jendela di tengah, bisa digeser ---- @2026-Alifiandra */
(function () {
  var box = document.getElementById('help-drawer');
  if (!box) return;
  var head = box.querySelector('[data-help-drag]');
  try { localStorage.removeItem('almara-help-pos'); } catch (e) {} // posisi lama tidak dipakai lagi
  var lastFocus = null;

  function clamp(x, y) {
    var w = box.offsetWidth, h = box.offsetHeight, m = 8;
    // jendela selalu terlihat utuh di layar
    return [Math.min(Math.max(m, x), Math.max(m, window.innerWidth - w - m)), Math.min(Math.max(m, y), Math.max(m, window.innerHeight - h - m))];
  }
  function place(x, y) {
    var p = clamp(x, y);
    box.classList.add('moved');
    box.style.left = p[0] + 'px'; box.style.top = p[1] + 'px';
    return p;
  }
  function center() {
    box.classList.remove('moved'); box.style.left = ''; box.style.top = '';
  }

  function open() {
    lastFocus = document.activeElement;
    box.hidden = false;
    center(); // selalu muncul di tengah layar
    requestAnimationFrame(function () { box.classList.add('open'); });
    var c = box.querySelector('[data-help-close]'); if (c) c.focus();
  }
  function close() {
    box.classList.remove('open');
    setTimeout(function () { box.hidden = true; }, 150);
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  // Geser (mouse & sentuh)
  var start = null;
  head.addEventListener('pointerdown', function (e) {
    if (e.target.closest('button, a')) return;
    var r = box.getBoundingClientRect();
    start = { dx: e.clientX - r.left, dy: e.clientY - r.top };
    place(r.left, r.top);
    box.classList.add('dragging');
    head.setPointerCapture(e.pointerId);
    e.preventDefault();
  });
  head.addEventListener('pointermove', function (e) {
    if (!start) return;
    place(e.clientX - start.dx, e.clientY - start.dy);
  });
  function endDrag() {
    if (!start) return;
    start = null; box.classList.remove('dragging');
  }
  head.addEventListener('pointerup', endDrag);
  head.addEventListener('pointercancel', endDrag);
  head.addEventListener('dblclick', function (e) { if (!e.target.closest('button')) center(); });

  window.addEventListener('resize', function () {
    if (!box.hidden && box.classList.contains('moved')) place(parseInt(box.style.left, 10), parseInt(box.style.top, 10));
  });

  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-help-open]')) { e.preventDefault(); box.hidden ? open() : close(); }
    else if (e.target.closest('[data-help-close]')) { e.preventDefault(); close(); }
  });
  document.addEventListener('keydown', function (e) {
    var typing = /INPUT|TEXTAREA|SELECT/.test((e.target.tagName || '')) || e.target.isContentEditable;
    if (e.key === 'Escape' && !box.hidden) close();
    else if (e.key === '?' && !typing) { e.preventDefault(); box.hidden ? open() : close(); }
  });

  // Kotak unggah lampiran (klik / seret & lepas) dengan pratinjau
  document.querySelectorAll('[data-dropzone]').forEach(function (zone) {
    var input = zone.querySelector('.dz-input');
    var empty = zone.querySelector('.dz-empty'), filled = zone.querySelector('.dz-filled');
    var thumb = zone.querySelector('.dz-thumb'), doc = zone.querySelector('.dz-doc');
    var url = null;
    function kb(n) { return n >= 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB'; }
    function render() {
      var f = input.files && input.files[0];
      if (url) { URL.revokeObjectURL(url); url = null; }
      if (f && f.size > 16 * 1024 * 1024) { alert('Berkas maksimal 16 MB.'); input.value = ''; f = null; }
      empty.hidden = !!f; filled.hidden = !f;
      if (!f) return;
      zone.querySelector('.dz-name').textContent = f.name;
      zone.querySelector('.dz-size').textContent = kb(f.size) + ' · ' + (f.type || 'file');
      var isImg = /^image\//.test(f.type);
      thumb.hidden = !isImg; doc.hidden = isImg;
      if (isImg) { url = URL.createObjectURL(f); thumb.src = url; }
    }
    input.addEventListener('change', render);
    zone.querySelector('.dz-clear').addEventListener('click', function (e) {
      e.preventDefault(); e.stopPropagation(); input.value = ''; render();
    });
    ['dragenter', 'dragover'].forEach(function (t) { zone.addEventListener(t, function (e) { e.preventDefault(); zone.classList.add('over'); }); });
    ['dragleave', 'drop'].forEach(function (t) { zone.addEventListener(t, function (e) { e.preventDefault(); zone.classList.remove('over'); }); });
    zone.addEventListener('drop', function (e) {
      if (e.dataTransfer && e.dataTransfer.files.length) { input.files = e.dataTransfer.files; render(); }
    });
  });

  // Tombol kirim campaign mengikuti pilihan (kirim sekarang / jadwalkan / draft)
  var modes = document.querySelector('[data-send-modes]'), sendBtn = document.querySelector('[data-send-button]');
  if (modes && sendBtn) {
    var labels = { now: '🚀 Kirim sekarang', schedule: '🕒 Jadwalkan kirim', draft: '📝 Simpan draft' };
    var syncBtn = function () {
      var m = modes.querySelector('input[name="mode"]:checked');
      sendBtn.textContent = labels[m ? m.value : 'now'];
      var dt = modes.querySelector('input[name="scheduled_at"]');
      if (dt) dt.required = !!(m && m.value === 'schedule');
    };
    modes.addEventListener('change', syncBtn);
    modes.querySelector('input[name="scheduled_at"]').addEventListener('focus', function () {
      modes.querySelector('input[value="schedule"]').checked = true; syncBtn();
    });
    syncBtn();
  }
})();
