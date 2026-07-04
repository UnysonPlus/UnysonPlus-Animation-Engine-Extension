(function(){"use strict";
var API=window.upwBgApi||(window.upwBgApi={}),BG=window.upwBg||(window.upwBg={});
var cfg=API.cfg,reduce=API.reduce,cssLayer=API.cssLayer,canvasLayer=API.canvasLayer,loop=API.loop,num=API.num,areaCount=API.areaCount,rnd=API.rnd,field=API.field,meteorField=API.meteorField,blobField=API.blobField;
BG.topo = function (host) {
		var L = canvasLayer(host), color = host.getAttribute('data-bg-color') || '#6aa6ff', speed = num(host, 'data-bg-speed', 6);
		function draw(t) {
			var ctx = L.ctx; ctx.clearRect(0, 0, L.w, L.h); ctx.strokeStyle = color; ctx.globalAlpha = 0.35; ctx.lineWidth = 1; var ph = t * speed * 0.0003;
			for (var k = 1; k <= 9; k++) { ctx.beginPath(); for (var x = 0; x <= L.w; x += 6) { var y = L.h / 2 + Math.sin(x / 70 + ph + k) * 22 + Math.sin(x / 33 + ph * 1.5) * 8 + (k - 5) * 15; if (x) { ctx.lineTo(x, y); } else { ctx.moveTo(x, y); } } ctx.stroke(); }
			ctx.globalAlpha = 1;
		}
		if (reduce) { draw(0); return; } loop(host, draw);
	};
})();
