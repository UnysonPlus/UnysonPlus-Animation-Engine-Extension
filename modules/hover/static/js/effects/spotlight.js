/* Animation Engine — Hover "spotlight": JS feeds --mx/--my; CSS paints the glow. */
(function () {
	(window.upwHoverFx = window.upwHoverFx || {}).spotlight = {
		pointer: true, reduceSkip: false,
		run: function (el) {
			if (getComputedStyle(el).position === 'static') { el.style.position = 'relative'; }
			el.addEventListener('pointermove', function (e) {
				var r = el.getBoundingClientRect();
				el.style.setProperty('--mx', (e.clientX - r.left) + 'px');
				el.style.setProperty('--my', (e.clientY - r.top) + 'px');
			});
		}
	};
})();
