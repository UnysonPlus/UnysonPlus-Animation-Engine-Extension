/**
 * Animation Engine — Backgrounds shared "field" engine (on-demand chunk).
 * Loads only when a style that uses it is on the page; registers onto window.upwBgApi.
 */
(function () {
	'use strict';
	var API = window.upwBgApi;
	if (!API) { return; }
	var canvasLayer = API.canvasLayer, num = API.num, areaCount = API.areaCount, rnd = API.rnd, reduce = API.reduce, loop = API.loop;
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
	API.field = field; API.meteorField = meteorField;
})();
