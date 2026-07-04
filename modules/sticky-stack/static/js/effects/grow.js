/* Sticky Card Stack — per-card style "grow" (on-demand partial). */
(window.upwStackFx = window.upwStackFx || {}).grow = function (i, cp, prevCp, ctx) {
	var I = ctx.intensity, center = ctx.center, parts = [], opacity = null, filter = '', origin = 'center top';
	var base = 1 - I * 0.5;
	parts.push('scale(' + (base + (1 - base) * prevCp).toFixed(4) + ')');
	return { transform: parts.length ? parts.join(' ') : 'none', opacity: opacity, filter: filter, origin: origin };
};
