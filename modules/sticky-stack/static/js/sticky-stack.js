/**
 * UnysonPlus Animation Engine — Sticky Card Stack (11 styles).
 *
 * Each direct card of a .upw-sticky-stack section is pinned with position:sticky (staggered tops).
 * A passive, rAF-throttled scroll listener transforms the cards per the chosen style, driven by one
 * "intensity" knob. No library.
 *
 * Cover progress cp[i] = how much card i+1 has risen to cover card i (0 → 1). "Cover" styles
 * (stack / fade / blur / tilt / …) transform the covered card by cp; "arrival" styles (grow) use
 * the previous pair's cp; "away" styles (peel / push) move the top card off (z-order reversed).
 */
(function () {
	'use strict';
	if (typeof window === 'undefined' || typeof document === 'undefined') return;
	var cfg = window.upwStickyStackCfg || {};

	function reducedMotion() {
		return cfg.reducedMotion && window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	}
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

	// Returns { transform, opacity, filter, origin } for card i given its cover progress cp and the
	// previous pair's progress prevCp.
	function styleFor(style, i, cp, prevCp, ctx) {
		var I = ctx.intensity, center = ctx.center, parts = [], opacity = null, filter = '', origin = 'center top';
		switch (style) {
			case 'fade':
				opacity = 1 - Math.min(0.85, I) * cp; break;
			case 'blur':
				filter = 'blur(' + (I * 10 * cp).toFixed(2) + 'px)';
				opacity = 1 - 0.15 * cp; break;
			case 'tilt':
				parts.push('perspective(1200px)', 'rotateX(' + (-I * 55 * cp).toFixed(2) + 'deg)', 'scale(' + (1 - 0.05 * cp).toFixed(4) + ')'); break;
			case 'scale_fade':
				parts.push('scale(' + (1 - I * 0.1 * cp).toFixed(4) + ')');
				opacity = 1 - Math.min(0.7, I * 0.8) * cp; break;
			case 'fan':
				origin = 'center bottom';
				parts.push('rotate(' + ((i - center) * I * 10).toFixed(2) + 'deg)', 'scale(' + (1 - I * 0.06 * cp).toFixed(4) + ')'); break;
			case 'messy':
				parts.push('rotate(' + (((i % 2) ? 1 : -1) * I * 7 * (1 + (i % 3) * 0.35)).toFixed(2) + 'deg)', 'scale(' + (1 - I * 0.05 * cp).toFixed(4) + ')'); break;
			case 'side':
				parts.push('translateX(' + (i * I * 42).toFixed(1) + 'px)', 'scale(' + (1 - I * 0.05 * cp).toFixed(4) + ')'); break;
			case 'peel':
				parts.push('translateY(' + (-cp * 106).toFixed(2) + '%)');
				opacity = 1 - cp * 0.7; break;
			case 'push':
				parts.push('translateY(' + (-cp * 102).toFixed(2) + '%)'); break;
			case 'grow':
				var base = 1 - I * 0.5;
				parts.push('scale(' + (base + (1 - base) * prevCp).toFixed(4) + ')'); break;
			case 'stack':
			default:
				parts.push('scale(' + (1 - I * 0.12 * cp).toFixed(4) + ')'); break;
		}
		return { transform: parts.length ? parts.join(' ') : 'none', opacity: opacity, filter: filter, origin: origin };
	}

	function initSection(sec) {
		var cards = cardsOf(sec);
		if (cards.length < 2) return;

		var style = sec.getAttribute('data-ss-style') || 'stack';
		var offset = num(sec, 'data-ss-offset', 40);
		var gap = num(sec, 'data-ss-gap', 18);
		var intensity = clamp(num(sec, 'data-ss-intensity', 0.5), 0, 1);
		var n = cards.length, center = (n - 1) / 2, away = !!AWAY[style];

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
			var prev = 1;
			for (var i = 0; i < n; i++) {
				var cp = cover(i);
				var s = styleFor(style, i, cp, prev, ctx);
				var card = cards[i];
				card.style.transform = s.transform;
				card.style.transformOrigin = s.origin;
				card.style.opacity = s.opacity == null ? '' : String(s.opacity.toFixed(3));
				card.style.filter = s.filter || '';
				prev = cp;
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

	document.readyState !== 'loading' ? init() : document.addEventListener('DOMContentLoaded', init);
})();
