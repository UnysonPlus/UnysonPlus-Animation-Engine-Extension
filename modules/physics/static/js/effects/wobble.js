(function(){"use strict";
var API=window.upwPhysApi||(window.upwPhysApi={}),PH=window.upwPhys||(window.upwPhys={});
var num=API.num,TF=API.TF,add=API.add,remove=API.remove,observe=API.observe,entrance=API.entrance,springTo=API.springTo,TAU=API.TAU,follow=API.follow,drag=API.drag,reactScale=API.reactScale,bindTrigger=API.bindTrigger;
PH.wobble = function (el) {
		var amt = num(el, 'amount', 3), spd = num(el, 'speed', 1), ph = Math.random() * TAU, t0 = null;
		observe(el, function (t) { if (t0 === null) { t0 = t; } var s = (t - t0) / 1000 * spd; TF(el, 'rotate(' + ((Math.sin(s * 7 + ph) + Math.sin(s * 11 + ph * 1.3) * 0.5) * amt * 0.7).toFixed(2) + 'deg)'); return true; });
	};
})();
