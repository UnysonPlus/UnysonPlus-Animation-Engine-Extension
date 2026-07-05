<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Scroll Loop module: render.
 *
 * Stamps the loop data-attributes onto the section wrapper (sc_build_wrapper_attr),
 * forces a wrapper when the loop flag is a section's only non-default setting
 * (sc_needs_wrapper), and conditionally enqueues Lenis (+ Snap) + the initializer +
 * CSS in wp_footer when at least one loop section rendered.
 */

/**
 * Stamp the loop data-attributes onto the section wrapper. Runs at priority 26 —
 * after the Scroll Motion filter (25) — so a section can carry both a scroll
 * effect and the loop flag without either clobbering the other's attributes.
 */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	$l = ( isset( $atts['scroll_loop'] ) && is_array( $atts['scroll_loop'] ) ) ? $atts['scroll_loop'] : array();

	$mode = isset( $l['mode'] ) ? (string) $l['mode'] : 'off';
	if ( $mode !== 'loop' ) {
		return $attr;
	}

	$s = ( isset( $l['loop'] ) && is_array( $l['loop'] ) ) ? $l['loop'] : array();

	$snap = ! isset( $s['snap'] ) || (string) $s['snap'] === 'yes'; // default on
	$dur  = isset( $s['snap_duration'] ) && is_numeric( $s['snap_duration'] )
		? rtrim( rtrim( number_format( (float) $s['snap_duration'], 2, '.', '' ), '0' ), '.' )
		: '0.8';
	$mobile = ! isset( $s['run_on_mobile'] ) || (string) $s['run_on_mobile'] === 'yes'; // default on

	$attr['data-upw-loop']      = '1';
	$attr['data-upw-loop-snap'] = $snap ? '1' : '0';
	if ( $snap ) {
		$attr['data-upw-loop-snap-dur'] = esc_attr( $dur );
		sc_scroll_loop_snap_used( true );
	}
	if ( ! $mobile ) {
		$attr['data-upw-loop-mobile'] = '0';
	}

	sc_scroll_loop_flag( true );

	return $attr;
}, 26, 2 );

/**
 * Force a wrapper when a section's ONLY non-default setting is the loop flag, so
 * the data-upw-loop* attributes have somewhere to land (mirrors the Scroll Motion
 * needs-wrapper force). Kept here so the engine stays self-contained.
 */
add_filter( 'sc_needs_wrapper', function ( $needs, $atts ) {
	if ( $needs ) {
		return $needs;
	}
	$l = ( isset( $atts['scroll_loop'] ) && is_array( $atts['scroll_loop'] ) ) ? $atts['scroll_loop'] : array();
	return isset( $l['mode'] ) && (string) $l['mode'] === 'loop';
}, 10, 2 );


/**
 * Conditionally enqueue Lenis (+ Snap when used) + the initializer + CSS at the
 * start of wp_footer. Priority 6 so it runs AFTER Scroll Motion's GSAP enqueue
 * (priority 5): when GSAP is present on the page, the loop init is ordered after
 * it (for the ScrollTrigger bridge); when it isn't, the runtime drives Lenis with
 * its own rAF loop instead (feature-detected client-side).
 */
add_action( 'wp_footer', function () {
	if ( ! sc_scroll_loop_flag() ) {
		return;
	}

	$ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( ! $ext ) {
		return;
	}

	$ver      = $ext->manifest->get_version();
	$lenis_ver = '1.1.18';
	$base     = '/modules/scroll-loop';

	// Vendor files are already minified — reference them directly.
	wp_enqueue_script(
		'upw-lenis',
		$ext->get_declared_URI( $base . '/static/js/vendor/lenis/lenis.min.js' ),
		array(),
		$lenis_ver,
		true
	);
	$init_deps = array( 'upw-lenis' );

	// Lenis Snap is a separate build — load only when a loop section uses snapping.
	if ( sc_scroll_loop_snap_used() ) {
		wp_enqueue_script(
			'upw-lenis-snap',
			$ext->get_declared_URI( $base . '/static/js/vendor/lenis/lenis-snap.min.js' ),
			array( 'upw-lenis' ),
			$lenis_ver,
			true
		);
		$init_deps[] = 'upw-lenis-snap';
	}

	// If Scroll Motion enqueued its GSAP initializer on this page, order the loop
	// init after it so the Lenis↔ScrollTrigger bridge wires against a ready GSAP.
	if ( function_exists( 'wp_script_is' ) && wp_script_is( 'upw-gsap-init', 'enqueued' ) ) {
		$init_deps[] = 'upw-gsap-init';
	}

	wp_enqueue_script(
		'upw-scroll-loop',
		$ext->get_declared_URI( $base . '/static/js/upw-scroll-loop.js' ),
		$init_deps,
		$ver,
		true
	);

	// Honour the engine's global "respect reduce motion" policy (like Page Transitions).
	$cfg = array(
		'respectReducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
	);
	wp_add_inline_script( 'upw-scroll-loop', 'window.upwLoopCfg=' . wp_json_encode( $cfg ) . ';', 'before' );

	wp_enqueue_style(
		'upw-scroll-loop',
		$ext->get_declared_URI( $base . '/static/css/upw-scroll-loop.css' ),
		array(),
		$ver
	);
}, 6 );
