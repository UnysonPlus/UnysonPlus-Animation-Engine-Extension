/**
 * UnysonPlus Animation Engine — Kinetic Scenes preset runtime.
 *
 * Plays a Kinetic Scenes preset on each [data-upw-kp] element with the Kinetic Scene element's own player
 * (window.UnysonPlusScene.playPreset), loaded once on demand. Each element is set to the preset's start (hidden, for
 * an entrance) straight away, then plays on its trigger: in view, on load, on hover or on click; Replay plays it
 * again each time. Visitors who ask for reduced motion see the element as it is, untouched.
 */
(function () {
	'use strict';
	if (typeof window === 'undefined' || typeof document === 'undefined') return;
	var cfg = window.upwKineticPresetCfg || {};

	function reducedMotion() {
		return cfg.reducedMotion !== false && (window.upwReduceMotion ? window.upwReduceMotion() : (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches));
	}

	var loading = null;
	function player() {
		if (window.UnysonPlusScene && window.UnysonPlusScene.playPreset) return Promise.resolve(window.UnysonPlusScene);
		if (loading) return loading;
		loading = new Promise(function (ok, bad) {
			var s = document.createElement('script');
			s.src = cfg.player;
			s.async = true;
			s.setAttribute('data-auto-mount', 'false');
			s.onload = function () { window.UnysonPlusScene && window.UnysonPlusScene.playPreset ? ok(window.UnysonPlusScene) : bad(new Error('playPreset missing')); };
			s.onerror = bad;
			document.head.appendChild(s);
		});
		return loading;
	}

	function setup(api, el) {
		if (el.__upwKp) return;
		var c;
		try { c = JSON.parse(el.getAttribute('data-upw-kp') || '{}'); } catch (e) { return; }
		if (!c.p) return;
		var run;
		try {
			run = api.playPreset(el, c.p, { duration: c.d || undefined, delay: c.w || 0, params: c.s || {}, autoplay: false });
		} catch (e) {
			if (window.console) console.warn('[Kinetic Scenes preset]', e.message);
			return;
		}
		el.__upwKp = run;
		var played = false;
		var play = function () { if (played && !c.r) return; played = true; run.play(); };
		if (c.t === 'load') { play(); return; }
		if (c.t === 'hover') { el.addEventListener('mouseenter', function () { played = false; play(); }); el.addEventListener('focusin', function () { played = false; play(); }); return; }
		if (c.t === 'click') { el.addEventListener('click', function () { played = false; play(); }); return; }
		// In view: a quarter on screen.
		if (!('IntersectionObserver' in window)) { play(); return; }
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (en) {
				if (en.isIntersecting) { play(); if (!c.r) io.unobserve(el); }
				else if (c.r && played) { run.reset(); played = false; }
			});
		}, { threshold: 0.25 });
		io.observe(el);
	}

	function init() {
		var els = document.querySelectorAll('[data-upw-kp]');
		if (!els.length || reducedMotion()) return;
		player().then(function (api) {
			Array.prototype.forEach.call(document.querySelectorAll('[data-upw-kp]'), function (el) { setup(api, el); });
		}, function () { /* the player could not load: elements stay as they are */ });
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
	else init();
	// The page builder's live preview re-renders elements: set up new ones.
	document.addEventListener('fw:builder:preview:updated', init);
})();
