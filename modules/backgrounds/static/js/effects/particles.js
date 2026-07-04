(function(){"use strict";
var API=window.upwBgApi||(window.upwBgApi={}),BG=window.upwBg||(window.upwBg={});
var cfg=API.cfg,reduce=API.reduce,cssLayer=API.cssLayer,canvasLayer=API.canvasLayer,loop=API.loop,num=API.num,areaCount=API.areaCount,rnd=API.rnd,field=API.field,meteorField=API.meteorField,blobField=API.blobField;
BG.particles = function (host) {
		var L = canvasLayer(host), color = host.getAttribute('data-bg-color') || '#6aa6ff';
		var density = num(host, 'data-bg-density', 60), speed = num(host, 'data-bg-speed', 3), parts = [];
		L.seed = function () {
			parts = []; var n = areaCount(density, L.w, L.h);
			for (var i = 0; i < n; i++) { parts.push({ x: Math.random() * L.w, y: Math.random() * L.h, vx: (Math.random() - .5) * speed * .18, vy: (Math.random() - .5) * speed * .18, r: Math.random() * 1.6 + .6 }); }
		};
		L.seed();
		function draw(move) {
			var ctx = L.ctx; ctx.clearRect(0, 0, L.w, L.h); ctx.fillStyle = color; ctx.globalAlpha = .6;
			for (var i = 0; i < parts.length; i++) {
				var p = parts[i];
				if (move) { p.x += p.vx; p.y += p.vy; if (p.x < 0 || p.x > L.w) { p.vx *= -1; } if (p.y < 0 || p.y > L.h) { p.vy *= -1; } }
				ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, 6.2832); ctx.fill();
			}
			ctx.globalAlpha = 1;
		}
		if (reduce) { draw(false); return; }
		loop(host, function () { draw(true); });
	};
})();
