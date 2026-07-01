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

	/* Shared particle field — snow/petals/embers/ash, confetti, bubbles, fireflies, bokeh,
	   rain, floating shapes. (Meteors handled by meteorField.) */
	function field(host, kind) {
		if (kind === 'meteors') { return meteorField(host); }
		var L = canvasLayer(host);
		var color = host.getAttribute('data-bg-color') || '#ffffff';
		var density = num(host, 'data-bg-density', 60), speed = num(host, 'data-bg-speed', 3);
		var variant = host.getAttribute('data-bg-variant') || 'snow';
		var PAL = ['#ff6b6b', '#f5a524', '#17c964', '#2f74e6', '#c56cff', '#00c2b2'];
		function R(a, b) { return a + (b - a) * Math.random(); }
		var ps = [];
		function spawn() {
			var p = { x: R(0, L.w), y: R(0, L.h), r: R(1, 3), vx: 0, vy: 0, rot: R(0, 6.28), vr: R(-0.05, 0.05), ph: R(0, 6.28), col: color };
			if (kind === 'snow') {
				if (variant === 'embers') { p.vy = -(0.3 + R(0, 0.6)) * speed * 0.4; p.r = R(1, 2.4); p.col = '#ff8a3c'; }
				else if (variant === 'petals') { p.vy = (0.25 + R(0, 0.5)) * speed * 0.4; p.r = R(3, 5); p.col = '#ff9ec7'; }
				else if (variant === 'ash') { p.vy = (0.2 + R(0, 0.4)) * speed * 0.4; p.r = R(0.8, 1.8); p.col = '#9aa4b2'; }
				else { p.vy = (0.3 + R(0, 0.6)) * speed * 0.4; p.col = '#ffffff'; }
				p.vx = R(-0.3, 0.3);
			} else if (kind === 'confetti') { p.vy = (0.5 + R(0, 1)) * speed * 0.5; p.vx = R(-1, 1); p.vr = R(-0.2, 0.2); p.w = R(4, 8); p.h = R(3, 5); p.col = PAL[(Math.random() * PAL.length) | 0]; }
			else if (kind === 'bubbles') { p.vy = -(0.3 + R(0, 0.6)) * speed * 0.4; p.vx = R(-0.2, 0.2); p.r = R(3, 10); }
			else if (kind === 'fireflies') { p.vx = R(-0.4, 0.4) * speed * 0.3; p.vy = R(-0.4, 0.4) * speed * 0.3; p.r = R(1, 2.2); }
			else if (kind === 'bokeh') { p.vx = R(-0.15, 0.15); p.vy = R(-0.15, 0.15); p.r = R(14, 42); }
			else if (kind === 'rain') { p.vy = (4 + R(0, 4)) * speed * 0.5; p.len = R(8, 18); p.x = R(-20, L.w); }
			else if (kind === 'shapes') { p.vx = R(-0.25, 0.25); p.vy = R(-0.25, 0.25); p.r = R(6, 16); p.sh = (Math.random() * 3) | 0; }
			return p;
		}
		L.seed = function () {
			ps = []; var n = areaCount(density, L.w, L.h);
			if (kind === 'bokeh') { n = Math.min(n, 26); }
			if (kind === 'shapes') { n = Math.min(n, 44); }
			for (var i = 0; i < n; i++) { ps.push(spawn()); }
		};
		L.seed();
		function move(p) {
			p.x += p.vx; p.y += p.vy; p.rot += p.vr; p.ph += 0.05;
			if (kind === 'snow' || kind === 'confetti') { p.x += Math.sin(p.ph) * 0.4; if (p.y > L.h + 8) { p.y = -8; p.x = R(0, L.w); } if (p.x < -10) { p.x = L.w + 10; } if (p.x > L.w + 10) { p.x = -10; } }
			else if (kind === 'bubbles') { p.x += Math.sin(p.ph) * 0.3; if (p.y < -12) { p.y = L.h + 12; p.x = R(0, L.w); } }
			else if (kind === 'fireflies' || kind === 'bokeh' || kind === 'shapes') { if (p.x < -20) { p.x = L.w + 20; } if (p.x > L.w + 20) { p.x = -20; } if (p.y < -20) { p.y = L.h + 20; } if (p.y > L.h + 20) { p.y = -20; } }
			else if (kind === 'rain') { if (p.y > L.h + 20) { p.y = -20; p.x = R(-20, L.w); } }
		}
		function render(ctx, p) {
			ctx.fillStyle = p.col; ctx.strokeStyle = p.col;
			if (kind === 'confetti') { ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.rot); ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h); ctx.restore(); }
			else if (kind === 'bubbles') { ctx.globalAlpha = 0.5; ctx.lineWidth = 1; ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, 6.2832); ctx.stroke(); ctx.globalAlpha = 0.1; ctx.fill(); ctx.globalAlpha = 1; }
			else if (kind === 'fireflies') { ctx.globalAlpha = 0.4 + 0.6 * Math.abs(Math.sin(p.ph)); ctx.shadowColor = p.col; ctx.shadowBlur = 8; ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, 6.2832); ctx.fill(); ctx.shadowBlur = 0; ctx.globalAlpha = 1; }
			else if (kind === 'bokeh') { ctx.globalAlpha = 0.12; ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, 6.2832); ctx.fill(); ctx.globalAlpha = 1; }
			else if (kind === 'rain') { ctx.globalAlpha = 0.4; ctx.lineWidth = 1.2; ctx.beginPath(); ctx.moveTo(p.x, p.y); ctx.lineTo(p.x - 1, p.y + p.len); ctx.stroke(); ctx.globalAlpha = 1; }
			else if (kind === 'shapes') { ctx.globalAlpha = 0.5; ctx.lineWidth = 1.5; ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.rot); ctx.beginPath(); if (p.sh === 0) { ctx.rect(-p.r / 2, -p.r / 2, p.r, p.r); } else if (p.sh === 1) { ctx.moveTo(0, -p.r / 2); ctx.lineTo(p.r / 2, p.r / 2); ctx.lineTo(-p.r / 2, p.r / 2); ctx.closePath(); } else { ctx.moveTo(-p.r / 2, 0); ctx.lineTo(p.r / 2, 0); ctx.moveTo(0, -p.r / 2); ctx.lineTo(0, p.r / 2); } ctx.stroke(); ctx.restore(); ctx.globalAlpha = 1; }
			else { ctx.globalAlpha = (variant === 'ash') ? 0.5 : 0.85; if (variant === 'embers') { ctx.shadowColor = p.col; ctx.shadowBlur = 6; } ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, 6.2832); ctx.fill(); ctx.shadowBlur = 0; ctx.globalAlpha = 1; }
		}
		function drawAll(anim) { var ctx = L.ctx; ctx.clearRect(0, 0, L.w, L.h); for (var i = 0; i < ps.length; i++) { if (anim) { move(ps[i]); } render(ctx, ps[i]); } }
		if (reduce) { drawAll(false); return; }
		loop(host, function () { drawAll(true); });
	}

	/* Occasional shooting stars with fading tails. */
	function meteorField(host) {
		var L = canvasLayer(host);
		var color = host.getAttribute('data-bg-color') || '#ffffff';
		var density = num(host, 'data-bg-density', 50), speed = num(host, 'data-bg-speed', 4);
		function R(a, b) { return a + (b - a) * Math.random(); }
		var list = [], last = 0, interval = Math.max(280, 3200 / (density / 25));
		function draw(t) {
			var ctx = L.ctx; ctx.clearRect(0, 0, L.w, L.h);
			if (!reduce && t - last > interval) { last = t; list.push({ x: R(0, L.w), y: -10, vx: R(2, 4) * (Math.random() < 0.5 ? -1 : 1), vy: R(4, 7) * speed * 0.4, len: R(30, 70) }); }
			for (var i = list.length - 1; i >= 0; i--) {
				var m = list[i]; m.x += m.vx * speed * 0.3; m.y += m.vy;
				var gx = m.x - m.vx * m.len * 0.12, gy = m.y - m.vy * m.len * 0.12;
				var g = ctx.createLinearGradient(m.x, m.y, gx, gy); g.addColorStop(0, color); g.addColorStop(1, 'rgba(0,0,0,0)');
				ctx.strokeStyle = g; ctx.lineWidth = 2; ctx.lineCap = 'round';
				ctx.beginPath(); ctx.moveTo(m.x, m.y); ctx.lineTo(gx, gy); ctx.stroke();
				if (m.y > L.h + 20) { list.splice(i, 1); }
			}
		}
		if (reduce) { return; }
		loop(host, draw);
	}

	['snow', 'confetti', 'bubbles', 'fireflies', 'bokeh', 'rain', 'shapes', 'meteors'].forEach(function (k) {
		BG[k] = function (host) { field(host, k); };
	});

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
