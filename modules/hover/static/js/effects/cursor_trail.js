/* Animation Engine — Hover "cursor_trail": spawns a fading dot as the pointer moves over the element. */
(function () {
	(window.upwHoverFx = window.upwHoverFx || {}).cursor_trail = {
		pointer: true, reduceSkip: true,
		run: function (el) {
			if (getComputedStyle(el).position === 'static') { el.style.position = 'relative'; }
			el.style.overflow = 'hidden';
			var last = 0;
			el.addEventListener('pointermove', function (e) {
				var now = (window.performance && performance.now()) || Date.now();
				if (now - last < 40) { return; }
				last = now;
				var r = el.getBoundingClientRect();
				var d = document.createElement('span');
				d.className = 'sc-hover-trail-dot';
				d.style.left = (e.clientX - r.left) + 'px';
				d.style.top = (e.clientY - r.top) + 'px';
				el.appendChild(d);
				setTimeout(function () { if (d.parentNode) { d.parentNode.removeChild(d); } }, 620);
			});
		}
	};
})();
