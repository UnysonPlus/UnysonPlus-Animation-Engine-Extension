<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Hover Interactions module.
 *
 * - Adds an "Interaction" hover-effect group to EVERY element's Animations tab
 *   (via the shortcodes extension's `sc_animation_fields` filter).
 * - Emits the chosen effect onto the element wrapper (via `sc_build_wrapper_attr`).
 * - Ships the runtime JS/CSS, enqueued only on pages that actually use an effect.
 * - Global on/off lives in Theme Settings → Animations → Interactions.
 *
 * Effects: magnetic · tilt (3D) · spotlight (cursor glow) · image_reveal · text_scramble.
 * Saved value shape (multi-picker, picker id `effect`):
 *   [ 'effect' => 'tilt', 'tilt' => [ 'max_tilt' => 12, … ] ]
 */

if ( ! function_exists( 'upw_hover_enabled' ) ) :
	/** Global master switch (Theme Settings → Animations → Interactions). */
	function upw_hover_enabled() {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return true;
		}
		$v = fw_get_db_settings_option( 'animation_hover', array() );
		$e = ( is_array( $v ) && isset( $v['enable'] ) ) ? $v['enable'] : 'yes';
		return $e !== 'no' && $e !== false;
	}
endif;

if ( ! function_exists( 'upw_hover_flag' ) ) :
	/** Per-request "a hover effect rendered" flag → gates the footer enqueue. */
	function upw_hover_flag( $set = false ) {
		static $used = false;
		if ( $set ) {
			$used = true;
		}
		return $used;
	}
endif;

/* ------------------------------------------------------------------ *
 * 1) The per-element "Interaction" group, appended to the Animations tab.
 * ------------------------------------------------------------------ */
