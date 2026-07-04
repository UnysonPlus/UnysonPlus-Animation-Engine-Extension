(function(){"use strict";
var API=window.upwBgApi||(window.upwBgApi={}),BG=window.upwBg||(window.upwBg={});
var cfg=API.cfg,reduce=API.reduce,cssLayer=API.cssLayer,canvasLayer=API.canvasLayer,loop=API.loop,num=API.num,areaCount=API.areaCount,rnd=API.rnd,field=API.field,meteorField=API.meteorField,blobField=API.blobField;
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
})();
