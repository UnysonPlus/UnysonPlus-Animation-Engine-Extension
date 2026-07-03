/* Animation Engine — Hover "tilt": 3D rotateX/rotateY from the pointer position. */
(function () {
	(window.upwHoverFx = window.upwHoverFx || {}).tilt = {
		pointer: true, reduceSkip: true,
		run: function (el) {
			var max = parseFloat(el.getAttribute('data-hover-max')) || 12;
			var scale = parseFloat(el.getAttribute('data-hover-scale')) || 1;
			var glare = el.getAttribute('data-hover-glare') === '1';
			var gl = null;
			if (glare) {
				if (getComputedStyle(el).position === 'static') { el.style.position = 'relative'; }
				el.style.overflow = 'hidden';
				gl = document.createElement('span');
				gl.className = 'sc-hover-glare';
				el.appendChild(gl);
			}
			el.addEventListener('pointermove', function (e) {
				var r = el.getBoundingClientRect();
				var px = (e.clientX - r.left) / r.width - 0.5;
				var py = (e.clientY - r.top) / r.height - 0.5;
				el.style.transform = 'perspective(800px) rotateY(' + (px * max).toFixed(2) + 'deg) rotateX(' +
					(-py * max).toFixed(2) + 'deg)' + (scale !== 1 ? ' scale(' + scale + ')' : '');
				if (gl) {
					gl.style.opacity = '1';
					gl.style.background = 'radial-gradient(circle at ' + ((px + 0.5) * 100).toFixed(0) + '% ' +
						((py + 0.5) * 100).toFixed(0) + '%, rgba(255,255,255,.35), transparent 55%)';
				}
			});
			el.addEventListener('pointerleave', function () {
				el.style.transform = '';
				if (gl) { gl.style.opacity = '0'; }
			});
		}
	};
})();
