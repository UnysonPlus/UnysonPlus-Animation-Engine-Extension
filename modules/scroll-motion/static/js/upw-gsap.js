/**
 * UnysonPlus — "Scroll Motion" runtime, on the Animation Engine's own scroll core (window.upwScroll, upw-scroll.js)
 * and text splitter (window.upwSplit, upw-split.js). Roadmap Phase 9c: it used to run on GSAP + ScrollTrigger +
 * SplitText; the file name, the script handle (upw-gsap-init) and the data-upw-g* attributes are kept, so pages and
 * the modules that depend on it are unchanged.
 *
 * Reads the data-upw-g* attributes stamped by scroll-motion-render.php and builds each effect.
 * Effects: reveal | stagger | splittext | parallax | pin | scrub | zoom | rotate | blur | clip | skew | flip | expand |
 * counter | velocity_skew | tilt_scrub | scroll_spin | mask_wipe | color_scrub, plus Motion Sequences (.upw-seq).
 *
 * Motion Snippets (Scroll Effect → Custom Code) are the author's own GSAP code, so they still run on GSAP, which the
 * page then loads (only on a page that has a snippet).
 *
 * Failsafe: elements that start hidden carry .upw-g-pending. If we bail (builder / reduced motion / no core) the
 * class is removed so nothing stays invisible; otherwise each effect sets its start state, then removes the class.
 */
