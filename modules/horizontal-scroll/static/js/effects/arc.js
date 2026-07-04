/* Horizontal Scroll — per-panel style "arc" (on-demand partial). */
(window.upwHsFx = window.upwHsFx || {}).arc = function (d, i, intensity, lastScrolled, clamp) {
	return {
		tf: 'translate3d(0,' + (-intensity * 60 * Math.max(0, 1 - (d * 2) * (d * 2))).toFixed(1) + 'px,0)'
	};
};
