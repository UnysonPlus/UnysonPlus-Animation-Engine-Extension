(function(){"use strict";
var API=window.upwPhysApi||(window.upwPhysApi={}),PH=window.upwPhys||(window.upwPhys={});
var num=API.num,TF=API.TF,add=API.add,remove=API.remove,observe=API.observe,entrance=API.entrance,springTo=API.springTo,TAU=API.TAU,follow=API.follow,drag=API.drag,reactScale=API.reactScale,bindTrigger=API.bindTrigger;
PH.pendulum = function (el) {
		var ang = num(el, 'angle', 8), spd = num(el, 'speed', 1), ph = Math.random() * TAU, t0 = null;
		el.style.transformOrigin = el.getAttribute('data-phys-anchor') === 'left' ? '0 0' : '50% 0';
		observe(el, function (t) { if (t0 === null) { t0 = t; } var s = (t - t0) / 1000 * spd; TF(el, 'rotate(' + (Math.sin(s * 1.8 + ph) * ang).toFixed(2) + 'deg)'); return true; });
	};
})();
