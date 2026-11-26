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
  function progress(y) {
    var sc = root.scrollHeight - root.clientHeight;
    return sc <= 0 ? 0 : Math.min(1, Math.max(0, (y != null ? y : (window.scrollY || root.scrollTop || 0)) / sc));
  }
  // window.upwScrollProgress is ALSO the engine's shared scroll-progress helper FUNCTION (static/js/upw-raf.js), which
  // Page Timelines, Scroll Keyframes, Image Sequence and Interactive Reveal call. Overwriting it with a number broke
  // them on a page that also had this family. So when the helper is there, the page progress rides ON it: .value, and
  // valueOf / toString so `upwScrollProgress * 100` still works. Without the helper it stays a plain number.
  function publish(p) {
    var h = window.upwScrollProgress;
    if (typeof h === 'function') {
      h.value = p;
      if (!h.__upwSpNum) { h.__upwSpNum = 1; h.valueOf = function () { return h.value; }; h.toString = function () { return String(h.value); }; }
    } else {
      window.upwScrollProgress = p;
    }
  }
  var pend = false;
  function tick(y) {
    pend = false;
    var p = progress(y);
    publish(p);
    var v = p.toFixed(4);
    root.style.setProperty('--sp-progress', v);
    var host = document.querySelector('.upw-sp--custom');
    if (host) {
      host.style.setProperty('--sp-progress', v);
      if (cfg.hideTop) host.classList.toggle('is-hidden', p < 0.01);
    }
    try { window.dispatchEvent(new CustomEvent('upw:scroll', { detail: p })); } catch (e) {}
  }
  function onScroll() { if (pend) return; pend = true; window.upwAnimRaf.add(function () { tick(window.upwAnimRaf.scrollY); return false; }); } // one update per frame, on the engine's shared loop (scroll position read once per frame there)
  function ready(fn) { document.readyState !== 'loading' ? fn() : document.addEventListener('DOMContentLoaded', fn); }
  ready(function () {
    tick();
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll, { passive: true });
  });
})();
