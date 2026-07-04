/**
 * Animation Engine — Scroll Text Highlight runtime.
 *
 * Splits each .sc-sth element's text into word (or character) spans, then scrubs an .is-on class
 * across them based on how far the element has scrolled through the viewport. One passive,
 * rAF-throttled scroll handler drives every instance. Honours reduced motion (everything lit).
 */
(function () {
	'use strict';
	if (typeof window === 'undefined' || typeof document === 'undefined') return;

	var CFG = window.upwSthCfg || {};
	var reduce = !!CFG.reducedMotion && window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	function splitEl(el, mode) {
		var words = [];
		var nodes = [];
		var walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, null);
		var n;
		while ((n = walker.nextNode())) {
			var p = n.parentNode;
			if (!p) continue;
			var tag = p.tagName;
			if (tag === 'SCRIPT' || tag === 'STYLE') continue;
			if (!n.nodeValue || !n.nodeValue.trim()) continue;
			nodes.push(n);
		}
		nodes.forEach(function (tn) {
			var frag = document.createDocumentFragment();
			var tokens = tn.nodeValue.match(/\s+|\S+/g) || [];
			tokens.forEach(function (tok) {
				if (/^\s+$/.test(tok)) {
					var sp = document.createElement('span');
					sp.className = 'sth-sp';
					sp.textContent = tok;
					frag.appendChild(sp);
					return;
				}
				if (mode === 'char') {
					var wrap = document.createElement('span');
					wrap.style.display = 'inline-block';
					wrap.style.whiteSpace = 'nowrap';
					for (var i = 0; i < tok.length; i++) {
						var c = document.createElement('span');
						c.className = 'sth-w';
						c.textContent = tok[i];
						wrap.appendChild(c);
						words.push(c);
					}
					frag.appendChild(wrap);
				} else {
					var w = document.createElement('span');
					w.className = 'sth-w';
					w.textContent = tok;
					frag.appendChild(w);
					words.push(w);
				}
			});
			tn.parentNode.replaceChild(frag, tn);
		});
		return words;
	}

	function update(item) {
		var el = item.el, words = item.words;
		var rect = el.getBoundingClientRect();
		var vh = window.innerHeight || document.documentElement.clientHeight;
		var startY = vh * 0.85;
		var endY = vh * 0.35;
		var span = (startY - endY) + Math.max(0, rect.height * 0.6);
		var p = (startY - rect.top) / span;
		p = p < 0 ? 0 : (p > 1 ? 1 : p);
		var active = Math.round(p * words.length);
		for (var i = 0; i < words.length; i++) {
			var on = i < active;
			if (item.once) { if (on) words[i].classList.add('is-on'); }
			else { words[i].classList.toggle('is-on', on); }
		}
	}

	var items = [];
	function build(el) {
		if (el.__upwSthDone) return;
		el.__upwSthDone = true;
		var mode = el.getAttribute('data-sth-split') === 'char' ? 'char' : 'word';
		var words = splitEl(el, mode);
		if (!words.length) return;
		if (reduce) { words.forEach(function (w) { w.classList.add('is-on'); }); return; }
		items.push({ el: el, words: words, once: el.getAttribute('data-sth-once') !== '0' });
	}

	var ticking = false;
	function onScroll() {
		if (ticking) return;
		ticking = true;
		requestAnimationFrame(function () {
			for (var i = 0; i < items.length; i++) { update(items[i]); }
			ticking = false;
		});
	}

	function init() {
		Array.prototype.forEach.call(document.querySelectorAll('.sc-sth'), build);
		if (!items.length) return;
		window.addEventListener('scroll', onScroll, { passive: true });
		window.addEventListener('resize', onScroll);
		onScroll();
	}

	document.readyState !== 'loading' ? init() : document.addEventListener('DOMContentLoaded', init);
})();
