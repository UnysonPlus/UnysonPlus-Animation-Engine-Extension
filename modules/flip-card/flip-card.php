<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — 3D Flip Card module.
 *
 * Flips any element front-to-back in 3D (on hover or click) to reveal a back face with your own
 * heading + text and colours. Per-element (attaches from the Animations tab, like Marquee). At
 * runtime the element's existing content becomes the FRONT face and a BACK face is built from the
 * options. Pure CSS 3D transforms, no library. Assets load only on pages that use it. Global
 * on/off: Theme Settings → Animations → Effects.
 *
 * Saved value shape (multi-picker, picker id `mode`):
 *   [ 'mode' => 'off'|'on', 'on' => [ 'trigger', 'direction', 'min_height', 'duration',
 *                                     'back_heading', 'back_text', 'back_bg', 'back_color' ] ]
 */

if ( ! function_exists( 'upw_flip_card_enabled' ) ) :
	function upw_flip_card_enabled() {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return true;
		}
		$v = fw_get_db_settings_option( 'animation_flip_card', array() );
		$e = ( is_array( $v ) && isset( $v['enable'] ) ) ? $v['enable'] : 'yes';
		return $e !== 'no' && $e !== false;
	}
endif;

if ( ! function_exists( 'upw_flip_card_flag' ) ) :
	function upw_flip_card_flag( $set = false ) {
		static $used = false;
		if ( $set ) {
			$used = true;
		}
		return $used;
	}
endif;

/** Resolve a color value (compact-color array or legacy string) to a CSS color. */
if ( ! function_exists( 'upw_flip_resolve_color' ) ) :
	function upw_flip_resolve_color( $val ) {
		if ( is_array( $val ) ) {
			if ( ! empty( $val['predefined'] ) ) {
				$slug = preg_replace( '/^(bg|text)-/', '', (string) $val['predefined'] );
				return 'var(--color-' . preg_replace( '/[^a-z0-9\-]/', '', strtolower( $slug ) ) . ')';
			}
			return isset( $val['custom'] ) ? (string) $val['custom'] : '';
		}
		return (string) $val;
	}
endif;

/* 1) The per-element "Flip Card" control, appended to the Animations tab. */
add_filter( 'sc_animation_fields', function ( $fields ) {
	if ( ! is_array( $fields ) ) {
		return $fields;
	}

	$bg_field = function_exists( 'sc_color_field_compact' )
		? sc_color_field_compact( array( 'label' => __( 'Back background', 'fw' ), 'kind' => 'bg' ) )
		: array( 'type' => 'color-picker', 'label' => __( 'Back background', 'fw' ), 'value' => '#2f74e6' );
	$col_field = function_exists( 'sc_color_field_compact' )
		? sc_color_field_compact( array( 'label' => __( 'Back text color', 'fw' ), 'kind' => 'text' ) )
		: array( 'type' => 'color-picker', 'label' => __( 'Back text color', 'fw' ), 'value' => '#ffffff' );

	$opts = array(
		'trigger' => array(
			'type'    => 'select',
			'label'   => __( 'Flip on', 'fw' ),
			'value'   => 'hover',
			'choices' => array( 'hover' => __( 'Hover', 'fw' ), 'click' => __( 'Click / tap', 'fw' ) ),
		),
		'direction' => array(
			'type'    => 'select',
			'label'   => __( 'Direction', 'fw' ),
			'value'   => 'h',
			'choices' => array( 'h' => __( 'Horizontal (Y axis)', 'fw' ), 'v' => __( 'Vertical (X axis)', 'fw' ) ),
		),
		'min_height' => array( 'type' => 'slider', 'label' => __( 'Card height (px)', 'fw' ), 'desc' => __( 'Both faces share this height.', 'fw' ), 'value' => 260, 'properties' => array( 'min' => 80, 'max' => 600, 'step' => 10 ) ),
		'duration'   => array( 'type' => 'slider', 'label' => __( 'Flip speed (s)', 'fw' ), 'value' => 0.6, 'properties' => array( 'min' => 0.2, 'max' => 1.5, 'step' => 0.05 ) ),
		'back_heading' => array( 'type' => 'text', 'label' => __( 'Back heading', 'fw' ), 'value' => '' ),
		'back_text'    => array( 'type' => 'textarea', 'label' => __( 'Back text', 'fw' ), 'value' => '' ),
		'back_bg'      => $bg_field,
		'back_color'   => $col_field,
	);

	$ext  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	$base = $ext ? $ext->get_declared_URI( '/modules/flip-card/static/img' ) : '';
	$tile = function ( $file, $label ) use ( $base ) {
		return array(
			'small' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 66 ),
			'large' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 132 ),
			'label' => $label,
		);
	};

	$fields['flip_card'] = array(
		'type'         => 'multi-picker',
		'popover'      => true,
		'label'        => __( '3D Flip Card', 'fw' ),
		'desc'         => __( 'Flip this element in 3D to reveal a back face with your own heading, text and colours — on hover or click.', 'fw' ),
		'help'         => __( '3D Flip Card (Animation Engine): the element\'s content becomes the front face and a back face is built from the options below. Flips on hover or click (click is keyboard-accessible). Pure CSS 3D transforms, no library. Loads only on pages that use it.', 'fw' ) . ( function_exists( 'upw_perf_note' ) ? ' ' . upw_perf_note() : '' ),
		'show_borders' => false,
		'value'        => array( 'mode' => 'off' ),
		'anim_meta'    => array( 'category' => __( 'Pointer', 'fw' ) ),
		'picker'       => array(
			'mode' => array(
				'type'    => 'image-picker',
				'label'   => false,
				'desc'    => __( 'Hover a tile to preview it larger.', 'fw' ),
				'value'   => 'off',
				'choices' => array(
					'off' => $tile( 'off', __( 'Off', 'fw' ) ),
					'on'  => $tile( 'on',  __( 'Flip Card', 'fw' ) ),
				),
			),
		),
		'choices' => array(
			'on' => array( 'group_flip_card' => array( 'type' => 'group', 'options' => $opts ) ),
		),
	);

	return $fields;
} );

