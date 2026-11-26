/**
 * Animation Engine — the scroll core (window.upwScroll). Roadmap Phase 9a.
 *
 * The engine's own scroll-linked motion, in place of GSAP + ScrollTrigger:
 *
 *   upwScroll.create(vars)            a scroll trigger (start / end, scrub, pin, toggle actions, callbacks)
 *   upwScroll.tween(targets, from, to) a tween on the browser's Web Animations (time-based or scrubbed)
 *   upwScroll.value(vars)             a tween of a plain number (counters, masks), driven by the frame loop
 *   upwScroll.timeline(vars)          tweens placed on one time line, played or scrubbed as one
 *   upwScroll.getAll() / refresh() / update() / kill(t)
 *   upwScroll.ease(name)              'power3.out', 'back.out(1.7)', 'elastic.out(1,0.5)', cubic-bezier(…), linear(…) → fn
 *
 * Positions ("start" / "end") read like GSAP's: "<element edge> <viewport edge>", each edge top | center | bottom |
 * N% | Npx ("top 85%" = when the element's top reaches 85% down the screen). "end" may also be relative to the start:
 * "+=100%" (one screen height) or "+=400" / "+=400px".
 *
 * Pinning holds the element in place (position: fixed) while the page scrolls through the trigger's range; a spacer
 * keeps the page's height, so the content below still follows. Limit, as with every fixed-position pin: an ancestor
 * with a transform, filter or perspective makes "fixed" relative to that ancestor instead of the screen.
 *
 * Everything runs on the shared frame loop (window.upwAnimRaf), only while something is moving.
 */
