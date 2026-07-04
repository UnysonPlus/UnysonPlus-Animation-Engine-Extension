(function(){"use strict";
var API=window.upwPhysApi||(window.upwPhysApi={}),PH=window.upwPhys||(window.upwPhys={});
var num=API.num,TF=API.TF,add=API.add,remove=API.remove,observe=API.observe,entrance=API.entrance,springTo=API.springTo,TAU=API.TAU,follow=API.follow,drag=API.drag,reactScale=API.reactScale,bindTrigger=API.bindTrigger;
PH.rubber_band = function (el) {
		var strength = num(el, 'strength', 0.4);
		var x = 0, y = 0, vx = 0, vy = 0, tx = 0, ty = 0, active = false;
		el.style.willChange = 'transform'; el.style.transformOrigin = 'center';
		function loop() {
			vx += (tx - x) * 0.12; vx *= 0.78; x += vx; vy += (ty - y) * 0.12; vy *= 0.78; y += vy;
			var st = 1 + Math.min(0.45, Math.sqrt(x * x + y * y) / 220 * (0.5 + strength));
			TF(el, 'translate(' + x.toFixed(2) + 'px,' + y.toFixed(2) + 'px) scale(' + st.toFixed(3) + ',' + (1 / st).toFixed(3) + ')');
			if (tx === 0 && ty === 0 && Math.abs(x) < 0.4 && Math.abs(y) < 0.4 && Math.abs(vx) < 0.05) { TF(el, 'translate(0,0)'); active = false; return false; }
			return true;
		}
		el.addEventListener('pointermove', function (e) { var r = el.getBoundingClientRect(); tx = (e.clientX - (r.left + r.width / 2)) * strength * 0.5; ty = (e.clientY - (r.top + r.height / 2)) * strength * 0.5; if (!active) { active = true; add(loop); } });
		el.addEventListener('pointerleave', function () { tx = 0; ty = 0; });
	};
})();
