(function(){"use strict";
var API=window.upwBgApi||(window.upwBgApi={}),BG=window.upwBg||(window.upwBg={});
var cfg=API.cfg,reduce=API.reduce,cssLayer=API.cssLayer,canvasLayer=API.canvasLayer,loop=API.loop,num=API.num,areaCount=API.areaCount,rnd=API.rnd,field=API.field,meteorField=API.meteorField,blobField=API.blobField;
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
})();
