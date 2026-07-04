/**
 * UnysonPlus — Scroll Progress "circle" family (standalone; only the chosen family loads).
 */
(function () {
  'use strict';
  if (typeof window === 'undefined' || typeof document === 'undefined') return;
  var cfg = window.upwScrollProgCfg || {};
  function ready(fn) { document.readyState !== 'loading' ? fn() : document.addEventListener('DOMContentLoaded', fn); }
  function el(cls, tag) { var e = document.createElement(tag || 'div'); if (cls) e.className = cls; return e; }
  ready(function () {
    var kind = cfg.kind || 'bar', color = cfg.color || '#2f74e6', pos = cfg.position || '';
    function corner(p) { return 'upw-sp--' + (({ br: 'br', bl: 'bl', tr: 'tr', tl: 'tl' })[p] || 'br'); }
    function build(k) {
      if (k === 'ring' || k === 'ring_number') {
        var sz = cfg.size || 56, r = (sz - 8) / 2, c = sz / 2, circ = 2 * Math.PI * r;
        var root = el('upw-sp upw-sp--circle ' + corner(pos)); root.style.width = sz + 'px'; root.style.height = sz + 'px'; root.style.color = color;
        root.innerHTML = '<svg width="' + sz + '" height="' + sz + '" viewBox="0 0 ' + sz + ' ' + sz + '"><circle class="upw-sp-track" cx="' + c + '" cy="' + c + '" r="' + r + '" fill="none" stroke-width="5"/><circle class="upw-sp-ring" cx="' + c + '" cy="' + c + '" r="' + r + '" fill="none" stroke="' + color + '" stroke-width="5" stroke-linecap="round" transform="rotate(-90 ' + c + ' ' + c + ')" stroke-dasharray="' + circ.toFixed(1) + '" stroke-dashoffset="' + circ.toFixed(1) + '"/></svg>' + (k === 'ring_number' ? '<span class="upw-sp-num">0</span>' : '<span class="upw-sp-arrow">&#8593;</span>');
        var ring = root.querySelector('.upw-sp-ring'), num = root.querySelector('.upw-sp-num');
        if (cfg.clickTop) { root.style.cursor = 'pointer'; root.setAttribute('role', 'button'); root.setAttribute('aria-label', 'Scroll to top'); root.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); }); }
        return { root: root, update: function (prog) { ring.setAttribute('stroke-dashoffset', (circ * (1 - prog)).toFixed(1)); if (num) num.textContent = Math.round(prog * 100); } };
      }
      if (k === 'gauge') {
        var gs = cfg.size || 56, gr = (gs - 12) / 2, gc = gs / 2, half = Math.PI * gr, gy = gr + 6, gh = Math.round(gs * 0.62);
        var root = el('upw-sp upw-sp--gauge ' + corner(pos)); root.style.width = gs + 'px'; root.style.height = gh + 'px'; root.style.color = color;
        var d = 'M6 ' + gy + ' A ' + gr + ' ' + gr + ' 0 0 1 ' + (gs - 6) + ' ' + gy;
        root.innerHTML = '<svg width="' + gs + '" height="' + gh + '" viewBox="0 0 ' + gs + ' ' + gh + '"><path class="upw-sp-track" d="' + d + '" fill="none" stroke-width="6" stroke-linecap="round"/><path class="upw-sp-arc" d="' + d + '" fill="none" stroke="' + color + '" stroke-width="6" stroke-linecap="round" stroke-dasharray="' + half.toFixed(1) + '" stroke-dashoffset="' + half.toFixed(1) + '"/></svg>';
        var arc = root.querySelector('.upw-sp-arc');
        return { root: root, update: function (prog) { arc.setAttribute('stroke-dashoffset', (half * (1 - prog)).toFixed(1)); } };
      }
      return null;
    }
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
  });
})();
