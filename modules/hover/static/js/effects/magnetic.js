/* Animation Engine — Hover "magnetic": the element is pulled toward (or pushed from) the cursor. */
(function () {
	(window.upwHoverFx = window.upwHoverFx || {}).magnetic = {
		pointer: true, reduceSkip: true,
		run: function (el) {
			var s = parseFloat(el.getAttribute('data-hover-strength')) || 0.3;
			if (el.getAttribute('data-hover-mode') === 'push') { s = -s; } // push away from the cursor
			el.addEventListener('pointermove', function (e) {
				var r = el.getBoundingClientRect();
				var x = (e.clientX - (r.left + r.width / 2)) * s;
				var y = (e.clientY - (r.top + r.height / 2)) * s;
				el.style.transform = 'translate(' + x.toFixed(1) + 'px,' + y.toFixed(1) + 'px)';
			});
			el.addEventListener('pointerleave', function () { el.style.transform = ''; });
		}
	};
})();
