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
 * Effects: magnetic · tilt (3D) · spotlight · image_reveal · text_scramble · glow_border ·
 *   underline_grow · ripple · lift · color_shift · scale · push · jelly · skew · shine ·
 *   gradient_border · corner_brackets · fill_sweep · border_draw · glitch · text_swap ·
 *   rotate · pulse · shake · bounce · grayscale · blur · brightness · bg_pan · outline ·
 *   letter_spacing.
 * Multi-instance: several effects combine on one element (see upw_hover_instances).
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

if ( ! function_exists( 'upw_hover_effects' ) ) :
	/** The valid hover-effect ids — single source of truth for emit + wrapper checks. */
	function upw_hover_effects() {
		return array(
			'magnetic', 'tilt', 'spotlight', 'image_reveal', 'text_scramble',
			'glow_border', 'underline_grow', 'ripple', 'lift', 'color_shift',
			'scale', 'push', 'jelly', 'skew', 'shine', 'gradient_border',
			'corner_brackets', 'fill_sweep', 'border_draw', 'glitch', 'text_swap',
			'rotate', 'pulse', 'shake', 'bounce', 'grayscale', 'blur',
			'brightness', 'bg_pan', 'outline', 'letter_spacing',
		);
	}
endif;

if ( ! function_exists( 'upw_color_field' ) ) :
	/**
	 * Build a color option using the shortcodes Styling-tab preset selector
	 * (predefined-colors-color-picker-compact) instead of a raw color-picker, so
	 * element colors stay tied to the theme palette. Falls back to a plain
	 * color-picker if the helper isn't available (engine without shortcodes).
	 */
	function upw_color_field( $label, $kind = 'bg', $default_hex = '', $desc = '' ) {
		if ( function_exists( 'sc_color_field_compact' ) ) {
			return sc_color_field_compact( array(
				'label' => $label,
				'kind'  => $kind,
				'value' => $default_hex !== '' ? array( 'predefined' => '', 'custom' => $default_hex ) : '',
				'desc'  => $desc,
			) );
		}
		$f = array( 'type' => 'color-picker', 'label' => $label, 'value' => $default_hex );
		if ( $desc !== '' ) {
			$f['desc'] = $desc;
		}
		return $f;
	}
endif;

