/**
 * UnysonPlus — Scroll Progress "battery" family (standalone; only the chosen family loads).
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
      if (k === 'battery') {
        var root = el('upw-sp upw-sp--battery ' + corner(pos)); root.style.color = color;
        root.innerHTML = '<span class="upw-sp-bat"><span class="upw-sp-bat-fill" style="background:' + color + '"></span></span><span class="upw-sp-bat-tip"></span>';
        var bf = root.querySelector('.upw-sp-bat-fill');
        return { root: root, update: function (prog) { bf.style.width = (prog * 100).toFixed(1) + '%'; } };
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
