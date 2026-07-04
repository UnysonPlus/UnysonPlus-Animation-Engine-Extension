/* Horizontal Scroll — per-panel style "rotate3d" (on-demand partial). */
(window.upwHsFx = window.upwHsFx || {}).rotate3d = function (d, i, intensity, lastScrolled, clamp) {
	return {
		tf: 'perspective(1200px) rotateY(' + clamp(-d * intensity * 90, -55, 55).toFixed(2) + 'deg) scale(' + (1 - Math.abs(d) * 0.1).toFixed(3) + ')'
	};
};
