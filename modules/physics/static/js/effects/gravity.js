(function(){"use strict";
var API=window.upwPhysApi||(window.upwPhysApi={}),PH=window.upwPhys||(window.upwPhys={});
var num=API.num,TF=API.TF,add=API.add,remove=API.remove,observe=API.observe,entrance=API.entrance,springTo=API.springTo,TAU=API.TAU,follow=API.follow,drag=API.drag,reactScale=API.reactScale,bindTrigger=API.bindTrigger;
PH.gravity = function (el) {
		var drop = num(el, 'drop', 120), bounce = num(el, 'bounce', 0.5);
		TF(el, 'translateY(' + (-drop) + 'px)'); el.style.opacity = '0';
		entrance(el, function () {
			var y = -drop, v = 0, g = drop * 0.012 + 0.6, op = 0;
			add(function () {
				v += g; y += v;
				if (y >= 0) { y = 0; v = -v * bounce; if (Math.abs(v) < 0.6) { TF(el, 'translateY(0)'); el.style.opacity = '1'; return false; } }
				op = Math.min(1, op + 0.14); TF(el, 'translateY(' + y.toFixed(2) + 'px)'); el.style.opacity = op.toFixed(2); return true;
			});
		});
	};
})();
