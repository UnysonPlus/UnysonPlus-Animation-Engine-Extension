/**
 * Animation Engine — shared frame scheduler (window.upwAnimRaf).
 *
 * One requestAnimationFrame loop drives every subscribed animation across all engine modules,
 * instead of each module (cursor, backgrounds, physics, …) running its own. The whole loop
 * PAUSES while the tab is hidden and resumes on return — so background tabs cost nothing — and
 * a callback that returns `false` is auto-removed (one-shot / self-terminating animations).
 *
 * API:  var raf = window.upwAnimRaf;  raf.add(fn);  raf.remove(fn);
 *   fn receives the rAF timestamp; return false from fn to unsubscribe it.
 * Self-defining + idempotent: loaded once as a shared dependency; safe to include twice.
 */
(function () {
	'use strict';

	// Shared reduced-motion helper — the single source every scroll/animation module calls, instead
	// of each re-testing matchMedia. Returns true when the visitor asked the OS to reduce motion.
	// A module combines it with its own "respect reduce motion" setting, e.g.
	//   if (cfg.reducedMotion !== false && window.upwReduceMotion()) return;
	if (!window.upwReduceMotion) {
		window.upwReduceMotion = function () {
			return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
		};
	}

	// Shared "is this a small / mobile screen" helper — the single test a module combines with its own
	// "disable on mobile" setting, e.g.  if (cfg.disableMobile && window.upwIsMobile()) return;
	if (!window.upwIsMobile) {
		window.upwIsMobile = function () {
			return !!(window.matchMedia && window.matchMedia('(max-width: 767px)').matches);
		};
	}

	// Shared easing resolver — one CSS easing string (as the shared easing picker stores / resolves it:
	// linear(…) with springs and the Motion design curves, cubic-bezier(…), steps(…), or a keyword) as a
	// 0..1 → 0..1 function, for modules that ease by hand (scroll-scrubbed keyframes, GSAP). Cached per
	// string; null for anything it does not recognise, so the caller keeps its own default.
	//   var f = window.upwEase('linear(0, 0.3, 0.9, 1.05, 1)'); f(0.5) → 0.9
	if (!window.upwEase) {
		var EASE_CACHE = {};
		var bezier = function (x1, y1, x2, y2) {
			x1 = Math.max(0, Math.min(1, x1)); x2 = Math.max(0, Math.min(1, x2));
			var bx = function (t) { var u = 1 - t; return 3 * x1 * t * u * u + 3 * x2 * t * t * u + t * t * t; };
			var by = function (t) { var u = 1 - t; return 3 * y1 * t * u * u + 3 * y2 * t * t * u + t * t * t; };
			return function (x) {
				if (x <= 0) { return 0; }
				if (x >= 1) { return 1; }
				var lo = 0, hi = 1;
				for (var i = 0; i < 24; i++) { var m = (lo + hi) / 2; if (bx(m) < x) { lo = m; } else { hi = m; } }
				return by((lo + hi) / 2);
			};
		};
		var KW = { ease: [0.25, 0.1, 0.25, 1], 'ease-in': [0.42, 0, 1, 1], 'ease-out': [0, 0, 0.58, 1], 'ease-in-out': [0.42, 0, 0.58, 1] };
		window.upwEase = function (css) {
			css = String(css || '').trim();
			if (!css) { return null; }
			if (EASE_CACHE[css]) { return EASE_CACHE[css]; }
			var f = null, m, inner;
			if (css === 'linear' || css === 'none') {
				f = function (t) { return t; };
			} else if (KW[css]) {
				f = bezier.apply(null, KW[css]);
			} else if ((m = /^cubic-bezier\(([^)]*)\)$/.exec(css))) {
				var c = m[1].split(',').map(Number);
				if (c.length === 4 && !c.some(isNaN)) { f = bezier(c[0], c[1], c[2], c[3]); }
			} else if ((m = /^linear\(([^)]*)\)$/.exec(css))) {
				// Points as "value" or "value pos%"; unpositioned points are spread evenly between their neighbours.
				inner = m[1].split(',').map(function (p) { var q = p.trim().split(/\s+/); return { v: Number(q[0]), x: q[1] ? parseFloat(q[1]) / 100 : null }; });
				if (inner.length >= 2 && !inner.some(function (p) { return isNaN(p.v); })) {
					if (inner[0].x === null) { inner[0].x = 0; }
					if (inner[inner.length - 1].x === null) { inner[inner.length - 1].x = 1; }
					for (var i = 1; i < inner.length; i++) {
						if (inner[i].x !== null) { continue; }
						var j = i; while (inner[j].x === null) { j++; }
						for (var k = i; k < j; k++) { inner[k].x = inner[i - 1].x + (inner[j].x - inner[i - 1].x) * (k - i + 1) / (j - i + 1); }
					}
					f = function (t) {
						if (t <= 0) { return inner[0].v; }
						if (t >= 1) { return inner[inner.length - 1].v; }
						for (var n = 1; n < inner.length; n++) {
							if (t <= inner[n].x) {
								var a = inner[n - 1], b = inner[n], w = b.x - a.x;
								return w > 0 ? a.v + (b.v - a.v) * (t - a.x) / w : b.v;
							}
						}
						return 1;
					};
				}
			} else if ((m = /^steps\(\s*(\d+)\s*(?:,\s*([a-z-]+))?\s*\)$/.exec(css))) {
				var n = Math.max(1, parseInt(m[1], 10)), start = m[2] === 'jump-start' || m[2] === 'start';
				f = function (t) { return t >= 1 ? 1 : Math.min(1, (start ? Math.floor(t * n) + 1 : Math.floor(t * n)) / n); };
			}
			if (f) { EASE_CACHE[css] = f; }
			return f;
		};
	}

	// Shared scroll-progress helper — one 0..1 formula every scroll-scrub module uses, instead of each
	// re-deriving getBoundingClientRect math.
	//   opts.pin === true  → progress through a PINNED stretch: while `el` is pinned, 0 at the start of
	//       the pinned range and 1 at the end. opts.stageH = the pinned element's height (default vh);
	//       opts.align = 'top' (sticks at top:0, default) | 'center' (centred in the viewport) | a px
	//       offset from the top.
	//   opts.pin falsy (default) → PASS-THROUGH: 0 as `el` enters from the bottom, 1 as it clears the top.
	if (!window.upwScrollProgress) {
		window.upwScrollProgress = function (el, opts) {
			opts = opts || {};
			var r = el.getBoundingClientRect();
			var vh = window.innerHeight || document.documentElement.clientHeight || 1;
			var h = r.height || el.offsetHeight || 1;
			var p;
			if (opts.pin) {
				var stageH = (typeof opts.stageH === 'number') ? opts.stageH : vh;
				var align = (opts.align == null) ? 'top' : opts.align;
				var stick = (align === 'center') ? Math.max(0, (vh - stageH) / 2)
					: (typeof align === 'number' ? align : 0);
				p = (stick - r.top) / Math.max(1, h - stageH);
			} else {
				p = (vh - r.top) / (vh + h);
			}
			return p < 0 ? 0 : (p > 1 ? 1 : p);
		};
	}

	if (window.upwAnimRaf) { return; }

	var fns = [], id = 0;

	function tick(t) {
		id = 0;
		// Read the scroll position and viewport height ONCE, here, before any subscriber runs.
		//
		// At the top of a frame the layout is still clean (nothing has written to the DOM since the
		// last paint), so these two reads are free. Every scroll-driven module used to read them for
		// itself, scattered through the tick AFTER other subscribers had written styles - and a
		// geometry read that follows a style write forces the browser to redo layout synchronously
		// before it can answer. Measured on a real page under 4x CPU throttling, that single
		// window.pageYOffset read inside the colour-shift module cost 2.9ms across 12 reads purely
		// because of where it sat. Hoisting it here makes it one free read shared by everyone.
		//
		// Modules should prefer raf.scrollY / raf.vh over reading the window themselves, and fall
		// back to their own read when the scheduler is absent:
		//   var y = (window.upwAnimRaf && window.upwAnimRaf.scrollY) || window.pageYOffset;
		api.scrollY = window.pageYOffset || document.documentElement.scrollTop || 0;
		api.vh = window.innerHeight || document.documentElement.clientHeight || 0;
		// iterate backwards so a callback can remove itself (return false) mid-loop
		for (var i = fns.length - 1; i >= 0; i--) {
			try { if (fns[i](t) === false) { fns.splice(i, 1); } }
			catch (e) { fns.splice(i, 1); } // a broken effect must not kill the whole loop
		}
		if (fns.length && !document.hidden) { id = requestAnimationFrame(tick); }
	}

	function start() {
		if (!id && fns.length && !document.hidden) { id = requestAnimationFrame(tick); }
	}

	document.addEventListener('visibilitychange', function () { if (!document.hidden) { start(); } });

	// `scrollY` / `vh` are refreshed at the top of every tick (see tick()). They are seeded here so a
	// module that reads them before the first frame still gets a real number rather than undefined.
	var api = {
		scrollY: window.pageYOffset || document.documentElement.scrollTop || 0,
		vh: window.innerHeight || document.documentElement.clientHeight || 0,
		add: function (fn) { if (typeof fn === 'function' && fns.indexOf(fn) < 0) { fns.push(fn); start(); } return fn; },
		remove: function (fn) { var i = fns.indexOf(fn); if (i >= 0) { fns.splice(i, 1); } }
	};

	window.upwAnimRaf = api;
})();
