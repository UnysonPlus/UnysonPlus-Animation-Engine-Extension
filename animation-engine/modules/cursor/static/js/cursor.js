/**
 * Animation Engine — Cursor runtime.
 *
 * Builds a custom cursor (dot and/or trailing ring) from window.upwCursorCfg.
 * The dot tracks the pointer instantly; the ring lerps behind it. Skips touch
 * screens entirely; under reduced motion the ring stops trailing (snaps to the dot).
 * Vanilla JS, no dependencies.
 */
(function () {
	'use strict';

	var cfg = window.upwCursorCfg || {};
	var mql = window.matchMedia || function () { return { matches: false }; };

	// No custom cursor on touch / coarse pointers.
	if (mql('(hover: none), (pointer: coarse)').matches) { return; }

	var reduce = cfg.reducedMotion && mql('(prefers-reduced-motion: reduce)').matches;
	var style = cfg.style || 'dot_ring';
	var lag = reduce ? 1 : (cfg.ringLag != null ? cfg.ringLag : 0.18);

	var root = document.documentElement;
	root.style.setProperty('--upw-cursor-color', cfg.color || '#2f74e6');
	root.style.setProperty('--upw-cursor-size', (cfg.size || 8) + 'px');
	if (cfg.hideDefault) { root.classList.add('upw-cursor-hide-default'); }
	if (cfg.blend) { root.classList.add('upw-cursor-blend'); }

	function make(cls) {
		var d = document.createElement('div');
		d.className = 'upw-cursor ' + cls;
		d.setAttribute('aria-hidden', 'true');
		document.body.appendChild(d);
		return d;
	}

	var dot = (style === 'dot' || style === 'dot_ring') ? make('upw-cursor-dot') : null;
	var ring = (style === 'ring' || style === 'dot_ring') ? make('upw-cursor-ring') : null;

	var mx = window.innerWidth / 2, my = window.innerHeight / 2;
	var rx = mx, ry = my, seen = false, raf = 0;

	function place(el, x, y) {
		el.style.transform = 'translate(' + x.toFixed(1) + 'px,' + y.toFixed(1) + 'px) translate(-50%,-50%)';
	}

	document.addEventListener('pointermove', function (e) {
		mx = e.clientX; my = e.clientY;
		if (!seen) { seen = true; rx = mx; ry = my; root.classList.add('upw-cursor-active'); }
		if (dot) { place(dot, mx, my); }
		if (ring && lag >= 1) { place(ring, mx, my); }
	}, { passive: true });

	if (ring && lag < 1) {
		(function loop() {
			rx += (mx - rx) * lag;
			ry += (my - ry) * lag;
			place(ring, rx, ry);
			raf = requestAnimationFrame(loop);
		})();
	}

	// Grow the ring over interactive elements.
	if (cfg.hoverGrow) {
		var SEL = 'a, button, [role="button"], input, textarea, select, label, .btn, .sc-btn, [data-hover]';
		document.addEventListener('pointerover', function (e) {
			if (e.target.closest && e.target.closest(SEL)) { root.classList.add('upw-cursor-hover'); }
		}, { passive: true });
		document.addEventListener('pointerout', function (e) {
			if (e.target.closest && e.target.closest(SEL)) { root.classList.remove('upw-cursor-hover'); }
		}, { passive: true });
	}

	// Hide the cursor elements when the pointer leaves the window.
	document.addEventListener('pointerleave', function () { root.classList.remove('upw-cursor-active'); });
	document.addEventListener('pointerenter', function () { root.classList.add('upw-cursor-active'); });
})();
