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

	window.upwAnimRaf = {
		add: function (fn) { if (typeof fn === 'function' && fns.indexOf(fn) < 0) { fns.push(fn); start(); } return fn; },
		remove: function (fn) { var i = fns.indexOf(fn); if (i >= 0) { fns.splice(i, 1); } }
	};
})();
