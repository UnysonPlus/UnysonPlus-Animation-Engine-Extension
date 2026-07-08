/**
 * Scrollytelling style: Frame Sequence. The media items are treated as sequential frames and the
 * scroll progress (0..1) drives which frame shows (hard cuts) — a flipbook / mini-video. Scrub +
 * progress-media, so the core hands us continuous progress instead of the discrete step.
 */
(function () {
	'use strict';
	(window.upwStoryFx = window.upwStoryFx || {}).frame_sequence = {
		scrub: true, mediaMode: 'progress',
		onProgress: function (section, p, ctx) {
			var n = ctx.layers.length;
			var idx = Math.max(0, Math.min(n - 1, Math.floor(p * n)));
			for (var i = 0; i < n; i++) { ctx.layers[i].classList.toggle('is-active', i === idx); }
		}
	};
})();
