/* Horizontal Scroll — per-panel style "parallax" (on-demand partial). */
(window.upwHsFx = window.upwHsFx || {}).parallax = function (d, i, intensity, lastScrolled, clamp) {
	return {
		tf: 'translate3d(' + (((i % 2) ? 1 : -1) * lastScrolled * 0.08 * intensity).toFixed(1) + 'px,0,0)'
	};
};
