/* Horizontal Scroll — per-panel style "zigzag" (on-demand partial). */
(window.upwHsFx = window.upwHsFx || {}).zigzag = function (d, i, intensity, lastScrolled, clamp) {
	return {
		tf: 'translate3d(0,' + (((i % 2) ? 1 : -1) * intensity * 40).toFixed(1) + 'px,0)'
	};
};
