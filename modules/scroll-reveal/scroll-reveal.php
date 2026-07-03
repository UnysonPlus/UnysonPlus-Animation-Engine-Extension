<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Scroll Reveal (Clip Wipe) module.
 *
 * Un-masks any element with an animated clip-path wipe as it scrolls into view — a directional
 * wipe (left / right / up / down), an iris (circle) or a diagonal — a richer alternative to a plain
 * fade. Per-element (attaches from the Animations tab, like Marquee). Pure CSS clip-path transition
 * + one IntersectionObserver, no library. Assets load only on pages that use it. Global on/off:
 * Theme Settings → Animations → Effects.
 *
 * Saved value shape (multi-picker, picker id `mode`):
 *   [ 'mode' => 'left', 'left' => [ 'duration' => 0.7, 'delay' => 0, 'easing' => '…', 'replay' => 'no' ] ]
 */

if ( ! function_exists( 'upw_scroll_reveal_enabled' ) ) :
	function upw_scroll_reveal_enabled() {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return true;
		}
		$v = fw_get_db_settings_option( 'animation_scroll_reveal', array() );
		$e = ( is_array( $v ) && isset( $v['enable'] ) ) ? $v['enable'] : 'yes';
		return $e !== 'no' && $e !== false;
	}
endif;

if ( ! function_exists( 'upw_scroll_reveal_flag' ) ) :
	function upw_scroll_reveal_flag( $set = false ) {
		static $used = false;
		if ( $set ) {
			$used = true;
		}
		return $used;
	}
endif;

/* 1) The per-element "Scroll Reveal" control, appended to the Animations tab. */
add_filter( 'sc_animation_fields', function ( $fields ) {
	if ( ! is_array( $fields ) ) {
		return $fields;
	}

	// Shared reveal options (built once, mapped onto each direction).
	$opts = array(
		'duration' => array( 'type' => 'slider', 'label' => __( 'Duration (s)', 'fw' ), 'value' => 0.7, 'properties' => array( 'min' => 0.2, 'max' => 2, 'step' => 0.05 ) ),
		'delay'    => array( 'type' => 'number', 'label' => __( 'Delay (s)', 'fw' ), 'desc' => __( 'Wait before the wipe starts after the element enters view.', 'fw' ), 'value' => 0, 'min' => 0, 'step' => 0.1, 'numeric_type' => 'float' ),
		'easing'   => array(
			'type'    => 'select',
			'label'   => __( 'Easing', 'fw' ),
			'value'   => 'cubic-bezier(0.22, 1, 0.36, 1)',
			'choices' => array(
				'ease'                                => __( 'Ease', 'fw' ),
				'ease-out'                            => __( 'Ease Out', 'fw' ),
				'ease-in-out'                         => __( 'Ease In Out', 'fw' ),
				'linear'                              => __( 'Linear', 'fw' ),
				'cubic-bezier(0.22, 1, 0.36, 1)'      => __( 'Smooth out (default)', 'fw' ),
				'cubic-bezier(0.68, -0.55, 0.27, 1.55)' => __( 'Overshoot', 'fw' ),
			),
		),
		'replay'   => array(
			'type'         => 'switch',
			'label'        => __( 'Replay on scroll', 'fw' ),
			'desc'         => __( 'Re-run the wipe every time the element re-enters the viewport.', 'fw' ),
			'value'        => 'no',
			'left-choice'  => array( 'value' => 'no',  'label' => __( 'Off', 'fw' ) ),
			'right-choice' => array( 'value' => 'yes', 'label' => __( 'On', 'fw' ) ),
		),
	);

	$ext  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	$base = $ext ? $ext->get_declared_URI( '/modules/scroll-reveal/static/img' ) : '';
	$tile = function ( $file, $label ) use ( $base ) {
		return array(
			'small' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 66 ),
			'large' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 132 ),
			'label' => $label,
		);
	};

	$dirs = array(
		'left'     => __( 'Wipe Left', 'fw' ),
		'right'    => __( 'Wipe Right', 'fw' ),
		'up'       => __( 'Wipe Up', 'fw' ),
		'down'     => __( 'Wipe Down', 'fw' ),
		'iris'     => __( 'Iris (circle)', 'fw' ),
		'diagonal' => __( 'Diagonal', 'fw' ),
	);
	$choices_tiles = array( 'none' => $tile( 'none', __( 'None', 'fw' ) ) );
	$reveal        = array( 'none' => array() );
	foreach ( $dirs as $k => $lbl ) {
		$choices_tiles[ $k ] = $tile( $k, $lbl );
		$reveal[ $k ]        = array( 'group_scroll_reveal_' . $k => array( 'type' => 'group', 'options' => $opts ) );
	}

	$fields['scroll_reveal'] = array(
		'type'         => 'multi-picker',
		'popover'      => true,
		'label'        => __( 'Scroll Reveal', 'fw' ),
		'desc'         => __( 'Un-mask this element with an animated clip-path wipe as it scrolls into view — a richer alternative to a fade.', 'fw' ),
		'help'         => __( 'Scroll Reveal (Animation Engine): a directional clip-path wipe (left / right / up / down), an iris or a diagonal, triggered by a passive scroll check when the element enters view. Pure CSS transition, no library. Honours "reduce motion" (shows instantly) and loads only on pages that use it.', 'fw' ) . ( function_exists( 'upw_perf_note' ) ? ' ' . upw_perf_note() : '' ),
		'show_borders' => false,
		'value'        => array( 'mode' => 'none' ),
		'anim_meta'    => array( 'category' => __( 'Scroll', 'fw' ) ),
		'picker'       => array(
			'mode' => array(
				'type'    => 'image-picker',
				'label'   => false,
				'desc'    => __( 'Hover a tile to preview it larger.', 'fw' ),
				'value'   => 'none',
				'choices' => $choices_tiles,
			),
		),
		'choices' => $reveal,
	);

	return $fields;
} );

