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

	/* ---- Shared registry + API surface the per-effect partials use ---- */
	var PH = window.upwPhys || (window.upwPhys = {});
	window.upwPhysApi = {
		cfg: cfg, num: num, TF: TF, add: add, remove: remove, observe: observe, entrance: entrance,
		springTo: springTo, TAU: TAU
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
