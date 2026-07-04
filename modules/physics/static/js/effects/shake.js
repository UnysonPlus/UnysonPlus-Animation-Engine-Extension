(function(){"use strict";
var API=window.upwPhysApi||(window.upwPhysApi={}),PH=window.upwPhys||(window.upwPhys={});
var num=API.num,TF=API.TF,add=API.add,remove=API.remove,observe=API.observe,entrance=API.entrance,springTo=API.springTo,TAU=API.TAU,follow=API.follow,drag=API.drag,reactScale=API.reactScale,bindTrigger=API.bindTrigger;
PH.shake = function (el) {
		var i = num(el, 'intensity', 0.5), amp = 0, ph = 0, active = false;
		el.style.willChange = 'transform';
		function loop() { ph += 0.85; amp *= 0.9; TF(el, 'translateX(' + (Math.sin(ph) * amp).toFixed(2) + 'px)'); if (amp < 0.3) { TF(el, 'translateX(0)'); active = false; return false; } return true; }
		bindTrigger(el, function () { amp = 9 * i; ph = 0; if (!active) { active = true; add(loop); } });
	};
})();
