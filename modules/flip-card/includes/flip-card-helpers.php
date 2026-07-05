<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — 3D Flip Card module: helpers.
 *
 * The setting reader (upw_flip_card_enabled), the used-on-this-page flag (upw_flip_card_flag),
 * the flip-style registry (upw_flip_card_styles), the color resolver (upw_flip_resolve_color),
 * and the shared back-face options group (upw_flip_card_options). Loaded first — the settings +
 * render parts depend on it.
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
