(function(){"use strict";
var API=window.upwPhysApi||(window.upwPhysApi={}),PH=window.upwPhys||(window.upwPhys={});
var num=API.num,TF=API.TF,add=API.add,remove=API.remove,observe=API.observe,entrance=API.entrance,springTo=API.springTo,TAU=API.TAU,follow=API.follow,drag=API.drag,reactScale=API.reactScale,bindTrigger=API.bindTrigger;
PH.orbit_cursor = function (el) {
		var rad = num(el, 'radius', 26), spd = num(el, 'speed', 1);
		var cx = 0, cy = 0, has = false, ang = 0, x = 0, y = 0, vx = 0, vy = 0, active = false;
		el.style.willChange = 'transform';
		function loop() {
			ang += 0.06 * spd;
			var tx = has ? cx + Math.cos(ang) * rad : 0, ty = has ? cy + Math.sin(ang) * rad : 0;
			vx += (tx - x) * 0.18; vx *= 0.8; x += vx; vy += (ty - y) * 0.18; vy *= 0.8; y += vy;
			TF(el, 'translate(' + x.toFixed(2) + 'px,' + y.toFixed(2) + 'px)');
			if (!has && Math.abs(x) < 0.4 && Math.abs(y) < 0.4 && Math.abs(vx) < 0.05) { TF(el, 'translate(0,0)'); active = false; return false; }
			return true;
		}
		el.addEventListener('pointermove', function (e) { var r = el.getBoundingClientRect(); cx = e.clientX - (r.left + r.width / 2); cy = e.clientY - (r.top + r.height / 2); has = true; if (!active) { active = true; add(loop); } });
		el.addEventListener('pointerleave', function () { has = false; });
	};
})();
