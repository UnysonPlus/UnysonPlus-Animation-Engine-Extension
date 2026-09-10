/* Scroll Choreography engine — shared by the Image and Text Scroll Choreography elements.
 * Self-contained (no other dependency). A scene (.fw-choreo) is a zero-height anchor; the runtime measures
 * scroll from it over `travel` screen-heights into a shared progress p (0..1), damps it (one "smoothing"
 * control), and for EACH layer (.fw-choreo__layer) interpolates its keyframes {at,x,y,s,r,o,e} at p — writing
 * a transform (base-anchor translate + keyframe translate in vw/vh + rotate + scale) and opacity on the
 * LAYER wrapper only. Whatever a layer contains (an <img>, or a warped text block whose glyphs a Text Effect
 * animates) lives inside and is untouched by this engine, so the pose never fights inner content animation.
 * Opt-in guide draws a marker at every phase point + a live HUD. Honours reduced motion. Idempotent: a scene
 * is inited once (scene.__choreo guard), so loading this file twice on a page is harmless. */
(function () {
	'use strict';

	if (window.__fwScrollChoreoEngine) { return; } // one engine per page, even if enqueued twice
	window.__fwScrollChoreoEngine = true;

	var REDUCE = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
	function clamp01(v) { return v < 0 ? 0 : (v > 1 ? 1 : v); }

	function easeT(t, mode) {
		if (t <= 0) { return 0; }
		if (t >= 1) { return 1; }
		if (mode === 'linear') { return t; }
		if (mode === 'in') { return t * t; }
		if (mode === 'out') { return t * (2 - t); }
		return t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t; // in-out
	}

	function poseAt(keys, p) {
		var at = p * 100, last = keys[keys.length - 1];
		if (at <= keys[0].at) { return keys[0]; }
		if (at >= last.at) { return last; }
		for (var i = 1; i < keys.length; i++) {
			if (at <= keys[i].at) {
				var a = keys[i - 1], b = keys[i], span = (b.at - a.at) || 1;
				var t = easeT((at - a.at) / span, b.e);
				return {
					x: a.x + (b.x - a.x) * t, y: a.y + (b.y - a.y) * t,
					s: a.s + (b.s - a.s) * t, r: a.r + (b.r - a.r) * t,
					o: a.o + (b.o - a.o) * t
				};
			}
		}
		return last;
	}

	function applyPose(L, k) {
		var s = k.s / 100;
		L.el.style.transform =
			'translate(calc(' + L.bx + ' + ' + k.x.toFixed(2) + 'vw),calc(' + L.by + ' + ' + k.y.toFixed(2) + 'vh))' +
			' rotate(' + k.r.toFixed(2) + 'deg) scale(' + s.toFixed(3) + ')';
		L.el.style.opacity = (k.o / 100).toFixed(3);
	}

	function restKey(keys) {
		for (var i = 0; i < keys.length; i++) { if (keys[i].o >= 100) { return keys[i]; } }
		return keys[keys.length - 1];
	}

	// ---- Guide (opt-in authoring overlay) ------------------------------------
 	// Delegated to the shared window.upwAnimGuide builder so this guide and the Model Viewer guide look
 	// and behave identically (numbered, tinted, stacking, collapsible, click-to-flash).
	function scenePercents(layers) {
		var set = {}, ats = [];
		layers.forEach(function (L) { L.keys.forEach(function (k) { var a = Math.round(k.at); if (!(a in set)) { set[a] = 1; ats.push(a); } }); });
		return ats.sort(function (a, b) { return a - b; });
	}
	function buildGuide(scene, fixed, layers, cfg, editor) {
		if (!window.upwAnimGuide) { return null; }
		return window.upwAnimGuide.create({
			label: cfg.label || 'Choreography',
			target: fixed || scene,
			marks: scenePercents(layers),
			sections: layers.map(function (L, i) { return { name: L.name || ('Layer ' + (i + 1)), cells: ['X', 'Y', 'scale', 'turn', 'fade'] }; }),
			meta: 'travel ' + Math.max(1, cfg.travel || 3) + ' screens · smooth ' + (cfg.smooth || 0) + '%',
			editor: editor || null
		});
	}
	function updateGuide(guide, layers, dispP, pct) {
		guide.update({
			pct: pct,
			rows: layers.map(function (L) {
				var k = poseAt(L.keys, dispP);
				return { on: k.o > 5, fade: k.o.toFixed(0) + '%',
					values: [k.x.toFixed(0) + 'vw', k.y.toFixed(0) + 'vh', k.s.toFixed(0) + '%', k.r.toFixed(0) + '°', k.o.toFixed(0) + '%'] };
			})
		});
	}

	function initScene(scene) {
		if (scene.__choreo) { return; }
		scene.__choreo = true;

		var cfg; try { cfg = JSON.parse(scene.getAttribute('data-choreo') || '{}'); } catch (e) { return; }
		var fixed = scene.querySelector('.fw-choreo__fixed');
		if (!fixed) { return; }

		var layers = [].slice.call(scene.querySelectorAll('.fw-choreo__layer')).map(function (node) {
			var keys = []; try { keys = JSON.parse(node.getAttribute('data-keys') || '[]'); } catch (e) {}
			return { el: node, keys: keys, bx: node.getAttribute('data-bx') || '0px', by: node.getAttribute('data-by') || '0px', name: node.getAttribute('data-name') || '' };
		}).filter(function (L) { return L.keys.length; });
		if (!layers.length) { return; }

		var travel = Math.max(1, cfg.travel || 3);
		var damp   = Math.min(1, Math.max(0.06, 1 - (cfg.smooth || 0) / 100));

		if (REDUCE) { layers.forEach(function (L) { applyPose(L, restKey(L.keys)); }); return; }

		var guide = null;
		var dispP = 0, running = false;
		var editP = null; // null = follow the real scroll; else a frozen progress 0..1 (scrub playhead)

		function poseAll(p) { for (var i = 0; i < layers.length; i++) { applyPose(layers[i], poseAt(layers[i].keys, p)); } }
		function reposeFrozen() { poseAll(dispP); if (guide) { updateGuide(guide, layers, dispP, Math.round(dispP * 100)); } }

		// Tier 3 editor — scrub the timeline + nudge the nearest keyframe of each layer live, then Copy keys.
		// Rides on the (logged-in-only, opt-in) guide, so it's inherently author-only. cells → keyframe fields.
		var EDIT_FIELDS = ['x', 'y', 's', 'r', 'o'], EDIT_STEP = [1, 1, 1, 1, 5];
		function nearestKey(keys, atPct) { var best = 0, bd = Infinity; for (var i = 0; i < keys.length; i++) { var d = Math.abs(keys[i].at - atPct); if (d < bd) { bd = d; best = i; } } return best; }
		var editor = {
			steps: EDIT_STEP,
			onScrub: function (p) { editP = clamp01(p); dispP = editP; reposeFrozen(); },
			onLive:  function () { editP = null; kick(); },
			onNudge: function (li, ci, dir) {
				var f = EDIT_FIELDS[ci]; if (!f || !layers[li]) { return; }
				var keys = layers[li].keys; if (!keys.length) { return; }
				var k = keys[nearestKey(keys, Math.round(dispP * 100))];
				k[f] += dir * (EDIT_STEP[ci] || 1);
				if (f === 'o') { k.o = Math.max(0, Math.min(100, k.o)); }
				if (f === 's') { k.s = Math.max(0, k.s); }
				reposeFrozen();
			},
			onCopy: function () { return JSON.stringify(layers.map(function (L) { return { name: L.name || '', keys: L.keys }; }), null, 2); },

			// ---- Keyframe-track timeline ops (the HUD draws a track per layer from these) --------------
			getTracks: function () { return layers.map(function (L) { return { ats: L.keys.map(function (k) { return k.at; }) }; }); },
			// Retime a keyframe — clamp between its neighbours (keeps the list ordered, indices stable).
			onKeyMove: function (li, ki, at) {
				var keys = layers[li] && layers[li].keys; if (!keys || !keys[ki]) { return; }
				var lo = ki > 0 ? keys[ki - 1].at + 0.5 : 0, hi = ki < keys.length - 1 ? keys[ki + 1].at - 0.5 : 100;
				keys[ki].at = Math.max(lo, Math.min(hi, Math.round(at)));
				reposeFrozen();
			},
			// Add a keyframe at `at%` — its pose is the interpolated pose there, so the curve is unchanged
			// until you tweak the new point.
			onKeyAdd: function (li, at) {
				var keys = layers[li] && layers[li].keys; if (!keys) { return; }
				at = Math.max(0, Math.min(100, Math.round(at)));
				var p = poseAt(keys, at / 100);
				keys.push({ at: at, x: p.x, y: p.y, s: p.s, r: p.r, o: p.o, e: keys[0].e || 'inout' });
				keys.sort(function (a, b) { return a.at - b.at; });
				reposeFrozen();
			},
			// Delete a keyframe (keep at least two so a layer always has a from/to).
			onKeyDelete: function (li, ki) {
				var keys = layers[li] && layers[li].keys; if (!keys || keys.length <= 2 || !keys[ki]) { return; }
				keys.splice(ki, 1);
				reposeFrozen();
			}
		};

		guide = cfg.guide ? buildGuide(scene, fixed, layers, cfg, editor) : null;

		var startY = 0, vh = 1;
		function measure() {
			vh = window.innerHeight || 1;
			startY = scene.getBoundingClientRect().top + (window.pageYOffset || window.scrollY || 0);
			if (guide) { guide.positionMarks(travel, vh, startY); }
		}
		function raw() { return ((window.pageYOffset || window.scrollY || 0) - startY) / (travel * vh); }

		function tick() {
			if (editP !== null) { dispP = editP; poseAll(dispP); if (guide) { updateGuide(guide, layers, dispP, Math.round(dispP * 100)); } running = false; return; }
			var tp = clamp01(raw());
			dispP += (tp - dispP) * damp;
			if (Math.abs(tp - dispP) < 0.0004) { dispP = tp; }
			poseAll(dispP);
			if (guide) { updateGuide(guide, layers, dispP, Math.round(tp * 100)); guide.syncScrub(Math.round(tp * 100)); }
			var r = raw(), active = r > -0.3 && r < 1.3;
			if (dispP === tp && !active) { running = false; return; }
			requestAnimationFrame(tick);
		}
		function kick() { if (!running) { running = true; requestAnimationFrame(tick); } }

		measure();
		dispP = clamp01(raw());
		poseAll(dispP);
		if (guide) { updateGuide(guide, layers, dispP, Math.round(dispP * 100)); }
		window.addEventListener('scroll', kick, { passive: true });
		window.addEventListener('resize', function () { measure(); kick(); }, { passive: true });
		kick();
	}

	function boot() { [].slice.call(document.querySelectorAll('.fw-choreo')).forEach(initScene); }
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
	window.fwScrollChoreoRescan = boot;
})();
