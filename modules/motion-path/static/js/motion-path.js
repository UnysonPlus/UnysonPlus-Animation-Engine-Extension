/*!
 * Animation Engine — Motion Path runtime.
 *
 * Sends every .sc-motion-path element travelling along an SVG path. The path `d` (data-mp-d) lives
 * in a normalized 0..100 box; we host it in one hidden <svg>, sample it with getPointAtLength, and
 * translate the element RELATIVE to the path's start point — so it begins at its natural layout
 * position and moves the shape from there, scaled to data-mp-size (px).
 *
 * Drive modes:
 *   scrub — position = the element's passage through the viewport (tied to the scrollbar).
 *   loop  — travels the path forever over data-mp-dur seconds (linear, seamless).
 *   view  — plays once (0→1, eased) when it first enters the viewport.
 * data-mp-align rotates the element to the path tangent; data-mp-reverse travels backwards.
 * Honours reduced motion (leaves the element at its layout position). One scroll listener drives
 * every scrub item; one rAF loop drives loop + active view animations.
 */
(function () {
	'use strict';

	var cfg = window.upwMotionPathCfg || {};
	var reduce = cfg.reducedMotion !== false &&
		window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	var SVGNS = 'http://www.w3.org/2000/svg';
	var host = null;

	function ensureHost() {
		if (host) { return host; }
		host = document.createElementNS(SVGNS, 'svg');
		host.setAttribute('width', '0');
		host.setAttribute('height', '0');
		host.setAttribute('aria-hidden', 'true');
		host.style.cssText = 'position:absolute;width:0;height:0;overflow:hidden;pointer-events:none;left:-9999px;top:0';
		(document.body || document.documentElement).appendChild(host);
		return host;
	}

	var EASE = {
		'linear': function (p) { return p; },
		'ease-in': function (p) { return p * p; },
		'ease-out': function (p) { return 1 - (1 - p) * (1 - p); },
		'ease-in-out': function (p) { return p < 0.5 ? 2 * p * p : 1 - Math.pow(-2 * p + 2, 2) / 2; }
	};

	var scrubItems = [];
	var loopItems = [];
	var raf = 0;

	function num(el, attr, def) {
		var v = parseFloat(el.getAttribute(attr));
		return isNaN(v) ? def : v;
	}

	function build(el) {
		var d = el.getAttribute('data-mp-d');
		if (!d) { return null; }
		var path = document.createElementNS(SVGNS, 'path');
		path.setAttribute('d', d);
		ensureHost().appendChild(path);
		var total = 0;
		try { total = path.getTotalLength(); } catch (e) { total = 0; }
		if (!total || !isFinite(total)) { host.removeChild(path); return null; }

		var size = num(el, 'data-mp-size', 300);
		var scale = size / 100;
		var reverse = el.getAttribute('data-mp-reverse') === '1';
		var align = el.getAttribute('data-mp-align') === '1';
		var dir = reverse ? -1 : 1;
		var startLen = (Math.max(0, Math.min(100, num(el, 'data-mp-offset', 0))) / 100) * total;

		function ptAt(len) {
			len = ((len % total) + total) % total;
			return path.getPointAtLength(len);
		}
		var base = ptAt(startLen);

		var item = {
			el: el, path: path, total: total, scale: scale, dir: dir, align: align,
			startLen: startLen, base: base, ptAt: ptAt,
			ease: EASE[el.getAttribute('data-mp-ease')] || EASE['ease-in-out'],
			dur: Math.max(0.5, num(el, 'data-mp-dur', 4)) * 1000
		};
		el.classList.add('sc-mp-ready');
		apply(item, 0); // seat it at its layout position
		return item;
	}

	// Place the element at path-progress p (0..1).
	function apply(item, p) {
		var travel = item.dir * (p * item.total);
		var cur = item.ptAt(item.startLen + travel);
		var dx = (cur.x - item.base.x) * item.scale;
		var dy = (cur.y - item.base.y) * item.scale;
		var t = 'translate(' + dx.toFixed(2) + 'px,' + dy.toFixed(2) + 'px)';
		if (item.align) {
			var step = Math.max(1, item.total * 0.01);
			var ahead = item.ptAt(item.startLen + travel + item.dir * step);
			var ang = Math.atan2(ahead.y - cur.y, ahead.x - cur.x) * 180 / Math.PI;
			t += ' rotate(' + ang.toFixed(1) + 'deg)';
		}
		item.el.style.transform = t;
	}

	// ---- scrub: progress from the element's passage through the viewport ----
	function measure(item) {
		var prev = item.el.style.transform;
		item.el.style.transform = 'none';
		var r = item.el.getBoundingClientRect();
		item.baseTop = r.top + (window.pageYOffset || document.documentElement.scrollTop || 0);
		item.baseH = r.height;
		item.el.style.transform = prev;
	}
	function scrubUpdate() {
		var vh = window.innerHeight || document.documentElement.clientHeight;
		var sy = window.pageYOffset || document.documentElement.scrollTop || 0;
		for (var i = 0; i < scrubItems.length; i++) {
			var it = scrubItems[i];
			var topInVp = it.baseTop - sy;
			var prog = (vh - topInVp) / (vh + it.baseH);
			prog = prog < 0 ? 0 : prog > 1 ? 1 : prog;
			apply(it, prog);
		}
	}

	// ---- loop + view: rAF ----
	function tick(now) {
		var alive = false;
		for (var i = 0; i < loopItems.length; i++) {
			var it = loopItems[i];
			if (it.mode === 'loop') {
				alive = true;
				var p = ((now - it.t0) / it.dur) % 1;
				apply(it, p);
			} else if (it.mode === 'view' && it.playing) {
				alive = true;
				var e = (now - it.t0) / it.dur;
				if (e >= 1) { e = 1; it.playing = false; }
				apply(it, it.ease(e));
			}
		}
		raf = alive ? requestAnimationFrame(tick) : 0;
	}
	function kick() { if (!raf) { raf = requestAnimationFrame(tick); } }

	function init() {
		if (reduce) { return; }
		var els = document.querySelectorAll('.sc-motion-path');
		if (!els.length) { return; }

		var viewObs = ('IntersectionObserver' in window) ? new IntersectionObserver(function (entries) {
			entries.forEach(function (en) {
				if (!en.isIntersecting) { return; }
				var it = en.target.__mpItem;
				if (it && it.mode === 'view' && !it.done) {
					it.done = true; it.playing = true; it.t0 = performance.now();
					kick();
				}
			});
		}, { threshold: 0.2 }) : null;

		for (var i = 0; i < els.length; i++) {
			var el = els[i];
			if (el.__mpItem) { continue; }
			var item = build(el);
			if (!item) { continue; }
			el.__mpItem = item;
			var drive = el.getAttribute('data-mp-drive') || 'scrub';

			if (drive === 'loop') {
				item.mode = 'loop'; item.t0 = performance.now();
				loopItems.push(item); kick();
			} else if (drive === 'view') {
				item.mode = 'view'; item.done = false; item.playing = false;
				loopItems.push(item);
				if (viewObs) { viewObs.observe(el); }
				else { item.done = true; item.playing = true; item.t0 = performance.now(); kick(); }
			} else {
				item.mode = 'scrub';
				measure(item);
				scrubItems.push(item);
			}
		}

		if (scrubItems.length) {
			window.addEventListener('scroll', scrubUpdate, { passive: true });
			window.addEventListener('resize', function () {
				for (var j = 0; j < scrubItems.length; j++) { measure(scrubItems[j]); }
				scrubUpdate();
			}, { passive: true });
			scrubUpdate();
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
