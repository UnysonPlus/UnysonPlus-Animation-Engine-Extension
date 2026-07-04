(function(){"use strict";
var API=window.upwPhysApi||(window.upwPhysApi={}),PH=window.upwPhys||(window.upwPhys={});
var num=API.num,TF=API.TF,add=API.add,remove=API.remove,observe=API.observe,entrance=API.entrance,springTo=API.springTo,TAU=API.TAU,follow=API.follow,drag=API.drag,reactScale=API.reactScale,bindTrigger=API.bindTrigger;
PH.spin = function (el) {
		var spd = num(el, 'speed', 1), ang = 0, av = 0, active = false;
		el.style.willChange = 'transform'; el.style.transformOrigin = 'center';
		function loop() { ang += av; av *= 0.94; TF(el, 'rotate(' + ang.toFixed(2) + 'deg)'); if (Math.abs(av) < 0.05) { active = false; return false; } return true; }
		bindTrigger(el, function () { av += 11 * spd; if (!active) { active = true; add(loop); } });
	};
})();
