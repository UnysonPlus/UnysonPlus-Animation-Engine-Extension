/**
 * Animation Engine — Backgrounds shared "blob" engine (on-demand chunk).
 * Loads only when a style that uses it is on the page; registers onto window.upwBgApi.
 */
(function () {
	'use strict';
	var API = window.upwBgApi;
	if (!API) { return; }
	var canvasLayer = API.canvasLayer, num = API.num, rnd = API.rnd, reduce = API.reduce, loop = API.loop;
	function blobField(host, cols, count, rmin, rmax) {
		var L = canvasLayer(host), speed = num(host, 'data-bg-speed', 6), bl = [];
		L.seed = function () { bl = []; for (var i = 0; i < count; i++) { bl.push({ x: rnd(0, L.w), y: rnd(0, L.h), r: rnd(rmin, rmax), vx: rnd(-0.3, 0.3) * speed * 0.25, vy: rnd(-0.3, 0.3) * speed * 0.25, c: cols[i % cols.length] }); } };
		L.seed();
		function draw(anim) {
			var ctx = L.ctx; ctx.clearRect(0, 0, L.w, L.h); ctx.globalCompositeOperation = 'lighter';
			for (var i = 0; i < bl.length; i++) { var b = bl[i]; if (anim) { b.x += b.vx; b.y += b.vy; if (b.x < 0 || b.x > L.w) { b.vx *= -1; } if (b.y < 0 || b.y > L.h) { b.vy *= -1; } } var g = ctx.createRadialGradient(b.x, b.y, 0, b.x, b.y, b.r); g.addColorStop(0, b.c); g.addColorStop(1, 'rgba(0,0,0,0)'); ctx.fillStyle = g; ctx.beginPath(); ctx.arc(b.x, b.y, b.r, 0, 6.2832); ctx.fill(); }
			ctx.globalCompositeOperation = 'source-over';
		}
		if (reduce) { draw(false); return; }
		loop(host, function () { draw(true); });
	}
	API.blobField = blobField;
})();
