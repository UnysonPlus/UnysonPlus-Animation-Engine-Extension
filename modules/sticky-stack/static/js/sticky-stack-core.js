/**
 * UnysonPlus Animation Engine — Sticky Card Stack core.
 *
 * Pins each card of a .upw-sticky-stack section (staggered sticky tops) and transforms them on
 * scroll per the chosen style. Each style's per-card transform ships as its own partial that
 * registers window.upwStackFx[<style>] — only the used style's partial is enqueued. No library.
 */
(function () {
	'use strict';
	if (typeof window === 'undefined' || typeof document === 'undefined') return;
	var cfg = window.upwStickyStackCfg || {};

	function reducedMotion() { return cfg.reducedMotion && window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches; }
	function isMobile() { return cfg.disableMobile && window.matchMedia && window.matchMedia('(max-width: 767px)').matches; }
	function clamp(v, a, b) { return v < a ? a : (v > b ? b : v); }
	function num(el, attr, dflt) { var v = parseFloat(el.getAttribute(attr)); return isNaN(v) ? dflt : v; }

	var AWAY = { peel: 1, push: 1 }; // styles where the top card moves away (reverse z-order)

	function cardsOf(sec) {
		var row = sec.querySelector('.fw-row') || sec.querySelector('.upw-row') || sec;
		var cols = row.querySelectorAll(':scope > [class*="fw-col"]');
		if (cols.length >= 2) return Array.prototype.slice.call(cols);
		return Array.prototype.slice.call(sec.children).filter(function (c) { return c.offsetHeight > 40; });
	}

	function initSection(sec) {
		if (sec._upwSs) { return; }
		var cards = cardsOf(sec);
		if (cards.length < 2) return;
		sec._upwSs = true;

		var style = sec.getAttribute('data-ss-style') || 'stack';
		var offset = num(sec, 'data-ss-offset', 40);
		var gap = num(sec, 'data-ss-gap', 18);
		var intensity = clamp(num(sec, 'data-ss-intensity', 0.5), 0, 1);
		var n = cards.length, center = (n - 1) / 2, away = !!AWAY[style];
		var fx = window.upwStackFx && window.upwStackFx[style];

		cards.forEach(function (card, i) {
			card.classList.add('upw-ss-card');
			card.style.position = 'sticky';
			card.style.top = (offset + i * gap) + 'px';
			card.style.zIndex = String(away ? (n - i) : (i + 1));
			card.style.willChange = 'transform, opacity, filter';
		});

		function cover(i) {
			if (i < 0) return 1;      // "already arrived" for card 0
			if (i >= n - 1) return 0; // last card is never covered
			var cr = cards[i].getBoundingClientRect(), nr = cards[i + 1].getBoundingClientRect();
			var visible = nr.top - cr.top;
			return 1 - clamp(cr.height ? visible / cr.height : 0, 0, 1);
		}

		var ctx = { intensity: intensity, center: center };
		var pending = false;
		function tick() {
			pending = false;
			if (!fx) { return; } // per-style partial not loaded — cards still stick, just no transform
			// Self-clean on a builder re-render: the section left the DOM.
			if (!document.documentElement.contains(sec)) {
				window.removeEventListener('scroll', onScroll);
				window.removeEventListener('resize', onScroll);
				return;
			}
			var sr = sec.getBoundingClientRect();
			if (sr.bottom < -200 || sr.top > (window.innerHeight || 0) + 200) { return; } // off-screen: skip
			// READ phase — gather every rect first (cover() reads two card rects), THEN write, so we
			// don't force a reflow between each card's read and write.
			var covers = [];
			for (var i = 0; i < n; i++) { covers[i] = cover(i); }
			// WRITE phase.
			var prev = 1;
			for (var j = 0; j < n; j++) {
				var s = fx(j, covers[j], prev, ctx) || {};
				var card = cards[j];
				card.style.transform = s.transform || 'none';
				card.style.transformOrigin = s.origin || 'center top';
				card.style.opacity = s.opacity == null ? '' : String(s.opacity.toFixed(3));
				card.style.filter = s.filter || '';
				prev = covers[j];
			}
		}
		function onScroll() { if (pending) return; pending = true; requestAnimationFrame(tick); }
		window.addEventListener('scroll', onScroll, { passive: true });
		window.addEventListener('resize', onScroll, { passive: true });
		tick();
	}

	function init() {
		if (reducedMotion() || isMobile()) return;
		var els = document.querySelectorAll('.upw-sticky-stack');
		Array.prototype.forEach.call(els, initSection);
	}

	// Defer so the per-style partial (which loads after this core) registers first.
	if (document.readyState !== 'loading') { setTimeout(init, 0); } else { document.addEventListener('DOMContentLoaded', init); }
	window.upwSsRescan = init;
})();
