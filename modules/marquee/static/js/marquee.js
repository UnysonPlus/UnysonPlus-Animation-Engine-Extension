/**
 * UnysonPlus — Marquee runtime.
 *
 * Turns each .sc-marquee element's content into a seamless ticker: the content is repeated until
 * it spans the container ("one fill"), that fill is duplicated, and the track is translated by
 * exactly 50% (one fill) on a linear-infinite CSS animation — so the wrap is invisible for both
 * short text and long strips. Horizontal (left/right) or vertical (up/down). Pure CSS animation.
 *
 * Guards: reduced motion + disable-on-mobile leave the content static (no clone, no clip).
 */
(function () {
  'use strict';
  if (typeof window === 'undefined' || typeof document === 'undefined') return;

  var cfg = window.upwMarqueeCfg || {};
  function ready(fn) { document.readyState !== 'loading' ? fn() : document.addEventListener('DOMContentLoaded', fn); }

  ready(function () {
    var REDUCE = cfg.reducedMotion && window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var disableMobile = cfg.disableMobile && window.innerWidth < 768;
    var els = Array.prototype.slice.call(document.querySelectorAll('.sc-marquee'));
    if (!els.length) return;

    var SPEED = { slow: 40, normal: 80, fast: 140 }; // px per second
    function num(el, a, d) { var v = parseFloat(el.getAttribute(a)); return isNaN(v) ? d : v; }
    function dirOf(c) {
      if (/sc-marquee--right/.test(c)) return 'right';
      if (/sc-marquee--up/.test(c)) return 'up';
      if (/sc-marquee--down/.test(c)) return 'down';
      return 'left';
    }

    els.forEach(function (el) {
      if (el.__mq) return; el.__mq = true;
      if (REDUCE || disableMobile) return; // leave content static, unclipped

      var unitHTML = el.innerHTML.trim();
      if (!unitHTML) return;

      var dir = dirOf(el.className);
      var vertical = (dir === 'up' || dir === 'down');
      var reverse = (dir === 'right' || dir === 'down');
      var gap = num(el, 'data-mq-gap', 40);
      var sep = el.getAttribute('data-mq-sep') || '';
      var pause = el.getAttribute('data-mq-pause') !== '0';
      var fade = el.getAttribute('data-mq-fade') === '1';
      var cspeed = num(el, 'data-mq-cspeed', 0);
      var pxs = cspeed > 0 ? cspeed : (SPEED[el.getAttribute('data-mq-speed')] || SPEED.normal);

      var track = document.createElement('div');
      track.className = 'sc-mq-track' + (vertical ? ' is-vertical' : '');
      var edge = vertical ? 'marginBottom' : 'marginRight';

      function addUnit() {
        var u = document.createElement('div'); u.className = 'sc-mq-unit'; u.style[edge] = gap + 'px';
        u.innerHTML = unitHTML; track.appendChild(u);
        if (sep) { var s = document.createElement('div'); s.className = 'sc-mq-sep'; s.style[edge] = gap + 'px'; s.textContent = sep; track.appendChild(s); }
      }

      // Reset the host, mount a warp wrapper + the track, then fill until it spans the container.
      var warp = document.createElement('div');
      warp.className = 'sc-mq-warp' + (vertical ? ' is-vertical' : '');
      warp.appendChild(track);
      el.classList.add('sc-mq-live');
      el.innerHTML = '';
      el.appendChild(warp);

      var span = vertical ? el.clientHeight : el.clientWidth;
      var size = function () { return vertical ? track.scrollHeight : track.scrollWidth; };
      var guard = 0;
      do { addUnit(); guard++; } while (size() < Math.max(1, span) && guard < 80);

      var fill = size();                 // one full pass width/height
      var firstPass = track.children.length;
      // Duplicate the fill so translating -50% (== one fill) is seamless.
      for (var i = 0; i < firstPass; i++) { track.appendChild(track.children[i].cloneNode(true)); }

      var dur = Math.max(4, fill / pxs);
      track.style.animation = (vertical ? 'sc-mq-v' : 'sc-mq-h') + ' ' + dur.toFixed(2) + 's linear infinite' + (reverse ? ' reverse' : '');
      if (pause) el.classList.add('sc-mq-pausable');
      if (fade) {
        var g = 'linear-gradient(' + (vertical ? '180deg' : '90deg') + ',transparent,#000 8%,#000 92%,transparent)';
        el.style.webkitMaskImage = g; el.style.maskImage = g;
      }

      // Warp / distortion — applied to the wrapper so it doesn't fight the scroll animation.
      var tf = [];
      var tilt = num(el, 'data-mq-tilt', 0), skewh = num(el, 'data-mq-skewh', 0),
          skewv = num(el, 'data-mq-skewv', 0), bend = num(el, 'data-mq-bend', 0);
      if (tilt)  tf.push('rotate(' + (tilt / 100 * 18).toFixed(2) + 'deg)');
      if (skewh) tf.push('skewX(' + (skewh / 100 * 25).toFixed(2) + 'deg)');
      if (skewv) tf.push('skewY(' + (skewv / 100 * 25).toFixed(2) + 'deg)');
      if (bend) { el.style.perspective = '700px'; tf.push('rotateX(' + (bend / 100 * 45).toFixed(2) + 'deg)'); }
      if (tf.length) warp.style.transform = tf.join(' ');

      // Wave — per-unit vertical bob, phase-shifted by position (a travelling wave).
      var wave = num(el, 'data-mq-wave', 0);
      if (wave > 0) {
        var amp = (wave / 100 * 16).toFixed(1), us = track.querySelectorAll('.sc-mq-unit'), bd = 2.2;
        Array.prototype.forEach.call(us, function (u, i) {
          u.style.setProperty('--mq-amp', amp + 'px');
          u.style.animation = 'sc-mq-bob ' + bd + 's ease-in-out infinite';
          u.style.animationDelay = (-(i) * (bd / 10)).toFixed(2) + 's';
        });
      }
    });
  });
})();
