<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Parallax module: runtime.
 *
 * Emits the role + settings onto the element wrapper (`sc_build_wrapper_attr`), forces a wrapper
 * when the only non-default setting is a parallax role (`sc_needs_wrapper`), and enqueues the
 * self-contained runtime only on pages that actually used parallax (`wp_footer`). Depends on the
 * helpers.
 *
 * NOTE: uses UPW_PARALLAX_DIR (defined in parallax.php) — NOT __DIR__ — for filemtime
 * cache-busting, because this file lives in includes/ but the static assets are at the module
 * root.
 */

/* ------------------------------------------------------------------ *
 * 2) Emit the role + settings onto the element wrapper.
 * ------------------------------------------------------------------ */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	if ( ! upw_parallax_enabled() ) {
		return $attr;
	}
	$pl   = ( isset( $atts['parallax'] ) && is_array( $atts['parallax'] ) ) ? $atts['parallax'] : array();
	$role = isset( $pl['role'] ) ? (string) $pl['role'] : 'none';
	if ( $role !== 'scene' && $role !== 'layer' ) {
		return $attr;
	}
	$o = ( isset( $pl[ $role ] ) && is_array( $pl[ $role ] ) ) ? $pl[ $role ] : array();

	$cls = isset( $attr['class'] ) ? trim( (string) $attr['class'] ) : '';

	if ( $role === 'scene' ) {
		$attr['class']            = esc_attr( trim( $cls . ' sc-parallax-scene' ) );
		$attr['data-pl-scene']    = esc_attr( isset( $o['source'] ) && in_array( $o['source'], array( 'mouse', 'scroll', 'both' ), true ) ? $o['source'] : 'mouse' );
		$attr['data-pl-intensity'] = esc_attr( (string) ( isset( $o['intensity'] ) ? (float) $o['intensity'] : 40 ) );
		$attr['data-pl-smooth']    = esc_attr( (string) ( isset( $o['smoothing'] ) ? (float) $o['smoothing'] : 50 ) );
	} else { // layer
		$attr['class']         = esc_attr( trim( $cls . ' sc-parallax-layer' ) );
		$attr['data-pl-depth'] = esc_attr( (string) ( isset( $o['depth'] ) ? (float) $o['depth'] : 30 ) );
		$attr['data-pl-axis']  = esc_attr( isset( $o['axis'] ) && in_array( $o['axis'], array( 'both', 'x', 'y' ), true ) ? $o['axis'] : 'both' );
		$attr['data-pl-dir']   = esc_attr( ( isset( $o['direction'] ) && $o['direction'] === 'against' ) ? 'against' : 'with' );
		if ( isset( $o['scale_far'] ) && $o['scale_far'] === 'yes' ) { $attr['data-pl-scale'] = '1'; }
		if ( isset( $o['blur_far'] ) && $o['blur_far'] === 'yes' ) { $attr['data-pl-blur'] = '1'; }
	}

	upw_parallax_flag( true );
	return $attr;
}, 21, 2 );

/* ------------------------------------------------------------------ *
 * 2b) Force a wrapper when an element's ONLY non-default setting is a parallax role.
 * ------------------------------------------------------------------ */
add_filter( 'sc_needs_wrapper', function ( $needs, $atts ) {
	if ( $needs || ! upw_parallax_enabled() ) {
		return $needs;
	}
	$pl   = ( isset( $atts['parallax'] ) && is_array( $atts['parallax'] ) ) ? $atts['parallax'] : array();
	$role = isset( $pl['role'] ) ? (string) $pl['role'] : 'none';
	return ( $role === 'scene' || $role === 'layer' );
}, 10, 2 );

/* ------------------------------------------------------------------ *
 * 3) Enqueue the runtime — only on pages that actually used parallax.
 * ------------------------------------------------------------------ */
add_action( 'wp_footer', function () {
	if ( ! upw_parallax_flag() ) {
		return;
	}
	$ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( ! $ext ) {
		return;
	}
	$base = $ext->get_declared_URI( '/modules/parallax' );
	$ver  = $ext->manifest->get_version();
	$dir  = UPW_PARALLAX_DIR;
	$jsv  = file_exists( "$dir/static/js/parallax.js" )  ? $ver . '.' . filemtime( "$dir/static/js/parallax.js" )  : $ver;
	$cssv = file_exists( "$dir/static/css/parallax.css" ) ? $ver . '.' . filemtime( "$dir/static/css/parallax.css" ) : $ver;

	wp_enqueue_style( 'upw-parallax', $base . '/static/css/parallax.css', array(), $cssv );
	wp_enqueue_script( 'upw-parallax', $base . '/static/js/parallax.js', array(), $jsv, true );

	$cfg = array(
		'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
		'disableMobile' => ( function_exists( 'upw_anim_engine_setting' ) && upw_anim_engine_setting( 'disable_on_mobile', 'no' ) === 'yes' ),
	);
	wp_add_inline_script( 'upw-parallax', 'window.upwParallaxCfg=' . wp_json_encode( $cfg ) . ';', 'before' );
}, 5 );
