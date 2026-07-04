(function(){"use strict";
var API=window.upwPhysApi||(window.upwPhysApi={}),PH=window.upwPhys||(window.upwPhys={});
var num=API.num,TF=API.TF,add=API.add,remove=API.remove,observe=API.observe,entrance=API.entrance,springTo=API.springTo,TAU=API.TAU,follow=API.follow,drag=API.drag,reactScale=API.reactScale,bindTrigger=API.bindTrigger;
PH.drift = function (el) {
		var amt = num(el, 'amount', 14), spd = num(el, 'speed', 1), ph = Math.random() * TAU, t0 = null;
		observe(el, function (t) { if (t0 === null) { t0 = t; } var s = (t - t0) / 1000 * spd; var x = (Math.sin(s * 0.7 + ph) + Math.sin(s * 0.31 + ph * 2) * 0.6) * amt * 0.6; var y = (Math.cos(s * 0.53 + ph) + Math.sin(s * 0.23) * 0.5) * amt * 0.6; TF(el, 'translate(' + x.toFixed(2) + 'px,' + y.toFixed(2) + 'px)'); return true; });
	};
})();
