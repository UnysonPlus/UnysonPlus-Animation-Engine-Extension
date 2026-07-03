/**
 * UnysonPlus Animation Engine — 3D Flip Card.
 *
 * Restructures each .sc-flip element: its existing content becomes the front face, and a back face
 * is built from the data-flip-* attributes. Hover flips via CSS; click toggles .is-flipped (and is
 * keyboard accessible). No library.
 */
(function () {
	'use strict';
	if (typeof window === 'undefined' || typeof document === 'undefined') return;

	function build(el) {
		if (el.__upwFlipDone) return;
		el.__upwFlipDone = true;

		var inner = document.createElement('div');
		inner.className = 'sc-flip-inner';

		var front = document.createElement('div');
		front.className = 'sc-flip-front';
		while (el.firstChild) { front.appendChild(el.firstChild); }

		var back = document.createElement('div');
		back.className = 'sc-flip-back';
		var bg = el.getAttribute('data-flip-bg');
		var col = el.getAttribute('data-flip-color');
		if (bg) back.style.background = bg;
		if (col) back.style.color = col;

		var heading = el.getAttribute('data-flip-heading');
		var text = el.getAttribute('data-flip-text');
		if (heading) {
			var h = document.createElement('h3');
			h.className = 'sc-flip-back-title';
			h.textContent = heading;
			back.appendChild(h);
		}
		if (text) {
			var t = document.createElement('div');
			t.className = 'sc-flip-back-text';
			text.split('\n').forEach(function (line, i) {
				if (i) t.appendChild(document.createElement('br'));
				t.appendChild(document.createTextNode(line));
			});
			back.appendChild(t);
		}

		inner.appendChild(front);
		inner.appendChild(back);
		el.appendChild(inner);

		if (el.classList.contains('sc-flip-click')) {
			el.setAttribute('tabindex', '0');
			el.setAttribute('role', 'button');
			el.setAttribute('aria-pressed', 'false');
			var toggle = function () {
				var on = el.classList.toggle('is-flipped');
				el.setAttribute('aria-pressed', on ? 'true' : 'false');
			};
			el.addEventListener('click', toggle);
			el.addEventListener('keydown', function (e) {
				if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
			});
		}
	}

	function init() {
		var els = document.querySelectorAll('.sc-flip');
		Array.prototype.forEach.call(els, build);
	}

	document.readyState !== 'loading' ? init() : document.addEventListener('DOMContentLoaded', init);
})();
