(function(){"use strict";
var API=window.upwPhysApi||(window.upwPhysApi={}),PH=window.upwPhys||(window.upwPhys={});
var num=API.num,TF=API.TF,add=API.add,remove=API.remove,observe=API.observe,entrance=API.entrance,springTo=API.springTo,TAU=API.TAU,follow=API.follow,drag=API.drag,reactScale=API.reactScale,bindTrigger=API.bindTrigger;
PH.float = function (el) {
		var amt = num(el, 'amount', 12), spd = num(el, 'speed', 1), sway = el.getAttribute('data-phys-rotate') !== 'no';
		var ph = Math.random() * TAU, t0 = null;
		observe(el, function (t) { if (t0 === null) { t0 = t; } var s = (t - t0) / 1000 * spd; TF(el, 'translateY(' + (Math.sin(s * 1.6 + ph) * amt).toFixed(2) + 'px) rotate(' + (sway ? Math.sin(s * 1.05 + ph) * amt * 0.14 : 0).toFixed(2) + 'deg)'); return true; });
	};
})();
