<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — 3D Flip Card module.
 *
 * Flips any element front-to-back in 3D to reveal a back face with your own heading, text, image,
 * button and colours. Per-element (attaches from the Animations tab). At runtime the element's
 * existing content becomes the FRONT face and a BACK face is built from the options. Pure CSS 3D
 * transforms, no library. Assets load only on pages that use it. Global on/off: Theme Settings →
 * Animations → Effects.
 *
 * FLIP STYLES (picker id `mode`): flip · cube · fold · door · diagonal · pop · carousel (+ off).
 * Each style reveals the same shared settings group.
 *
 * Saved value shape (multi-picker):
 *   [ 'mode' => 'off'|'<style>', '<style>' => [ trigger, auto_interval, direction, min_height,
 *       duration, perspective, easing, radius, back_align, back_heading, back_text, back_image,
 *       back_btn_text, back_btn_url, back_bg, back_color ] ]
 */

if ( ! function_exists( 'upw_flip_card_styles' ) ) :
	function upw_flip_card_styles() {
		return array(
			'flip'     => __( 'Flip', 'fw' ),
			'cube'     => __( 'Cube', 'fw' ),
			'fold'     => __( 'Fold', 'fw' ),
			'door'     => __( 'Door', 'fw' ),
			'diagonal' => __( 'Diagonal', 'fw' ),
			'pop'      => __( 'Pop', 'fw' ),
			'carousel' => __( 'Carousel', 'fw' ),
		);
	}
endif;

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

/** The shared settings group revealed under every flip style. */
if ( ! function_exists( 'upw_flip_card_options' ) ) :
	function upw_flip_card_options() {
		$bg_field = function_exists( 'sc_color_field_compact' )
			? sc_color_field_compact( array( 'label' => __( 'Back background', 'fw' ), 'kind' => 'bg' ) )
			: array( 'type' => 'color-picker', 'label' => __( 'Back background', 'fw' ), 'value' => '#2f74e6' );
		$col_field = function_exists( 'sc_color_field_compact' )
			? sc_color_field_compact( array( 'label' => __( 'Back text color', 'fw' ), 'kind' => 'text' ) )
			: array( 'type' => 'color-picker', 'label' => __( 'Back text color', 'fw' ), 'value' => '#ffffff' );

		return array(
			'trigger' => array(
				'type'    => 'select',
				'label'   => __( 'Flip on', 'fw' ),
				'value'   => 'hover',
				'choices' => array(
					'hover'  => __( 'Hover', 'fw' ),
					'click'  => __( 'Click / tap', 'fw' ),
					'scroll' => __( 'Scroll into view', 'fw' ),
					'auto'   => __( 'Auto (loop)', 'fw' ),
				),
				'desc' => __( 'Hover and Click flip both ways; Scroll flips once when it enters view; Auto flips back and forth on a timer.', 'fw' ),
			),
			'auto_interval' => array( 'type' => 'slider', 'label' => __( 'Auto interval (s)', 'fw' ), 'desc' => __( 'Only used by the Auto trigger.', 'fw' ), 'value' => 3, 'properties' => array( 'min' => 1, 'max' => 12, 'step' => 0.5 ) ),
			'direction' => array(
				'type'    => 'select',
				'label'   => __( 'Direction / axis', 'fw' ),
				'value'   => 'h',
				'choices' => array( 'h' => __( 'Horizontal (Y axis)', 'fw' ), 'v' => __( 'Vertical (X axis)', 'fw' ) ),
				'desc'    => __( 'The axis the card turns on. Diagonal ignores this.', 'fw' ),
			),
			'min_height' => array( 'type' => 'slider', 'label' => __( 'Card height (px)', 'fw' ), 'desc' => __( 'Both faces share this height.', 'fw' ), 'value' => 260, 'properties' => array( 'min' => 80, 'max' => 600, 'step' => 10 ) ),
			'duration'   => array( 'type' => 'slider', 'label' => __( 'Flip speed (s)', 'fw' ), 'value' => 0.6, 'properties' => array( 'min' => 0.2, 'max' => 1.5, 'step' => 0.05 ) ),
			'perspective' => array( 'type' => 'slider', 'label' => __( '3D depth (perspective px)', 'fw' ), 'desc' => __( 'Lower = more dramatic 3D.', 'fw' ), 'value' => 1400, 'properties' => array( 'min' => 500, 'max' => 2600, 'step' => 50 ) ),
			'easing' => array(
				'type'    => 'select',
				'label'   => __( 'Easing', 'fw' ),
				'value'   => 'smooth',
				'choices' => array(
					'smooth' => __( 'Smooth', 'fw' ),
					'spring' => __( 'Spring (overshoot)', 'fw' ),
					'out'    => __( 'Ease out', 'fw' ),
					'linear' => __( 'Linear', 'fw' ),
				),
			),
			'radius' => array( 'type' => 'slider', 'label' => __( 'Corner radius (px)', 'fw' ), 'value' => 0, 'properties' => array( 'min' => 0, 'max' => 48, 'step' => 1 ) ),
			'back_align' => array(
				'type'    => 'select',
				'label'   => __( 'Back content align', 'fw' ),
				'value'   => 'center',
				'choices' => array( 'top' => __( 'Top', 'fw' ), 'center' => __( 'Center', 'fw' ), 'bottom' => __( 'Bottom', 'fw' ) ),
			),
			'back_heading'  => array( 'type' => 'text', 'label' => __( 'Back heading', 'fw' ), 'value' => '' ),
			'back_text'     => array( 'type' => 'textarea', 'label' => __( 'Back text', 'fw' ), 'value' => '' ),
			'back_image'    => array( 'type' => 'upload', 'label' => __( 'Back background image', 'fw' ), 'desc' => __( 'Optional. Sits behind the back heading / text (cover).', 'fw' ), 'value' => array() ),
			'back_btn_text' => array( 'type' => 'text', 'label' => __( 'Back button text', 'fw' ), 'value' => '' ),
			'back_btn_url'  => array( 'type' => 'text', 'label' => __( 'Back button URL', 'fw' ), 'value' => '' ),
			'back_bg'       => $bg_field,
			'back_color'    => $col_field,
		);
	}
