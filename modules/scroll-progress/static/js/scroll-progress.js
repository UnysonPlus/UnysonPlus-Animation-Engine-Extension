/**
 * UnysonPlus — Scroll Progress indicator. Builds a top/bottom bar or a corner ring that fills
 * with the page's scroll position. One passive scroll listener, RAF-throttled.
 */
(function () {
  'use strict';
  if (typeof window === 'undefined' || typeof document === 'undefined') return;
  var cfg = window.upwScrollProgCfg || {};
  function ready(fn) { document.readyState !== 'loading' ? fn() : document.addEventListener('DOMContentLoaded', fn); }

  ready(function () {
    var kind = cfg.kind || 'bar_top', color = cfg.color || '#2f74e6';
    var root, fillEl, ring, circ = 0;

    if (kind === 'circle') {
      var sz = cfg.size || 52, r = (sz - 6) / 2, c = sz / 2; circ = 2 * Math.PI * r;
      root = document.createElement('div');
      root.className = 'upw-sp upw-sp--circle upw-sp--' + (cfg.position === 'bl' ? 'bl' : 'br');
      root.style.width = sz + 'px'; root.style.height = sz + 'px'; root.style.color = color;
      root.innerHTML =
        '<svg width="' + sz + '" height="' + sz + '" viewBox="0 0 ' + sz + ' ' + sz + '">'
        + '<circle class="upw-sp-track" cx="' + c + '" cy="' + c + '" r="' + r + '" fill="none" stroke-width="4"/>'
        + '<circle class="upw-sp-ring" cx="' + c + '" cy="' + c + '" r="' + r + '" fill="none" stroke-width="4" stroke="' + color + '" stroke-linecap="round" transform="rotate(-90 ' + c + ' ' + c + ')" stroke-dasharray="' + circ.toFixed(1) + '" stroke-dashoffset="' + circ.toFixed(1) + '"/>'
        + '</svg><span class="upw-sp-arrow" aria-hidden="true">&#8593;</span>';
      ring = root.querySelector('.upw-sp-ring');
      if (cfg.clickTop) {
        root.style.cursor = 'pointer'; root.setAttribute('role', 'button'); root.setAttribute('aria-label', 'Scroll to top');
        root.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });
      }
    } else {
      root = document.createElement('div');
      root.className = 'upw-sp upw-sp--bar upw-sp--' + (kind === 'bar_bottom' ? 'bottom' : 'top');
      root.style.height = (cfg.thickness || 4) + 'px';
      fillEl = document.createElement('div'); fillEl.className = 'upw-sp-fill';
      fillEl.style.background = color; fillEl.style.width = '0%';
      root.appendChild(fillEl);
    }
    document.body.appendChild(root);

    function progress() {
      var d = document.documentElement, sc = d.scrollHeight - d.clientHeight;
      if (sc <= 0) return 0;
      return Math.min(1, Math.max(0, (window.scrollY || d.scrollTop || 0) / sc));
    }
    function update() {
      var p = progress();
      if (kind === 'circle') ring.setAttribute('stroke-dashoffset', (circ * (1 - p)).toFixed(1));
      else fillEl.style.width = (p * 100).toFixed(2) + '%';
      if (cfg.hideTop) root.classList.toggle('is-hidden', p < 0.01);
    }
    var pend = false;
    function onScroll() { if (pend) return; pend = true; requestAnimationFrame(function () { pend = false; update(); }); }
    update();
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll, { passive: true });
  });
})();
