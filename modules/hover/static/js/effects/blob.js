/* Animation Engine — Hover "blob": JS feeds --bx/--by; CSS paints the following blob. */
(function () {
	(window.upwHoverFx = window.upwHoverFx || {}).blob = {
		pointer: true, reduceSkip: false,
		run: function (el) {
			if (getComputedStyle(el).position === 'static') { el.style.position = 'relative'; }
			el.addEventListener('pointermove', function (e) {
				var r = el.getBoundingClientRect();
				el.style.setProperty('--bx', (e.clientX - r.left) + 'px');
				el.style.setProperty('--by', (e.clientY - r.top) + 'px');
			});
		}
	};
})();
