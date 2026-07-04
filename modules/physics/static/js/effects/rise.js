(function(){"use strict";
var API=window.upwPhysApi||(window.upwPhysApi={}),PH=window.upwPhys||(window.upwPhys={});
var num=API.num,TF=API.TF,add=API.add,remove=API.remove,observe=API.observe,entrance=API.entrance,springTo=API.springTo,TAU=API.TAU,follow=API.follow,drag=API.drag,reactScale=API.reactScale,bindTrigger=API.bindTrigger;
PH.rise = function (el) {
		var drop = num(el, 'drop', 120);
		TF(el, 'translateY(' + drop + 'px)'); el.style.opacity = '0'; var op = 0;
		entrance(el, function () { springTo(drop, 0, 0.12, 0.84, function (x) { TF(el, 'translateY(' + x.toFixed(2) + 'px)'); op = Math.min(1, op + 0.12); el.style.opacity = op.toFixed(2); }, function () { el.style.opacity = '1'; }); });
	};
})();
