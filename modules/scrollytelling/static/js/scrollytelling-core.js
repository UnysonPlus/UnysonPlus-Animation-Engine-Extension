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

	var tracked = [];

	/* ---- Stage backdrop renderers (sequence canvas / scrubbed video / fixed image) ---- */
	function buildBackdrop(el, bcfg, teardown) {
		var wrap = document.createElement('div');
		wrap.className = 'upw-story-backdrop';
		wrap.setAttribute('aria-hidden', 'true');
		var api = { update: function () {} };

		var frameList = (bcfg.type === 'sequence' && bcfg.frames && bcfg.frames.length > 1) ? bcfg.frames : null;
		if (bcfg.type === 'sequence' && (frameList || bcfg.pattern)) {
			var canvas = document.createElement('canvas');
			wrap.appendChild(canvas);
			var ctx2d = canvas.getContext('2d');
			var count = frameList ? frameList.length : Math.max(2, bcfg.count | 0);
			var start = bcfg.start | 0;
			var pad = bcfg.pad | 0;
			var frames = new Array(count);
			var current = -1, pending = -1;
			function src(i) {
				if (frameList) { return frameList[i]; }
				var n = String(start + i);
				while (pad && n.length < pad) { n = '0' + n; }
				return bcfg.pattern.replace('%d', n);
			}
			function draw(img) {
				var w = canvas.clientWidth || wrap.clientWidth, h = canvas.clientHeight || wrap.clientHeight;
				if (!w || !h || !img || !img.naturalWidth) { return; }
				var dpr = Math.min(2, window.devicePixelRatio || 1);
				if (canvas.width !== (w * dpr) | 0) { canvas.width = (w * dpr) | 0; canvas.height = (h * dpr) | 0; }
				var cw = canvas.width, ch = canvas.height;
				var s = (bcfg.fit === 'contain' ? Math.min : Math.max)(cw / img.naturalWidth, ch / img.naturalHeight);
				var dw = img.naturalWidth * s, dh = img.naturalHeight * s;
				ctx2d.clearRect(0, 0, cw, ch);
				ctx2d.drawImage(img, (cw - dw) / 2, (ch - dh) / 2, dw, dh);
			}
			function load(i, cb) {
				if (frames[i]) { if (frames[i].complete) { cb && cb(frames[i]); } return; }
				var img = new Image();
				img.decoding = 'async';
				img.onload = function () { cb && cb(img); };
				img.src = src(i);
				frames[i] = img;
			}
			// Warm the sequence in coarse passes so scrubbing has nearby frames early.
			var strides = [Math.max(1, Math.floor(count / 12)), Math.max(1, Math.floor(count / 48)), 1];
			(function warm(si, i) {
				if (si >= strides.length) { return; }
				var st = strides[si];
				function step(j) {
					if (j >= count) { warm(si + 1, 0); return; }
					load(j, null);
					setTimeout(function () { step(j + st); }, 12);
				}
				step(i);
			})(0, 0);
			api.update = function (p) {
				var idx = Math.max(0, Math.min(count - 1, Math.round(p * (count - 1))));
				if (idx === current) { return; }
				pending = idx;
				load(idx, function (img) {
					if (pending !== idx) { return; }
					current = idx;
					draw(img);
				});
				if (frames[idx] && frames[idx].complete && frames[idx].naturalWidth) { current = idx; draw(frames[idx]); }
			};
			var onResize = function () { current = -1; api.update(lastP); };
			var lastP = 0;
			var origUpdate = api.update;
			api.update = function (p) { lastP = p; origUpdate(p); };
			window.addEventListener('resize', onResize);
			teardown.push(function () { window.removeEventListener('resize', onResize); });
		} else if (bcfg.type === 'video' && bcfg.url) {
			var video = document.createElement('video');
			video.muted = true; video.playsInline = true; video.preload = 'auto';
			video.src = bcfg.url;
			video.style.objectFit = bcfg.fit || 'cover';
			wrap.appendChild(video);
			var seekReady = false;
			video.addEventListener('loadedmetadata', function () { seekReady = true; });
			var lastT = -1;
			api.update = function (p) {
				if (!seekReady || !isFinite(video.duration)) { return; }
				var t = p * video.duration;
				if (Math.abs(t - lastT) < 0.033) { return; } // ~1 frame @30fps
				lastT = t;
				try { video.currentTime = t; } catch (e) {}
			};
		} else if (bcfg.type === 'image' && bcfg.url) {
			var im = document.createElement('img');
			im.src = bcfg.url; im.alt = '';
			im.style.objectFit = bcfg.fit || 'cover';
			wrap.appendChild(im);
		}
		return { el: wrap, update: api.update };
	}

	/* ---- Full-screen Stage layout: every column is a scene; backdrop scrubs behind ---- */
	function setupStage(el, teardown) {
		var prog = el.getAttribute('data-story-progress') || 'dots';
		var sceneLen = parseFloat(el.getAttribute('data-story-scenelen')) || 1;
		var row = el.querySelector('.fw-row') || el.querySelector('[class*="fw-row"]');
		if (!row) { return false; }
		var scenes = Array.prototype.filter.call(row.children, function (c) {
			return c.nodeType === 1 && /(^|\s)fw-col/.test(c.className);
		});
		if (scenes.length < 1) { return false; }

		el.classList.add('upw-story--stage');
		el.style.setProperty('--story-top', (parseFloat(el.getAttribute('data-story-top')) || 0) + 'px');
		el.style.setProperty('--story-h', (parseFloat(el.getAttribute('data-story-h')) || 100) + 'vh');
		el.style.setProperty('--story-trans', (parseFloat(el.getAttribute('data-story-trans')) || 0.6) + 's');
		el.style.setProperty('--story-intensity', (parseFloat(el.getAttribute('data-story-intensity')) || 0.5));
		// Section height = scroll runway: one viewport for the pin + sceneLen per scene.
		el.style.height = 'calc(' + (scenes.length * sceneLen * 100) + 'vh + var(--story-h, 100vh))';

		row.classList.add('upw-story-stage');
		scenes.forEach(function (s) { s.classList.add('upw-story-layer', 'upw-story-scene'); });
		// Group runs of 2+ consecutive CTA children (a/button) into a side-by-side row.
		scenes.forEach(function (s) {
			var run = [];
			var flush = function () {
				if (run.length > 1) {
					var wrap = document.createElement('div');
					wrap.className = 'upw-story-cta-row';
					run[0].parentNode.insertBefore(wrap, run[0]);
					run.forEach(function (n) { wrap.appendChild(n); });
				}
				run = [];
			};
			Array.prototype.slice.call(s.children).forEach(function (c) {
				if (c.nodeType === 1 && /^(a|button)$/i.test(c.tagName)) { run.push(c); } else { flush(); }
			});
			flush();
		});
		// The sticky stage needs its whole ancestor chain (container/inner wrappers)
		// to span the section's runway height, or sticky has no room to travel.
		for (var anc = row.parentElement; anc && anc !== el; anc = anc.parentElement) {
			anc.style.height = '100%';
		}

		// Backdrop (optional).
		var backdrop = null;
		var braw = el.getAttribute('data-story-backdrop');
		if (braw) {
			try { backdrop = buildBackdrop(el, JSON.parse(window.atob(braw)), teardown); } catch (e) { backdrop = null; }
			if (backdrop) { row.insertBefore(backdrop.el, row.firstChild); }
		}

		// Progress rail (dots = scenes; click scrolls to that scene's segment).
		var rail = null, dots = [];
		if (prog !== 'none') {
			rail = document.createElement('div');
			rail.className = 'upw-story-progress upw-story-progress--' + (prog === 'bar' ? 'bar' : 'dots');
			rail.setAttribute('aria-hidden', 'true');
			if (prog === 'bar') {
				rail.__isBar = true;
				var fill = document.createElement('span'); fill.className = 'upw-story-progress__fill';
				rail.appendChild(fill);
			} else {
				scenes.forEach(function (s, k) {
					var d = document.createElement('button');
					d.type = 'button'; d.className = 'upw-story-dot';
					d.addEventListener('click', function () {
						var vh = window.innerHeight || 1;
						var y = el.getBoundingClientRect().top + window.pageYOffset + k * sceneLen * vh + 2;
						if (window.__upwLenis && typeof window.__upwLenis.scrollTo === 'function') { window.__upwLenis.scrollTo(y); }
						else { window.scrollTo({ top: y, behavior: 'smooth' }); }
					});
					rail.appendChild(d); dots.push(d);
				});
			}
			row.appendChild(rail);
		}

		var style = el.getAttribute('data-story-style') || 'crossfade';
		var def = FX[style] || null;
		var ctx = { intensity: parseFloat(el.getAttribute('data-story-intensity')) || 0.5, layers: scenes, steps: scenes, media: row, section: el };
		var current = -1;
		function activate(i) {
			i = Math.max(0, Math.min(scenes.length - 1, i));
			if (i === current) { return; }
			current = i;
			scenes.forEach(function (s, k) {
				s.classList.toggle('is-active', k === i);
				s.classList.toggle('is-past', k < i);
			});
			if (rail && !rail.__isBar) { dots.forEach(function (d, k) { d.classList.toggle('is-active', k === i); }); }
			if (def && typeof def.onActivate === 'function') {
				try { def.onActivate(scenes[i], i, ctx); } catch (e) {}
			}
		}

		// Exit hand-off: near the end, fade the pinned stage out so the Section background (which the
		// author sets to the next section's colour) shows through — the ride dissolves into what
		// follows instead of hard-cutting when the pin releases.
		var exitFade = el.getAttribute('data-story-exit') === 'fade';
		var exitAt = parseFloat(el.getAttribute('data-story-exit-at'));
		if (isNaN(exitAt)) { exitAt = 0.78; }

		// rAF progress loop while the section is in view: scene switching + backdrop scrub.
		var running = false, raf = 0, inView = true, lastFade = -1;
		function frame() {
			if (!running) { return; }
			raf = requestAnimationFrame(frame);
			var r = el.getBoundingClientRect();
			var vh = window.innerHeight || 1;
			var runway = Math.max(1, r.height - vh);
			var p = Math.max(0, Math.min(1, -r.top / runway));
			activate(Math.min(scenes.length - 1, Math.floor(p * scenes.length)));
			if (rail && rail.__isBar) { rail.firstChild.style.transform = 'scaleY(' + p + ')'; }
			if (backdrop) { backdrop.update(p); }
			if (def && def.scrub && typeof def.onProgress === 'function') {
				try { def.onProgress(el, p, ctx); } catch (e) {}
			}
			if (exitFade) {
				// opacity eases from 1 at exitAt to 0 at p=1 (smoothstep for a soft dissolve).
				var f = p <= exitAt ? 0 : (p - exitAt) / (1 - exitAt);
				f = f * f * (3 - 2 * f);
				if (f !== lastFade) { row.style.opacity = String(1 - f); lastFade = f; }
			}
		}
		function run() { if (!running && inView) { running = true; raf = requestAnimationFrame(frame); } }
		function stop() { running = false; if (raf) { cancelAnimationFrame(raf); raf = 0; } }
		if ('IntersectionObserver' in window) {
			var io = new IntersectionObserver(function (es) {
				inView = es[0].isIntersecting; if (inView) { run(); } else { stop(); }
			}, { threshold: 0 });
			io.observe(el);
			teardown.push(function () { stop(); io.disconnect(); });
		} else { run(); }
		activate(0);
		if (backdrop) { backdrop.update(0); }
		return true;
	}

	function setup(el) {
		if (el.__storyReady) { return; }
		el.__storyReady = true;
		var teardown = [];

		var style = el.getAttribute('data-story-style') || 'crossfade';
		var side  = el.getAttribute('data-story-side') || 'left';
		var at    = parseFloat(el.getAttribute('data-story-at')) || 50;
		var prog  = el.getAttribute('data-story-progress') || 'dots';

		// Reduced motion / mobile-disabled: leave the Section as plain columns (linear fallback).
		if (reduce || (cfg.disableMobile && isMobile)) { return; }

		// Full-screen Stage layout branches off before the panel/steps wiring.
		if (el.getAttribute('data-story-layout') === 'stage') {
			if (setupStage(el, teardown)) {
				el.__storyTeardown = function () {
					teardown.forEach(function (f) { try { f(); } catch (e) {} });
					(el.__upwStoryCleanup || []).forEach(function (f) { try { f(); } catch (e) {} });
					el.__upwStoryCleanup = [];
				};
				tracked.push(el);
			}
			return;
		}

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
			teardown.push(function () { io.disconnect(); });
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
				var scrubIo = new IntersectionObserver(function (es) {
					inView = es[0].isIntersecting; if (inView) { run(); } else { stop(); }
				}, { threshold: 0 });
				scrubIo.observe(el);
				teardown.push(function () { stop(); scrubIo.disconnect(); });
			} else { run(); }
		}

		// Teardown for builder rescans: disconnect observers, stop the loop, and run any per-style
		// cleanup (e.g. liquid.js disposes its WebGL context + canvas).
		el.__storyTeardown = function () {
			teardown.forEach(function (f) { try { f(); } catch (e) {} });
			(el.__upwStoryCleanup || []).forEach(function (f) { try { f(); } catch (e) {} });
			el.__upwStoryCleanup = [];
		};
		tracked.push(el);
	}

	function init() {
		// Dispose instances whose section left the DOM (a builder re-render replaces the node).
		for (var t = tracked.length - 1; t >= 0; t--) {
			if (!document.documentElement.contains(tracked[t])) {
				try { tracked[t].__storyTeardown(); } catch (e) {}
				tracked.splice(t, 1);
			}
		}
		var els = document.querySelectorAll('.upw-story');
		for (var i = 0; i < els.length; i++) { setup(els[i]); }
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
	window.upwStoryRescan = init;
})();
