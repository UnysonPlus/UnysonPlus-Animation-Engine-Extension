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
            subtle:   { scale: 0.98, blur: 0,  ease: 'power2.out', duration: 0.6 },
            standard: { scale: 0.96, blur: 4,  ease: 'power3.out', duration: 0.9 },
            dramatic: { scale: 0.90, blur: 10, ease: 'expo.out',   duration: 1.2 }
        };

        function attr(el, name) { return el.getAttribute(name); }
        function num(v, d) { v = parseFloat(v); return isNaN(v) ? d : v; }
        function styleOf(el) { return STYLES[attr(el, 'data-upw-g-style')] || STYLES.standard; }
        function startPos(v) {
            return (typeof v === 'string' && /^[a-z]+ [a-z0-9%]+$/i.test(v)) ? v : 'top 85%';
        }

        // Translate a direction + distance into from-vars.
        function offsetFor(dir, dist) {
            var o = {};
            if (dir === 'up') o.y = dist;
            else if (dir === 'down') o.y = -dist;
            else if (dir === 'left') o.x = dist;
            else if (dir === 'right') o.x = -dist;
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
            c.to.scrollTrigger = {
                trigger: el,
                start: startPos(attr(el, 'data-upw-g-start')),
                toggleActions: attr(el, 'data-upw-g-once') === '0'
                    ? 'play none none reverse'
                    : 'play none none none'
            };
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

            Array.prototype.forEach.call(targets, function (t) {
                var split = new window.SplitText(t, { type: unit, linesClass: 'upw-g-line' });
                var pieces = split[unit];
                if (!pieces || !pieces.length) { return; }

                if (unit !== 'lines') gsap.set(pieces, { display: 'inline-block' });
                gsap.set(pieces, { opacity: 0, yPercent: 100 * dirSign });

                gsap.to(pieces, {
                    opacity: 1, yPercent: 0,
                    duration: Math.max(0.4, st.duration * 0.7),
                    ease: st.ease,
                    stagger: each,
                    scrollTrigger: { trigger: t, start: start },
                    onComplete: function () { if (split.revert) split.revert(); }
                });
            });
            el.classList.remove('upw-g-pending');
        }

        function parallax(el) {
            var prop = attr(el, 'data-upw-g-axis') === 'x' ? 'xPercent' : 'yPercent';
            var speed = num(attr(el, 'data-upw-g-speed'), 20);
            var from = {}; from[prop] = -speed;
            var to = { ease: 'none', scrollTrigger: { trigger: el, start: 'top bottom', end: 'bottom top', scrub: true } };
            to[prop] = speed;
            gsap.fromTo(el, from, to);
        }

        function pin(el) {
            var len = num(attr(el, 'data-upw-g-pin-length'), 100);
            window.ScrollTrigger.create({
                trigger: el,
                start: 'top top',
                end: '+=' + len + '%',
                pin: true,
                pinSpacing: true
            });
        }

        function scrub(el) {
            var kind = attr(el, 'data-upw-g-scrub-kind') || 'fade';
            var intensity = num(attr(el, 'data-upw-g-intensity'), 20);
            var from = {}, to = {
                ease: 'none',
                scrollTrigger: {
                    trigger: el,
                    start: startPos(attr(el, 'data-upw-g-start')),
                    end: 'center center',
                    scrub: true
                }
            };

            if (kind === 'scale') {
                from.scale = Math.max(0, 1 - intensity / 100); to.scale = 1;
            } else if (kind === 'rotate') {
                from.rotation = -intensity; to.rotation = 0;
            } else if (kind === 'slide') {
                from.yPercent = intensity; to.yPercent = 0;
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
            oneShot(el, { opacity: 0, scale: num(attr(el, 'data-upw-g-scale'), 0.6) },
                        { opacity: 1, scale: 1 });
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
                left: 'inset(0 100% 0 0)', right: 'inset(0 0 0 100%)'
            };
            var f = FROM[attr(el, 'data-upw-g-dir')] || FROM.up;
            oneShot(el, { clipPath: f, webkitClipPath: f },
                        { clipPath: 'inset(0% 0% 0% 0%)', webkitClipPath: 'inset(0% 0% 0% 0%)' });
        }
        function skewIn(el) {
            oneShot(el, { opacity: 0, skewY: num(attr(el, 'data-upw-g-skew'), 8), y: num(attr(el, 'data-upw-g-distance'), 40) },
                        { opacity: 1, skewY: 0, y: 0 });
        }

        var BUILDERS = {
            reveal: reveal, stagger: stagger, splittext: splittext,
            parallax: parallax, pin: pin, scrub: scrub,
            zoom: zoom, rotate: rotateIn, blur: blurIn, clip: clipIn, skew: skewIn
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

        scan();

        // Pick up content injected late (AJAX, infinite scroll, etc.).
        if ('MutationObserver' in window) {
            var mo = new MutationObserver(function (muts) {
                muts.forEach(function (m) {
                    m.addedNodes && m.addedNodes.forEach(function (n) {
                        if (n.nodeType !== 1) return;
                        if (n.hasAttribute && n.hasAttribute('data-upw-g')) build(n);
                        if (n.querySelectorAll) scan(n);
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
