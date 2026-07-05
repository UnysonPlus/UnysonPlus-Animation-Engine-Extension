<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — 3D Flip Card module: render + enqueue.
 *
 * Emits the flip settings onto the element wrapper (sc_build_wrapper_attr), forces a wrapper when
 * flip is an element's only non-default setting (sc_needs_wrapper), and enqueues the runtime in
 * wp_footer — only on pages that actually used it.
 *
 * NOTE: uses UPW_FLIP_CARD_DIR (defined in flip-card.php) — NOT __DIR__ — for filemtime
 * cache-busting, because this file lives in includes/ but the static assets are at the module root.
 */

/* 2) Emit the flip settings onto the element wrapper. */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	if ( ! upw_flip_card_enabled() ) {
		return $attr;
	}
	$f    = ( isset( $atts['flip_card'] ) && is_array( $atts['flip_card'] ) ) ? $atts['flip_card'] : array();
	$mode = isset( $f['mode'] ) ? (string) $f['mode'] : 'off';
	if ( $mode === 'off' || $mode === '' ) {
		return $attr;
	}

	// Legacy tolerance: the old on/off value maps to the classic "flip" style.
	$style = $mode;
	$src   = $mode;
	if ( $mode === 'on' ) {
		$style = 'flip';
		$src   = 'on';
	}
	$styles = upw_flip_card_styles();
	if ( ! isset( $styles[ $style ] ) ) {
		return $attr;
	}
	$o = ( isset( $f[ $src ] ) && is_array( $f[ $src ] ) ) ? $f[ $src ] : array();

	$dir     = ( isset( $o['direction'] ) && $o['direction'] === 'v' ) ? 'v' : 'h';
	$trigger = isset( $o['trigger'] ) ? (string) $o['trigger'] : 'hover';
	if ( ! in_array( $trigger, array( 'hover', 'click', 'scroll', 'auto' ), true ) ) {
		$trigger = 'hover';
	}
	$align = isset( $o['back_align'] ) && in_array( $o['back_align'], array( 'top', 'center', 'bottom' ), true ) ? $o['back_align'] : 'center';

	$cls = isset( $attr['class'] ) ? trim( (string) $attr['class'] ) : '';
	$cls = trim( $cls
		. ' sc-flip'
		. ' sc-flip--' . $style
		. ' sc-flip--' . $dir
		. ' sc-flip--balign-' . $align
		. ' sc-flip-' . $trigger );
	$attr['class'] = esc_attr( $cls );

	$mh    = isset( $o['min_height'] ) ? (int) $o['min_height'] : 260;
	$dur   = isset( $o['duration'] ) ? (float) $o['duration'] : 0.6;
	$persp = isset( $o['perspective'] ) ? (int) $o['perspective'] : 1400;
	$rad   = isset( $o['radius'] ) ? (int) $o['radius'] : 0;
	$ease_map = array(
		'smooth' => 'cubic-bezier(0.4, 0.2, 0.2, 1)',
		'spring' => 'cubic-bezier(0.34, 1.56, 0.64, 1)',
		'out'    => 'cubic-bezier(0.16, 1, 0.3, 1)',
		'linear' => 'linear',
	);
	$ease = ( isset( $o['easing'] ) && isset( $ease_map[ $o['easing'] ] ) ) ? $ease_map[ $o['easing'] ] : $ease_map['smooth'];

	$fmt   = function ( $n ) { return rtrim( rtrim( number_format( (float) $n, 2, '.', '' ), '0' ), '.' ); };
	$decls = array(
		'min-height: ' . max( 40, $mh ) . 'px',
		'--flip-dur: ' . $fmt( $dur ) . 's',
		'--flip-persp: ' . max( 200, $persp ) . 'px',
		'--flip-ease: ' . $ease,
		'--flip-radius: ' . max( 0, $rad ) . 'px',
	);
	$existing_style = isset( $attr['style'] ) ? trim( (string) $attr['style'] ) : '';
	$css            = implode( '; ', $decls ) . ';';
	$attr['style']  = esc_attr( $existing_style === '' ? $css : rtrim( $existing_style, '; ' ) . '; ' . $css );

	$bg  = upw_flip_resolve_color( isset( $o['back_bg'] ) ? $o['back_bg'] : '' );
	$col = upw_flip_resolve_color( isset( $o['back_color'] ) ? $o['back_color'] : '' );
	$attr['data-flip-bg']    = esc_attr( $bg !== '' ? $bg : '#2f74e6' );
	$attr['data-flip-color'] = esc_attr( $col !== '' ? $col : '#ffffff' );

	$heading = isset( $o['back_heading'] ) ? (string) $o['back_heading'] : '';
	$text    = isset( $o['back_text'] ) ? (string) $o['back_text'] : '';
	if ( $heading !== '' ) { $attr['data-flip-heading'] = esc_attr( $heading ); }
	if ( $text !== '' )    { $attr['data-flip-text'] = esc_attr( $text ); }

	// Back background image.
	$img     = isset( $o['back_image'] ) ? $o['back_image'] : '';
	$img_url = is_array( $img ) ? ( isset( $img['url'] ) ? $img['url'] : '' ) : (string) $img;
	if ( $img_url !== '' ) { $attr['data-flip-image'] = esc_url( $img_url ); }

	// Back button.
	$btn_text = isset( $o['back_btn_text'] ) ? (string) $o['back_btn_text'] : '';
	$btn_url  = isset( $o['back_btn_url'] ) ? (string) $o['back_btn_url'] : '';
	if ( $btn_text !== '' ) {
		$attr['data-flip-btn'] = esc_attr( $btn_text );
		if ( $btn_url !== '' ) { $attr['data-flip-btn-url'] = esc_url( $btn_url ); }
	}

	if ( $trigger === 'auto' ) {
		$iv = isset( $o['auto_interval'] ) ? (float) $o['auto_interval'] : 3;
		$attr['data-flip-interval'] = esc_attr( $fmt( max( 0.5, $iv ) ) );
	}

	upw_flip_card_flag( true );
	return $attr;
}, 22, 2 );

/* 2b) Force a wrapper when an element's ONLY non-default setting is a flip card. */
add_filter( 'sc_needs_wrapper', function ( $needs, $atts ) {
	if ( $needs || ! upw_flip_card_enabled() ) {
		return $needs;
	}
	$f = ( isset( $atts['flip_card'] ) && is_array( $atts['flip_card'] ) ) ? $atts['flip_card'] : array();
	return ( isset( $f['mode'] ) && $f['mode'] !== 'off' && $f['mode'] !== '' );
}, 10, 2 );

/* 3) Enqueue the runtime — only on pages that actually used it. */
add_action( 'wp_footer', function () {
	if ( ! upw_flip_card_flag() ) {
		return;
	}
	$ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( ! $ext ) {
		return;
	}
	$base = $ext->get_declared_URI( '/modules/flip-card' );
	$ver  = $ext->manifest->get_version();
	$dir  = UPW_FLIP_CARD_DIR;
	$jsv  = file_exists( "$dir/static/js/flip-card.js" )  ? $ver . '.' . filemtime( "$dir/static/js/flip-card.js" )  : $ver;
	$cssv = file_exists( "$dir/static/css/flip-card.css" ) ? $ver . '.' . filemtime( "$dir/static/css/flip-card.css" ) : $ver;

	wp_enqueue_style( 'upw-flip-card', $base . '/static/css/flip-card.css', array(), $cssv );
	wp_enqueue_script( 'upw-flip-card', $base . '/static/js/flip-card.js', array(), $jsv, true );
}, 5 );
