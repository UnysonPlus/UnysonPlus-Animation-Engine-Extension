/**
 * Animation Engine — Scroll Reveal "dissolve": Canvas 2D random-block reveal on scroll-into-view.
 *
 * Each .sc-dissolve-reveal element's <img> is drawn to a <canvas> that starts empty; its blocks are
 * then filled in random order over a few frames until the whole image is present — a "dissolve in".
 * Pure Canvas 2D (no WebGL, no library). Falls back to the untouched <img> on reduced-motion or no
 * canvas / no <img>.
 *
 * Reads (stamped by scroll-reveal-render.php):
 *   data-dsv-block   8..80    block size in px (smaller = finer grain)
 *   data-dsv-speed   10..120  ms per reveal batch
 *   data-dsv-replay  "1"      re-run every time the element re-enters the viewport
 */
(function () {
	'use strict';

	var cfg = window.upwScrollRevealCfg || {};
	var mql = window.matchMedia || function () { return { matches: false }; };
	var reduce = cfg.reducedMotion !== false && mql('(prefers-reduced-motion: reduce)').matches;

	function shuffle(a) {
		for (var i = a.length - 1; i > 0; i--) {
			var j = Math.floor(Math.random() * (i + 1));
			var t = a[i]; a[i] = a[j]; a[j] = t;
		}
		return a;
	}

	function build(el) {
		if (el.__dsvReady) { return; }
		var img = el.tagName === 'IMG' ? el : el.querySelector('img');
		if (!img) { return; }
		el.__dsvReady = true;
		if (reduce) { return; } // leave the untouched image

		var block  = Math.max(8, Math.min(80, parseFloat(el.getAttribute('data-dsv-block')) || 24));
		var speed  = Math.max(10, Math.min(120, parseFloat(el.getAttribute('data-dsv-speed')) || 40));
		var replay = el.getAttribute('data-dsv-replay') === '1';

		function start() {
			if (!img.naturalWidth) { img.addEventListener('load', start, { once: true }); return; }

			var canvas = document.createElement('canvas');
			canvas.className = 'sc-dsv-canvas';
			var ctx = canvas.getContext('2d');
			if (!ctx) { return; } // no 2D context — leave the <img>

			var ratio = img.naturalWidth / img.naturalHeight;
			if (getComputedStyle(el).position === 'static') { el.style.position = 'relative'; }
			el.appendChild(canvas);
			el.classList.add('sc-dissolve-active');

			var dpr = 1, cw = 0, ch = 0, cx = 0, cy = 0, cells = [], timer = null;

			function fit() {
				var r = img.getBoundingClientRect();
				dpr = Math.min(window.devicePixelRatio || 1, 2);
				canvas.width = Math.max(1, Math.round(r.width * dpr));
				canvas.height = Math.max(1, Math.round(r.height * dpr));
				canvas.style.width = r.width + 'px';
				canvas.style.height = r.height + 'px';
				canvas.style.left = (img.offsetLeft || 0) + 'px';
				canvas.style.top = (img.offsetTop || 0) + 'px';
				// cover-fit geometry
				var w = canvas.width, h = canvas.height;
				cw = w; ch = h;
				if (w / h > ratio) { ch = Math.round(w / ratio); } else { cw = Math.round(h * ratio); }
				cx = Math.round((w - cw) / 2); cy = Math.round((h - ch) / 2);
			}

			function drawBlock(bx, by, bw, bh) {
				ctx.save();
				ctx.beginPath();
				ctx.rect(bx, by, bw, bh);
				ctx.clip();
				ctx.drawImage(img, cx, cy, cw, ch);
				ctx.restore();
			}

			function buildCells() {
				cells = [];
				var b = Math.max(2, Math.round(block * dpr));
				for (var y = 0; y < canvas.height; y += b) {
					for (var x = 0; x < canvas.width; x += b) {
						cells.push([x, y, Math.min(b, canvas.width - x), Math.min(b, canvas.height - y)]);
					}
				}
				shuffle(cells);
			}

			function drawAll() { ctx.clearRect(0, 0, canvas.width, canvas.height); ctx.drawImage(img, cx, cy, cw, ch); }

			var running = false;
			function animate() {
				if (running) { return; }
				running = true;
				fit();
				buildCells();
				ctx.clearRect(0, 0, canvas.width, canvas.height);
				var perBatch = Math.max(1, Math.ceil(cells.length / 14)); // ~14 batches
				var k = 0;
				function step() {
					if (!el.isConnected) { running = false; return; }
					for (var n = 0; n < perBatch && k < cells.length; n++, k++) {
						var c = cells[k];
						drawBlock(c[0], c[1], c[2], c[3]);
					}
					if (k < cells.length) {
						timer = setTimeout(function () { requestAnimationFrame(step); }, speed);
					} else {
						running = false;
					}
				}
				step();
			}

			window.addEventListener('resize', function () { if (timer) { clearTimeout(timer); } fit(); drawAll(); });

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
		var els = document.querySelectorAll('.sc-dissolve-reveal');
		for (var i = 0; i < els.length; i++) { build(els[i]); }
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }

	window.upwScrollRevealDissolveRescan = init;
})();