add_filter( 'sc_animation_fields', function ( $fields ) {
	if ( ! is_array( $fields ) ) {
		return $fields;
	}

	// Visual tiles for the picker — hand-authored animated SVG diagrams (same
	// approach as the Scroll Effect picker). Each tile shows the SVG at two sizes
	// (large = the hover preview).
	$ix_ext  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	$ix_base = $ix_ext ? $ix_ext->get_declared_URI( '/modules/hover/static/img/interactions' ) : '';
	$ix      = function ( $file, $label ) use ( $ix_base ) {
		return array(
			'small' => array( 'src' => $ix_base . '/' . $file . '.svg', 'height' => 66 ),
			'large' => array( 'src' => $ix_base . '/' . $file . '.svg', 'height' => 132 ),
			'label' => $label,
		);
	};

	$fields['interaction'] = array(
		'type'         => 'multi-picker',
		'label'        => __( 'Hover Interaction', 'fw' ),
		'desc'         => __( 'A pointer-driven effect applied while hovering this element.', 'fw' ),
		'help'         => __( 'Hover Interactions (Animation Engine): magnetic pull, 3D tilt, cursor spotlight, image reveal or text scramble. Honours "reduce motion" and is pointer-only (skipped on touch screens). The runtime loads only on pages that use an effect.', 'fw' ),
		'popover'      => true,
		'show_borders' => false,
		'value'        => array( 'effect' => 'none' ),
		'picker'       => array(
			'effect' => array(
				'type'    => 'image-picker',
				'label'   => false,
				'desc'    => __( 'Hover a tile to preview it larger.', 'fw' ),
				'value'   => 'none',
				'choices' => array(
					'none'          => $ix( 'none',          __( 'None', 'fw' ) ),
					'magnetic'      => $ix( 'magnetic',      __( 'Magnetic', 'fw' ) ),
					'tilt'          => $ix( 'tilt',          __( '3D Tilt', 'fw' ) ),
					'spotlight'     => $ix( 'spotlight',     __( 'Spotlight', 'fw' ) ),
					'image_reveal'  => $ix( 'image-reveal',  __( 'Image Reveal', 'fw' ) ),
					'text_scramble' => $ix( 'text-scramble', __( 'Text Scramble', 'fw' ) ),
				),
			),
		),
		'choices' => array(
			'magnetic' => array(
				'strength' => array(
					'type'       => 'slider',
					'label'      => __( 'Strength', 'fw' ),
					'desc'       => __( 'How far the element is pulled toward the cursor.', 'fw' ),
					'value'      => 0.3,
					'properties' => array( 'min' => 0.05, 'max' => 0.6, 'step' => 0.05 ),
				),
			),
			'tilt' => array(
				'max_tilt' => array(
					'type'       => 'slider',
					'label'      => __( 'Max tilt (°)', 'fw' ),
					'value'      => 12,
					'properties' => array( 'min' => 2, 'max' => 25, 'step' => 1 ),
				),
				'hover_scale' => array(
					'type'       => 'slider',
					'label'      => __( 'Hover scale', 'fw' ),
					'value'      => 1,
					'properties' => array( 'min' => 1, 'max' => 1.15, 'step' => 0.01 ),
				),
				'glare' => array(
					'type'         => 'switch',
					'label'        => __( 'Glare', 'fw' ),
					'desc'         => __( 'A light sheen that follows the tilt.', 'fw' ),
					'value'        => 'no',
					'left-choice'  => array( 'value' => 'no',  'label' => __( 'Off', 'fw' ) ),
					'right-choice' => array( 'value' => 'yes', 'label' => __( 'On', 'fw' ) ),
				),
			),
			'spotlight' => array(
				'glow_color' => array(
					'type'  => 'color-picker',
					'label' => __( 'Glow color', 'fw' ),
					'value' => '#6aa6ff',
				),
				'glow_size' => array(
					'type'       => 'slider',
					'label'      => __( 'Glow size (%)', 'fw' ),
					'value'      => 40,
					'properties' => array( 'min' => 10, 'max' => 90, 'step' => 5 ),
				),
			),
			'image_reveal' => array(
				'reveal_style' => array(
					'type'    => 'select',
					'label'   => __( 'Style', 'fw' ),
					'value'   => 'zoom_gray',
					'choices' => array(
						'zoom'       => __( 'Zoom', 'fw' ),
						'grayscale'  => __( 'Grayscale → color', 'fw' ),
						'zoom_gray'  => __( 'Zoom + color', 'fw' ),
						'shine'      => __( 'Shine sweep', 'fw' ),
					),
				),
				'zoom' => array(
					'type'       => 'slider',
					'label'      => __( 'Zoom', 'fw' ),
					'value'      => 1.06,
					'properties' => array( 'min' => 1, 'max' => 1.2, 'step' => 0.01 ),
				),
			),
			'text_scramble' => array(
				'duration' => array(
					'type'       => 'slider',
					'label'      => __( 'Duration (s)', 'fw' ),
					'value'      => 0.8,
					'properties' => array( 'min' => 0.2, 'max' => 2, 'step' => 0.1 ),
				),
			),
		),
	);

	return $fields;
} );

