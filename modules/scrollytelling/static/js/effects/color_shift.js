/**
 * Scrollytelling style: Color Shift. Beyond the crossfade, the pinned panel hue-rotates per step,
 * so the whole media panel shifts colour as the story advances (works media-led or text-led).
 */
(function () {
	'use strict';
	(window.upwStoryFx = window.upwStoryFx || {}).color_shift = {
		onActivate: function (layer, i, ctx) {
			if (ctx.media) {
				ctx.media.style.transition = 'filter var(--story-trans, 0.6s) ease';
				ctx.media.style.filter = 'hue-rotate(' + ((i * 55) % 360) + 'deg) saturate(1.15)';
			}
		}
	};
})();
