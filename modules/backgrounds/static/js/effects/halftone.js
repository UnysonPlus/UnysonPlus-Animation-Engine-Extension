(function(){"use strict";
var API=window.upwBgApi||(window.upwBgApi={}),BG=window.upwBg||(window.upwBg={});
var cfg=API.cfg,reduce=API.reduce,cssLayer=API.cssLayer,canvasLayer=API.canvasLayer,loop=API.loop,num=API.num,areaCount=API.areaCount,rnd=API.rnd,field=API.field,meteorField=API.meteorField,blobField=API.blobField;
BG.halftone = function (host) {
		var L = canvasLayer(host), color = host.getAttribute('data-bg-color') || '#6aa6ff', gap = num(host, 'data-bg-gap', 16), speed = num(host, 'data-bg-speed', 6);
		function draw(t) {
			var ctx = L.ctx; ctx.clearRect(0, 0, L.w, L.h); ctx.fillStyle = color; ctx.globalAlpha = 0.5; var ph = t * speed * 0.0006;
			for (var x = gap / 2; x < L.w; x += gap) { for (var y = gap / 2; y < L.h; y += gap) { var d = Math.sqrt((x - L.w / 2) * (x - L.w / 2) + (y - L.h / 2) * (y - L.h / 2)), r = (gap * 0.42) * (0.5 + 0.5 * Math.sin(ph - d / 40)); ctx.beginPath(); ctx.arc(x, y, Math.max(0.3, r), 0, 6.2832); ctx.fill(); } }
			ctx.globalAlpha = 1;
		}
		if (reduce) { draw(0); return; } loop(host, draw);
	};
})();
