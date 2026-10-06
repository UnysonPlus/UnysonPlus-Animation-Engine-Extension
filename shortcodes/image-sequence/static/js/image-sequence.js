/**
 * UnysonPlus — Image Sequence scrubbing.
 *
 * Draws a frame from a preloaded sequence to a <canvas> based on scroll position. "pin" mode
 * sticks the canvas full-screen (position:sticky over a tall wrapper) and advances the frames as
 * you scroll through it; "inview" mode advances as the element passes the viewport. Frames are
 * drawn with cover/contain fit. Reduced motion → a single static frame, no scrubbing.
 *
 * Rides the shared frame scheduler (window.upwAnimRaf) and reads scroll position through the shared
 * window.upwScrollProgress helper, so it stays in lockstep with smooth scroll and the other scroll
 * effects; it only redraws when the scroll position actually changed. Falls back to a passive scroll
 * listener + local math if the shared util isn't present.
 */
(function () {
  'use strict';
  if (typeof window === 'undefined' || typeof document === 'undefined') return;
  var cfg = window.upwSeqCfg || {};
  function ready(fn) { document.readyState !== 'loading' ? fn() : document.addEventListener('DOMContentLoaded', fn); }
  function clamp(v, a, b) { return v < a ? a : (v > b ? b : v); }

  function run() {
    var REDUCE = cfg.reducedMotion !== false && (typeof window.upwReduceMotion === 'function'
      ? window.upwReduceMotion()
      : (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches));
    var seqs = Array.prototype.slice.call(document.querySelectorAll('.sc-seq'));
    if (!seqs.length) return;
    seqs.forEach(setup);

    function setup(el) {
      if (el.__seq) return; el.__seq = true;
      var frames; try { frames = JSON.parse(el.getAttribute('data-seq-frames') || '[]'); } catch (e) { frames = []; }
      if (!frames.length) return;
      var mode = el.getAttribute('data-seq-mode') || 'pin';
      var fit = el.getAttribute('data-seq-fit') || 'cover';
      var reverse = el.getAttribute('data-seq-dir') === 'reverse';
      var canvas = el.querySelector('.sc-seq__canvas'); if (!canvas) return;
      var ctx = canvas.getContext('2d');
      var host = mode === 'pin' ? (el.querySelector('.sc-seq__pin') || el) : el;
      var imgs = new Array(frames.length), loaded = new Array(frames.length), cur = -1;
      var cw = 0, ch = 0, dpr = Math.min(2, window.devicePixelRatio || 1), firstDrawn = false;

      frames.forEach(function (src, i) {
        var im = new Image(); im.decoding = 'async';
        im.onload = function () {
          loaded[i] = true;
          if (!firstDrawn) { firstDrawn = true; resize(); draw(indexFor()); }
          else if (i === cur) draw(cur);
        };
        im.src = src; imgs[i] = im;
      });

      function resize() {
        cw = Math.max(1, host.clientWidth || host.getBoundingClientRect().width);
        ch = Math.max(1, host.clientHeight || host.getBoundingClientRect().height);
        canvas.width = Math.round(cw * dpr); canvas.height = Math.round(ch * dpr);
        canvas.style.width = cw + 'px'; canvas.style.height = ch + 'px';
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      }

      function nearestLoaded(i) { for (var d = 0; d < frames.length; d++) { if (loaded[i - d]) return i - d; if (loaded[i + d]) return i + d; } return -1; }

      function draw(i) {
        if (i < 0 || i >= frames.length) return;
        if (!loaded[i]) { var j = nearestLoaded(i); if (j < 0) return; i = j; }
        var im = imgs[i]; var iw = im.naturalWidth || im.width, ih = im.naturalHeight || im.height;
        if (!iw || !ih) return;
        cur = i;
        var s = fit === 'contain' ? Math.min(cw / iw, ch / ih) : Math.max(cw / iw, ch / ih);
        var w = iw * s, h = ih * s;
        ctx.clearRect(0, 0, cw, ch);
        ctx.drawImage(im, (cw - w) / 2, (ch - h) / 2, w, h);
      }

      function progress() {
        if (typeof window.upwScrollProgress === 'function') {
          return mode === 'pin'
            ? window.upwScrollProgress(el, { pin: true, stageH: window.innerHeight || 1, align: 'top' })
            : window.upwScrollProgress(host, {});
        }
        var vh = window.innerHeight || 1;
        if (mode === 'pin') { var r = el.getBoundingClientRect(), dist = r.height - vh; return dist <= 0 ? 0 : clamp(-r.top / dist, 0, 1); }
        var r2 = host.getBoundingClientRect(); return clamp((vh - r2.top) / (vh + r2.height), 0, 1);
      }
      function indexFor() { var i = Math.round(progress() * (frames.length - 1)); return reverse ? (frames.length - 1 - i) : i; }

      var pending = false;
      function onScroll() { if (pending) return; pending = true; requestAnimationFrame(function () { pending = false; draw(indexFor()); }); }

      resize();
      draw(indexFor());
      if (REDUCE) return; // static frame only

      // Ride the shared frame scheduler — redraw only when the scroll position changed (cheap when
      // idle, frame-synced with smooth scroll). Fall back to a passive scroll listener otherwise.
      window.addEventListener('resize', function () { resize(); draw(cur < 0 ? indexFor() : cur); lastY = -1; }, { passive: true });
      var lastY = -1;
      function frame() {
        var y = window.pageYOffset || document.documentElement.scrollTop || 0;
        if (y === lastY) return;
        lastY = y;
        draw(indexFor());
      }
      if (window.upwAnimRaf && window.upwAnimRaf.add) {
        window.upwAnimRaf.add(frame);
      } else {
        window.addEventListener('scroll', onScroll, { passive: true });
      }
    }
  }
  ready(run);

  /* Re-init hook for surfaces that insert this element's markup after page load (the
     block editor's preview, the Elementor editor's re-renders): they call every
     window.fwShortcodeInit entry. The scan skips elements it has already set up, so
     repeated calls are safe. */
  window.fwShortcodeInit = window.fwShortcodeInit || [];
  window.fwShortcodeInit.push( function () { run(); } );
})();
