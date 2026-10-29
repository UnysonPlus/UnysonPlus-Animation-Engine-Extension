/* Parallax Scene runtime — self-contained (no Animation Engine dependency).
 *  - Entrance: reveal each layer's .fw-ps-enter (with its data-delay) when the scene scrolls into view.
 *  - Parallax: translate each .fw-ps-move by depth × scene-progress (scroll) and/or pointer.
 *  Honours prefers-reduced-motion (skips parallax; CSS freezes sway + entrance).
 */
(function () {
	'use strict';
	var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

	function init(scene) {
		if (scene._ps) { return; }
		scene._ps = true;
		var source    = scene.getAttribute('data-source') || 'scroll';
		var intensity = parseFloat(scene.getAttribute('data-intensity')) || 60;
		var moves     = [].slice.call(scene.querySelectorAll('.fw-ps-move'));
		var enters    = [].slice.call(scene.querySelectorAll('.fw-ps-enter'));

		// Enter / exit — scroll-driven: each layer reveals (.is-in) as the scene scrolls into view and
		// leaves (.is-out) as it scrolls back out, so the planes animate in AND out on scroll (a layer
		// with Exit = None keeps its .is-in and just parallaxes off). State is read off the SCENE box,
		// which is stable — the parallax transform lives on the layers, not the scene.
		// A PINNED scene (ps--sticky) is a tall runway holding a sticky inner viewport: it HOLDS full-screen
		// while the runway scrolls, then the viewport releases in the runway's last ~viewport-height. So the
		// exit must fire as it releases (runway bottom drops near one viewport), not when the whole runway has
		// scrolled off the top — otherwise the scene is long gone before it dissolves.
		var pinned = scene.classList.contains('ps--sticky');
		if (reduce) {
			enters.forEach(function (el) { el.classList.add('is-in'); });
		} else {
			var rTick = false;
			function reveal() {
				var r = scene.getBoundingClientRect(), vh = window.innerHeight || 1;
				var outAt = pinned ? vh * 0.92 : vh * 0.12;
				var state = (r.bottom <= outAt) ? 'out'        // released / scrolled above the top → exit
					: (r.top >= vh * 0.90) ? 'pre'             // still below the fold → pre-entrance
					: 'in';                                    // comfortably in view → settled (held, when pinned)
				for (var i = 0; i < enters.length; i++) {
					var el = enters[i];
					el.style.transitionDelay = (parseFloat(el.getAttribute('data-delay')) || 0) + 'ms';
					if (state === 'out' && el.getAttribute('data-exit') !== 'none') {
						el.classList.remove('is-in'); el.classList.add('is-out');
					} else if (state === 'pre') {
						el.classList.remove('is-in'); el.classList.remove('is-out');
					} else {
						el.classList.add('is-in'); el.classList.remove('is-out');
					}
				}
			}
			function rSchedule() { if (!rTick) { rTick = true; requestAnimationFrame(function () { reveal(); rTick = false; }); } }
			addEventListener('scroll', rSchedule, { passive: true });
			addEventListener('resize', rSchedule, { passive: true });
			reveal();
		}

		if (reduce || source === 'none' || !moves.length) { return; }

		// Parallax. For a pinned scene, read progress off the sticky viewport (stable at screen-top while
		// held → layers barely drift during the hold) rather than the tall runway (whose centre sweeps the
		// whole runway and would fling the foreground out of the clipped frame).
		var progEl = (pinned && scene.querySelector('.fw-ps-viewport')) || scene;
		var mx = 0, my = 0, ticking = false;
		function apply() {
			var r = progEl.getBoundingClientRect(), vh = window.innerHeight || 1;
			// progress: +1 when the scene sits below centre, -1 when above (0 when centred).
			var prog = ((r.top + r.height / 2) - vh / 2) / (vh / 2 + r.height / 2);
			for (var i = 0; i < moves.length; i++) {
				var depth = (parseFloat(moves[i].getAttribute('data-depth')) || 0) / 100;
				var tx = 0, ty = 0;
				if (source === 'scroll' || source === 'both') { ty += -prog * intensity * depth; }
				if (source === 'pointer' || source === 'both') { tx += mx * intensity * depth * 0.5; ty += my * intensity * depth * 0.5; }
				moves[i].style.transform = 'translate(' + tx.toFixed(2) + 'px,' + ty.toFixed(2) + 'px)';
			}
		}
		function schedule() { if (!ticking) { ticking = true; requestAnimationFrame(function () { apply(); ticking = false; }); } }
		addEventListener('scroll', schedule, { passive: true });
		addEventListener('resize', schedule, { passive: true });
		if (source === 'pointer' || source === 'both') {
			addEventListener('pointermove', function (e) {
				mx = (e.clientX / (window.innerWidth || 1) - 0.5) * 2;
				my = (e.clientY / (window.innerHeight || 1) - 0.5) * 2;
				schedule();
			}, { passive: true });
		}
		apply();
	}

	function boot() { [].slice.call(document.querySelectorAll('.fw-parallax-scene')).forEach(init); }
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
	window.fwParallaxSceneRescan = boot;

	/* Re-init hook for surfaces that insert this element's markup after page load (the
	   block editor's preview, the Elementor editor's re-renders): they call every
	   window.fwShortcodeInit entry. The scan skips elements it has already set up, so
	   repeated calls are safe. */
	window.fwShortcodeInit = window.fwShortcodeInit || [];
	window.fwShortcodeInit.push( function () { boot(); } );
})();