/* 2) Emit the reveal settings onto the element wrapper. */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	if ( ! upw_scroll_reveal_enabled() ) {
		return $attr;
	}
	$cr   = ( isset( $atts['scroll_reveal'] ) && is_array( $atts['scroll_reveal'] ) ) ? $atts['scroll_reveal'] : array();
	$mode = isset( $cr['mode'] ) ? (string) $cr['mode'] : 'none';
	if ( ! in_array( $mode, array( 'left', 'right', 'up', 'down', 'iris', 'diagonal' ), true ) ) {
		return $attr;
	}
	$o = ( isset( $cr[ $mode ] ) && is_array( $cr[ $mode ] ) ) ? $cr[ $mode ] : array();

	$cls           = isset( $attr['class'] ) ? trim( (string) $attr['class'] ) : '';
	$attr['class'] = esc_attr( trim( $cls . ' sc-clip-reveal sc-clip--' . $mode ) );

	$dur   = isset( $o['duration'] ) ? (float) $o['duration'] : 0.7;
	$delay = isset( $o['delay'] ) ? (float) $o['delay'] : 0;
	$ease  = isset( $o['easing'] ) ? (string) $o['easing'] : 'cubic-bezier(0.22, 1, 0.36, 1)';

	$styles = array();
	$styles[] = '--cr-dur: ' . rtrim( rtrim( number_format( $dur, 2, '.', '' ), '0' ), '.' ) . 's';
	if ( $delay > 0 ) {
		$styles[] = '--cr-delay: ' . rtrim( rtrim( number_format( $delay, 2, '.', '' ), '0' ), '.' ) . 's';
	}
	if ( $ease && preg_match( '/^[a-zA-Z0-9\.,\-\(\)\s]+$/', $ease ) ) {
		$styles[] = '--cr-ease: ' . $ease;
	}
	$existing_style = isset( $attr['style'] ) ? trim( (string) $attr['style'] ) : '';
	$css            = implode( '; ', $styles ) . ';';
	$attr['style']  = esc_attr( $existing_style === '' ? $css : rtrim( $existing_style, '; ' ) . '; ' . $css );

	if ( isset( $o['replay'] ) && $o['replay'] === 'yes' ) {
		$attr['data-cr-replay'] = '1';
	}

	upw_scroll_reveal_flag( true );
	return $attr;
}, 22, 2 );

/* 2b) Force a wrapper when an element's ONLY non-default setting is a scroll reveal. */
add_filter( 'sc_needs_wrapper', function ( $needs, $atts ) {
	if ( $needs || ! upw_scroll_reveal_enabled() ) {
		return $needs;
	}
	$cr   = ( isset( $atts['scroll_reveal'] ) && is_array( $atts['scroll_reveal'] ) ) ? $atts['scroll_reveal'] : array();
	$mode = isset( $cr['mode'] ) ? (string) $cr['mode'] : 'none';
	return in_array( $mode, array( 'left', 'right', 'up', 'down', 'iris', 'diagonal' ), true );
}, 10, 2 );

/* 3) Enqueue the runtime — only on pages that actually used it. */
add_action( 'wp_footer', function () {
	if ( ! upw_scroll_reveal_flag() ) {
		return;
	}
	$ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( ! $ext ) {
		return;
	}
	$base = $ext->get_declared_URI( '/modules/scroll-reveal' );
	$ver  = $ext->manifest->get_version();
	$dir  = __DIR__;
	$jsv  = file_exists( "$dir/static/js/scroll-reveal.js" )  ? $ver . '.' . filemtime( "$dir/static/js/scroll-reveal.js" )  : $ver;
	$cssv = file_exists( "$dir/static/css/scroll-reveal.css" ) ? $ver . '.' . filemtime( "$dir/static/css/scroll-reveal.css" ) : $ver;

	wp_enqueue_style( 'upw-scroll-reveal', $base . '/static/css/scroll-reveal.css', array(), $cssv );
	wp_enqueue_script( 'upw-scroll-reveal', $base . '/static/js/scroll-reveal.js', array(), $jsv, true );

	$cfg = array(
		'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
	);
	wp_add_inline_script( 'upw-scroll-reveal', 'window.upwScrollRevealCfg=' . wp_json_encode( $cfg ) . ';', 'before' );
}, 5 );
