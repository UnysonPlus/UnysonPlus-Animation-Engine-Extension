/**
 * Animation Engine — the text splitter (window.upwSplit). Roadmap Phase 9b, in place of GSAP's SplitText.
 *
 *   var s = upwSplit(el, { type: 'chars' | 'words' | 'lines' | 'words,chars' …, linesClass: 'upw-g-line' });
 *   s.chars / s.words / s.lines   the pieces (arrays of elements), ready to animate
 *   s.revert()                    puts the original markup back
 *
 * Words keep their spaces and never break inside; chars sit inside their word. Lines are found by where the words
 * wrap, and are worked out again when the element changes width (until revert()). Inline markup (links, <strong>,
 * <em>, <br>) is kept: each piece is wrapped where it stands. Screen readers read the original text: the element gets
 * an aria-label with it and the pieces are hidden from them.
 */
(function () {
	'use strict';
	if (typeof window === 'undefined' || window.upwSplit) { return; }

	function wrapWords(root, words) {
		// Walk the text nodes (not the inline elements around them) and wrap each word where it stands.
		var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null, false), nodes = [], n;
		while ((n = walker.nextNode())) { nodes.push(n); }
		nodes.forEach(function (node) {
			var text = node.nodeValue;
			if (!text || !/\S/.test(text)) { return; }
			var frag = document.createDocumentFragment();
			text.split(/(\s+)/).forEach(function (part) {
				if (!part) { return; }
				if (/^\s+$/.test(part)) { frag.appendChild(document.createTextNode(part)); return; }
				var w = document.createElement('span');
				w.className = 'upw-split-word';
				w.style.display = 'inline-block';
				w.setAttribute('aria-hidden', 'true');
				w.textContent = part;
				frag.appendChild(w);
				words.push(w);
			});
			node.parentNode.replaceChild(frag, node);
		});
	}
	function wrapChars(words, chars) {
		words.forEach(function (w) {
			var t = w.textContent;
			w.textContent = '';
			// Array.from keeps emoji and other two-code-unit letters whole.
			Array.from(t).forEach(function (ch) {
				var c = document.createElement('span');
				c.className = 'upw-split-char';
				c.style.display = 'inline-block';
				c.textContent = ch;
				w.appendChild(c);
				chars.push(c);
			});
		});
	}
	function groupLines(el, words, cls) {
		// Words whose tops match share a line. Each line becomes a block holding its words, in order.
		var lines = [], cur = null, lastTop = null;
		words.forEach(function (w) {
			var top = Math.round(w.offsetTop);
			if (lastTop === null || Math.abs(top - lastTop) > 2) { cur = []; lines.push(cur); lastTop = top; }
			cur.push(w);
		});
		// Only plain text (words directly inside the element) is regrouped; nested inline markup keeps its words in place.
		var flat = words.every(function (w) { return w.parentNode === el; });
		if (!flat) { return lines.map(function (ws) { return ws; }); }
		var out = [];
		var tail = document.createDocumentFragment();
		while (el.firstChild) { tail.appendChild(el.firstChild); }
		lines.forEach(function (ws) {
			var line = document.createElement('span');
			line.className = cls || 'upw-split-line';
			line.style.display = 'block';
			line.setAttribute('aria-hidden', 'true');
			ws.forEach(function (w, i) { if (i) { line.appendChild(document.createTextNode(' ')); } line.appendChild(w); });
			el.appendChild(line);
			out.push(line);
		});
		return out;
	}

	window.upwSplit = function (el, opts) {
		opts = opts || {};
		var type = String(opts.type || 'chars');
		var want = { chars: /chars/.test(type), words: /words/.test(type) || /chars|lines/.test(type), lines: /lines/.test(type) };
		var original = el.innerHTML, label = el.getAttribute('aria-label'), width = el.clientWidth, ro = null;
		var res = { chars: [], words: [], lines: [], el: el };
		function build() {
			res.chars = []; res.words = []; res.lines = [];
			el.innerHTML = original;
			wrapWords(el, res.words);
			if (want.lines) {
				var ls = groupLines(el, res.words, opts.linesClass);
				res.lines = ls.length && ls[0].nodeType ? ls : [];
			}
			if (want.chars) { wrapChars(res.words, res.chars); }
		}
		if (label == null) { el.setAttribute('aria-label', (el.textContent || '').replace(/\s+/g, ' ').trim()); }
		build();
		// Lines depend on the width: split again when it changes (the pieces are then new elements).
		if (want.lines && window.ResizeObserver) {
			ro = new ResizeObserver(function () {
				if (Math.abs(el.clientWidth - width) < 1) { return; }
				width = el.clientWidth; build();
				if (typeof opts.onSplit === 'function') { opts.onSplit(res); }
			});
			ro.observe(el);
		}
		res.revert = function () {
			if (ro) { ro.disconnect(); ro = null; }
			el.innerHTML = original;
			if (label == null) { el.removeAttribute('aria-label'); } else { el.setAttribute('aria-label', label); }
		};
		return res;
	};
})();
