(function(){"use strict";
var API=window.upwPhysApi||(window.upwPhysApi={}),PH=window.upwPhys||(window.upwPhys={});
var num=API.num,TF=API.TF,add=API.add,remove=API.remove,observe=API.observe,entrance=API.entrance,springTo=API.springTo,TAU=API.TAU,follow=API.follow,drag=API.drag,reactScale=API.reactScale,bindTrigger=API.bindTrigger;
PH.repel = function (el) {
		var radius = num(el, 'radius', 120), strength = num(el, 'strength', 0.6);
		var x = 0, y = 0, vx = 0, vy = 0, tx = 0, ty = 0, active = false;
		el.style.willChange = 'transform';
		function loop() {
			vx += (tx - x) * 0.2; vx *= 0.7; x += vx; vy += (ty - y) * 0.2; vy *= 0.7; y += vy;
			TF(el, 'translate(' + x.toFixed(2) + 'px,' + y.toFixed(2) + 'px)');
			if (tx === 0 && ty === 0 && Math.abs(x) < 0.4 && Math.abs(y) < 0.4 && Math.abs(vx) < 0.05) { TF(el, 'translate(0,0)'); active = false; return false; }
			return true;
		}
		document.addEventListener('pointermove', function (e) {
			var r = el.getBoundingClientRect(); var dx = (r.left + r.width / 2) - e.clientX, dy = (r.top + r.height / 2) - e.clientY, d = Math.sqrt(dx * dx + dy * dy);
			if (d < radius && d > 0.001) { var f = (1 - d / radius) * radius * strength * 0.5; tx = dx / d * f; ty = dy / d * f; } else { tx = 0; ty = 0; }
			if (!active) { active = true; add(loop); }
		}, { passive: true });
	};
})();
