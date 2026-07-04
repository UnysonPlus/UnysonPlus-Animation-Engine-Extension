(function(){"use strict";
var API=window.upwPhysApi||(window.upwPhysApi={}),PH=window.upwPhys||(window.upwPhys={});
var num=API.num,TF=API.TF,add=API.add,remove=API.remove,observe=API.observe,entrance=API.entrance,springTo=API.springTo,TAU=API.TAU,follow=API.follow,drag=API.drag,reactScale=API.reactScale,bindTrigger=API.bindTrigger;
PH.recoil = function (el) {
		var dist = num(el, 'distance', 14), x = 0, vx = 0, active = false;
		el.style.willChange = 'transform';
		function loop() { vx += (0 - x) * 0.25; vx *= 0.7; x += vx; TF(el, 'translate(' + x.toFixed(2) + 'px,0)'); if (Math.abs(x) < 0.4 && Math.abs(vx) < 0.05) { TF(el, 'translate(0,0)'); active = false; return false; } return true; }
		bindTrigger(el, function () { x = -dist; vx = 0; if (!active) { active = true; add(loop); } });
	};
})();
