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
			el.addEventListener('pointermove', function (e) {
				for (var j = 0; j < chars.length; j++) {
					var r = chars[j].getBoundingClientRect();
					var dx = e.clientX - (r.left + r.width / 2);
					var dy = e.clientY - (r.top + r.height / 2);
					var dist = Math.sqrt(dx * dx + dy * dy);
					if (dist < MAX) {
						var f = (1 - dist / MAX) * s * 0.4;
						chars[j].style.transform = 'translate(' + (dx * f).toFixed(1) + 'px,' + (dy * f).toFixed(1) + 'px)';
					} else {
						chars[j].style.transform = '';
					}
				}
			});
			el.addEventListener('pointerleave', function () {
				for (var k = 0; k < chars.length; k++) { chars[k].style.transform = ''; }
			});
		}
	};
})();
