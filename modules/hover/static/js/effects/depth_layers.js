/* Animation Engine — Hover "depth_layers": direct children parallax-shift by depth as the pointer moves. */
(function () {
	(window.upwHoverFx = window.upwHoverFx || {}).depth_layers = {
		pointer: true, reduceSkip: true,
		run: function (el) {
			var kids = Array.prototype.slice.call(el.children).filter(function (k) {
				return k.nodeType === 1 && getComputedStyle(k).position !== 'absolute';
			});
			if (!kids.length) { return; }
			var s = parseFloat(el.getAttribute('data-hover-depth')) || 1;
			el.addEventListener('pointermove', function (e) {
				var r = el.getBoundingClientRect();
				var dx = (e.clientX - (r.left + r.width / 2)) / (r.width || 1);
				var dy = (e.clientY - (r.top + r.height / 2)) / (r.height || 1);
				kids.forEach(function (k, i) {
					var d = (i + 1) * 4 * s;
					k.style.transform = 'translate(' + (dx * d).toFixed(1) + 'px,' + (dy * d).toFixed(1) + 'px)';
				});
			});
			el.addEventListener('pointerleave', function () {
				kids.forEach(function (k) { k.style.transform = ''; });
			});
		}
	};
})();
