/*!
 * Animation Engine — SVG Morph runtime.
 *
 * Morphs a shortcode's <path> between two or more shapes. Each shape `d` is sampled into N equally
 * spaced points (getPointAtLength); consecutive shapes are cyclically + directionally ALIGNED (the
 * second point-ring is rotated/reversed to best match the first) so the morph doesn't twist or
 * spin; then points are linearly interpolated per frame. Works for any single-subpath closed shape
 * without a morph library. Triggers: loop, hover, on-view, click. Honours reduced motion.
 */
(function () {
	'use strict';

	var cfg = window.upwSvgMorphCfg || {};
	var reduce = cfg.reducedMotion !== false &&
		window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	var SVGNS = 'http://www.w3.org/2000/svg';
	var N = 100;          // sample points per shape
	var host = null;      // offscreen <svg> for sampling

	function ensureHost() {
		if (host) { return host; }
		host = document.createElementNS(SVGNS, 'svg');
		host.setAttribute('width', '0'); host.setAttribute('height', '0'); host.setAttribute('aria-hidden', 'true');
		host.style.cssText = 'position:absolute;left:-9999px;top:0;width:0;height:0;overflow:hidden;pointer-events:none';
		(document.body || document.documentElement).appendChild(host);
		return host;
	}

	function sample(d) {
		var p = document.createElementNS(SVGNS, 'path');
		p.setAttribute('d', d);
		ensureHost().appendChild(p);
		var total = 0;
		try { total = p.getTotalLength(); } catch (e) { total = 0; }
		var pts = [];
		if (total > 0 && isFinite(total)) {
			for (var i = 0; i < N; i++) {
				var pt = p.getPointAtLength(total * i / N);
				pts.push({ x: pt.x, y: pt.y });
			}
		}
		host.removeChild(p);
		return pts;
	}

	// Fit any point ring into the 0–100 canvas: scale the larger dimension to 88 and centre at
	// (50,50), preserving aspect. Lets shapes from any coordinate space (uploads, custom paths, the
	// library) share one consistent size, so morphs read cleanly.
	function normalize(pts) {
		if (!pts.length) { return pts; }
		var minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity, i, p;
		for (i = 0; i < pts.length; i++) {
			p = pts[i];
			if (p.x < minX) { minX = p.x; } if (p.x > maxX) { maxX = p.x; }
			if (p.y < minY) { minY = p.y; } if (p.y > maxY) { maxY = p.y; }
		}
		var s = Math.max(maxX - minX, maxY - minY) || 1;
		var scale = 88 / s, cx = (minX + maxX) / 2, cy = (minY + maxY) / 2, out = [];
		for (i = 0; i < pts.length; i++) {
			out.push({ x: 50 + (pts[i].x - cx) * scale, y: 50 + (pts[i].y - cy) * scale });
		}
		return out;
	}

	function rot(arr, off) { return off ? arr.slice(off).concat(arr.slice(0, off)) : arr; }

	// Rotate/reverse `b` to best match `a` (minimise summed squared distance over cyclic offsets
	// and both directions). Sampled every 4th point for speed — done once per shape pair.
	function align(a, b) {
		if (!a.length || !b.length) { return b; }
		var best = b, bestCost = Infinity;
		var cands = [b, b.slice().reverse()];
		for (var c = 0; c < cands.length; c++) {
			var cand = cands[c];
			for (var off = 0; off < N; off += 2) {
				var cost = 0;
				for (var i = 0; i < N; i += 4) {
					var pa = a[i], pb = cand[(i + off) % N];
					var dx = pa.x - pb.x, dy = pa.y - pb.y;
					cost += dx * dx + dy * dy;
				}
				if (cost < bestCost) { bestCost = cost; best = rot(cand, off); }
			}
		}
		return best;
	}

	function toPath(pts) {
		if (!pts.length) { return ''; }
		var d = 'M' + rnd(pts[0].x) + ',' + rnd(pts[0].y);
		for (var i = 1; i < pts.length; i++) { d += 'L' + rnd(pts[i].x) + ',' + rnd(pts[i].y); }
		return d + 'Z';
	}
	function rnd(n) { return Math.round(n * 100) / 100; }

	function lerpFrame(A, B, t) {
		var out = [];
		for (var i = 0; i < N; i++) {
			out.push({ x: A[i].x + (B[i].x - A[i].x) * t, y: A[i].y + (B[i].y - A[i].y) * t });
		}
		return out;
	}

	var EASE = {
		'linear': function (t) { return t; },
		'ease-in': function (t) { return t * t; },
		'ease-out': function (t) { return 1 - (1 - t) * (1 - t); },
		'ease-in-out': function (t) { return t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2; }
	};

	function init(el) {
		var shapes;
		try { shapes = JSON.parse(el.getAttribute('data-shapes') || '[]'); } catch (e) { shapes = []; }
		if (!Array.isArray(shapes) || shapes.length < 2) { return; }
		var path = el.querySelector('path');
		if (!path) { return; }

		// Sample → normalize → chain-align every shape into a consistent point ordering.
		var frames = [normalize(sample(shapes[0]))];
		for (var i = 1; i < shapes.length; i++) {
			frames.push(align(frames[i - 1], normalize(sample(shapes[i]))));
		}
		if (frames.some(function (f) { return f.length !== N; })) { return; }
		if (el.getAttribute('data-loopback') === '1') {
			frames.push(align(frames[frames.length - 1], frames[0])); // …→ last → first (seamless)
		}

		path.setAttribute('d', toPath(frames[0]));
		if (reduce) { return; } // static first shape

		var trigger = el.getAttribute('data-trigger') || 'loop';
		var ease = EASE[el.getAttribute('data-ease')] || EASE['ease-in-out'];
		var segs = frames.length - 1; // number of transitions

		// Per-shape timing: data-timing = [[morphDur, hold], …] in seconds (one entry per shape).
		var timing = [];
		try { timing = JSON.parse(el.getAttribute('data-timing') || '[]'); } catch (e) { timing = []; }
		function segDurMs(i) { var t = timing[i]; return ((t && t[0] > 0) ? t[0] : 1.2) * 1000; }   // morph OUT of shape i
		function holdMs(i) { var t = timing[i]; return ((t && t[1] >= 0) ? t[1] : 0.6) * 1000; }     // dwell of shape i

		function draw(from, to, t) { path.setAttribute('d', toPath(lerpFrame(frames[from], frames[to], ease(t)))); }

		if (trigger === 'loop') {
			var seg = 0, t0 = null, holding = true; // hold shape 0 first, then morph out
			(function run(now) {
				if (t0 == null) { t0 = now; }
				var e = now - t0;
				if (holding) {
					if (e >= holdMs(seg)) { holding = false; t0 = now; }
				} else {
					var d = segDurMs(seg);
					var t = d > 0 ? Math.min(1, e / d) : 1;
					draw(seg, seg + 1, t);
					if (t >= 1) { seg = (seg + 1) % segs; holding = true; t0 = now; }
				}
				requestAnimationFrame(run);
			})(performance.now());
			return;
		}

		if (trigger === 'hover') {
			// Continuous morph between shape 0 and 1, following the pointer state (shape 0's duration).
			var hdur = segDurMs(0), p = 0, target = 0, last = null, raf = 0;
			function tick(now) {
				if (last == null) { last = now; }
				var dt = now - last; last = now;
				var dir = target > p ? 1 : -1;
				p += dir * (hdur > 0 ? dt / hdur : 1);
				if ((dir > 0 && p >= target) || (dir < 0 && p <= target)) { p = target; }
				draw(0, 1, p < 0 ? 0 : p > 1 ? 1 : p);
				if (p !== target) { raf = requestAnimationFrame(tick); } else { raf = 0; last = null; }
			}
			el.addEventListener('pointerenter', function () { target = 1; if (!raf) { last = null; raf = requestAnimationFrame(tick); } });
			el.addEventListener('pointerleave', function () { target = 0; if (!raf) { last = null; raf = requestAnimationFrame(tick); } });
			return;
		}

		// view / click: play once through the sequence, each transition at its shape's duration.
		function play() {
			var seg = 0, t0 = null;
			(function run(now) {
				if (t0 == null) { t0 = now; }
				var d = segDurMs(seg);
				var t = d > 0 ? Math.min(1, (now - t0) / d) : 1;
				draw(seg, seg + 1, t);
				if (t >= 1) { seg++; if (seg >= segs) { return; } t0 = now; }
				requestAnimationFrame(run);
			})(performance.now());
		}
		if (trigger === 'click') {
			el.style.cursor = 'pointer';
			el.addEventListener('click', play);
		} else if ('IntersectionObserver' in window) {
			var io = new IntersectionObserver(function (entries) {
				entries.forEach(function (en) { if (en.isIntersecting) { play(); io.unobserve(en.target); } });
			}, { threshold: 0.3 });
			io.observe(el);
		} else {
			play();
		}
	}

	function boot() {
		var els = document.querySelectorAll('.sc-svg-morph');
		for (var i = 0; i < els.length; i++) {
			if (els[i].__morphInit) { continue; }
			els[i].__morphInit = 1;
			init(els[i]);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
