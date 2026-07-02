/**
 * Animation Engine — Physics Effects runtime.
 *
 * Reads [data-phys] elements emitted by physics.php and applies a physics-driven motion via a
 * tiny spring/verlet integrator (vanilla JS, no deps). A single shared RAF ticker drives every
 * active element; continuous effects pause off-screen + on tab-hide. Honours window.upwPhysicsCfg
 * (reduced-motion / disable-on-mobile), prefers-reduced-motion, and skips pointer-following
 * effects on touch. Effects overwrite the element's transform each frame.
 */
(function () {
	'use strict';

	var cfg = window.upwPhysicsCfg || {};
	var mql = window.matchMedia || function () { return { matches: false }; };
	var REDUCE = cfg.reducedMotion !== false && mql('(prefers-reduced-motion: reduce)').matches;
	var isTouch = mql('(hover: none), (pointer: coarse)').matches;
	var isMobile = (window.innerWidth || 1024) < 768;

	/* ---- Shared ticker ---- */
	var running = [], raf = 0;
	function tick(t) {
		raf = 0;
		for (var i = running.length - 1; i >= 0; i--) { if (running[i](t) === false) { running.splice(i, 1); } }
		if (running.length) { raf = requestAnimationFrame(tick); }
	}
	function add(fn) { if (running.indexOf(fn) < 0) { running.push(fn); if (!raf) { raf = requestAnimationFrame(tick); } } }
	function remove(fn) { var i = running.indexOf(fn); if (i >= 0) { running.splice(i, 1); } }

	function num(el, attr, dflt) { var v = parseFloat(el.getAttribute('data-phys-' + attr)); return isNaN(v) ? dflt : v; }
	function TF(el, s) { el.style.transform = s; }

	/* ---- Continuous: run only while on-screen + tab visible ---- */
	function observe(el, fn) {
		el.style.willChange = 'transform';
		el.__pvis = false;
		function show() { if (!el.__pvis && !document.hidden) { el.__pvis = true; add(fn); } }
		function hide() { el.__pvis = false; remove(fn); }
		if ('IntersectionObserver' in window) {
			new IntersectionObserver(function (e) { e.forEach(function (en) { return en.isIntersecting ? show() : hide(); }); }, { threshold: 0.01 }).observe(el);
		} else { show(); }
		document.addEventListener('visibilitychange', function () { if (document.hidden) { remove(fn); } else if (el.__pvis) { add(fn); } });
	}

	/* ---- One-shot entrance when scrolled into view ---- */
	function entrance(el, run) {
		el.style.willChange = 'transform, opacity';
		if (!('IntersectionObserver' in window)) { run(); return; }
		var done = false;
		var io = new IntersectionObserver(function (e) { e.forEach(function (en) { if (en.isIntersecting && !done) { done = true; io.disconnect(); run(); } }); }, { threshold: 0.15 });
		io.observe(el);
	}

	/* ---- Spring a scalar from → to, calling apply(x) each frame ---- */
	function springTo(from, to, k, damp, apply, done) {
		var x = from, v = 0;
		add(function () {
			v += (to - x) * k; v *= damp; x += v; apply(x);
			if (Math.abs(x - to) < 0.3 && Math.abs(v) < 0.05) { apply(to); if (done) { done(); } return false; }
			return true;
		});
	}

	var TAU = Math.PI * 2;

	/* ============================ Continuous ============================ */
	function float(el) {
		var amt = num(el, 'amount', 12), spd = num(el, 'speed', 1), sway = el.getAttribute('data-phys-rotate') !== 'no';
		var ph = Math.random() * TAU, t0 = null;
		observe(el, function (t) { if (t0 === null) { t0 = t; } var s = (t - t0) / 1000 * spd; TF(el, 'translateY(' + (Math.sin(s * 1.6 + ph) * amt).toFixed(2) + 'px) rotate(' + (sway ? Math.sin(s * 1.05 + ph) * amt * 0.14 : 0).toFixed(2) + 'deg)'); return true; });
	}
	function levitate(el) {
		var rise = num(el, 'rise', 20), bob = num(el, 'bob', 8), ph = Math.random() * TAU, t0 = null;
		observe(el, function (t) { if (t0 === null) { t0 = t; } var s = (t - t0) / 1000; var y = -rise * (1 - Math.exp(-s * 2.5)) + Math.sin(s * 1.6 + ph) * bob; TF(el, 'translateY(' + y.toFixed(2) + 'px)'); return true; });
	}
	function sway(el) {
		var ang = num(el, 'angle', 6), spd = num(el, 'speed', 1), ph = Math.random() * TAU, t0 = null;
		el.style.transformOrigin = '50% 100%';
		observe(el, function (t) { if (t0 === null) { t0 = t; } var s = (t - t0) / 1000 * spd; TF(el, 'rotate(' + (Math.sin(s * 1.5 + ph) * ang).toFixed(2) + 'deg)'); return true; });
	}
	function pendulum(el) {
		var ang = num(el, 'angle', 8), spd = num(el, 'speed', 1), ph = Math.random() * TAU, t0 = null;
		el.style.transformOrigin = el.getAttribute('data-phys-anchor') === 'left' ? '0 0' : '50% 0';
		observe(el, function (t) { if (t0 === null) { t0 = t; } var s = (t - t0) / 1000 * spd; TF(el, 'rotate(' + (Math.sin(s * 1.8 + ph) * ang).toFixed(2) + 'deg)'); return true; });
	}
	function wobble(el) {
		var amt = num(el, 'amount', 3), spd = num(el, 'speed', 1), ph = Math.random() * TAU, t0 = null;
		observe(el, function (t) { if (t0 === null) { t0 = t; } var s = (t - t0) / 1000 * spd; TF(el, 'rotate(' + ((Math.sin(s * 7 + ph) + Math.sin(s * 11 + ph * 1.3) * 0.5) * amt * 0.7).toFixed(2) + 'deg)'); return true; });
	}
	function breathing(el) {
		var amt = num(el, 'amount', 0.06), spd = num(el, 'speed', 1), ph = Math.random() * TAU, t0 = null;
		el.style.transformOrigin = 'center';
		observe(el, function (t) { if (t0 === null) { t0 = t; } var s = (t - t0) / 1000 * spd; TF(el, 'scale(' + (1 + Math.sin(s * 1.5 + ph) * amt).toFixed(3) + ')'); return true; });
	}
	function drift(el) {
		var amt = num(el, 'amount', 14), spd = num(el, 'speed', 1), ph = Math.random() * TAU, t0 = null;
		observe(el, function (t) { if (t0 === null) { t0 = t; } var s = (t - t0) / 1000 * spd; var x = (Math.sin(s * 0.7 + ph) + Math.sin(s * 0.31 + ph * 2) * 0.6) * amt * 0.6; var y = (Math.cos(s * 0.53 + ph) + Math.sin(s * 0.23) * 0.5) * amt * 0.6; TF(el, 'translate(' + x.toFixed(2) + 'px,' + y.toFixed(2) + 'px)'); return true; });
	}
	function orbit(el) {
		var rad = num(el, 'radius', 20), spd = num(el, 'speed', 1), ph = Math.random() * TAU, t0 = null;
		observe(el, function (t) { if (t0 === null) { t0 = t; } var a = (t - t0) / 1000 * spd * 1.4 + ph; TF(el, 'translate(' + (Math.cos(a) * rad).toFixed(2) + 'px,' + (Math.sin(a) * rad).toFixed(2) + 'px)'); return true; });
	}

	/* ============================ Entrance ============================ */
	function gravity(el) {
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
	}
	function rise(el) {
		var drop = num(el, 'drop', 120);
		TF(el, 'translateY(' + drop + 'px)'); el.style.opacity = '0'; var op = 0;
		entrance(el, function () { springTo(drop, 0, 0.12, 0.84, function (x) { TF(el, 'translateY(' + x.toFixed(2) + 'px)'); op = Math.min(1, op + 0.12); el.style.opacity = op.toFixed(2); }, function () { el.style.opacity = '1'; }); });
	}
	function sag(el) {
		var drop = num(el, 'drop', 60);
		TF(el, 'translateY(' + (-drop) + 'px)'); el.style.opacity = '0'; var op = 0;
		entrance(el, function () { springTo(-drop, 0, 0.06, 0.72, function (x) { TF(el, 'translateY(' + x.toFixed(2) + 'px)'); op = Math.min(1, op + 0.1); el.style.opacity = op.toFixed(2); }, function () { el.style.opacity = '1'; }); });
	}
	function pop(el) {
		var bounce = num(el, 'bounce', 0.6);
		el.style.transformOrigin = 'center'; TF(el, 'scale(0)'); el.style.opacity = '0';
		entrance(el, function () { el.style.opacity = '1'; springTo(0, 1, 0.14 + bounce * 0.12, 0.62 + bounce * 0.18, function (x) { TF(el, 'scale(' + Math.max(0, x).toFixed(3) + ')'); }); });
	}
	function ragdoll(el) {
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
	}

	/* ============================ Pointer ============================ */
	// Shared: lean/follow toward the cursor (element-local), spring back on leave.
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
	function repel(el) {
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
	}
	function orbitCursor(el) {
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
	}
	function rubberBand(el) {
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
	}
	function tiltInertia(el) {
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
	}

	/* ============================ Drag ============================ */
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

	/* ============================ Container ============================ */
	function bounded(el) {
		var spd = num(el, 'speed', 1);
		el.style.willChange = 'transform';
		var x = 0, y = 0, ang = Math.random() * TAU, v = (1.2 + Math.random()) * spd, vx = Math.cos(ang) * v, vy = Math.sin(ang) * v;
		observe(el, function () {
			var p = el.parentElement, er = el.getBoundingClientRect();
			if (!p) { return true; }
			var pr = p.getBoundingClientRect();
			var maxX = (pr.width - er.width) / 2, maxY = (pr.height - er.height) / 2;
			if (maxX < 4 || maxY < 4) { return true; }
			x += vx; y += vy;
			if (x > maxX) { x = maxX; vx = -vx; } else if (x < -maxX) { x = -maxX; vx = -vx; }
			if (y > maxY) { y = maxY; vy = -vy; } else if (y < -maxY) { y = -maxY; vy = -vy; }
			TF(el, 'translate(' + x.toFixed(1) + 'px,' + y.toFixed(1) + 'px)'); return true;
		});
	}

	/* ============================ Reactions ============================ */
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
	function jelly(el) { var i = num(el, 'intensity', 0.5), poke = reactScale(el, 'center', 'center'); bindTrigger(el, function () { poke(-i * 0.55, i * 0.55); }); }
	function squash(el) { var i = num(el, 'intensity', 0.5), poke = reactScale(el, 'center', 'bottom'); bindTrigger(el, function () { poke(i * 0.4, -i * 0.6); }); }
	function recoil(el) {
		var dist = num(el, 'distance', 14), x = 0, vx = 0, active = false;
		el.style.willChange = 'transform';
		function loop() { vx += (0 - x) * 0.25; vx *= 0.7; x += vx; TF(el, 'translate(' + x.toFixed(2) + 'px,0)'); if (Math.abs(x) < 0.4 && Math.abs(vx) < 0.05) { TF(el, 'translate(0,0)'); active = false; return false; } return true; }
		bindTrigger(el, function () { x = -dist; vx = 0; if (!active) { active = true; add(loop); } });
	}
	function shake(el) {
		var i = num(el, 'intensity', 0.5), amp = 0, ph = 0, active = false;
		el.style.willChange = 'transform';
		function loop() { ph += 0.85; amp *= 0.9; TF(el, 'translateX(' + (Math.sin(ph) * amp).toFixed(2) + 'px)'); if (amp < 0.3) { TF(el, 'translateX(0)'); active = false; return false; } return true; }
		bindTrigger(el, function () { amp = 9 * i; ph = 0; if (!active) { active = true; add(loop); } });
	}
	function spin(el) {
		var spd = num(el, 'speed', 1), ang = 0, av = 0, active = false;
		el.style.willChange = 'transform'; el.style.transformOrigin = 'center';
		function loop() { ang += av; av *= 0.94; TF(el, 'rotate(' + ang.toFixed(2) + 'deg)'); if (Math.abs(av) < 0.05) { active = false; return false; } return true; }
		bindTrigger(el, function () { av += 11 * spd; if (!active) { active = true; add(loop); } });
	}

	/* ============================ Wiring ============================ */
	var HANDLERS = {
		draggable: function (el) { drag(el, false); }, slingshot: function (el) { drag(el, true); },
		spring: function (el) { follow(el, num(el, 'strength', 0.25), num(el, 'stiffness', 0.12)); },
		attract: function (el) { follow(el, num(el, 'strength', 0.6), num(el, 'stiffness', 0.15)); },
		repel: repel, orbit_cursor: orbitCursor, rubber_band: rubberBand, tilt_inertia: tiltInertia,
		float: float, levitate: levitate, sway: sway, pendulum: pendulum, wobble: wobble, breathing: breathing, drift: drift, orbit: orbit,
		gravity: gravity, rise: rise, sag: sag, ragdoll: ragdoll, pop: pop, bounded: bounded,
		jelly: jelly, squash: squash, recoil: recoil, shake: shake, spin: spin
	};
	var POINTER = { spring: 1, attract: 1, repel: 1, orbit_cursor: 1, rubber_band: 1, tilt_inertia: 1 };

	function setup(el) {
		if (el.__upwPhys) { return; }
		el.__upwPhys = true;
		var fx = el.getAttribute('data-phys');
		if (!fx || !HANDLERS[fx] || REDUCE) { return; }        // every effect is motion → skip under reduce-motion
		if (cfg.disableMobile && isMobile) { return; }
		if (POINTER[fx] && isTouch) { return; }                // pointer-only
		el.classList.add('sc-phys-ready');
		HANDLERS[fx](el);
	}

	function init() { Array.prototype.forEach.call(document.querySelectorAll('[data-phys]'), setup); }
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
