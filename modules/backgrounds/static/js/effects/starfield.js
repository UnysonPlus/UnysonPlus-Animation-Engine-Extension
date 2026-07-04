(function(){"use strict";
var API=window.upwBgApi||(window.upwBgApi={}),BG=window.upwBg||(window.upwBg={});
var cfg=API.cfg,reduce=API.reduce,cssLayer=API.cssLayer,canvasLayer=API.canvasLayer,loop=API.loop,num=API.num,areaCount=API.areaCount,rnd=API.rnd,field=API.field,meteorField=API.meteorField,blobField=API.blobField;
BG.starfield = function (host) {
		var L = canvasLayer(host), color = host.getAttribute('data-bg-color') || '#ffffff';
		var density = num(host, 'data-bg-density', 120), speed = num(host, 'data-bg-speed', 4), stars = [];
		L.seed = function () {
			stars = []; var n = Math.max(20, Math.min(500, density));
			for (var i = 0; i < n; i++) { stars.push({ x: (Math.random() - .5) * L.w, y: (Math.random() - .5) * L.h, z: Math.random() * L.w }); }
		};
		L.seed();
		function draw(move) {
			var ctx = L.ctx, cx = L.w / 2, cy = L.h / 2; ctx.clearRect(0, 0, L.w, L.h); ctx.fillStyle = color;
			for (var i = 0; i < stars.length; i++) {
				var s = stars[i];
				if (move) { s.z -= speed * .6; if (s.z < 1) { s.x = (Math.random() - .5) * L.w; s.y = (Math.random() - .5) * L.h; s.z = L.w; } }
				var k = 128 / s.z, sx = cx + s.x * k, sy = cy + s.y * k, r = (1 - s.z / L.w) * 1.8;
				if (sx > 0 && sx < L.w && sy > 0 && sy < L.h) { ctx.globalAlpha = Math.min(1, (1 - s.z / L.w) + .2); ctx.beginPath(); ctx.arc(sx, sy, Math.max(.4, r), 0, 6.2832); ctx.fill(); }
			}
			ctx.globalAlpha = 1;
		}
		if (reduce) { draw(false); return; }
		loop(host, function () { draw(true); });
	};
})();
