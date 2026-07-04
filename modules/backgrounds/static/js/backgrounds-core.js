/**
 * Animation Engine — Animated Backgrounds core (shared canvas engine + dispatch).
 *
 * Loads FIRST; each per-style partial (static/js/effects/<style>.js) loads after it and
 * aliases these helpers at load time, then registers window.upwBg[<style>] = fn(host). This
 * core exposes the shared engine on window.upwBgApi and runs the dispatch once the DOM is
 * ready (by then all used-style partials have registered). Only the styles a page actually
 * uses are enqueued, so no page pays for the other ~34 backgrounds. No dependencies.
 */
(function () {
	'use strict';

	var cfg = window.upwBgCfg || {};
	var mql = window.matchMedia || function () { return { matches: false }; };
	var reduce = cfg.reducedMotion && mql('(prefers-reduced-motion: reduce)').matches;

	function cssLayer(host, cls) {
		var d = document.createElement('div'); d.className = 'upw-bg-layer ' + cls; d.setAttribute('aria-hidden', 'true');
		host.insertBefore(d, host.firstChild); return d;
	}

	function canvasLayer(host) {
		var cv = document.createElement('canvas'); cv.className = 'upw-bg-layer upw-bg-canvas'; cv.setAttribute('aria-hidden', 'true');
		host.insertBefore(cv, host.firstChild);
		var ctx = cv.getContext('2d'), dpr = Math.min(2, window.devicePixelRatio || 1);
		var L = { cv: cv, ctx: ctx, w: 1, h: 1, seed: null };
		function size() {
			var r = host.getBoundingClientRect();
			L.w = Math.max(1, r.width); L.h = Math.max(1, r.height);
			cv.width = L.w * dpr; cv.height = L.h * dpr; cv.style.width = L.w + 'px'; cv.style.height = L.h + 'px';
			ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
			if (L.seed) { L.seed(); }
		}
		size();
		if ('ResizeObserver' in window) { new ResizeObserver(size).observe(host); } else { window.addEventListener('resize', size, { passive: true }); }
		return L;
	}

	// Subscribe to the shared frame scheduler (window.upwAnimRaf): one rAF loop for all engine
	// modules that pauses while the tab is hidden. The callback also no-ops while its host is
	// off-screen. Enqueued as a dependency by the loader (needs_raf).
	var RAF = window.upwAnimRaf;
	function loop(host, cb) {
		var visible = true;
		if ('IntersectionObserver' in window) {
			new IntersectionObserver(function (e) { visible = e[0].isIntersecting; }, { threshold: 0, rootMargin: '120px' }).observe(host);
		}
		if (RAF) { RAF.add(function (t) { if (visible) { cb(t); } }); }
	}

	function num(host, attr, def) { var v = parseFloat(host.getAttribute(attr)); return isNaN(v) ? def : v; }
	function areaCount(density, w, h) { return Math.max(6, Math.min(400, Math.round(density * (w * h) / (1280 * 720)))); }
	function rnd(a, b) { return a + (b - a) * Math.random(); }

	// Shared registry + API surface the per-style partials use.
	var BG = window.upwBg || (window.upwBg = {});
	window.upwBgApi = {
		cfg: cfg, reduce: reduce,
		cssLayer: cssLayer, canvasLayer: canvasLayer, loop: loop, num: num, areaCount: areaCount, rnd: rnd
	};

	function init() {
		var nodes = document.querySelectorAll('[data-bg]');
		Array.prototype.forEach.call(nodes, function (host) {
			if (host._upwBg) { return; }
			var fn = BG[host.getAttribute('data-bg')];
			if (!fn) { return; } // style partial not registered yet — leave unmarked so a rescan can retry
			host._upwBg = true;
			if (getComputedStyle(host).position === 'static') { host.style.position = 'relative'; }
			host.classList.add('upw-bg-host');
			try { fn(host); } catch (e) { /* never break the page */ }
			// Lift the real content above the layer.
			Array.prototype.forEach.call(host.children, function (ch) {
				if (ch.classList.contains('upw-bg-layer')) { return; }
				if (getComputedStyle(ch).position === 'static') { ch.style.position = 'relative'; }
				ch.style.zIndex = '1';
			});
		});
	}

	// Defer so the per-style partials (which load AFTER this core) register first.
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); }
	else { setTimeout(init, 0); }
	window.upwBgRescan = init;
})();
