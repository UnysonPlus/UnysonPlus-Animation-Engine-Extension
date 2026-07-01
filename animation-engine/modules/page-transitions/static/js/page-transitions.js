/**
 * Animation Engine — Page Transitions runtime (vanilla, no deps).
 *
 * Entrance is CSS (auto). Here we: (1) intercept same-origin link clicks to play the cover
 * animation (.is-exiting) then navigate; (2) run the optional first-visit loader. Skips
 * new-tab / download / hash / external / modified clicks. Under reduced motion, does nothing
 * (normal navigation). A safety timeout always navigates in case animationend never fires.
 */
(function () {
	'use strict';

	var cfg = window.upwPtCfg || {};
	var mql = window.matchMedia || function () { return { matches: false }; };
	var reduce = cfg.reducedMotion && mql('(prefers-reduced-motion: reduce)').matches;
	var dur = (cfg.duration || 0.6) * 1000;

	if (cfg.loader) { loader(); }

	var overlay = document.querySelector('.upw-pt');
	if (!overlay || reduce) { return; }

	document.addEventListener('click', function (e) {
		if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
		var a = e.target.closest ? e.target.closest('a[href]') : null;
		if (!a) { return; }
		if (a.target && a.target !== '_self') { return; }
		if (a.hasAttribute('download') || a.getAttribute('data-no-transition') !== null) { return; }
		var href = a.getAttribute('href') || '';
		if (!href || href.charAt(0) === '#' || /^(mailto:|tel:|javascript:)/i.test(href)) { return; }
		var url;
		try { url = new URL(a.href, location.href); } catch (err) { return; }
		if (url.origin !== location.origin) { return; }                       // external
		if (url.pathname === location.pathname && url.search === location.search && url.hash) { return; } // same-page anchor
		if (url.href === location.href) { return; }

		e.preventDefault();
		overlay.classList.add('is-exiting');
		var done = false;
		function go() { if (done) { return; } done = true; location.href = a.href; }
		overlay.addEventListener('animationend', go, { once: true });
		setTimeout(go, dur + 250);
	}, false);

	// Browsers restore the page from bfcache on back/forward with .is-exiting still applied —
	// clear it so the overlay doesn't stay covering.
	window.addEventListener('pageshow', function (e) { if (e.persisted) { overlay.classList.remove('is-exiting'); } });

	function loader() {
		var el = document.querySelector('.upw-pt-loader');
		if (!el) { return; }
		var seen; try { seen = sessionStorage.getItem('upwPtSeen'); } catch (e2) { seen = '1'; }
		if (seen) { el.parentNode && el.parentNode.removeChild(el); return; }
		function done() {
			try { sessionStorage.setItem('upwPtSeen', '1'); } catch (e3) { }
			setTimeout(function () { el.classList.add('is-done'); setTimeout(function () { el.parentNode && el.parentNode.removeChild(el); }, 600); }, 250);
		}
		if (document.readyState === 'complete') { done(); }
		else { window.addEventListener('load', done); setTimeout(done, 8000); } // hard cap
	}
})();
