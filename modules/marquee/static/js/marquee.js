/**
 * UnysonPlus — Marquee runtime.
 *
 * Seamless ticker for any element's content, driven by ONE shared requestAnimationFrame loop so
 * pause-on-hover, drag-with-momentum and scroll-reactive speed all work uniformly.
 *
 *  - Straight mode: the content is cloned into a doubled track; the track is translated by JS
 *    (modulo one fill), seamless for short text or long strips. Skew / tilt / bend (3D) ride on a
 *    warp wrapper; Wave bobs the units.
 *  - Curved mode (Curve ≠ 0, horizontal, text content): the TEXT is rendered on an SVG arc path —
 *    a real curve — and scrolled by animating the textPath startOffset (modulo one repeat).
 *
 * Guards: reduced motion + disable-on-mobile leave the content static, unclipped.
 */
(function () {
  'use strict';
  if (typeof window === 'undefined' || typeof document === 'undefined') return;

  var cfg = window.upwMarqueeCfg || {};
  var SVGNS = 'http://www.w3.org/2000/svg', XLINK = 'http://www.w3.org/1999/xlink', uid = 0;
  function ready(fn) { document.readyState !== 'loading' ? fn() : document.addEventListener('DOMContentLoaded', fn); }
  function num(el, a, d) { var v = parseFloat(el.getAttribute(a)); return isNaN(v) ? d : v; }
  function now() { return (window.performance && performance.now) ? performance.now() : Date.now(); }

  ready(function () {
    var REDUCE = cfg.reducedMotion && window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var disableMobile = cfg.disableMobile && window.innerWidth < 768;
    var els = Array.prototype.slice.call(document.querySelectorAll('.sc-marquee'));
    if (!els.length || REDUCE || disableMobile) return;

    var SPEED = { slow: 40, normal: 80, fast: 140 };
    function dirOf(c) { if (/sc-marquee--right/.test(c)) return 'right'; if (/sc-marquee--up/.test(c)) return 'up'; if (/sc-marquee--down/.test(c)) return 'down'; return 'left'; }

    var items = [];
    els.forEach(function (el) { var m = build(el); if (m) items.push(m); });
    if (!items.length) return;

    // Global scroll velocity → a decaying boost for scroll-reactive marquees.
    var lastY = window.scrollY || window.pageYOffset || 0, boost = 0;
    window.addEventListener('scroll', function () {
      var y = window.scrollY || window.pageYOffset || 0;
      boost = Math.min(8, boost + Math.abs(y - lastY) * 0.06); lastY = y;
    }, { passive: true });

    var last = 0, running = true;
    document.addEventListener('visibilitychange', function () { if (document.hidden) { running = false; } else if (!running) { running = true; last = 0; requestAnimationFrame(tick); } });

    function tick(t) {
      if (!running) return;
      var dt = last ? Math.min(0.05, (t - last) / 1000) : 0.016; last = t;
      boost *= 0.92;
      for (var i = 0; i < items.length; i++) {
        var m = items[i];
        if (m.dragging) { m.apply(); continue; }
        if (m.paused && !m.momentum) continue;
        if (m.momentum) {
          m.offset += m.momentum * dt; m.momentum *= 0.94;
          if (Math.abs(m.momentum) < 3) m.momentum = 0;
        } else {
          m.offset += m.pxs * (1 + (m.scrollReactive ? boost : 0)) * dt;
        }
        m.apply();
      }
      requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);

    function build(el) {
      if (el.__mq) return null; el.__mq = true;
      var unitHTML = el.innerHTML.trim(); if (!unitHTML) return null;
      var dir = dirOf(el.className), vertical = dir === 'up' || dir === 'down', reverse = dir === 'right' || dir === 'down';
      var gap = num(el, 'data-mq-gap', 40), sep = el.getAttribute('data-mq-sep') || '';
      var pause = el.getAttribute('data-mq-pause') !== '0', fade = el.getAttribute('data-mq-fade') === '1';
      var cspeed = num(el, 'data-mq-cspeed', 0), pxs = cspeed > 0 ? cspeed : (SPEED[el.getAttribute('data-mq-speed')] || SPEED.normal);
      var curve = num(el, 'data-mq-curve', 0);
      var m = { el: el, offset: 0, pxs: pxs, reverse: reverse, vertical: vertical, paused: false, dragging: false, momentum: 0,
                scrollReactive: el.getAttribute('data-mq-scrollreact') === '1', apply: function () {} };
      el.classList.add('sc-mq-live');

      var text = (el.textContent || '').replace(/\s+/g, ' ').trim();
      if (curve !== 0 && !vertical && text) buildCurved(m, el, text, sep, curve, fade);
      else buildStraight(m, el, unitHTML, vertical, gap, sep, fade);

      if (pause) { el.addEventListener('mouseenter', function () { m.paused = true; }); el.addEventListener('mouseleave', function () { m.paused = false; }); }
      if (el.getAttribute('data-mq-drag') === '1') setupDrag(m, el, vertical);
      return m;
    }

    function buildStraight(m, el, unitHTML, vertical, gap, sep, fade) {
      var track = document.createElement('div'); track.className = 'sc-mq-track' + (vertical ? ' is-vertical' : '');
      var edge = vertical ? 'marginBottom' : 'marginRight';
      function addUnit() {
        var u = document.createElement('div'); u.className = 'sc-mq-unit'; u.style[edge] = gap + 'px'; u.innerHTML = unitHTML; track.appendChild(u);
        if (sep) { var s = document.createElement('div'); s.className = 'sc-mq-sep'; s.style[edge] = gap + 'px'; s.textContent = sep; track.appendChild(s); }
      }
      var warp = document.createElement('div'); warp.className = 'sc-mq-warp' + (vertical ? ' is-vertical' : ''); warp.appendChild(track);
      el.innerHTML = ''; el.appendChild(warp);
      var span = vertical ? el.clientHeight : el.clientWidth, size = function () { return vertical ? track.scrollHeight : track.scrollWidth; }, g = 0;
      do { addUnit(); g++; } while (size() < Math.max(1, span) && g < 80);
      var fill = size(), pass = track.children.length;
      for (var i = 0; i < pass; i++) track.appendChild(track.children[i].cloneNode(true));
      m.apply = function () {
        var pos = ((m.offset % fill) + fill) % fill, v = m.reverse ? (pos - fill) : -pos;
        track.style.transform = (vertical ? 'translateY(' : 'translateX(') + v.toFixed(1) + 'px)';
      };
      applyWarp(el, warp, false);
      var wave = num(el, 'data-mq-wave', 0);
      if (wave > 0) {
        var amp = (wave / 100 * 16).toFixed(1), us = track.querySelectorAll('.sc-mq-unit'), bd = 2.2;
        Array.prototype.forEach.call(us, function (u, i) { u.style.setProperty('--mq-amp', amp + 'px'); u.style.animation = 'sc-mq-bob ' + bd + 's ease-in-out infinite'; u.style.animationDelay = (-(i) * (bd / 10)).toFixed(2) + 's'; });
      }
      if (fade) { var gr = 'linear-gradient(' + (vertical ? '180deg' : '90deg') + ',transparent,#000 8%,#000 92%,transparent)'; el.style.webkitMaskImage = gr; el.style.maskImage = gr; }
    }

    function buildCurved(m, el, text, sep, curve, fade) {
      var cs = getComputedStyle(el);
      var W = el.clientWidth || 600, H = Math.max(el.clientHeight || 0, parseFloat(cs.fontSize) * 1.8, 100);
      var mid = H / 2, arc = curve / 100 * (H * 0.5), id = 'mqpath' + (++uid);
      var svg = document.createElementNS(SVGNS, 'svg');
      svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H); svg.setAttribute('width', W); svg.setAttribute('height', H);
      svg.setAttribute('preserveAspectRatio', 'none'); svg.setAttribute('class', 'sc-mq-svg');
      var defs = document.createElementNS(SVGNS, 'defs'), path = document.createElementNS(SVGNS, 'path');
      path.setAttribute('id', id); path.setAttribute('fill', 'none');
      path.setAttribute('d', 'M 0 ' + mid + ' Q ' + (W / 2) + ' ' + (mid - arc) + ' ' + W + ' ' + mid);
      defs.appendChild(path); svg.appendChild(defs);
      var t = document.createElementNS(SVGNS, 'text');
      t.setAttribute('fill', cs.color); t.setAttribute('font-family', cs.fontFamily);
      t.setAttribute('font-size', parseFloat(cs.fontSize)); t.setAttribute('font-weight', cs.fontWeight);
      t.setAttribute('dominant-baseline', 'middle'); t.setAttribute('letter-spacing', cs.letterSpacing === 'normal' ? '0' : cs.letterSpacing);
      if (el.classList.contains('sc-mq--outline')) { t.setAttribute('fill', 'none'); t.setAttribute('stroke', cs.color); t.setAttribute('stroke-width', '1'); }
      var tp = document.createElementNS(SVGNS, 'textPath');
      tp.setAttributeNS(XLINK, 'href', '#' + id); tp.setAttribute('href', '#' + id);
      var unit = text + ' ' + (sep ? sep + ' ' : '');
      tp.textContent = unit; t.appendChild(tp); svg.appendChild(t);
      el.innerHTML = ''; el.appendChild(svg);
      var unitLen = (tp.getComputedTextLength && tp.getComputedTextLength()) || (W * 0.5);
      var pathLen = (path.getTotalLength && path.getTotalLength()) || W;
      var reps = Math.ceil((pathLen + unitLen) / unitLen) + 1, full = '';
      for (var i = 0; i < reps; i++) full += unit;
      tp.textContent = full;
      m.apply = function () {
        var pos = ((m.offset % unitLen) + unitLen) % unitLen, so = m.reverse ? (pos - unitLen) : -pos;
        tp.setAttribute('startOffset', so.toFixed(1));
      };
      applyWarp(el, svg, true); // skew/tilt only — the arc IS the curve
      if (fade) { var gr = 'linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent)'; el.style.webkitMaskImage = gr; el.style.maskImage = gr; }
    }

    function applyWarp(el, target, noBend) {
      var tf = [], tilt = num(el, 'data-mq-tilt', 0), sh = num(el, 'data-mq-skewh', 0), sv = num(el, 'data-mq-skewv', 0), bend = num(el, 'data-mq-bend', 0);
      if (tilt) tf.push('rotate(' + (tilt / 100 * 18).toFixed(2) + 'deg)');
      if (sh) tf.push('skewX(' + (sh / 100 * 25).toFixed(2) + 'deg)');
      if (sv) tf.push('skewY(' + (sv / 100 * 25).toFixed(2) + 'deg)');
      if (!noBend && bend) { el.style.perspective = '700px'; tf.push('rotateX(' + (bend / 100 * 45).toFixed(2) + 'deg)'); }
      if (tf.length) target.style.transform = tf.join(' ');
    }

    function setupDrag(m, el, vertical) {
      el.classList.add('sc-mq-grab');
      var startPos = 0, startOff = 0, lastPos = 0, lastT = 0, vel = 0;
      el.addEventListener('pointerdown', function (e) {
        m.dragging = true; m.momentum = 0; el.classList.add('sc-mq-grabbing');
        startPos = vertical ? e.clientY : e.clientX; startOff = m.offset; lastPos = startPos; lastT = now(); vel = 0;
        if (e.pointerId != null && el.setPointerCapture) { try { el.setPointerCapture(e.pointerId); } catch (x) {} }
      });
      window.addEventListener('pointermove', function (e) {
        if (!m.dragging) return;
        var p = vertical ? e.clientY : e.clientX;
        m.offset = startOff - (p - startPos);
        var nt = now(), d = nt - lastT;
        if (d > 0) { vel = -(p - lastPos) / d * 1000; lastPos = p; lastT = nt; }
      }, { passive: true });
      function end() { if (!m.dragging) return; m.dragging = false; el.classList.remove('sc-mq-grabbing'); m.momentum = Math.max(-2500, Math.min(2500, vel)); }
      window.addEventListener('pointerup', end); window.addEventListener('pointercancel', end);
    }
  });
})();
