(function(){"use strict";
var API=window.upwPhysApi||(window.upwPhysApi={}),PH=window.upwPhys||(window.upwPhys={});
var num=API.num,TF=API.TF,add=API.add,remove=API.remove,observe=API.observe,entrance=API.entrance,springTo=API.springTo,TAU=API.TAU,follow=API.follow,drag=API.drag,reactScale=API.reactScale,bindTrigger=API.bindTrigger;
PH.bounded = function (el) {
		var spd = num(el, 'speed', 1);
		el.style.willChange = 'transform';
		var x = 0, y = 0, ang = Math.random() * TAU, v = (1.2 + Math.random()) * spd, vx = Math.cos(ang) * v, vy = Math.sin(ang) * v;
		observe(el, function () {
			var p = el.parentElement, er = el.getBoundingClientRect();
			if (!p) { return true; }
			var pr = p.getBoundingClientRect();
			var maxX = (pr.width - er.width) / 2, maxY = (pr.height - er.height) / 2;
			if (maxX < 4 || maxY < 4) { return true; }
			x += vx; y += vy;
			if (x > maxX) { x = maxX; vx = -vx; } else if (x < -maxX) { x = -maxX; vx = -vx; }
			if (y > maxY) { y = maxY; vy = -vy; } else if (y < -maxY) { y = -maxY; vy = -vy; }
			TF(el, 'translate(' + x.toFixed(1) + 'px,' + y.toFixed(1) + 'px)'); return true;
		});
	};
})();
