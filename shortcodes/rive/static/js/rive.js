/* Rive Animation element runtime (UMD global `rive` from @rive-app/canvas).
 * For each .fw-rive[data-rive]: mounts a Rive instance on its <canvas>, runs the chosen State Machine /
 * animation, and wires how it plays — autoplay (pause off-screen), on view, on hover, on click, or
 * scroll-scrubbed by writing the scroll % (0–100) into a named State Machine number input. Interactive
 * State Machines still react to the pointer on top of any trigger. Guardrails: prefers-reduced-motion
 * keeps the poster (or loads a single static frame); the poster stays if the runtime fails to load; the
 * animation pauses off-screen and when the tab is hidden. Idempotent (page + element guards). */
(function () {
	'use strict';
	if (window.__fwRive) { return; }
	window.__fwRive = true;

	var R = window.rive;
	if (!R) { return; }
	var REDUCE = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

	// Point the runtime at the vendored WASM (Rive otherwise fetches it from unpkg).
	try { if (R.RuntimeLoader && window.__fwRiveWasm) { R.RuntimeLoader.setWasmUrl(window.__fwRiveWasm); } } catch (e) {}

	var FIT = { contain: 'Contain', cover: 'Cover', fill: 'Fill', 'fit-width': 'FitWidth', 'fit-height': 'FitHeight', none: 'None', layout: 'Layout' };
	var ALIGN = { center: 'Center', top: 'TopCenter', bottom: 'BottomCenter', left: 'CenterLeft', right: 'CenterRight' };

	function scrollProgress(el, len) {
		var vh = window.innerHeight || 1;
		var top = el.getBoundingClientRect().top + (window.pageYOffset || window.scrollY || 0);
		return Math.max(0, Math.min(1, ((window.pageYOffset || window.scrollY || 0) - top + vh * 0.5) / (Math.max(1, len) * vh)));
	}
	function clamp01(v) { return v < 0 ? 0 : (v > 1 ? 1 : v); }

	// Shared pointer position (viewport coords), tracked lazily — used by "Live inputs" pointer feeds.
	var PTR = { x: 0, y: 0, has: false, _on: false };
	function ensurePointer() {
		if (PTR._on) { return; }
		PTR._on = true;
		window.addEventListener('mousemove', function (e) { PTR.x = e.clientX; PTR.y = e.clientY; PTR.has = true; }, { passive: true });
		window.addEventListener('touchmove', function (e) { if (e.touches && e.touches[0]) { PTR.x = e.touches[0].clientX; PTR.y = e.touches[0].clientY; PTR.has = true; } }, { passive: true });
	}

	function initEl(el) {
		if (el.__rive) { return; }
		el.__rive = true;
		var cfg; try { cfg = JSON.parse(el.getAttribute('data-config') || '{}'); } catch (e) { return; }
		if (!cfg.src) { return; }
		var canvas = el.querySelector('.fw-rive__canvas');
		if (!canvas) { return; }
		var hasPoster = !!el.querySelector('.fw-rive__poster');

		// Reduced motion: if there's a poster, keep it (no WASM/animation). Otherwise load a static first frame.
		if (REDUCE && hasPoster) { return; }
		var staticOnly = REDUCE;

		// Fit.Layout is Rive's responsive layout — the artboard reflows to the canvas (N-slicing) instead
		// of scaling. It reads a layoutScaleFactor (the density/zoom of the reflowed content).
		var layout = null;
		try {
			var lopts = { fit: R.Fit[FIT[cfg.fit] || 'Contain'], alignment: R.Alignment[ALIGN[cfg.align] || 'Center'] };
			if (cfg.fit === 'layout') { lopts.layoutScaleFactor = cfg.layoutScale || 1; }
			layout = new R.Layout(lopts);
		} catch (e) {}

		var trigger = cfg.trigger || 'autoplay';
		var sm = cfg.stateMachine || '';
		var autoplay = !staticOnly && trigger === 'autoplay';

		var opts = { src: cfg.src, canvas: canvas, autoplay: autoplay, onLoad: onLoad, onLoadError: onError };
		if (layout) { opts.layout = layout; }
		if (cfg.artboard) { opts.artboard = cfg.artboard; }
		if (sm) { opts.stateMachines = sm; }
		else if (cfg.animation) { opts.animations = cfg.animation; }
		// Auto-bind the file's default View Model — so data-bound files render correctly, builder-entered
		// bindings can be applied, and the JS control API (el.fwRive / window.fwRive) can drive properties
		// at runtime. Harmless (a no-op) for files without a View Model.
		opts.autoBind = true;

		// Asset swaps: replace a referenced image/font in the .riv with the author's own. The runtime calls
		// this for every asset; for names we're given, fetch → decode → set (async), and return true so Rive
		// doesn't also load the embedded/CDN copy. Everything else returns false (Rive loads it normally).
		var assetMap = {};
		(cfg.assets || []).forEach(function (a) { if (a && a.name && a.url) { assetMap[a.name] = a.url; } });
		if (Object.keys(assetMap).length) {
			opts.assetLoader = function (asset) {
				var url = assetMap[asset.name];
				if (!url) { return false; }
				if (asset.isImage && R.decodeImage) {
					fetch(url).then(function (res) { return res.arrayBuffer(); })
						.then(function (buf) { return R.decodeImage(new Uint8Array(buf)); })
						.then(function (img) { try { asset.setRenderImage(img); if (img.unref) { img.unref(); } } catch (e) {} })
						.catch(function () {});
					return true;
				}
				if (asset.isFont && R.decodeFont) {
					fetch(url).then(function (res) { return res.arrayBuffer(); })
						.then(function (buf) { return R.decodeFont(new Uint8Array(buf)); })
						.then(function (font) { try { asset.setFont(font); if (font.unref) { font.unref(); } } catch (e) {} })
						.catch(function () {});
					return true;
				}
				return false;
			};
		}

		var r;
		try { r = new R.Rive(opts); } catch (e) { return; } // keep poster on failure

		function play() { try { sm ? r.play(sm) : r.play(); } catch (e) {} }
		function pause() { try { r.pause(); } catch (e) {} }

		var inView = true;
		function observe(cb) {
			if ('IntersectionObserver' in window) { new IntersectionObserver(function (es) { inView = es[0].isIntersecting; cb(inView); }, { rootMargin: '80px' }).observe(el); }
			else { cb(true); }
		}

		// Shrink the wrapper to the artboard's own aspect ratio (centered) so a `contain`/`fit-height`/
		// `none` fit doesn't leave dead pillarbox/letterbox space around the artwork — the hover/click
		// footprint then matches the object, not the full column. Skipped for fits that already fill the
		// canvas horizontally (cover/fill/fit-width) and when the author turned "Trim empty space" off.
		function fitToArtboard() {
			if (cfg.snug === false) { return; }
			var f = cfg.fit || 'contain';
			if (f === 'cover' || f === 'fill' || f === 'fit-width' || f === 'layout') { return; }
			var b; try { b = r.bounds; } catch (e) {}
			if (!b) { return; }
			var aw = b.maxX - b.minX, ah = b.maxY - b.minY;
			if (!(aw > 0 && ah > 0)) { return; }
			el.style.setProperty('--rive-aspect', (aw / ah).toFixed(4));
			el.classList.add('fw-rive--snug');
			try { r.resizeDrawingSurfaceToCanvas(); } catch (e) {}
		}

		// Rive Events: a State Machine can emit events at runtime (Rive editor → Events). Two safe hooks,
		// both opt-in: (1) forward every event to the page as a `fwRiveEvent` CustomEvent so the author's
		// own JS can react; (2) follow Rive's built-in "Open URL" event — sanitised to http/https/mailto/tel
		// (javascript:/data: etc. are ignored), opened per the chosen target with no window.opener leak.
		function openSafe(url, target) {
			if (!/^(https?:|mailto:|tel:)/i.test(url)) { return; }
			var pick = (cfg.events && cfg.events.urlTarget) || 'auto';
			var t = pick !== 'auto' ? pick : (target || '_self');
			if (t === '_blank') { var w = window.open(url, '_blank', 'noopener'); if (w) { try { w.opener = null; } catch (e) {} } }
			else { window.location.href = url; }
		}
		function wireEvents() {
			var ev = cfg.events || {};
			if (!ev.openUrl && !ev.jsHook) { return; }
			var TYPE = (R.EventType && R.EventType.RiveEvent) || 'riveevent';
			var OPEN_URL = (R.RiveEventType && R.RiveEventType.OpenUrl) || 131;
			try {
				r.on(TYPE, function (event) {
					var d = (event && event.data) ? event.data : event;
					if (!d) { return; }
					if (ev.jsHook) {
						try { el.dispatchEvent(new CustomEvent('fwRiveEvent', { bubbles: true, detail: { name: d.name, type: d.type, properties: d.properties || {}, url: d.url || '', target: d.target || '', element: el } })); } catch (e) {}
					}
					if (ev.openUrl && d.type === OPEN_URL && d.url) { openSafe(String(d.url), d.target); }
				});
			} catch (e) {}
		}

		// Data binding: read/write a file's View-Model properties (text / number / boolean / colour / enum /
		// trigger). Shared by the builder bindings AND the JS control API. Missing properties are skipped.
		function hexToArgb(hex) {
			hex = String(hex).trim().replace(/^#/, '');
			if (hex.length === 3) { hex = hex.replace(/(.)/g, '$1$1'); }
			if (hex.length === 6) { hex = 'ff' + hex; }        // opaque
			if (!/^[0-9a-fA-F]{8}$/.test(hex)) { return null; }
			return parseInt(hex, 16) >>> 0;                    // ARGB uint32
		}
		function vmSet(vmi, prop, type, val) {
			var p;
			if (type === 'number') { p = vmi.number(prop); if (p) { p.value = (typeof val === 'number' ? val : parseFloat(val) || 0); } }
			else if (type === 'boolean') { p = vmi.boolean(prop); if (p) { p.value = (typeof val === 'boolean' ? val : /^(yes|true|1|on)$/i.test(val)); } }
			else if (type === 'color') { var argb = (typeof val === 'number' ? val >>> 0 : hexToArgb(val)); p = vmi.color(prop); if (p && argb !== null) { p.value = argb; } }
			else if (type === 'enum') { p = vmi.enum(prop); if (p) { p.value = String(val); } }
			else if (type === 'trigger') { p = vmi.trigger(prop); if (p && p.trigger) { p.trigger(); } }
			else { p = vmi.string(prop); if (p) { p.value = String(val); } }
		}
		function vmGet(vmi, prop, type) {
			var p;
			if (type === 'number') { p = vmi.number(prop); }
			else if (type === 'boolean') { p = vmi.boolean(prop); }
			else if (type === 'color') { p = vmi.color(prop); }
			else if (type === 'enum') { p = vmi.enum(prop); }
			else { p = vmi.string(prop); }
			return p ? p.value : undefined;
		}
		function applyBindings() {
			var bs = cfg.bindings || [];
			if (!bs.length) { return; }
			var vmi; try { vmi = r.viewModelInstance; } catch (e) {}
			if (!vmi) { return; }
			bs.forEach(function (b) { try { vmSet(vmi, b.prop, b.type, b.value); } catch (e) {} });
		}

		// ---- JS control API: el.fwRive (per element) + window.fwRive (registry) ----
		// Lets page scripts drive a Rive instance at runtime: play/pause/stop, fire State-Machine triggers,
		// set/read SM inputs, and set/read data-binding (View-Model) properties — so bindings can reflect
		// LIVE values (a counter, a colour picker, data from your app), not just static builder values.
		function smInput(name) {
			try { var ins = r.stateMachineInputs(sm) || []; for (var i = 0; i < ins.length; i++) { if (ins[i].name === name) { return ins[i]; } } } catch (e) {}
			return null;
		}
		var api = {
			el: el,
			get rive() { return r; },
			play: function () { play(); return api; },
			pause: function () { pause(); return api; },
			stop: function () { try { r.stop(); } catch (e) {} return api; },
			fireTrigger: function (name) { var i = smInput(name); if (i && i.fire) { try { i.fire(); } catch (e) {} } return api; },
			setInput: function (name, val) { var i = smInput(name); if (i) { try { i.value = (typeof val === 'boolean') ? val : (typeof val === 'number' ? val : (parseFloat(val) || 0)); } catch (e) {} } return api; },
			getInput: function (name) { var i = smInput(name); return i ? i.value : undefined; },
			setBinding: function (prop, val, type) {
				var vmi; try { vmi = r.viewModelInstance; } catch (e) {}
				if (!vmi) { return api; }
				if (!type) { type = (typeof val === 'boolean') ? 'boolean' : (typeof val === 'number' ? 'number' : 'text'); }
				try { vmSet(vmi, prop, type, val); } catch (e) {}
				return api;
			},
			getBinding: function (prop, type) {
				var vmi; try { vmi = r.viewModelInstance; } catch (e) {}
				if (!vmi) { return undefined; }
				try { return vmGet(vmi, prop, type || 'text'); } catch (e) {}
				return undefined;
			}
		};
		el.fwRive = api;
		var REG = window.fwRive || (window.fwRive = {
			instances: [], byId: {},
			get: function (t) {
				if (!t) { return null; }
				if (t.nodeType === 1) { var n = t.closest ? (t.closest('.fw-rive') || t) : t; return n.fwRive || null; }
				var s = String(t);
				if (this.byId[s]) { return this.byId[s]; }
				if (s.charAt(0) === '#' && this.byId[s.slice(1)]) { return this.byId[s.slice(1)]; }
				var el2 = document.querySelector(s); return el2 ? (el2.fwRive || null) : null;
			}
		});
		REG.instances.push(api);
		if (el.id) { REG.byId[el.id] = api; }

		function onLoad() {
			try { r.resizeDrawingSurfaceToCanvas(); } catch (e) {}
			fitToArtboard();
			wireEvents();
			applyBindings();
			setupFeeds();
			el.classList.add('is-ready');
			// Signal that the JS control API (el.fwRive) is live — page scripts can now drive this instance.
			try { el.dispatchEvent(new CustomEvent('fwRiveReady', { bubbles: true, detail: { api: api, element: el } })); } catch (e) {}

			// Click-to-fire a named State Machine TRIGGER input (works alongside any Plays mode). Rive
			// State Machines that carry their own pointer LISTENERS already react on their own; this is for
			// files that expose a trigger input (a "bump", a button press…) with no built-in listener.
			if (cfg.clickTrigger && sm) {
				var trig = null;
				try { var ins = r.stateMachineInputs(sm) || []; for (var i = 0; i < ins.length; i++) { if (ins[i].name === cfg.clickTrigger && ins[i].fire) { trig = ins[i]; break; } } } catch (e) {}
				if (trig) {
					el.style.cursor = 'pointer';
					el.addEventListener('click', function () { try { play(); trig.fire(); } catch (e) {} });
					el.addEventListener('keydown', function (ev) { if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); try { play(); trig.fire(); } catch (e) {} } });
				}
			}

			if (staticOnly) { pause(); return; } // a single rendered frame, then hold

			if (trigger === 'hover') {
				pause();
				el.addEventListener('mouseenter', play);
				el.addEventListener('mouseleave', pause);
				el.addEventListener('focus', play); el.addEventListener('blur', pause);
			} else if (trigger === 'click') {
				pause();
				var toggle = function () { r && r.isPlaying ? pause() : play(); };
				el.addEventListener('click', toggle);
				el.addEventListener('keydown', function (ev) { if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); toggle(); } });
			} else if (trigger === 'scroll') {
				setupScroll();
			}
			// Autoplay + View: pause when off-screen / tab hidden; resume when back.
			if (trigger === 'autoplay' || trigger === 'view') {
				if (trigger === 'view') { pause(); }
				observe(function (v) { (v && !document.hidden) ? play() : pause(); });
				document.addEventListener('visibilitychange', function () { (inView && !document.hidden) ? play() : pause(); });
			}
		}

		function setupScroll() {
			var input = null;
			if (sm && cfg.scrollInput) {
				try {
					var inputs = r.stateMachineInputs(sm) || [];
					for (var i = 0; i < inputs.length; i++) { if (inputs[i].name === cfg.scrollInput) { input = inputs[i]; break; } }
				} catch (e) {}
			}
			if (!input) { play(); return; } // no matching input → just run it
			play(); // the SM must run to react to the input
			var onScroll = function () { try { input.value = scrollProgress(el, cfg.scrollLen || 2) * 100; } catch (e) {} };
			window.addEventListener('scroll', onScroll, { passive: true });
			onScroll();
			observe(function () {}); // keep inView tracked (no pause — scrub reflects position)
		}

		// Live input feeds: each frame, map the pointer / scroll (0–1) into the configured range and write it
		// to a State-Machine Number input (or a View-Model number property of the same name), eased by the
		// feed's smoothing. Paused off-screen / when the tab is hidden; skipped under reduced motion.
		function setupFeeds() {
			var feeds = cfg.feeds || [];
			if (!feeds.length || staticOnly) { return; }
			ensurePointer();
			observe(function () {}); // keep inView tracked
			play(); // the SM must run to react
			var vmi; try { vmi = r.viewModelInstance; } catch (e) {}
			var state = feeds.map(function (f) { return { f: f, cur: (typeof f.from === 'number' ? f.from : 0), in: smInput(f.input) }; });
			function srcVal(src) {
				var vw = window.innerWidth || 1, vh = window.innerHeight || 1;
				if (src === 'pointer_x') { return PTR.has ? clamp01(PTR.x / vw) : 0.5; }
				if (src === 'pointer_y') { return PTR.has ? clamp01(PTR.y / vh) : 0.5; }
				if (src === 'pointer_dist') {
					var rc = el.getBoundingClientRect(), cx = rc.left + rc.width / 2, cy = rc.top + rc.height / 2;
					var dx = PTR.x - cx, dy = PTR.y - cy, maxd = Math.sqrt(vw * vw + vh * vh) / 2;
					return PTR.has ? clamp01(Math.sqrt(dx * dx + dy * dy) / (maxd || 1)) : 0;
				}
				if (src === 'scroll') { return scrollProgress(el, 1); }
				return 0;
			}
			(function frame() {
				if (inView && !document.hidden) {
					for (var i = 0; i < state.length; i++) {
						var s = state[i], f = s.f;
						var target = f.from + srcVal(f.source) * (f.to - f.from);
						s.cur += (target - s.cur) * (1 - (f.smooth || 0));
						try {
							if (s.in) { s.in.value = s.cur; }
							else if (vmi) { var p = vmi.number(f.input); if (p) { p.value = s.cur; } }
						} catch (e) {}
					}
				}
				requestAnimationFrame(frame);
			})();
		}

		function onError() { /* keep the poster / veil as the fallback */ }

		window.addEventListener('resize', function () { try { r.resizeDrawingSurfaceToCanvas(); } catch (e) {} }, { passive: true });
	}

	function boot() { [].slice.call(document.querySelectorAll('.fw-rive[data-rive]')).forEach(initEl); }
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
	window.fwRiveRescan = boot;
})();
