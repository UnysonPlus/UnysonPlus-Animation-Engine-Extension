/**
 * UnysonPlus — Scroll Progress "custom" driver (standalone; only loads for the Custom (code) style).
 *
 * The admin's own markup IS the rail (printed server-side into `.upw-sp upw-sp--custom`). This driver
 * does NOT build any DOM — it only publishes the current scroll progress so custom HTML/CSS/JS can bind
 * to it without re-implementing the math:
 *   - `window.upwScrollProgress` — a 0..1 number, always current.
 *   - a `--sp-progress` CSS var (0..1) on <html> AND on the `.upw-sp--custom` container, so a CSS-only
 *     rail can do e.g. `transform: scaleY(var(--sp-progress))` / `top: calc(var(--sp-progress) * 100%)`.
 *   - a `upw:scroll` CustomEvent (detail = progress) on window, for JS rails.
 * Honours the shared `hideTop` toggle by toggling `.is-hidden` on the container under 1%.
 */
(function () {
  'use strict';
  if (typeof window === 'undefined' || typeof document === 'undefined') return;
  var cfg = window.upwScrollProgCfg || {};
  var root = document.documentElement;
  function progress() {
    var sc = root.scrollHeight - root.clientHeight;
    return sc <= 0 ? 0 : Math.min(1, Math.max(0, (window.scrollY || root.scrollTop || 0) / sc));
  }
  var pend = false;
  function tick() {
    pend = false;
    var p = progress();
    window.upwScrollProgress = p;
    var v = p.toFixed(4);
    root.style.setProperty('--sp-progress', v);
    var host = document.querySelector('.upw-sp--custom');
    if (host) {
      host.style.setProperty('--sp-progress', v);
      if (cfg.hideTop) host.classList.toggle('is-hidden', p < 0.01);
    }
    try { window.dispatchEvent(new CustomEvent('upw:scroll', { detail: p })); } catch (e) {}
  }
  function onScroll() { if (pend) return; pend = true; requestAnimationFrame(tick); }
  function ready(fn) { document.readyState !== 'loading' ? fn() : document.addEventListener('DOMContentLoaded', fn); }
  ready(function () {
    tick();
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll, { passive: true });
  });
})();
