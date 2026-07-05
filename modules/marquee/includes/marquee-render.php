<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Marquee module: runtime + enqueue.
 *
 * Emits the marquee settings onto the element wrapper (sc_build_wrapper_attr), forces a wrapper
 * when a marquee is the only non-default setting (sc_needs_wrapper), and enqueues the runtime in
 * wp_footer only on pages that actually used a marquee. Depends on the helpers.
 *
 * NOTE: uses UPW_MARQUEE_DIR (defined in marquee.php) — NOT __DIR__ — for filemtime cache-busting,
 * because this file lives in includes/ but the static assets are at the module root.
 */

/* ------------------------------------------------------------------ *
 * 2) Emit the marquee settings onto the element wrapper.
 * ------------------------------------------------------------------ */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	if ( ! upw_marquee_enabled() ) {
		return $attr;
	}
	$mq   = ( isset( $atts['marquee'] ) && is_array( $atts['marquee'] ) ) ? $atts['marquee'] : array();
	$mode = isset( $mq['mode'] ) ? (string) $mq['mode'] : 'none';
	if ( ! in_array( $mode, array( 'left', 'right', 'up', 'down' ), true ) ) {
		return $attr;
	}
	$o = ( isset( $mq[ $mode ] ) && is_array( $mq[ $mode ] ) ) ? $mq[ $mode ] : array();

	$cls           = isset( $attr['class'] ) ? trim( (string) $attr['class'] ) : '';
	$attr['class'] = esc_attr( trim( $cls . ' sc-marquee sc-marquee--' . $mode ) );

	$speed = isset( $o['speed'] ) && in_array( $o['speed'], array( 'slow', 'normal', 'fast' ), true ) ? $o['speed'] : 'normal';
	$attr['data-mq-speed'] = esc_attr( $speed );
	$attr['data-mq-gap']   = esc_attr( (string) ( isset( $o['gap'] ) ? (float) $o['gap'] : 40 ) );

	$sep = isset( $o['separator'] ) ? trim( (string) $o['separator'] ) : '';
	if ( $sep !== '' ) {
		$attr['data-mq-sep'] = esc_attr( $sep );
	}
	if ( isset( $o['pause_on_hover'] ) && $o['pause_on_hover'] === 'no' ) {
		$attr['data-mq-pause'] = '0';
	}
	if ( isset( $o['edge_fade'] ) && $o['edge_fade'] === 'yes' ) {
		$attr['data-mq-fade'] = '1';
	}

	// Custom speed (px/s) overrides the preset when > 0.
	$cs = isset( $o['custom_speed'] ) ? (int) $o['custom_speed'] : 0;
	if ( $cs > 0 ) {
		$attr['data-mq-cspeed'] = esc_attr( (string) $cs );
	}

	if ( isset( $o['scroll_reactive'] ) && $o['scroll_reactive'] === 'yes' ) {
		$attr['data-mq-scrollreact'] = '1';
	}
	if ( isset( $o['draggable'] ) && $o['draggable'] === 'yes' ) {
		$attr['data-mq-drag'] = '1';
	}

	// Warp / distortion — stamp only the non-zero values.
	foreach ( array( 'skew_h' => 'skewh', 'skew_v' => 'skewv', 'tilt' => 'tilt', 'bend' => 'bend', 'curve' => 'curve', 'wave' => 'wave' ) as $key => $suffix ) {
		$v = isset( $o[ $key ] ) ? (float) $o[ $key ] : 0;
		if ( $v != 0.0 ) {
			$attr[ 'data-mq-' . $suffix ] = esc_attr( rtrim( rtrim( number_format( $v, 2, '.', '' ), '0' ), '.' ) );
		}
	}

	// Text fill style.
	$ts = isset( $o['text_style'] ) ? (string) $o['text_style'] : 'normal';
	if ( $ts === 'outline' || $ts === 'gradient' ) {
		$attr['class'] = esc_attr( trim( (string) $attr['class'] . ' sc-mq--' . $ts ) );
	}

	upw_marquee_flag( true );
	return $attr;
}, 23, 2 );

/* ------------------------------------------------------------------ *
 * 2b) Force a wrapper when an element's ONLY non-default setting is a marquee.
 * ------------------------------------------------------------------ */
add_filter( 'sc_needs_wrapper', function ( $needs, $atts ) {
	if ( $needs || ! upw_marquee_enabled() ) {
		return $needs;
	}
	$mq   = ( isset( $atts['marquee'] ) && is_array( $atts['marquee'] ) ) ? $atts['marquee'] : array();
	$mode = isset( $mq['mode'] ) ? (string) $mq['mode'] : 'none';
	return in_array( $mode, array( 'left', 'right', 'up', 'down' ), true );
}, 10, 2 );

/* ------------------------------------------------------------------ *
 * 3) Enqueue the runtime — only on pages that actually used a marquee.
 * ------------------------------------------------------------------ */
add_action( 'wp_footer', function () {
	if ( ! upw_marquee_flag() ) {
		return;
	}
	$ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( ! $ext ) {
		return;
	}
	$base = $ext->get_declared_URI( '/modules/marquee' );
	$ver  = $ext->manifest->get_version();
	$dir  = UPW_MARQUEE_DIR;
	$jsv  = file_exists( "$dir/static/js/marquee.js" )  ? $ver . '.' . filemtime( "$dir/static/js/marquee.js" )  : $ver;
	$cssv = file_exists( "$dir/static/css/marquee.css" ) ? $ver . '.' . filemtime( "$dir/static/css/marquee.css" ) : $ver;

	wp_enqueue_style( 'upw-marquee', $base . '/static/css/marquee.css', array(), $cssv );
	wp_enqueue_script( 'upw-marquee', $base . '/static/js/marquee.js', array(), $jsv, true );

	$cfg = array(
		'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
		'disableMobile' => ( function_exists( 'upw_anim_engine_setting' ) && upw_anim_engine_setting( 'disable_on_mobile', 'no' ) === 'yes' ),
	);
	wp_add_inline_script( 'upw-marquee', 'window.upwMarqueeCfg=' . wp_json_encode( $cfg ) . ';', 'before' );
}, 5 );
