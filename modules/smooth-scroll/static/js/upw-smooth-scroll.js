/*
 * Animation Engine — Smooth (inertia) Scroll (site-wide).
 *
 * Boots one Lenis instance for the whole page, reading window.upwSmoothScrollCfg. It
 * coordinates with the Scroll Loop module through the shared window.__upwLenis
 * singleton: whichever boots first owns the instance; the other reuses it, so the two
 * features never spawn two Lenis instances or two rAF drives.
 *
 * Drive bridge (set up once, flagged by window.__upwLenisBridged): when GSAP is present
 * its ticker drives lenis.raf and lenis.on('scroll', ScrollTrigger.update) keeps
 * ScrollTrigger in lockstep (so Scroll Motion pages stay perfectly synced); otherwise a
 * private rAF loop drives it. This mirrors the proven Scroll Loop bridge exactly.
 *
 * Honors "reduce motion" (leaves native scroll untouched) and stays off on touch devices
 * unless explicitly enabled.
 */
(function () {
	'use strict';

	var cfg = window.upwSmoothScrollCfg || {};

	function prefersReduced() {
		return !!cfg.reducedMotion && window.matchMedia &&
			window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	}
	function isTouch() {
		return window.matchMedia ? window.matchMedia('(pointer: coarse)').matches
			: ('ontouchstart' in window);
	}

	function bridge(lenis) {
		if (window.__upwLenisBridged) { return; }
		window.__upwLenisBridged = true;
		var gsap = window.gsap;
		if (gsap && gsap.ticker) {
			gsap.ticker.add(function (t) { lenis.raf(t * 1000); });
			gsap.ticker.lagSmoothing(0);
			if (window.ScrollTrigger) {
				lenis.on('scroll', window.ScrollTrigger.update);
				window.ScrollTrigger.refresh();
			}
		} else {
			var raf = function (time) { lenis.raf(time); requestAnimationFrame(raf); };
			requestAnimationFrame(raf);
		}
	}

	function boot() {
		if (!window.Lenis) { return; }
		if (prefersReduced()) { return; }                    // reduced motion → native scroll
		if (isTouch() && !cfg.smoothTouch) { return; }        // touch → native scroll unless opted in

		// Another module (e.g. Scroll Loop) may have already created the singleton.
		if (window.__upwLenis) {
			window.__upwSmoothScrollOn = true;
			bridge(window.__upwLenis);
			return;
		}

		var opts = {
			lerp: (typeof cfg.lerp === 'number') ? cfg.lerp : 0.09,
			smoothWheel: true,
			wheelMultiplier: (typeof cfg.wheelMultiplier === 'number') ? cfg.wheelMultiplier : 1
		};
		if (cfg.smoothTouch) { opts.syncTouch = true; }

		var lenis = new window.Lenis(opts);
		window.__upwLenis = lenis;
		window.__upwSmoothScrollOn = true;
		bridge(lenis);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
