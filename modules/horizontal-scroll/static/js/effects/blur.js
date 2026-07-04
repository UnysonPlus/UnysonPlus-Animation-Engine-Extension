/* Horizontal Scroll — per-panel style "blur" (on-demand partial). */
(window.upwHsFx = window.upwHsFx || {}).blur = function (d, i, intensity, lastScrolled, clamp) {
	return {
		fil: 'blur(' + clamp(Math.abs(d) * intensity * 9, 0, 10).toFixed(2) + 'px)',
		op: (1 - clamp(Math.abs(d) * intensity * 0.5, 0, 0.4)).toFixed(3)
	};
};
