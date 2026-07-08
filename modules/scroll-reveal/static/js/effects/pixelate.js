/**
 * Animation Engine — Scroll Reveal "pixelate": Canvas 2D pixel-resolve on scroll-into-view.
 *
 * Each .sc-pixel-reveal element's <img> is drawn to a <canvas> at a fraction of its size with
 * image-smoothing OFF (chunky blocks), then the resolution steps up over a few frames until it's
 * sharp — the Codrops "image pixel loading" reveal. The image starts pixelated and RESOLVES when
 * it scrolls into view. Pure Canvas 2D (no WebGL, no library); cross-origin images are fine here
 * (we never read pixels back). Falls back to the untouched <img> on reduced-motion or no canvas.
 *
 * Reads (stamped by scroll-reveal-render.php):
 *   data-px-coarse  20..200  starting block size in px (larger = chunkier)
 *   data-px-steps   3..8     resolution steps from blocks to sharp
 *   data-px-speed   40..300  ms per step
 *   data-px-replay  "1"      re-run every time the element re-enters the viewport
 */
(function () {
	'use strict';

	var cfg = window.upwScrollRevealCfg || {};
	var mql = window.matchMedia || function () { return { matches: false }; };
	var reduce = cfg.reducedMotion !== false && mql('(prefers-reduced-motion: reduce)').matches;

	function build(el) {
		if (el.__pxReady) { return; }
		var img = el.tagName === 'IMG' ? el : el.querySelector('img');
		if (!img) { return; }
		el.__pxReady = true;

		if (reduce) { return; } // leave the untouched image

		var coarse = parseFloat(el.getAttribute('data-px-coarse')) || 100;
		var steps  = Math.max(2, parseInt(el.getAttribute('data-px-steps'), 10) || 5);
		var speed  = parseFloat(el.getAttribute('data-px-speed')) || 80;
		var replay = el.getAttribute('data-px-replay') === '1';

		function start() {
			if (!img.naturalWidth) { img.addEventListener('load', start, { once: true }); return; }

			var canvas = document.createElement('canvas');
			canvas.className = 'sc-px-canvas';
			var ctx = canvas.getContext('2d');
			if (!ctx) { return; } // no 2D context — leave the <img>

			var ratio = img.naturalWidth / img.naturalHeight;
			if (getComputedStyle(el).position === 'static') { el.style.position = 'relative'; }
			el.appendChild(canvas);
			el.classList.add('sc-pixel-active');

			function fit() {
				var r = img.getBoundingClientRect();
				var dpr = Math.min(window.devicePixelRatio || 1, 2);
				canvas.width = Math.max(1, Math.round(r.width * dpr));
				canvas.height = Math.max(1, Math.round(r.height * dpr));
				canvas.style.width = r.width + 'px';
				canvas.style.height = r.height + 'px';
				canvas.style.left = (img.offsetLeft || 0) + 'px';
				canvas.style.top = (img.offsetTop || 0) + 'px';
			}

			// Draw the image at a size fraction (0..1). <1 downscales then upscales with smoothing
			// off = visible blocks; 1 = sharp. Cover-fit so the image fills the canvas.
			function draw(size) {
				var w = canvas.width, h = canvas.height;
				var cw = w, ch = h;
				if (w / h > ratio) { ch = Math.round(w / ratio); } else { cw = Math.round(h * ratio); }
				var cx = Math.round((w - cw) / 2), cy = Math.round((h - ch) / 2);
				var smooth = size >= 1;
				ctx.imageSmoothingEnabled = smooth;
				ctx.mozImageSmoothingEnabled = smooth;
				ctx.webkitImageSmoothingEnabled = smooth;
				ctx.clearRect(0, 0, w, h);
				var sw = Math.max(1, Math.round(cw * size));
				var sh = Math.max(1, Math.round(ch * size));
				ctx.drawImage(img, 0, 0, sw, sh);                     // tiny copy in the corner
				ctx.drawImage(canvas, 0, 0, sw, sh, cx, cy, cw, ch);  // blow it up to full size
			}

			// Geometric ramp of size fractions from blocky → sharp (ends exactly at 1).
			var startSize = Math.min(0.5, Math.max(0.01, 1 / coarse));
			var sizes = [];
			for (var i = 0; i < steps; i++) {
				sizes.push(startSize * Math.pow(1 / startSize, i / (steps - 1)));
			}
			sizes[sizes.length - 1] = 1;

			// Initial state: blockiest frame (image reads as pixel blocks until it resolves).
			fit();
			draw(sizes[0]);

			var running = false;
			function animate() {
				if (running) { return; }
				running = true;
				fit();
				var k = 0;
				function step() {
					// Stop if the element was removed mid-resolve (builder re-render) — don't draw to a
					// detached canvas or keep a timer alive.
					if (k >= sizes.length || !el.isConnected) { running = false; return; }
					draw(sizes[k]);
					var wait = (k === 0) ? speed * 3 : speed; // dwell on the first (blockiest) frame
					k++;
					setTimeout(function () { requestAnimationFrame(step); }, wait);
				}
				step();
			}

			window.addEventListener('resize', function () { fit(); draw(1); });

			if ('IntersectionObserver' in window) {
				var io = new IntersectionObserver(function (ents) {
					ents.forEach(function (en) {
						if (en.isIntersecting) { animate(); if (!replay) { io.unobserve(el); } }
					});
				}, { threshold: 0.15 });
				io.observe(el);
			} else {
				animate();
			}
		}

		start();
	}

	function init() {
		var els = document.querySelectorAll('.sc-pixel-reveal');
		for (var i = 0; i < els.length; i++) { build(els[i]); }
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }

	// Re-scan after the builder re-renders or content is injected dynamically.
	window.upwScrollRevealPixelRescan = init;
})();
