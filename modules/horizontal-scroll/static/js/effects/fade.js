/* Horizontal Scroll — per-panel style "fade" (on-demand partial). */
(window.upwHsFx = window.upwHsFx || {}).fade = function (d, i, intensity, lastScrolled, clamp) {
	return {
		op: (1 - clamp(Math.abs(d) * intensity * 1.6, 0, 0.82)).toFixed(3)
	};
};
