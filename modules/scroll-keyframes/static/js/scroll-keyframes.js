/**
 * Animation Engine — Scroll Keyframes runtime.
 *
 * Each .sc-scroll-kf element carries base64 JSON on data-upw-skf: { kf: [ { at, v:{…}, ease }, … ] }.
 * We resolve a 0..1 progress for the element and interpolate its transform / opacity / blur between the
 * keyframes, with a per-segment easing curve.
 *
 * Progress source (automatic):
 *   • inside a Scroll Story stage → the story's published __storyProgress, remapped to the element's
 *     scene's beat slice (so it plays across that pinned scene);
 *   • otherwise → the shared window.upwScrollProgress helper (the element's travel through the viewport).
 *
 * Rides the shared frame scheduler (window.upwAnimRaf) — one ordered tick, pauses when the tab is hidden
 * — recomputing only when progress changed. Honours reduce motion + the per-element mobile opt-out.
 */
(function () {
	'use strict';
	if (typeof window === 'undefined' || typeof document === 'undefined') return;

	var cfg = window.upwSkfCfg || {};
	var KEYS = ['x', 'y', 'scale', 'rotation', 'rotationX', 'rotationY', 'opacity', 'blur'];
	var IDENT = { x: 0, y: 0, scale: 1, rotation: 0, rotationX: 0, rotationY: 0, opacity: 1, blur: 0 };

	// Easing curves (0..1 → 0..1).
	var EASE = {
		linear: function (t) { return t; },
		out:    function (t) { return 1 - Math.pow(1 - t, 3); },
		in:     function (t) { return t * t * t; },
		inout:  function (t) { return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2; },
		back:   function (t) { var c1 = 1.70158, c3 = c1 + 1; return 1 + c3 * Math.pow(t - 1, 3) + c1 * Math.pow(t - 1, 2); },
		sine:   function (t) { return -(Math.cos(Math.PI * t) - 1) / 2; }
	};

	// Registry (element key -> element) + beat-position override plumbing, for the Scroll Timeline editor.
	var REG = {};
	function keyOf(el) {
		if (!el || !el.getAttribute) { return ''; }
		var k = el.getAttribute('data-upw-g-key');
		if (k) { return k; }
		var a = el.closest ? el.closest('[data-upw-g-key]') : null;
		return a ? (a.getAttribute('data-upw-g-key') || '') : '';
	}
	// Set each kf[i].at from atArr[i], clamped to [0,1] and kept non-decreasing so beats never cross.
	function applyKfAt(kf, atArr) {
		if (!kf || !atArr) { return; }
		var prev = 0;
		for (var i = 0; i < kf.length; i++) {
			var a = (typeof atArr[i] === 'number' && isFinite(atArr[i])) ? atArr[i] : kf[i].at;
			a = a < 0 ? 0 : (a > 1 ? 1 : a);
			if (a < prev) { a = prev; }
			kf[i].at = a; prev = a;
		}
	}
	// Override each beat's STATE: merge kfsArr[i] = { v:{prop:val,…}, ease } | null into the beat.
	function applyKfState(kf, kfsArr) {
		if (!kf || !kfsArr) { return; }
		for (var i = 0; i < kf.length && i < kfsArr.length; i++) {
			var o = kfsArr[i];
			if (!o) { continue; }
			if (o.v) { for (var k in o.v) { if (typeof o.v[k] === 'number' && isFinite(o.v[k])) { kf[i].v[k] = o.v[k]; } } }
			if (o.ease) { kf[i].ease = o.ease; }
		}
	}

	function clamp(v, a, b) { return v < a ? a : (v > b ? b : v); }
	function reduced() {
		return cfg.reducedMotion !== false && (typeof window.upwReduceMotion === 'function'
			? window.upwReduceMotion()
			: (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches));
	}
	function isMobileOff(el) {
		return el.getAttribute('data-upw-skf-mobile') === '0' &&
			(typeof window.upwIsMobile === 'function' ? window.upwIsMobile() : (window.matchMedia && window.matchMedia('(max-width: 767px)').matches));
	}

	// Pin · Hold · Exit: wrap the element in a sticky "runway" so it holds on screen while its keyframes
	// scrub. The element stays position:sticky at the top for `pin` screen-heights, then its exit keyframe
	// carries it off. Least-invasive — the element keeps its own box; only a wrapper is added.
	function pinWrap(el) {
		var screens = parseFloat(el.getAttribute('data-upw-skf-pin')) || 0;
		if (!screens || el.__skfPinned || !el.parentNode) { return; }
		el.__skfPinned = true;
		var runway = document.createElement('div');
		runway.className = 'sc-skf-runway';
		runway.style.cssText = 'position:relative;height:' + (screens * 100) + 'vh;';
		el.parentNode.insertBefore(runway, el);
		runway.appendChild(el);
		el.style.position = 'sticky';
		el.style.top = '0';
		el.__skfRunway = runway;
	}
	function pinProgress(el) {
		var runway = el.__skfRunway, vh = window.innerHeight || 1;
		var total = runway.offsetHeight - (el.offsetHeight || vh); // scroll distance while the element sticks
		if (total <= 0) { return 0; }
		return clamp((-runway.getBoundingClientRect().top) / total, 0, 1);
	}

	// 0..1 progress for the element: pin runway if pinned, else story-scene slice inside a Scroll Story,
	// else viewport travel.
	function progressOf(el, cache) {
		if (el.__skfRunway) { return pinProgress(el); }
		if (!cache.story && el.closest) { cache.story = el.closest('.upw-story--stage') || false; }
		var story = cache.story;
		if (story && typeof story.__storyProgress === 'number') {
			var pr = story.__storyProgress;
			if (cache.scene === undefined) { cache.scene = (el.closest && el.closest('.upw-story-scene')) || false; }
			var scene = cache.scene;
			if (scene && story.__storyBeats && typeof scene.__beatIndex === 'number') {
				var lo = scene.__beatIndex / story.__storyBeats, hi = (scene.__beatIndex + 1) / story.__storyBeats;
				return hi > lo ? clamp((pr - lo) / (hi - lo), 0, 1) : pr;
			}
			return pr;
		}
		return (typeof window.upwScrollProgress === 'function') ? window.upwScrollProgress(el, {}) : viewportTravel(el);
	}
	function viewportTravel(el) {
		var r = el.getBoundingClientRect(), vh = window.innerHeight || 1;
		return clamp((vh - r.top) / (vh + r.height), 0, 1);
	}

	function interp(kf, p) {
		if (p <= kf[0].at) { return kf[0].v; }
		if (p >= kf[kf.length - 1].at) { return kf[kf.length - 1].v; }
		for (var i = 1; i < kf.length; i++) {
			if (p <= kf[i].at) {
				var a = kf[i - 1], b = kf[i];
				var span = b.at - a.at;
				var seg = span > 0 ? (p - a.at) / span : 1;
				var e = (EASE[b.ease] || EASE.out)(seg);
				var out = {};
				for (var k = 0; k < KEYS.length; k++) { var key = KEYS[k]; out[key] = a.v[key] + (b.v[key] - a.v[key]) * e; }
				return out;
			}
		}
		return kf[kf.length - 1].v;
	}

	function applyTo(el, v) {
		var t = 'translate3d(' + (v.x || 0).toFixed(2) + 'px,' + (v.y || 0).toFixed(2) + 'px,0)';
		if (v.rotationX || v.rotationY) {
			t = 'perspective(900px) ' + t + ' rotateX(' + (v.rotationX || 0).toFixed(2) + 'deg) rotateY(' + (v.rotationY || 0).toFixed(2) + 'deg)';
		}
		if (v.rotation) { t += ' rotate(' + v.rotation.toFixed(2) + 'deg)'; }
		if (v.scale != null && v.scale !== 1) { t += ' scale(' + v.scale.toFixed(4) + ')'; }
		el.style.transform = t;
		el.style.opacity = String(clamp(v.opacity == null ? 1 : v.opacity, 0, 1));
		el.style.filter = v.blur > 0.01 ? 'blur(' + v.blur.toFixed(2) + 'px)' : '';
	}

	function setup(el) {
		if (el.__skf) { return; }
		el.__skf = true;
		if (isMobileOff(el)) { return; } // leave static on mobile when opted out
		var data;
		try { data = JSON.parse(window.atob(el.getAttribute('data-upw-skf') || '')); } catch (e) { return; }
		var kf = data && data.kf;
		if (!kf || kf.length < 2) { return; }
		// Fill any missing prop with its identity so every segment interpolates cleanly.
		kf.forEach(function (k) { KEYS.forEach(function (key) { if (k.v[key] == null) { k.v[key] = IDENT[key]; } }); });

		// Expose to the Scroll Timeline editor (keyframe lanes), remember the authored beat positions,
		// and apply any persisted per-page retime of those positions (ships for every visitor).
		var key = keyOf(el);
		el.__skfKf = kf;
		el.__skfBaseAt = kf.map(function (k) { return k.at; });
		el.__skfBaseV = kf.map(function (k) { var c = {}; KEYS.forEach(function (kk) { c[kk] = k.v[kk]; }); return c; });
		el.__skfBaseEase = kf.map(function (k) { return k.ease; });
		if (key) {
			REG[key] = el;
			var ov = window.upwTimingOverrides && window.upwTimingOverrides[key];
			if (ov) {
				if (ov.kf) { applyKfAt(kf, ov.kf); }     // beat positions (Phase 3a)
				if (ov.kfs) { applyKfState(kf, ov.kfs); } // beat states  (Phase 3b)
			}
		}

		if (el.hasAttribute('data-upw-skf-pin')) { pinWrap(el); } // Pin · Hold · Exit → sticky runway
		el.style.willChange = 'transform, opacity, filter';
		var cache = { story: null, scene: undefined };
		el.__skfLastP = -1;
		function frame() {
			if (!document.documentElement.contains(el)) { return false; } // self-clean on builder re-render
			var p = progressOf(el, cache);
			if (p === el.__skfLastP) { return; }
			el.__skfLastP = p;
			applyTo(el, interp(kf, p));
		}
		el.__skfTick = frame; // let a live retime force an immediate re-tick
		if (window.upwAnimRaf && window.upwAnimRaf.add) {
			window.upwAnimRaf.add(frame);
		} else {
			var pending = false;
			var onScroll = function () { if (pending) return; pending = true; requestAnimationFrame(function () { pending = false; el.__skfLastP = -1; frame(); }); };
			window.addEventListener('scroll', onScroll, { passive: true });
			window.addEventListener('resize', onScroll, { passive: true });
		}
		frame();
	}

	function init(root) {
		if (reduced()) { return; } // reduce motion → leave elements in their natural state
		var els = (root || document).querySelectorAll('.sc-scroll-kf[data-upw-skf]');
		Array.prototype.forEach.call(els, setup);
	}

	if (document.readyState !== 'loading') { init(); } else { document.addEventListener('DOMContentLoaded', function () { init(); }); }
	window.upwSkfRescan = init;

	// --- Scroll Timeline editor hooks (keyframe lanes) ---------------------------------------------
	function reTick(el) { el.__skfLastP = -1; if (el.__skfTick) { el.__skfTick(); } }

	// Live retime: set an element's beat positions and re-tick at the current scroll immediately.
	window.upwSkfRetime = function (key, atArr) {
		var el = REG[key];
		if (!el || !el.__skfKf) { return; }
		applyKfAt(el.__skfKf, atArr);
		reTick(el);
	};
	// Live state edit: merge a v-partial and/or ease into beat i, re-tick.
	window.upwSkfSetState = function (key, i, vPartial, ease) {
		var el = REG[key];
		if (!el || !el.__skfKf || !el.__skfKf[i]) { return; }
		if (vPartial) { for (var k in vPartial) { if (typeof vPartial[k] === 'number' && isFinite(vPartial[k])) { el.__skfKf[i].v[k] = vPartial[k]; } } }
		if (ease) { el.__skfKf[i].ease = ease; }
		reTick(el);
	};
	// Revert ONE beat to its authored position + state + ease.
	window.upwSkfResetBeat = function (key, i) {
		var el = REG[key];
		if (!el || !el.__skfKf || !el.__skfKf[i]) { return; }
		if (el.__skfBaseAt) { el.__skfKf[i].at = el.__skfBaseAt[i]; }
		if (el.__skfBaseV && el.__skfBaseV[i]) { KEYS.forEach(function (kk) { el.__skfKf[i].v[kk] = el.__skfBaseV[i][kk]; }); }
		if (el.__skfBaseEase) { el.__skfKf[i].ease = el.__skfBaseEase[i]; }
		applyKfAt(el.__skfKf, el.__skfKf.map(function (k) { return k.at; })); // re-clamp monotonic
		reTick(el);
	};
	// Revert the whole element to its authored positions + states + eases.
	window.upwSkfReset = function (key) {
		var el = REG[key];
		if (!el || !el.__skfKf) { return; }
		if (el.__skfBaseAt) { applyKfAt(el.__skfKf, el.__skfBaseAt.slice()); }
		el.__skfKf.forEach(function (k, i) {
			if (el.__skfBaseV && el.__skfBaseV[i]) { KEYS.forEach(function (kk) { k.v[kk] = el.__skfBaseV[i][kk]; }); }
			if (el.__skfBaseEase) { k.ease = el.__skfBaseEase[i]; }
		});
		reTick(el);
	};
	window.upwSkfReg = REG;
})();
