/* Animation Engine — Hover "magnetic_letters": split the text into chars; each pulls toward the pointer. */
(function () {
	(window.upwHoverFx = window.upwHoverFx || {}).magnetic_letters = {
		pointer: true, reduceSkip: true,
		run: function (el) {
			if (el.__upwML) { return; }
			el.__upwML = true;
			var text = el.textContent;
			if (!text) { return; }
			el.textContent = '';
			var chars = [];
			for (var i = 0; i < text.length; i++) {
				var sp = document.createElement('span');
				sp.className = 'sc-hover-ml-char';
				sp.textContent = text[i];
				el.appendChild(sp);
				chars.push(sp);
			}
			var s = parseFloat(el.getAttribute('data-hover-ml-strength')) || 1;
			var MAX = 80;

			// Measure each char's RESTING centre ONCE (transforms cleared first) and cache it — so the
			// hot pointermove path never calls getBoundingClientRect per char (that was N forced reflows
			// per pointer event = classic layout thrash). Re-measured on hover-in and on resize.
			var centers = [];
			function measure() {
				for (var j = 0; j < chars.length; j++) {
					chars[j].style.transform = '';
					var r = chars[j].getBoundingClientRect();
					centers[j] = { x: r.left + r.width / 2, y: r.top + r.height / 2 };
				}
			}

			var mx = 0, my = 0, ticking = false, hovering = false;
			function apply() {
				ticking = false;
				if (!hovering) { return; }
				for (var j = 0; j < chars.length; j++) {
					var c = centers[j]; if (!c) { continue; }
					var dx = mx - c.x, dy = my - c.y;
					var dist = Math.sqrt(dx * dx + dy * dy);
					if (dist < MAX) {
						var f = (1 - dist / MAX) * s * 0.4;
						chars[j].style.transform = 'translate(' + (dx * f).toFixed(1) + 'px,' + (dy * f).toFixed(1) + 'px)';
					} else {
						chars[j].style.transform = '';
					}
				}
			}

			el.addEventListener('pointerenter', function () { hovering = true; measure(); });
			el.addEventListener('pointermove', function (e) {
				mx = e.clientX; my = e.clientY;
				if (!ticking) { ticking = true; requestAnimationFrame(apply); } // coalesce writes to one per frame
			}, { passive: true });
			el.addEventListener('pointerleave', function () {
				hovering = false;
				for (var k = 0; k < chars.length; k++) { chars[k].style.transform = ''; }
			});
			window.addEventListener('resize', function () { if (hovering) { measure(); } }, { passive: true });
		}
	};
})();
