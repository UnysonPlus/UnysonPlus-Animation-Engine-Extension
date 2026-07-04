/**
 * Animation Engine — shared frame scheduler (window.upwAnimRaf).
 *
 * One requestAnimationFrame loop drives every subscribed animation across all engine modules,
 * instead of each module (cursor, backgrounds, physics, …) running its own. The whole loop
 * PAUSES while the tab is hidden and resumes on return — so background tabs cost nothing — and
 * a callback that returns `false` is auto-removed (one-shot / self-terminating animations).
 *
 * API:  var raf = window.upwAnimRaf;  raf.add(fn);  raf.remove(fn);
 *   fn receives the rAF timestamp; return false from fn to unsubscribe it.
 * Self-defining + idempotent: loaded once as a shared dependency; safe to include twice.
 */
(function () {
	'use strict';
	if (window.upwAnimRaf) { return; }

	var fns = [], id = 0;

	function tick(t) {
		id = 0;
		// iterate backwards so a callback can remove itself (return false) mid-loop
		for (var i = fns.length - 1; i >= 0; i--) {
			try { if (fns[i](t) === false) { fns.splice(i, 1); } }
			catch (e) { fns.splice(i, 1); } // a broken effect must not kill the whole loop
		}
		if (fns.length && !document.hidden) { id = requestAnimationFrame(tick); }
	}

	function start() {
		if (!id && fns.length && !document.hidden) { id = requestAnimationFrame(tick); }
	}

	document.addEventListener('visibilitychange', function () { if (!document.hidden) { start(); } });

	window.upwAnimRaf = {
		add: function (fn) { if (typeof fn === 'function' && fns.indexOf(fn) < 0) { fns.push(fn); start(); } return fn; },
		remove: function (fn) { var i = fns.indexOf(fn); if (i >= 0) { fns.splice(i, 1); } }
	};
})();
