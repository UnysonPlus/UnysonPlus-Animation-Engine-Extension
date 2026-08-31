/**
 * UnysonPlus — Interactive Reveal.
 *
 * Animates stacked image layers from an assembled state to an exploded/peeled state
 * using a single progress value (0..1). The value is produced by the chosen trigger:
 *   scroll  — rides the shared frame scheduler (window.upwAnimRaf) + window.upwScrollProgress.
 *   hover   — tweens 0→1 on pointer-enter / focus, back on leave.
 *   toggle  — click flips between assembled and exploded.
 *   auto    — loops assembled ↔ exploded while on-screen.
 * Each layer's transform is translate(dx%,dy%) rotate(rot) scale(1+scale) at full reveal.
 * Hotspots fade in once progress passes their data-at threshold.
 * Reduced motion → the fully-revealed static state (internals + all hotspots visible), no motion.
 */
(function () {
  'use strict';
  if (typeof window === 'undefined' || typeof document === 'undefined') return;
  var cfg = window.upwIRevealCfg || {};
  function ready(fn) { document.readyState !== 'loading' ? fn() : document.addEventListener('DOMContentLoaded', fn); }
  function clamp(v, a, b) { return v < a ? a : (v > b ? b : v); }
  function easeInOut(t) { return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2; }

  ready(function () {
    var REDUCE = cfg.reducedMotion !== false && (typeof window.upwReduceMotion === 'function'
      ? window.upwReduceMotion()
      : (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches));

    Array.prototype.slice.call(document.querySelectorAll('.sc-ireveal')).forEach(setup);

    function setup(el) {
      if (el.__ireveal) return; el.__ireveal = true;

      var trigger  = el.getAttribute('data-ir-trigger') || 'scroll';
      var reverse  = el.getAttribute('data-ir-reverse') === '1';
      var rest     = clamp(parseFloat(el.getAttribute('data-ir-start') || '0') / 100, 0, 1);
      var duration = Math.max(120, parseFloat(el.getAttribute('data-ir-duration') || '700'));

      var from = reverse ? 1 : rest;
      var to   = reverse ? rest : 1;

      var layers = Array.prototype.slice.call(el.querySelectorAll('.sc-ireveal__layer')).map(function (n) {
        return {
          n: n,
          dx: parseFloat(n.getAttribute('data-dx')) || 0,
          dy: parseFloat(n.getAttribute('data-dy')) || 0,
          rot: parseFloat(n.getAttribute('data-rot')) || 0,
          sc: parseFloat(n.getAttribute('data-scale')) || 0,
          fade: n.getAttribute('data-fade') === '1'
        };
      });
      var spots = Array.prototype.slice.call(el.querySelectorAll('.sc-ireveal__spot')).map(function (n) {
        return { n: n, at: (parseFloat(n.getAttribute('data-at')) || 0) / 100 };
      });
      if (!layers.length) return;

      // Apply a reveal progress p (0 = assembled, 1 = fully exploded) to every layer + hotspot.
      function apply(p) {
        for (var i = 0; i < layers.length; i++) {
          var L = layers[i];
          var t = 'translate(' + (L.dx * p) + '%,' + (L.dy * p) + '%) rotate(' + (L.rot * p) + 'deg) scale(' + (1 + L.sc * p / 100) + ')';
          L.n.style.transform = t;
          if (L.fade) L.n.style.opacity = clamp(0.04 + p, 0, 1);
        }
        for (var j = 0; j < spots.length; j++) {
          spots[j].n.classList.toggle('is-on', p >= spots[j].at - 0.001);
        }
      }

      // effective progress = from → to as the driver d goes 0 → 1.
      function applyDriver(d) { apply(from + (to - from) * clamp(d, 0, 1)); }

      // --- Reduced motion: fully-revealed static state, no animation. ---------
      if (REDUCE) { apply(1); return; }

      // --- Scroll ------------------------------------------------------------
      if (trigger === 'scroll') {
        var pinned = el.getAttribute('data-ir-pin') === '1';
        var pinWrap = pinned && el.closest ? el.closest('.sc-ireveal-pin') : null;
        applyDriver(0);
        var lastY = -1;
        function frame() {
          var y = window.pageYOffset || document.documentElement.scrollTop || 0;
          if (y === lastY) return; lastY = y;
          var d;
          if (typeof window.upwScrollProgress === 'function') {
            d = (pinned && pinWrap)
              ? window.upwScrollProgress(pinWrap, { pin: true, stageH: window.innerHeight || 1, align: 'top' })
              : window.upwScrollProgress(el, {});
          } else { d = localProgress(); }
          applyDriver(d);
        }
        function localProgress() {
          var vh = window.innerHeight || 1;
          if (pinned && pinWrap) {
            var rp = pinWrap.getBoundingClientRect(), dist = rp.height - vh;
            return dist <= 0 ? 0 : clamp(-rp.top / dist, 0, 1);
          }
          var r = el.getBoundingClientRect();
          return clamp((vh - r.top) / (vh + r.height), 0, 1);
        }
        if (window.upwAnimRaf && window.upwAnimRaf.add) { window.upwAnimRaf.add(frame); }
        else { window.addEventListener('scroll', function () { lastY = -1; frame(); }, { passive: true }); }
        window.addEventListener('resize', function () { lastY = -1; }, { passive: true });
        frame();
        return;
      }

      // --- Tweened drivers (hover / toggle / auto) ---------------------------
      var d = 0, target = 0, raf = 0, animStart = 0, animFrom = 0;
      applyDriver(0);

      function tweenTo(tv) {
        target = tv; animFrom = d; animStart = 0;
        if (raf) return;
        raf = requestAnimationFrame(step);
      }
      function step(ts) {
        if (!animStart) animStart = ts;
        var k = clamp((ts - animStart) / duration, 0, 1);
        d = animFrom + (target - animFrom) * easeInOut(k);
        applyDriver(d);
        if (k < 1) { raf = requestAnimationFrame(step); } else { raf = 0; d = target; }
      }

      if (trigger === 'hover') {
        el.addEventListener('mouseenter', function () { tweenTo(1); });
        el.addEventListener('mouseleave', function () { tweenTo(0); });
        el.addEventListener('focusin', function () { tweenTo(1); });
        el.addEventListener('focusout', function () { tweenTo(0); });
      } else if (trigger === 'toggle') {
        if (!el.hasAttribute('tabindex')) el.setAttribute('tabindex', '0');
        el.setAttribute('role', 'button');
        var open = false;
        function flip() { open = !open; tweenTo(open ? 1 : 0); }
        el.addEventListener('click', flip);
        el.addEventListener('keydown', function (e) {
          if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); flip(); }
        });
      } else if (trigger === 'drag') {
        var axis = el.getAttribute('data-ir-drag-axis') === 'y' ? 'y' : 'x';
        var dragging = false, startPos = 0, startD = 0;
        el.addEventListener('pointerdown', function (e) {
          dragging = true; startD = d; startPos = axis === 'y' ? e.clientY : e.clientX;
          el.classList.add('is-dragging');
          if (el.setPointerCapture) { try { el.setPointerCapture(e.pointerId); } catch (x) {} }
        });
        el.addEventListener('pointermove', function (e) {
          if (!dragging) return;
          var pos = axis === 'y' ? e.clientY : e.clientX;
          var size = (axis === 'y' ? el.clientHeight : el.clientWidth) || 1;
          d = clamp(startD + (pos - startPos) / size, 0, 1);
          applyDriver(d);
        });
        function endDrag() { dragging = false; el.classList.remove('is-dragging'); }
        el.addEventListener('pointerup', endDrag);
        el.addEventListener('pointercancel', endDrag);
      } else if (trigger === 'auto') {
        var dir = 1, io;
        function loop() {
          tweenTo(dir > 0 ? 1 : 0);
          dir = -dir;
        }
        var timer = 0;
        function run() { loop(); timer = setInterval(loop, duration + 400); }
        function stop() { if (timer) { clearInterval(timer); timer = 0; } }
        if ('IntersectionObserver' in window) {
          io = new IntersectionObserver(function (ents) {
            ents.forEach(function (e2) { e2.isIntersecting ? (timer || run()) : stop(); });
          }, { threshold: 0.2 });
          io.observe(el);
        } else { run(); }
        el.addEventListener('mouseenter', stop);
        el.addEventListener('mouseleave', function () { if (!timer) run(); });
      }
    }
  });
})();
