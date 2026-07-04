(function(){"use strict";
var API=window.upwBgApi||(window.upwBgApi={}),BG=window.upwBg||(window.upwBg={});
var cfg=API.cfg,reduce=API.reduce,cssLayer=API.cssLayer,canvasLayer=API.canvasLayer,loop=API.loop,num=API.num,areaCount=API.areaCount,rnd=API.rnd,field=API.field,meteorField=API.meteorField,blobField=API.blobField;
BG.spotlight = function (host) {
		var L = canvasLayer(host), color = host.getAttribute('data-bg-color') || '#6aa6ff', size = num(host, 'data-bg-size', 260);
		var mx = L.w / 2, my = L.h / 2;
		host.addEventListener('pointermove', function (e) { var r = host.getBoundingClientRect(); mx = e.clientX - r.left; my = e.clientY - r.top; }, { passive: true });
		loop(host, function () { var ctx = L.ctx; ctx.clearRect(0, 0, L.w, L.h); var g = ctx.createRadialGradient(mx, my, 0, mx, my, size); g.addColorStop(0, color); g.addColorStop(1, 'rgba(0,0,0,0)'); ctx.globalAlpha = 0.4; ctx.fillStyle = g; ctx.fillRect(0, 0, L.w, L.h); ctx.globalAlpha = 1; });
	};
})();
