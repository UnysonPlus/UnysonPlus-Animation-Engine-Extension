/* Animation Engine — Hover "text_scramble": characters resolve from random glyphs. */
(function () {
	(window.upwHoverFx = window.upwHoverFx || {}).text_scramble = {
		pointer: false, reduceSkip: true,
		run: function (el) {
			var dur = (parseFloat(el.getAttribute('data-hover-duration')) || 0.8) * 1000;
			var chars = '!<>-_\\/[]{}=+*^?#abcdef0123456789';
			var orig = el.getAttribute('data-hover-orig');
			if (orig === null) { orig = el.textContent; el.setAttribute('data-hover-orig', orig); }
			var running = false;

			el.addEventListener('pointerenter', function () {
				if (running) { return; }
				running = true;
				var text = orig, len = text.length, start = 0;
				var th = [];
				for (var i = 0; i < len; i++) { th[i] = (i / len) * 0.6 + Math.random() * 0.4; }

				function frame(ts) {
					if (!start) { start = ts; }
					var p = Math.min(1, (ts - start) / dur);
					var out = '';
					for (var i = 0; i < len; i++) {
						var c = text.charAt(i);
						if (c === ' ' || p >= th[i]) { out += c; }
						else { out += chars.charAt(Math.floor(Math.random() * chars.length)); }
					}
					el.textContent = out;
					if (p < 1) { requestAnimationFrame(frame); }
					else { el.textContent = text; running = false; }
				}
				requestAnimationFrame(frame);
			});
		}
	};
})();
