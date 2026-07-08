/**
 * Animation Engine — Scrollytelling core.
 *
 * For each `.upw-story` Section: one column is pinned as the MEDIA panel (its direct content items
 * become stacked layers) while the other column's STEPS scroll past. As each step crosses the
 * trigger line, the matching media layer (by index) is activated — the per-style CSS/JS does the
 * visual (crossfade / slide / zoom / wipe / blur / ken-burns / parallax / pixelate).
 *
 * Per-style runtimes (scrub / canvas styles only) self-register into window.upwStoryFx as
 *   upwStoryFx[<style>] = { scrub?:bool, onActivate?(layer,i,ctx), onProgress?(section,p,ctx) }
 * and load as their own on-demand partial. CSS-only styles need no entry.
 */
(function () {
	'use strict';

	var cfg = window.upwStoryCfg || {};
	var mql = window.matchMedia || function () { return { matches: false }; };
	var reduce = cfg.reducedMotion !== false && mql('(prefers-reduced-motion: reduce)').matches;
	var isMobile = (window.innerWidth || 1024) < 768;
	var FX = window.upwStoryFx || (window.upwStoryFx = {});

	// Walk down single-child wrappers (fw-col > inner > ...) to the level holding the real items.
	function contentKids(node) {
		var n = node;
		while (n && n.children && n.children.length === 1 && n.children[0].nodeType === 1) {
			n = n.children[0];
		}
		return n ? Array.prototype.filter.call(n.children, function (c) { return c.nodeType === 1; }) : [];
	}

	function setup(el) {
		if (el.__storyReady) { return; }
		el.__storyReady = true;

		var style = el.getAttribute('data-story-style') || 'crossfade';
		var side  = el.getAttribute('data-story-side') || 'left';
		var at    = parseFloat(el.getAttribute('data-story-at')) || 50;
		var prog  = el.getAttribute('data-story-progress') || 'dots';

		// Reduced motion / mobile-disabled: leave the Section as plain columns (linear fallback).
		if (reduce || (cfg.disableMobile && isMobile)) { return; }

		var row = el.querySelector('.fw-row') || el.querySelector('[class*="fw-row"]');
		if (!row) { return; }
		var cols = Array.prototype.filter.call(row.children, function (c) {
			return c.nodeType === 1 && /(^|\s)fw-col/.test(c.className);
		});
		if (cols.length < 2) { return; } // need a media column + a steps column

		var mediaCol = (side === 'right') ? cols[cols.length - 1] : cols[0];
		var stepCols = cols.filter(function (c) { return c !== mediaCol; });

		var layers = contentKids(mediaCol);
		var steps  = [];
		stepCols.forEach(function (c) { steps = steps.concat(contentKids(c)); });
		if (!layers.length || !steps.length) { return; }

		// Set CSS custom properties the base + style CSS consume.
		el.style.setProperty('--story-top', (parseFloat(el.getAttribute('data-story-top')) || 0) + 'px');
		el.style.setProperty('--story-h', (parseFloat(el.getAttribute('data-story-h')) || 100) + 'vh');
		el.style.setProperty('--story-trans', (parseFloat(el.getAttribute('data-story-trans')) || 0.6) + 's');
		el.style.setProperty('--story-intensity', (parseFloat(el.getAttribute('data-story-intensity')) || 0.5));

		// Tag the DOM so the CSS pins the media, stacks the layers and paces the steps.
		mediaCol.classList.add('upw-story-media');
		if (layers[0] && layers[0].parentNode) { layers[0].parentNode.classList.add('upw-story-layers'); }
		layers.forEach(function (l) { l.classList.add('upw-story-layer'); });
		stepCols.forEach(function (c) { c.classList.add('upw-story-steps'); });
		steps.forEach(function (s) { s.classList.add('upw-story-step'); });

		var def = FX[style] || null;
		var ctx = { intensity: parseFloat(el.getAttribute('data-story-intensity')) || 0.5, layers: layers, steps: steps, media: mediaCol, section: el };
		// Some scrub styles (Frame Sequence, Horizontal Track) drive the media from continuous
		// progress rather than the discrete step, so the core skips the per-step layer toggle for them.
		var progressMedia = !!(def && def.mediaMode === 'progress');
		var current = -1;

		function activate(i) {
			i = Math.max(0, Math.min(steps.length - 1, i));
			if (i === current) { return; }
			current = i;
			// Map the active step to a media layer proportionally, so it stays aligned even when the
			// step count differs from the layer count (e.g. an atom that renders several sibling
			// elements per caption). Equal counts collapse to a clean 1:1 mapping.
			var mediaIdx = (steps.length <= layers.length)
				? Math.min(i, layers.length - 1)
				: Math.min(layers.length - 1, Math.floor(i * layers.length / steps.length));
			if (!progressMedia) {
				layers.forEach(function (l, k) { l.classList.toggle('is-active', k === mediaIdx); });
			}
			steps.forEach(function (s, k) { s.classList.toggle('is-active', k === i); });
			if (rail) { updateRail(i); }
			if (def && typeof def.onActivate === 'function') {
				try { def.onActivate(layers[mediaIdx], i, ctx); } catch (e) {}
			}
		}

		// Progress rail (optional).
		var rail = null, dots = [];
		function updateRail(i) {
			if (rail.__isBar) { rail.firstChild.style.transform = 'scaleY(' + ((i) / Math.max(1, steps.length - 1)) + ')'; return; }
			dots.forEach(function (d, k) { d.classList.toggle('is-active', k === i); });
		}
		if (prog !== 'none') {
			rail = document.createElement('div');
			rail.className = 'upw-story-progress upw-story-progress--' + (prog === 'bar' ? 'bar' : 'dots');
			rail.setAttribute('aria-hidden', 'true');
			if (prog === 'bar') {
				rail.__isBar = true;
				var fill = document.createElement('span'); fill.className = 'upw-story-progress__fill';
				rail.appendChild(fill);
			} else {
				steps.forEach(function (s, k) {
					var d = document.createElement('button');
					d.type = 'button'; d.className = 'upw-story-dot';
					d.addEventListener('click', function () { scrollToStep(s); });
					rail.appendChild(d); dots.push(d);
				});
			}
			el.appendChild(rail);
		}

		function scrollToStep(s) {
			var top = parseFloat(el.getAttribute('data-story-top')) || 0;
			if (window.__upwLenis && typeof window.__upwLenis.scrollTo === 'function') {
				window.__upwLenis.scrollTo(s, { offset: -top });
			} else {
				s.scrollIntoView({ behavior: 'smooth', block: 'center' });
			}
		}

		// Step detection: a thin trigger band at `at`% of the viewport.
		var band = '-' + at + '% 0px -' + (100 - at) + '% 0px';
		if ('IntersectionObserver' in window) {
			var io = new IntersectionObserver(function (entries) {
				entries.forEach(function (en) {
					if (en.isIntersecting) { activate(steps.indexOf(en.target)); }
				});
			}, { rootMargin: band, threshold: 0 });
			steps.forEach(function (s) { io.observe(s); });
		}
		activate(0);

		// Scrub styles: run a rAF loop while the Section is in view, feeding 0..1 progress.
		if (def && def.scrub && typeof def.onProgress === 'function') {
			var running = false, raf = 0, inView = true;
			function frame() {
				if (!running) { return; }
				raf = requestAnimationFrame(frame);
				var r = el.getBoundingClientRect();
				var p = Math.max(0, Math.min(1, (window.innerHeight - r.top) / ((r.height + window.innerHeight) || 1)));
				try { def.onProgress(el, p, ctx); } catch (e) {}
			}
			function run() { if (!running && inView) { running = true; raf = requestAnimationFrame(frame); } }
			function stop() { running = false; if (raf) { cancelAnimationFrame(raf); raf = 0; } }
			if ('IntersectionObserver' in window) {
				new IntersectionObserver(function (es) {
					inView = es[0].isIntersecting; if (inView) { run(); } else { stop(); }
				}, { threshold: 0 }).observe(el);
			} else { run(); }
		}
	}

	function init() {
		var els = document.querySelectorAll('.upw-story');
		for (var i = 0; i < els.length; i++) { setup(els[i]); }
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
	window.upwStoryRescan = init;
})();
