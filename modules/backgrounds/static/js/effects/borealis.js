(function(){"use strict";
var API=window.upwBgApi||(window.upwBgApi={}),BG=window.upwBg||(window.upwBg={});
var cfg=API.cfg,reduce=API.reduce,cssLayer=API.cssLayer,canvasLayer=API.canvasLayer,loop=API.loop,num=API.num,areaCount=API.areaCount,rnd=API.rnd,field=API.field,meteorField=API.meteorField,blobField=API.blobField;
BG.borealis = function (host) {
		var L = canvasLayer(host), c1 = host.getAttribute('data-bg-color') || '#3bffb0', c2 = host.getAttribute('data-bg-color2') || '#6a8dff', speed = num(host, 'data-bg-speed', 6);
		function draw(t) {
			var ctx = L.ctx; ctx.clearRect(0, 0, L.w, L.h); var ph = t * speed * 0.0003;
			for (var b = 0; b < 3; b++) {
				var baseY = L.h * (0.28 + b * 0.16); ctx.beginPath(); ctx.moveTo(0, 0);
				for (var x = 0; x <= L.w; x += 8) { ctx.lineTo(x, baseY + Math.sin(x / 80 + ph + b) * 26 + Math.sin(x / 38 + ph * 1.4) * 10); }
				ctx.lineTo(L.w, 0); ctx.closePath();
				var g = ctx.createLinearGradient(0, baseY - 50, 0, baseY + 40); g.addColorStop(0, 'rgba(0,0,0,0)'); g.addColorStop(1, b % 2 ? c2 : c1);
				ctx.globalAlpha = 0.22; ctx.fillStyle = g; ctx.fill();
			}
			ctx.globalAlpha = 1;
		}
		if (reduce) { draw(0); return; } loop(host, draw);
	};
})();
