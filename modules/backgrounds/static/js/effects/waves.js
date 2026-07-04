(function(){"use strict";
var API=window.upwBgApi||(window.upwBgApi={}),BG=window.upwBg||(window.upwBg={});
var cfg=API.cfg,reduce=API.reduce,cssLayer=API.cssLayer,canvasLayer=API.canvasLayer,loop=API.loop,num=API.num,areaCount=API.areaCount,rnd=API.rnd,field=API.field,meteorField=API.meteorField,blobField=API.blobField;
BG.waves = function (host) {
		var L = canvasLayer(host), color = host.getAttribute('data-bg-color') || '#2f74e6';
		var amp = num(host, 'data-bg-amp', 30), speed = num(host, 'data-bg-speed', 6);
		function draw(t) {
			var ctx = L.ctx; ctx.clearRect(0, 0, L.w, L.h);
			var layers = [{ a: amp, y: .68, o: .18, s: 1 }, { a: amp * .7, y: .78, o: .22, s: 1.4 }, { a: amp * .5, y: .88, o: .3, s: .8 }];
			for (var k = 0; k < layers.length; k++) {
				var ly = layers[k], base = L.h * ly.y, ph = (t / 1000) * (speed / 6) * ly.s;
				ctx.beginPath(); ctx.moveTo(0, L.h);
				for (var x = 0; x <= L.w; x += 8) { ctx.lineTo(x, base + Math.sin(x / 90 + ph + k) * ly.a); }
				ctx.lineTo(L.w, L.h); ctx.closePath();
				ctx.fillStyle = color; ctx.globalAlpha = ly.o; ctx.fill();
			}
			ctx.globalAlpha = 1;
		}
		if (reduce) { draw(0); return; }
		loop(host, draw);
	};
})();
