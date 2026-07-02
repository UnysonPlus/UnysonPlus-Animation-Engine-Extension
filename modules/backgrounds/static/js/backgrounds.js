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

	/* ---- Wave 3: structural / fluid ---- */
	function rnd(a, b) { return a + (b - a) * Math.random(); }

	BG.pgrid = function (host) {
		var L = canvasLayer(host), color = host.getAttribute('data-bg-color') || '#ff6ac1', speed = num(host, 'data-bg-speed', 6), off = 0;
		function draw() {
			var ctx = L.ctx, hz = L.h * 0.42, vx = L.w / 2; ctx.clearRect(0, 0, L.w, L.h); ctx.strokeStyle = color; ctx.lineWidth = 1;
			ctx.globalAlpha = 0.35; for (var i = -12; i <= 12; i++) { ctx.beginPath(); ctx.moveTo(vx, hz); ctx.lineTo(vx + i * (L.w / 12), L.h); ctx.stroke(); }
			for (var j = 0; j < 22; j++) { var t = ((j + off) % 22) / 22, y = hz + (L.h - hz) * t * t; ctx.globalAlpha = 0.5 * t + 0.05; ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(L.w, y); ctx.stroke(); }
			ctx.globalAlpha = 1;
		}
		if (reduce) { draw(); return; } loop(host, function () { off = (off + speed * 0.02) % 22; draw(); });
	};

	BG.hexgrid = function (host) {
		var L = canvasLayer(host), color = host.getAttribute('data-bg-color') || '#6aa6ff', speed = num(host, 'data-bg-speed', 6);
		function hx(ctx, cx, cy, r) { ctx.beginPath(); for (var k = 0; k < 6; k++) { var a = Math.PI / 3 * k + Math.PI / 6, x = cx + r * Math.cos(a), y = cy + r * Math.sin(a); if (k) { ctx.lineTo(x, y); } else { ctx.moveTo(x, y); } } ctx.closePath(); }
		function draw(t) {
			var ctx = L.ctx; ctx.clearRect(0, 0, L.w, L.h); ctx.strokeStyle = color; ctx.lineWidth = 1;
			var r = 16, dx = r * 1.5, dy = r * 1.732, cxm = L.w / 2, cym = L.h / 2;
			for (var col = 0; col * dx < L.w + r; col++) { for (var row = 0; row * dy < L.h + r; row++) { var cx = col * dx, cy = row * dy + (col % 2 ? dy / 2 : 0), d = Math.sqrt((cx - cxm) * (cx - cxm) + (cy - cym) * (cy - cym)); ctx.globalAlpha = Math.max(0.05, 0.32 + 0.3 * Math.sin(t * speed * 0.0004 - d / 40)); hx(ctx, cx, cy, r * 0.9); ctx.stroke(); } }
			ctx.globalAlpha = 1;
		}
		if (reduce) { draw(0); return; } loop(host, draw);
	};

	BG.topo = function (host) {
		var L = canvasLayer(host), color = host.getAttribute('data-bg-color') || '#6aa6ff', speed = num(host, 'data-bg-speed', 6);
		function draw(t) {
			var ctx = L.ctx; ctx.clearRect(0, 0, L.w, L.h); ctx.strokeStyle = color; ctx.globalAlpha = 0.35; ctx.lineWidth = 1; var ph = t * speed * 0.0003;
			for (var k = 1; k <= 9; k++) { ctx.beginPath(); for (var x = 0; x <= L.w; x += 6) { var y = L.h / 2 + Math.sin(x / 70 + ph + k) * 22 + Math.sin(x / 33 + ph * 1.5) * 8 + (k - 5) * 15; if (x) { ctx.lineTo(x, y); } else { ctx.moveTo(x, y); } } ctx.stroke(); }
			ctx.globalAlpha = 1;
		}
		if (reduce) { draw(0); return; } loop(host, draw);
	};

	BG.circuit = function (host) {
		var L = canvasLayer(host), color = host.getAttribute('data-bg-color') || '#00e5a0', segs = [], dots = [];
		L.seed = function () {
			segs = []; dots = []; var g = 34;
			for (var x = g; x < L.w; x += g) { for (var y = g; y < L.h; y += g) { if (Math.random() < 0.5) { segs.push([x, y, x + g, y]); } if (Math.random() < 0.5) { segs.push([x, y, x, y + g]); } } }
			for (var i = 0; i < Math.min(30, segs.length); i++) { dots.push({ s: (Math.random() * segs.length) | 0, p: Math.random(), v: rnd(0.01, 0.03) }); }
		};
		L.seed();
		function draw() {
			var ctx = L.ctx, i; ctx.clearRect(0, 0, L.w, L.h); ctx.strokeStyle = color; ctx.fillStyle = color;
			ctx.globalAlpha = 0.16; ctx.lineWidth = 1; for (i = 0; i < segs.length; i++) { ctx.beginPath(); ctx.moveTo(segs[i][0], segs[i][1]); ctx.lineTo(segs[i][2], segs[i][3]); ctx.stroke(); }
			for (i = 0; i < segs.length; i += 3) { ctx.beginPath(); ctx.arc(segs[i][0], segs[i][1], 1.4, 0, 6.2832); ctx.fill(); }
			ctx.globalAlpha = 1; ctx.shadowColor = color;
			for (i = 0; i < dots.length; i++) { var d = dots[i]; if (!reduce) { d.p += d.v; if (d.p > 1) { d.p = 0; d.s = (Math.random() * segs.length) | 0; } } var s = segs[d.s]; if (!s) { continue; } ctx.shadowBlur = 6; ctx.beginPath(); ctx.arc(s[0] + (s[2] - s[0]) * d.p, s[1] + (s[3] - s[1]) * d.p, 2, 0, 6.2832); ctx.fill(); }
			ctx.shadowBlur = 0;
		}
		if (reduce) { draw(); return; } loop(host, draw);
	};

	BG.halftone = function (host) {
		var L = canvasLayer(host), color = host.getAttribute('data-bg-color') || '#6aa6ff', gap = num(host, 'data-bg-gap', 16), speed = num(host, 'data-bg-speed', 6);
		function draw(t) {
			var ctx = L.ctx; ctx.clearRect(0, 0, L.w, L.h); ctx.fillStyle = color; ctx.globalAlpha = 0.5; var ph = t * speed * 0.0006;
			for (var x = gap / 2; x < L.w; x += gap) { for (var y = gap / 2; y < L.h; y += gap) { var d = Math.sqrt((x - L.w / 2) * (x - L.w / 2) + (y - L.h / 2) * (y - L.h / 2)), r = (gap * 0.42) * (0.5 + 0.5 * Math.sin(ph - d / 40)); ctx.beginPath(); ctx.arc(x, y, Math.max(0.3, r), 0, 6.2832); ctx.fill(); } }
			ctx.globalAlpha = 1;
		}
		if (reduce) { draw(0); return; } loop(host, draw);
	};

	BG.blobs = function (host) { blobField(host, [host.getAttribute('data-bg-color') || '#6a8dff', host.getAttribute('data-bg-color2') || '#c56cff'], 5, 40, 90); };
	BG.nebula = function (host) { blobField(host, [host.getAttribute('data-bg-color') || '#3b3fff', host.getAttribute('data-bg-color2') || '#c56cff', host.getAttribute('data-bg-color3') || '#00d4c8'], 4, 70, 140); };
	function blobField(host, cols, count, rmin, rmax) {
		var L = canvasLayer(host), speed = num(host, 'data-bg-speed', 6), bl = [];
		L.seed = function () { bl = []; for (var i = 0; i < count; i++) { bl.push({ x: rnd(0, L.w), y: rnd(0, L.h), r: rnd(rmin, rmax), vx: rnd(-0.3, 0.3) * speed * 0.25, vy: rnd(-0.3, 0.3) * speed * 0.25, c: cols[i % cols.length] }); } };
		L.seed();
		function draw(anim) {
			var ctx = L.ctx; ctx.clearRect(0, 0, L.w, L.h); ctx.globalCompositeOperation = 'lighter';
			for (var i = 0; i < bl.length; i++) { var b = bl[i]; if (anim) { b.x += b.vx; b.y += b.vy; if (b.x < 0 || b.x > L.w) { b.vx *= -1; } if (b.y < 0 || b.y > L.h) { b.vy *= -1; } } var g = ctx.createRadialGradient(b.x, b.y, 0, b.x, b.y, b.r); g.addColorStop(0, b.c); g.addColorStop(1, 'rgba(0,0,0,0)'); ctx.fillStyle = g; ctx.beginPath(); ctx.arc(b.x, b.y, b.r, 0, 6.2832); ctx.fill(); }
			ctx.globalCompositeOperation = 'source-over';
		}
		if (reduce) { draw(false); return; } loop(host, function () { draw(true); });
	}

	BG.ripple = function (host) {
		var L = canvasLayer(host), color = host.getAttribute('data-bg-color') || '#6aa6ff', speed = num(host, 'data-bg-speed', 6), rs = [], last = 0;
		function draw(t) {
			var ctx = L.ctx; ctx.clearRect(0, 0, L.w, L.h); if (!reduce && t - last > 900 / (speed / 4)) { last = t; rs.push({ x: rnd(0, L.w), y: rnd(0, L.h), r: 0 }); }
			ctx.strokeStyle = color; ctx.lineWidth = 1.5; var max = Math.max(L.w, L.h);
			for (var i = rs.length - 1; i >= 0; i--) { var r = rs[i]; r.r += speed * 0.4; ctx.globalAlpha = Math.max(0, 1 - r.r / (max * 1.3)); ctx.beginPath(); ctx.arc(r.x, r.y, r.r, 0, 6.2832); ctx.stroke(); if (r.r > max * 1.3) { rs.splice(i, 1); } }
			ctx.globalAlpha = 1;
		}
		if (reduce) { draw(0); return; } loop(host, draw);
	};

	BG.flow = function (host) {
		var L = canvasLayer(host), color = host.getAttribute('data-bg-color') || '#6aa6ff', density = num(host, 'data-bg-density', 60), speed = num(host, 'data-bg-speed', 6), ps = [];
		L.seed = function () { ps = []; var n = areaCount(density, L.w, L.h); for (var i = 0; i < n; i++) { ps.push({ x: rnd(0, L.w), y: rnd(0, L.h) }); } };
		L.seed();
		function draw(t) {
			var ctx = L.ctx; ctx.clearRect(0, 0, L.w, L.h); ctx.fillStyle = color; ctx.globalAlpha = 0.55; var tt = t * speed * 0.0002;
			for (var i = 0; i < ps.length; i++) { var p = ps[i], a = (Math.sin(p.x / 90 + tt) + Math.cos(p.y / 70 - tt)) * Math.PI; p.x += Math.cos(a) * 0.8; p.y += Math.sin(a) * 0.8; if (p.x < 0 || p.x > L.w || p.y < 0 || p.y > L.h) { p.x = rnd(0, L.w); p.y = rnd(0, L.h); } ctx.fillRect(p.x, p.y, 1.6, 1.6); }
			ctx.globalAlpha = 1;
		}
		if (reduce) { draw(0); return; } loop(host, draw);
	};

	BG.matrix = function (host) {
		var L = canvasLayer(host), color = host.getAttribute('data-bg-color') || '#19ff7a', speed = num(host, 'data-bg-speed', 6), cols = [];
		var GL = 'ｱｲｳｴｵｶｷｸ0123456789ABCDEF';
		L.seed = function () { cols = []; var n = Math.floor(L.w / 12); for (var i = 0; i < n; i++) { cols.push({ y: rnd(-L.h, 0), v: rnd(2, 6) * speed * 0.3 }); } };
		L.seed();
		function draw() {
			var ctx = L.ctx; ctx.clearRect(0, 0, L.w, L.h); ctx.font = '12px monospace';
			for (var i = 0; i < cols.length; i++) { var c = cols[i]; if (!reduce) { c.y += c.v; if (c.y > L.h + 60) { c.y = rnd(-L.h, 0); } } var x = i * 12 + 2; for (var k = 0; k < 8; k++) { var y = c.y - k * 13; if (y < 0 || y > L.h) { continue; } ctx.globalAlpha = Math.max(0, 1 - k / 8); ctx.fillStyle = k === 0 ? '#d6ffe6' : color; ctx.fillText(GL[(Math.random() * GL.length) | 0], x, y); } }
			ctx.globalAlpha = 1;
		}
		if (reduce) { draw(); return; } loop(host, draw);
	};

	BG.borealis = function (host) {
		var L = canvasLayer(host), c1 = host.getAttribute('data-bg-color') || '#3bffb0', c2 = host.getAttribute('data-bg-color2') || '#6a8dff', speed = num(host, 'data-bg-speed', 6);
		function draw(t) {
			var ctx = L.ctx; ctx.clearRect(0, 0, L.w, L.h); var ph = t * speed * 0.0003;
			for (var b = 0; b < 3; b++) {
				var baseY = L.h * (0.28 + b * 0.16); ctx.beginPath(); ctx.moveTo(0, 0);
				for (var x = 0; x <= L.w; x += 8) { ctx.lineTo(x, baseY + Math.sin(x / 80 + ph + b) * 26 + Math.sin(x / 38 + ph * 1.4) * 10); }
				ctx.lineTo(L.w, 0); ctx.closePath();
				var g = ctx.createLinearGradient(0, baseY - 50, 0, baseY + 40); g.addColorStop(0, 'rgba(0,0,0,0)'); g.addColorStop(1, b % 2 ? c2 : c1);
				ctx.globalAlpha = 0.22; ctx.fillStyle = g; ctx.fill();
			}
			ctx.globalAlpha = 1;
		}
		if (reduce) { draw(0); return; } loop(host, draw);
	};

	BG.orbits = function (host) {
		var L = canvasLayer(host), color = host.getAttribute('data-bg-color') || '#6aa6ff', count = num(host, 'data-bg-density', 4), centers = [];
		L.seed = function () {
			centers = []; for (var i = 0; i < Math.min(6, count); i++) { var c = { x: rnd(L.w * 0.2, L.w * 0.8), y: rnd(L.h * 0.2, L.h * 0.8), s: [] }; for (var j = 0; j < ((Math.random() * 3) | 0) + 2; j++) { c.s.push({ r: rnd(14, 42), a: rnd(0, 6.28), v: rnd(0.005, 0.02) * (Math.random() < 0.5 ? -1 : 1) }); } centers.push(c); }
		};
		L.seed();
		function draw() {
			var ctx = L.ctx; ctx.clearRect(0, 0, L.w, L.h); ctx.strokeStyle = color; ctx.fillStyle = color;
			for (var i = 0; i < centers.length; i++) { var c = centers[i]; ctx.globalAlpha = 0.15; for (var j = 0; j < c.s.length; j++) { ctx.beginPath(); ctx.arc(c.x, c.y, c.s[j].r, 0, 6.2832); ctx.stroke(); } ctx.globalAlpha = 0.9; ctx.beginPath(); ctx.arc(c.x, c.y, 2, 0, 6.2832); ctx.fill(); for (j = 0; j < c.s.length; j++) { var s = c.s[j]; if (!reduce) { s.a += s.v; } ctx.beginPath(); ctx.arc(c.x + s.r * Math.cos(s.a), c.y + s.r * Math.sin(s.a), 2.2, 0, 6.2832); ctx.fill(); } }
			ctx.globalAlpha = 1;
		}
		if (reduce) { draw(); return; } loop(host, draw);
	};

	BG.spotlight = function (host) {
		var L = canvasLayer(host), color = host.getAttribute('data-bg-color') || '#6aa6ff', size = num(host, 'data-bg-size', 260);
		var mx = L.w / 2, my = L.h / 2;
		host.addEventListener('pointermove', function (e) { var r = host.getBoundingClientRect(); mx = e.clientX - r.left; my = e.clientY - r.top; }, { passive: true });
		loop(host, function () { var ctx = L.ctx; ctx.clearRect(0, 0, L.w, L.h); var g = ctx.createRadialGradient(mx, my, 0, mx, my, size); g.addColorStop(0, color); g.addColorStop(1, 'rgba(0,0,0,0)'); ctx.globalAlpha = 0.4; ctx.fillStyle = g; ctx.fillRect(0, 0, L.w, L.h); ctx.globalAlpha = 1; });
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
