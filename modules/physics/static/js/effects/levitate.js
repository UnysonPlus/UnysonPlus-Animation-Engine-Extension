(function(){"use strict";
var API=window.upwPhysApi||(window.upwPhysApi={}),PH=window.upwPhys||(window.upwPhys={});
var num=API.num,TF=API.TF,add=API.add,remove=API.remove,observe=API.observe,entrance=API.entrance,springTo=API.springTo,TAU=API.TAU,follow=API.follow,drag=API.drag,reactScale=API.reactScale,bindTrigger=API.bindTrigger;
PH.levitate = function (el) {
		var rise = num(el, 'rise', 20), bob = num(el, 'bob', 8), ph = Math.random() * TAU, t0 = null;
		observe(el, function (t) { if (t0 === null) { t0 = t; } var s = (t - t0) / 1000; var y = -rise * (1 - Math.exp(-s * 2.5)) + Math.sin(s * 1.6 + ph) * bob; TF(el, 'translateY(' + y.toFixed(2) + 'px)'); return true; });
	};
})();
