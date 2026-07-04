/* Horizontal Scroll — per-panel style "wave" (on-demand partial). */
(window.upwHsFx = window.upwHsFx || {}).wave = function (d, i, intensity, lastScrolled, clamp) {
	return {
		tf: 'translate3d(0,' + (Math.sin(d * Math.PI * 3) * intensity * 40).toFixed(1) + 'px,0)'
	};
};
