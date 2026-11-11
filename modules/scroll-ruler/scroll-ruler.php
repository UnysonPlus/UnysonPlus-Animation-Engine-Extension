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

/**
 * Admin-bar entry for the Scroll Ruler. The toggle used to be a fixed floating button that collided
 * with site-side floating chrome (chat FABs, back-to-top), blocking clicks; it now lives in a shared
 * "Scroll Tools" admin-bar menu instead. The parent node is added idempotently (the Timeline module
 * adds the same node), so whichever author tools are active contribute their own item under it. The
 * ruler JS wires this item's click to its toggle, and only falls back to the floating button when the
 * admin bar is hidden.
 */
add_action( 'admin_bar_menu', function ( $bar ) {
	if ( is_admin() || ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$bar->add_node( array(
		'id'    => 'upw-scroll-tools',
		'title' => '<span class="ab-icon dashicons dashicons-chart-bar" style="top:2px;font:400 20px/1 dashicons;"></span><span class="ab-label">Scroll Tools</span>',
		'href'  => false,
		'meta'  => array( 'title' => __( 'Unyson+ scroll author tools', 'fw' ) ),
	) );
	$bar->add_node( array(
		'parent' => 'upw-scroll-tools',
		'id'     => 'upw-scroll-tools-ruler',
		'title'  => __( 'Scroll Ruler', 'fw' ),
		'href'   => '#',
		'meta'   => array( 'class' => 'upw-scroll-tools-item', 'title' => __( 'Toggle the Scroll Ruler (Shift+R)', 'fw' ) ),
	) );
}, 80 );
