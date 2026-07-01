/**
 * Animation Engine — Animated Backgrounds runtime (vanilla, no deps).
 *
 * For each [data-bg] container: inject a layer behind the content and run the chosen effect.
 * CSS effects (aurora/gradient/dots) are just a class; canvas effects (particles/constellation/
 * waves/starfield/noise) draw on a sized canvas. Loops pause when the section is off-screen or
 * the tab is hidden. Under reduced motion a single static frame is drawn (no animation).
 */
(function () {
	'use strict';

	var cfg = window.upwBgCfg || {};
	var mql = window.matchMedia || function () { return { matches: false }; };
	var reduce = cfg.reducedMotion && mql('(prefers-reduced-motion: reduce)').matches;

	function cssLayer(host, cls) {
		var d = document.createElement('div'); d.className = 'upw-bg-layer ' + cls; d.setAttribute('aria-hidden', 'true');
		host.insertBefore(d, host.firstChild); return d;
	}

	function canvasLayer(host) {
		var cv = document.createElement('canvas'); cv.className = 'upw-bg-layer upw-bg-canvas'; cv.setAttribute('aria-hidden', 'true');
		host.insertBefore(cv, host.firstChild);
		var ctx = cv.getContext('2d'), dpr = Math.min(2, window.devicePixelRatio || 1);
		var L = { cv: cv, ctx: ctx, w: 1, h: 1, seed: null };
		function size() {
			var r = host.getBoundingClientRect();
			L.w = Math.max(1, r.width); L.h = Math.max(1, r.height);
			cv.width = L.w * dpr; cv.height = L.h * dpr; cv.style.width = L.w + 'px'; cv.style.height = L.h + 'px';
			ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
			if (L.seed) { L.seed(); }
		}
		size();
		if ('ResizeObserver' in window) { new ResizeObserver(size).observe(host); } else { window.addEventListener('resize', size, { passive: true }); }
		return L;
	}

	// RAF loop that pauses when the host is off-screen or the tab is hidden.
	function loop(host, cb) {
		var visible = true;
		if ('IntersectionObserver' in window) {
			new IntersectionObserver(function (e) { visible = e[0].isIntersecting; }, { threshold: 0, rootMargin: '120px' }).observe(host);
		}
		(function frame(t) {
			if (visible && !document.hidden) { cb(t); }
			requestAnimationFrame(frame);
		})(0);
	}

	function num(host, attr, def) { var v = parseFloat(host.getAttribute(attr)); return isNaN(v) ? def : v; }
	function areaCount(density, w, h) { return Math.max(6, Math.min(400, Math.round(density * (w * h) / (1280 * 720)))); }

	var BG = {};

	BG.aurora = function (host) { cssLayer(host, 'upw-bg-aurora'); };
	BG.gradient = function (host) { cssLayer(host, 'upw-bg-gradient'); };
	BG.dots = function (host) { cssLayer(host, 'upw-bg-dots'); };
	BG.mesh = function (host) { cssLayer(host, 'upw-bg-mesh'); };
	BG.grid = function (host) { cssLayer(host, 'upw-bg-grid'); };
	BG.orbs = function (host) { var l = cssLayer(host, 'upw-bg-orbs'); l.innerHTML = '<i></i><i></i><i></i>'; };
	BG.conic = function (host) { cssLayer(host, 'upw-bg-conic'); };
	BG.scanlines = function (host) { cssLayer(host, 'upw-bg-scanlines'); };
	BG.rays = function (host) { cssLayer(host, 'upw-bg-rays'); };

	BG.particles = function (host) {
		var L = canvasLayer(host), color = host.getAttribute('data-bg-color') || '#6aa6ff';
		var density = num(host, 'data-bg-density', 60), speed = num(host, 'data-bg-speed', 3), parts = [];
		L.seed = function () {
			parts = []; var n = areaCount(density, L.w, L.h);
			for (var i = 0; i < n; i++) { parts.push({ x: Math.random() * L.w, y: Math.random() * L.h, vx: (Math.random() - .5) * speed * .18, vy: (Math.random() - .5) * speed * .18, r: Math.random() * 1.6 + .6 }); }
		};
		L.seed();
		function draw(move) {
			var ctx = L.ctx; ctx.clearRect(0, 0, L.w, L.h); ctx.fillStyle = color; ctx.globalAlpha = .6;
			for (var i = 0; i < parts.length; i++) {
				var p = parts[i];
				if (move) { p.x += p.vx; p.y += p.vy; if (p.x < 0 || p.x > L.w) { p.vx *= -1; } if (p.y < 0 || p.y > L.h) { p.vy *= -1; } }
				ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, 6.2832); ctx.fill();
			}
			ctx.globalAlpha = 1;
		}
		if (reduce) { draw(false); return; }
		loop(host, function () { draw(true); });
	};

	BG.constellation = function (host) {
		var L = canvasLayer(host), color = host.getAttribute('data-bg-color') || '#6aa6ff';
		var density = num(host, 'data-bg-density', 55), link = num(host, 'data-bg-link', 120), parts = [];
		L.seed = function () {
			parts = []; var n = areaCount(density, L.w, L.h);
			for (var i = 0; i < n; i++) { parts.push({ x: Math.random() * L.w, y: Math.random() * L.h, vx: (Math.random() - .5) * .35, vy: (Math.random() - .5) * .35 }); }
		};
		L.seed();
		function draw(move) {
			var ctx = L.ctx, i, j; ctx.clearRect(0, 0, L.w, L.h);
			for (i = 0; i < parts.length; i++) {
				var p = parts[i];
				if (move) { p.x += p.vx; p.y += p.vy; if (p.x < 0 || p.x > L.w) { p.vx *= -1; } if (p.y < 0 || p.y > L.h) { p.vy *= -1; } }
			}
			ctx.strokeStyle = color; ctx.lineWidth = 1;
			for (i = 0; i < parts.length; i++) {
				for (j = i + 1; j < parts.length; j++) {
					var dx = parts[i].x - parts[j].x, dy = parts[i].y - parts[j].y, d = Math.sqrt(dx * dx + dy * dy);
					if (d < link) { ctx.globalAlpha = (1 - d / link) * .5; ctx.beginPath(); ctx.moveTo(parts[i].x, parts[i].y); ctx.lineTo(parts[j].x, parts[j].y); ctx.stroke(); }
				}
			}
			ctx.globalAlpha = .8; ctx.fillStyle = color;
			for (i = 0; i < parts.length; i++) { ctx.beginPath(); ctx.arc(parts[i].x, parts[i].y, 1.5, 0, 6.2832); ctx.fill(); }
			ctx.globalAlpha = 1;
		}
		if (reduce) { draw(false); return; }
		loop(host, function () { draw(true); });
	};

	BG.waves = function (host) {
		var L = canvasLayer(host), color = host.getAttribute('data-bg-color') || '#2f74e6';
		var amp = num(host, 'data-bg-amp', 30), speed = num(host, 'data-bg-speed', 6);
		function draw(t) {
			var ctx = L.ctx; ctx.clearRect(0, 0, L.w, L.h);
			var layers = [{ a: amp, y: .68, o: .18, s: 1 }, { a: amp * .7, y: .78, o: .22, s: 1.4 }, { a: amp * .5, y: .88, o: .3, s: .8 }];
			for (var k = 0; k < layers.length; k++) {
				var ly = layers[k], base = L.h * ly.y, ph = (t / 1000) * (speed / 6) * ly.s;
				ctx.beginPath(); ctx.moveTo(0, L.h);
				for (var x = 0; x <= L.w; x += 8) { ctx.lineTo(x, base + Math.sin(x / 90 + ph + k) * ly.a); }
				ctx.lineTo(L.w, L.h); ctx.closePath();
				ctx.fillStyle = color; ctx.globalAlpha = ly.o; ctx.fill();
			}
			ctx.globalAlpha = 1;
		}
		if (reduce) { draw(0); return; }
		loop(host, draw);
	};

	BG.starfield = function (host) {
		var L = canvasLayer(host), color = host.getAttribute('data-bg-color') || '#ffffff';
		var density = num(host, 'data-bg-density', 120), speed = num(host, 'data-bg-speed', 4), stars = [];
		L.seed = function () {
			stars = []; var n = Math.max(20, Math.min(500, density));
			for (var i = 0; i < n; i++) { stars.push({ x: (Math.random() - .5) * L.w, y: (Math.random() - .5) * L.h, z: Math.random() * L.w }); }
		};
		L.seed();
		function draw(move) {
			var ctx = L.ctx, cx = L.w / 2, cy = L.h / 2; ctx.clearRect(0, 0, L.w, L.h); ctx.fillStyle = color;
			for (var i = 0; i < stars.length; i++) {
				var s = stars[i];
				if (move) { s.z -= speed * .6; if (s.z < 1) { s.x = (Math.random() - .5) * L.w; s.y = (Math.random() - .5) * L.h; s.z = L.w; } }
				var k = 128 / s.z, sx = cx + s.x * k, sy = cy + s.y * k, r = (1 - s.z / L.w) * 1.8;
				if (sx > 0 && sx < L.w && sy > 0 && sy < L.h) { ctx.globalAlpha = Math.min(1, (1 - s.z / L.w) + .2); ctx.beginPath(); ctx.arc(sx, sy, Math.max(.4, r), 0, 6.2832); ctx.fill(); }
			}
			ctx.globalAlpha = 1;
		}
		if (reduce) { draw(false); return; }
		loop(host, function () { draw(true); });
	};

	BG.noise = function (host) {
		var L = canvasLayer(host), opacity = num(host, 'data-bg-opacity', .06), speed = num(host, 'data-bg-speed', 1);
		var tile = document.createElement('canvas'); tile.width = tile.height = 90;
		var tctx = tile.getContext('2d');
		function regen() {
			var img = tctx.createImageData(90, 90), d = img.data;
			for (var i = 0; i < d.length; i += 4) { var v = (Math.random() * 255) | 0; d[i] = d[i + 1] = d[i + 2] = v; d[i + 3] = 255; }
			tctx.putImageData(img, 0, 0);
		}
		regen();
		function draw(pattern) {
			var ctx = L.ctx; ctx.clearRect(0, 0, L.w, L.h); ctx.globalAlpha = opacity;
			var p = ctx.createPattern(tile, 'repeat'); ctx.fillStyle = p; ctx.fillRect(0, 0, L.w, L.h); ctx.globalAlpha = 1;
		}
		if (reduce) { draw(); return; }
		var last = 0, interval = Math.max(40, 140 / speed);
		loop(host, function (t) { if (t - last > interval) { last = t; regen(); } draw(); });
	};

	function init() {
		var nodes = document.querySelectorAll('[data-bg]');
		Array.prototype.forEach.call(nodes, function (host) {
			if (host._upwBg) { return; } host._upwBg = true;
			var fn = BG[host.getAttribute('data-bg')];
			if (!fn) { return; }
			if (getComputedStyle(host).position === 'static') { host.style.position = 'relative'; }
			host.classList.add('upw-bg-host');
			try { fn(host); } catch (e) { /* never break the page */ }
			// Lift the real content above the layer (a positioned z-index:0 canvas would
			// otherwise paint over static block content).
			Array.prototype.forEach.call(host.children, function (ch) {
				if (ch.classList.contains('upw-bg-layer')) { return; }
				if (getComputedStyle(ch).position === 'static') { ch.style.position = 'relative'; }
				ch.style.zIndex = '1';
			});
		});
	}
	if (document.readyState !== 'loading') { init(); } else { document.addEventListener('DOMContentLoaded', init); }
})();
