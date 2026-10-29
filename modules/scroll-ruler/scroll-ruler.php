<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Scroll Ruler module (author tool).
 *
 * A front-end, admin-only overlay that lists every GSAP ScrollTrigger on the page — each trigger's
 * element, start / end scroll positions, pin and scrub flags — and draws a vertical ruler that maps
 * all of their [start, end] ranges onto the document's scroll height, with a live playhead. It's the
 * page-level view of "what pins / scrubs where" an author needs when composing scroll choreography
 * (galleries, Scroll Motion, Motion Sequence). Read-only. Loads only for logged-in admins on the
 * front end, and the overlay stays hidden until toggled, so it never affects visitors.
 */

add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() || ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( ! $ext ) {
		return;
	}
	$base = $ext->get_declared_URI( '/modules/scroll-ruler' );
	$ver  = $ext->manifest->get_version();
	$dir  = __DIR__;
	$fver = function ( $rel ) use ( $dir, $ver ) { $abs = $dir . $rel; return file_exists( $abs ) ? $ver . '.' . filemtime( $abs ) : $ver; };

	wp_enqueue_style( 'upw-scroll-ruler', $base . '/static/css/upw-scroll-ruler.css', array(), $fver( '/static/css/upw-scroll-ruler.css' ) );
	wp_enqueue_script( 'upw-scroll-ruler', $base . '/static/js/upw-scroll-ruler.js', array(), $fver( '/static/js/upw-scroll-ruler.js' ), true );
}, 30 );
