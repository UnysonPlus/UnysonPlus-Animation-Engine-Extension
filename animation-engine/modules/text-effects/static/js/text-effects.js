/**
 * Animation Engine — Text Effects runtime (vanilla, no deps).
 *
 * Scans [data-text] wrappers, resolves the text element inside each, and applies the
 * chosen effect: split_reveal / scramble / typewriter / shimmer / wave / glitch / vf_weight.
 * Reveal-type effects trigger on view (IntersectionObserver) or on load. Under reduced
 * motion nothing runs — the text is left exactly as authored.
 */
(function () {
	'use strict';

	var cfg = window.upwTextCfg || {};
	var mql = window.matchMedia || function () { return { matches: false }; };
	var reduce = cfg.reducedMotion && mql('(prefers-reduced-motion: reduce)').matches;

	/* The element that actually holds the visible text inside a wrapper. */
	function targetOf(el) {
		return el.querySelector('h1,h2,h3,h4,h5,h6,p,.sc-text-target') || el;
	}

	function onView(el, cb) {
		if (!('IntersectionObserver' in window)) { cb(); return; }
		var io = new IntersectionObserver(function (entries) {
			for (var i = 0; i < entries.length; i++) {
				if (entries[i].isIntersecting) { cb(); io.disconnect(); return; }
			}
		}, { threshold: 0.2, rootMargin: '0px 0px -8% 0px' });
		io.observe(el);
	}

	function piece(txt) {
		var s = document.createElement('span');
		s.className = 'upw-text-piece';
		s.textContent = txt;
		return s;
	}

	/* Wrap a target's text into inline-block spans by char or word (spaces preserved). */
	function wrapPieces(target, mode) {
		var text = target.textContent, frag = document.createDocumentFragment(), out = [], i;
		target.textContent = '';
		if (mode === 'words') {
			var parts = text.split(/(\s+)/);
			for (i = 0; i < parts.length; i++) {
				if (parts[i] === '') { continue; }
				if (/^\s+$/.test(parts[i])) { frag.appendChild(document.createTextNode(parts[i])); continue; }
				var w = piece(parts[i]); frag.appendChild(w); out.push(w);
			}
		} else {
			for (i = 0; i < text.length; i++) {
				if (text[i] === ' ') { frag.appendChild(document.createTextNode(' ')); continue; }
				var c = piece(text[i]); frag.appendChild(c); out.push(c);
			}
		}
		target.appendChild(frag);
		return out;
	}

	/* Group word-spans into line wrappers by their offsetTop (needs layout, so run post-fonts). */
	function wrapLines(target) {
		var words = wrapPieces(target, 'words');
		if (!words.length) { return []; }
		for (var i = 0; i < words.length; i++) { words[i]._top = words[i].offsetTop; }
		var nodes = Array.prototype.slice.call(target.childNodes);
		var lines = [], cur = [], top = null;
		for (i = 0; i < nodes.length; i++) {
			var n = nodes[i];
			if (n.nodeType === 1 && n.classList && n.classList.contains('upw-text-piece')) {
				if (top === null) { top = n._top; }
				if (Math.abs(n._top - top) > 2) { lines.push(cur); cur = []; top = n._top; }
			}
			cur.push(n);
		}
		if (cur.length) { lines.push(cur); }
		var lineSpans = [];
		for (i = 0; i < lines.length; i++) {
			var line = document.createElement('span'); line.className = 'upw-text-line';
			for (var j = 0; j < lines[i].length; j++) { line.appendChild(lines[i][j]); }
			target.appendChild(line); lineSpans.push(line);
		}
		return lineSpans;
	}

	var H = {};

	function translateFrom(dir, amt) {
		amt = amt || '0.85em';
		if (dir === 'left') { return 'translateX(-' + amt + ')'; }
		if (dir === 'right') { return 'translateX(' + amt + ')'; }
		if (dir === 'down') { return 'translateY(-' + amt + ')'; }
		return 'translateY(' + amt + ')'; // up (default)
	}

	// Each preset returns the piece's initial state (transform/filter/origin) + easing.
	var REVEAL = {
		split_reveal: function (dir) { return { tf: translateFrom(dir || 'up'), ease: 'cubic-bezier(.2,.7,.2,1)' }; },
		blur:   function () { return { tf: 'translateY(.3em)', filter: 'blur(8px)', ease: 'ease-out' }; },
		flip3d: function () { return { tf: 'perspective(500px) rotateX(-90deg)', origin: 'center bottom', ease: 'cubic-bezier(.2,.7,.2,1)' }; },
		scale:  function () { return { tf: 'scale(.3)', ease: 'cubic-bezier(.34,1.56,.64,1)' }; },
		slide:  function (dir) { return { tf: translateFrom(dir || 'left', '1.2em'), ease: 'cubic-bezier(.2,.7,.2,1)' }; },
		bounce: function () { return { tf: 'translateY(.9em)', ease: 'cubic-bezier(.28,1.6,.5,1)' }; },
		random: function () { return { tf: 'scale(.5) translateY(.3em)', ease: 'ease-out', shuffle: true }; },
		skew:   function () { return { tf: 'skewY(7deg) translateY(.5em)', ease: 'cubic-bezier(.2,.7,.2,1)' }; }
	};

	function doReveal(el, kind) {
		var target = targetOf(el);
		var mode = el.getAttribute('data-text-split') || 'chars';
		var dir = el.getAttribute('data-text-dir') || '';
		var stagger = parseFloat(el.getAttribute('data-text-stagger')) || 0.03;
		var dur = parseFloat(el.getAttribute('data-text-duration')) || 0.6;
		var trigger = el.getAttribute('data-text-trigger') || 'view';
		if (kind === 'mask') { return maskReveal(el, target, mode, stagger, dur, trigger); }
		var pieces = mode === 'lines' ? wrapLines(target) : wrapPieces(target, mode);
		if (!pieces.length) { return; }
		if (mode === 'lines') { target.classList.add('upw-text-lines'); }
		var preset = REVEAL[kind](dir), i;
		var order = []; for (i = 0; i < pieces.length; i++) { order.push(i); }
		if (preset.shuffle) { for (i = order.length - 1; i > 0; i--) { var j = (Math.random() * (i + 1)) | 0, t = order[i]; order[i] = order[j]; order[j] = t; } }
		for (i = 0; i < pieces.length; i++) {
			var p = pieces[i];
			p.style.opacity = '0';
			p.style.transform = preset.tf;
			if (preset.filter) { p.style.filter = preset.filter; }
			if (preset.origin) { p.style.transformOrigin = preset.origin; }
			p.style.transition = 'opacity ' + dur + 's ease, transform ' + dur + 's ' + preset.ease + (preset.filter ? ', filter ' + dur + 's ease' : '');
			p.style.transitionDelay = (order[i] * stagger) + 's';
			p.style.willChange = 'opacity, transform';
		}
		function play() { for (var k = 0; k < pieces.length; k++) { pieces[k].style.opacity = '1'; pieces[k].style.transform = 'none'; if (preset.filter) { pieces[k].style.filter = 'none'; } } }
		if (trigger === 'load') { requestAnimationFrame(play); } else { onView(el, play); }
	}

	// Mask reveal — each piece clips an inner span that slides up from behind it.
	function maskReveal(el, target, mode, stagger, dur, trigger) {
		var pieces = mode === 'lines' ? wrapLines(target) : wrapPieces(target, mode);
		if (!pieces.length) { return; }
		var inners = [];
		for (var i = 0; i < pieces.length; i++) {
			var p = pieces[i], inner = document.createElement('span'); inner.className = 'upw-text-inner';
			while (p.firstChild) { inner.appendChild(p.firstChild); }
			p.appendChild(inner);
			p.style.overflow = 'hidden';
			p.style.display = (mode === 'lines') ? 'block' : 'inline-block';
			inner.style.display = 'inline-block';
			inner.style.transform = 'translateY(110%)';
			inner.style.transition = 'transform ' + dur + 's cubic-bezier(.5,0,.1,1)';
			inner.style.transitionDelay = (i * stagger) + 's';
			inner.style.willChange = 'transform';
			inners.push(inner);
		}
		function play() { for (var k = 0; k < inners.length; k++) { inners[k].style.transform = 'translateY(0)'; } }
		if (trigger === 'load') { requestAnimationFrame(play); } else { onView(el, play); }
	}

	['split_reveal', 'blur', 'flip3d', 'scale', 'slide', 'bounce', 'random', 'skew'].forEach(function (k) {
		H[k] = function (el) { doReveal(el, k); };
	});
	H.mask = function (el) { doReveal(el, 'mask'); };

	H.scramble = function (el) {
		var target = targetOf(el);
		var dur = (parseFloat(el.getAttribute('data-text-duration')) || 1.2) * 1000;
		var trigger = el.getAttribute('data-text-trigger') || 'view';
		var final = target.textContent, len = final.length;
		var CH = '!<>-_\\/[]{}—=+*^?#abcdef0123456789';
		function run() {
			var start = null;
			(function frame(t) {
				if (!start) { start = t; }
				var prog = Math.min(1, (t - start) / dur), rev = Math.floor(prog * len), out = '', i;
				for (i = 0; i < len; i++) {
					if (final[i] === ' ') { out += ' '; }
					else if (i < rev) { out += final[i]; }
					else { out += CH[(Math.random() * CH.length) | 0]; }
				}
				target.textContent = out;
				if (prog < 1) { requestAnimationFrame(frame); } else { target.textContent = final; }
			})(0);
		}
		if (trigger === 'load') { run(); } else { onView(el, run); }
	};

	H.typewriter = function (el) {
		var target = targetOf(el);
		var speed = parseInt(el.getAttribute('data-text-speed'), 10) || 55;
		var caret = el.getAttribute('data-text-caret') === '1';
		var loop = el.getAttribute('data-text-loop') === '1';
		var trigger = el.getAttribute('data-text-trigger') || 'view';
		var full = target.textContent;
		target.textContent = '';
		if (caret) { target.classList.add('upw-text-caret'); }
		function type(cb) { var i = 0; (function s() { target.textContent = full.slice(0, i); if (i++ < full.length) { setTimeout(s, speed); } else if (cb) { cb(); } })(); }
		function erase(cb) { var i = full.length; (function s() { target.textContent = full.slice(0, i); if (i-- > 0) { setTimeout(s, speed / 1.7); } else if (cb) { cb(); } })(); }
		function cycle() { type(function () { if (loop) { setTimeout(function () { erase(function () { setTimeout(cycle, 350); }); }, 1400); } else if (caret) { target.classList.add('upw-text-caret-done'); } }); }
		if (trigger === 'load') { cycle(); } else { onView(el, cycle); }
	};

	H.shimmer = function (el) { targetOf(el).classList.add('upw-text-shimmer'); };

	H.wave = function (el) {
		var pieces = wrapPieces(targetOf(el), 'chars');
		for (var i = 0; i < pieces.length; i++) {
			pieces[i].classList.add('upw-text-wave-ch');
			pieces[i].style.animationDelay = (i * 0.06) + 's';
		}
	};

	H.glitch = function (el) {
		var target = targetOf(el);
		target.setAttribute('data-text-content', target.textContent);
		target.classList.add('upw-text-glitch');
		if ((el.getAttribute('data-text-trigger') || 'hover') === 'always') { target.classList.add('is-always'); }
	};

	H.vf_weight = function (el) {
		var target = targetOf(el);
		target.classList.add('upw-text-vf');
		if ((el.getAttribute('data-text-trigger') || 'hover') === 'view') {
			onView(el, function () { target.classList.add('is-on'); });
		}
	};

	function init() {
		if (reduce) { return; } // leave all text exactly as authored
		var nodes = document.querySelectorAll('[data-text]');
		Array.prototype.forEach.call(nodes, function (el) {
			if (el._upwText) { return; } el._upwText = true;
			var fn = H[el.getAttribute('data-text')];
			if (fn) { try { fn(el); } catch (e) { /* never break the page */ } }
		});
	}

	// Wait for fonts so line-splitting measures the real wrap points.
	function boot() { if (document.fonts && document.fonts.ready) { document.fonts.ready.then(init); } else { init(); } }
	if (document.readyState !== 'loading') { boot(); } else { document.addEventListener('DOMContentLoaded', boot); }
})();
