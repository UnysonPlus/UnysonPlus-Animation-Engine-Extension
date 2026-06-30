/**
 * Animation Engine — Hover Interactions runtime.
 *
 * Reads [data-hover] elements emitted by hover.php and wires the pointer-driven
 * effect. Vanilla JS, no dependencies. Honours window.upwHoverCfg (the engine's
 * global reduced-motion / disable-on-mobile policy), prefers-reduced-motion, and
 * skips pointer effects on touch screens. image_reveal is pure CSS (no JS here).
 */
(function () {
	'use strict';

	var cfg = window.upwHoverCfg || {};
	var mql = window.matchMedia || function () { return { matches: false }; };
	var reduceMotion = cfg.reducedMotion !== false && mql('(prefers-reduced-motion: reduce)').matches;
	var isTouch = mql('(hover: none), (pointer: coarse)').matches;
	var isMobile = (window.innerWidth || 1024) < 768;

	function setup(el) {
		if (el.__upwHover) { return; }
		el.__upwHover = true;

		var fx = el.getAttribute('data-hover');
		if (!fx || fx === 'image_reveal') { return; } // image_reveal = CSS only

		var pointerFx = (fx === 'magnetic' || fx === 'tilt' || fx === 'spotlight');
		if (pointerFx && (isTouch || (cfg.disableMobile && isMobile))) { return; }
		if (reduceMotion && (fx === 'magnetic' || fx === 'tilt' || fx === 'text_scramble')) { return; }

		if (fx === 'magnetic') { magnetic(el); }
		else if (fx === 'tilt') { tilt(el); }
		else if (fx === 'spotlight') { spotlight(el); }
		else if (fx === 'text_scramble') { scramble(el); }
	}

	/* ---- Magnetic: the element is pulled toward the cursor. ---- */
	function magnetic(el) {
		var s = parseFloat(el.getAttribute('data-hover-strength')) || 0.3;
		el.addEventListener('pointermove', function (e) {
			var r = el.getBoundingClientRect();
			var x = (e.clientX - (r.left + r.width / 2)) * s;
			var y = (e.clientY - (r.top + r.height / 2)) * s;
			el.style.transform = 'translate(' + x.toFixed(1) + 'px,' + y.toFixed(1) + 'px)';
		});
		el.addEventListener('pointerleave', function () { el.style.transform = ''; });
	}

	/* ---- 3D Tilt: rotateX/rotateY from the pointer position over the element. ---- */
	function tilt(el) {
		var max = parseFloat(el.getAttribute('data-hover-max')) || 12;
		var scale = parseFloat(el.getAttribute('data-hover-scale')) || 1;
		var glare = el.getAttribute('data-hover-glare') === '1';
		var gl = null;
		if (glare) {
			if (getComputedStyle(el).position === 'static') { el.style.position = 'relative'; }
			el.style.overflow = 'hidden';
			gl = document.createElement('span');
			gl.className = 'sc-hover-glare';
			el.appendChild(gl);
		}
		el.addEventListener('pointermove', function (e) {
			var r = el.getBoundingClientRect();
			var px = (e.clientX - r.left) / r.width - 0.5;
			var py = (e.clientY - r.top) / r.height - 0.5;
			el.style.transform = 'perspective(800px) rotateY(' + (px * max).toFixed(2) + 'deg) rotateX(' +
				(-py * max).toFixed(2) + 'deg)' + (scale !== 1 ? ' scale(' + scale + ')' : '');
			if (gl) {
				gl.style.opacity = '1';
				gl.style.background = 'radial-gradient(circle at ' + ((px + 0.5) * 100).toFixed(0) + '% ' +
					((py + 0.5) * 100).toFixed(0) + '%, rgba(255,255,255,.35), transparent 55%)';
			}
		});
		el.addEventListener('pointerleave', function () {
			el.style.transform = '';
			if (gl) { gl.style.opacity = '0'; }
		});
	}

	/* ---- Spotlight: JS feeds --mx/--my; CSS paints the cursor-following glow. ---- */
	function spotlight(el) {
		if (getComputedStyle(el).position === 'static') { el.style.position = 'relative'; }
		el.addEventListener('pointermove', function (e) {
			var r = el.getBoundingClientRect();
			el.style.setProperty('--mx', (e.clientX - r.left) + 'px');
			el.style.setProperty('--my', (e.clientY - r.top) + 'px');
		});
	}

	/* ---- Text Scramble: characters resolve from random glyphs on hover. ---- */
	function scramble(el) {
		var dur = (parseFloat(el.getAttribute('data-hover-duration')) || 0.8) * 1000;
		var chars = '!<>-_\\/[]{}=+*^?#abcdef0123456789';
		var orig = el.getAttribute('data-hover-orig');
		if (orig === null) { orig = el.textContent; el.setAttribute('data-hover-orig', orig); }
		var running = false;

		el.addEventListener('pointerenter', function () {
			if (running) { return; }
			running = true;
			var text = orig, len = text.length, start = 0;
			// Per-char reveal thresholds: left-to-right with a little jitter.
			var th = [];
			for (var i = 0; i < len; i++) { th[i] = (i / len) * 0.6 + Math.random() * 0.4; }

			function frame(ts) {
				if (!start) { start = ts; }
				var p = Math.min(1, (ts - start) / dur);
				var out = '';
				for (var i = 0; i < len; i++) {
					var c = text.charAt(i);
					if (c === ' ' || p >= th[i]) { out += c; }
					else { out += chars.charAt(Math.floor(Math.random() * chars.length)); }
				}
				el.textContent = out;
				if (p < 1) { requestAnimationFrame(frame); }
				else { el.textContent = text; running = false; }
			}
			requestAnimationFrame(frame);
		});
	}

	function init() {
		var els = document.querySelectorAll('[data-hover]');
		for (var i = 0; i < els.length; i++) { setup(els[i]); }
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	// Re-scan when the builder re-renders or content is injected dynamically.
	window.upwHoverRescan = init;
})();
