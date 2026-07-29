/**
 * UnysonPlus Animation Engine — Viewport Units.
 *
 * Publishes stable viewport-unit CSS custom properties on <html>:
 *   --vh = window.innerHeight / 100 (px)
 *   --vw = window.innerWidth  / 100 (px)
 *
 * Recomputed on resize + orientationchange only (NOT on scroll), so mobile
 * viewport units stay put when the address bar shows/hides. Use in CSS as
 * calc(var(--vh) * 100) for a jump-free 100vh, or var(--vw) for fluid sizing.
 *
 * Dependency-free. Debounced with requestAnimationFrame.
 */
(function () {
	'use strict';

	var root = document.documentElement;
	if ( ! root ) {
		return;
	}

	var raf = 0;

	function apply() {
		raf = 0;
		root.style.setProperty( '--vh', ( window.innerHeight / 100 ) + 'px' );
		root.style.setProperty( '--vw', ( window.innerWidth / 100 ) + 'px' );
	}

	function schedule() {
		if ( raf ) {
			return;
		}
		raf = window.requestAnimationFrame ? window.requestAnimationFrame( apply ) : ( apply(), 0 );
	}

	apply();
	window.addEventListener( 'resize', schedule, { passive: true } );
	window.addEventListener( 'orientationchange', schedule, { passive: true } );
})();
