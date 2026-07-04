/* Sticky Card Stack — per-card style "push" (on-demand partial). */
(window.upwStackFx = window.upwStackFx || {}).push = function (i, cp, prevCp, ctx) {
	var I = ctx.intensity, center = ctx.center, parts = [], opacity = null, filter = '', origin = 'center top';
	parts.push('translateY(' + (-cp * 102).toFixed(2) + '%)');
	return { transform: parts.length ? parts.join(' ') : 'none', opacity: opacity, filter: filter, origin: origin };
};
