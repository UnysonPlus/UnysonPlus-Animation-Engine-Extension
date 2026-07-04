/* Sticky Card Stack — per-card style "tilt" (on-demand partial). */
(window.upwStackFx = window.upwStackFx || {}).tilt = function (i, cp, prevCp, ctx) {
	var I = ctx.intensity, center = ctx.center, parts = [], opacity = null, filter = '', origin = 'center top';
	parts.push('perspective(1200px)', 'rotateX(' + (-I * 55 * cp).toFixed(2) + 'deg)', 'scale(' + (1 - 0.05 * cp).toFixed(4) + ')');
	return { transform: parts.length ? parts.join(' ') : 'none', opacity: opacity, filter: filter, origin: origin };
};
