/**
 * Animation Engine — Scroll Color Shift runtime.
 *
 * Watches every .sc-colorshift Section and morphs the page (body) background — and optionally text —
 * to the colour of whichever section is crossing the middle of the viewport; the CSS transition does
 * the smoothing.
 *
 * Rides the shared frame scheduler (window.upwAnimRaf): one ordered tick that pauses while the tab is
 * hidden and stays in lockstep with smooth scroll. It only recomputes when the scroll position
 * actually changed, so it costs nothing while idle. Honours "reduce motion" via the shared helper.
 * Falls back to a passive scroll listener if the scheduler isn't present.
 */
(function () {
	'use strict';
	if (typeof window === 'undefined' || typeof document === 'undefined') return;

	function reduced() {
		var c = window.upwColorShiftCfg || {};
		return c.reducedMotion !== false && typeof window.upwReduceMotion === 'function' && window.upwReduceMotion();
	}

	function init() {
		if (reduced()) return; // reduce motion → leave the page colours static

		var sections = Array.prototype.slice.call(document.querySelectorAll('.sc-colorshift[data-cs-bg]'));
		if (!sections.length) return;

		var body = document.body;
		var cs = getComputedStyle(body);
		var origBg = cs.backgroundColor;
		var origColor = cs.color;
		body.classList.add('upw-cs-on');

		var lastBg = null, lastColor = null;

		function pick() {
			var vh = window.innerHeight || document.documentElement.clientHeight;
			var mid = vh * 0.5;
			var active = null, bestTop = -Infinity;
			for (var i = 0; i < sections.length; i++) {
				var t = sections[i].getBoundingClientRect().top;
				if (t <= mid && t > bestTop) { bestTop = t; active = sections[i]; }
			}
			var bg, col, dur;
			if (active) {
				bg = active.getAttribute('data-cs-bg');
				col = active.getAttribute('data-cs-text') || '';
				dur = active.getAttribute('data-cs-dur') || '0.6';
			} else {
				bg = origBg; col = ''; dur = '0.6';
			}
			if (bg === lastBg && col === lastColor) return;
			lastBg = bg; lastColor = col;
			body.style.setProperty('--cs-dur', dur + 's');
			body.style.backgroundColor = bg;
			body.style.color = col ? col : origColor;
		}

		// Recompute only when the scroll position moved (or on resize) — cheap when idle, frame-synced
		// with smooth scroll when scrolling.
		var lastY = -1;
		function frame() {
			var y = window.pageYOffset || document.documentElement.scrollTop || 0;
			if (y === lastY) return;
			lastY = y;
			pick();
		}
		window.addEventListener('resize', function () { lastY = -1; }, { passive: true });

		if (window.upwAnimRaf && window.upwAnimRaf.add) {
			window.upwAnimRaf.add(frame);
		} else {
			// Fallback: the original passive, rAF-throttled scroll handler.
			var ticking = false;
			var onScroll = function () {
				if (ticking) return;
				ticking = true;
				requestAnimationFrame(function () { pick(); ticking = false; });
			};
			window.addEventListener('scroll', onScroll, { passive: true });
			window.addEventListener('resize', onScroll, { passive: true });
		}
		pick(); // initial paint
	}

	document.readyState !== 'loading' ? init() : document.addEventListener('DOMContentLoaded', init);
})();