(function () {
	'use strict';
	if (typeof window === 'undefined' || typeof document === 'undefined') { return; }

	function clearPending(root) {
		(root || document).querySelectorAll('.upw-g-pending').forEach(function (el) { el.classList.remove('upw-g-pending'); });
	}
	function inBuilder() {
		return document.body && (
			document.body.classList.contains('fw-builder-active') ||
			document.body.classList.contains('fw-backend-builder') ||
			(window.self !== window.top && !/[?&]fw-le-motion-preview=/.test(window.location.search)) /* the Live Editor Preview canvas plays like the real page */
		);
	}
	function ready(fn) { if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', fn); } else { fn(); } }
	var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	ready(function () {
		var S = window.upwScroll;
		if (reducedMotion || inBuilder() || !S) { clearPending(); return; }

		var isMobile = window.matchMedia ? window.matchMedia('(max-width: 767px)').matches : window.innerWidth < 768;

		// Reveal / Stagger "Style" presets: the compound character (scale + blur + ease + duration) behind one dropdown.
		var STYLES = {
			subtle:   { scale: 0.98, blur: 0,  ease: 'power2.out',         duration: 0.6 },
			standard: { scale: 0.96, blur: 4,  ease: 'power3.out',         duration: 0.9 },
			dramatic: { scale: 0.90, blur: 10, ease: 'expo.out',           duration: 1.2 },
			bounce:   { scale: 0.90, blur: 0,  ease: 'back.out(1.7)',      duration: 1.0 },
			elastic:  { scale: 0.85, blur: 0,  ease: 'elastic.out(1,0.5)', duration: 1.3 }
		};
		function attr(el, name) { return el.getAttribute(name); }
		function num(v, d) { v = parseFloat(v); return isNaN(v) ? d : v; }
		function assign(a, b) { for (var k in b) { if (Object.prototype.hasOwnProperty.call(b, k)) { a[k] = b[k]; } } return a; }
		// The Style preset decides the ease, unless Advanced → Easing overrides it (a CSS timing function from the shared
		// curve picker, or an ease name); clone first, the presets are shared.
		function styleOf(el) {
			var st = STYLES[attr(el, 'data-upw-g-style')] || STYLES.standard, ease = attr(el, 'data-upw-g-ease');
			return ease ? assign(assign({}, st), { ease: ease }) : st;
		}
		/** The shared Advanced settings: scrub smoothing (seconds) and debug markers (stamped only for editors). */
		function trig(el, cfg) {
			var sm = parseFloat(attr(el, 'data-upw-g-scrub'));
			if (cfg.scrub === true && !isNaN(sm) && sm > 0) { cfg.scrub = sm; }
			if (attr(el, 'data-upw-g-markers') === '1') { cfg.markers = true; }
			return cfg;
		}
		function startPos(v) { return (typeof v === 'string' && /^[a-z]+ [a-z0-9%]+$/i.test(v)) ? v : 'top 85%'; }
		function offsetFor(dir, dist) {
			var o = {}; dir = dir || 'up';
			if (dir.indexOf('up') > -1) { o.y = dist; } else if (dir.indexOf('down') > -1) { o.y = -dist; }
			if (dir.indexOf('left') > -1) { o.x = dist; } else if (dir.indexOf('right') > -1) { o.x = -dist; }
			return o;
		}
		function compound(el, st) {
			var from = offsetFor(attr(el, 'data-upw-g-dir') || 'up', num(attr(el, 'data-upw-g-distance'), 50));
			from.opacity = 0; from.scale = st.scale;
			var to = { opacity: 1, x: 0, y: 0, scale: 1, duration: st.duration, delay: num(attr(el, 'data-upw-g-delay'), 0), ease: st.ease };
			if (st.blur) { from.blur = st.blur; to.blur = 0; }
			return { from: from, to: to };
		}
		function entranceTrigger(el) {
			return trig(el, {
				trigger: el, start: startPos(attr(el, 'data-upw-g-start')),
				toggleActions: attr(el, 'data-upw-g-once') === '0' ? 'play none none reverse' : 'play none none none'
			});
		}
		function done(el) { el.classList.remove('upw-g-pending'); }

		function reveal(el) {
			var c = compound(el, styleOf(el));
			c.to.scrollTrigger = entranceTrigger(el);
			S.tween(el, c.from, c.to); done(el);
		}

		// The real grid items of a stagger: "direct" = the element's children; otherwise the largest group of same-tag
		// siblings anywhere below it (shallowest wins ties), so a title or wrapper beside the grid never fools it.
		function staggerTargets(el) {
			var direct = Array.prototype.slice.call(el.children);
			if (attr(el, 'data-upw-g-scope') === 'direct') { return direct; }
			var best = null, bestScore = 1, queue = [ el ], guard = 0;
			while (queue.length && guard < 4000) {
				guard++;
				var kids = Array.prototype.slice.call(queue.shift().children), counts = {}, topTag = null, topCount = 0, i;
				for (i = 0; i < kids.length; i++) { var t = kids[i].tagName; counts[t] = (counts[t] || 0) + 1; if (counts[t] > topCount) { topCount = counts[t]; topTag = t; } }
				if (topCount > bestScore) { bestScore = topCount; best = kids.filter(function (c) { return c.tagName === topTag; }); }
				for (i = 0; i < kids.length; i++) { if (kids[i].children && kids[i].children.length) { queue.push(kids[i]); } }
			}
			return best || direct;
		}
		function stagger(el) {
			var kids = staggerTargets(el);
			if (!kids.length) { done(el); return; }
			var c = compound(el, styleOf(el)), from = attr(el, 'data-upw-g-from') || 'start';
			if ([ 'start', 'end', 'center', 'edges' ].indexOf(from) === -1) { from = 'start'; }
			c.to.stagger = { each: num(attr(el, 'data-upw-g-each'), 0.12), from: from };
			c.to.scrollTrigger = trig(el, { trigger: el, start: startPos(attr(el, 'data-upw-g-start')), toggleActions: 'play none none none' });
			S.tween(kids, c.from, c.to); done(el);
		}

		var TARGETS = { headings: 'h1,h2,h3,h4,h5,h6', paragraphs: 'p', all: 'h1,h2,h3,h4,h5,h6,p' };
		function splittext(el) {
			if (!window.upwSplit) { done(el); return; }
			var st = styleOf(el), unit = attr(el, 'data-upw-g-split') || 'chars';
			if ([ 'chars', 'words', 'lines' ].indexOf(unit) === -1) { unit = 'chars'; }
			var targets = el.querySelectorAll(TARGETS[attr(el, 'data-upw-g-target')] || TARGETS.headings);
			if (!targets.length) { done(el); return; }
			var dirSign = attr(el, 'data-upw-g-dir') === 'down' ? -1 : 1, each = num(attr(el, 'data-upw-g-each'), 0.03);
			var start = startPos(attr(el, 'data-upw-g-start')), anim = attr(el, 'data-upw-g-split-anim') || 'slide';
			var KINDS = [ 'slide', 'flip3d', 'scale', 'blur', 'rotate' ];
			function fromFor(kind, i) {
				if (kind === 'random') { kind = KINDS[i % KINDS.length]; }
				if (kind === 'flip3d') { return { opacity: 0, rotationX: -90 * dirSign, transformPerspective: 400 }; }
				if (kind === 'scale') { return { opacity: 0, scale: 0.3 }; }
				if (kind === 'blur') { return { opacity: 0, blur: 8 }; }
				if (kind === 'rotate') { return { opacity: 0, rotation: 25 * dirSign, yPercent: 40 * dirSign }; }
				return { opacity: 0, yPercent: 100 * dirSign };
			}
			function toFor(from) {
				var to = { opacity: 1, duration: Math.max(0.4, st.duration * 0.7), ease: st.ease, paused: true, keep: true };
				for (var k in from) { if (k !== 'opacity' && k !== 'transformPerspective') { to[k] = k === 'scale' ? 1 : 0; } }
				if (from.transformPerspective) { to.transformPerspective = from.transformPerspective; }
				return to;
			}
			Array.prototype.forEach.call(targets, function (t) {
				var split = window.upwSplit(t, { type: unit, linesClass: 'upw-g-line' });
				// Lines are regrouped only in plain text; with inline markup inside, the words cascade instead.
				var pieces = split[unit] && split[unit].length ? split[unit] : split.words;
				if (!pieces || !pieces.length) { split.revert(); return; }
				var tl = S.timeline({ onComplete: function () { split.revert(); }, scrollTrigger: { trigger: t, start: start } });
				Array.prototype.forEach.call(pieces, function (pc, i) {
					if (anim === 'flip3d' || (anim === 'random' && i % KINDS.length === 1)) { pc.style.transformOrigin = '50% 50% -20px'; }
					var from = fromFor(anim, i);
					tl.add(S.tween(pc, from, toFor(from)), i * each);
				});
				tl.progress(0);
			});
			done(el);
		}

		function parallax(el) {
			var prop = attr(el, 'data-upw-g-axis') === 'x' ? 'xPercent' : 'yPercent', speed = num(attr(el, 'data-upw-g-speed'), 20);
			var extra = attr(el, 'data-upw-g-pmotion') || 'none', from = {}, to = { ease: 'none', scrollTrigger: trig(el, { trigger: el, start: 'top bottom', end: 'bottom top', scrub: true }) };
			from[prop] = -speed; to[prop] = speed;
			if (extra === 'rotate') { from.rotation = -speed * 0.3; to.rotation = speed * 0.3; }
			else if (extra === 'scale') { from.scale = 1 - speed / 200; to.scale = 1 + speed / 200; }
			if (attr(el, 'data-upw-g-pfade') === '1') { from.opacity = 0.25; to.opacity = 1; }
			S.tween(el, from, to);
		}

		function pin(el) {
			var len = num(attr(el, 'data-upw-g-pin-length'), 100);
			var p = S.create(trig(el, { trigger: el, start: 'top top', end: '+=' + len + '%', pin: true }));
			// Optional fade: in as the element rises to the top, out over the last fifth of the pin.
			if (attr(el, 'data-upw-g-pin-fade') === '1') {
				S.create({
					trigger: el, start: function () { return Math.max(0, p.start - (window.innerHeight || 1)); }, end: function () { return p.end; },
					onUpdate: function () {
						var y = window.pageYOffset || 0, vh = window.innerHeight || 1, o;
						if (y < p.start) { o = 1 - (p.start - y) / vh; }
						else { var fadeFrom = p.start + (p.end - p.start) * 0.8; o = y < fadeFrom ? 1 : 1 - (y - fadeFrom) / Math.max(1, p.end - fadeFrom); }
						el.style.opacity = String(Math.max(0, Math.min(1, o)));
					}
				});
			}
		}

		function scrub(el) {
			var kind = attr(el, 'data-upw-g-scrub-kind') || 'fade', n = num(attr(el, 'data-upw-g-intensity'), 20), from = {}, to = {
				ease: 'none', scrollTrigger: trig(el, { trigger: el, start: startPos(attr(el, 'data-upw-g-start')), end: 'center center', scrub: true })
			};
			if (kind === 'scale') { from.scale = Math.max(0, 1 - n / 100); to.scale = 1; }
			else if (kind === 'rotate') { from.rotation = -n; to.rotation = 0; }
			else if (kind === 'slide') { from.yPercent = n; to.yPercent = 0; }
			else if (kind === 'blur') { from.blur = n; to.blur = 0; }
			else if (kind === 'skew') { from.skewY = n; to.skewY = 0; }
			else { from.opacity = 0; to.opacity = 1; }
			S.tween(el, from, to); done(el);
		}

		function oneShot(el, from, to) {
			to.delay = num(attr(el, 'data-upw-g-delay'), 0);
			if (!to.duration) { to.duration = 0.9; }
			if (!to.ease) { to.ease = 'power3.out'; }
			to.scrollTrigger = entranceTrigger(el);
			S.tween(el, from, to); done(el);
		}
		function zoom(el) {
			var s = num(attr(el, 'data-upw-g-scale'), 0.6);
			if (attr(el, 'data-upw-g-zdir') === 'out') { s = 2 - s; }
			oneShot(el, { opacity: 0, scale: s }, { opacity: 1, scale: 1 });
		}
		function rotateIn(el) {
			var deg = num(attr(el, 'data-upw-g-rotate'), 8);
			if (attr(el, 'data-upw-g-dir') === 'right') { deg = -deg; }
			oneShot(el, { opacity: 0, rotation: deg, scale: 0.96 }, { opacity: 1, rotation: 0, scale: 1 });
		}
		function blurIn(el) { oneShot(el, { opacity: 0, blur: num(attr(el, 'data-upw-g-blur'), 12) }, { opacity: 1, blur: 0 }); }
		function clipIn(el) {
			var FROM = { up: 'inset(100% 0% 0% 0%)', down: 'inset(0% 0% 100% 0%)', left: 'inset(0% 100% 0% 0%)', right: 'inset(0% 0% 0% 100%)',
				iris: 'circle(0% at 50% 50%)', diagonal: 'polygon(0% 0%, 0% 0%, 0% 0%, 0% 0%)', rounded: 'inset(50% 50% 50% 50% round 40px)' };
			var TO = { iris: 'circle(75% at 50% 50%)', diagonal: 'polygon(0% 0%, 200% 0%, 0% 200%, 0% 0%)', rounded: 'inset(0% 0% 0% 0% round 0px)' };
			var dir = attr(el, 'data-upw-g-dir') || 'up';
			oneShot(el, { clipPath: FROM[dir] || FROM.up }, { clipPath: TO[dir] || 'inset(0% 0% 0% 0%)' });
		}
		function skewIn(el) {
			var horiz = attr(el, 'data-upw-g-axis') === 'x', amt = num(attr(el, 'data-upw-g-skew'), 8), dist = num(attr(el, 'data-upw-g-distance'), 40);
			oneShot(el, horiz ? { opacity: 0, skewX: amt, x: dist } : { opacity: 0, skewY: amt, y: dist },
				horiz ? { opacity: 1, skewX: 0, x: 0 } : { opacity: 1, skewY: 0, y: 0 });
		}
		// 3D Flip In: from 90° on the X or Y axis, with perspective for a true card-flip depth.
		function flipIn(el) {
			var horiz = attr(el, 'data-upw-g-axis') === 'x', deg = num(attr(el, 'data-upw-g-deg'), 90);
			if (attr(el, 'data-upw-g-dir') === 'right') { deg = -deg; }
			var from = { opacity: 0, transformPerspective: 800 }, to = { opacity: 1, transformPerspective: 800 };
			if (horiz) { from.rotationX = deg; to.rotationX = 0; } else { from.rotationY = deg; to.rotationY = 0; }
			oneShot(el, from, to);
		}
		// Expand / Grow: scales in from a line about an origin edge (underlines, dividers, bars).
		function expand(el) {
			var vertical = attr(el, 'data-upw-g-axis') === 'y';
			el.style.transformOrigin = attr(el, 'data-upw-g-origin') || (vertical ? 'top' : 'left');
			oneShot(el, vertical ? { scaleY: 0 } : { scaleX: 0 }, vertical ? { scaleY: 1 } : { scaleX: 1 });
		}

		// Count Up: the first number inside the element, from a start value to its own; 'count' or 'odometer'.
		function counter(el) {
			var dur = num(attr(el, 'data-upw-g-duration'), 2), useSep = attr(el, 'data-upw-g-sep') === '1', fromVal = num(attr(el, 'data-upw-g-from'), 0);
			var uiPre = attr(el, 'data-upw-g-prefix') || '', uiPost = attr(el, 'data-upw-g-suffix') || '', style = attr(el, 'data-upw-g-cstyle') || 'count';
			var re = /-?\d[\d,]*(?:\.\d+)?/, tw = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, null, false), node = null, mm = null;
			while (tw.nextNode()) { var mt = tw.currentNode.nodeValue.match(re); if (mt) { node = tw.currentNode; mm = mt; break; } }
			if (!node) { done(el); return; }
			var rawNum = mm[0].replace(/,/g, ''), endVal = parseFloat(rawNum);
			if (isNaN(endVal)) { done(el); return; }
			var decimals = (rawNum.split('.')[1] || '').length;
			var pre = uiPre + node.nodeValue.slice(0, mm.index), post = node.nodeValue.slice(mm.index + mm[0].length) + uiPost;
			function fmt(v) {
				var s = v.toFixed(decimals);
				if (useSep) { var p = s.split('.'); p[0] = p[0].replace(/\B(?=(\d{3})+(?!\d))/g, ','); s = p.join('.'); }
				return s;
			}
			var tr = entranceTrigger(el);
			if (style === 'odometer') { buildOdometer(el, node, pre, post, endVal, decimals, dur, tr); return; }
			var obj = { v: fromVal };
			node.nodeValue = pre + fmt(fromVal) + post;
			S.value({ target: obj, to: { v: endVal }, duration: dur, ease: 'power1.out', scrollTrigger: tr, onUpdate: function () { node.nodeValue = pre + fmt(obj.v) + post; } });
			done(el);
		}
		// Odometer: per-digit vertical reels (0-9 stacked) that roll to the final digits.
		function buildOdometer(el, node, pre, post, endVal, decimals, dur, tr) {
			var endStr = endVal.toFixed(decimals), span = document.createElement('span'), reels = [];
			span.className = 'upw-g-odo'; span.setAttribute('aria-label', pre + endStr + post);
			span.style.display = 'inline-flex'; span.style.alignItems = 'baseline';
			if (pre) { span.appendChild(document.createTextNode(pre)); }
			for (var i = 0; i < endStr.length; i++) {
				var ch = endStr[i];
				if (ch < '0' || ch > '9') { span.appendChild(document.createTextNode(ch)); continue; }
				var reel = document.createElement('span'), strip = document.createElement('span');
				reel.className = 'upw-g-odo-reel'; strip.className = 'upw-g-odo-strip';
				reel.style.cssText = 'display:inline-block;overflow:hidden;height:1em;line-height:1em;vertical-align:bottom';
				strip.style.cssText = 'display:inline-flex;flex-direction:column';
				for (var d = 0; d <= 9; d++) { var c = document.createElement('span'); c.className = 'upw-g-odo-d'; c.textContent = d; strip.appendChild(c); }
				reel.appendChild(strip); span.appendChild(reel);
				reels.push({ strip: strip, target: parseInt(ch, 10) });
			}
			if (post) { span.appendChild(document.createTextNode(post)); }
			node.parentNode.replaceChild(span, node);
			var rolled = reels.map(function (r, idx) {
				return S.tween(r.strip, { yPercent: 0 }, { yPercent: -(r.target / 10) * 100, duration: dur, ease: 'power2.out', delay: idx * 0.08, paused: true, keep: true });
			});
			tr.onEnter = function () { rolled.forEach(function (t) { t.play(); }); };
			tr.toggleActions = 'none none none none';
			S.create(tr);
			done(el);
		}

		// Velocity Skew: leans with the scroll speed and springs back to 0 when the scrolling stops.
		function velocitySkew(el) {
			var max = num(attr(el, 'data-upw-g-max'), 20), axis = attr(el, 'data-upw-g-axis') === 'x' ? 'skewX' : 'skewY';
			el.style.transformOrigin = attr(el, 'data-upw-g-origin') || 'center center';
			var proxy = { s: 0 }, settle = null;
			function setSkew() { el.style.transform = 'translateZ(0) ' + axis + '(' + proxy.s.toFixed(2) + 'deg)'; }
			S.create({
				trigger: el, start: 'top bottom', end: 'bottom top',
				onUpdate: function (self) {
					var s = Math.max(-max, Math.min(max, self.getVelocity() / -300));
					if (Math.abs(s) > Math.abs(proxy.s)) {
						proxy.s = s; setSkew();
						if (settle) { settle.pause(); }
						settle = S.value({ target: proxy, to: { s: 0 }, duration: 0.8, ease: 'power3.out', onUpdate: setSkew });
					}
				}
			});
		}
		function tiltScrub(el) {
			var axis = attr(el, 'data-upw-g-axis') === 'x' ? 'rotationX' : 'rotationY', deg = num(attr(el, 'data-upw-g-deg'), 12);
			var from = { transformPerspective: 800 }, to = { transformPerspective: 800, ease: 'none', scrollTrigger: { trigger: el, start: 'top bottom', end: 'bottom top', scrub: true } };
			from[axis] = deg; to[axis] = -deg;
			S.tween(el, from, to);
		}
		function scrollSpin(el) {
			var turns = num(attr(el, 'data-upw-g-turns'), 1);
			if (attr(el, 'data-upw-g-dir') === 'ccw') { turns = -turns; }
			S.tween(el, { rotation: 0 }, { rotation: 360 * turns, ease: 'none', scrollTrigger: { trigger: el, start: 'top bottom', end: 'bottom top', scrub: true } });
		}
		// Mask Wipe: a soft, feathered reveal that sweeps across (the gradient is rebuilt every frame).
		function maskWipe(el) {
			var dir = attr(el, 'data-upw-g-dir') || 'left', soft = num(attr(el, 'data-upw-g-soft'), 25);
			var toDir = { left: 'to right', right: 'to left', up: 'to bottom', down: 'to top' }[dir] || 'to right';
			function apply(p) {
				var edge = p * (100 + soft), g = 'linear-gradient(' + toDir + ', #000 ' + (edge - soft) + '%, transparent ' + edge + '%)';
				el.style.webkitMaskImage = g; el.style.maskImage = g;
			}
			apply(0);
			var proxy = { p: 0 };
			S.value({
				target: proxy, to: { p: 1 }, duration: 0.9, ease: 'power2.out', delay: num(attr(el, 'data-upw-g-delay'), 0),
				scrollTrigger: entranceTrigger(el), onUpdate: function () { apply(proxy.p); },
				onComplete: function () { el.style.webkitMaskImage = el.style.maskImage = 'none'; }
			});
			done(el);
		}

		// Colour Scrub: text or background colour A → B through the scroll. Each colour (a hex or a var(--colour-x)
		// preset) is resolved to a real rgb() by writing it on the element and reading it back, batched in phases (write
		// all, read all, write all, read all, restore all) so the whole batch costs two style recalculations.
		var colorScrubQueue = [], colorScrubQueued = false;
		function colorScrub(el) {
			colorScrubQueue.push(el);
			if (colorScrubQueued) { return; }
			colorScrubQueued = true;
			Promise.resolve().then(flushColorScrub);
		}
		function flushColorScrub() {
			colorScrubQueued = false;
			var items = colorScrubQueue.splice(0, colorScrubQueue.length), rows = [];
			items.forEach(function (el) {
				if (!el || el.isConnected === false) { return; }
				var bg = attr(el, 'data-upw-g-ctarget') === 'bg', row = { el: el, target: bg ? 'backgroundColor' : 'color', cssProp: bg ? 'background-color' : 'color' };
				row.prev = el.style.getPropertyValue(row.cssProp);
				el.style.setProperty(row.cssProp, attr(el, 'data-upw-g-c1') || '#888888');
				rows.push(row);
			});
			if (!rows.length) { return; }
			rows.forEach(function (r) { r.from = getComputedStyle(r.el)[r.target]; });
			rows.forEach(function (r) { r.el.style.setProperty(r.cssProp, attr(r.el, 'data-upw-g-c2') || '#2f74e6'); });
			rows.forEach(function (r) { r.to = getComputedStyle(r.el)[r.target]; });
			rows.forEach(function (r) { r.el.style.setProperty(r.cssProp, r.prev); });
			rows.forEach(function (r) {
				var from = {}, to = { ease: 'none', scrollTrigger: { trigger: r.el, start: 'top 80%', end: 'bottom 40%', scrub: true } };
				from[r.target] = r.from; to[r.target] = r.to;
				S.tween(r.el, from, to);
			});
		}

		var BUILDERS = {
			reveal: reveal, stagger: stagger, splittext: splittext, parallax: parallax, pin: pin, scrub: scrub,
			zoom: zoom, rotate: rotateIn, blur: blurIn, clip: clipIn, skew: skewIn, flip: flipIn, expand: expand, counter: counter,
			velocity_skew: velocitySkew, tilt_scrub: tiltScrub, scroll_spin: scrollSpin, mask_wipe: maskWipe, color_scrub: colorScrub
		};
		function build(el) {
			if (el.__upwG) { return; }
			el.__upwG = true;
			if (attr(el, 'data-upw-g-mobile') === '0' && isMobile) { done(el); return; }
			var fn = BUILDERS[attr(el, 'data-upw-g')];
			if (fn) { try { fn(el); } catch (e) { done(el); if (window.console) { console.warn('[Scroll Motion]', e); } } } else { done(el); }
		}
		function scan(root) { (root || document).querySelectorAll('[data-upw-g]').forEach(build); }

		/**
		 * MOTION SEQUENCE (module: motion-sequence). A Section marked .upw-seq plays its descendant Reveal / Stagger
		 * elements as ONE timeline in document order. Runs before scan() so it claims its steps first.
		 */
		function initSequences(root) {
			(root || document).querySelectorAll('.upw-seq').forEach(function (sec) {
				if (sec.__upwSeq) { return; }
				sec.__upwSeq = true;
				if (attr(sec, 'data-upw-seq-mobile') === '0' && isMobile) { return; }
				var steps = [].slice.call(sec.querySelectorAll('[data-upw-g="reveal"], [data-upw-g="stagger"]')).filter(function (el) { return !el.__upwG; });
				if (!steps.length) { return; }
				var overlap = num(attr(sec, 'data-upw-seq-overlap'), 0.35), isScrub = attr(sec, 'data-upw-seq-trigger') === 'scrub';
				var st = isScrub
					? { trigger: sec, start: 'top 70%', end: 'bottom 60%', scrub: true }
					: { trigger: sec, start: startPos(attr(sec, 'data-upw-seq-start')), toggleActions: 'play none none none' };
				var tl = S.timeline({ scrollTrigger: st });
				steps.forEach(function (el, i) {
					el.__upwG = true;
					done(el);
					var c = compound(el, styleOf(el)), dur = c.to.duration || 0.6;
					var pos = i === 0 ? 0 : (attr(el, 'data-upw-seq-pos') === 'with' ? '<' : '>-' + Math.max(0, Math.min(dur, overlap)));
					c.to.keep = true;
					if (attr(el, 'data-upw-g') === 'stagger') {
						c.to.stagger = num(attr(el, 'data-upw-g-each'), 0.12);
						tl.fromTo(staggerTargets(el), c.from, c.to, pos);
					} else {
						tl.fromTo(el, c.from, c.to, pos);
					}
				});
				tl.progress(0);
			});
		}

		/**
		 * MOTION SNIPPETS (Scroll Effect → Custom Code): the author's own GSAP code, base64 in data-upw-snip, run with
		 * (el, tl, gsap) where tl is a timeline already tied to a ScrollTrigger. GSAP is loaded only on pages that have
		 * one. EXECUTION IS GATED: only when window.upwSnippetsOK is set, which the footer prints solely when the page's
		 * author has unfiltered_html, so untrusted stored code never runs.
		 */
		function runSnippets(root) {
			var gsap = window.gsap;
			// Not allowed to run here: still make the element visible.
			if (!window.upwSnippetsOK) { (root || document).querySelectorAll('[data-upw-snip]').forEach(done); return; }
			(root || document).querySelectorAll('[data-upw-snip]').forEach(function (el) {
				if (el.__upwSnip) { return; }
				el.__upwSnip = true;
				done(el);
				if (!gsap || (isMobile && attr(el, 'data-upw-snip-mobile') === '0')) { return; }
				if (window.ScrollTrigger && gsap.registerPlugin) { gsap.registerPlugin(window.ScrollTrigger); }
				var code;
				try { code = decodeURIComponent(escape(window.atob(attr(el, 'data-upw-snip') || ''))); }
				catch (e) { try { code = window.atob(attr(el, 'data-upw-snip') || ''); } catch (e2) { return; } }
				if (!code) { return; }
				try {
					var stCfg = { trigger: el, start: attr(el, 'data-upw-snip-start') || 'top 80%' }, sc = attr(el, 'data-upw-snip-scrub');
					if (sc === 'yes') { stCfg.scrub = true; stCfg.end = 'bottom top'; } else if (sc === 'smooth') { stCfg.scrub = 1; stCfg.end = 'bottom top'; }
					var tl = gsap.timeline({ scrollTrigger: stCfg });
					/* eslint-disable no-new-func */
					(new Function('el', 'tl', 'gsap', code))(el, tl, gsap);
				} catch (e) { if (window.console) { console.warn('[Motion Snippet]', e); } }
			});
		}

		initSequences();
		scan();
		runSnippets();

		/**
		 * Timing overrides from the Scroll Timeline editor, keyed by each element's data-upw-g-key: they set the trigger's
		 * vars.start / vars.end and a refresh recomputes the range. Printed publicly, so a retimed animation ships for
		 * every visitor.
		 */
		function applyTimingOverrides() {
			var O = window.upwTimingOverrides;
			if (!O) { return; }
			S.getAll().forEach(function (st) {
				var el = st.trigger;
				if (!el || !el.getAttribute) { return; }
				var key = el.getAttribute('data-upw-g-key');
				if (!key && el.closest) { var anc = el.closest('[data-upw-g-key]'); if (anc) { key = anc.getAttribute('data-upw-g-key'); } }
				if (!key || !O[key]) { return; }
				if (O[key].start || O[key].end) { st._upwOrig = st._upwOrig || { start: st.vars.start, end: st.vars.end }; }
				if (O[key].start) { st.vars.start = O[key].start; }
				if (O[key].end) { st.vars.end = O[key].end; }
			});
			S.refresh();
		}
		window.upwApplyTimingOverrides = applyTimingOverrides;
		applyTimingOverrides();

		// Content injected later (AJAX, infinite scroll…).
		if ('MutationObserver' in window) {
			new MutationObserver(function (muts) {
				muts.forEach(function (m) {
					m.addedNodes && m.addedNodes.forEach(function (n) {
						if (n.nodeType !== 1 || (n.classList && (n.classList.contains('upw-pin-spacer') || /^upw-split-/.test(n.className)))) { return; }
						if (n.classList && n.classList.contains('upw-seq')) { initSequences(n.parentNode || n); }
						else if (n.querySelector && n.querySelector('.upw-seq')) { initSequences(n); }
						if (n.hasAttribute && n.hasAttribute('data-upw-g')) { build(n); }
						if (n.querySelectorAll) { scan(n); runSnippets(n); }
					});
				});
			}).observe(document.body || document.documentElement, { childList: true, subtree: true });
		}
	});
})();
