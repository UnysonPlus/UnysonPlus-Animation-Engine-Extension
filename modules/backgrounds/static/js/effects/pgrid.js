(function(){"use strict";
var API=window.upwBgApi||(window.upwBgApi={}),BG=window.upwBg||(window.upwBg={});
var cfg=API.cfg,reduce=API.reduce,cssLayer=API.cssLayer,canvasLayer=API.canvasLayer,loop=API.loop,num=API.num,areaCount=API.areaCount,rnd=API.rnd,field=API.field,meteorField=API.meteorField,blobField=API.blobField;
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
})();
