(function(){"use strict";
var API=window.upwPhysApi||(window.upwPhysApi={}),PH=window.upwPhys||(window.upwPhys={});
var num=API.num,TF=API.TF,add=API.add,remove=API.remove,observe=API.observe,entrance=API.entrance,springTo=API.springTo,TAU=API.TAU,follow=API.follow,drag=API.drag,reactScale=API.reactScale,bindTrigger=API.bindTrigger;
PH.orbit = function (el) {
		var rad = num(el, 'radius', 20), spd = num(el, 'speed', 1), ph = Math.random() * TAU, t0 = null;
		observe(el, function (t) { if (t0 === null) { t0 = t; } var a = (t - t0) / 1000 * spd * 1.4 + ph; TF(el, 'translate(' + (Math.cos(a) * rad).toFixed(2) + 'px,' + (Math.sin(a) * rad).toFixed(2) + 'px)'); return true; });
	};
})();