if ( ! function_exists( 'upw_hover_color' ) ) :
	/**
	 * Resolve a preset-or-custom color value (from upw_color_field) to a CSS color
	 * string: a preset → var(--color-{slug}) (live-linked to Theme Settings); a
	 * custom color → its hex; a legacy plain string → passed through.
	 */
	function upw_hover_color( $val ) {
		if ( is_string( $val ) ) {
			return $val;
		}
		if ( ! is_array( $val ) ) {
			return '';
		}
		$pre = isset( $val['predefined'] ) ? trim( (string) $val['predefined'] ) : '';
		$cus = isset( $val['custom'] )     ? trim( (string) $val['custom'] )     : '';
		if ( $pre !== '' ) {
			$slug = preg_replace( '/[^a-z0-9\-]/', '', preg_replace( '/^(text|bg)-/', '', $pre ) );
			return $slug !== '' ? 'var(--color-' . $slug . ')' : '';
		}
		if ( $cus !== '' ) {
			return preg_replace( '/[^A-Za-z0-9#\(\),.%\s]/', '', $cus );
		}
		return '';
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
		'help'         => __( 'Hover Interactions (Animation Engine): magnetic pull, 3D tilt, cursor spotlight, image reveal or text scramble. Honours "reduce motion" and is pointer-only (skipped on touch screens).', 'fw' ) . ( function_exists( 'upw_perf_note' ) ? ' ' . upw_perf_note() : '' ),
		'popover'      => true,
		'show_borders' => false,
		'value'        => array( 'effect' => 'none' ),
		'anim_meta'    => array( 'category' => __( 'Pointer', 'fw' ), 'multi' => true ), // multi = combine several hover effects on one element
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
						'glow_border'    => $ix( 'glow-border',    __( 'Glow Border', 'fw' ) ),
						'underline_grow' => $ix( 'underline-grow', __( 'Underline Grow', 'fw' ) ),
						'ripple'         => $ix( 'ripple',         __( 'Ripple', 'fw' ) ),
						'lift'           => $ix( 'lift',           __( 'Lift', 'fw' ) ),
						'color_shift'    => $ix( 'color-shift',    __( 'Color Shift', 'fw' ) ),
						'scale'           => $ix( 'scale',           __( 'Scale / Zoom', 'fw' ) ),
						'push'            => $ix( 'push',            __( 'Push', 'fw' ) ),
						'jelly'           => $ix( 'jelly',           __( 'Pop / Jelly', 'fw' ) ),
						'skew'            => $ix( 'skew',            __( 'Skew', 'fw' ) ),
						'shine'           => $ix( 'shine',           __( 'Shine Sweep', 'fw' ) ),
						'gradient_border' => $ix( 'gradient-border', __( 'Gradient Border', 'fw' ) ),
						'corner_brackets' => $ix( 'corner-brackets', __( 'Corner Brackets', 'fw' ) ),
						'fill_sweep'      => $ix( 'fill-sweep',      __( 'Fill Sweep', 'fw' ) ),
						'border_draw'     => $ix( 'border-draw',     __( 'Border Draw', 'fw' ) ),
						'glitch'          => $ix( 'glitch',          __( 'Glitch', 'fw' ) ),
						'text_swap'       => $ix( 'text-swap',       __( 'Text Swap', 'fw' ) ),
						'rotate'          => $ix( 'rotate',          __( 'Rotate', 'fw' ) ),
						'pulse'           => $ix( 'pulse',           __( 'Pulse', 'fw' ) ),
						'shake'           => $ix( 'shake',           __( 'Shake / Buzz', 'fw' ) ),
						'bounce'          => $ix( 'bounce',          __( 'Bounce', 'fw' ) ),
						'grayscale'       => $ix( 'grayscale',       __( 'Grayscale', 'fw' ) ),
						'blur'            => $ix( 'blur',            __( 'Blur Focus', 'fw' ) ),
						'brightness'      => $ix( 'brightness',      __( 'Brightness', 'fw' ) ),
						'bg_pan'          => $ix( 'bg-pan',          __( 'Background Pan', 'fw' ) ),
						'outline'         => $ix( 'outline',         __( 'Outline Expand', 'fw' ) ),
						'letter_spacing'  => $ix( 'letter-spacing',  __( 'Letter Spacing', 'fw' ) ),
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
				'glow_color' => upw_color_field( __( 'Glow color', 'fw' ), 'bg', '#6aa6ff' ),
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
			'glow_border' => array(
				'glow_color' => upw_color_field( __( 'Glow color', 'fw' ), 'bg', '#6aa6ff' ),
			),
			'underline_grow' => array(
				'line_color' => upw_color_field( __( 'Line color', 'fw' ), 'text', '', __( 'Defaults to the text color when left blank.', 'fw' ) ),
				'origin' => array(
					'type'    => 'select',
					'label'   => __( 'Grow from', 'fw' ),
					'value'   => 'left',
					'choices' => array(
						'left'   => __( 'Left', 'fw' ),
						'center' => __( 'Center', 'fw' ),
					),
				),
			),
			'ripple' => array(
				'ripple_color' => upw_color_field( __( 'Ripple color', 'fw' ), 'bg', '#6aa6ff' ),
			),
			'lift' => array(
				'distance' => array(
					'type'       => 'slider',
					'label'      => __( 'Lift distance (px)', 'fw' ),
					'value'      => 6,
					'properties' => array( 'min' => 2, 'max' => 20, 'step' => 1 ),
				),
				'shadow' => array(
					'type'         => 'switch',
					'label'        => __( 'Shadow', 'fw' ),
					'value'        => 'yes',
					'left-choice'  => array( 'value' => 'no',  'label' => __( 'Off', 'fw' ) ),
					'right-choice' => array( 'value' => 'yes', 'label' => __( 'On', 'fw' ) ),
				),
			),
			'color_shift' => array(
				'shift_color' => upw_color_field( __( 'Hover background', 'fw' ), 'bg', '#6aa6ff' ),
			),
			'scale' => array(
				'scale_to' => array( 'type' => 'slider', 'label' => __( 'Scale to', 'fw' ), 'value' => 1.04, 'properties' => array( 'min' => 1, 'max' => 1.2, 'step' => 0.01 ) ),
			),
			'push' => array(
				'depth' => array( 'type' => 'slider', 'label' => __( 'Press depth (px)', 'fw' ), 'value' => 5, 'properties' => array( 'min' => 1, 'max' => 12, 'step' => 1 ) ),
			),
			'jelly' => array(
				'strength' => array( 'type' => 'slider', 'label' => __( 'Bounciness', 'fw' ), 'value' => 1, 'properties' => array( 'min' => 0.3, 'max' => 2, 'step' => 0.1 ) ),
			),
			'skew' => array(
				'angle' => array( 'type' => 'slider', 'label' => __( 'Skew angle', 'fw' ), 'value' => -6, 'properties' => array( 'min' => -14, 'max' => 14, 'step' => 1 ) ),
			),
			'shine' => array(
				'shine_color' => upw_color_field( __( 'Shine color', 'fw' ), 'bg', '#ffffff' ),
			),
			'gradient_border' => array(
				'color_a' => upw_color_field( __( 'Gradient color A', 'fw' ), 'bg', '#6aa6ff' ),
				'color_b' => upw_color_field( __( 'Gradient color B', 'fw' ), 'bg', '#a06bff' ),
				'speed' => array( 'type' => 'slider', 'label' => __( 'Flow speed (s)', 'fw' ), 'value' => 3, 'properties' => array( 'min' => 1, 'max' => 8, 'step' => 0.5 ) ),
			),
			'corner_brackets' => array(
				'bracket_color' => upw_color_field( __( 'Bracket color', 'fw' ), 'bg', '#6aa6ff' ),
				'bracket_size' => array( 'type' => 'slider', 'label' => __( 'Bracket size (px)', 'fw' ), 'value' => 18, 'properties' => array( 'min' => 8, 'max' => 40, 'step' => 2 ) ),
			),
			'fill_sweep' => array(
				'fill_color' => upw_color_field( __( 'Fill color', 'fw' ), 'bg', '#2f74e6' ),
				'direction' => array( 'type' => 'select', 'label' => __( 'Fill from', 'fw' ), 'value' => 'left', 'choices' => array( 'left' => __( 'Left', 'fw' ), 'right' => __( 'Right', 'fw' ), 'up' => __( 'Bottom', 'fw' ), 'center' => __( 'Center', 'fw' ) ) ),
			),
			'border_draw' => array(
				'line_color' => upw_color_field( __( 'Line color', 'fw' ), 'bg', '#6aa6ff' ),
				'thickness' => array( 'type' => 'slider', 'label' => __( 'Thickness (px)', 'fw' ), 'value' => 2, 'properties' => array( 'min' => 1, 'max' => 6, 'step' => 1 ) ),
			),
			'glitch' => array(
				'strength' => array( 'type' => 'slider', 'label' => __( 'Intensity', 'fw' ), 'value' => 1, 'properties' => array( 'min' => 0.3, 'max' => 2, 'step' => 0.1 ) ),
			),
			'text_swap' => array(
				'swap_text' => array( 'type' => 'text', 'label' => __( 'Swap-to text', 'fw' ), 'desc' => __( 'Slides in on hover. Blank = reuse the original text.', 'fw' ), 'value' => '' ),
				'direction' => array( 'type' => 'select', 'label' => __( 'Slide', 'fw' ), 'value' => 'up', 'choices' => array( 'up' => __( 'Up', 'fw' ), 'down' => __( 'Down', 'fw' ) ) ),
			),
			'rotate' => array(
				'angle' => array( 'type' => 'slider', 'label' => __( 'Rotation', 'fw' ), 'value' => 6, 'properties' => array( 'min' => -45, 'max' => 45, 'step' => 1 ) ),
			),
			'pulse' => array(
				'strength' => array( 'type' => 'slider', 'label' => __( 'Pulse size', 'fw' ), 'value' => 1, 'properties' => array( 'min' => 0.3, 'max' => 2, 'step' => 0.1 ) ),
			),
			'shake' => array(
				'strength' => array( 'type' => 'slider', 'label' => __( 'Intensity', 'fw' ), 'value' => 1, 'properties' => array( 'min' => 0.3, 'max' => 2, 'step' => 0.1 ) ),
			),
			'bounce' => array(
				'height' => array( 'type' => 'slider', 'label' => __( 'Bounce height (px)', 'fw' ), 'value' => 10, 'properties' => array( 'min' => 4, 'max' => 30, 'step' => 1 ) ),
			),
			'grayscale' => array(
				'amount' => array( 'type' => 'slider', 'label' => __( 'Grayscale at rest (%)', 'fw' ), 'desc' => __( 'Desaturated when idle, full color on hover.', 'fw' ), 'value' => 100, 'properties' => array( 'min' => 20, 'max' => 100, 'step' => 5 ) ),
			),
			'blur' => array(
				'amount' => array( 'type' => 'slider', 'label' => __( 'Blur amount (px)', 'fw' ), 'value' => 4, 'properties' => array( 'min' => 1, 'max' => 12, 'step' => 1 ) ),
				'direction' => array( 'type' => 'select', 'label' => __( 'Blur', 'fw' ), 'value' => 'rest', 'choices' => array( 'rest' => __( 'Blurred at rest → sharp on hover', 'fw' ), 'hover' => __( 'Sharp at rest → blurred on hover', 'fw' ) ) ),
			),
			'brightness' => array(
				'mode' => array( 'type' => 'select', 'label' => __( 'On hover', 'fw' ), 'value' => 'brighten', 'choices' => array( 'brighten' => __( 'Brighten', 'fw' ), 'dim' => __( 'Dim', 'fw' ) ) ),
				'amount' => array( 'type' => 'slider', 'label' => __( 'Amount (%)', 'fw' ), 'value' => 20, 'properties' => array( 'min' => 5, 'max' => 60, 'step' => 5 ) ),
			),
			'bg_pan' => array(
				'color_a' => upw_color_field( __( 'Gradient color A', 'fw' ), 'bg', '#2f74e6' ),
				'color_b' => upw_color_field( __( 'Gradient color B', 'fw' ), 'bg', '#a06bff' ),
				'speed' => array( 'type' => 'slider', 'label' => __( 'Pan speed (s)', 'fw' ), 'value' => 3, 'properties' => array( 'min' => 1, 'max' => 8, 'step' => 0.5 ) ),
			),
			'outline' => array(
				'line_color' => upw_color_field( __( 'Outline color', 'fw' ), 'bg', '#6aa6ff' ),
				'offset' => array( 'type' => 'slider', 'label' => __( 'Offset (px)', 'fw' ), 'value' => 6, 'properties' => array( 'min' => 2, 'max' => 20, 'step' => 1 ) ),
				'thickness' => array( 'type' => 'slider', 'label' => __( 'Thickness (px)', 'fw' ), 'value' => 2, 'properties' => array( 'min' => 1, 'max' => 6, 'step' => 1 ) ),
			),
			'letter_spacing' => array(
				'amount' => array( 'type' => 'slider', 'label' => __( 'Extra spacing (px)', 'fw' ), 'value' => 3, 'properties' => array( 'min' => 1, 'max' => 12, 'step' => 1 ) ),
			),
		),
	);

	return $fields;
} );

