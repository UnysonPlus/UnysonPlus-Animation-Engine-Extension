(function(){"use strict";
var API=window.upwBgApi||(window.upwBgApi={}),BG=window.upwBg||(window.upwBg={});
var cfg=API.cfg,reduce=API.reduce,cssLayer=API.cssLayer,canvasLayer=API.canvasLayer,loop=API.loop,num=API.num,areaCount=API.areaCount,rnd=API.rnd,field=API.field,meteorField=API.meteorField,blobField=API.blobField;
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
})();
