/**
 * Animation Engine — Cursor "swarm" (standalone; only the chosen style's file loads site-wide).
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

	swarm(style);
	if (cfg.clickRipple || cfg.clickBurst) { clickFx(); }

	function swarm(kind) {
		var PALETTE = ['#2f74e6', '#e0447d', '#f5a524', '#17c964', '#9353d3', '#ff6b6b', '#00c2b2'];
		var head = make('upw-cursor--dot upw-cursor-primary');
		if (kind === 'firefly' || kind === 'bubble') { head.style.opacity = '0'; } // swarm is the star
		var N = Math.max(3, Math.min(30, cfg.count || 10)), pool = [], i;
		for (i = 0; i < N; i++) {
			var el = make('upw-cursor-p upw-cursor-p-' + kind);
			pool.push({ el: el, life: 0, x: mx, y: my, vx: 0, vy: 0, rot: 0, vr: 0 });
		}
		var idx = 0, last = 0, spawnMs = (kind === 'confetti') ? 30 : 45;
		(function loop(t) {
			var pp = pos(); if (kind === 'echo') { place(head, pp.x, pp.y); }
			if (!reduce) {
				if (!last) { last = t; }
				if (t - last > spawnMs) {
					last = t;
					var q = pool[idx % N]; idx++;
					q.x = pp.x; q.y = pp.y; q.life = 1; q.rot = Math.random() * 360;
					if (kind === 'echo') { q.vx = 0; q.vy = 0; q.vr = 0; }
					else if (kind === 'firefly') { q.vx = (Math.random() - 0.5) * 1.2; q.vy = (Math.random() - 0.5) * 1.2; q.vr = 0; }
					else if (kind === 'confetti') {
						q.vx = (Math.random() - 0.5) * 3; q.vy = -Math.random() * 2 - 0.5; q.vr = (Math.random() - 0.5) * 24;
						if (cfg.confettiMulti) { q.el.style.backgroundColor = PALETTE[(Math.random() * PALETTE.length) | 0]; }
					}
					else if (kind === 'bubble') { q.vx = (Math.random() - 0.5) * 0.8; q.vy = -Math.random() * 1.2 - 0.4; q.vr = 0; }
				}
			}
			for (i = 0; i < N; i++) {
				var s = pool[i];
				if (s.life <= 0) { continue; }
				s.life = Math.max(0, s.life - (kind === 'confetti' ? 0.018 : 0.028));
				if (kind === 'confetti') { s.vy += 0.12; } // gravity
				s.x += s.vx; s.y += s.vy; s.rot += s.vr;
				var sc = kind === 'echo' ? (0.4 + s.life * 0.9) : (0.4 + s.life * 0.6);
				var flick = kind === 'firefly' ? (0.55 + 0.45 * Math.abs(Math.sin((s.life + i) * 6))) : 1;
				s.el.style.opacity = String(s.life * (kind === 'echo' ? 0.55 : 0.85) * flick);
				s.el.style.transform = 'translate(' + s.x.toFixed(1) + 'px,' + s.y.toFixed(1) + 'px) translate(-50%,-50%) rotate(' + s.rot.toFixed(0) + 'deg) scale(' + sc.toFixed(2) + ')';
			}
			requestAnimationFrame(loop);
		})(0);
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
