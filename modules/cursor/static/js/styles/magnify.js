/**
 * Animation Engine — Cursor "magnify" (standalone; only the chosen style's file loads site-wide).
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

	magnify();
	if (cfg.clickRipple || cfg.clickBurst) { clickFx(); }

	function magnify() {
		var scope = cfg.magnifyScope || 'images';
		if (scope === 'all') { return magnifyAll(); }
		var el = make('upw-cursor--magnify upw-cursor-primary');
		var zoom = cfg.zoom || 2, srcEl = null;
		function find(node) {
			if (!node || !node.closest) { return null; }
			var img = node.closest('img');
			if (img && (img.currentSrc || img.src)) { return { el: img, url: img.currentSrc || img.src }; }
			if (scope === 'media') {
				var n = node;
				while (n && n !== document.body) {
					var m = ('' + getComputedStyle(n).backgroundImage).match(/url\((['"]?)(.*?)\1\)/);
					if (m && m[2]) { return { el: n, url: m[2] }; }
					n = n.parentElement;
				}
			}
			return null;
		}
		document.addEventListener('pointerover', function (e) {
			var f = find(e.target);
			if (f) { srcEl = f.el; el.style.backgroundImage = 'url("' + f.url + '")'; el.classList.add('is-on'); }
		}, { passive: true });
		document.addEventListener('pointerout', function () {
			if (srcEl) { srcEl = null; el.classList.remove('is-on'); el.style.backgroundImage = ''; }
		}, { passive: true });
		RAF.add(function () {
			place(el, mx, my);
			if (srcEl) {
				var r = srcEl.getBoundingClientRect(), d = el.getBoundingClientRect();
				var bw = r.width * zoom, bh = r.height * zoom;
				el.style.backgroundSize = bw + 'px ' + bh + 'px';
				el.style.backgroundPosition = (-((mx - r.left) / r.width) * bw + d.width / 2) + 'px ' + (-((my - r.top) / r.height) * bh + d.height / 2) + 'px';
			}
		});
	}

	function magnifyAll() {
		var el = make('upw-cursor--magnify upw-cursor--magnify-all upw-cursor-primary is-on');
		var stage = document.createElement('div'); stage.className = 'upw-magnify-clone'; el.appendChild(stage);
		var zoom = cfg.zoom || 2, clone = null;
		function build() {
			if (clone) { clone.remove(); }
			clone = document.body.cloneNode(true);
			// Drop our own cursor DOM from the clone so it doesn't recurse / show stray bits.
			var junk = clone.querySelectorAll('[class*="upw-cursor"], [class*="upw-goo"], [class*="upw-magnify"], canvas.upw-cursor-canvas, script, noscript');
			for (var i = 0; i < junk.length; i++) { junk[i].parentNode && junk[i].parentNode.removeChild(junk[i]); }
			clone.style.margin = '0';
			stage.appendChild(clone);
		}
		build();
		// Re-snapshot on resize (layout changes); cheap enough vs. per-frame.
		window.addEventListener('resize', build, { passive: true });
		var R = 0;
		RAF.add(function () {
			place(el, mx, my);
			if (!R) { R = (el.offsetWidth || 80) / 2; }
			var sx = window.pageXOffset || 0, sy = window.pageYOffset || 0;
			var docX = (mx + sx) * zoom, docY = (my + sy) * zoom;
			stage.style.transform = 'translate(' + (R - docX) + 'px,' + (R - docY) + 'px) scale(' + zoom + ')';
		});
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