/**
 * Collect every hover instance saved on an element — the base `interaction` plus any `interaction__N`
 * slots (multi-instance). Returns a list of [ 'effect' => key, 'settings' => array ] for the active
 * ones only, so a user can combine several hover effects (Lift + Ripple, …) on one element.
 */
if ( ! function_exists( 'upw_hover_instances' ) ) :
	function upw_hover_instances( $atts ) {
		$out = array();
		if ( ! is_array( $atts ) ) {
			return $out;
		}
		foreach ( $atts as $k => $v ) {
			if ( $k !== 'interaction' && ! preg_match( '/^interaction__\d+$/', (string) $k ) ) {
				continue;
			}
			if ( ! is_array( $v ) ) {
				continue;
			}
			$eff = isset( $v['effect'] ) ? (string) $v['effect'] : 'none';
			if ( ! in_array( $eff, upw_hover_effects(), true ) ) {
				continue;
			}
			$out[] = array(
				'effect'   => $eff,
				'settings' => ( isset( $v[ $eff ] ) && is_array( $v[ $eff ] ) ) ? $v[ $eff ] : array(),
			);
		}
		return $out;
	}
endif;

/* ------------------------------------------------------------------ *
 * 2) Emit the chosen effect(s) onto the element wrapper.
 *    Priority 21 → runs just after the entrance-animation filter (20).
 * ------------------------------------------------------------------ */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	if ( ! upw_hover_enabled() ) {
		return $attr;
	}

	$instances = upw_hover_instances( $atts );
	if ( empty( $instances ) ) {
		return $attr;
	}

	// Append a CSS custom-property string to whatever style is already on the wrapper.
	$add_style = function ( $css ) use ( &$attr ) {
		$existing      = isset( $attr['style'] ) ? rtrim( (string) $attr['style'], '; ' ) . '; ' : '';
		$attr['style'] = esc_attr( $existing . $css );
	};

	$classes = array( 'sc-hover' );
	$effects = array();

	// Apply EACH added hover instance onto the one wrapper, so effects combine (e.g. Lift + Ripple).
	// Per-effect data-attrs / CSS vars mostly don't collide; where two effects share one, the later
	// instance wins — that is the user's experiment to make.
	foreach ( $instances as $inst ) {
		$effect    = $inst['effect'];
		$o         = $inst['settings'];
		$classes[] = 'sc-hover--' . sanitize_html_class( $effect );
		$effects[] = $effect;

		// On-demand assets: record this effect so ONLY its CSS/JS partial is enqueued.
		if ( function_exists( 'upw_anim_use_asset' ) ) {
			upw_anim_use_asset( 'hover', $effect );
		}

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
			$color = upw_hover_color( $o['glow_color'] ?? '' );
			if ( $color === '' ) { $color = '#6aa6ff'; }
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

		case 'glow_border':
			$gc = upw_hover_color( $o['glow_color'] ?? '' );
			$add_style( '--hover-glow:' . ( $gc !== '' ? $gc : '#6aa6ff' ) . ';' );
			break;

		case 'underline_grow':
			$attr['data-hover-style'] = esc_attr( ( ( $o['origin'] ?? 'left' ) === 'center' ) ? 'center' : 'left' );
			$line = upw_hover_color( $o['line_color'] ?? '' );
			if ( $line !== '' ) {
				$add_style( '--hover-line:' . $line . ';' );
			}
			break;

		case 'ripple':
			$rc = upw_hover_color( $o['ripple_color'] ?? '' );
			$add_style( '--hover-ripple:' . ( $rc !== '' ? $rc : '#6aa6ff' ) . ';' );
			break;

		case 'lift':
			$add_style( '--hover-lift:' . (int) ( $o['distance'] ?? 6 ) . 'px;' );
			if ( ( $o['shadow'] ?? 'yes' ) !== 'yes' ) {
				$attr['data-hover-noshadow'] = '1';
			}
			break;

		case 'color_shift':
			$sc = upw_hover_color( $o['shift_color'] ?? '' );
			$add_style( '--hover-shift:' . ( $sc !== '' ? $sc : '#6aa6ff' ) . ';' );
			break;

		case 'scale':
			$add_style( '--hover-scale-to:' . (float) ( $o['scale_to'] ?? 1.04 ) . ';' );
			break;

		case 'push':
			$add_style( '--hover-push:' . (int) ( $o['depth'] ?? 5 ) . 'px;' );
			break;

		case 'jelly':
			$add_style( '--hover-jelly:' . (float) ( $o['strength'] ?? 1 ) . ';' );
			break;

		case 'skew':
			$add_style( '--hover-skew:' . (float) ( $o['angle'] ?? -6 ) . 'deg;' );
			break;

		case 'shine':
			$shc = upw_hover_color( $o['shine_color'] ?? '' );
			$add_style( '--hover-shine:' . ( $shc !== '' ? $shc : 'rgba(255,255,255,.55)' ) . ';' );
			break;

		case 'gradient_border':
			$ga = upw_hover_color( $o['color_a'] ?? '' );
			$gb = upw_hover_color( $o['color_b'] ?? '' );
			$add_style( '--hover-grad-a:' . ( $ga !== '' ? $ga : '#6aa6ff' ) . '; --hover-grad-b:' . ( $gb !== '' ? $gb : '#a06bff' ) . '; --hover-grad-speed:' . (float) ( $o['speed'] ?? 3 ) . 's;' );
			break;

		case 'corner_brackets':
			$bc = upw_hover_color( $o['bracket_color'] ?? '' );
			$add_style( '--hover-bracket:' . ( $bc !== '' ? $bc : '#6aa6ff' ) . '; --hover-bracket-size:' . (int) ( $o['bracket_size'] ?? 18 ) . 'px;' );
			break;

		case 'fill_sweep':
			$fc  = upw_hover_color( $o['fill_color'] ?? '' );
			$dir = in_array( ( $o['direction'] ?? 'left' ), array( 'left', 'right', 'up', 'center' ), true ) ? $o['direction'] : 'left';
			$attr['data-hover-fill'] = esc_attr( $dir );
			$add_style( '--hover-fill:' . ( $fc !== '' ? $fc : '#2f74e6' ) . ';' );
			break;

		case 'border_draw':
			$lc = upw_hover_color( $o['line_color'] ?? '' );
			$add_style( '--hover-line:' . ( $lc !== '' ? $lc : '#6aa6ff' ) . '; --hover-line-w:' . (int) ( $o['thickness'] ?? 2 ) . 'px;' );
			break;

		case 'glitch':
			$add_style( '--hover-glitch:' . (float) ( $o['strength'] ?? 1 ) . ';' );
			break;

		case 'text_swap':
			$swap = isset( $o['swap_text'] ) ? wp_strip_all_tags( (string) $o['swap_text'] ) : '';
			if ( $swap !== '' ) {
				$attr['data-hover-swap'] = esc_attr( $swap );
			}
			$attr['data-hover-swap-dir'] = esc_attr( ( ( $o['direction'] ?? 'up' ) === 'down' ) ? 'down' : 'up' );
			break;

		case 'rotate':
			$add_style( '--hover-rotate:' . (float) ( $o['angle'] ?? 6 ) . 'deg;' );
			break;

		case 'pulse':
			$add_style( '--hover-pulse:' . (float) ( $o['strength'] ?? 1 ) . ';' );
			break;

		case 'shake':
			$add_style( '--hover-shake:' . (float) ( $o['strength'] ?? 1 ) . ';' );
			break;

		case 'bounce':
			$add_style( '--hover-bounce:' . (int) ( $o['height'] ?? 10 ) . 'px;' );
			break;

		case 'grayscale':
			$add_style( '--hover-gray:' . (int) ( $o['amount'] ?? 100 ) . '%;' );
			break;

		case 'blur':
			$attr['data-hover-blur'] = esc_attr( ( ( $o['direction'] ?? 'rest' ) === 'hover' ) ? 'hover' : 'rest' );
			$add_style( '--hover-blur:' . (int) ( $o['amount'] ?? 4 ) . 'px;' );
			break;

		case 'brightness':
			$mode = ( ( $o['mode'] ?? 'brighten' ) === 'dim' ) ? 'dim' : 'brighten';
			$amt  = (int) ( $o['amount'] ?? 20 );
			$attr['data-hover-bright'] = esc_attr( $mode );
			$add_style( '--hover-bright:' . ( $mode === 'dim' ? ( 1 - $amt / 100 ) : ( 1 + $amt / 100 ) ) . ';' );
			break;

		case 'bg_pan':
			$pa = upw_hover_color( $o['color_a'] ?? '' );
			$pb = upw_hover_color( $o['color_b'] ?? '' );
			$add_style( '--hover-pan-a:' . ( $pa !== '' ? $pa : '#2f74e6' ) . '; --hover-pan-b:' . ( $pb !== '' ? $pb : '#a06bff' ) . '; --hover-pan-speed:' . (float) ( $o['speed'] ?? 3 ) . 's;' );
			break;

		case 'outline':
			$oc = upw_hover_color( $o['line_color'] ?? '' );
			$add_style( '--hover-outline:' . ( $oc !== '' ? $oc : '#6aa6ff' ) . '; --hover-outline-off:' . (int) ( $o['offset'] ?? 6 ) . 'px; --hover-outline-w:' . (int) ( $o['thickness'] ?? 2 ) . 'px;' );
			break;

		case 'letter_spacing':
			$add_style( '--hover-letter:' . (int) ( $o['amount'] ?? 3 ) . 'px;' );
			break;
	}
	}

	$cls                = isset( $attr['class'] ) ? trim( (string) $attr['class'] ) : '';
	$attr['class']      = esc_attr( trim( $cls . ' ' . implode( ' ', array_values( array_unique( $classes ) ) ) ) );
	$attr['data-hover'] = esc_attr( implode( ' ', $effects ) );

	upw_hover_flag( true );
	return $attr;
}, 21, 2 );

