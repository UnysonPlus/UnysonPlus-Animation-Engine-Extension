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

	/* CSS-driven helpers used by the CSS-class partials. */
	function cssEffect(cls) { return function (el, target) { target.classList.add(cls); }; }
	function cssTriggered(cls) {
		return function (el, target) {
			var t = target; t.classList.add(cls);
			if ((el.getAttribute('data-text-trigger') || 'hover') === 'view') { onView(el, function () { t.classList.add('is-on'); }); }
		};
	}

	// Bind a one-shot effect's play() to every selected trigger. data-text-trigger is a space-
	// separated list of view / load / click / hover (missing = 'view'). view/load fire once;
	// click/hover replay — the effect's play() should supersede any in-flight run (see the token
	// pattern in scramble.js / typewriter.js) so replays don't stack.
	function bindTriggers(el, play) {
		var t = (el.getAttribute('data-text-trigger') || 'view').split(/\s+/).filter(Boolean);
		if (!t.length) { t = ['view']; }
		var has = function (x) { return t.indexOf(x) >= 0; };
		if (has('load')) { play(); }
		if (has('view')) { onView(el, play); }
		if (has('click')) { el.addEventListener('click', play); }
		if (has('hover')) { el.addEventListener('mouseenter', play); }
	}

	var FLAP = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	var GLYPH = 'ｱｲｳｴｵｶｷｸｹｺｻｼｽｾｿ0123456789ABCDEF';

	// Shared registry + API surface the per-effect partials use.
	var H = window.upwText || (window.upwText = {});
	window.upwTextApi = {
		cfg: cfg, reduce: reduce,
		targetsOf: targetsOf, onView: onView, piece: piece, wrapPieces: wrapPieces, wrapLines: wrapLines,
		cssEffect: cssEffect, cssTriggered: cssTriggered, bindTriggers: bindTriggers,
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
				try { if (window.upwTextApi.doRevealCascade) { window.upwTextApi.doRevealCascade(wrap, targets, fx); } } catch (e) { /* never break the page */ }
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
