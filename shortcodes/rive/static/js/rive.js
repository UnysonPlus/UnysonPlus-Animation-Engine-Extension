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

	var FIT = { contain: 'Contain', cover: 'Cover', fill: 'Fill', 'fit-width': 'FitWidth', 'fit-height': 'FitHeight', none: 'None' };
	var ALIGN = { center: 'Center', top: 'TopCenter', bottom: 'BottomCenter', left: 'CenterLeft', right: 'CenterRight' };

	function scrollProgress(el, len) {
		var vh = window.innerHeight || 1;
		var top = el.getBoundingClientRect().top + (window.pageYOffset || window.scrollY || 0);
		return Math.max(0, Math.min(1, ((window.pageYOffset || window.scrollY || 0) - top + vh * 0.5) / (Math.max(1, len) * vh)));
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

		var layout = null;
		try { layout = new R.Layout({ fit: R.Fit[FIT[cfg.fit] || 'Contain'], alignment: R.Alignment[ALIGN[cfg.align] || 'Center'] }); } catch (e) {}

		var trigger = cfg.trigger || 'autoplay';
		var sm = cfg.stateMachine || '';
		var autoplay = !staticOnly && trigger === 'autoplay';

		var opts = { src: cfg.src, canvas: canvas, autoplay: autoplay, onLoad: onLoad, onLoadError: onError };
		if (layout) { opts.layout = layout; }
		if (cfg.artboard) { opts.artboard = cfg.artboard; }
		if (sm) { opts.stateMachines = sm; }
		else if (cfg.animation) { opts.animations = cfg.animation; }
		// Data binding: auto-bind the file's default View Model so builder-entered values can be applied.
		if (cfg.bindings && cfg.bindings.length) { opts.autoBind = true; }

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
			if (f === 'cover' || f === 'fill' || f === 'fit-width') { return; }
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

		// Data binding: push builder-entered values into the file's View-Model properties (text / number /
		// boolean / colour). Needs a .riv built with data binding; missing properties are skipped silently.
		function hexToArgb(hex) {
			hex = String(hex).trim().replace(/^#/, '');
			if (hex.length === 3) { hex = hex.replace(/(.)/g, '$1$1'); }
			if (hex.length === 6) { hex = 'ff' + hex; }        // opaque
			if (!/^[0-9a-fA-F]{8}$/.test(hex)) { return null; }
			return parseInt(hex, 16) >>> 0;                    // ARGB uint32
		}
		function applyBindings() {
			var bs = cfg.bindings || [];
			if (!bs.length) { return; }
			var vmi; try { vmi = r.viewModelInstance; } catch (e) {}
			if (!vmi) { return; }
			bs.forEach(function (b) {
				try {
					var p;
					if (b.type === 'number') { p = vmi.number(b.prop); if (p) { p.value = parseFloat(b.value) || 0; } }
					else if (b.type === 'boolean') { p = vmi.boolean(b.prop); if (p) { p.value = /^(yes|true|1|on)$/i.test(b.value); } }
					else if (b.type === 'color') { var argb = hexToArgb(b.value); p = vmi.color(b.prop); if (p && argb !== null) { p.value = argb; } }
					else if (b.type === 'trigger') { p = vmi.trigger(b.prop); if (p && p.trigger) { p.trigger(); } }
					else { p = vmi.string(b.prop); if (p) { p.value = b.value; } }
				} catch (e) {}
			});
		}

		function onLoad() {
			try { r.resizeDrawingSurfaceToCanvas(); } catch (e) {}
			fitToArtboard();
			wireEvents();
			applyBindings();
			el.classList.add('is-ready');

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

		function onError() { /* keep the poster / veil as the fallback */ }

		window.addEventListener('resize', function () { try { r.resizeDrawingSurfaceToCanvas(); } catch (e) {} }, { passive: true });
	}

	function boot() { [].slice.call(document.querySelectorAll('.fw-rive[data-rive]')).forEach(initEl); }
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
	window.fwRiveRescan = boot;
})();
