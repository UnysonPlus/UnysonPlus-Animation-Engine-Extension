/**
 * Animation Engine — Text Effects core (shared text engine + dispatch).
 *
 * Loads FIRST; each per-effect partial (static/js/effects/<effect>.js) loads after it, aliases
 * these helpers at load time, and registers window.upwText[<effect>] = fn(wrap, target). This
 * core exposes the shared engine (split-into-pieces/lines, reveal/mask presets, CSS-class
 * helpers, decode alphabets) on window.upwTextApi and runs the dispatch once fonts are ready
 * (by then all used-effect partials have registered). Only the effects a page uses are
 * enqueued, so no page pays for the other ~36 text effects. No dependencies.
 */
(function () {
	'use strict';

	var cfg = window.upwTextCfg || {};
	var mql = window.matchMedia || function () { return { matches: false }; };
	var reduce = cfg.reducedMotion && mql('(prefers-reduced-motion: reduce)').matches;

	function targetsOf(el) {
		var list = el.querySelectorAll('h1,h2,h3,h4,h5,h6,p,li,blockquote,.sc-text-target');
		return list.length ? Array.prototype.slice.call(list) : [el];
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

	function translateFrom(dir, amt) {
		amt = amt || '0.85em';
		if (dir === 'left') { return 'translateX(-' + amt + ')'; }
		if (dir === 'right') { return 'translateX(' + amt + ')'; }
		if (dir === 'down') { return 'translateY(-' + amt + ')'; }
		return 'translateY(' + amt + ')'; // up (default)
	}

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

	function revealSetup(el, target, kind, offset) {
		var mode = el.getAttribute('data-text-split') || 'chars';
		var dir = el.getAttribute('data-text-dir') || '';
		var stagger = parseFloat(el.getAttribute('data-text-stagger')) || 0.03;
		var dur = parseFloat(el.getAttribute('data-text-duration')) || 0.6;
		if (kind === 'mask') { return maskSetup(el, target, mode, stagger, dur, offset); }
		var pieces = mode === 'lines' ? wrapLines(target) : wrapPieces(target, mode);
		if (!pieces.length) { return { play: function () {}, count: 0 }; }
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
			p.style.transitionDelay = ((offset + order[i]) * stagger) + 's';
			p.style.willChange = 'opacity, transform';
		}
		return {
			count: pieces.length,
			play: function () { for (var k = 0; k < pieces.length; k++) { pieces[k].style.opacity = '1'; pieces[k].style.transform = 'none'; if (preset.filter) { pieces[k].style.filter = 'none'; } } }
		};
	}

	function maskSetup(el, target, mode, stagger, dur, offset) {
		var pieces = mode === 'lines' ? wrapLines(target) : wrapPieces(target, mode);
		if (!pieces.length) { return { play: function () {}, count: 0 }; }
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
			inner.style.transitionDelay = ((offset + i) * stagger) + 's';
			inner.style.willChange = 'transform';
			inners.push(inner);
		}
		return { count: pieces.length, play: function () { for (var k = 0; k < inners.length; k++) { inners[k].style.transform = 'translateY(0)'; } } };
	}

	function doReveal(el, target, kind) {
		var s = revealSetup(el, target, kind, 0);
		if ((el.getAttribute('data-text-trigger') || 'view') === 'load') { requestAnimationFrame(s.play); } else { onView(el, s.play); }
	}

	function doRevealCascade(wrap, targets, kind) {
		var plays = [], offset = 0;
		targets.forEach(function (target) { var s = revealSetup(wrap, target, kind, offset); plays.push(s.play); offset += s.count; });
		function playAll() { for (var i = 0; i < plays.length; i++) { plays[i](); } }
		if ((wrap.getAttribute('data-text-trigger') || 'view') === 'load') { requestAnimationFrame(playAll); } else { onView(wrap, playAll); }
	}

	/* CSS-driven helpers used by the CSS-class partials. */
	function cssEffect(cls) { return function (el, target) { target.classList.add(cls); }; }
	function cssTriggered(cls) {
		return function (el, target) {
			var t = target; t.classList.add(cls);
			if ((el.getAttribute('data-text-trigger') || 'hover') === 'view') { onView(el, function () { t.classList.add('is-on'); }); }
		};
	}

	var FLAP = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	var GLYPH = 'ｱｲｳｴｵｶｷｸｹｺｻｼｽｾｿ0123456789ABCDEF';

	// Shared registry + API surface the per-effect partials use.
	var H = window.upwText || (window.upwText = {});
	window.upwTextApi = {
		cfg: cfg, reduce: reduce,
		targetsOf: targetsOf, onView: onView, piece: piece, wrapPieces: wrapPieces, wrapLines: wrapLines,
		translateFrom: translateFrom, doReveal: doReveal, cssEffect: cssEffect, cssTriggered: cssTriggered,
		FLAP: FLAP, GLYPH: GLYPH
	};

	var REVEAL_IDS = { split_reveal: 1, blur: 1, mask: 1, flip3d: 1, scale: 1, slide: 1, bounce: 1, random: 1, skew: 1 };

	function init() {
		if (reduce) { return; } // leave all text exactly as authored
		var nodes = document.querySelectorAll('[data-text]');
		Array.prototype.forEach.call(nodes, function (wrap) {
			if (wrap._upwText) { return; }
			var fx = wrap.getAttribute('data-text');
			var targets = targetsOf(wrap);
			// Reveal effects set to "one after another" cascade as one continuous sequence.
			if (REVEAL_IDS[fx] && wrap.getAttribute('data-text-seq') === 'cascade' && targets.length > 1) {
				wrap._upwText = true;
				try { doRevealCascade(wrap, targets, fx); } catch (e) { /* never break the page */ }
				return;
			}
			var fn = H[fx];
			if (!fn) { return; } // partial not registered yet — leave unmarked so a rescan can retry
			wrap._upwText = true;
			targets.forEach(function (target) {
				try { fn(wrap, target); } catch (e) { /* never break the page */ }
			});
		});
	}

	// Wait for fonts so line-splitting measures the real wrap points; the per-effect partials
	// (which load AFTER this core) have registered by the time fonts.ready resolves.
	function boot() { if (document.fonts && document.fonts.ready) { document.fonts.ready.then(init); } else { setTimeout(init, 0); } }
	if (document.readyState !== 'loading') { boot(); } else { document.addEventListener('DOMContentLoaded', boot); }
	window.upwTextRescan = init;
})();
