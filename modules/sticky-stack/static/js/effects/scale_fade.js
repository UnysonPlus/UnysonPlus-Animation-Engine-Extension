/* Sticky Card Stack — per-card style "scale_fade" (on-demand partial). */
(window.upwStackFx = window.upwStackFx || {}).scale_fade = function (i, cp, prevCp, ctx) {
	var I = ctx.intensity, center = ctx.center, parts = [], opacity = null, filter = '', origin = 'center top';
	parts.push('scale(' + (1 - I * 0.1 * cp).toFixed(4) + ')');
	opacity = 1 - Math.min(0.7, I * 0.8) * cp;
	return { transform: parts.length ? parts.join(' ') : 'none', opacity: opacity, filter: filter, origin: origin };
};
