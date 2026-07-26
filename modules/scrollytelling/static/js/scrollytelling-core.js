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
	var reduce = cfg.reducedMotion !== false && (window.upwReduceMotion ? window.upwReduceMotion() : mql('(prefers-reduced-motion: reduce)').matches);
	var isMobile = window.upwIsMobile ? window.upwIsMobile() : ((window.innerWidth || 1024) < 768);
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

		// Persistent layer(s): a scene column marked `upw-story-persist` stays visible the WHOLE
		// time (it does not cross-fade) while the remaining "beat" scenes play in order over it.
		// Use it for a device/gallery that morphs with scroll while the copy beats change above it.
		// With NO persist scene, beats === scenes and every path below behaves exactly as before.
		// The marker may sit on the fw-col itself OR on the column's inner wrapper (that's where a
		// builder column's CSS-class lands), so check the scene and its descendants.
		var persistScenes = scenes.filter(function (s) {
			return /(^|\s)upw-story-persist(\s|$)/.test(s.className) || (s.querySelector && s.querySelector('.upw-story-persist'));
		});
		var beats = scenes.filter(function (s) { return persistScenes.indexOf(s) === -1; });
		if (beats.length < 1) { beats = scenes; persistScenes = []; } // all-persist → treat as normal
		// Publish each beat's index on its scene element so a scroll-driven child (e.g. a photo_scatter
		// gallery placed INSIDE a beat) can remap the story's global progress to its own beat's slice —
		// letting the child's motion (scatter in/hold/out) sync to when that scene enters and leaves.
		beats.forEach(function (s, k) { s.__beatIndex = k; });

		el.classList.add('upw-story--stage');
		// Clip the scrollbar-gutter overshoot of the 100vw full-bleed stage at the root (see base.css
		// `html.upw-fullbleed`) — kills the stray horizontal scrollbar without breaking the sticky pin.
		document.documentElement.classList.add('upw-fullbleed');
		el.style.setProperty('--story-top', (parseFloat(el.getAttribute('data-story-top')) || 0) + 'px');
		el.style.setProperty('--story-h', (parseFloat(el.getAttribute('data-story-h')) || 100) + 'vh');
		el.style.setProperty('--story-trans', (parseFloat(el.getAttribute('data-story-trans')) || 0.6) + 's');
		el.style.setProperty('--story-intensity', (parseFloat(el.getAttribute('data-story-intensity')) || 0.5));
		// Section height = scroll runway: one viewport for the pin + sceneLen per BEAT scene
		// (persistent layers don't own scroll time — they span the whole ride).
		el.style.height = 'calc(' + (beats.length * sceneLen * 100) + 'vh + var(--story-h, 100vh))';

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
				beats.forEach(function (s, k) {
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
		var ctx = { intensity: parseFloat(el.getAttribute('data-story-intensity')) || 0.5, layers: beats, steps: beats, media: row, section: el };
		el.__storyBeats = beats.length;   // published so a ranged-persist child can remap progress to its range
		// Persistent layers: mark for styling + parse an optional beat RANGE. A persist scene with
		// `upw-persist-from-A` / `upw-persist-to-B` (on the scene or its inner wrapper) is shown only
		// while the active beat is within [A,B] and cross-fades in/out at the edges — so one Stage can
		// hand a persistent element (a morphing gallery) off to a different one a few beats later. No
		// range = the whole ride. activate() toggles their is-active by range on each beat change.
		persistScenes.forEach(function (s) {
			s.classList.add('upw-story-scene--persist');
			var marker = (s.querySelector && s.querySelector('.upw-story-persist')) || s;
			var cls = (marker.className || '') + ' ' + (s.className || '');
			var mf = cls.match(/upw-persist-from-(\d+)/), mt = cls.match(/upw-persist-to-(\d+)/);
			s.__pFrom = mf ? parseInt(mf[1], 10) : 0;
			s.__pTo   = mt ? parseInt(mt[1], 10) : (beats.length - 1);
		});
		var current = -1;
		function activate(i) {
			i = Math.max(0, Math.min(beats.length - 1, i));
			if (i === current) { return; }
			current = i;
			beats.forEach(function (s, k) {
				s.classList.toggle('is-active', k === i);
				s.classList.toggle('is-past', k < i);
			});
			persistScenes.forEach(function (s) { s.classList.toggle('is-active', i >= s.__pFrom && i <= s.__pTo); });
			if (rail && !rail.__isBar) { dots.forEach(function (d, k) { d.classList.toggle('is-active', k === i); }); }
			if (def && typeof def.onActivate === 'function') {
				try { def.onActivate(beats[i], i, ctx); } catch (e) {}
			}
		}

		// Exit hand-off: near the end, fade the pinned stage out so the Section background (which the
		// author sets to the next section's colour) shows through — the ride dissolves into what
		// follows instead of hard-cutting when the pin releases.
		var exitFade = el.getAttribute('data-story-exit') === 'fade';
		var exitAt = parseFloat(el.getAttribute('data-story-exit-at'));
		if (isNaN(exitAt)) { exitAt = 0.78; }

		// Progress loop while the section is in view: scene switching + backdrop scrub. Rides the shared
		// frame scheduler (window.upwAnimRaf) — one ordered tick that pauses when the tab is hidden — and
		// falls back to a private rAF loop if the scheduler isn't present.
		var raf = 0, inView = true, lastFade = -1;
		// Backdrop Motion — a scroll-scrubbed CAMERA move on the backdrop (the "camera glide"), not a
		// flat parallax slide. The backdrop is treated as a PLANE the camera glides across: it pans
		// (translate) AND yaws through a real perspective (rotateY/rotateX). The yaw is the crux — it
		// gives the plane a keystone so the near edge sweeps faster than the far edge (depth-parallax
		// WITHIN the one image), which reads as a camera physically turning across the space rather
		// than a background layer sliding behind fixed content. A gentle counter-arc on the cross axis
		// adds the organic drift of a real camera. Dolly (scale) pushes in over the ride. Generous
		// cover-scale so neither the pan nor the yaw's far edge ever reveals a gap.
		var bdMotion = el.getAttribute('data-story-bd-motion') || 'none';
		var bdMedia = ( bdMotion !== 'none' && backdrop && backdrop.el ) ? backdrop.el : null;
		var bdApply = function () {};
		if ( bdMedia ) {
			var bdDir = el.getAttribute('data-story-bd-dir') || 'left';
			var bdInt = Math.max(0, Math.min(100, parseFloat(el.getAttribute('data-story-bd-intensity')) || 50)) / 100;
			var bdPan = ( bdMotion === 'pan' || bdMotion === 'pan_dolly' );
			var bdDolly = ( bdMotion === 'dolly' || bdMotion === 'pan_dolly' );
			var panMax = 11 * bdInt;      // % of travel — a real sweep, not a nudge
			var yawMax = 6 * bdInt;       // deg of camera yaw through the perspective (the depth cue)
			var arcMax = 2.2 * bdInt;     // % of gentle cross-axis drift (organic camera arc)
			var dollyMax = 0.14 * bdInt;
			var baseScale = 1.05 + ( bdPan ? ( panMax * 2 / 100 + yawMax / 60 ) : 0 );
			bdMedia.style.willChange = 'transform';
			bdMedia.style.transformOrigin = '50% 50%';
			bdApply = function ( p ) {
				var sc = baseScale + ( bdDolly ? p * dollyMax : 0 );
				var tx = 0, ty = 0, ry = 0, rx = 0;
				if ( bdPan ) {
					var d = ( 0.5 - p ) * 2;               // -1 … +1 across the ride (continuous sweep)
					var arc = Math.sin( p * Math.PI ) * arcMax; // 0 → peak → 0: a soft camera arc
					if ( bdDir === 'left' )      { tx =  d * panMax; ry = -d * yawMax; ty = -arc; }
					else if ( bdDir === 'right' ) { tx = -d * panMax; ry =  d * yawMax; ty = -arc; }
					else if ( bdDir === 'up' )    { ty =  d * panMax; rx =  d * yawMax; tx =  arc; }
					else                          { ty = -d * panMax; rx = -d * yawMax; tx =  arc; }
				}
				bdMedia.style.transform = 'perspective(1400px) translate(' + tx.toFixed(3) + '%,' + ty.toFixed(3) + '%) rotateY(' + ry.toFixed(3) + 'deg) rotateX(' + rx.toFixed(3) + 'deg) scale(' + sc.toFixed(4) + ')';
			};
		}
		function frame() {
			var r = el.getBoundingClientRect();
			var vh = window.innerHeight || 1;
			var runway = Math.max(1, r.height - vh);
			var p = Math.max(0, Math.min(1, -r.top / runway));
			el.__storyProgress = p;           // publish for child scrubbers (galleries, group moves)
			el.style.setProperty('--story-progress', p.toFixed(4));
			activate(Math.min(beats.length - 1, Math.floor(p * beats.length)));
			if (rail && rail.__isBar) { rail.firstChild.style.transform = 'scaleY(' + p + ')'; }
			if (backdrop) { backdrop.update(p); }
			bdApply(p);
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
		function run() {
			if (!inView) { return; }
			if (window.upwAnimRaf && window.upwAnimRaf.add) { window.upwAnimRaf.add(frame); }
			else if (!raf) { var loop = function () { frame(); raf = requestAnimationFrame(loop); }; raf = requestAnimationFrame(loop); }
		}
		function stop() {
			if (window.upwAnimRaf && window.upwAnimRaf.remove) { window.upwAnimRaf.remove(frame); }
			if (raf) { cancelAnimationFrame(raf); raf = 0; }
		}
		if ('IntersectionObserver' in window) {
			var io = new IntersectionObserver(function (es) {
				inView = es[0].isIntersecting; if (inView) { run(); } else { stop(); }
			}, { threshold: 0 });
			io.observe(el);
			teardown.push(function () { stop(); io.disconnect(); });
		} else { run(); }
		activate(0);
		if (backdrop) { backdrop.update(0); }
		bdApply(0);
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

		// Scrub styles: feed 0..1 progress while the Section is in view. Rides the shared frame
		// scheduler (window.upwAnimRaf), with a private-rAF fallback.
		if (def && def.scrub && typeof def.onProgress === 'function') {
			var raf = 0, inView = true;
			function frame() {
				var r = el.getBoundingClientRect();
				var p = Math.max(0, Math.min(1, (window.innerHeight - r.top) / ((r.height + window.innerHeight) || 1)));
				try { def.onProgress(el, p, ctx); } catch (e) {}
			}
			function run() {
				if (!inView) { return; }
				if (window.upwAnimRaf && window.upwAnimRaf.add) { window.upwAnimRaf.add(frame); }
				else if (!raf) { var loop = function () { frame(); raf = requestAnimationFrame(loop); }; raf = requestAnimationFrame(loop); }
			}
			function stop() {
				if (window.upwAnimRaf && window.upwAnimRaf.remove) { window.upwAnimRaf.remove(frame); }
				if (raf) { cancelAnimationFrame(raf); raf = 0; }
			}
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
