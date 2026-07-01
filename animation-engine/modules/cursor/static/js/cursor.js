/**
 * Animation Engine — Cursor runtime.
 *
 * Builds a custom cursor from window.upwCursorCfg. Most styles are a single element
 * tracking the pointer (CSS does the shape); dot_ring adds a lagging ring; comet spawns
 * a fading trail; spotlight is a full-screen mask. Modifiers (grow, magnetic, blend,
 * hide-native) layer on top. Skips touch; under reduced motion, trailing stops. No deps.
 */
(function () {
	'use strict';

	var cfg = window.upwCursorCfg || {};
	var mql = window.matchMedia || function () { return { matches: false }; };
	if (mql('(hover: none), (pointer: coarse)').matches) { return; }

	var reduce = cfg.reducedMotion && mql('(prefers-reduced-motion: reduce)').matches;
	var style = cfg.style || 'dot_ring';
	var trail = reduce ? 1 : (cfg.trail != null ? cfg.trail : 0.18);

	var root = document.documentElement;
	root.style.setProperty('--upw-cursor-color', cfg.color || '#2f74e6');
	root.style.setProperty('--upw-cursor-size', (cfg.size || 8) + 'px');
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

	if (style === 'spotlight') { spotlight(); }
	else if (style === 'comet') { comet(); }
	else { shapes(); }

	/* Single element (+ lagging ring for dot_ring). */
	function shapes() {
		var primary = make('upw-cursor--' + style.replace(/_/g, '-') + ' upw-cursor-primary');
		if (style === 'custom' && cfg.image) { primary.style.backgroundImage = 'url("' + cfg.image + '")'; }
		if (style === 'glyph') { primary.textContent = cfg.glyph || '→'; }
		var ring = (style === 'dot_ring') ? make('upw-cursor-ring upw-cursor-secondary') : null;
		var rx = mx, ry = my;
		(function loop() {
			var p = pos();
			place(primary, p.x, p.y);
			if (ring) { rx += (p.x - rx) * trail; ry += (p.y - ry) * trail; place(ring, rx, ry); }
			requestAnimationFrame(loop);
		})();
	}

	/* Fading tail of segments chasing the head. */
	function comet() {
		var head = make('upw-cursor--dot upw-cursor-primary');
		var segs = [], N = 10, i;
		for (i = 0; i < N; i++) {
			var s = make('upw-cursor-comet-seg');
			s.style.opacity = (1 - i / N) * 0.5;
			s.style.setProperty('--seg', String(1 - i / N));
			segs.push({ el: s, x: mx, y: my });
		}
		(function loop() {
			var p = pos(); place(head, p.x, p.y);
			var px = p.x, py = p.y;
			for (i = 0; i < segs.length; i++) {
				var k = Math.max(0.08, 0.35 - i * 0.025);
				segs[i].x += (px - segs[i].x) * k; segs[i].y += (py - segs[i].y) * k;
				place(segs[i].el, segs[i].x, segs[i].y);
				px = segs[i].x; py = segs[i].y;
			}
			requestAnimationFrame(loop);
		})();
	}

	/* Full-screen dim with a lit hole around the cursor. */
	function spotlight() {
		var ov = make('upw-cursor-spotlight', false);
		ov.style.setProperty('--spot-r', (cfg.spotRadius || 160) + 'px');
		ov.style.setProperty('--spot-dim', String(cfg.spotDim != null ? cfg.spotDim : 0.6));
		(function loop() {
			ov.style.setProperty('--spot-x', mx + 'px');
			ov.style.setProperty('--spot-y', my + 'px');
			requestAnimationFrame(loop);
		})();
	}
})();
