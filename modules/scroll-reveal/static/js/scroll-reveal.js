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
		return cfg.reducedMotion && window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	}

	var items = [];

	function check() {
		var vh = window.innerHeight || document.documentElement.clientHeight;
		for (var i = 0; i < items.length; i++) {
			var el = items[i].el, replay = items[i].replay;
			var r = el.getBoundingClientRect(); // layout box — unaffected by clip-path
			var inView = r.top < vh * 0.88 && r.bottom > vh * 0.06;
			if (inView) {
				el.classList.add('is-in');
				if (!replay) { items.splice(i, 1); i--; }
			} else if (replay) {
				el.classList.remove('is-in');
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
			return { el: el, replay: el.hasAttribute('data-cr-replay') };
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
