/**
 * UnysonPlus — Parallax Depth Layers runtime.
 *
 * Reads the clean data-pl-* attributes stamped by parallax.php and drives multi-layer depth
 * parallax from the pointer and/or scroll. One shared requestAnimationFrame loop; scene-level
 * pointer smoothing (each layer maps the smoothed pointer by its depth). No library.
 *
 * Guards: reduced motion + disable-on-mobile skip everything; the pointer source is skipped on
 * touch (scroll layers still move); off-screen layers are culled (IntersectionObserver); the loop
 * pauses when the tab is hidden.
 */
(function () {
  'use strict';
  if (typeof window === 'undefined' || typeof document === 'undefined') return;

  var cfg = window.upwParallaxCfg || {};

  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  ready(function () {
    var REDUCE = cfg.reducedMotion && window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var disableMobile = cfg.disableMobile && window.innerWidth < 768;
    var isTouch = window.matchMedia && window.matchMedia('(hover: none), (pointer: coarse)').matches;

    var layerEls = Array.prototype.slice.call(document.querySelectorAll('.sc-parallax-layer'));
    if (!layerEls.length || REDUCE || disableMobile) return;

    function num(el, attr, d) { var v = parseFloat(el.getAttribute(attr)); return isNaN(v) ? d : v; }
    function clamp(v, a, b) { return v < a ? a : (v > b ? b : v); }
    function easeFrom(s) { return clamp(0.06 + (1 - clamp(s, 0, 100) / 100) * 0.22, 0.05, 0.3); }

    function makeScene(el) {
      return {
        el: el,
        source: el ? (el.getAttribute('data-pl-scene') || 'mouse') : 'mouse',
        intensity: el ? num(el, 'data-pl-intensity', 40) : 40,
        ease: easeFrom(el ? num(el, 'data-pl-smooth', 50) : 50),
        rect: null, tmx: 0, tmy: 0, mx: 0, my: 0, scroll: 0
      };
    }

    var scenes = [];
    var winScene = makeScene(null); // fallback for layers with no scene ancestor
    scenes.push(winScene);
    var byEl = [];

    var layers = layerEls.map(function (el) {
      var sceneEl = el.closest ? el.closest('[data-pl-scene]') : null;
      var scene = winScene;
      if (sceneEl) {
        var found = null;
        for (var i = 0; i < byEl.length; i++) { if (byEl[i].el === sceneEl) { found = byEl[i].scene; break; } }
        if (!found) { found = makeScene(sceneEl); byEl.push({ el: sceneEl, scene: found }); scenes.push(found); }
        scene = found;
      }
      el.style.willChange = 'transform';
      return {
        el: el, scene: scene,
        depth: Math.max(0, num(el, 'data-pl-depth', 30)) / 100,
        axis: el.getAttribute('data-pl-axis') || 'both',
        dir: el.getAttribute('data-pl-dir') === 'against' ? -1 : 1,
        scale: el.getAttribute('data-pl-scale') === '1',
        blur: el.getAttribute('data-pl-blur') === '1',
        visible: true
      };
    });

    function measure() {
      for (var i = 0; i < scenes.length; i++) {
        var s = scenes[i];
        s.rect = s.el ? s.el.getBoundingClientRect()
                      : { left: 0, top: 0, width: window.innerWidth, height: window.innerHeight };
      }
    }
    measure();

    var usesMouse = !isTouch && scenes.some(function (s) { return s.source !== 'scroll'; });
    if (usesMouse) {
      window.addEventListener('mousemove', function (e) {
        for (var i = 0; i < scenes.length; i++) {
          var s = scenes[i]; if (s.source === 'scroll' || !s.rect) continue;
          s.tmx = clamp(((e.clientX - s.rect.left) / s.rect.width - 0.5) * 2, -1, 1);
          s.tmy = clamp(((e.clientY - s.rect.top) / s.rect.height - 0.5) * 2, -1, 1);
        }
      }, { passive: true });
    }

    function updateScroll() {
      for (var i = 0; i < scenes.length; i++) {
        var s = scenes[i]; if (s.source === 'mouse' || !s.el) continue;
        var r = s.el.getBoundingClientRect();
        var cy = r.top + r.height / 2;
        s.scroll = clamp((window.innerHeight / 2 - cy) / (window.innerHeight / 2 + r.height / 2), -1, 1);
      }
    }
    var ticking = false;
    window.addEventListener('scroll', function () {
      if (!ticking) { ticking = true; requestAnimationFrame(function () { updateScroll(); ticking = false; }); }
    }, { passive: true });
    window.addEventListener('resize', function () { measure(); updateScroll(); }, { passive: true });
    updateScroll();

    // Cull off-screen layers.
    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          for (var i = 0; i < layers.length; i++) { if (layers[i].el === en.target) { layers[i].visible = en.isIntersecting; break; } }
        });
      }, { rootMargin: '25%' });
      layers.forEach(function (l) { io.observe(l.el); });
    }

    var running = true;
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) { running = false; }
      else if (!running) { running = true; requestAnimationFrame(tick); }
    });

    function tick() {
      if (!running) return;
      for (var i = 0; i < scenes.length; i++) { var s = scenes[i]; s.mx += (s.tmx - s.mx) * s.ease; s.my += (s.tmy - s.my) * s.ease; }
      for (var j = 0; j < layers.length; j++) {
        var l = layers[j]; if (!l.visible) continue;
        var sc = l.scene, f = l.depth, I = sc.intensity;
        var mx = sc.source !== 'scroll' ? sc.mx : 0;
        var my = sc.source !== 'scroll' ? sc.my : 0;
        var scr = 0;
        if (sc.source !== 'mouse') {
          if (sc.el) { scr = sc.scroll; }
          else {
            var r = l.el.getBoundingClientRect(), cy = r.top + r.height / 2;
            scr = clamp((window.innerHeight / 2 - cy) / (window.innerHeight / 2 + r.height / 2), -1, 1);
          }
        }
        var tx = l.axis !== 'y' ? mx * f * I * l.dir : 0;
        var ty = l.axis !== 'x' ? (my * f * I * l.dir + scr * f * I) : 0;
        var t = 'translate3d(' + tx.toFixed(2) + 'px,' + ty.toFixed(2) + 'px,0)';
        if (l.scale) t += ' scale(' + (1 + f * 0.08).toFixed(3) + ')';
        l.el.style.transform = t;
        if (l.blur) l.el.style.filter = 'blur(' + (f * 2.2).toFixed(2) + 'px)';
      }
      requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  });
})();
