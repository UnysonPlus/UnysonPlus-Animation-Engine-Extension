(function(){"use strict";
var API=window.upwPhysApi||(window.upwPhysApi={}),PH=window.upwPhys||(window.upwPhys={});
var num=API.num,TF=API.TF,add=API.add,remove=API.remove,observe=API.observe,entrance=API.entrance,springTo=API.springTo,TAU=API.TAU,follow=API.follow,drag=API.drag,reactScale=API.reactScale,bindTrigger=API.bindTrigger;
PH.ragdoll = function (el) {
		var drop = num(el, 'drop', 120);
		el.style.transformOrigin = 'center'; TF(el, 'translateY(' + (-drop) + 'px)'); el.style.opacity = '0';
		entrance(el, function () {
			var y = -drop, v = 0, g = drop * 0.012 + 0.6, rot = 0, rv = (Math.random() < 0.5 ? -1 : 1) * (5 + Math.random() * 5), op = 0, rest = (Math.random() < 0.5 ? -1 : 1) * (3 + Math.random() * 5);
			add(function () {
				v += g; y += v; rot += rv;
				if (y >= 0) { y = 0; v = -v * 0.32; rv *= 0.45; if (Math.abs(v) < 0.6) { rot += (rest - rot) * 0.2; if (Math.abs(rot - rest) < 0.4) { rot = rest; TF(el, 'translateY(0) rotate(' + rest.toFixed(1) + 'deg)'); el.style.opacity = '1'; return false; } } }
				op = Math.min(1, op + 0.14); TF(el, 'translateY(' + y.toFixed(2) + 'px) rotate(' + rot.toFixed(1) + 'deg)'); el.style.opacity = op.toFixed(2); return true;
			});
		});
	};
})();
