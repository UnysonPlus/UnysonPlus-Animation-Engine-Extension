/* Horizontal Scroll — per-panel style "grow" (on-demand partial). */
(window.upwHsFx = window.upwHsFx || {}).grow = function (d, i, intensity, lastScrolled, clamp) {
	var g = Math.max(0, d);
	return {
		tf: 'scale(' + (1 - clamp(g * intensity * 1.2, 0, 0.4)).toFixed(3) + ')',
		op: (1 - clamp(g * intensity * 1.5, 0, 0.7)).toFixed(3)
	};
};
