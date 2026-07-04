/* Sticky Card Stack — per-card style "stack" (on-demand partial). */
(window.upwStackFx = window.upwStackFx || {}).stack = function (i, cp, prevCp, ctx) {
	var I = ctx.intensity, center = ctx.center, parts = [], opacity = null, filter = '', origin = 'center top';
	parts.push('scale(' + (1 - I * 0.12 * cp).toFixed(4) + ')');
	return { transform: parts.length ? parts.join(' ') : 'none', opacity: opacity, filter: filter, origin: origin };
};
