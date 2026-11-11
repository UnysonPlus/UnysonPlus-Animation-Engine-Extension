/**
 * UnysonPlus Animation Engine — Scroll Reveal (Clip Wipe).
 *
 * Adds .is-in to each .sc-clip-reveal element when it scrolls into view, which transitions its
 * clip-path from the hidden state (set per direction in CSS) to full.
 *
 * NOTE: a plain IntersectionObserver can't be used here — the element starts clipped to zero area,
 * and Chromium factors the target's own clip-path into the intersection ratio, so a clipped element
 * reports ratio 0 and never fires (chicken-and-egg). getBoundingClientRect ignores clip-path, so we
 * use a passive, rAF-throttled scroll check on the layout box instead.
 */
(function () {
	'use strict';
	if (typeof window === 'undefined' || typeof document === 'undefined') return;
	var cfg = window.upwScrollRevealCfg || {};

	function reducedMotion() {
		return cfg.reducedMotion && (window.upwReduceMotion ? window.upwReduceMotion() : (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches));
	}

	var items = [];

	/*
	 * How far BELOW the fold an element starts revealing, as a fraction of the viewport.
	 *
	 * This used to be 0.88 — the reveal only began once the element was almost entirely on
	 * screen. With a wipe of any real length that is too late to be invisible: the reader
	 * arrives at the section, finds a blank box where the picture goes, and watches it open.
	 * It reads as an image that has not loaded, which is the one thing a reveal must never
	 * look like. Measured on a real page at the shipped 1.3s duration, an element was still
	 * only about a quarter open by the time it was fully in view.
	 *
	 * 1.6 was chosen by measurement rather than taste. Sweeping it on a real page, counting
	 * how open each image was at the moment a reader's eye would land on it: 0.88 left 9 of
	 * 46 samples under a quarter open (i.e. looking broken); 1.25 left 3; 1.6 left none,
	 * while still catching 17 of 46 mid-wipe so the effect is plainly visible. Going further
	 * buys nothing and costs the animation — at 2.5 nearly every element is already fully
	 * open before it is seen, so the reveal may as well not exist. `lead` overrides it.
	 */
	var LEAD = typeof cfg.lead === 'number' ? cfg.lead : 1.6;

	/*
	 * How many viewport-heights of scrolling a reader covers per second. Used only to turn
	 * an element's own wipe duration into runway, so the figure just needs to be the right
	 * order of magnitude for an unhurried scroll, not exact.
	 */
	var VH_PER_SEC = typeof cfg.scrollRate === 'number' ? cfg.scrollRate : 1.1;

	/*
	 * Runway has to scale with the wipe, or a long duration re-creates the blank.
	 *
	 * A fixed lead buys a fixed amount of time, but the time NEEDED is the duration itself:
	 * a 1.3s wipe wants roughly twice the head start of a 0.6s one. Holding the lead
	 * constant meant a page that had raised its durations still showed a half-open image on
	 * arrival - better than before, but still reading as something that had not loaded.
	 * Deriving the lead per element from its own computed duration fixes every duration at
	 * once, including ones nobody has chosen yet.
	 *
	 * The ceiling is the honest part of this: past about 2s of wipe the element would have
	 * to start revealing so far down that nobody ever sees it animate. We cap the runway
	 * there rather than pretend, and the real answer for a wipe that long is a shorter one.
	 */
	function leadFor(el) {
		if (typeof cfg.lead === 'number') return cfg.lead; // explicit override wins
		var d = 0;
		try { d = parseFloat(getComputedStyle(el).transitionDuration) || 0; } catch (e) {}
		if (!(d > 0)) d = 0.7; // the CSS default, if the duration cannot be read
		return 1 + Math.min(d, 2) * VH_PER_SEC;
	}

	/* Decide which elements are close enough to the viewport to start revealing.
	 *
	 * Two passes on purpose: measure everything first, then change classes. Adding .is-in writes to
	 * an element, and the NEXT element's getBoundingClientRect() then has to wait for the browser to
	 * lay the page out again before it can answer - one forced layout per revealed element. Reading
	 * them all up front costs a single layout for the whole set.
	 */
	function check() {
		// The shared scheduler refreshes `vh` once at the top of each frame, while the layout is still
		// clean; reading the window here would be another chance to force a layout.
		var vh = (window.upwAnimRaf && window.upwAnimRaf.vh) || window.innerHeight || document.documentElement.clientHeight;
		var i, inView = [];
		// Pass 1 - reads only. `null` marks an element that has left the document.
		for (i = 0; i < items.length; i++) {
			var el = items[i].el;
			if (!document.documentElement.contains(el)) { inView.push(null); continue; }
			var r = el.getBoundingClientRect(); // layout box - unaffected by clip-path
			inView.push(r.top < vh * items[i].lead && r.bottom > vh * 0.06);
		}
		// Pass 2 - writes only. Backwards so removing an item cannot disturb the indexes still to come.
		for (i = items.length - 1; i >= 0; i--) {
			if (inView[i] === null) { items.splice(i, 1); continue; } // self-clean on builder re-render
			var it = items[i];
			if (inView[i]) {
				it.el.classList.add('is-in');
				if (!it.replay) { items.splice(i, 1); }
			} else if (it.replay) {
				it.el.classList.remove('is-in');
			}
		}
	}

	function init() {
		var els = document.querySelectorAll('.sc-clip-reveal');
		if (!els.length) return;

		if (reducedMotion()) {
			Array.prototype.forEach.call(els, function (el) { el.classList.add('is-in'); });
			return;
		}

		items = Array.prototype.map.call(els, function (el) {
			return { el: el, replay: el.hasAttribute('data-cr-replay'), lead: leadFor(el) };
		});

		var pending = false;
		function onScroll() {
			if (pending) return;
			pending = true;
			requestAnimationFrame(function () { pending = false; check(); });
		}
		window.addEventListener('scroll', onScroll, { passive: true });
		window.addEventListener('resize', onScroll, { passive: true });
		window.addEventListener('load', check);
		check();
	}

	document.readyState !== 'loading' ? init() : document.addEventListener('DOMContentLoaded', init);
})();
