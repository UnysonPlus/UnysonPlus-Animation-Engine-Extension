(function(){"use strict";
var API=window.upwBgApi||(window.upwBgApi={}),BG=window.upwBg||(window.upwBg={});
var cfg=API.cfg,reduce=API.reduce,cssLayer=API.cssLayer,canvasLayer=API.canvasLayer,loop=API.loop,num=API.num,areaCount=API.areaCount,rnd=API.rnd,field=API.field,meteorField=API.meteorField,blobField=API.blobField;
BG.matrix = function (host) {
		var L = canvasLayer(host), color = host.getAttribute('data-bg-color') || '#19ff7a', speed = num(host, 'data-bg-speed', 6), cols = [];
		var GL = 'ｱｲｳｴｵｶｷｸ0123456789ABCDEF';
		L.seed = function () { cols = []; var n = Math.floor(L.w / 12); for (var i = 0; i < n; i++) { cols.push({ y: rnd(-L.h, 0), v: rnd(2, 6) * speed * 0.3 }); } };
		L.seed();
		function draw() {
			var ctx = L.ctx; ctx.clearRect(0, 0, L.w, L.h); ctx.font = '12px monospace';
			for (var i = 0; i < cols.length; i++) { var c = cols[i]; if (!reduce) { c.y += c.v; if (c.y > L.h + 60) { c.y = rnd(-L.h, 0); } } var x = i * 12 + 2; for (var k = 0; k < 8; k++) { var y = c.y - k * 13; if (y < 0 || y > L.h) { continue; } ctx.globalAlpha = Math.max(0, 1 - k / 8); ctx.fillStyle = k === 0 ? '#d6ffe6' : color; ctx.fillText(GL[(Math.random() * GL.length) | 0], x, y); } }
			ctx.globalAlpha = 1;
		}
		if (reduce) { draw(); return; } loop(host, draw);
	};
})();
