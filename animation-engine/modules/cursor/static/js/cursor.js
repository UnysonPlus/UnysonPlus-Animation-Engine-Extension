/**
 * Animation Engine — Cursor runtime.
 *
 * Builds a custom cursor from window.upwCursorCfg. Most styles are a single element
 * tracking the pointer (CSS does the shape); dot_ring adds a lagging ring; comet spawns
 * a fading trail; particles emit a decaying swarm; elastic squashes with velocity; arrow
 * rotates toward motion; radar pulses; spotlight is a full-screen mask. Modifiers (grow,
 * magnetic, blend, hide-native) layer on top. Skips touch; reduced motion stops trailing.
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

	if (style === 'spotlight') { spotlight(); }
	else if (style === 'comet') { comet(); }
	else if (style === 'particles') { particles(); }
	else if (style === 'elastic') { elastic(); }
	else if (style === 'arrow') { arrow(); }
	else if (style === 'echo' || style === 'firefly' || style === 'confetti' || style === 'bubble') { swarm(style); }
	else if (style === 'spring') { spring(); }
	else if (style === 'streak') { streak(); }
	else if (style === 'rope') { rope(); }
	else if (style === 'metaball') { metaball(); }
	else if (style === 'label') { label(); }
	else if (style === 'sticky') { sticky(); }
	else if (style === 'word_trail') { wordTrail(); }
	else if (style === 'reveal') { reveal(); }
	else if (style === 'magnify') { magnify(); }
	else if (style === 'ink') { canvasFx('ink'); }
	else if (style === 'fluid') { canvasFx('fluid'); }
	else if (style === 'distort') { canvasFx('distort'); }
	else { shapes(); }

	// Click feedback modifiers — layered on top of ANY style.
	if (cfg.clickRipple || cfg.clickBurst) { clickFx(); }

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

	/* Swarm of small dots spawned at the head that drift + fade, recycled from a pool. */
	function particles() {
		var head = make('upw-cursor--dot upw-cursor-primary');
		var N = Math.max(3, Math.min(24, cfg.count || 8)), pool = [], i;
		for (i = 0; i < N; i++) {
			var p = make('upw-cursor-particle');
			pool.push({ el: p, life: 0, x: mx, y: my, vx: 0, vy: 0 });
		}
		var idx = 0, last = 0;
		(function loop(t) {
			var pp = pos(); place(head, pp.x, pp.y);
			if (!reduce) {
				if (!last) { last = t; }
				if (t - last > 38) {
					last = t;
					var q = pool[idx % N]; idx++;
					q.x = pp.x; q.y = pp.y; q.life = 1;
					q.vx = (Math.random() - 0.5) * 0.7; q.vy = (Math.random() - 0.5) * 0.7 - 0.15;
				}
			}
			for (i = 0; i < N; i++) {
				var s = pool[i];
				if (s.life > 0) {
					s.life = Math.max(0, s.life - 0.03);
					s.x += s.vx; s.y += s.vy;
					s.el.style.opacity = String(s.life * 0.8);
					s.el.style.transform = 'translate(' + s.x.toFixed(1) + 'px,' + s.y.toFixed(1) + 'px) translate(-50%,-50%) scale(' + (0.3 + s.life * 0.7).toFixed(2) + ')';
				}
			}
			requestAnimationFrame(loop);
		})(0);
	}

	/* A ring that squashes & stretches along its velocity vector (gooey feel). */
	function elastic() {
		var el = make('upw-cursor--elastic upw-cursor-primary');
		var cx = mx, cy = my, px = mx, py = my;
		var amt = cfg.elastic != null ? cfg.elastic : 0.5;
		(function loop() {
			var p = pos();
			cx += (p.x - cx) * 0.2; cy += (p.y - cy) * 0.2;
			var dx = cx - px, dy = cy - py; px = cx; py = cy;
			var d = reduce ? 0 : Math.min(0.6, (Math.sqrt(dx * dx + dy * dy) / 40) * (0.5 + amt));
			var ang = Math.atan2(dy, dx) * 180 / Math.PI;
			el.style.transform = 'translate(' + cx.toFixed(1) + 'px,' + cy.toFixed(1) + 'px) translate(-50%,-50%) rotate(' + ang.toFixed(1) + 'deg) scale(' + (1 + d).toFixed(2) + ',' + (1 - d * 0.6).toFixed(2) + ')';
			requestAnimationFrame(loop);
		})();
	}

	/* A triangle that eases toward the pointer and rotates to face the travel direction. */
	function arrow() {
		var el = make('upw-cursor--arrow upw-cursor-primary');
		var cx = mx, cy = my, ang = 0;
		(function loop() {
			var p = pos();
			var dx = p.x - cx, dy = p.y - cy;
			cx += dx * 0.25; cy += dy * 0.25;
			if (!reduce && Math.abs(dx) + Math.abs(dy) > 0.6) { ang = Math.atan2(dy, dx) * 180 / Math.PI; }
			el.style.transform = 'translate(' + cx.toFixed(1) + 'px,' + cy.toFixed(1) + 'px) translate(-50%,-50%) rotate(' + ang.toFixed(1) + 'deg)';
			requestAnimationFrame(loop);
		})();
	}

	/* Generic trailing swarm — one pool, per-kind spawn + physics (echo/firefly/confetti/bubble). */
	function swarm(kind) {
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
					else if (kind === 'confetti') { q.vx = (Math.random() - 0.5) * 3; q.vy = -Math.random() * 2 - 0.5; q.vr = (Math.random() - 0.5) * 24; }
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

	/* Dot that overshoots and springs back (critically-damped-ish spring). */
	function spring() {
		var el = make('upw-cursor--dot upw-cursor-primary');
		var x = mx, y = my, vx = 0, vy = 0, k = reduce ? 1 : 0.18, damp = 0.72;
		(function loop() {
			var p = pos();
			vx = (vx + (p.x - x) * k) * damp; vy = (vy + (p.y - y) * k) * damp;
			x += vx; y += vy; place(el, x, y);
			requestAnimationFrame(loop);
		})();
	}

	/* Filled teardrop that elongates along its velocity vector. */
	function streak() {
		var el = make('upw-cursor--streak upw-cursor-primary');
		var cx = mx, cy = my, px = mx, py = my;
		(function loop() {
			var p = pos();
			cx += (p.x - cx) * 0.25; cy += (p.y - cy) * 0.25;
			var dx = cx - px, dy = cy - py; px = cx; py = cy;
			var spd = reduce ? 0 : Math.min(1.4, Math.sqrt(dx * dx + dy * dy) / 22);
			var ang = Math.atan2(dy, dx) * 180 / Math.PI;
			el.style.transform = 'translate(' + cx.toFixed(1) + 'px,' + cy.toFixed(1) + 'px) translate(-50%,-50%) rotate(' + ang.toFixed(1) + 'deg) scale(' + (1 + spd).toFixed(2) + ',' + (1 - spd * 0.4).toFixed(2) + ')';
			requestAnimationFrame(loop);
		})();
	}

	/* A stretchy band drawn between a lagging tail point and the head dot. */
	function rope() {
		var line = make('upw-cursor--rope', false);
		var head = make('upw-cursor--dot upw-cursor-primary');
		var tx = mx, ty = my;
		(function loop() {
			var p = pos(); place(head, p.x, p.y);
			tx += (p.x - tx) * (reduce ? 1 : 0.2); ty += (p.y - ty) * (reduce ? 1 : 0.2);
			var dx = p.x - tx, dy = p.y - ty, len = Math.sqrt(dx * dx + dy * dy), ang = Math.atan2(dy, dx) * 180 / Math.PI;
			line.style.width = len.toFixed(1) + 'px';
			line.style.transform = 'translate(' + tx.toFixed(1) + 'px,' + ty.toFixed(1) + 'px) rotate(' + ang.toFixed(1) + 'deg)';
			requestAnimationFrame(loop);
		})();
	}

	/* Two blobs that merge like liquid via an SVG gooey filter. */
	function metaball() {
		var ns = 'http://www.w3.org/2000/svg';
		var svg = document.createElementNS(ns, 'svg');
		svg.setAttribute('width', '0'); svg.setAttribute('height', '0');
		svg.style.position = 'absolute';
		svg.innerHTML = '<defs><filter id="upw-goo"><feGaussianBlur in="SourceGraphic" stdDeviation="6" result="b"/>'
			+ '<feColorMatrix in="b" mode="matrix" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 20 -9" result="g"/>'
			+ '<feBlend in="SourceGraphic" in2="g"/></filter></defs>';
		document.body.appendChild(svg);
		var wrap = make('upw-cursor-goo', false);
		var a = document.createElement('div'); a.className = 'upw-goo-ball upw-goo-a'; wrap.appendChild(a);
		var b = document.createElement('div'); b.className = 'upw-goo-ball upw-goo-b'; wrap.appendChild(b);
		var rx = mx, ry = my, lag = reduce ? 1 : (cfg.trail != null ? cfg.trail : 0.18);
		(function loop() {
			var p = pos(); place(a, p.x, p.y);
			rx += (p.x - rx) * lag; ry += (p.y - ry) * lag; place(b, rx, ry);
			requestAnimationFrame(loop);
		})();
	}

	var LABEL_SEL = '[data-cursor-label], a, button, [role="button"], .btn, .sc-btn';

	/* A pill following the pointer. Shows the default label persistently; any element with a
	   data-cursor-label overrides it on hover. Blank default => dot until over such an element. */
	function label() {
		var el = make('upw-cursor--label upw-cursor-primary');
		var txt = document.createElement('span'); txt.className = 'upw-cursor-label-txt'; el.appendChild(txt);
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
		(function loop() { x += (mx - x) * 0.2; y += (my - y) * 0.2; place(el, x, y); requestAnimationFrame(loop); })();
	}

	/* A ring that snaps to and wraps the hovered interactive element (morphing cursor). */
	function sticky() {
		var el = make('upw-cursor--sticky upw-cursor-primary');
		var target = null;
		document.addEventListener('pointerover', function (e) {
			var t = e.target.closest ? e.target.closest(LABEL_SEL) : null; if (t) { target = t; }
		}, { passive: true });
		document.addEventListener('pointerout', function (e) {
			var t = e.target.closest ? e.target.closest(LABEL_SEL) : null; if (t) { target = null; }
		}, { passive: true });
		var x = mx, y = my;
		(function loop() {
			if (target) {
				var r = target.getBoundingClientRect();
				x += (r.left + r.width / 2 - x) * 0.2; y += (r.top + r.height / 2 - y) * 0.2;
				el.style.width = (r.width + 14) + 'px'; el.style.height = (r.height + 14) + 'px';
				el.style.borderRadius = (parseFloat(getComputedStyle(target).borderRadius) || 6) + 6 + 'px';
				el.classList.add('is-stuck');
			} else {
				x += (mx - x) * 0.3; y += (my - y) * 0.3;
				el.style.width = ''; el.style.height = ''; el.style.borderRadius = '';
				el.classList.remove('is-stuck');
			}
			place(el, x, y); requestAnimationFrame(loop);
		})();
	}

	/* A word repeated down a fading trail behind the pointer. */
	function wordTrail() {
		var word = cfg.word || 'scroll', N = reduce ? 1 : 9, segs = [], i;
		var wf = cfg.wordFont || {};
		for (i = 0; i < N; i++) {
			var s = make('upw-cursor--wordseg'); s.textContent = word;
			// Typography-v2 props applied once (transform is set per-frame, so keep them separate).
			if (wf.family) { s.style.fontFamily = wf.family; }
			if (wf.weight) { s.style.fontWeight = wf.weight; }
			if (wf.size) { s.style.fontSize = wf.size + 'px'; }
			if (wf.lineHeight) { s.style.lineHeight = wf.lineHeight + 'px'; }
			if (wf.letterSpacing) { s.style.letterSpacing = wf.letterSpacing + 'px'; }
			if (wf.style) { s.style.fontStyle = wf.style; }
			s.style.opacity = String((1 - i / N) * 0.9);
			segs.push({ el: s, x: mx, y: my });
		}
		(function loop() {
			var px = pos().x, py = pos().y;
			for (i = 0; i < N; i++) {
				var k = Math.max(0.12, 0.4 - i * 0.03);
				segs[i].x += (px - segs[i].x) * k; segs[i].y += (py - segs[i].y) * k;
				place(segs[i].el, segs[i].x, segs[i].y); px = segs[i].x; py = segs[i].y;
			}
			requestAnimationFrame(loop);
		})();
	}

	/* A circular window whose image stays fixed to the viewport, so moving it reveals the image. */
	function reveal() {
		var el = make('upw-cursor--reveal upw-cursor-primary');
		if (cfg.revealImage) { el.style.backgroundImage = 'url("' + cfg.revealImage + '")'; }
		var x = mx, y = my;
		(function loop() { x += (mx - x) * 0.25; y += (my - y) * 0.25; place(el, x, y); requestAnimationFrame(loop); })();
	}

	/* Magnify lens. Scope: 'images' / 'media' (img + background-image) = light image reposition;
	   'all' = clone the whole page into the lens and scale it (magnifies text + everything). */
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
		(function loop() {
			place(el, mx, my);
			if (srcEl) {
				var r = srcEl.getBoundingClientRect(), d = el.getBoundingClientRect();
				var bw = r.width * zoom, bh = r.height * zoom;
				el.style.backgroundSize = bw + 'px ' + bh + 'px';
				el.style.backgroundPosition = (-((mx - r.left) / r.width) * bw + d.width / 2) + 'px ' + (-((my - r.top) / r.height) * bh + d.height / 2) + 'px';
			}
			requestAnimationFrame(loop);
		})();
	}

	/* Total-maximization lens: a scaled clone of the whole page, clipped to the lens circle.
	   The clone is a one-time snapshot (dynamic/lazy/video/iframe content won't update in it). */
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
		(function loop() {
			place(el, mx, my);
			if (!R) { R = (el.offsetWidth || 80) / 2; }
			var sx = window.pageXOffset || 0, sy = window.pageYOffset || 0;
			var docX = (mx + sx) * zoom, docY = (my + sy) * zoom;
			stage.style.transform = 'translate(' + (R - docX) + 'px,' + (R - docY) + 'px) scale(' + zoom + ')';
			requestAnimationFrame(loop);
		})();
	}

	/* Shared full-viewport canvas trail — ink stroke / fluid smear / ripple rings.
	   Colour is a resolved hex (canvas can't use var()). When "follow page scroll" is on:
	   ink/fluid persist as pixels and are shifted by the scroll delta each frame; ripple is
	   procedural, so its origins are stored in DOCUMENT coords and drawn offset by scroll. */
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

	/* Click ripple + burst — spawns transient elements at the click point. */
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
