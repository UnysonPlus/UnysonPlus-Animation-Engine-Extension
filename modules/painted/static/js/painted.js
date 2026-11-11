/**
 * Animation Engine — Painted: paints a decoration onto an element with real brushes (p5.brush) when it
 * scrolls into view, and draws it on.
 *
 * Each `.upw-painted[data-upw-paint]` element carries its config as JSON (style, medium, colours, size,
 * strength, spread, duration, delay, front, seed, fit, plus bleed/texture for the wash and gap/angle for
 * hatching). The paint engine (scene-paint.js, the Kinetic Scene element's own copy) is loaded once,
 * only when a painted element comes near the screen. Each element is painted ONCE into two canvases
 * (the fill and the line); the paint-on is then a CSS mask moved by the shared frame scheduler, so
 * nothing is repainted per frame. A painting is redrawn (finished) only when the element's size changes.
 * With reduced motion it is shown finished. Without WebGL2 the element simply stays unpainted.
 */
(function () {
	'use strict';

	var cfg = window.upwPaintedCfg || {};
	var MAX_PIXELS = 1600000; // a painting's canvas never exceeds this many device pixels

	function reduceMotion() {
		if (cfg.reducedMotion === false) { return false; }
		if (typeof window.upwReduceMotion === 'function') { return window.upwReduceMotion(); }
		return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
	}

	/* ---- The paint engine, loaded once ---- */
	var enginePromise = null;
	function engine() {
		if (window.UnysonPlusScenePaint) { return Promise.resolve(window.UnysonPlusScenePaint); }
		if (enginePromise) { return enginePromise; }
		enginePromise = new Promise(function (resolve, reject) {
			if (!cfg.engine) { reject(new Error('Painted: no paint engine URL.')); return; }
			var s = document.createElement('script');
			s.src = cfg.engine;
			s.async = true;
			s.onload = function () { return window.UnysonPlusScenePaint ? resolve(window.UnysonPlusScenePaint) : reject(new Error('Painted: the paint engine did not start.')); };
			s.onerror = function () { reject(new Error('Painted: could not load ' + cfg.engine)); };
			document.head.appendChild(s);
		});
		enginePromise.catch(function () { enginePromise = null; });
		return enginePromise;
	}

	/* ---- Small helpers ---- */
	function clamp01(v) { return v < 0 ? 0 : v > 1 ? 1 : v; }
	function ease(t) { return 0.5 - 0.5 * Math.cos(Math.PI * t); }
	function hash(str) {
		var h = 2166136261;
		for (var i = 0; i < str.length; i++) { h ^= str.charCodeAt(i); h = Math.imul(h, 16777619); }
		return (h >>> 0) % 9999 + 1;
	}
	function rng(seed) {
		var a = seed >>> 0;
		return function () {
			a = (a + 0x6D2B79F5) >>> 0;
			var t = Math.imul(a ^ (a >>> 15), 1 | a);
			t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
			return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
		};
	}

	/** The box round the element's content: its words, and its inline boxes, pictures and controls. */
	var LEAF = /^(IMG|SVG|VIDEO|CANVAS|IFRAME|INPUT|BUTTON|SELECT|TEXTAREA|PICTURE|svg)$/;
	function contentBox(el) {
		var box = null;
		function add(r) {
			if (!r || (r.width < 1 && r.height < 1)) { return; }
			if (!box) { box = { l: r.left, t: r.top, r: r.right, b: r.bottom }; return; }
			box.l = Math.min(box.l, r.left); box.t = Math.min(box.t, r.top);
			box.r = Math.max(box.r, r.right); box.b = Math.max(box.b, r.bottom);
		}
		(function walk(n) {
			for (var c = n.firstChild; c; c = c.nextSibling) {
				if (c.nodeType === 3) {
					if (/\S/.test(c.nodeValue)) {
						var rg = document.createRange();
						rg.selectNodeContents(c);
						var rs = rg.getClientRects();
						for (var i = 0; i < rs.length; i++) { add(rs[i]); }
					}
				} else if (c.nodeType === 1) {
					if (c.classList && c.classList.contains('upw-paint-host')) { continue; }
					var cs = getComputedStyle(c);
					if (cs.display === 'none' || cs.visibility === 'hidden') { continue; }
					if (LEAF.test(c.tagName) || /inline-(block|flex|grid)/.test(cs.display)) { add(c.getBoundingClientRect()); }
					else { walk(c); }
				}
			}
		})(el);
		return box;
	}

	/* ---- One painted element ---- */
	function Painting(el, index) {
		var o;
		try { o = JSON.parse(el.getAttribute('data-upw-paint') || '{}'); } catch (e) { o = {}; }
		this.el = el;
		this.o = o;
		this.seed = o.seed > 0 ? o.seed : hash((el.getAttribute('data-upw-paint') || '') + ':' + index + ':' + (el.id || el.className));
		this.painted = false;
		this.started = false;
		this.done = false;
		this.size = '';
	}

	/** Builds the outline and the layer's place, in CSS pixels relative to the element. */
	Painting.prototype.measure = function () {
		var el = this.el, o = this.o, style = o.style;
		var er = el.getBoundingClientRect();
		if (er.width < 2 || er.height < 2) { return null; }
		var useContent = o.fit === 'content';
		var cb = useContent ? contentBox(el) : null;
		var bx = cb ? { l: cb.l - er.left, t: cb.t - er.top, w: cb.r - cb.l, h: cb.b - cb.t } : { l: 0, t: 0, w: er.width, h: er.height };
		if (bx.w < 2 || bx.h < 2) { return null; }
		var s = +o.spread || 0;
		var w = bx.w, h = bx.h;
		var R = rng(this.seed);
		var pts = [], closed = true, curve = 0, sweep = null;

		if (style === 'underline') {
			closed = false; curve = 0.6;
			var y0 = h + Math.max(7, s * 0.9), n = 14, tilt = (R() - 0.5) * Math.min(14, h * 0.15), sag = h * 0.05 + 2;
			for (var i = 0; i <= n; i++) {
				var u = i / n;
				pts.push([-s * 0.5 + u * (w + s), y0 + Math.sin(u * Math.PI) * sag + (u - 0.5) * tilt]);
			}
			sweep = { kind: 'linear', x0: -s * 0.5, x1: w + s * 0.5 };
		} else if (style === 'circle') {
			closed = false; curve = 0.5;
			var cx = w / 2, cy = h / 2, rx = (w / 2) * 1.08 + s, ry = (h / 2) * 1.6 + s * 0.6, a0 = -Math.PI * (0.6 + R() * 0.15), turns = 1.08, m = 80, ph = R() * 6.28;
			for (var k = 0; k <= m; k++) {
				var t = k / m, a = a0 + t * turns * 2 * Math.PI, f = 1 + 0.03 * Math.sin(t * 9 + ph) - 0.03 * t;
				pts.push([cx + rx * f * Math.cos(a), cy + ry * f * Math.sin(a)]);
			}
			sweep = { kind: 'conic', cx: cx, cy: cy, from: a0 * 180 / Math.PI + 90, turns: turns };
		} else if (style === 'highlight') {
			var j = function (amt) { return (R() - 0.5) * amt; };
			var jy = Math.min(8, h * 0.12);
			pts = [[-s + j(6), -s * 0.3 + j(jy)], [w + s + j(6), -s * 0.1 + j(jy)], [w + s + j(6), h + s * 0.4 + j(jy)], [-s + j(6), h + s * 0.2 + j(jy)]];
			sweep = { kind: 'conic', cx: w / 2, cy: h / 2, from: Math.atan2(-(w / 2 + s), h / 2 + s) * 180 / Math.PI, turns: 1 };
		} else {
			// wash, frame, hatch: the box grown by the spread, with the element's own corner rounding.
			var rad = 0;
			if (!cb) { rad = parseFloat(getComputedStyle(el).borderTopLeftRadius) || 0; }
			var L = -s, T = -s, W = w + 2 * s, H = h + 2 * s, r = Math.min(rad + (rad ? s : 0), W / 2, H / 2);
			if (r < 1 && style === 'frame') {
				// A quick pencil sketch: four strokes that run past each other at the corners.
				closed = false;
				var ov = Math.min(10, 4 + s * 0.4), jt = function () { return (R() - 0.5) * 3; };
				pts = [[L - ov, T + jt()], [L + W + ov * 0.6, T + jt()], [L + W + jt(), T - ov * 0.4], [L + W + jt(), T + H + ov], [L + W + ov * 0.5, T + H + jt()], [L - ov * 0.8, T + H + jt()], [L + jt(), T + H + ov * 0.5], [L + jt(), T - ov]];
			} else if (r < 1) {
				pts = [[L, T], [L + W, T], [L + W, T + H], [L, T + H]];
			} else {
				var arc = function (acx, acy, from) { for (var q = 0; q <= 6; q++) { var aa = from + (q * Math.PI) / 12; pts.push([acx + r * Math.cos(aa), acy + r * Math.sin(aa)]); } };
				arc(L + W - r, T + r, -Math.PI / 2); arc(L + W - r, T + H - r, 0); arc(L + r, T + H - r, Math.PI / 2); arc(L + r, T + r, Math.PI);
				pts.unshift([L + r, T]);
			}
			sweep = { kind: 'conic', cx: w / 2, cy: h / 2, from: Math.atan2(-W / 2, H / 2) * 180 / Math.PI, turns: 1 };
		}

		// The layer reaches past the outline far enough for the brush and the watercolour bleed, and
		// never past the page's left or right edge (so it can never make the page scroll sideways).
		var line = 8 + (+o.size || 1) * 4;
		var bleedX = style === 'wash' ? (0.06 + 0.3 * (+o.bleed || 0)) * (w + 2 * s) : 0;
		var bleedY = style === 'wash' ? (0.06 + 0.3 * (+o.bleed || 0)) * (h + 2 * s) : 0;
		var minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
		for (var p = 0; p < pts.length; p++) {
			minX = Math.min(minX, pts[p][0]); maxX = Math.max(maxX, pts[p][0]);
			minY = Math.min(minY, pts[p][1]); maxY = Math.max(maxY, pts[p][1]);
		}
		var hl = bx.l + minX - line - bleedX, hr = bx.l + maxX + line + bleedX;
		var ht = bx.t + minY - line - bleedY, hb = bx.t + maxY + line + bleedY;
		var vw = document.documentElement.clientWidth;
		hl = Math.max(hl, -er.left); hr = Math.min(hr, vw - er.left);
		if (hr - hl < 2 || hb - ht < 2) { return null; }
		// Points relative to the layer's top-left.
		var ox = bx.l - hl, oy = bx.t - ht;
		var local = pts.map(function (pt) { return [pt[0] + ox, pt[1] + oy]; });
		sweep.cx = (sweep.cx !== undefined ? sweep.cx + ox : 0);
		sweep.cy = (sweep.cy !== undefined ? sweep.cy + oy : 0);
		if (sweep.kind === 'linear') { sweep.x0 += ox; sweep.x1 += ox; }
		// Where the layer sits inside the element (its padding box: absolute children start inside the border).
		var bl = el.clientLeft || 0, bt = el.clientTop || 0;
		return { left: hl - bl, top: ht - bt, width: hr - hl, height: hb - ht, pts: local, closed: closed, curve: curve, sweep: sweep };
	};

	Painting.prototype.paint = function (api) {
		var el = this.el, o = this.o, g = this.measure();
		if (!g) { return false; }
		if (getComputedStyle(el).position === 'static') { el.style.position = 'relative'; }
		var host = this.host;
		if (!host) {
			host = this.host = document.createElement('div');
			host.className = 'upw-paint-host' + (o.front ? ' is-front' : '');
			host.setAttribute('aria-hidden', 'true');
			this.fillCv = document.createElement('canvas');
			this.lineCv = document.createElement('canvas');
			host.appendChild(this.fillCv);
			host.appendChild(this.lineCv);
			el.appendChild(host);
		}
		host.style.left = g.left + 'px';
		host.style.top = g.top + 'px';
		host.style.width = g.width + 'px';
		host.style.height = g.height + 'px';
		this.geo = g;

		var dpr = Math.min(2, window.devicePixelRatio || 1);
		var q = Math.min(dpr, Math.sqrt(MAX_PIXELS / (g.width * g.height)));
		var W = Math.max(2, Math.round(g.width * q)), H = Math.max(2, Math.round(g.height * q));
		var pts = g.pts.map(function (pt) { return [pt[0] * q, pt[1] * q]; });
		// The highlight is a light watercolour with almost no bleed: a marker's streaky, see-through ink.
		var fillMode = o.style === 'wash' || o.style === 'highlight' ? 'watercolour' : o.style === 'hatch' ? 'hatch' : 'none';
		var job = {
			width: W, height: H, points: pts, closed: g.closed, curve: g.curve,
			medium: o.medium || 'pencil', line: !!o.line, lineColor: o.lineColor || '#1c1e22',
			fill: fillMode, fillColor: o.fillColor || '#8ecae6',
			opacity: Math.max(0.05, Math.min(1, +o.strength || 1)), weight: +o.size || 1,
			bleed: o.style === 'highlight' ? 0.04 : +o.bleed || 0, texture: o.style === 'highlight' ? 0.25 : o.texture !== undefined ? +o.texture : 0.5,
			gap: +o.gap || 7, angle: o.angle !== undefined ? +o.angle : -35,
			scale: q * 2, seed: this.seed, progress: 1, part: 'fill'
		};
		var ctx;
		if (fillMode !== 'none') { api.paint(job, this.fillCv); }
		else { this.fillCv.width = this.fillCv.height = 1; ctx = this.fillCv.getContext('2d'); if (ctx) { ctx.clearRect(0, 0, 1, 1); } }
		if (job.line) { job.part = 'line'; api.paint(job, this.lineCv); }
		else { this.lineCv.width = this.lineCv.height = 1; ctx = this.lineCv.getContext('2d'); if (ctx) { ctx.clearRect(0, 0, 1, 1); } }
		this.hasFill = fillMode !== 'none';
		this.hasLine = !!job.line;
		this.painted = true;
		this.size = Math.round(g.width) + 'x' + Math.round(g.height);
		return true;
	};

	/** Shows the painting at a point of its paint-on, 0 to 1. */
	Painting.prototype.show = function (t) {
		var g = this.geo;
		if (!g) { return; }
		var lineOnly = !this.hasFill;
		var lt = lineOnly ? clamp01(t) : clamp01(t / 0.7);
		var ft = clamp01((t - 0.25) / 0.75);
		var lineMask = '';
		if (lt < 1) {
			var sw = g.sweep, e = ease(lt);
			if (sw.kind === 'linear') {
				var x = sw.x0 + (sw.x1 - sw.x0) * e;
				var pc = (x / g.width) * 100;
				lineMask = 'linear-gradient(90deg, #000 ' + pc.toFixed(2) + '%, transparent ' + (pc + 1.5).toFixed(2) + '%)';
			} else {
				var deg = Math.min(360, e * 360 * (sw.turns || 1));
				lineMask = 'conic-gradient(from ' + sw.from.toFixed(1) + 'deg at ' + sw.cx.toFixed(1) + 'px ' + sw.cy.toFixed(1) + 'px, #000 ' + deg.toFixed(2) + 'deg, transparent ' + Math.min(360, deg + 1).toFixed(2) + 'deg)';
			}
		}
		var ls = this.lineCv.style;
		ls.opacity = lt > 0 ? '1' : '0';
		ls.webkitMaskImage = lineMask; ls.maskImage = lineMask;
		var fs = this.fillCv.style, fe = ease(ft);
		// The brushes draw hatching at full strength, so its Paint strength is applied to the layer here.
		fs.opacity = (fe * (this.o.style === 'hatch' ? Math.max(0.05, Math.min(1, +this.o.strength || 1)) : 1)).toFixed(3);
		var edge = -30 + 130 * fe;
		var fillMask = ft >= 1 ? '' : 'linear-gradient(100deg, #000 ' + edge.toFixed(1) + '%, transparent ' + (edge + 30).toFixed(1) + '%)';
		fs.webkitMaskImage = fillMask; fs.maskImage = fillMask;
	};

	Painting.prototype.play = function () {
		var self = this, o = this.o;
		if (this.started) { return; }
		this.started = true;
		var dur = (+o.duration || 0) * 1000, delay = (+o.delay || 0) * 1000;
		if (!dur || reduceMotion()) { this.show(1); this.done = true; return; }
		var t0 = 0;
		var tick = function (now) {
			if (!document.documentElement.contains(self.el)) { return false; }
			if (!t0) { t0 = now + delay; }
			var t = clamp01((now - t0) / dur);
			self.show(t);
			if (t >= 1) { self.done = true; return false; }
		};
		var RAF = window.upwAnimRaf;
		if (RAF && RAF.add) { RAF.add(tick); }
		else { (function loop(now) { if (tick(now) !== false) { requestAnimationFrame(loop); } })(performance.now()); }
	};

	/* ---- Wiring: paint when near the screen, draw on when in view, repaint on resize ---- */
	var queue = [], busy = false;
	function runQueue() {
		if (busy || !queue.length) { return; }
		busy = true;
		engine().then(function (api) {
			(function next() {
				var job = queue.shift();
				if (!job) { busy = false; return; }
				try { job(api); } catch (e) { if (window.console) { console.warn('Painted:', e); } }
				requestAnimationFrame(next);
			})();
		}, function (err) { busy = false; queue.length = 0; if (window.console) { console.warn(err); } });
	}
	function schedule(fn) { queue.push(fn); runQueue(); }

	var count = 0;
	function setup(el) {
		if (el.__upwPaint) { return; }
		var p = el.__upwPaint = new Painting(el, count++);
		var inView = false;
		var near = function () {
			schedule(function (api) {
				if (p.painted || !p.paint(api)) { return; }
				p.show(p.started ? 1 : 0);
				if (inView) { p.play(); }
			});
		};
		if ('IntersectionObserver' in window) {
			var nearIO = new IntersectionObserver(function (es) {
				if (es.some(function (e) { return e.isIntersecting; })) { nearIO.disconnect(); near(); }
			}, { rootMargin: '400px 0px' });
			nearIO.observe(el);
			var viewIO = new IntersectionObserver(function (es) {
				if (!es.some(function (e) { return e.isIntersecting; })) { return; }
				viewIO.disconnect();
				inView = true;
				if (p.painted) { p.play(); }
			}, { threshold: 0.2 });
			viewIO.observe(el);
		} else {
			inView = true;
			near();
		}
		if ('ResizeObserver' in window) {
			var timer = 0;
			new ResizeObserver(function () {
				if (!p.painted) { return; }
				clearTimeout(timer);
				timer = setTimeout(function () {
					var g = p.measure();
					if (!g || Math.round(g.width) + 'x' + Math.round(g.height) === p.size) { return; }
					schedule(function (api) {
						if (!p.paint(api)) { return; }
						p.show(p.started ? 1 : 0); // mid paint-on, the next frame puts it back where it was
					});
				}, 250);
			}).observe(el);
		}
	}

	function scan(root) {
		var scope = root && root.querySelectorAll ? root : document;
		var list = scope.querySelectorAll('.upw-painted[data-upw-paint]');
		for (var i = 0; i < list.length; i++) { setup(list[i]); }
	}

	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', function () { scan(document); }); }
	else { scan(document); }
	window.fwShortcodeInit = window.fwShortcodeInit || [];
	window.fwShortcodeInit.push(function (s) { scan(s); });
})();
