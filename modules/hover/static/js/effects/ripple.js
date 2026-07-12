/* Animation Engine — Hover "ripple": a circle emanates from the cursor entry point (or the centre). */
(function () {
	(window.upwHoverFx = window.upwHoverFx || {}).ripple = {
		pointer: false, reduceSkip: true,
		run: function (el) {
			if (getComputedStyle(el).position === 'static') { el.style.position = 'relative'; }
			el.style.overflow = 'hidden';
			var center = el.getAttribute('data-hover-ripple-origin') === 'center';
			el.addEventListener('pointerenter', function (e) {
				var r = el.getBoundingClientRect();
				var size = Math.max(r.width, r.height) * 2;
				var span = document.createElement('span');
				span.className = 'sc-hover-ripple';
				span.style.width = span.style.height = size + 'px';
				span.style.left = (center ? r.width / 2 : (e.clientX - r.left)) + 'px';
				span.style.top = (center ? r.height / 2 : (e.clientY - r.top)) + 'px';
				el.appendChild(span);
				setTimeout(function () { if (span.parentNode) { span.parentNode.removeChild(span); } }, 650);
			});
		}
	};
})();
