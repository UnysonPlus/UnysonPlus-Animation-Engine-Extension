/**
 * UnysonPlus — Scroll Progress indicator (16 styles). Builds the chosen indicator and updates it
 * from the page's scroll position. One passive scroll listener, RAF-throttled.
 */
(function () {
  'use strict';
  if (typeof window === 'undefined' || typeof document === 'undefined') return;
  var cfg = window.upwScrollProgCfg || {};
  function ready(fn) { document.readyState !== 'loading' ? fn() : document.addEventListener('DOMContentLoaded', fn); }
  function el(cls, tag) { var e = document.createElement(tag || 'div'); if (cls) e.className = cls; return e; }

  ready(function () {
    var kind = cfg.kind || 'bar', color = cfg.color || '#2f74e6', pos = cfg.position || '';
    var api = build(kind);
    if (!api) return;
    document.body.appendChild(api.root);

    function progress() { var d = document.documentElement, sc = d.scrollHeight - d.clientHeight; return sc <= 0 ? 0 : Math.min(1, Math.max(0, (window.scrollY || d.scrollTop || 0) / sc)); }
    var pend = false;
    function tick() { pend = false; var p = progress(); api.update(p); if (cfg.hideTop && kind !== 'dots') api.root.classList.toggle('is-hidden', p < 0.01); }
    function onScroll() { if (pend) return; pend = true; requestAnimationFrame(tick); }
    tick();
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll, { passive: true });

    function corner(p) { return 'upw-sp--' + (({ br: 'br', bl: 'bl', tr: 'tr', tl: 'tl' })[p] || 'br'); }

    function build(k) {
      // ---- horizontal bars ----
      if (['bar', 'gradient', 'glow', 'segments', 'pill', 'labeled', 'liquid'].indexOf(k) >= 0) {
        var p = pos === 'bottom' ? 'bottom' : 'top';
        var root = el('upw-sp upw-sp--bar upw-sp--' + p + ' upw-sp--' + k);
        root.style.height = (cfg.thickness || 4) + 'px';
        var fill = el('upw-sp-fill'); root.appendChild(fill);
        fill.style.background = k === 'gradient' ? ('linear-gradient(90deg,' + color + ',' + (cfg.color2 || '#c56cff') + ')') : color;
        var glow, label;
        if (k === 'glow') { glow = el('upw-sp-glow'); glow.style.background = color; glow.style.color = color; root.appendChild(glow); }
        if (k === 'labeled') { label = el('upw-sp-label'); root.appendChild(label); }
        if (k === 'segments') { root.style.setProperty('--seg-w', (100 / Math.max(2, cfg.segments || 12)) + '%'); }
        return { root: root, update: function (prog) {
          if (k === 'segments') { var n = Math.max(2, cfg.segments || 12); fill.style.width = (Math.round(prog * n) / n * 100).toFixed(2) + '%'; }
          else fill.style.width = (prog * 100).toFixed(2) + '%';
          if (glow) glow.style.left = 'calc(' + (prog * 100).toFixed(2) + '% - 4px)';
          if (label) { label.textContent = Math.round(prog * 100) + '%'; label.style.left = 'calc(' + (prog * 100).toFixed(2) + '% + 8px)'; }
        } };
      }
      // ---- under-nav bar ----
      if (k === 'under_nav') {
        var r1 = el('upw-sp upw-sp--bar upw-sp--top'); r1.style.height = (cfg.thickness || 4) + 'px'; r1.style.top = (cfg.offset || 60) + 'px';
        var f1 = el('upw-sp-fill'); f1.style.background = color; r1.appendChild(f1);
        return { root: r1, update: function (prog) { f1.style.width = (prog * 100).toFixed(2) + '%'; } };
      }
      // ---- side edge (vertical) ----
      if (k === 'edge') {
        var side = pos === 'left' ? 'left' : 'right';
        var r2 = el('upw-sp upw-sp--edge upw-sp--' + side); r2.style.width = (cfg.thickness || 4) + 'px';
        var f2 = el('upw-sp-fill-v'); f2.style.background = color; r2.appendChild(f2);
        return { root: r2, update: function (prog) { f2.style.height = (prog * 100).toFixed(2) + '%'; } };
      }
      // ---- ring / ring + number ----
      if (k === 'ring' || k === 'ring_number') {
        var sz = cfg.size || 56, r = (sz - 8) / 2, c = sz / 2, circ = 2 * Math.PI * r;
        var root = el('upw-sp upw-sp--circle ' + corner(pos)); root.style.width = sz + 'px'; root.style.height = sz + 'px'; root.style.color = color;
        root.innerHTML = '<svg width="' + sz + '" height="' + sz + '" viewBox="0 0 ' + sz + ' ' + sz + '"><circle class="upw-sp-track" cx="' + c + '" cy="' + c + '" r="' + r + '" fill="none" stroke-width="5"/><circle class="upw-sp-ring" cx="' + c + '" cy="' + c + '" r="' + r + '" fill="none" stroke="' + color + '" stroke-width="5" stroke-linecap="round" transform="rotate(-90 ' + c + ' ' + c + ')" stroke-dasharray="' + circ.toFixed(1) + '" stroke-dashoffset="' + circ.toFixed(1) + '"/></svg>' + (k === 'ring_number' ? '<span class="upw-sp-num">0</span>' : '<span class="upw-sp-arrow">&#8593;</span>');
        var ring = root.querySelector('.upw-sp-ring'), num = root.querySelector('.upw-sp-num');
        if (cfg.clickTop) { root.style.cursor = 'pointer'; root.setAttribute('role', 'button'); root.setAttribute('aria-label', 'Scroll to top'); root.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); }); }
        return { root: root, update: function (prog) { ring.setAttribute('stroke-dashoffset', (circ * (1 - prog)).toFixed(1)); if (num) num.textContent = Math.round(prog * 100); } };
      }
      // ---- gauge (semicircle) ----
      if (k === 'gauge') {
        var gs = cfg.size || 56, gr = (gs - 12) / 2, gc = gs / 2, half = Math.PI * gr, gy = gr + 6, gh = Math.round(gs * 0.62);
        var root = el('upw-sp upw-sp--gauge ' + corner(pos)); root.style.width = gs + 'px'; root.style.height = gh + 'px'; root.style.color = color;
        var d = 'M6 ' + gy + ' A ' + gr + ' ' + gr + ' 0 0 1 ' + (gs - 6) + ' ' + gy;
        root.innerHTML = '<svg width="' + gs + '" height="' + gh + '" viewBox="0 0 ' + gs + ' ' + gh + '"><path class="upw-sp-track" d="' + d + '" fill="none" stroke-width="6" stroke-linecap="round"/><path class="upw-sp-arc" d="' + d + '" fill="none" stroke="' + color + '" stroke-width="6" stroke-linecap="round" stroke-dasharray="' + half.toFixed(1) + '" stroke-dashoffset="' + half.toFixed(1) + '"/></svg>';
        var arc = root.querySelector('.upw-sp-arc');
        return { root: root, update: function (prog) { arc.setAttribute('stroke-dashoffset', (half * (1 - prog)).toFixed(1)); } };
      }
      // ---- battery ----
      if (k === 'battery') {
        var root = el('upw-sp upw-sp--battery ' + corner(pos)); root.style.color = color;
        root.innerHTML = '<span class="upw-sp-bat"><span class="upw-sp-bat-fill" style="background:' + color + '"></span></span><span class="upw-sp-bat-tip"></span>';
        var bf = root.querySelector('.upw-sp-bat-fill');
        return { root: root, update: function (prog) { bf.style.width = (prog * 100).toFixed(1) + '%'; } };
      }
      // ---- % counter chip ----
      if (k === 'counter') {
        var root = el('upw-sp upw-sp--chip ' + corner(pos)); root.style.background = color; root.textContent = '0%';
        return { root: root, update: function (prog) { root.textContent = Math.round(prog * 100) + '%'; } };
      }
      // ---- reading time chip ----
      if (k === 'reading_time') {
        var root = el('upw-sp upw-sp--chip upw-sp--time ' + corner(pos)); root.style.background = color;
        var words = (document.body.innerText || '').trim().split(/\s+/).length, wpm = cfg.wpm || 220;
        root.innerHTML = '<span class="upw-sp-clock"></span><span class="upw-sp-t"></span>';
        var tt = root.querySelector('.upw-sp-t');
        return { root: root, update: function (prog) { var rem = Math.ceil(words * (1 - prog) / wpm); tt.textContent = rem <= 0 ? 'Done' : ('~' + rem + ' min left'); } };
      }
      // ---- section dots (scroll-spy) ----
      if (k === 'dots') {
        var secs = Array.prototype.slice.call(document.querySelectorAll('.fw-section, section, .upw-section'))
          .filter(function (s) { return s.offsetParent !== null && s.offsetHeight > 80; });
        if (secs.length < 2) return null;
        var side = pos === 'left' ? 'left' : 'right';
        var root = el('upw-sp upw-sp--dots upw-sp--' + side); root.style.color = color;
        var dots = secs.map(function (s) { var d = el('upw-sp-dot'); d.addEventListener('click', function () { s.scrollIntoView({ behavior: 'smooth' }); }); root.appendChild(d); return d; });
        return { root: root, update: function () {
          var vh = window.innerHeight, active = 0, best = 1e9;
          secs.forEach(function (s, i) { var rc = s.getBoundingClientRect(), dd = Math.abs(rc.top - vh * 0.35); if (rc.top < vh * 0.6 && dd < best) { best = dd; active = i; } });
          dots.forEach(function (d, i) { d.classList.toggle('is-active', i === active); d.style.background = i <= active ? color : ''; });
        } };
      }
      return null;
    }
  });
})();
