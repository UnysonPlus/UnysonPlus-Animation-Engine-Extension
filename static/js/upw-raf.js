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

	// Shared easing resolver: THE one set of curves every engine module eases with (roadmap Phase 11). Takes
	//   - a CSS easing string, as the shared easing picker stores / resolves it: linear(…) with springs and the
	//     Motion design curves, cubic-bezier(…), steps(…), or a keyword (ease, ease-in, ease-out, ease-in-out);
	//   - a named curve: none, power1-4 (quad, cubic, quart, quint, strong), sine, expo, circ, back(s), elastic(a,p),
	//     bounce, each .in / .out (default) / .inOut, e.g. 'power3.out', 'back.out(1.7)', 'elastic.out(1,0.5)'.
	// and returns a 0..1 → 0..1 function, cached per string; null for anything it does not recognise, so the caller
	// keeps its own default. The scroll core's upwScroll.ease() and every module's own short names map onto it.
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
		// Named curves, as "in" functions; .out and .inOut are made from them.
		var powIn = function (p) { return function (t) { return Math.pow(t, p); }; };
		var mkOut = function (g) { return function (t) { return 1 - g(1 - t); }; };
		var mkInOut = function (g) { return function (t) { return t < 0.5 ? g(t * 2) / 2 : 1 - g((1 - t) * 2) / 2; }; };
		var NAMED = {
			power1: powIn(2), quad: powIn(2), power2: powIn(3), cubic: powIn(3), power3: powIn(4), quart: powIn(4),
			power4: powIn(5), quint: powIn(5), strong: powIn(5),
			sine: function (t) { return 1 - Math.cos(t * Math.PI / 2); },
			expo: function (t) { return t === 0 ? 0 : Math.pow(2, 10 * (t - 1)); },
			circ: function (t) { return 1 - Math.sqrt(1 - t * t); }
		};
		var backIn = function (b) { b = isNaN(b) ? 1.70158 : b; return function (t) { return t * t * ((b + 1) * t - b); }; };
		var elasticOut = function (a, p) {
			a = isNaN(a) ? 1 : Math.max(1, a); p = isNaN(p) ? 0.3 : p;
			var o = p / (2 * Math.PI) * Math.asin(1 / a);
			return function (t) { return t === 0 || t === 1 ? t : a * Math.pow(2, -10 * t) * Math.sin((t - o) * (2 * Math.PI) / p) + 1; };
		};
		var bounceOut = function (t) {
			var n = 7.5625, d = 2.75;
			if (t < 1 / d) { return n * t * t; }
			if (t < 2 / d) { t -= 1.5 / d; return n * t * t + 0.75; }
			if (t < 2.5 / d) { t -= 2.25 / d; return n * t * t + 0.9375; }
			t -= 2.625 / d; return n * t * t + 0.984375;
		};
		var named = function (css) {
			var m = /^([a-z0-9]+)(?:\.(in|out|inOut))?(?:\(([^)]*)\))?$/i.exec(css);
			if (!m) { return null; }
			var kind = m[1].toLowerCase(), dir = m[2] || 'out', args = (m[3] || '').split(',').map(parseFloat), g = null;
			if (NAMED[kind]) { g = NAMED[kind]; }
			else if (kind === 'back') { g = backIn(args[0]); }
			else if (kind === 'elastic') { g = mkOut(elasticOut(args[0], args[1])); }
			else if (kind === 'bounce') { g = mkOut(bounceOut); }
			if (!g) { return null; }
			return dir === 'in' ? g : (dir === 'inOut' ? mkInOut(g) : mkOut(g));
		};
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
			} else {
				f = named(css);
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

	// Shared "in view" watcher (roadmap Phase 11c) — the one way every engine module learns that an element entered or
	// left the screen, instead of each module creating its own IntersectionObserver. One observer per setting
	// (threshold + rootMargin) serves every element and module that asks for it.
	//   var off = window.upwInView(el, function (inView, entry) { … }, { threshold: 0.2, rootMargin: '0px 0px -10% 0px', once: true });
	//   off();  // stop watching
	// cb(inView, entry) runs with the element's current state as soon as it is known, then on every change. once: stop
	// after the first time it is in view. root: watch inside a scrolling element (its own observer, not shared). Without
	// IntersectionObserver (very old browsers) cb(true, null) runs at once, so content is never left hidden.
	if (!window.upwInView) {
		var IO_POOL = {};
		window.upwInView = function (el, cb, opts) {
			opts = opts || {};
			var none = function () {};
			if (!el || typeof cb !== 'function') { return none; }
			if (!('IntersectionObserver' in window)) { cb(true, null); return none; }
			var th = opts.threshold == null ? 0 : opts.threshold, rm = opts.rootMargin || '0px';
			var key = (opts.root ? '' : (Array.isArray(th) ? th.join(',') : String(th)) + '|' + rm), g = key ? IO_POOL[key] : null;
			if (!g) {
				g = { list: new Map(), last: new Map() };
				g.io = new IntersectionObserver(function (entries) {
					entries.forEach(function (en) {
						g.last.set(en.target, en);
						var hs = g.list.get(en.target);
						if (hs) { hs.slice().forEach(function (h) { h(en); }); }
					});
				}, { threshold: th, rootMargin: rm, root: opts.root || null });
				if (key) { IO_POOL[key] = g; }
			}
			var done = false;
			var off = function () {
				if (done) { return; }
				done = true;
				var hs = g.list.get(el);
				if (!hs) { return; }
				var i = hs.indexOf(h);
				if (i >= 0) { hs.splice(i, 1); }
				if (!hs.length) { g.list.delete(el); g.last.delete(el); g.io.unobserve(el); }
			};
			var h = function (en) {
				if (done) { return; }
				try { cb(en.isIntersecting, en); } catch (e) { setTimeout(function () { throw e; }); }
				if (opts.once && en.isIntersecting) { off(); }
			};
			var hs = g.list.get(el);
			if (!hs) {
				g.list.set(el, [h]);
				g.io.observe(el);
			} else {
				// Already watched for another caller: this one gets the state the observer last reported.
				hs.push(h);
				var last = g.last.get(el);
				if (last) { setTimeout(function () { h(last); }, 0); }
			}
			return off;
		};
	}

	if (window.upwAnimRaf) { return; }

	var fns = [], id = 0, ticking = false;
	function drop(fn) { var j = fns.indexOf(fn); if (j >= 0) { fns.splice(j, 1); } }

	function tick(t) {
		id = 0;
		// While subscribers run, add() must not request a frame of its own: the end of this tick requests the next one.
		// (Without this, a subscriber that re-adds itself each frame doubled the ticks every frame.)
		ticking = true;
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
		// Run a snapshot, newest first, and remove by identity: a subscriber may return false, call remove() on itself or
		// on another subscriber, or add one, mid-loop, and every other subscriber still runs exactly once this frame.
		var list = fns.slice();
		for (var i = list.length - 1; i >= 0; i--) {
			var fn = list[i];
			if (fns.indexOf(fn) < 0) { continue; } // removed earlier in this frame
			try { if (fn(t) === false) { drop(fn); } }
			catch (e) { drop(fn); } // a broken effect must not kill the whole loop
		}
		ticking = false;
		if (fns.length && !document.hidden) { id = requestAnimationFrame(tick); }
	}

	function start() {
		if (!id && !ticking && fns.length && !document.hidden) { id = requestAnimationFrame(tick); }
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
