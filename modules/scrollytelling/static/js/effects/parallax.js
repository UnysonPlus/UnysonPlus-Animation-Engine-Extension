/**
 * Scrollytelling style: Parallax Depth. Layers crossfade (base CSS) AND the pinned media drifts
 * vertically at a fraction of the scroll — adds depth. Scrub style: the core feeds 0..1 progress.
 */
(function () {
	'use strict';
	(window.upwStoryFx = window.upwStoryFx || {}).parallax = {
		scrub: true,
		onProgress: function (section, p, ctx) {
			var shift = (p - 0.5) * 2 * (ctx.intensity || 0.5) * 8; // -8%..+8% * intensity
			for (var i = 0; i < ctx.layers.length; i++) {
				var inner = ctx.layers[i].querySelector('img') || ctx.layers[i].firstElementChild;
				if (inner) { inner.style.transform = 'translate3d(0,' + shift.toFixed(2) + '%,0) scale(1.12)'; }
			}
		}
	};
})();
