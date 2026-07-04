/**
 * Animation Engine — Physics shared "pointer" helpers (on-demand chunk).
 * Loads only when a drag/follow/reaction effect is on the page; registers onto window.upwPhysApi.
 */
(function () {
	'use strict';
	var API = window.upwPhysApi;
	if (!API) { return; }
	var TF = API.TF, add = API.add, remove = API.remove, num = API.num;

	/* ---- Shared pointer helper: lean/follow toward the cursor (spring/attract) ---- */
	function follow(el, reach, k) {
		var x = 0, y = 0, vx = 0, vy = 0, tx = 0, ty = 0, active = false;
		el.style.willChange = 'transform';
		function loop() {
			vx += (tx - x) * k; vx *= 0.75; x += vx; vy += (ty - y) * k; vy *= 0.75; y += vy;
			TF(el, 'translate(' + x.toFixed(2) + 'px,' + y.toFixed(2) + 'px)');
			if (tx === 0 && ty === 0 && Math.abs(x) < 0.4 && Math.abs(y) < 0.4 && Math.abs(vx) < 0.05 && Math.abs(vy) < 0.05) { TF(el, 'translate(0,0)'); active = false; return false; }
			return true;
		}
		el.addEventListener('pointermove', function (e) { var r = el.getBoundingClientRect(); tx = (e.clientX - (r.left + r.width / 2)) * reach; ty = (e.clientY - (r.top + r.height / 2)) * reach; if (!active) { active = true; add(loop); } });
		el.addEventListener('pointerleave', function () { tx = 0; ty = 0; });
	}

	/* ---- Shared drag helper (draggable / slingshot) ---- */
	function drag(el, slingshot) {
		var ret = slingshot ? 'spring' : (el.getAttribute('data-phys-return') === 'free' ? 'free' : 'spring');
		var k = slingshot ? 0.1 : num(el, 'stiffness', 0.15);
		var damp = slingshot ? (0.72 + num(el, 'power', 0.7) * 0.22) : 0.8;
		var axis = el.getAttribute('data-phys-axis') || 'both';
		var x = 0, y = 0, vx = 0, vy = 0, dragging = false, ox = 0, oy = 0, lx = 0, ly = 0;
		el.style.willChange = 'transform'; el.style.cursor = 'grab';
		el.style.touchAction = axis === 'x' ? 'pan-y' : (axis === 'y' ? 'pan-x' : 'none');
		function apply() { TF(el, 'translate(' + x.toFixed(2) + 'px,' + y.toFixed(2) + 'px)'); }
		function loop() {
			if (ret === 'spring') {
				vx += (0 - x) * k; vx *= damp; x += vx; vy += (0 - y) * k; vy *= damp; y += vy; apply();
				if (Math.abs(x) < 0.4 && Math.abs(y) < 0.4 && Math.abs(vx) < 0.05 && Math.abs(vy) < 0.05) { x = 0; y = 0; apply(); return false; }
				return true;
			}
			x += vx; y += vy; vx *= 0.92; vy *= 0.92; apply(); return Math.abs(vx) > 0.1 || Math.abs(vy) > 0.1;
		}
		el.addEventListener('pointerdown', function (e) { dragging = true; remove(loop); try { el.setPointerCapture(e.pointerId); } catch (err) {} el.style.cursor = 'grabbing'; ox = e.clientX - x; oy = e.clientY - y; lx = e.clientX; ly = e.clientY; vx = vy = 0; e.preventDefault(); });
		el.addEventListener('pointermove', function (e) { if (!dragging) { return; } if (axis !== 'y') { x = e.clientX - ox; vx = e.clientX - lx; } if (axis !== 'x') { y = e.clientY - oy; vy = e.clientY - ly; } lx = e.clientX; ly = e.clientY; apply(); });
		function endDrag() { if (!dragging) { return; } dragging = false; el.style.cursor = 'grab'; add(loop); }
		el.addEventListener('pointerup', endDrag);
		el.addEventListener('pointercancel', endDrag);
	}

	/* ---- Shared reaction helpers (jelly / squash + recoil / shake / spin triggers) ---- */
	function reactScale(el, ox, oy) {
		var sx = 1, sy = 1, vx = 0, vy = 0, active = false;
		el.style.willChange = 'transform'; el.style.transformOrigin = ox + ' ' + oy;
		function loop() {
			vx += (1 - sx) * 0.22; vx *= 0.8; sx += vx; vy += (1 - sy) * 0.22; vy *= 0.8; sy += vy;
			TF(el, 'scale(' + sx.toFixed(3) + ',' + sy.toFixed(3) + ')');
			if (Math.abs(vx) < 0.002 && Math.abs(vy) < 0.002 && Math.abs(sx - 1) < 0.003 && Math.abs(sy - 1) < 0.003) { sx = sy = 1; TF(el, 'scale(1,1)'); active = false; return false; }
			return true;
		}
		return function (dx, dy) { vx = dx; vy = dy; if (!active) { active = true; add(loop); } };
	}
	function bindTrigger(el, poke) { el.addEventListener(el.getAttribute('data-phys-trigger') === 'click' ? 'click' : 'pointerenter', poke); }

	API.follow = follow; API.drag = drag; API.reactScale = reactScale; API.bindTrigger = bindTrigger;
})();
