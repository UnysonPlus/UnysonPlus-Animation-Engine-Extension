(function(){"use strict";
var API=window.upwBgApi||(window.upwBgApi={}),BG=window.upwBg||(window.upwBg={});
var cfg=API.cfg,reduce=API.reduce,cssLayer=API.cssLayer,canvasLayer=API.canvasLayer,loop=API.loop,num=API.num,areaCount=API.areaCount,rnd=API.rnd,field=API.field,meteorField=API.meteorField,blobField=API.blobField;
BG.noise = function (host) {
		var L = canvasLayer(host), opacity = num(host, 'data-bg-opacity', .06), speed = num(host, 'data-bg-speed', 1);
		var tile = document.createElement('canvas'); tile.width = tile.height = 90;
		var tctx = tile.getContext('2d');
		function regen() {
			var img = tctx.createImageData(90, 90), d = img.data;
			for (var i = 0; i < d.length; i += 4) { var v = (Math.random() * 255) | 0; d[i] = d[i + 1] = d[i + 2] = v; d[i + 3] = 255; }
			tctx.putImageData(img, 0, 0);
		}
		regen();
		function draw(pattern) {
			var ctx = L.ctx; ctx.clearRect(0, 0, L.w, L.h); ctx.globalAlpha = opacity;
			var p = ctx.createPattern(tile, 'repeat'); ctx.fillStyle = p; ctx.fillRect(0, 0, L.w, L.h); ctx.globalAlpha = 1;
		}
		if (reduce) { draw(); return; }
		var last = 0, interval = Math.max(40, 140 / speed);
		loop(host, function (t) { if (t - last > interval) { last = t; regen(); } draw(); });
	};
})();
