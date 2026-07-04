/**
 * Animation Engine — Cursor "label" (standalone; only the chosen style's file loads site-wide).
 */
(function () {
	'use strict';
	var RAF = window.upwAnimRaf || (window.upwAnimRaf = { add: function (f) { (function l(t) { if (!document.hidden) { f(t); } requestAnimationFrame(l); })(0); return f; }, remove: function () {} });


	var cfg = window.upwCursorCfg || {};
	var mql = window.matchMedia || function () { return { matches: false }; };
	if (mql('(hover: none), (pointer: coarse)').matches) { return; }

	var reduce = cfg.reducedMotion && mql('(prefers-reduced-motion: reduce)').matches;
	var style = cfg.style || 'dot_ring';
	var trail = reduce ? 1 : (cfg.trail != null ? cfg.trail : 0.18);

	var root = document.documentElement;
	root.style.setProperty('--upw-cursor-color', cfg.color || '#2f74e6');
	root.style.setProperty('--upw-cursor-size', (cfg.size || 8) + 'px');
	root.style.setProperty('--upw-lens-r', (cfg.lensRadius || 70) + 'px');
	root.style.setProperty('--upw-lens-blur', (cfg.lensBlur != null ? cfg.lensBlur : 4) + 'px');
	root.style.setProperty('--upw-radar-speed', (cfg.radarSpeed || 1.6) + 's');
	root.style.setProperty('--upw-reveal-r', (cfg.revealRadius || 80) + 'px');
	root.classList.add('upw-cursor-on', 'upw-cursor-style-' + style.replace(/_/g, '-'));
	if (cfg.hideDefault) { root.classList.add('upw-cursor-hide-default'); }
	if (cfg.blend) { root.classList.add('upw-cursor-blend'); }

	function make(cls, base) {
		var d = document.createElement('div');
		d.className = (base === false ? '' : 'upw-cursor ') + cls;
		d.setAttribute('aria-hidden', 'true');
		document.body.appendChild(d);
		return d;
	}
	function place(el, x, y) {
		el.style.transform = 'translate(' + x.toFixed(1) + 'px,' + y.toFixed(1) + 'px) translate(-50%,-50%)';
	}

	var mx = window.innerWidth / 2, my = window.innerHeight / 2, seen = false;
	document.addEventListener('pointermove', function (e) {
		mx = e.clientX; my = e.clientY;
		if (!seen) { seen = true; root.classList.add('upw-cursor-active'); }
	}, { passive: true });
	document.addEventListener('pointerleave', function () { root.classList.remove('upw-cursor-active'); });
	document.addEventListener('pointerenter', function () { root.classList.add('upw-cursor-active'); });

	// Hover-grow + magnetic tracking over interactive elements.
	var SEL = 'a, button, [role="button"], input, textarea, select, label, .btn, .sc-btn, [data-hover]';
	var magEl = null;
	if (cfg.hoverGrow || cfg.magnetic) {
		document.addEventListener('pointerover', function (e) {
			var t = e.target.closest ? e.target.closest(SEL) : null;
			if (t) { if (cfg.hoverGrow) { root.classList.add('upw-cursor-hover'); } if (cfg.magnetic) { magEl = t; } }
		}, { passive: true });
		document.addEventListener('pointerout', function (e) {
			var t = e.target.closest ? e.target.closest(SEL) : null;
			if (t) { if (cfg.hoverGrow) { root.classList.remove('upw-cursor-hover'); } if (cfg.magnetic) { magEl = null; } }
		}, { passive: true });
	}
	function pos() {
		if (cfg.magnetic && magEl) {
			var r = magEl.getBoundingClientRect();
			return { x: mx + (r.left + r.width / 2 - mx) * 0.35, y: my + (r.top + r.height / 2 - my) * 0.35 };
		}
		return { x: mx, y: my };
	}

	label();
	if (cfg.clickRipple || cfg.clickBurst) { clickFx(); }

	var LABEL_SEL = '[data-cursor-label], a, button, [role="button"], .btn, .sc-btn';

	function label() {
		var el = make('upw-cursor--label upw-cursor-primary');
		var txt = document.createElement('span'); txt.className = 'upw-cursor-label-txt'; el.appendChild(txt);
		var lf = cfg.labelFont || {}; // typography-v2 props (inherited by the text span)
		if (lf.family) { el.style.fontFamily = lf.family; }
		if (lf.weight) { el.style.fontWeight = lf.weight; }
		if (lf.size) { el.style.fontSize = lf.size + 'px'; }
		if (lf.lineHeight) { el.style.lineHeight = lf.lineHeight + 'px'; }
		if (lf.letterSpacing) { el.style.letterSpacing = lf.letterSpacing + 'px'; }
		if (lf.style) { el.style.fontStyle = lf.style; }
		var def = cfg.label || '', current = def;
		function render() { txt.textContent = current; el.classList.toggle('is-shown', !!current); }
		render(); // baseline: default label shows right away (if set)
		document.addEventListener('pointerover', function (e) {
			var t = e.target.closest ? e.target.closest('[data-cursor-label]') : null;
			if (t) { current = t.getAttribute('data-cursor-label') || def; render(); }
		}, { passive: true });
		document.addEventListener('pointerout', function (e) {
			var t = e.target.closest ? e.target.closest('[data-cursor-label]') : null;
			if (t) { current = def; render(); }
		}, { passive: true });
		var x = mx, y = my;
		RAF.add(function () { x += (mx - x) * 0.2; y += (my - y) * 0.2; place(el, x, y); });
	}

	function clickFx() {
		document.addEventListener('pointerdown', function (e) {
			var x = e.clientX, y = e.clientY;
			if (cfg.clickRipple) {
				var r = make('upw-cursor-click-ripple', false);
				r.style.left = x + 'px'; r.style.top = y + 'px'; // animation owns transform, so position via left/top
				r.addEventListener('animationend', function () { r.remove(); });
			}
			if (cfg.clickBurst && !reduce) {
				for (var i = 0; i < 8; i++) {
					(function (ang) {
						var s = make('upw-cursor-click-spark', false);
						var dist = 18 + Math.random() * 14;
						s.style.setProperty('--dx', (Math.cos(ang) * dist).toFixed(1) + 'px');
						s.style.setProperty('--dy', (Math.sin(ang) * dist).toFixed(1) + 'px');
						s.style.left = x + 'px'; s.style.top = y + 'px';
						s.addEventListener('animationend', function () { s.remove(); });
					})(Math.PI * 2 * i / 8);
				}
			}
		}, { passive: true });
	}
})();
