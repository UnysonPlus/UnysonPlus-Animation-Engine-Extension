(function(){"use strict";
var API=window.upwPhysApi||(window.upwPhysApi={}),PH=window.upwPhys||(window.upwPhys={});
var num=API.num,TF=API.TF,add=API.add,remove=API.remove,observe=API.observe,entrance=API.entrance,springTo=API.springTo,TAU=API.TAU,follow=API.follow,drag=API.drag,reactScale=API.reactScale,bindTrigger=API.bindTrigger;
PH.pop = function (el) {
		var bounce = num(el, 'bounce', 0.6);
		el.style.transformOrigin = 'center'; TF(el, 'scale(0)'); el.style.opacity = '0';
		entrance(el, function () { el.style.opacity = '1'; springTo(0, 1, 0.14 + bounce * 0.12, 0.62 + bounce * 0.18, function (x) { TF(el, 'scale(' + Math.max(0, x).toFixed(3) + ')'); }); });
	};
})();
