/**
 * Animation Engine — Cursor "canvasfx" (standalone; only the chosen style's file loads site-wide).
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

	canvasFx(style);
	if (cfg.clickRipple || cfg.clickBurst) { clickFx(); }

	function canvasFx(mode) {
		var cv = document.createElement('canvas'); cv.className = 'upw-cursor-canvas'; cv.setAttribute('aria-hidden', 'true');
		document.body.appendChild(cv);
		var ctx = cv.getContext('2d'), dpr = Math.min(2, window.devicePixelRatio || 1);
		function resize() {
			cv.width = window.innerWidth * dpr; cv.height = window.innerHeight * dpr;
			cv.style.width = window.innerWidth + 'px'; cv.style.height = window.innerHeight + 'px';
			ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
		}
		resize(); window.addEventListener('resize', resize, { passive: true });
		var col = cfg.colorHex || cfg.color || '#2f74e6', px = mx, py = my, ripples = [], lastEmit = 0;
		var follow = cfg.canvasFollowScroll !== false, persistent = (mode === 'ink' || mode === 'fluid');
		var lastSX = window.pageXOffset || 0, lastSY = window.pageYOffset || 0, i;
		(function loop(t) {
			var sx = window.pageXOffset || 0, sy = window.pageYOffset || 0;
			// Persistent pixels: slide them with the page so ink/fluid stick to content.
			if (follow && persistent) {
				var dsx = sx - lastSX, dsy = sy - lastSY;
				if (dsx || dsy) {
					ctx.setTransform(1, 0, 0, 1, 0, 0);
					ctx.globalCompositeOperation = 'copy';
					ctx.drawImage(cv, -dsx * dpr, -dsy * dpr);
					ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
				}
			}
			lastSX = sx; lastSY = sy;

			ctx.globalCompositeOperation = 'destination-out';
			ctx.fillStyle = 'rgba(0,0,0,' + (mode === 'ink' ? 0.06 : 0.1) + ')';
			ctx.fillRect(0, 0, window.innerWidth, window.innerHeight);
			ctx.globalCompositeOperation = 'source-over';
			if (!reduce) {
				if (mode === 'ink') {
					ctx.strokeStyle = col; ctx.lineWidth = cfg.inkWidth || 6; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
					ctx.beginPath(); ctx.moveTo(px, py); ctx.lineTo(mx, my); ctx.stroke();
				} else if (mode === 'fluid') {
					// Solid-core soft blobs interpolated along the move = a clearly-visible liquid smear.
					var steps = Math.max(1, Math.round(Math.sqrt((mx - px) * (mx - px) + (my - py) * (my - py)) / 6));
					for (i = 0; i <= steps; i++) {
						var ix = px + (mx - px) * (i / steps), iy = py + (my - py) * (i / steps);
						var g = ctx.createRadialGradient(ix, iy, 0, ix, iy, 24);
						g.addColorStop(0, col); g.addColorStop(0.5, col); g.addColorStop(1, 'rgba(0,0,0,0)');
						ctx.fillStyle = g; ctx.beginPath(); ctx.arc(ix, iy, 24, 0, 6.2832); ctx.fill();
					}
				} else {
					if (t - lastEmit > 90) { lastEmit = t; ripples.push({ x: mx + (follow ? sx : 0), y: my + (follow ? sy : 0), r: 2 }); }
					ctx.strokeStyle = col; ctx.lineWidth = 2;
					for (i = ripples.length - 1; i >= 0; i--) {
						var rp = ripples[i]; rp.r += 2.3;
						ctx.globalAlpha = Math.max(0, 1 - rp.r / 80);
						ctx.beginPath(); ctx.arc(follow ? rp.x - sx : rp.x, follow ? rp.y - sy : rp.y, rp.r, 0, 6.2832); ctx.stroke();
						if (rp.r > 80) { ripples.splice(i, 1); }
					}
					ctx.globalAlpha = 1;
				}
			}
			px = mx; py = my; requestAnimationFrame(loop);
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
