/* Sticky Card Stack — per-card style "blur" (on-demand partial). */
(window.upwStackFx = window.upwStackFx || {}).blur = function (i, cp, prevCp, ctx) {
	var I = ctx.intensity, center = ctx.center, parts = [], opacity = null, filter = '', origin = 'center top';
	filter = 'blur(' + (I * 10 * cp).toFixed(2) + 'px)';
	opacity = 1 - 0.15 * cp;
	return { transform: parts.length ? parts.join(' ') : 'none', opacity: opacity, filter: filter, origin: origin };
};
