/**
 * UnysonPlus — GSAP "Scroll Motion" initializer.
 *
 * Reads the clean `data-upw-g*` attributes stamped by shortcode-gsap-helper.php
 * and builds the matching GSAP + ScrollTrigger animation. Loaded only on pages
 * that actually use a GSAP effect (gated server-side by sc_gsap_flag()).
 *
 * Effects: reveal | stagger | parallax | pin | scrub.
 *
 * Failsafe contract: elements that start hidden carry `.upw-g-pending`. If we
 * bail (builder / reduced-motion / GSAP missing) we strip that class so nothing
 * is left invisible. On the normal path, GSAP's fromTo (immediateRender) sets
 * the start-state inline, so we can drop the class immediately after building.
 */
(function () {
    'use strict';

    if (typeof window === 'undefined' || typeof document === 'undefined') return;

    function clearPending(root) {
        (root || document).querySelectorAll('.upw-g-pending').forEach(function (el) {
            el.classList.remove('upw-g-pending');
        });
    }

    function inBuilder() {
        return document.body && (
            document.body.classList.contains('fw-builder-active') ||
            document.body.classList.contains('fw-backend-builder') ||
            window.self !== window.top
        );
    }

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    ready(function () {
        var gsap = window.gsap;

        // Bail safely: keep content visible, run no motion.
        if (reducedMotion || inBuilder() || !gsap) {
            clearPending();
            return;
        }

        if (gsap.registerPlugin) {
            if (window.ScrollTrigger) gsap.registerPlugin(window.ScrollTrigger);
            if (window.SplitText) gsap.registerPlugin(window.SplitText);
        }

        var isMobile = window.innerWidth < 768;

        // Reveal/Stagger "Style" presets — the compound character (scale + blur
        // + ease + duration) behind a single dropdown.
        var STYLES = {
            subtle:   { scale: 0.98, blur: 0,  ease: 'power2.out',        duration: 0.6 },
            standard: { scale: 0.96, blur: 4,  ease: 'power3.out',        duration: 0.9 },
            dramatic: { scale: 0.90, blur: 10, ease: 'expo.out',          duration: 1.2 },
            bounce:   { scale: 0.90, blur: 0,  ease: 'back.out(1.7)',     duration: 1.0 },
            elastic:  { scale: 0.85, blur: 0,  ease: 'elastic.out(1,0.5)', duration: 1.3 }
        };

        function attr(el, name) { return el.getAttribute(name); }
        function num(v, d) { v = parseFloat(v); return isNaN(v) ? d : v; }
        // The Style preset decides the ease… unless Advanced → Easing overrides it. The preset
        // object is SHARED across elements, so clone before overriding (never mutate STYLES).
        function styleOf(el) {
            var st = STYLES[attr(el, 'data-upw-g-style')] || STYLES.standard;
            var ease = attr(el, 'data-upw-g-ease');
            if (!ease) { return st; }
            var out = {}, k;
            for (k in st) { if (Object.prototype.hasOwnProperty.call(st, k)) { out[k] = st[k]; } }
            out.ease = ease;
            return out;
        }

        /**
         * Apply the shared Advanced ScrollTrigger settings to a trigger config:
         *   • Scrub smoothing — `scrub: true` becomes `scrub: <seconds>` (catch-up time).
         *   • Debug markers — PHP only stamps this for users who can edit the site.
         * Returns the same object so it can wrap a config inline: `scrollTrigger: trig(el, {…})`.
         */
        function trig(el, cfg) {
            if (!cfg) { return cfg; }
            var sm = parseFloat(attr(el, 'data-upw-g-scrub'));
            if (cfg.scrub === true && !isNaN(sm) && sm > 0) { cfg.scrub = sm; }
            if (attr(el, 'data-upw-g-markers') === '1') { cfg.markers = true; }
            return cfg;
        }
        function startPos(v) {
            return (typeof v === 'string' && /^[a-z]+ [a-z0-9%]+$/i.test(v)) ? v : 'top 85%';
        }

        // Translate a direction + distance into from-vars. Supports the four cardinals plus the
        // four diagonals (e.g. 'up_left') via substring checks, and 'none' (fade only).
        function offsetFor(dir, dist) {
            var o = {};
            dir = dir || 'up';
            if (dir.indexOf('up') > -1) o.y = dist;
            else if (dir.indexOf('down') > -1) o.y = -dist;
            if (dir.indexOf('left') > -1) o.x = dist;
            else if (dir.indexOf('right') > -1) o.x = -dist;
            return o;
        }

        // Build the compound from/to vars shared by reveal + stagger.
        function compound(el, st) {
            var dir = attr(el, 'data-upw-g-dir') || 'up';
            var from = offsetFor(dir, num(attr(el, 'data-upw-g-distance'), 50));
            from.opacity = 0;
            from.scale = st.scale;
            if (st.blur) from.filter = 'blur(' + st.blur + 'px)';

            var to = {
                opacity: 1, x: 0, y: 0, scale: 1,
                duration: st.duration,
                delay: num(attr(el, 'data-upw-g-delay'), 0),
                ease: st.ease
            };
            if (st.blur) to.filter = 'blur(0px)';
            return { from: from, to: to };
        }

        function reveal(el) {
            var st = styleOf(el);
            var c = compound(el, st);
            c.to.scrollTrigger = trig(el, {
                trigger: el,
                start: startPos(attr(el, 'data-upw-g-start')),
                toggleActions: attr(el, 'data-upw-g-once') === '0'
                    ? 'play none none reverse'
                    : 'play none none none'
            });
            gsap.fromTo(el, c.from, c.to);
            el.classList.remove('upw-g-pending');
        }

        // Resolve which elements a stagger cascades. "direct" = this element's immediate
        // children as-is (good for a row of columns / a column of blocks). Default
        // ("auto") finds the REAL grid items: a gallery/masonry/grid renders
        // wrapper > [title?] > .grid-container > items (possibly with a heading sibling
        // or extra nesting), so el.children is NOT the items. We scan the subtree and
        // pick the largest group of same-tag siblings — that IS the grid row — shallowest
        // wins ties, so a title/container beside the grid never fools it. Falls back to
        // direct children if nothing repeats (e.g. a single-item element).
        function staggerTargets(el) {
            var direct = Array.prototype.slice.call(el.children);
            if (attr(el, 'data-upw-g-scope') === 'direct') return direct;

            var best = null, bestScore = 1, queue = [el], guard = 0;
            while (queue.length && guard < 4000) {
                guard++;
                var kids = Array.prototype.slice.call(queue.shift().children);
                // Largest group of same-tag children in this container.
                var counts = {}, topTag = null, topCount = 0, i;
                for (i = 0; i < kids.length; i++) {
                    var t = kids[i].tagName;
                    counts[t] = (counts[t] || 0) + 1;
                    if (counts[t] > topCount) { topCount = counts[t]; topTag = t; }
                }
                if (topCount > bestScore) { // strictly greater => shallowest max wins (BFS)
                    bestScore = topCount;
                    best = kids.filter(function (c) { return c.tagName === topTag; });
                }
                for (i = 0; i < kids.length; i++) {
                    if (kids[i].children && kids[i].children.length) queue.push(kids[i]);
                }
            }
            return best || direct;
        }

        function stagger(el) {
            var kids = staggerTargets(el);
            if (!kids.length) { el.classList.remove('upw-g-pending'); return; }

            var st = styleOf(el);
            var c = compound(el, st);

            var fromWhich = attr(el, 'data-upw-g-from') || 'start';
            if (['start', 'end', 'center', 'edges'].indexOf(fromWhich) === -1) fromWhich = 'start';

            c.to.stagger = { each: num(attr(el, 'data-upw-g-each'), 0.12), from: fromWhich };
            c.to.scrollTrigger = {
                trigger: el,
                start: startPos(attr(el, 'data-upw-g-start')),
                toggleActions: 'play none none none'
            };
            gsap.fromTo(kids, c.from, c.to);
            el.classList.remove('upw-g-pending');
        }

        var TARGETS = {
            headings: 'h1,h2,h3,h4,h5,h6',
            paragraphs: 'p',
            all: 'h1,h2,h3,h4,h5,h6,p'
        };

        function splittext(el) {
            if (!window.SplitText) { el.classList.remove('upw-g-pending'); return; }

            var st = styleOf(el);
            var unit = attr(el, 'data-upw-g-split') || 'chars';
            if (['chars', 'words', 'lines'].indexOf(unit) === -1) unit = 'chars';

            var sel = TARGETS[attr(el, 'data-upw-g-target')] || TARGETS.headings;
            var targets = el.querySelectorAll(sel);
            if (!targets.length) { el.classList.remove('upw-g-pending'); return; }

            var dirSign = attr(el, 'data-upw-g-dir') === 'down' ? -1 : 1;
            var each = num(attr(el, 'data-upw-g-each'), 0.03);
            var start = startPos(attr(el, 'data-upw-g-start'));
            var anim = attr(el, 'data-upw-g-split-anim') || 'slide';

            // Per-piece start-state builder. 'random' picks a different look per piece.
            var KINDS = ['slide', 'flip3d', 'scale', 'blur', 'rotate'];
            function fromFor(kind, i) {
                if (kind === 'random') kind = KINDS[i % KINDS.length];
                if (kind === 'flip3d') return { opacity: 0, rotationX: -90 * dirSign, transformOrigin: '50% 50% -20px', transformPerspective: 400 };
                if (kind === 'scale')  return { opacity: 0, scale: 0.3 };
                if (kind === 'blur')   return { opacity: 0, filter: 'blur(8px)' };
                if (kind === 'rotate') return { opacity: 0, rotation: 25 * dirSign, yPercent: 40 * dirSign };
                return { opacity: 0, yPercent: 100 * dirSign }; // slide
            }
            function toFor(kind) {
                var to = { opacity: 1, yPercent: 0, rotationX: 0, rotation: 0, scale: 1,
                           duration: Math.max(0.4, st.duration * 0.7), ease: st.ease, stagger: each };
                if (kind === 'blur' || kind === 'random') to.filter = 'blur(0px)';
                return to;
            }

            Array.prototype.forEach.call(targets, function (t) {
                var split = new window.SplitText(t, { type: unit, linesClass: 'upw-g-line' });
                var pieces = split[unit];
                if (!pieces || !pieces.length) { return; }

                if (unit !== 'lines') gsap.set(pieces, { display: 'inline-block' });
                // Set each piece's start-state individually (random varies per piece).
                Array.prototype.forEach.call(pieces, function (pc, i) { gsap.set(pc, fromFor(anim, i)); });

                var to = toFor(anim);
                to.scrollTrigger = { trigger: t, start: start };
                to.onComplete = function () { if (split.revert) split.revert(); };
                gsap.to(pieces, to);
            });
            el.classList.remove('upw-g-pending');
        }

        function parallax(el) {
            var prop = attr(el, 'data-upw-g-axis') === 'x' ? 'xPercent' : 'yPercent';
            var speed = num(attr(el, 'data-upw-g-speed'), 20);
            var extra = attr(el, 'data-upw-g-pmotion') || 'none'; // none | rotate | scale
            var from = {}, to = { ease: 'none', scrollTrigger: trig(el, { trigger: el, start: 'top bottom', end: 'bottom top', scrub: true }) };
            from[prop] = -speed; to[prop] = speed;
            if (extra === 'rotate') { from.rotation = -speed * 0.3; to.rotation = speed * 0.3; }
            else if (extra === 'scale') { from.scale = 1 - speed / 200; to.scale = 1 + speed / 200; }
            if (attr(el, 'data-upw-g-pfade') === '1') { from.opacity = 0.25; to.opacity = 1; }
            gsap.fromTo(el, from, to);
        }

        function pin(el) {
            var len = num(attr(el, 'data-upw-g-pin-length'), 100);
            window.ScrollTrigger.create(trig(el, {
                trigger: el,
                start: 'top top',
                end: '+=' + len + '%',
                pin: true,
                pinSpacing: true,
                anticipatePin: 1 // smoother hand-off into the pin at speed
            }));
            // Optional fade: dip opacity in at the start of the pin and out at the end.
            if (attr(el, 'data-upw-g-pin-fade') === '1') {
                gsap.fromTo(el, { opacity: 0 }, {
                    opacity: 1, ease: 'none', immediateRender: false,
                    scrollTrigger: { trigger: el, start: 'top bottom', end: 'top top', scrub: true }
                });
                gsap.to(el, {
                    opacity: 0, ease: 'none',
                    scrollTrigger: { trigger: el, start: '+=' + (len * 0.8) + '%', end: '+=' + len + '%', scrub: true }
                });
            }
        }

        function scrub(el) {
            var kind = attr(el, 'data-upw-g-scrub-kind') || 'fade';
            var intensity = num(attr(el, 'data-upw-g-intensity'), 20);
            var from = {}, to = {
                ease: 'none',
                scrollTrigger: trig(el, {
                    trigger: el,
                    start: startPos(attr(el, 'data-upw-g-start')),
                    end: 'center center',
                    scrub: true
                })
            };

            if (kind === 'scale') {
                from.scale = Math.max(0, 1 - intensity / 100); to.scale = 1;
            } else if (kind === 'rotate') {
                from.rotation = -intensity; to.rotation = 0;
            } else if (kind === 'slide') {
                from.yPercent = intensity; to.yPercent = 0;
            } else if (kind === 'blur') {
                from.filter = 'blur(' + intensity + 'px)'; to.filter = 'blur(0px)';
            } else if (kind === 'skew') {
                from.skewY = intensity; to.skewY = 0;
            } else { // fade
                from.opacity = 0; to.opacity = 1;
            }

            gsap.fromTo(el, from, to);
            el.classList.remove('upw-g-pending');
        }

        // One-shot entrance helper shared by the reveal-variants below.
        function entranceTrigger(el) {
            return {
                trigger: el,
                start: startPos(attr(el, 'data-upw-g-start')),
                toggleActions: attr(el, 'data-upw-g-once') === '0'
                    ? 'play none none reverse'
                    : 'play none none none'
            };
        }
        function oneShot(el, from, to) {
            to.delay = num(attr(el, 'data-upw-g-delay'), 0);
            if (!to.duration) { to.duration = 0.9; }
            if (!to.ease) { to.ease = 'power3.out'; }
            to.scrollTrigger = entranceTrigger(el);
            gsap.fromTo(el, from, to);
            el.classList.remove('upw-g-pending');
        }

        function zoom(el) {
            var s = num(attr(el, 'data-upw-g-scale'), 0.6);
            if (attr(el, 'data-upw-g-zdir') === 'out') { s = 2 - s; } // start larger, shrink into place
            oneShot(el, { opacity: 0, scale: s }, { opacity: 1, scale: 1 });
        }
        function rotateIn(el) {
            var deg = num(attr(el, 'data-upw-g-rotate'), 8);
            if (attr(el, 'data-upw-g-dir') === 'right') { deg = -deg; }
            oneShot(el, { opacity: 0, rotation: deg, scale: 0.96 },
                        { opacity: 1, rotation: 0, scale: 1 });
        }
        function blurIn(el) {
            var b = num(attr(el, 'data-upw-g-blur'), 12);
            oneShot(el, { opacity: 0, filter: 'blur(' + b + 'px)' },
                        { opacity: 1, filter: 'blur(0px)' });
        }
        function clipIn(el) {
            var FROM = {
                up:   'inset(100% 0 0 0)', down:  'inset(0 0 100% 0)',
                left: 'inset(0 100% 0 0)', right: 'inset(0 0 0 100%)',
                iris:     'circle(0% at 50% 50%)',
                diagonal: 'polygon(0 0, 0 0, 0 0, 0 0)',
                rounded:  'inset(50% 50% 50% 50% round 40px)'
            };
            var TO = {
                iris:     'circle(75% at 50% 50%)',
                diagonal: 'polygon(0 0, 200% 0, 0 200%, 0 0)',
                rounded:  'inset(0% 0% 0% 0% round 0px)'
            };
            var dir = attr(el, 'data-upw-g-dir') || 'up';
            var f = FROM[dir] || FROM.up;
            var t = TO[dir] || 'inset(0% 0% 0% 0%)';
            oneShot(el, { clipPath: f, webkitClipPath: f },
                        { clipPath: t, webkitClipPath: t });
        }
        function skewIn(el) {
            var horiz = attr(el, 'data-upw-g-axis') === 'x';
            var amt = num(attr(el, 'data-upw-g-skew'), 8), dist = num(attr(el, 'data-upw-g-distance'), 40);
            oneShot(el, horiz
                        ? { opacity: 0, skewX: amt, x: dist }
                        : { opacity: 0, skewY: amt, y: dist },
                    horiz
                        ? { opacity: 1, skewX: 0, x: 0 }
                        : { opacity: 1, skewY: 0, y: 0 });
        }

        // 3D Flip In — the element flips in on a 3D axis (X or Y) from 90°. Distinct from
        // Rotate In, which is a flat 2D z-spin. Perspective gives the true card-flip depth.
        function flipIn(el) {
            var horiz = attr(el, 'data-upw-g-axis') === 'x'; // x => flip around the X axis (top/bottom)
            var deg = num(attr(el, 'data-upw-g-deg'), 90);
            if (attr(el, 'data-upw-g-dir') === 'right') deg = -deg;
            var from = { opacity: 0, transformPerspective: 800 };
            var to   = { opacity: 1, transformPerspective: 800 };
            if (horiz) { from.rotationX = deg; to.rotationX = 0; }
            else       { from.rotationY = deg; to.rotationY = 0; }
            oneShot(el, from, to);
        }

        // Expand / Grow — scales in from a line (scaleX or scaleY from 0) about an origin edge.
        // Ideal for underlines, dividers, bars, progress rules. No fade (it grows into place).
        function expand(el) {
            var vertical = attr(el, 'data-upw-g-axis') === 'y';
            var origin = attr(el, 'data-upw-g-origin') || (vertical ? 'top' : 'left');
            el.style.transformOrigin = origin;
            var from = {}, to = {};
            if (vertical) { from.scaleY = 0; to.scaleY = 1; }
            else          { from.scaleX = 0; to.scaleX = 1; }
            oneShot(el, from, to);
        }

        // Count Up — animates the first number found inside the element from a start value to its
        // value as it scrolls in. Operates on the exact text node so surrounding markup is kept.
        // Two styles: 'count' (plain tween) and 'odometer' (per-digit vertical roll).
        function counter(el) {
            var dur = num(attr(el, 'data-upw-g-duration'), 2);
            var useSep = attr(el, 'data-upw-g-sep') === '1';
            var fromVal = num(attr(el, 'data-upw-g-from'), 0);
            var uiPre = attr(el, 'data-upw-g-prefix') || '';
            var uiPost = attr(el, 'data-upw-g-suffix') || '';
            var style = attr(el, 'data-upw-g-cstyle') || 'count';
            var re = /-?\d[\d,]*(?:\.\d+)?/;
            var tw = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, null, false);
            var node = null, mm = null;
            while (tw.nextNode()) {
                var mt = tw.currentNode.nodeValue.match(re);
                if (mt) { node = tw.currentNode; mm = mt; break; }
            }
            if (!node) { el.classList.remove('upw-g-pending'); return; }
            var rawNum = mm[0].replace(/,/g, ''), endVal = parseFloat(rawNum);
            if (isNaN(endVal)) { el.classList.remove('upw-g-pending'); return; }
            var decimals = (rawNum.split('.')[1] || '').length;
            var pre = uiPre + node.nodeValue.slice(0, mm.index), post = node.nodeValue.slice(mm.index + mm[0].length) + uiPost;
            function fmt(v) {
                var s = v.toFixed(decimals);
                if (useSep) { var p = s.split('.'); p[0] = p[0].replace(/\B(?=(\d{3})+(?!\d))/g, ','); s = p.join('.'); }
                return s;
            }
            var trig = {
                trigger: el,
                start: startPos(attr(el, 'data-upw-g-start')),
                toggleActions: attr(el, 'data-upw-g-once') === '0' ? 'play none none reverse' : 'play none none none'
            };

            if (style === 'odometer') { buildOdometer(el, node, pre, post, fromVal, endVal, decimals, dur, trig); return; }

            var obj = { v: fromVal };
            node.nodeValue = pre + fmt(fromVal) + post;
            gsap.to(obj, {
                v: endVal, duration: dur, ease: 'power1.out', scrollTrigger: trig,
                onUpdate: function () { node.nodeValue = pre + fmt(obj.v) + post; }
            });
            el.classList.remove('upw-g-pending');
        }

        // Odometer — replaces the number's text node with per-digit vertical reels (0-9 stacked)
        // that roll to the final digits. Non-digit chars (commas / decimals) render as static cells.
        function buildOdometer(el, node, pre, post, fromVal, endVal, decimals, dur, trig) {
            var endStr = endVal.toFixed(decimals);
            var span = document.createElement('span');
            span.className = 'upw-g-odo';
            span.setAttribute('aria-label', pre + endStr + post);
            if (pre) span.appendChild(document.createTextNode(pre));
            var reels = [];
            for (var i = 0; i < endStr.length; i++) {
                var ch = endStr[i];
                if (ch < '0' || ch > '9') { span.appendChild(document.createTextNode(ch)); continue; }
                var reel = document.createElement('span'); reel.className = 'upw-g-odo-reel';
                var strip = document.createElement('span'); strip.className = 'upw-g-odo-strip';
                for (var d = 0; d <= 9; d++) { var c = document.createElement('span'); c.className = 'upw-g-odo-d'; c.textContent = d; strip.appendChild(c); }
                reel.appendChild(strip); span.appendChild(reel);
                reels.push({ strip: strip, target: parseInt(ch, 10) });
            }
            if (post) span.appendChild(document.createTextNode(post));
            node.parentNode.replaceChild(span, node);
            gsap.set(span, { display: 'inline-flex', alignItems: 'baseline' });
            reels.forEach(function (r) {
                gsap.set(r.strip, { display: 'inline-flex', flexDirection: 'column' });
                r.reel = r.strip.parentNode;
                gsap.set(r.reel, { display: 'inline-block', overflow: 'hidden', height: '1em', lineHeight: '1em', verticalAlign: 'bottom' });
            });
            gsap.to({}, {
                duration: 0.01, scrollTrigger: trig, onComplete: function () {
                    reels.forEach(function (r, idx) {
                        gsap.fromTo(r.strip, { yPercent: 0 },
                            { yPercent: -(r.target / 10) * 100, duration: dur, ease: 'power2.out', delay: idx * 0.08 });
                    });
                }
            });
            el.classList.remove('upw-g-pending');
        }

        // Velocity Skew — the element leans by an amount proportional to SCROLL SPEED and springs
        // back to 0 when scrolling stops. Canonical GSAP recipe: quickSetter + clamp + getVelocity().
        function velocitySkew(el) {
            var max = num(attr(el, 'data-upw-g-max'), 20);
            var axis = attr(el, 'data-upw-g-axis') === 'x' ? 'skewX' : 'skewY';
            gsap.set(el, { transformOrigin: attr(el, 'data-upw-g-origin') || 'center center', force3D: true });
            var clamp = gsap.utils.clamp(-max, max);
            var setter = gsap.quickSetter(el, axis, 'deg');
            var proxy = { s: 0 };
            window.ScrollTrigger.create({
                trigger: el, start: 'top bottom', end: 'bottom top',
                onUpdate: function (self) {
                    var s = clamp(self.getVelocity() / -300);
                    if (Math.abs(s) > Math.abs(proxy.s)) {
                        proxy.s = s;
                        gsap.to(proxy, { s: 0, duration: 0.8, ease: 'power3', overwrite: true, onUpdate: function () { setter(proxy.s); } });
                    }
                }
            });
        }

        // 3D Tilt Scrub — perspective lean (rotationX/Y) tied to scroll position; the element tips
        // from +deg to -deg as it travels through the viewport. Distinct from flat Parallax.
        function tiltScrub(el) {
            var axis = attr(el, 'data-upw-g-axis') === 'x' ? 'rotationX' : 'rotationY';
            var deg = num(attr(el, 'data-upw-g-deg'), 12);
            gsap.set(el, { transformPerspective: 800, transformOrigin: 'center center' });
            var from = {}, to = { ease: 'none', scrollTrigger: { trigger: el, start: 'top bottom', end: 'bottom top', scrub: true } };
            from[axis] = deg; to[axis] = -deg;
            gsap.fromTo(el, from, to);
        }

        // Scroll Spin — continuous rotation scrubbed across the viewport (a wheel/badge that turns).
        function scrollSpin(el) {
            var turns = num(attr(el, 'data-upw-g-turns'), 1);
            if (attr(el, 'data-upw-g-dir') === 'ccw') turns = -turns;
            gsap.fromTo(el, { rotation: 0 },
                { rotation: 360 * turns, ease: 'none', scrollTrigger: { trigger: el, start: 'top bottom', end: 'bottom top', scrub: true } });
        }

        // Mask Wipe — a soft, feathered reveal that sweeps across (distinct from Clip's hard edge).
        // The mask gradient is regenerated each frame so the feathered leading edge is exact.
        function maskWipe(el) {
            var dir = attr(el, 'data-upw-g-dir') || 'left';
            var soft = num(attr(el, 'data-upw-g-soft'), 25);
            var toDir = { left: 'to right', right: 'to left', up: 'to bottom', down: 'to top' }[dir] || 'to right';
            function apply(p) {
                var edge = p * (100 + soft);
                var g = 'linear-gradient(' + toDir + ', #000 ' + (edge - soft) + '%, transparent ' + edge + '%)';
                el.style.webkitMaskImage = g; el.style.maskImage = g;
            }
            apply(0);
            var proxy = { p: 0 };
            gsap.to(proxy, {
                p: 1, duration: 0.9, ease: 'power2.out', delay: num(attr(el, 'data-upw-g-delay'), 0),
                scrollTrigger: entranceTrigger(el), onUpdate: function () { apply(proxy.p); },
                onComplete: function () { el.style.webkitMaskImage = el.style.maskImage = 'none'; }
            });
            el.classList.remove('upw-g-pending');
        }

        // Color Scrub — tween the element's text or background colour A→B as it scrolls through.
        // Raw values may be a hex or a var(--color-x) preset; resolve each to a concrete rgb() by
        // reading it back off the element so GSAP's colour interpolation always has real values.
        function colorScrub(el) {
            var bg = attr(el, 'data-upw-g-ctarget') === 'bg';
            var target = bg ? 'backgroundColor' : 'color';
            var cssProp = bg ? 'background-color' : 'color';
            function resolve(raw, fb) {
                var prev = el.style.getPropertyValue(cssProp);
                el.style.setProperty(cssProp, raw || fb);
                var c = getComputedStyle(el)[target];
                el.style.setProperty(cssProp, prev);
                return c;
            }
            var from = {}, to = { ease: 'none', scrollTrigger: { trigger: el, start: 'top 80%', end: 'bottom 40%', scrub: true } };
            from[target] = resolve(attr(el, 'data-upw-g-c1'), '#888888');
            to[target]   = resolve(attr(el, 'data-upw-g-c2'), '#2f74e6');
            gsap.fromTo(el, from, to);
        }

        var BUILDERS = {
            reveal: reveal, stagger: stagger, splittext: splittext,
            parallax: parallax, pin: pin, scrub: scrub,
            zoom: zoom, rotate: rotateIn, blur: blurIn, clip: clipIn, skew: skewIn,
            flip: flipIn, expand: expand, counter: counter,
            velocity_skew: velocitySkew, tilt_scrub: tiltScrub, scroll_spin: scrollSpin,
            mask_wipe: maskWipe, color_scrub: colorScrub
        };

        function build(el) {
            if (el.__upwG) return;
            el.__upwG = true;

            // Per-element mobile opt-out.
            if (attr(el, 'data-upw-g-mobile') === '0' && isMobile) {
                el.classList.remove('upw-g-pending');
                return;
            }

            var fn = BUILDERS[attr(el, 'data-upw-g')];
            if (fn) { fn(el); } else { el.classList.remove('upw-g-pending'); }
        }

        function scan(root) {
            (root || document).querySelectorAll('[data-upw-g]').forEach(build);
        }

        /**
         * MOTION SEQUENCE (module: motion-sequence). A Section marked `.upw-seq` plays its descendant
         * Reveal / Stagger animations as ONE gsap.timeline() in document order, instead of each firing
         * independently. Reuses this runtime's config builders (compound / staggerVars). Must run
         * BEFORE scan() so it can CLAIM its steps (mark __upwG) and stop them self-triggering.
         */
        function initSequences(root) {
            (root || document).querySelectorAll('.upw-seq').forEach(function (sec) {
                if (sec.__upwSeq) { return; }
                sec.__upwSeq = true;
                if (attr(sec, 'data-upw-seq-mobile') === '0' && isMobile) { return; } // children fall back to standalone

                // Steps = descendant Reveal/Stagger elements in document order (querySelectorAll is
                // already document-ordered). Only these two share the compound() entrance config;
                // other effects inside the Section keep their own standalone behaviour.
                var steps = [].slice.call(sec.querySelectorAll('[data-upw-g="reveal"], [data-upw-g="stagger"]'))
                    .filter(function (el) { return !el.__upwG; });
                if (!steps.length) { return; }

                var overlap = num(attr(sec, 'data-upw-seq-overlap'), 0.35);
                var scrub   = attr(sec, 'data-upw-seq-trigger') === 'scrub';
                var start   = startPos(attr(sec, 'data-upw-seq-start')) || 'top 80%';

                var st = scrub
                    ? { trigger: sec, start: 'top 70%', end: 'bottom 60%', scrub: true }
                    : { trigger: sec, start: start, toggleActions: 'play none none none' };
                var tl = gsap.timeline({ scrollTrigger: st });

                steps.forEach(function (el, i) {
                    el.__upwG = true;                 // claim: scan()/build() will skip it
                    el.classList.remove('upw-g-pending');
                    var isStagger = attr(el, 'data-upw-g') === 'stagger';
                    var c = compound(el, styleOf(el));
                    var dur = (c.to && c.to.duration) || 0.6;
                    // Position: first step at the start; each later step begins `overlap` seconds
                    // before the previous tween ENDS (">-<overlap>"). overlap ≥ duration → sequential.
                    var pos = i === 0 ? 0 : '>-' + Math.max(0, Math.min(dur, overlap));
                    if (isStagger) {
                        var kids = staggerTargets(el);
                        var each = num(attr(el, 'data-upw-g-stagger'), 0.12);
                        tl.fromTo(kids, c.from, Object.assign({}, c.to, { stagger: each }), pos);
                    } else {
                        tl.fromTo(el, c.from, c.to, pos);
                    }
                });
            });
        }

        /**
         * MOTION SNIPPETS (Scroll Effect → Custom Code). The author's GSAP is base64 in the element's
         * `data-upw-snip` (baked into post_content). We run it with (el, tl, gsap), where `tl` is a
         * fresh timeline already tied to a ScrollTrigger. EXECUTION IS GATED: only runs when
         * window.upwSnippetsOK is set — the footer emits that flag solely when the page AUTHOR has
         * unfiltered_html, so untrusted baked code never executes. Reduced motion already bailed above.
         */
        function runSnippets(root) {
            if (!window.upwSnippetsOK) { return; } // per-request author gate; no flag = never run
            (root || document).querySelectorAll('[data-upw-snip]').forEach(function (el) {
                if (el.__upwSnip) { return; }
                el.__upwSnip = true;
                el.classList.remove('upw-g-pending');
                if (isMobile && attr(el, 'data-upw-snip-mobile') === '0') { return; }
                var code;
                try { code = decodeURIComponent(escape(window.atob(attr(el, 'data-upw-snip') || ''))); }
                catch (e) { try { code = window.atob(attr(el, 'data-upw-snip') || ''); } catch (e2) { return; } }
                if (!code) { return; }
                try {
                    var tl = gsap.timeline({ scrollTrigger: { trigger: el, start: 'top 80%' } });
                    /* eslint-disable no-new-func */
                    var fn = new Function('el', 'tl', 'gsap', code);
                    fn(el, tl, gsap);
                } catch (e) { if (window.console) { console.warn('[Motion Snippet]', e); } }
            });
        }

        initSequences();
        scan();
        runSnippets();

        // Pick up content injected late (AJAX, infinite scroll, etc.).
        if ('MutationObserver' in window) {
            var mo = new MutationObserver(function (muts) {
                muts.forEach(function (m) {
                    m.addedNodes && m.addedNodes.forEach(function (n) {
                        if (n.nodeType !== 1) return;
                        // A late-injected sequence must claim its steps BEFORE they're scanned.
                        if (n.classList && n.classList.contains('upw-seq')) { initSequences(n.parentNode || n); }
                        else if (n.querySelector && n.querySelector('.upw-seq')) { initSequences(n); }
                        if (n.hasAttribute && n.hasAttribute('data-upw-g')) build(n);
                        if (n.querySelectorAll) { scan(n); runSnippets(n); }
                    });
                });
            });
            mo.observe(document.body || document.documentElement, { childList: true, subtree: true });
        }

        // Recalculate triggers once images/fonts settle.
        window.addEventListener('load', function () {
            if (window.ScrollTrigger) window.ScrollTrigger.refresh();
        });
    });
})();
