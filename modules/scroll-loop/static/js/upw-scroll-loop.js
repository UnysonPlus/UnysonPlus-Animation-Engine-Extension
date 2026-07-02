/**
 * UnysonPlus — Seamless / Infinite Scroll Loop initializer (Lenis).
 *
 * Reads the clean `data-upw-loop*` attributes stamped by scroll-loop.php and turns
 * the marked run of full-height sections into a TERMINAL infinite loop:
 *
 *   - Everything ABOVE the first marked section (header, intro sections) scrolls
 *     normally and is reachable by scrolling up.
 *   - The marked group sits at the bottom and loops forever DOWNWARD. A clone of
 *     the first section is appended after the last, so the wrap is pixel-seamless.
 *   - Optional mandatory section snapping (Lenis Snap), one section per gesture.
 *
 * Coexists with the Scroll Motion module: when GSAP + ScrollTrigger are present
 * (e.g. the media inside uses the Parallax effect), Lenis drives them (one global
 * bridge, guarded by window.__upwLenis) so parallax reads the smoothed scroll.
 * When GSAP is absent, a plain rAF loop drives Lenis.
 *
 * Loaded only on pages with a loop group (gated server-side by sc_scroll_loop_flag()).
 * Bails safely (native scroll, no clone, nothing hidden) in the builder, for
 * "reduce motion" visitors, on mobile opt-out, or if Lenis is missing.
 */
(function () {
	'use strict';

	if (typeof window === 'undefined' || typeof document === 'undefined') return;

	var cfg = window.upwLoopCfg || {};
	var respectReduced = cfg.respectReducedMotion !== false; // default: respect

	function inBuilder() {
		return document.body && (
			document.body.classList.contains('fw-builder-active') ||
			document.body.classList.contains('fw-backend-builder') ||
			window.self !== window.top
		);
	}

	function ready(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	}

	function attr(el, name) { return el.getAttribute(name); }
	function num(v, d) { v = parseFloat(v); return isNaN(v) ? d : v; }

	// getBoundingClientRect top in DOCUMENT space (independent of current scroll).
	function docTop(el) {
		return el.getBoundingClientRect().top + (window.scrollY || window.pageYOffset || 0);
	}

	var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	ready(function () {
		// --- Bail conditions ------------------------------------------------
		if (inBuilder()) return;
		if (respectReduced && reducedMotion) return;
		if (window.__upwLenis) return;         // already running (single instance)
		if (typeof window.Lenis !== 'function') return;

		var sections = Array.prototype.slice.call(document.querySelectorAll('[data-upw-loop="1"]'));
		if (sections.length < 2) return;       // a single section can't loop

		var first = sections[0];
		var last  = sections[sections.length - 1];

		// Group-level settings live on the first marked section.
		var snapOn   = attr(first, 'data-upw-loop-snap') !== '0';
		var snapDur  = num(attr(first, 'data-upw-loop-snap-dur'), 0.8);
		var mobileOk = attr(first, 'data-upw-loop-mobile') !== '0';

		if (!mobileOk && window.innerWidth < 768) return;

		// --- Clone the first section to make the seam invisible --------------
		var clone = first.cloneNode(true);
		clone.setAttribute('aria-hidden', 'true');
		clone.setAttribute('data-upw-loop-clone', '1');
		clone.removeAttribute('id');
		// Never let the clone be re-collected as a loop member.
		clone.removeAttribute('data-upw-loop');
		clone.removeAttribute('data-upw-loop-snap');
		clone.removeAttribute('data-upw-loop-snap-dur');
		clone.removeAttribute('data-upw-loop-mobile');
		// Strip ids inside the clone to avoid duplicate DOM ids.
		clone.querySelectorAll('[id]').forEach(function (n) { n.removeAttribute('id'); });
		last.parentNode.insertBefore(clone, last.nextSibling);

		// --- Loop geometry (recomputed on resize) ---------------------------
		var regionTop = 0, cloneTop = 0, cycle = 0;
		function measure() {
			regionTop = docTop(first);
			cloneTop  = docTop(clone);
			cycle     = cloneTop - regionTop; // combined height of the real sections
		}
		measure();
		if (cycle <= 0) return;                // degenerate layout — leave native scroll

		// --- Lenis ----------------------------------------------------------
		var lenis = new window.Lenis({ lerp: 0.1, smoothWheel: true, wheelMultiplier: 1 });
		window.__upwLenis = lenis;

		// One global bridge: GSAP drives Lenis + ScrollTrigger when present, else rAF.
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

		// --- Seamless downward wrap -----------------------------------------
		// When the smoothed scroll reaches the clone (one full cycle past the
		// region top), jump back by exactly one cycle. The clone == first section,
		// so the reposition is pixel-identical and invisible. Bound to EVERY scroll
		// frame (not just on snap-complete): this both drives the loop and caps
		// travel at the clone, so any content below the group (e.g. a theme footer)
		// is never reached — the terminal-loop contract.
		var wrapping = false;
		function maybeWrap() {
			if (wrapping) return;
			var s = lenis.scroll;
			if (s >= regionTop + cycle - 1) {
				wrapping = true;
				lenis.scrollTo(s - cycle, { immediate: true, force: true });
				// release on the next frame so the synthetic scroll doesn't re-enter
				requestAnimationFrame(function () { wrapping = false; });
			}
		}
		lenis.on('scroll', maybeWrap);

		// --- Optional mandatory section snapping ----------------------------
		// Snap adds the eased "one section per gesture" feel. The wrap above is what
		// keeps the loop seamless; snap only needs the real sections as targets (the
		// clone is a visual bridge, not a rest point — resting on it is pre-empted by
		// the wrap, so we don't register it as a snap element).
		if (snapOn && typeof window.Snap === 'function') {
			var snap = new window.Snap(lenis, {
				type: 'mandatory',
				duration: snapDur,
				velocityThreshold: 0.5
			});
			sections.forEach(function (sec) { snap.addElement(sec, { align: ['start'] }); });
			window.__upwLoopSnap = snap;
		}

		// --- Keep geometry correct as things settle / resize ----------------
		function refresh() {
			measure();
			if (window.ScrollTrigger) window.ScrollTrigger.refresh();
		}
		window.addEventListener('load', refresh);
		var rt;
		window.addEventListener('resize', function () {
			clearTimeout(rt);
			rt = setTimeout(refresh, 200);
		});
	});
})();
