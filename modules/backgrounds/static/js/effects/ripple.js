(function(){"use strict";
var API=window.upwBgApi||(window.upwBgApi={}),BG=window.upwBg||(window.upwBg={});
var cfg=API.cfg,reduce=API.reduce,cssLayer=API.cssLayer,canvasLayer=API.canvasLayer,loop=API.loop,num=API.num,areaCount=API.areaCount,rnd=API.rnd,field=API.field,meteorField=API.meteorField,blobField=API.blobField;
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
})();
