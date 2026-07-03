/* Animation Engine — Hover "ripple": a circle emanates from where the cursor enters. */
(function () {
	(window.upwHoverFx = window.upwHoverFx || {}).ripple = {
		pointer: false, reduceSkip: true,
		run: function (el) {
			if (getComputedStyle(el).position === 'static') { el.style.position = 'relative'; }
			el.style.overflow = 'hidden';
			el.addEventListener('pointerenter', function (e) {
				var r = el.getBoundingClientRect();
				var size = Math.max(r.width, r.height) * 2;
				var span = document.createElement('span');
				span.className = 'sc-hover-ripple';
				span.style.width = span.style.height = size + 'px';
				span.style.left = (e.clientX - r.left) + 'px';
				span.style.top = (e.clientY - r.top) + 'px';
				el.appendChild(span);
				setTimeout(function () { if (span.parentNode) { span.parentNode.removeChild(span); } }, 650);
			});
		}
	};
})();