/* 2) Emit the flip settings onto the element wrapper. */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	if ( ! upw_flip_card_enabled() ) {
		return $attr;
	}
	$f    = ( isset( $atts['flip_card'] ) && is_array( $atts['flip_card'] ) ) ? $atts['flip_card'] : array();
	$mode = isset( $f['mode'] ) ? (string) $f['mode'] : 'off';
	if ( $mode !== 'on' ) {
		return $attr;
	}
	$o = ( isset( $f['on'] ) && is_array( $f['on'] ) ) ? $f['on'] : array();

	$dir     = ( isset( $o['direction'] ) && $o['direction'] === 'v' ) ? 'v' : 'h';
	$trigger = ( isset( $o['trigger'] ) && $o['trigger'] === 'click' ) ? 'click' : 'hover';

	$cls           = isset( $attr['class'] ) ? trim( (string) $attr['class'] ) : '';
	$attr['class'] = esc_attr( trim( $cls . ' sc-flip sc-flip--' . $dir . ' sc-flip-' . $trigger ) );

	$mh  = isset( $o['min_height'] ) ? (int) $o['min_height'] : 260;
	$dur = isset( $o['duration'] ) ? (float) $o['duration'] : 0.6;
	$styles = array( 'min-height: ' . max( 40, $mh ) . 'px', '--flip-dur: ' . rtrim( rtrim( number_format( $dur, 2, '.', '' ), '0' ), '.' ) . 's' );
	$existing_style = isset( $attr['style'] ) ? trim( (string) $attr['style'] ) : '';
	$css            = implode( '; ', $styles ) . ';';
	$attr['style']  = esc_attr( $existing_style === '' ? $css : rtrim( $existing_style, '; ' ) . '; ' . $css );

	$bg  = upw_flip_resolve_color( isset( $o['back_bg'] ) ? $o['back_bg'] : '' );
	$col = upw_flip_resolve_color( isset( $o['back_color'] ) ? $o['back_color'] : '' );
	$attr['data-flip-bg']    = esc_attr( $bg !== '' ? $bg : '#2f74e6' );
	$attr['data-flip-color'] = esc_attr( $col !== '' ? $col : '#ffffff' );

	$heading = isset( $o['back_heading'] ) ? (string) $o['back_heading'] : '';
	$text    = isset( $o['back_text'] ) ? (string) $o['back_text'] : '';
	if ( $heading !== '' ) { $attr['data-flip-heading'] = esc_attr( $heading ); }
	if ( $text !== '' )    { $attr['data-flip-text'] = esc_attr( $text ); }

	upw_flip_card_flag( true );
	return $attr;
}, 22, 2 );

/* 2b) Force a wrapper when an element's ONLY non-default setting is a flip card. */
add_filter( 'sc_needs_wrapper', function ( $needs, $atts ) {
	if ( $needs || ! upw_flip_card_enabled() ) {
		return $needs;
	}
	$f = ( isset( $atts['flip_card'] ) && is_array( $atts['flip_card'] ) ) ? $atts['flip_card'] : array();
	return ( isset( $f['mode'] ) && $f['mode'] === 'on' );
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
	$dir  = __DIR__;
	$jsv  = file_exists( "$dir/static/js/flip-card.js" )  ? $ver . '.' . filemtime( "$dir/static/js/flip-card.js" )  : $ver;
	$cssv = file_exists( "$dir/static/css/flip-card.css" ) ? $ver . '.' . filemtime( "$dir/static/css/flip-card.css" ) : $ver;

	wp_enqueue_style( 'upw-flip-card', $base . '/static/css/flip-card.css', array(), $cssv );
	wp_enqueue_script( 'upw-flip-card', $base . '/static/js/flip-card.js', array(), $jsv, true );
}, 5 );
