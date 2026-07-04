/* Sticky Card Stack — per-card style "fan" (on-demand partial). */
(window.upwStackFx = window.upwStackFx || {}).fan = function (i, cp, prevCp, ctx) {
	var I = ctx.intensity, center = ctx.center, parts = [], opacity = null, filter = '', origin = 'center top';
	origin = 'center bottom';
	parts.push('rotate(' + ((i - center) * I * 10).toFixed(2) + 'deg)', 'scale(' + (1 - I * 0.06 * cp).toFixed(4) + ')');
	return { transform: parts.length ? parts.join(' ') : 'none', opacity: opacity, filter: filter, origin: origin };
};
