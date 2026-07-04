/**
 * UnysonPlus Animation Engine — 3D Flip Card.
 *
 * Restructures each .sc-flip element: its existing content becomes the front face, and a back face
 * (heading / text / image / button) is built from the data-flip-* attributes. Hover flips via CSS;
 * click / scroll / auto toggle .is-flipped in JS. Cube reads the card size into --flip-w/--flip-h.
 * No library.
 */
(function () {
	'use strict';
	if (typeof window === 'undefined' || typeof document === 'undefined') return;

	var each = function (list, fn) { Array.prototype.forEach.call(list, fn); };

	function isExternal(url) {
		try { var u = new URL(url, window.location.href); return !!u.host && u.host !== window.location.host; }
		catch (e) { return false; }
	}

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

		var img = el.getAttribute('data-flip-image');
		if (img) {
			back.style.backgroundImage = 'url("' + img + '")';
			var scrim = document.createElement('div');
			scrim.className = 'sc-flip-back-scrim';
			back.appendChild(scrim);
		}

		var heading = el.getAttribute('data-flip-heading');
		if (heading) {
			var h = document.createElement('h3');
			h.className = 'sc-flip-back-title';
			h.textContent = heading;
			back.appendChild(h);
		}
		var text = el.getAttribute('data-flip-text');
		if (text) {
			var t = document.createElement('div');
			t.className = 'sc-flip-back-text';
			text.split('\n').forEach(function (line, i) {
				if (i) t.appendChild(document.createElement('br'));
				t.appendChild(document.createTextNode(line));
			});
			back.appendChild(t);
		}
		var btn = el.getAttribute('data-flip-btn');
		if (btn) {
			var url = el.getAttribute('data-flip-btn-url');
			var a = document.createElement(url ? 'a' : 'span');
			a.className = 'sc-flip-back-btn';
			a.textContent = btn;
			if (url) {
				a.href = url;
				if (isExternal(url)) { a.target = '_blank'; a.rel = 'noopener noreferrer'; }
			}
			back.appendChild(a);
		}

		inner.appendChild(front);
		inner.appendChild(back);
		el.appendChild(inner);

		// Cube needs the real card dimensions for its translateZ (half the side).
		if (el.classList.contains('sc-flip--cube')) {
			var setDims = function () {
				el.style.setProperty('--flip-w', el.offsetWidth + 'px');
				el.style.setProperty('--flip-h', el.offsetHeight + 'px');
			};
			setDims();
			if (window.ResizeObserver) { new ResizeObserver(setDims).observe(el); }
			else { window.addEventListener('resize', setDims); }
		}

		/* --- Triggers (hover is pure CSS) --- */
		var isClick = el.classList.contains('sc-flip-click');
		var isScroll = el.classList.contains('sc-flip-scroll');
		var isAuto = el.classList.contains('sc-flip-auto');

		function setFlipped(on) {
			el.classList.toggle('is-flipped', on);
			el.setAttribute('aria-pressed', on ? 'true' : 'false');
		}

		if (isClick) {
			el.setAttribute('tabindex', '0');
			el.setAttribute('role', 'button');
			el.setAttribute('aria-pressed', 'false');
			var toggle = function () { setFlipped(!el.classList.contains('is-flipped')); };
			el.addEventListener('click', toggle);
			el.addEventListener('keydown', function (e) {
				if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
			});
		}

		if (isScroll) {
			if ('IntersectionObserver' in window) {
				var io = new IntersectionObserver(function (entries) {
					entries.forEach(function (en) {
						if (en.isIntersecting) { el.classList.add('is-flipped'); io.disconnect(); }
					});
				}, { threshold: 0.35 });
				io.observe(el);
			} else {
				el.classList.add('is-flipped');
			}
		}

		if (isAuto) {
			var iv = parseFloat(el.getAttribute('data-flip-interval')) || 3;
			var timer = null, inView = true;
			var tick = function () { setFlipped(!el.classList.contains('is-flipped')); };
			var start = function () { if (!timer) { timer = setInterval(tick, Math.max(500, iv * 1000)); } };
			var stop = function () { if (timer) { clearInterval(timer); timer = null; } };
			if ('IntersectionObserver' in window) {
				new IntersectionObserver(function (entries) {
					entries.forEach(function (en) {
						inView = en.isIntersecting;
						(inView && !document.hidden) ? start() : stop();
					});
				}, { threshold: 0.2 }).observe(el);
			} else { start(); }
			document.addEventListener('visibilitychange', function () {
				if (document.hidden) { stop(); } else if (inView) { start(); }
			});
			// Keyboard flip for accessibility.
			el.setAttribute('tabindex', '0');
			el.addEventListener('keydown', function (e) {
				if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); tick(); }
			});
		}
	}

	function init() { each(document.querySelectorAll('.sc-flip'), build); }
	document.readyState !== 'loading' ? init() : document.addEventListener('DOMContentLoaded', init);
})();
