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

	H.split_reveal = function (el) {
		var target = targetOf(el);
		var mode = el.getAttribute('data-text-split') || 'words';
		var dir = el.getAttribute('data-text-dir') || 'up';
		var stagger = parseFloat(el.getAttribute('data-text-stagger')) || 0.03;
		var dur = parseFloat(el.getAttribute('data-text-duration')) || 0.6;
		var trigger = el.getAttribute('data-text-trigger') || 'view';
		var pieces = mode === 'lines' ? wrapLines(target) : wrapPieces(target, mode);
		if (!pieces.length) { return; }
		if (mode === 'lines') { target.classList.add('upw-text-lines'); }
		var tx = dir === 'left' ? '-0.5em' : dir === 'right' ? '0.5em' : '0';
		var ty = dir === 'up' ? '0.85em' : dir === 'down' ? '-0.85em' : '0';
		for (var i = 0; i < pieces.length; i++) {
			var p = pieces[i];
			p.style.opacity = '0';
			p.style.transform = 'translate(' + tx + ',' + ty + ')';
			p.style.transition = 'opacity ' + dur + 's ease, transform ' + dur + 's cubic-bezier(.2,.7,.2,1)';
			p.style.transitionDelay = (i * stagger) + 's';
			p.style.willChange = 'opacity, transform';
		}
		function play() { for (var k = 0; k < pieces.length; k++) { pieces[k].style.opacity = '1'; pieces[k].style.transform = 'none'; } }
		if (trigger === 'load') { requestAnimationFrame(play); } else { onView(el, play); }
	};

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
