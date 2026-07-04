/* Sticky Card Stack — per-card style "fade" (on-demand partial). */
(window.upwStackFx = window.upwStackFx || {}).fade = function (i, cp, prevCp, ctx) {
	var I = ctx.intensity, center = ctx.center, parts = [], opacity = null, filter = '', origin = 'center top';
	opacity = 1 - Math.min(0.85, I) * cp;
	return { transform: parts.length ? parts.join(' ') : 'none', opacity: opacity, filter: filter, origin: origin };
};
