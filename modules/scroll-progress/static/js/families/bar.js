/**
 * UnysonPlus — Scroll Progress "bar" family (standalone; only the chosen family loads).
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
      if (k === 'under_nav') {
        var r1 = el('upw-sp upw-sp--bar upw-sp--top'); r1.style.height = (cfg.thickness || 4) + 'px'; r1.style.top = (cfg.offset || 60) + 'px';
        var f1 = el('upw-sp-fill'); f1.style.background = color; r1.appendChild(f1);
        return { root: r1, update: function (prog) { f1.style.width = (prog * 100).toFixed(2) + '%'; } };
      }
      if (k === 'edge') {
        var side = pos === 'left' ? 'left' : 'right';
        var r2 = el('upw-sp upw-sp--edge upw-sp--' + side); r2.style.width = (cfg.thickness || 4) + 'px';
        var f2 = el('upw-sp-fill-v'); f2.style.background = color; r2.appendChild(f2);
        return { root: r2, update: function (prog) { f2.style.height = (prog * 100).toFixed(2) + '%'; } };
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
