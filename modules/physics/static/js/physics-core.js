/**
 * Animation Engine — Physics Effects core (shared integrator + dispatch).
 *
 * Loads FIRST; each per-effect partial (static/js/effects/<effect>.js) loads after it, aliases
 * these helpers at load time, and registers window.upwPhys[<effect>] = fn(el). This core owns the
 * single shared RAF ticker, the spring/verlet helpers, the on-screen/entrance observers, the
 * shared pointer helpers (drag / follow / reactScale / bindTrigger), and the dispatch. Only the
 * effects a page uses are enqueued, so no page pays for the other ~26 physics effects. No deps.
 */
(function () {
	'use strict';

	var cfg = window.upwPhysicsCfg || {};
	var mql = window.matchMedia || function () { return { matches: false }; };
	var REDUCE = cfg.reducedMotion !== false && mql('(prefers-reduced-motion: reduce)').matches;
	var isTouch = mql('(hover: none), (pointer: coarse)').matches;
	var isMobile = (window.innerWidth || 1024) < 768;

	/* ---- Shared frame scheduler (window.upwAnimRaf): one rAF loop for every engine module,
	   paused while the tab is hidden. Enqueued as a dependency by the loader (needs_raf). ---- */
	var RAF = window.upwAnimRaf;
	function add(fn) { if (RAF) { RAF.add(fn); } }
	function remove(fn) { if (RAF) { RAF.remove(fn); } }

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

	/* ---- Shared registry + API surface the per-effect partials use ---- */
	var PH = window.upwPhys || (window.upwPhys = {});
	window.upwPhysApi = {
		cfg: cfg, num: num, TF: TF, add: add, remove: remove, observe: observe, entrance: entrance,
		springTo: springTo, TAU: TAU, follow: follow, drag: drag, reactScale: reactScale, bindTrigger: bindTrigger
	};

	// Pointer-only effects — skipped on touch screens.
	var POINTER = { spring: 1, attract: 1, repel: 1, orbit_cursor: 1, rubber_band: 1, tilt_inertia: 1 };

	function setup(el) {
		if (el.__upwPhys) { return; }
		var fx = el.getAttribute('data-phys');
		if (!fx) { return; }
		var fn = PH[fx];
		if (!fn) { return; }                                   // partial not registered yet — rescan-safe
		el.__upwPhys = true;
		if (REDUCE) { return; }                                // every effect is motion → skip under reduce-motion
		if (cfg.disableMobile && isMobile) { return; }
		if (POINTER[fx] && isTouch) { return; }                // pointer-only
		el.classList.add('sc-phys-ready');
		try { fn(el); } catch (e) { /* never break the page */ }
	}

	function init() { Array.prototype.forEach.call(document.querySelectorAll('[data-phys]'), setup); }
	// Defer so the per-effect partials (which load AFTER this core) register first.
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { setTimeout(init, 0); }
	window.upwPhysRescan = init;
})();
