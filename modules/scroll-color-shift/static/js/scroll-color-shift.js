/**
 * Animation Engine — Scroll Color Shift runtime.
 *
 * Watches every .sc-colorshift Section and morphs the page (body) background — and optionally text —
 * to the colour of whichever section is crossing the middle of the viewport. One passive,
 * rAF-throttled scroll handler; the CSS transition does the smoothing.
 */
(function () {
	'use strict';
	if (typeof window === 'undefined' || typeof document === 'undefined') return;

	function init() {
		var sections = Array.prototype.slice.call(document.querySelectorAll('.sc-colorshift[data-cs-bg]'));
		if (!sections.length) return;

		var body = document.body;
		var cs = getComputedStyle(body);
		var origBg = cs.backgroundColor;
		var origColor = cs.color;
		body.classList.add('upw-cs-on');

		var lastBg = null, lastColor = null, ticking = false;

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

		function onScroll() {
			if (ticking) return;
			ticking = true;
			requestAnimationFrame(function () { pick(); ticking = false; });
		}

		window.addEventListener('scroll', onScroll, { passive: true });
		window.addEventListener('resize', onScroll);
		pick();
	}

	document.readyState !== 'loading' ? init() : document.addEventListener('DOMContentLoaded', init);
})();