/* ------------------------------------------------------------------ *
 * 2) Emit the chosen effect onto the element wrapper.
 *    Priority 21 → runs just after the entrance-animation filter (20).
 * ------------------------------------------------------------------ */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	if ( ! upw_hover_enabled() ) {
		return $attr;
	}

	$ix     = ( isset( $atts['interaction'] ) && is_array( $atts['interaction'] ) ) ? $atts['interaction'] : array();
	$effect = isset( $ix['effect'] ) ? (string) $ix['effect'] : 'none';

	$allowed = array( 'magnetic', 'tilt', 'spotlight', 'image_reveal', 'text_scramble' );
	if ( ! in_array( $effect, $allowed, true ) ) {
		return $attr;
	}

	$o = ( isset( $ix[ $effect ] ) && is_array( $ix[ $effect ] ) ) ? $ix[ $effect ] : array();

	$cls           = isset( $attr['class'] ) ? trim( (string) $attr['class'] ) : '';
	$attr['class'] = esc_attr( trim( $cls . ' sc-hover sc-hover--' . sanitize_html_class( $effect ) ) );
	$attr['data-hover'] = esc_attr( $effect );

	// Append a CSS custom-property string to whatever style is already on the wrapper.
	$add_style = function ( $css ) use ( &$attr ) {
		$existing      = isset( $attr['style'] ) ? rtrim( (string) $attr['style'], '; ' ) . '; ' : '';
		$attr['style'] = esc_attr( $existing . $css );
	};

	switch ( $effect ) {
		case 'magnetic':
			$attr['data-hover-strength'] = esc_attr( (float) ( $o['strength'] ?? 0.3 ) );
			break;

		case 'tilt':
			$attr['data-hover-max']   = esc_attr( (float) ( $o['max_tilt'] ?? 12 ) );
			$attr['data-hover-scale'] = esc_attr( (float) ( $o['hover_scale'] ?? 1 ) );
			if ( ( $o['glare'] ?? 'no' ) === 'yes' ) {
				$attr['data-hover-glare'] = '1';
			}
			break;

		case 'spotlight':
			$color = (string) ( $o['glow_color'] ?? '#6aa6ff' );
			$size  = (int) ( $o['glow_size'] ?? 40 );
			$add_style( '--hover-glow:' . $color . '; --hover-glow-size:' . $size . '%;' );
			break;

		case 'image_reveal':
			$attr['data-hover-style'] = esc_attr( sanitize_html_class( (string) ( $o['reveal_style'] ?? 'zoom_gray' ) ) );
			$add_style( '--hover-zoom:' . (float) ( $o['zoom'] ?? 1.06 ) . ';' );
			break;

		case 'text_scramble':
			$attr['data-hover-duration'] = esc_attr( (float) ( $o['duration'] ?? 0.8 ) );
			break;
	}

	upw_hover_flag( true );
	return $attr;
}, 21, 2 );

/* ------------------------------------------------------------------ *
 * 3) Enqueue the runtime — only on pages that actually used an effect.
 * ------------------------------------------------------------------ */
add_action( 'wp_footer', function () {
	if ( ! upw_hover_flag() ) {
		return;
	}
	$ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( ! $ext ) {
		return;
	}
	$base = $ext->get_declared_URI( '/modules/hover' );
	$ver  = $ext->manifest->get_version();
	$dir  = __DIR__;
	$jsv  = file_exists( "$dir/static/js/hover.js" )  ? $ver . '.' . filemtime( "$dir/static/js/hover.js" )  : $ver;
	$cssv = file_exists( "$dir/static/css/hover.css" ) ? $ver . '.' . filemtime( "$dir/static/css/hover.css" ) : $ver;

	wp_enqueue_style( 'upw-hover', $base . '/static/css/hover.css', array(), $cssv );
	wp_enqueue_script( 'upw-hover', $base . '/static/js/hover.js', array(), $jsv, true );

	// Honour the engine's global policy (reduced motion, disable-on-mobile).
	$cfg = array(
		'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
		'disableMobile' => ( function_exists( 'upw_anim_engine_setting' ) && upw_anim_engine_setting( 'disable_on_mobile', 'no' ) === 'yes' ),
	);
	wp_add_inline_script( 'upw-hover', 'window.upwHoverCfg=' . wp_json_encode( $cfg ) . ';', 'before' );
}, 5 );

/* ------------------------------------------------------------------ *
 * 4) Global on/off → Theme Settings → Animations → Interactions sub-tab.
 * ------------------------------------------------------------------ */
add_filter( 'upw_anim_engine_module_tabs', function ( $tabs ) {
	$tabs['hover_interactions'] = array(
		'title'   => __( 'Interactions', 'fw' ),
		'type'    => 'tab',
		'options' => array(
			'hover_box' => array(
				'title'   => __( 'Hover Interactions', 'fw' ),
				'type'    => 'box',
				'options' => array(
					'animation_hover' => array(
						'type'          => 'multi',
						'label'         => false,
						'inner-options' => array(
							'enable' => array(
								'label'        => __( 'Enable hover interactions', 'fw' ),
								'desc'         => __( 'Master switch for the per-element Hover Interaction effects. Off = none load anywhere.', 'fw' ),
								'type'         => 'switch',
								'value'        => 'yes',
								'left-choice'  => array( 'value' => 'no',  'label' => __( 'No', 'fw' ) ),
								'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
							),
						),
					),
				),
			),
		),
	);
	return $tabs;
} );
