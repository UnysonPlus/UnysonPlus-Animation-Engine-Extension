/**
 * Animation Engine — Preloader runtime.
 * Removes the overlay printed at wp_body_open once the page has loaded (after a minimum display
 * time), unlocking scroll and fading/sliding it out. Animates the counter style toward 100%.
 */
(function () {
	'use strict';
	if (typeof window === 'undefined' || typeof document === 'undefined') return;
	var el = document.querySelector('.upw-preloader');
	if (!el) return;

	var CFG = window.upwPreloaderCfg || {};
	var minMs = (CFG.minDisplay || 0) * 1000;
	var fadeMs = (CFG.fadeOut || 0.5) * 1000;
	var startT = Date.now();
	var done = false;

	var numEl = el.querySelector('.upw-pl-num');
	var counterTimer = null, pct = 0;
	if (numEl) {
		counterTimer = setInterval(function () {
			pct += Math.max(0.6, (90 - pct) * 0.06);
			if (pct > 90) pct = 90;
			numEl.textContent = Math.round(pct);
		}, 60);
	}

	function finish() {
		if (done) return;
		done = true;
		if (counterTimer) { clearInterval(counterTimer); }
		if (numEl) { numEl.textContent = '100'; }
		var wait = Math.max(0, minMs - (Date.now() - startT));
		setTimeout(function () {
			el.classList.add('is-done');
			document.documentElement.classList.remove('upw-pl-lock');
			setTimeout(function () { if (el.parentNode) { el.parentNode.removeChild(el); } }, fadeMs + 140);
		}, wait);
	}

	if (document.readyState === 'complete') { finish(); }
	else { window.addEventListener('load', finish); }
	setTimeout(finish, 8000); // safety net
})();
