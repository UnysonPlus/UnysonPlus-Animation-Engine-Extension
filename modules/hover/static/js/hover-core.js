/**
 * Animation Engine — Hover Interactions core dispatcher.
 *
 * The per-effect runtimes register themselves into window.upwHoverFx as
 *   upwHoverFx[<effect>] = { pointer:bool, reduceSkip:bool, run:function(el,cfg){…} }
 * (each ships as its own on-demand partial under static/js/effects/, loaded ONLY
 * when that effect is used on the page). This core reads every [data-hover] element
 * and runs the registered effects for it, applying the shared gating policy
 * (reduced-motion, touch / mobile). CSS-only effects have no registry entry and are
 * simply skipped here — their styling comes from their CSS partial. No dependencies.
 */
(function () {
	'use strict';

	var cfg = window.upwHoverCfg || {};
	var mql = window.matchMedia || function () { return { matches: false }; };
	var reduceMotion = cfg.reducedMotion !== false && mql('(prefers-reduced-motion: reduce)').matches;
	var isTouch = mql('(hover: none), (pointer: coarse)').matches;
	var isMobile = (window.innerWidth || 1024) < 768;
	var FX = window.upwHoverFx || (window.upwHoverFx = {});

	function setup(el) {
		if (el.__upwHover) { return; }
		el.__upwHover = true;

		// data-hover is a space-separated list of effects (multi-instance) — run each
		// registered one. Effects without a registry entry are CSS-only (no JS needed).
		var list = (el.getAttribute('data-hover') || '').split(/\s+/).filter(Boolean);
		if (!list.length) { return; }

		list.forEach(function (fx) {
			var def = FX[fx];
			if (!def || typeof def.run !== 'function') { return; }
			if (def.pointer && (isTouch || (cfg.disableMobile && isMobile))) { return; }
			if (def.reduceSkip && reduceMotion) { return; }
			try { def.run(el, cfg); } catch (e) { /* one effect must not break the rest */ }
		});
	}

	function init() {
		var els = document.querySelectorAll('[data-hover]');
		for (var i = 0; i < els.length; i++) { setup(els[i]); }
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	// Re-scan when the builder re-renders or content is injected dynamically.
	window.upwHoverRescan = init;
})();
