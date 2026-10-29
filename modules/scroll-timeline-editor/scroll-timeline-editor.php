<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Scroll Timeline Editor module (author tool).
 *
 * A front-end, admin-only VISUAL TIMELINE of every GSAP ScrollTrigger on the page. Where the Scroll
 * Ruler is a thin vertical read-out of raw scroll pixels, this is the editor view: a horizontal,
 * video-editor-style timeline docked at the bottom of the screen with one TRACK per scroll animation,
 * each drawn as a block on a readable 0–100 % scroll axis, a draggable PLAYHEAD that scrubs the real
 * page live (so scrub-linked animations animate as you drag), and element highlighting on hover.
 *
 * This is Phase 1 of the "visual Scroll Timeline Editor" (3D-Motion roadmap item 1b) — it reads and
 * scrubs. Drag-to-retime + persistence back into each element's Scroll Motion options is the next
 * phase. Front end only, logged-in admins only, and the whole panel stays hidden until toggled, so a
 * visitor never sees or loads anything that affects the page.
 */

// Persistence layer for drag-to-retime (public override print + admin-only save endpoint). Required
// always: the override print must run for every visitor, not only when the admin editor enqueues.
require_once __DIR__ . '/timing-overrides.php';

add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() || ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( ! $ext ) {
		return;
	}
	$base = $ext->get_declared_URI( '/modules/scroll-timeline-editor' );
	$ver  = $ext->manifest->get_version();
	$dir  = __DIR__;
	$fver = function ( $rel ) use ( $dir, $ver ) { $abs = $dir . $rel; return file_exists( $abs ) ? $ver . '.' . filemtime( $abs ) : $ver; };

	wp_enqueue_style( 'upw-scroll-timeline', $base . '/static/css/upw-scroll-timeline.css', array(), $fver( '/static/css/upw-scroll-timeline.css' ) );
	wp_enqueue_script( 'upw-scroll-timeline', $base . '/static/js/upw-scroll-timeline.js', array(), $fver( '/static/js/upw-scroll-timeline.js' ), true );

	// Drag-to-retime needs the save endpoint, a nonce and the current page id (edit-capable only).
	$pid = is_singular() ? get_queried_object_id() : 0;
	wp_localize_script( 'upw-scroll-timeline', 'upwStlData', array(
		'ajaxurl'  => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'upw_timing' ),
		'postId'   => $pid,
		'canEdit'  => ( $pid && current_user_can( 'edit_post', $pid ) ) ? 1 : 0,
	) );
}, 31 );
