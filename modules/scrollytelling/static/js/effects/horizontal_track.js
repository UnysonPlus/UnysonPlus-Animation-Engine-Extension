/**
 * Scrollytelling style: Horizontal Track. The media items lay side-by-side in a filmstrip; scroll
 * progress translates the strip sideways (the pinned column clips it). Scrub + progress-media.
 * base.css positions each layer full-size; we set its left offset and the shared translate.
 */
(function () {
	'use strict';
	(window.upwStoryFx = window.upwStoryFx || {}).horizontal_track = {
		scrub: true, mediaMode: 'progress',
		onProgress: function (section, p, ctx) {
			var n = ctx.layers.length;
			var shift = -p * (n - 1) * 100;
			for (var i = 0; i < n; i++) {
				ctx.layers[i].style.left = (i * 100) + '%';
				ctx.layers[i].style.transform = 'translateX(' + shift.toFixed(2) + '%)';
			}
		}
	};
})();
