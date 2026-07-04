(function(){"use strict";
var API=window.upwBgApi||(window.upwBgApi={}),BG=window.upwBg||(window.upwBg={});
var cfg=API.cfg,reduce=API.reduce,cssLayer=API.cssLayer,canvasLayer=API.canvasLayer,loop=API.loop,num=API.num,areaCount=API.areaCount,rnd=API.rnd,field=API.field,meteorField=API.meteorField,blobField=API.blobField;
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
})();
