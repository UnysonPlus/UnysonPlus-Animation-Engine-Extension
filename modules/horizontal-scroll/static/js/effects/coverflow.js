/* Horizontal Scroll — per-panel style "coverflow" (on-demand partial). */
(window.upwHsFx = window.upwHsFx || {}).coverflow = function (d, i, intensity, lastScrolled, clamp) {
	return {
		tf: 'scale(' + (1 - clamp(Math.abs(d) * intensity * 1.1, 0, 0.4)).toFixed(3) + ')',
		op: (1 - clamp(Math.abs(d) * intensity * 1.4, 0, 0.65)).toFixed(3)
	};
};
