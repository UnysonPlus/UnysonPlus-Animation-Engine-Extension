/* Sticky Card Stack — per-card style "peel" (on-demand partial). */
(window.upwStackFx = window.upwStackFx || {}).peel = function (i, cp, prevCp, ctx) {
	var I = ctx.intensity, center = ctx.center, parts = [], opacity = null, filter = '', origin = 'center top';
	parts.push('translateY(' + (-cp * 106).toFixed(2) + '%)');
	opacity = 1 - cp * 0.7;
	return { transform: parts.length ? parts.join(' ') : 'none', opacity: opacity, filter: filter, origin: origin };
};
