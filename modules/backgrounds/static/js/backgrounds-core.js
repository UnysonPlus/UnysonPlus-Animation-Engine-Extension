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
		// Measure the host BEFORE the canvas is put inside it. A geometry read that follows a DOM
		// write forces the browser to lay the whole page out again before it can answer, and reading
		// the host immediately after inserting a child into it is exactly that. Measured on a real
		// page under 4x CPU throttling this one read cost 9.9ms - the largest single forced-layout
		// item on that page. Taking the measurement first costs nothing and gives the same number:
		// inserting an absolutely-positioned background layer does not change the host's own box.
		var box = host.getBoundingClientRect();
		var cv = document.createElement('canvas'); cv.className = 'upw-bg-layer upw-bg-canvas'; cv.setAttribute('aria-hidden', 'true');
		host.insertBefore(cv, host.firstChild);
		var ctx = cv.getContext('2d'), dpr = Math.min(2, window.devicePixelRatio || 1);
		var L = { cv: cv, ctx: ctx, w: 1, h: 1, seed: null };
		// `r` is supplied whenever a box is already to hand - the first measurement above, or the one
		// ResizeObserver hands us, which the browser produced during its own layout pass. Only the
		// plain resize fallback has to measure for itself.
		function size(r) {
			if (!r) { r = host.getBoundingClientRect(); }
			L.w = Math.max(1, r.width); L.h = Math.max(1, r.height);
			cv.width = L.w * dpr; cv.height = L.h * dpr; cv.style.width = L.w + 'px'; cv.style.height = L.h + 'px';
			ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
			if (L.seed) { L.seed(); }
		}
		size(box);
		if ('ResizeObserver' in window) {
			new ResizeObserver(function (entries) {
				size(entries && entries[0] ? entries[0].contentRect : null);
			}).observe(host);
		} else {
			window.addEventListener('resize', function () { size(); }, { passive: true });
		}
		return L;
	}

	// Subscribe to the shared frame scheduler (window.upwAnimRaf): one rAF loop for all engine
	// modules that pauses while the tab is hidden. The callback also no-ops while its host is
	// off-screen. Enqueued as a dependency by the loader (needs_raf).
	var RAF = window.upwAnimRaf;
	function loop(host, cb) {
		var visible = true, io = null;
		if ('IntersectionObserver' in window) {
			io = new IntersectionObserver(function (e) { visible = e[0].isIntersecting; }, { threshold: 0, rootMargin: '120px' });
			io.observe(host);
		}
		// Self-cleaning: once the host leaves the DOM (a builder re-render), disconnect the observer
		// and return false so the shared scheduler drops this callback — no leaked RAF work or IO.
		if (RAF) {
			RAF.add(function (t) {
				if (!document.documentElement.contains(host)) { if (io) { io.disconnect(); } return false; }
				if (visible) { cb(t); }
			});
		}
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

	// Expand a `[data-bg-layers]` host (multiple stacked background effects) into one absolutely-positioned
	// child `[data-bg]` layer per effect, back-to-front. Each layer is then rendered by the normal loop below,
	// so the engine needs no per-effect awareness of stacking. Idempotent.
	function expandStacks() {
		var stacks = document.querySelectorAll('[data-bg-layers]');
		Array.prototype.forEach.call(stacks, function (host) {
			if (host._upwBgStack) { return; }
			host._upwBgStack = true;
			var layers;
			try { layers = JSON.parse(host.getAttribute('data-bg-layers')); } catch (e) { return; }
			if (!Array.isArray(layers) || !layers.length) { return; }
			if (getComputedStyle(host).position === 'static') { host.style.position = 'relative'; }
			layers.forEach(function (L) {
				if (!L || !L.effect) { return; }
				var d = document.createElement('div');
				d.className = 'sc-bg-layer';
				d.setAttribute('data-bg', L.effect);
				if (L.attrs && typeof L.attrs === 'object') {
					for (var k in L.attrs) { if (Object.prototype.hasOwnProperty.call(L.attrs, k)) { d.setAttribute(k, L.attrs[k]); } }
				}
				d.style.position = 'absolute'; d.style.left = '0'; d.style.top = '0'; d.style.right = '0'; d.style.bottom = '0'; d.style.zIndex = '0';
				if (L.style) { d.setAttribute('style', d.getAttribute('style') + ';' + L.style); }
				host.insertBefore(d, host.firstChild);
			});
			// Lift the host's REAL content above the layer stack (the layer divs stay at z-index 0).
			Array.prototype.forEach.call(host.children, function (ch) {
				if (ch.classList && ch.classList.contains('sc-bg-layer')) { return; }
				if (getComputedStyle(ch).position === 'static') { ch.style.position = 'relative'; }
				ch.style.zIndex = ch.style.zIndex || '1';
			});
		});
	}

	function init() {
		expandStacks();
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