(function () {
	'use strict';
	if (typeof window === 'undefined' || window.upwScroll) { return; }

	var raf = window.upwAnimRaf;
	var doc = document, docEl = doc.documentElement;
	function sy() { return window.pageYOffset || docEl.scrollTop || 0; }
	function vh() { return window.innerHeight || docEl.clientHeight || 1; }
	function now() { return (window.performance && performance.now) ? performance.now() : Date.now(); }
	function clamp(v, a, b) { return v < a ? a : (v > b ? b : v); }
	function toArr(t) {
		if (!t) { return []; }
		if (typeof t === 'string') { return [].slice.call(doc.querySelectorAll(t)); }
		if (t.nodeType || t === window) { return [ t ]; }
		return [].slice.call(t);
	}

	/* ------------------------------------------------------------------ eases */

	// One set of curves for the whole engine: window.upwEase (static/js/upw-raf.js, this file's dependency) knows
	// the named curves (power3.out, back.out(1.7), elastic, bounce…) and every CSS timing function.
	var EASES = {}, LIN = function (t) { return t; };
	/** An ease name (GSAP-style) or a CSS timing function → a 0..1 function. Unknown → power2.out. */
	function ease(name) {
		if (typeof name === 'function') { return name; }
		name = String(name == null ? 'power2.out' : name).trim();
		if (EASES[name]) { return EASES[name]; }
		var E = window.upwEase, f = E ? E(name) || E('power2.out') : null;
		return (EASES[name] = f || LIN);
	}
	/** The same curve as a CSS timing function the browser can run (a linear() of samples, or a keyword). */
	var CSS_EASE = {}, LINEAR_OK = !!(window.CSS && CSS.supports && CSS.supports('transition-timing-function', 'linear(0, 1)'));
	function cssEase(name) {
		if (typeof name === 'string' && /^(linear\(|cubic-bezier\(|steps\(|ease|linear$)/.test(name.trim())) { return name.trim(); }
		var key = String(name);
		if (CSS_EASE[key]) { return CSS_EASE[key]; }
		var f = ease(name), out;
		if (LINEAR_OK) {
			var pts = [], n = 48;
			for (var i = 0; i <= n; i++) { pts.push(+f(i / n).toFixed(4)); }
			out = 'linear(' + pts.join(', ') + ')';
		} else {
			out = /\.in$/.test(key) ? 'ease-in' : (/inOut/.test(key) ? 'ease-in-out' : 'ease-out');
		}
		CSS_EASE[key] = out;
		return out;
	}

	/* ----------------------------------------------------------- style values */

	var TF = { x: 1, y: 1, z: 1, xPercent: 1, yPercent: 1, scale: 1, scaleX: 1, scaleY: 1, rotation: 1, rotate: 1, rotationX: 1, rotationY: 1, rotationZ: 1, skewX: 1, skewY: 1, transformPerspective: 1 };
	var IDENT = { x: 0, y: 0, z: 0, xPercent: 0, yPercent: 0, scale: 1, scaleX: 1, scaleY: 1, rotation: 0, rotate: 0, rotationX: 0, rotationY: 0, rotationZ: 0, skewX: 0, skewY: 0 };
	function unit(v, u) { return typeof v === 'number' ? v + u : String(v); }
	/** One transform string (a fixed order, like GSAP's). `keys` names the functions to write even at their resting
	 *  value, so both ends of an animation list the same functions (a 0 → 360° spin then really turns once). */
	function transformOf(v, keys) {
		keys = keys || v;
		var has = function () { for (var i = 0; i < arguments.length; i++) { if (keys[arguments[i]] != null) { return true; } } return false; };
		var s = '';
		if (v.transformPerspective) { s += 'perspective(' + unit(v.transformPerspective, 'px') + ') '; }
		if (has('x', 'y', 'z')) { s += 'translate3d(' + unit(v.x || 0, 'px') + ', ' + unit(v.y || 0, 'px') + ', ' + unit(v.z || 0, 'px') + ') '; }
		if (has('xPercent', 'yPercent')) { s += 'translate(' + (v.xPercent || 0) + '%, ' + (v.yPercent || 0) + '%) '; }
		if (has('rotationX')) { s += 'rotateX(' + unit(v.rotationX || 0, 'deg') + ') '; }
		if (has('rotationY')) { s += 'rotateY(' + unit(v.rotationY || 0, 'deg') + ') '; }
		if (has('rotation', 'rotate', 'rotationZ')) { s += 'rotate(' + unit(v.rotation != null ? v.rotation : (v.rotate != null ? v.rotate : (v.rotationZ || 0)), 'deg') + ') '; }
		if (has('skewX', 'skewY')) { s += 'skew(' + unit(v.skewX || 0, 'deg') + ', ' + unit(v.skewY || 0, 'deg') + ') '; }
		if (has('scale', 'scaleX', 'scaleY')) {
			var sx = v.scaleX != null ? v.scaleX : (v.scale != null ? v.scale : 1), syv = v.scaleY != null ? v.scaleY : (v.scale != null ? v.scale : 1);
			s += 'scale(' + sx + ', ' + syv + ') ';
		}
		return s.trim() || 'none';
	}
	var SKIP = { duration: 1, delay: 1, ease: 1, stagger: 1, scrollTrigger: 1, onComplete: 1, onUpdate: 1, onStart: 1, immediateRender: 1, keep: 1, overwrite: 1, paused: 1 };
	/** A pair of Web Animations keyframes for a from / to pair of value objects. */
	function keyframes(el, from, to) {
		var a = {}, b = {}, usesTf = false, k, cs = null;
		var keys = {}; for (k in from) { keys[k] = 1; } for (k in to) { keys[k] = 1; }
		var fa = {}, fb = {};
		for (k in keys) {
			if (SKIP[k]) { continue; }
			if (TF[k]) {
				usesTf = true;
				fa[k] = from[k] != null ? from[k] : (IDENT[k] != null ? IDENT[k] : to[k]);
				fb[k] = to[k] != null ? to[k] : (IDENT[k] != null ? IDENT[k] : from[k]);
				continue;
			}
			var prop = k === 'blur' ? 'filter' : k;
			var va = from[k], vb = to[k];
			if (k === 'blur') { va = va != null ? 'blur(' + unit(va, 'px') + ')' : 'blur(0px)'; vb = vb != null ? 'blur(' + unit(vb, 'px') + ')' : 'blur(0px)'; }
			if (va == null || vb == null) {
				cs = cs || getComputedStyle(el);
				var cur = cs[prop];
				if (va == null) { va = cur; } else { vb = cur; }
			}
			a[prop] = String(va); b[prop] = String(vb);
		}
		if (usesTf) {
			if (fa.transformPerspective == null && fb.transformPerspective != null) { fa.transformPerspective = fb.transformPerspective; }
			if (fb.transformPerspective == null && fa.transformPerspective != null) { fb.transformPerspective = fa.transformPerspective; }
			var union = {}; for (k in fa) { union[k] = 1; } for (k in fb) { union[k] = 1; }
			a.transform = transformOf(fa, union); b.transform = transformOf(fb, union);
		}
		return [ a, b ];
	}
	/** Is this "to" state the element's natural look? Then the tween removes itself at the end, so later CSS (a hover
	 *  transform, a theme change) is not overridden by a finished animation. */
	function natural(to) {
		for (var k in to) {
			if (SKIP[k] || k === 'transformPerspective') { continue; } // perspective alone changes nothing at rest
			if (TF[k]) { if (to[k] !== IDENT[k]) { return false; } continue; }
			if (k === 'opacity' && +to[k] === 1) { continue; }
			if (k === 'blur' && +to[k] === 0) { continue; }
			if (k === 'filter' && /^(none|blur\(0(px)?\))$/.test(String(to[k]))) { continue; }
			if ((k === 'clipPath' || k === 'webkitClipPath') && /^inset\(0(%|px)?( 0(%|px)?){0,3}\)$/.test(String(to[k]).replace(/\s+/g, ' '))) { continue; }
			return false;
		}
		return true;
	}
	/** Set values at once (no animation), like gsap.set. */
	function set(targets, v) {
		toArr(targets).forEach(function (el) {
			var tf = {}, has = false;
			for (var k in v) {
				if (TF[k]) { tf[k] = v[k]; has = true; }
				else if (k === 'blur') { el.style.filter = 'blur(' + unit(v[k], 'px') + ')'; }
				else { el.style[k] = typeof v[k] === 'number' && !/opacity|zIndex|flex|order|lineHeight/.test(k) ? v[k] + 'px' : v[k]; }
			}
			if (has) { el.style.transform = transformOf(tf); }
		});
	}

	/* ------------------------------------------------------------------ tweens */

	function staggerDelays(n, st) {
		var each = typeof st === 'number' ? st : (st && st.each) || 0, from = (st && st.from) || 'start', out = [], i, c = (n - 1) / 2;
		for (i = 0; i < n; i++) {
			var d;
			if (from === 'end') { d = n - 1 - i; }
			else if (from === 'center') { d = Math.abs(i - c); }
			else if (from === 'edges') { d = c - Math.abs(i - c); }
			else if (from === 'random') { d = Math.random() * (n - 1); }
			else if (typeof from === 'number') { d = Math.abs(i - from); }
			else { d = i; }
			out.push(d * each);
		}
		return out;
	}

	/**
	 * tween(targets, from, to): Web Animations on each target. to.duration (s, default 0.5), to.delay, to.ease,
	 * to.stagger (a number or { each, from: start|end|center|edges|random }), to.onComplete, to.scrollTrigger (vars for
	 * create(); the trigger then plays it, or scrubs it when scrub is set). Returns a controller:
	 * play / reverse / restart / pause / reset / complete / progress(p) / kill, and .duration (s, with the stagger).
	 */
	function tween(targets, from, to) {
		from = from || {}; to = to || {};
		var els = toArr(targets).filter(function (e) { return e && e.animate; });
		var dur = Math.max(0.001, to.duration != null ? +to.duration : 0.5) * 1000;
		var delay = (+to.delay || 0) * 1000, delays = staggerDelays(els.length, to.stagger);
		var easing = cssEase(to.ease), clear = to.keep ? false : natural(to);
		var total = delay + dur + (delays.length ? Math.max.apply(null, delays) * 1000 : 0);
		var anims = els.map(function (el, i) {
			var kf = keyframes(el, from, to);
			var an = el.animate(kf, { duration: dur, delay: delay + delays[i] * 1000, easing: easing, fill: 'both' });
			an.pause();
			an.currentTime = 0;
			return an;
		});
		var done = 0, dir = 1, ended = false;
		var ctl = {
			targets: els, duration: total / 1000, anims: anims, vars: to,
			play: function () { dir = 1; ended = false; run(1); return ctl; },
			reverse: function () { dir = -1; ended = false; run(-1); return ctl; },
			restart: function () { anims.forEach(function (a) { a.currentTime = 0; }); return ctl.play(); },
			pause: function () { anims.forEach(function (a) { if (a.playState !== 'idle') { a.pause(); } }); return ctl; },
			resume: function () { return dir > 0 ? ctl.play() : ctl.reverse(); },
			reset: function () { anims.forEach(function (a) { a.pause(); a.currentTime = 0; }); return ctl; },
			complete: function () { ctl.progress(1); finish(); return ctl; },
			progress: function (p) {
				if (p == null) { var a0 = anims[0]; return a0 && a0.currentTime != null ? clamp(a0.currentTime / total, 0, 1) : 0; }
				anims.forEach(function (a) { if (a.playState === 'idle') { a.pause(); } a.pause(); a.currentTime = clamp(p, 0, 1) * total; });
				return ctl;
			},
			kill: function () { anims.forEach(function (a) { a.cancel(); }); if (ctl.scrollTrigger) { ctl.scrollTrigger.kill(); } }
		};
		function finish() {
			if (ended) { return; } ended = true;
			if (dir > 0 && clear) { anims.forEach(function (a) { a.cancel(); }); }
			if (dir > 0 && typeof to.onComplete === 'function') { try { to.onComplete(); } catch (e) {} }
		}
		function run(d) {
			done = 0;
			if (!anims.length) { if (d > 0) { finish(); } return; }
			anims.forEach(function (a) {
				if (a.playState === 'idle') { a.pause(); a.currentTime = d > 0 ? 0 : total; }
				a.playbackRate = d;
				a.onfinish = function () { if (++done >= anims.length && dir === d) { if (d > 0) { finish(); } } };
				a.play();
			});
		}
		if (to.scrollTrigger) {
			var stv = to.scrollTrigger.nodeType ? { trigger: to.scrollTrigger } : to.scrollTrigger;
			stv.animation = ctl;
			if (!stv.trigger) { stv.trigger = els[0]; }
			ctl.scrollTrigger = create(stv);
		} else if (to.paused !== true) {
			ctl.play();
		}
		return ctl;
	}

	/** A tween of plain numbers on an object, eased by hand on the frame loop (counters, masks, anything a style can't
	 *  express). vars: { target: obj, to: { v: 100 }, duration, delay, ease, onUpdate, onComplete, scrollTrigger, paused }. */
	function value(vars) {
		var obj = vars.target || {}, to = vars.to || {}, from = {}, k, f = ease(vars.ease || 'power2.out');
		for (k in to) { from[k] = +obj[k] || 0; }
		var dur = Math.max(0.001, vars.duration != null ? +vars.duration : 0.5) * 1000, delay = (+vars.delay || 0) * 1000;
		var p = 0, dir = 1, t0 = 0, p0 = 0, running = false;
		function apply(q) {
			p = clamp(q, 0, 1);
			var e = f(p);
			for (var key in to) { obj[key] = from[key] + (to[key] - from[key]) * e; }
			if (vars.onUpdate) { try { vars.onUpdate(p); } catch (err) {} }
		}
		function tick(t) {
			if (!running) { return false; }
			var el = t - t0 - (dir > 0 && p0 === 0 ? delay : 0);
			if (el < 0) { return; }
			var q = p0 + dir * el / dur;
			apply(q);
			if ((dir > 0 && p >= 1) || (dir < 0 && p <= 0)) {
				running = false;
				if (dir > 0 && vars.onComplete) { try { vars.onComplete(); } catch (err) {} }
				return false;
			}
		}
		function go(d) { dir = d; p0 = p; t0 = now(); running = true; if (raf) { raf.add(tick); } else { apply(d > 0 ? 1 : 0); } }
		var ctl = {
			duration: (dur + delay) / 1000, vars: vars,
			play: function () { go(1); return ctl; }, reverse: function () { go(-1); return ctl; },
			restart: function () { p = 0; go(1); return ctl; }, pause: function () { running = false; return ctl; },
			resume: function () { go(dir); return ctl; }, reset: function () { running = false; apply(0); return ctl; },
			complete: function () { running = false; apply(1); if (vars.onComplete) { vars.onComplete(); } return ctl; },
			progress: function (q) { if (q == null) { return p; } running = false; apply(q); return ctl; },
			kill: function () { running = false; if (ctl.scrollTrigger) { ctl.scrollTrigger.kill(); } }
		};
		if (vars.scrollTrigger) {
			var stv = vars.scrollTrigger; stv.animation = ctl; ctl.scrollTrigger = create(stv);
		} else if (vars.paused !== true) { ctl.play(); }
		return ctl;
	}

	/**
	 * timeline(vars): place tweens at times and run them as one. tl.add(child, position) where child is a tween / value
	 * / timeline made with { paused: true } and position is seconds, '<' (with the previous one), '>' (after it, the
	 * default), '>-0.3' / '>+0.2' or '<+0.1'. tl.fromTo(targets, from, to, position) is a shortcut. The timeline plays on
	 * the frame loop, or is scrubbed by a trigger (vars.scrollTrigger).
	 */
	function timeline(vars) {
		vars = vars || {};
		var kids = [], lastStart = 0, lastEnd = 0, dur = 0, p = 0, dir = 1, running = false, t0 = 0, p0 = 0;
		function place(pos) {
			if (typeof pos === 'number') { return pos; }
			var m = /^([<>])?([+-]=?\s*[\d.]+)?$/.exec(String(pos == null ? '>' : pos).trim());
			if (!m) { return lastEnd; }
			var base = m[1] === '<' ? lastStart : lastEnd, off = m[2] ? parseFloat(m[2].replace('=', '')) : 0;
			return Math.max(0, base + off);
		}
		var tl = {
			vars: vars, children: kids,
			add: function (child, pos) {
				if (!child) { return tl; }
				var at = place(pos);
				kids.push({ c: child, at: at });
				lastStart = at; lastEnd = at + (child.duration || 0); dur = Math.max(dur, lastEnd); tl.duration = dur;
				if (child.progress) { child.progress(0); }
				return tl;
			},
			fromTo: function (targets, from, to, pos) { var o = {}; for (var k in to) { o[k] = to[k]; } o.paused = true; delete o.scrollTrigger; return tl.add(tween(targets, from, o), pos); },
			to: function (targets, to, pos) { return tl.fromTo(targets, {}, to, pos); },
			duration: 0,
			progress: function (q) {
				if (q == null) { return p; }
				p = clamp(q, 0, 1);
				var t = p * dur;
				kids.forEach(function (k) {
					var d = k.c.duration || 0.0001;
					k.c.progress(clamp((t - k.at) / d, 0, 1));
				});
				if (vars.onUpdate) { try { vars.onUpdate(p); } catch (e) {} }
				return tl;
			},
			play: function () { go(1); return tl; }, reverse: function () { go(-1); return tl; },
			restart: function () { tl.progress(0); go(1); return tl; }, pause: function () { running = false; return tl; },
			resume: function () { go(dir); return tl; }, reset: function () { running = false; tl.progress(0); return tl; },
			complete: function () { running = false; tl.progress(1); return tl; },
			kill: function () { running = false; kids.forEach(function (k) { if (k.c.kill) { k.c.kill(); } }); if (tl.scrollTrigger) { tl.scrollTrigger.kill(); } }
		};
		function tick(t) {
			if (!running) { return false; }
			tl.progress(p0 + dir * (t - t0) / Math.max(1, dur * 1000));
			if ((dir > 0 && p >= 1) || (dir < 0 && p <= 0)) {
				running = false;
				if (dir > 0 && vars.onComplete) { try { vars.onComplete(); } catch (e) {} }
				return false;
			}
		}
		function go(d) { dir = d; p0 = p; t0 = now(); running = true; if (raf) { raf.add(tick); } else { tl.progress(d > 0 ? 1 : 0); } }
		if (vars.scrollTrigger) {
			// Built after the children are added (the caller adds them synchronously), so the trigger reads the duration.
			var stv = vars.scrollTrigger; stv.animation = tl;
			Promise.resolve().then(function () { tl.scrollTrigger = create(stv); });
		}
		return tl;
	}

	/* ---------------------------------------------------------------- triggers */

	var all = [], lastY = sy(), lastT = now(), vel = 0, ticking = false, refreshing = false;

	function edge(v, size) {
		v = String(v).trim();
		if (v === 'top' || v === 'left') { return 0; }
		if (v === 'center') { return size / 2; }
		if (v === 'bottom' || v === 'right') { return size; }
		if (/%$/.test(v)) { return parseFloat(v) / 100 * size; }
		return parseFloat(v) || 0;
	}
	/** A position string → a scroll offset (px), given the element's top (document) and height. */
	function resolve(pos, elTop, elH, startPx, isEnd) {
		if (typeof pos === 'number') { return pos; }
		if (typeof pos === 'function') { return pos(); }
		pos = String(pos || (isEnd ? 'bottom top' : 'top bottom')).trim();
		var rel = /^\+=\s*([\d.]+)\s*(%|px)?$/.exec(pos);
		if (rel) { return startPx + (rel[2] === '%' ? parseFloat(rel[1]) / 100 * vh() : parseFloat(rel[1])); }
		var parts = pos.split(/\s+/);
		return elTop + edge(parts[0], elH) - edge(parts[1] || 'top', vh());
	}
	/** The four toggle actions: onEnter onLeave onEnterBack onLeaveBack. */
	function act(anim, a) {
		if (!anim || !a || a === 'none') { return; }
		if (a === 'play' && anim.play) { anim.play(); }
		else if (a === 'reverse' && anim.reverse) { anim.reverse(); }
		else if (a === 'restart' && anim.restart) { anim.restart(); }
		else if (a === 'pause' && anim.pause) { anim.pause(); }
		else if (a === 'resume' && anim.resume) { anim.resume(); }
		else if (a === 'reset' && anim.reset) { anim.reset(); }
		else if (a === 'complete' && anim.complete) { anim.complete(); }
	}
	function docTop(el) { var r = el.getBoundingClientRect(); return r.top + sy(); }

	/** Turn pinning off for a moment (so the element can be measured where it sits in the page). */
	function unpinStyle(t) {
		var s = t.pin.style;
		s.position = t._pinPos || ''; s.top = t._pinTop || ''; s.left = ''; s.width = ''; s.height = ''; s.maxWidth = ''; s.boxSizing = ''; s.zIndex = t._pinZ || '';
		t._pinState = 0;
	}
	function setPinState(t, st) {
		if (t._pinState === st) { return; }
		var s = t.pin.style;
		if (st === 1) {
			s.position = 'fixed'; s.top = t._fixTop + 'px'; s.left = t._left + 'px'; s.width = t._w + 'px'; s.maxWidth = t._w + 'px';
			s.height = t._h + 'px'; s.boxSizing = 'border-box'; s.zIndex = s.zIndex || '1';
		} else if (st === 2) {
			s.position = 'relative'; s.top = (t.end - t.start) + 'px'; s.left = ''; s.width = ''; s.maxWidth = ''; s.height = ''; s.boxSizing = '';
		} else {
			unpinStyle(t);
		}
		t._pinState = st;
	}

	function Trigger(vars) {
		var t = this;
		t.vars = vars; t.trigger = toArr(vars.trigger)[0] || null; t.animation = vars.animation || null;
		t.progress = 0; t.direction = 1; t.isActive = false; t.start = 0; t.end = 0; t._smooth = 0; t._shown = 0; t._state = -2;
		t.scrub = vars.scrub === true ? 0.0001 : (+vars.scrub || 0);
		var ta = String(vars.toggleActions || 'play none none none').split(/\s+/);
		t._acts = vars.once ? [ 'play', 'none', 'none', 'none' ] : ta;
		if (vars.pin) {
			t.pin = vars.pin === true ? t.trigger : toArr(vars.pin)[0];
			if (t.pin && t.pin.parentNode) {
				var sp = doc.createElement('div');
				sp.className = 'upw-pin-spacer';
				var cs = getComputedStyle(t.pin);
				// The spacer takes the element's place in its parent's layout (flex / grid placement, outer margins).
				sp.style.cssText = 'display:' + (cs.display === 'inline' ? 'block' : (/flex|grid/.test(cs.display) && !/inline/.test(cs.display) ? 'block' : cs.display)) +
					';flex:' + cs.flex + ';grid-area:' + cs.gridArea + ';align-self:' + cs.alignSelf + ';justify-self:' + cs.justifySelf +
					';margin:' + cs.marginTop + ' ' + cs.marginRight + ' ' + cs.marginBottom + ' ' + cs.marginLeft + ';padding:0;box-sizing:content-box';
				t._margin = t.pin.style.margin;
				t.pin.parentNode.insertBefore(sp, t.pin);
				sp.appendChild(t.pin);
				t.pin.style.margin = '0';
				t._pinPos = t.pin.style.position; t._pinTop = t.pin.style.top; t._pinZ = t.pin.style.zIndex;
				t.spacer = sp; t._pinState = 0;
			} else { t.pin = null; }
		}
		if (vars.markers) { t._markers = markers(t); }
	}
	Trigger.prototype = {
		measure: function () {
			var t = this, el = t.trigger;
			if (!el) { return; }
			if (t.pin) { unpinStyle(t); t.spacer.style.height = ''; }
			var top = docTop(el), h = el.offsetHeight || el.getBoundingClientRect().height;
			t.start = Math.max(0, resolve(t.vars.start, top, h, 0, false));
			t.end = resolve(t.vars.end, top, h, t.start, true);
			if (t.end < t.start) { t.end = t.start; }
			if (t.pin) {
				var r = t.pin.getBoundingClientRect();
				t._left = r.left; t._w = r.width; t._h = r.height;
				t._fixTop = docTop(t.pin) - t.start; // where the element sits on screen when the pin begins
				var extra = t.vars.pinSpacing === false ? 0 : (t.end - t.start);
				t.spacer.style.height = (r.height + extra) + 'px';
			}
			if (t._markers) { t._markers.place(t); }
		},
		update: function (y, dt) {
			var t = this, span = Math.max(1, t.end - t.start), raw = clamp((y - t.start) / span, 0, 1);
			var st = y < t.start ? -1 : (y > t.end ? 1 : 0);
			if (t.pin) { setPinState(t, st === -1 ? 0 : (st === 0 ? 1 : 2)); }
			if (st !== t._state) {
				var prev = t._state; t._state = st;
				var was = t.isActive; t.isActive = st === 0 || (t.end === t.start && st === 1 && prev === -1);
				if (prev !== -2 || st !== -1) {
					if (st >= 0 && prev === -1 || (prev === -2 && st >= 0)) { t.direction = 1; act(t.animation, t._acts[0]); call(t, 'onEnter'); }
					if (st === 1 && prev <= 0) { act(t.animation, t._acts[1]); call(t, 'onLeave'); }
					if (st === 0 && prev === 1) { t.direction = -1; act(t.animation, t._acts[2]); call(t, 'onEnterBack'); }
					if (st === -1 && prev >= 0) { act(t.animation, t._acts[3]); call(t, 'onLeaveBack'); }
				}
				if (was !== t.isActive) { call(t, 'onToggle'); }
				if (t.vars.once && st >= 0 && !t.scrub) { t._done = true; }
			}
			if (raw !== t.progress) { t.direction = raw > t.progress ? 1 : -1; }
			t.progress = raw;
			if (t.scrub && t.animation && t.animation.progress) {
				if (t.scrub <= 0.001) { t._shown = raw; }
				else { var k = 1 - Math.pow(0.001, (dt || 16) / (t.scrub * 1000)); t._shown += (raw - t._shown) * clamp(k, 0, 1); if (Math.abs(raw - t._shown) < 0.0005) { t._shown = raw; } }
				t.animation.progress(t._shown);
			}
			if (t.vars.onUpdate) { try { t.vars.onUpdate(t); } catch (e) {} }
			return t.scrub && Math.abs(t._shown - raw) > 0.0005;
		},
		getVelocity: function () { return vel; },
		refresh: function () { refresh(); },
		kill: function (revert) {
			var t = this, i = all.indexOf(t);
			if (i >= 0) { all.splice(i, 1); }
			if (t.pin && t.spacer && t.spacer.parentNode) {
				unpinStyle(t); t.pin.style.margin = t._margin || '';
				t.spacer.parentNode.insertBefore(t.pin, t.spacer); t.spacer.parentNode.removeChild(t.spacer);
			}
			if (t._markers) { t._markers.remove(); }
			if (revert && t.animation && t.animation.kill) { t.animation.kill(); }
		}
	};
	function call(t, name) { var f = t.vars[name]; if (typeof f === 'function') { try { f(t); } catch (e) {} } }

	/** Debug lines (only when asked for: the engine stamps markers for people who can edit the site). */
	function markers(t) {
		var mk = function (txt, col) { var d = doc.createElement('div'); d.textContent = txt; d.style.cssText = 'position:absolute;left:0;right:0;height:0;border-top:1px dashed ' + col + ';font:11px/1.4 monospace;color:' + col + ';z-index:99999;pointer-events:none;padding-left:4px'; doc.body.appendChild(d); return d; };
		var a = mk('start', '#2AD38B'), b = mk('end', '#e5484d');
		return {
			place: function (tr) { var top = docTop(tr.trigger); a.style.top = top + 'px'; b.style.top = (top + (tr.trigger.offsetHeight || 0)) + 'px'; },
			remove: function () { [ a, b ].forEach(function (d) { if (d.parentNode) { d.parentNode.removeChild(d); } }); }
		};
	}

	function create(vars) {
		var t = new Trigger(vars || {});
		if (!t.trigger) { return t; }
		all.push(t);
		// Document order, so a pin's spacer is in place before anything below it is measured.
		all.sort(function (a, b) {
			if (!a.trigger || !b.trigger || a.trigger === b.trigger) { return 0; }
			return a.trigger.compareDocumentPosition(b.trigger) & 4 ? -1 : 1;
		});
		if (t.animation && t.scrub && t.animation.progress) { t.animation.progress(0); }
		t.measure();
		t.update(sy(), 16);
		schedule();
		return t;
	}

	function refresh() {
		if (refreshing) { return; }
		refreshing = true;
		var y = sy();
		all.forEach(function (t) { if (t.pin) { unpinStyle(t); t.spacer.style.height = ''; } });
		all.forEach(function (t) { t.measure(); });
		all.forEach(function (t) { t.update(y, 16); });
		lastH = docEl.scrollHeight;
		refreshing = false;
		if (window.upwScroll && window.upwScroll.onRefresh) { try { window.upwScroll.onRefresh(); } catch (e) {} }
	}

	function frame(ts) {
		// The first frame after a pause would otherwise count the whole pause as one step (and a smoothed scrub
		// would jump straight to the end), so a step is at most 50 ms.
		var y = sy(), t = now(), dt = clamp(t - lastT, 1, 50);
		vel = (y - lastY) / dt * 1000; // px per second
		var moving = y !== lastY, busy = false;
		lastY = y; lastT = t;
		for (var i = 0; i < all.length; i++) { if (all[i].update(y, dt)) { busy = true; } }
		if (!moving && !busy) { vel = 0; ticking = false; return false; }
	}
	function schedule() {
		if (ticking) { return; }
		ticking = true;
		if (raf) { raf.add(frame); } else { requestAnimationFrame(function loop() { if (frame() !== false) { requestAnimationFrame(loop); } }); }
	}

	var rt = 0, lastH = docEl.scrollHeight;
	function soon() { clearTimeout(rt); rt = setTimeout(refresh, 150); }
	window.addEventListener('scroll', schedule, { passive: true });
	window.addEventListener('resize', soon);
	window.addEventListener('load', refresh);
	if (doc.fonts && doc.fonts.ready) { doc.fonts.ready.then(soon); }
	// Content that grows or shrinks later (images, lazy sections, accordions) moves everything below it.
	if (window.ResizeObserver) {
		var ro = new ResizeObserver(function () { if (!refreshing && Math.abs(docEl.scrollHeight - lastH) > 1 && all.length) { lastH = docEl.scrollHeight; soon(); } });
		var watch = function () { if (doc.body) { ro.observe(doc.body); } };
		if (doc.body) { watch(); } else { doc.addEventListener('DOMContentLoaded', watch); }
	}

	window.upwScroll = {
		version: '1.0.0',
		create: create, tween: tween, value: value, timeline: timeline, set: set, ease: ease, cssEase: cssEase,
		getAll: function () { return all.slice(); },
		refresh: refresh,
		update: function () { var y = sy(); all.forEach(function (t) { t.update(y, 16); }); },
		kill: function (t) { (t ? [ t ] : all.slice()).forEach(function (x) { x.kill(true); }); },
		velocity: function () { return vel; },
		onRefresh: null
	};
})();
