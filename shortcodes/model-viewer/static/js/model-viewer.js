/**
 * Model Viewer — light harness around Google's <model-viewer>.
 *
 * <model-viewer> already owns the render loop, camera, IBL lighting, poster and
 * off-screen pausing. Here we only: (1) strip auto-rotate under prefers-reduced-motion;
 * (2) drive the slim load-progress bar; (3) fall back to the poster image if 3D /
 * custom elements aren't supported.
 */
(function () {
	'use strict';

	var REDUCE = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var SUPPORTS_CE = 'customElements' in window;

	function initOne(root) {
		if (root.__modelReady) { return; }
		root.__modelReady = true;

		var mv = root.querySelector('model-viewer');
		if (!mv || !SUPPORTS_CE) { root.classList.add('is-unsupported'); return; }

		// Reduced motion: don't spin.
		if (REDUCE) { mv.removeAttribute('auto-rotate'); }

		// Scroll Choreography: drive the model's screen position / rotation / scale /
		// opacity from scroll, per keyframe.
		if (root.classList.contains('fw-model--choreo')) { initChoreo(root, mv); }

		var bar = root.querySelector('.fw-model__bar > i');

		mv.addEventListener('progress', function (e) {
			var p = (e.detail && typeof e.detail.totalProgress === 'number') ? e.detail.totalProgress : 1;
			if (bar) { bar.style.width = Math.round(p * 100) + '%'; }
			if (p >= 1) { root.classList.add('is-loaded'); }
		});

		mv.addEventListener('load', function () {
			root.classList.add('is-loaded');
			if (root.getAttribute('data-variants') !== null) { buildVariants(root, mv); }
		});

		// Model failed to load (bad URL, decode error) → show the poster fallback.
		mv.addEventListener('error', function () { root.classList.add('is-unsupported'); });
	}

	// Populate the swatch row from the model's built-in material variants.
	function buildVariants(root, mv) {
		var host = root.querySelector('.fw-model__variants');
		if (!host) { return; }
		var names = mv.availableVariants || [];
		if (!names.length) { host.style.display = 'none'; return; }

		host.innerHTML = '';
		var current = mv.variantName; // the default set via the attribute, or null
		names.forEach(function (name) {
			var b = document.createElement('button');
			b.type = 'button';
			b.className = 'fw-model__variant-btn';
			b.textContent = name;
			b.setAttribute('data-variant', name);
			if (name === current) { b.classList.add('is-active'); }
			b.addEventListener('click', function () {
				mv.variantName = name;
				Array.prototype.forEach.call(
					host.querySelectorAll('.fw-model__variant-btn'),
					function (x) { x.classList.remove('is-active'); }
				);
				b.classList.add('is-active');
			});
			host.appendChild(b);
		});
		// No default matched → the model's own default is showing; mark the first swatch.
		if (!host.querySelector('.is-active') && host.firstChild) {
			host.firstChild.classList.add('is-active');
		}
	}

	// ---- Scroll Choreography -------------------------------------------------
	// The model lives in a fixed, z-indexed layer (page sections scroll over/under
	// it); the .fw-model wrapper is a zero-height anchor we measure scroll from.
	// Each keyframe {at,x,y,s,yaw,pit,o,e} is a pose at a scroll % (0..100); we
	// interpolate between them, damp toward the scroll target (one control =
	// "smoothing"), and set a CSS transform (translate in vw/vh + scale), opacity,
	// and camera-orbit. camera tracks tightly (low interpolation-decay set in PHP)
	// so our damping is the only glide — the GSAP "cinematic scroll" pattern.
	function easeT(t, mode) {
		if (t <= 0) { return 0; }
		if (t >= 1) { return 1; }
		if (mode === 'linear') { return t; }
		if (mode === 'in') { return t * t; }
		if (mode === 'out') { return t * (2 - t); }
		return t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t; // in-out
	}

	// ---- Choreography guide (opt-in authoring overlay) ------------------------
	// A marker pinned in the scroll for each keyframe (so the author sees which
	// keyframe lines up with which real section) plus a live HUD of the current
	// scroll % and the model's interpolated pose. Toggled off before publishing.
	var GUIDE_CIRCLED = '①②③④⑤⑥⑦⑧⑨⑩⑪⑫⑬⑭⑮⑯⑰⑱⑲⑳';
	function guideNum(n) { return (n >= 1 && n <= 20) ? GUIDE_CIRCLED.charAt(n - 1) : ('#' + n); }
	function buildGuide(keys) {
		var wrap = document.createElement('div');
		wrap.className = 'fw-model__guide';
		var marks = [];
		for (var i = 0; i < keys.length; i++) {
			var m = document.createElement('div');
			m.className = 'fw-model__guide-mark';
			m.innerHTML = '<span class="fw-model__guide-badge">' + guideNum(i + 1) + ' ' + Math.round(keys[i].at) + '%</span>';
			wrap.appendChild(m);
			marks.push(m);
		}
		var hud = document.createElement('div');
		hud.className = 'fw-model__guide-hud';
		document.body.appendChild(wrap);
		document.body.appendChild(hud);
		return { wrap: wrap, marks: marks, hud: hud };
	}
	function guideCell(label, val) { return '<span class="fw-model__guide-cell"><i>' + label + '</i>' + val + '</span>'; }
	function updateGuide(guide, keys, pose, pct) {
		var ai = -1, i;
		for (i = 0; i < keys.length; i++) { if (pct >= keys[i].at) { ai = i; } }
		var span;
		if (ai < 0) { span = 'before ' + guideNum(1); }
		else if (ai >= keys.length - 1) { span = 'at ' + guideNum(keys.length); }
		else { span = guideNum(ai + 1) + ' ' + Math.round(keys[ai].at) + '%  →  ' + guideNum(ai + 2) + ' ' + Math.round(keys[ai + 1].at) + '%'; }
		guide.hud.innerHTML =
			'<div class="fw-model__guide-row fw-model__guide-pct">scroll <b>' + pct + '%</b></div>' +
			'<div class="fw-model__guide-row fw-model__guide-span">' + span + '</div>' +
			'<div class="fw-model__guide-grid">' +
				guideCell('X', pose.x.toFixed(0) + 'vw') + guideCell('Y', pose.y.toFixed(0) + 'vh') +
				guideCell('scale', pose.s.toFixed(0) + '%') + guideCell('turn', pose.yaw.toFixed(0) + '°') +
				guideCell('tilt', pose.pit.toFixed(0) + '°') + guideCell('fade', pose.o.toFixed(0) + '%') +
			'</div>';
		for (i = 0; i < guide.marks.length; i++) {
			guide.marks[i].classList.toggle('is-active', i === ai || i === ai + 1);
		}
	}

	function initChoreo(root, mv) {
		var cfg;
		try { cfg = JSON.parse(root.getAttribute('data-choreo') || '{}'); } catch (e) { return; }
		var keys = cfg.keys || [];
		if (!keys.length) { return; }

		var fixed  = root.querySelector('.fw-model__fixed') || root;
		var travel = Math.max(1, cfg.travel || 3);
		var damp   = Math.min(1, Math.max(0.06, 1 - (cfg.smooth || 0) / 100));
		var last   = keys[keys.length - 1];

		function poseAt(p) {
			var at = p * 100;
			if (at <= keys[0].at) { return keys[0]; }
			if (at >= last.at) { return last; }
			for (var i = 1; i < keys.length; i++) {
				if (at <= keys[i].at) {
					var a = keys[i - 1], b = keys[i];
					var span = (b.at - a.at) || 1;
					var t = easeT((at - a.at) / span, b.e);
					return {
						x: a.x + (b.x - a.x) * t, y: a.y + (b.y - a.y) * t,
						s: a.s + (b.s - a.s) * t, yaw: a.yaw + (b.yaw - a.yaw) * t,
						pit: a.pit + (b.pit - a.pit) * t, o: a.o + (b.o - a.o) * t
					};
				}
			}
			return last;
		}
		function applyPose(k) {
			mv.style.transform = 'translate(' + k.x.toFixed(2) + 'vw,' + k.y.toFixed(2) + 'vh) scale(' + (k.s / 100).toFixed(3) + ')';
			mv.style.opacity   = (k.o / 100).toFixed(3);
			mv.setAttribute('camera-orbit', k.yaw.toFixed(1) + 'deg ' + (90 - k.pit).toFixed(1) + 'deg auto');
		}

		// Reduced motion: rest at the final pose, no scrubbing (and no guide).
		if (REDUCE) { applyPose(last); fixed.style.visibility = 'visible'; return; }

		var guide = cfg.guide ? buildGuide(keys) : null;

		var startY = 0, vh = 1;
		function measure() {
			vh = window.innerHeight || 1;
			startY = root.getBoundingClientRect().top + (window.pageYOffset || window.scrollY || 0);
			if (guide) {
				for (var i = 0; i < keys.length; i++) {
					guide.marks[i].style.top = (startY + (keys[i].at / 100) * travel * vh) + 'px';
				}
			}
		}
		function scrollY() { return window.pageYOffset || window.scrollY || 0; }
		function raw() { return (scrollY() - startY) / (travel * vh); }

		var dispP = 0, running = false;
		function tick() {
			var tp = Math.min(1, Math.max(0, raw()));
			dispP += (tp - dispP) * damp;
			if (Math.abs(tp - dispP) < 0.0004) { dispP = tp; }
			applyPose(poseAt(dispP));
			var r = raw();
			var active = r > -0.3 && r < 1.3;      // a little lead-in / lead-out
			fixed.style.visibility = active ? 'visible' : 'hidden';
			if (guide) { updateGuide(guide, keys, poseAt(dispP), Math.round(tp * 100)); }
			if (dispP === tp && !active) { running = false; return; }   // idle → stop the loop
			requestAnimationFrame(tick);
		}
		function kick() { if (!running) { running = true; requestAnimationFrame(tick); } }

		measure();
		dispP = Math.min(1, Math.max(0, raw()));
		applyPose(poseAt(dispP));
		if (guide) { updateGuide(guide, keys, poseAt(dispP), Math.round(dispP * 100)); }
		window.addEventListener('scroll', kick, { passive: true });
		window.addEventListener('resize', function () { measure(); kick(); }, { passive: true });
		kick();
	}

	function init() {
		Array.prototype.forEach.call(document.querySelectorAll('.fw-model'), initOne);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