endif;

/* 1) The per-element "Flip Card" control, appended to the Animations tab. */
add_filter( 'sc_animation_fields', function ( $fields ) {
	if ( ! is_array( $fields ) ) {
		return $fields;
	}

	$ext  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	$base = $ext ? $ext->get_declared_URI( '/modules/flip-card/static/img' ) : '';
	$tile = function ( $file, $label ) use ( $base ) {
		return array(
			'small' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 66 ),
			'large' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 132 ),
			'label' => $label,
		);
	};

	$opts    = upw_flip_card_options();
	$choices = array( 'off' => $tile( 'off', __( 'Off', 'fw' ) ) );
	$reveals = array();
	foreach ( upw_flip_card_styles() as $key => $label ) {
		$choices[ $key ] = $tile( $key, $label );
		$reveals[ $key ] = array( 'group_flip_' . $key => array( 'type' => 'group', 'options' => $opts ) );
	}

	$fields['flip_card'] = array(
		'type'         => 'multi-picker',
		'popover'      => true,
		'label'        => __( '3D Flip Card', 'fw' ),
		'desc'         => __( 'Flip this element in 3D to reveal a back face — pick from seven flip styles (Flip, Cube, Fold, Door, Diagonal, Pop, Carousel).', 'fw' ),
		'help'         => __( '3D Flip Card (Animation Engine): the element\'s content becomes the front face and a back face is built from the options below (heading, text, image, button, colours). Flips on hover, click, scroll-into-view or an auto loop (click is keyboard-accessible). Pure CSS 3D transforms, no library. Loads only on pages that use it.', 'fw' ) . ( function_exists( 'upw_perf_note' ) ? ' ' . upw_perf_note() : '' ),
		'show_borders' => false,
		'value'        => array( 'mode' => 'off' ),
		'anim_meta'    => array( 'category' => __( 'Pointer', 'fw' ) ),
		'picker'       => array(
			'mode' => array(
				'type'    => 'image-picker',
				'label'   => false,
				'desc'    => __( 'Hover a tile to preview it larger.', 'fw' ),
				'value'   => 'off',
				'choices' => $choices,
			),
		),
		'choices' => $reveals,
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
	$dir  = __DIR__;
	$jsv  = file_exists( "$dir/static/js/flip-card.js" )  ? $ver . '.' . filemtime( "$dir/static/js/flip-card.js" )  : $ver;
	$cssv = file_exists( "$dir/static/css/flip-card.css" ) ? $ver . '.' . filemtime( "$dir/static/css/flip-card.css" ) : $ver;

	wp_enqueue_style( 'upw-flip-card', $base . '/static/css/flip-card.css', array(), $cssv );
	wp_enqueue_script( 'upw-flip-card', $base . '/static/js/flip-card.js', array(), $jsv, true );
}, 5 );
