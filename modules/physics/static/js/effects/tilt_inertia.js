(function(){"use strict";
var API=window.upwPhysApi||(window.upwPhysApi={}),PH=window.upwPhys||(window.upwPhys={});
var num=API.num,TF=API.TF,add=API.add,remove=API.remove,observe=API.observe,entrance=API.entrance,springTo=API.springTo,TAU=API.TAU,follow=API.follow,drag=API.drag,reactScale=API.reactScale,bindTrigger=API.bindTrigger;
PH.tilt_inertia = function (el) {
		var max = num(el, 'max-tilt', 14);
		var rx = 0, ry = 0, vrx = 0, vry = 0, tx = 0, ty = 0, active = false;
		el.style.willChange = 'transform';
		function loop() {
			vrx += (tx - rx) * 0.15; vrx *= 0.75; rx += vrx; vry += (ty - ry) * 0.15; vry *= 0.75; ry += vry;
			TF(el, 'perspective(600px) rotateX(' + rx.toFixed(2) + 'deg) rotateY(' + ry.toFixed(2) + 'deg)');
			if (tx === 0 && ty === 0 && Math.abs(rx) < 0.1 && Math.abs(ry) < 0.1 && Math.abs(vrx) < 0.02) { TF(el, 'perspective(600px)'); active = false; return false; }
			return true;
		}
		el.addEventListener('pointermove', function (e) { var r = el.getBoundingClientRect(); var px = (e.clientX - r.left) / r.width - 0.5, py = (e.clientY - r.top) / r.height - 0.5; tx = -py * max * 2; ty = px * max * 2; if (!active) { active = true; add(loop); } });
		el.addEventListener('pointerleave', function () { tx = 0; ty = 0; });
	};
})();