/* ------------------------------------------------------------------ *
 * 2b) Force a wrapper when an element's ONLY non-default setting is a hover
 *     interaction. Leaf shortcodes (text-block, special-heading, …) that gate
 *     their wrapper on sc_needs_wrapper() otherwise emit NO wrapper, so the
 *     data-hover attrs stamped above have nowhere to land and the effect
 *     silently never fires. Mirrors the GSAP module's sc_needs_wrapper hook.
 * ------------------------------------------------------------------ */
add_filter( 'sc_needs_wrapper', function ( $needs, $atts ) {
	if ( $needs || ! upw_hover_enabled() ) {
		return $needs;
	}
	return ! empty( upw_hover_instances( $atts ) );
}, 10, 2 );

/* ------------------------------------------------------------------ *
 * 3) On-demand assets. Register the module's per-effect partial layout with the
 *    shared loader (includes/asset-loader.php); a page then ships ONLY the CSS/JS
 *    for the effects it actually uses — recorded per element in the wrapper filter
 *    above via upw_anim_use_asset( 'hover', <effect> ). CSS-only pages load no JS.
 * ------------------------------------------------------------------ */
if ( function_exists( 'upw_anim_register_assets' ) ) {
	$upw_hover_ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( $upw_hover_ext ) {
		upw_anim_register_assets( 'hover', array(
			'path'      => __DIR__,
			'uri'       => $upw_hover_ext->get_declared_URI( '/modules/hover' ),
			'ver'       => $upw_hover_ext->manifest->get_version(),
			'css_dir'   => 'static/css/effects',
			'js_dir'    => 'static/js/effects',
			'base_js'   => 'static/js/hover-core.js',
			// ONLY these effects ship a JS partial; every other effect is CSS-only,
			// so a page using only CSS effects loads zero hover JavaScript.
			'js_styles' => array( 'magnetic', 'tilt', 'spotlight', 'ripple', 'text_scramble', 'text_swap' ),
			'js_cfg'    => function () {
				$cfg = array(
					'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
					'disableMobile' => ( function_exists( 'upw_anim_engine_setting' ) && upw_anim_engine_setting( 'disable_on_mobile', 'no' ) === 'yes' ),
				);
				return 'window.upwHoverCfg=' . wp_json_encode( $cfg ) . ';';
			},
		) );
	}
	unset( $upw_hover_ext );
}

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
