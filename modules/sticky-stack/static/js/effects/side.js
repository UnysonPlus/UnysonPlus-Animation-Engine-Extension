/* Sticky Card Stack — per-card style "side" (on-demand partial). */
(window.upwStackFx = window.upwStackFx || {}).side = function (i, cp, prevCp, ctx) {
	var I = ctx.intensity, center = ctx.center, parts = [], opacity = null, filter = '', origin = 'center top';
	parts.push('translateX(' + (i * I * 42).toFixed(1) + 'px)', 'scale(' + (1 - I * 0.05 * cp).toFixed(4) + ')');
	return { transform: parts.length ? parts.join(' ') : 'none', opacity: opacity, filter: filter, origin: origin };
};
