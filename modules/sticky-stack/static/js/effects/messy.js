/* Sticky Card Stack — per-card style "messy" (on-demand partial). */
(window.upwStackFx = window.upwStackFx || {}).messy = function (i, cp, prevCp, ctx) {
	var I = ctx.intensity, center = ctx.center, parts = [], opacity = null, filter = '', origin = 'center top';
	parts.push('rotate(' + (((i % 2) ? 1 : -1) * I * 7 * (1 + (i % 3) * 0.35)).toFixed(2) + 'deg)', 'scale(' + (1 - I * 0.05 * cp).toFixed(4) + ')');
	return { transform: parts.length ? parts.join(' ') : 'none', opacity: opacity, filter: filter, origin: origin };
};
