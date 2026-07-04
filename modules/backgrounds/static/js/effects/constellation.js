(function(){"use strict";
var API=window.upwBgApi||(window.upwBgApi={}),BG=window.upwBg||(window.upwBg={});
var cfg=API.cfg,reduce=API.reduce,cssLayer=API.cssLayer,canvasLayer=API.canvasLayer,loop=API.loop,num=API.num,areaCount=API.areaCount,rnd=API.rnd,field=API.field,meteorField=API.meteorField,blobField=API.blobField;
BG.constellation = function (host) {
		var L = canvasLayer(host), color = host.getAttribute('data-bg-color') || '#6aa6ff';
		var density = num(host, 'data-bg-density', 55), link = num(host, 'data-bg-link', 120), parts = [];
		L.seed = function () {
			parts = []; var n = areaCount(density, L.w, L.h);
			for (var i = 0; i < n; i++) { parts.push({ x: Math.random() * L.w, y: Math.random() * L.h, vx: (Math.random() - .5) * .35, vy: (Math.random() - .5) * .35 }); }
		};
		L.seed();
		function draw(move) {
			var ctx = L.ctx, i, j; ctx.clearRect(0, 0, L.w, L.h);
			for (i = 0; i < parts.length; i++) {
				var p = parts[i];
				if (move) { p.x += p.vx; p.y += p.vy; if (p.x < 0 || p.x > L.w) { p.vx *= -1; } if (p.y < 0 || p.y > L.h) { p.vy *= -1; } }
			}
			ctx.strokeStyle = color; ctx.lineWidth = 1;
			for (i = 0; i < parts.length; i++) {
				for (j = i + 1; j < parts.length; j++) {
					var dx = parts[i].x - parts[j].x, dy = parts[i].y - parts[j].y, d = Math.sqrt(dx * dx + dy * dy);
					if (d < link) { ctx.globalAlpha = (1 - d / link) * .5; ctx.beginPath(); ctx.moveTo(parts[i].x, parts[i].y); ctx.lineTo(parts[j].x, parts[j].y); ctx.stroke(); }
				}
			}
			ctx.globalAlpha = .8; ctx.fillStyle = color;
			for (i = 0; i < parts.length; i++) { ctx.beginPath(); ctx.arc(parts[i].x, parts[i].y, 1.5, 0, 6.2832); ctx.fill(); }
			ctx.globalAlpha = 1;
		}
		if (reduce) { draw(false); return; }
		loop(host, function () { draw(true); });
	};
})();
