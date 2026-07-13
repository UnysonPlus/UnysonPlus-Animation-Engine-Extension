/**
 * Animation Engine — SVG Draw shortcode runtime (vanilla, no deps).
 *
 * For each .sc-svg-draw: measure every drawable element in its inline SVG, set a
 * stroke-dash so it starts hidden, then animate stroke-dashoffset → 0 (staggered) on the
 * chosen trigger (view / load / hover), OR — in "scrub" mode — tie stroke-dashoffset to the
 * scroll position so the art draws/un-draws as the reader scrolls. Optional reverse, loop,
 * and fade-in fill after.
 * Under reduced motion the art is shown fully drawn (no animation).
 */
(function () {
	'use strict';

	var cfg = window.upwSvgDrawCfg || {};
	var mql = window.matchMedia || function () { return { matches: false }; };
	var reduce = cfg.reducedMotion && mql('(prefers-reduced-motion: reduce)').matches;
	var SEL = 'path, line, polyline, polygon, circle, rect, ellipse';

	function lengthOf(el) {
		try { if (typeof el.getTotalLength === 'function') { return el.getTotalLength() || 0; } } catch (e) { }
		var b; try { b = el.getBBox(); } catch (e2) { return 0; }
		return (b.width + b.height) * 2.4; // fallback estimate
	}

	function setup(host) {
		var svg = host.querySelector('svg');
		if (!svg) { return null; }
		var els = svg.querySelectorAll(SEL), items = [], i;
		var width = getComputedStyle(host).getPropertyValue('--draw-width') || '2px';
		var stroke = (getComputedStyle(host).getPropertyValue('--draw-stroke') || '').trim() || 'currentColor';
		for (i = 0; i < els.length; i++) {
			var el = els[i], len = lengthOf(el);
			if (len <= 0) { continue; }
			el.style.stroke = stroke;
			el.style.strokeWidth = width;
			el.style.fill = 'none';
			el.style.strokeDasharray = len;
			el.style.strokeDashoffset = len;
			el.style.strokeLinecap = 'round';
			el.style.strokeLinejoin = 'round';
			items.push({ el: el, len: len });
		}
		return items.length ? items : null;
	}

	function draw(host, items) {
		var dur = parseFloat(host.getAttribute('data-draw-duration')) || 1.6;
		var stagger = parseFloat(host.getAttribute('data-draw-stagger')) || 0;
		var reverse = host.getAttribute('data-draw-direction') === 'reverse';
		var fill = host.getAttribute('data-draw-fill') === '1';
		var n = items.length, total = dur + (n - 1) * stagger;
		for (var i = 0; i < n; i++) {
			var it = items[reverse ? (n - 1 - i) : i];
			it.el.style.transition = 'stroke-dashoffset ' + dur + 's ease';
			it.el.style.transitionDelay = (i * stagger) + 's';
			it.el.style.strokeDashoffset = '0';
		}
		if (fill) {
			host.classList.add('is-filling');
			setTimeout(function () { for (var k = 0; k < n; k++) { items[k].el.style.transition += ', fill .5s ease'; items[k].el.style.fill = 'var(--draw-fill)'; } }, total * 1000);
		}
		return total;
	}

	function reset(host, items) {
		for (var i = 0; i < items.length; i++) { var el = items[i].el; el.style.transition = 'none'; el.style.strokeDashoffset = items[i].len; el.style.fill = 'none'; }
		void host.offsetWidth; // reflow
	}

	function onView(el, cb) {
		if (!('IntersectionObserver' in window)) { cb(); return; }
		var io = new IntersectionObserver(function (e) { for (var i = 0; i < e.length; i++) { if (e[i].isIntersecting) { cb(); io.disconnect(); return; } } }, { threshold: 0.25 });
		io.observe(el);
	}

	function play(host, items) {
		var loop = host.getAttribute('data-draw-loop') === '1';
		var total = draw(host, items);
		if (loop) { setTimeout(function () { reset(host, items); setTimeout(function () { play(host, items); }, 400); }, (total + 0.8) * 1000); }
	}

	function clamp(v) { return v < 0 ? 0 : (v > 1 ? 1 : v); }

	// SCRUB: tie the draw progress to the scroll position instead of a timed transition.
	// p = 0 when the host's top is at 90% of the viewport (just entered from below),
	// p = 1 when its top reaches 25% (scrolled well up). With stagger > 0 the paths draw
	// sequentially across that band (path i completes at p = (i+1)/n); otherwise together.
	function scrubProgress(host) {
		var r = host.getBoundingClientRect();
		var vh = window.innerHeight || document.documentElement.clientHeight || 1;
		var start = vh * 0.9, end = vh * 0.25;
		return clamp((start - r.top) / (start - end || 1));
	}

	function applyScrub(host, items) {
		var p = scrubProgress(host);
		var reverse = host.getAttribute('data-draw-direction') === 'reverse';
		var stag = parseFloat(host.getAttribute('data-draw-stagger')) || 0;
		var n = items.length, j, it, lp;
		for (j = 0; j < n; j++) {
			it = items[reverse ? (n - 1 - j) : j];
			lp = (stag > 0 && n > 1) ? clamp(p * n - j) : p;
			it.el.style.transition = 'none';
			it.el.style.strokeDashoffset = String(it.len * (1 - lp));
		}
		if (host.getAttribute('data-draw-fill') === '1') {
			var full = p >= 1;
			for (j = 0; j < n; j++) { items[j].el.style.fill = full ? 'var(--draw-fill)' : 'none'; }
		}
	}

	function bindScrub(host, items) {
		var ticking = false;
		function tick() { ticking = false; applyScrub(host, items); }
		function onScroll() { if (!ticking) { ticking = true; requestAnimationFrame(tick); } }
		addEventListener('scroll', onScroll, { passive: true });
		addEventListener('resize', onScroll, { passive: true });
		applyScrub(host, items);
	}

	function init() {
		var hosts = document.querySelectorAll('.sc-svg-draw');
		Array.prototype.forEach.call(hosts, function (host) {
			if (host._upwDraw) { return; } host._upwDraw = true;
			var items = setup(host);
			if (!items) { return; }
			if (reduce) { for (var i = 0; i < items.length; i++) { items[i].el.style.strokeDashoffset = '0'; if (host.getAttribute('data-draw-fill') === '1') { items[i].el.style.fill = 'var(--draw-fill)'; } } return; }
			var trigger = host.getAttribute('data-draw-trigger') || 'view';
			if (trigger === 'scrub') { bindScrub(host, items); }
			else if (trigger === 'load') { requestAnimationFrame(function () { play(host, items); }); }
			else if (trigger === 'hover') {
				host.addEventListener('pointerenter', function () { reset(host, items); requestAnimationFrame(function () { play(host, items); }); });
			} else { onView(host, function () { play(host, items); }); }
		});
	}
	if (document.readyState !== 'loading') { init(); } else { document.addEventListener('DOMContentLoaded', init); }
})();
