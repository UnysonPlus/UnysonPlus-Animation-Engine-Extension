/**
 * UnysonPlus — Scroll Progress "dots" family (standalone; only the chosen family loads).
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
