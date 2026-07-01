<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Text Effects module.
 *
 * - Adds a "Text Effect" group to EVERY element's Animations tab
 *   (via the shortcodes extension's `sc_animation_fields` filter).
 * - Emits the chosen effect onto the element wrapper (via `sc_build_wrapper_attr`).
 * - Ships a self-contained vanilla-JS runtime (no GSAP), enqueued only on pages
 *   that actually use an effect.
 * - Global on/off lives in Theme Settings → Animations → Text.
 *
 * Effects: split_reveal · scramble · typewriter · shimmer · wave · glitch · vf_weight.
 * Saved value shape (multi-picker, picker id `effect`):
 *   [ 'effect' => 'split_reveal', 'split_reveal' => [ 'split_by' => 'words', … ] ]
 */

if ( ! function_exists( 'upw_text_enabled' ) ) :
	/** Global master switch (Theme Settings → Animations → Text). */
	function upw_text_enabled() {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return true;
		}
		$v = fw_get_db_settings_option( 'animation_text', array() );
		$e = ( is_array( $v ) && isset( $v['enable'] ) ) ? $v['enable'] : 'yes';
		return $e !== 'no' && $e !== false;
	}
endif;

if ( ! function_exists( 'upw_text_flag' ) ) :
	/** Per-request "a text effect rendered" flag → gates the footer enqueue. */
	function upw_text_flag( $set = false ) {
		static $used = false;
		if ( $set ) {
			$used = true;
		}
		return $used;
	}
endif;

if ( ! function_exists( 'upw_text_effects' ) ) :
	/** The valid text-effect ids — single source of truth for emit + wrapper checks. */
	function upw_text_effects() {
		return array(
			'split_reveal', 'scramble', 'typewriter', 'shimmer', 'wave', 'glitch', 'vf_weight',
		);
	}
endif;

if ( ! function_exists( 'upw_text_color_field' ) ) :
	/** A palette-preset color field, reusing the hover module's helper when present. */
	function upw_text_color_field( $label, $kind = 'text', $default_hex = '', $desc = '' ) {
		if ( function_exists( 'upw_color_field' ) ) {
			return upw_color_field( $label, $kind, $default_hex, $desc );
		}
		$f = array( 'type' => 'color-picker', 'label' => $label, 'value' => $default_hex );
		if ( $desc !== '' ) { $f['desc'] = $desc; }
		return $f;
	}
endif;

if ( ! function_exists( 'upw_text_color' ) ) :
	/** Resolve a preset-or-custom color to a CSS string (reuses the hover resolver). */
	function upw_text_color( $val, $fallback = '' ) {
		if ( function_exists( 'upw_hover_color' ) ) {
			$c = upw_hover_color( $val );
			return $c !== '' ? $c : $fallback;
		}
		return is_string( $val ) && $val !== '' ? $val : $fallback;
	}
endif;

/* ------------------------------------------------------------------ *
 * 1) The per-element "Text Effect" group, appended to the Animations tab.
 * ------------------------------------------------------------------ */
add_filter( 'sc_animation_fields', function ( $fields ) {
	if ( ! is_array( $fields ) ) {
		return $fields;
	}

	$tx_ext  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	$tx_base = $tx_ext ? $tx_ext->get_declared_URI( '/modules/text-effects/static/img/effects' ) : '';
	$tx      = function ( $file, $label ) use ( $tx_base ) {
		return array(
			'small' => array( 'src' => $tx_base . '/' . $file . '.svg', 'height' => 66 ),
			'large' => array( 'src' => $tx_base . '/' . $file . '.svg', 'height' => 132 ),
			'label' => $label,
		);
	};

	$trigger_view_load = array(
		'type'    => 'select',
		'label'   => __( 'Trigger', 'fw' ),
		'value'   => 'view',
		'choices' => array(
			'view' => __( 'When scrolled into view', 'fw' ),
			'load' => __( 'On page load', 'fw' ),
		),
	);

	$fields['text_effect'] = array(
		'type'         => 'multi-picker',
		'label'        => __( 'Text Effect', 'fw' ),
		'desc'         => __( 'A typographic animation applied to this element’s text.', 'fw' ) . ( function_exists( 'upw_perf_note' ) ? ' ' . upw_perf_note() : '' ),
		'help'         => __( 'Text Effects (Animation Engine): split-text reveal, scramble/decode, typewriter, gradient shimmer, wave, glitch and variable-font weight. Self-contained (no GSAP), honours "reduce motion", and the runtime loads only on pages that use an effect.', 'fw' ),
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
					'none'         => $tx( 'none',         __( 'None', 'fw' ) ),
					'split_reveal' => $tx( 'split-reveal', __( 'Split Reveal', 'fw' ) ),
					'scramble'     => $tx( 'scramble',     __( 'Scramble', 'fw' ) ),
					'typewriter'   => $tx( 'typewriter',   __( 'Typewriter', 'fw' ) ),
					'shimmer'      => $tx( 'shimmer',      __( 'Shimmer', 'fw' ) ),
					'wave'         => $tx( 'wave',         __( 'Wave', 'fw' ) ),
					'glitch'       => $tx( 'glitch',       __( 'Glitch', 'fw' ) ),
					'vf_weight'    => $tx( 'vf-weight',    __( 'Weight Sweep', 'fw' ) ),
				),
			),
		),
		'choices' => array(
			'split_reveal' => array(
				'split_by' => array(
					'type'    => 'select',
					'label'   => __( 'Split by', 'fw' ),
					'value'   => 'words',
					'choices' => array(
						'chars' => __( 'Characters', 'fw' ),
						'words' => __( 'Words', 'fw' ),
						'lines' => __( 'Lines', 'fw' ),
					),
				),
				'direction' => array(
					'type'    => 'select',
					'label'   => __( 'Rise from', 'fw' ),
					'value'   => 'up',
					'choices' => array(
						'up'    => __( 'Below', 'fw' ),
						'down'  => __( 'Above', 'fw' ),
						'left'  => __( 'Left', 'fw' ),
						'right' => __( 'Right', 'fw' ),
					),
				),
				'stagger' => array(
					'type'       => 'slider',
					'label'      => __( 'Stagger (s)', 'fw' ),
					'desc'       => __( 'Delay between each piece.', 'fw' ),
					'value'      => 0.03,
					'properties' => array( 'min' => 0.005, 'max' => 0.12, 'step' => 0.005 ),
				),
				'duration' => array(
					'type'       => 'slider',
					'label'      => __( 'Duration (s)', 'fw' ),
					'value'      => 0.6,
					'properties' => array( 'min' => 0.2, 'max' => 1.6, 'step' => 0.1 ),
				),
				'trigger' => $trigger_view_load,
			),
			'scramble' => array(
				'duration' => array(
					'type'       => 'slider',
					'label'      => __( 'Duration (s)', 'fw' ),
					'value'      => 1.2,
					'properties' => array( 'min' => 0.4, 'max' => 3, 'step' => 0.1 ),
				),
				'trigger' => $trigger_view_load,
			),
			'typewriter' => array(
				'speed' => array(
					'type'       => 'slider',
					'label'      => __( 'Speed (ms/char)', 'fw' ),
					'value'      => 55,
					'properties' => array( 'min' => 15, 'max' => 200, 'step' => 5 ),
				),
				'caret' => array(
					'type'         => 'switch',
					'label'        => __( 'Caret', 'fw' ),
					'value'        => 'yes',
					'left-choice'  => array( 'value' => 'no',  'label' => __( 'Off', 'fw' ) ),
					'right-choice' => array( 'value' => 'yes', 'label' => __( 'On', 'fw' ) ),
				),
				'loop' => array(
					'type'         => 'switch',
					'label'        => __( 'Loop', 'fw' ),
					'desc'         => __( 'Type, erase and retype forever.', 'fw' ),
					'value'        => 'no',
					'left-choice'  => array( 'value' => 'no',  'label' => __( 'Off', 'fw' ) ),
					'right-choice' => array( 'value' => 'yes', 'label' => __( 'On', 'fw' ) ),
				),
				'trigger' => $trigger_view_load,
			),
			'shimmer' => array(
				'color_a' => upw_text_color_field( __( 'Base color', 'fw' ), 'text', '#8a8f98' ),
				'color_b' => upw_text_color_field( __( 'Sheen color', 'fw' ), 'text', '#ffffff' ),
				'speed' => array(
					'type'       => 'slider',
					'label'      => __( 'Speed (s)', 'fw' ),
					'value'      => 3,
					'properties' => array( 'min' => 1, 'max' => 6, 'step' => 0.5 ),
				),
			),
			'wave' => array(
				'amplitude' => array(
					'type'       => 'slider',
					'label'      => __( 'Amplitude (px)', 'fw' ),
					'value'      => 6,
					'properties' => array( 'min' => 2, 'max' => 16, 'step' => 1 ),
				),
				'speed' => array(
					'type'       => 'slider',
					'label'      => __( 'Speed (s)', 'fw' ),
					'value'      => 1.4,
					'properties' => array( 'min' => 0.5, 'max' => 3, 'step' => 0.1 ),
				),
			),
			'glitch' => array(
				'trigger' => array(
					'type'    => 'select',
					'label'   => __( 'Trigger', 'fw' ),
					'value'   => 'hover',
					'choices' => array(
						'hover'  => __( 'On hover', 'fw' ),
						'always' => __( 'Always', 'fw' ),
					),
				),
				'intensity' => array(
					'type'       => 'slider',
					'label'      => __( 'Intensity (px)', 'fw' ),
					'value'      => 3,
					'properties' => array( 'min' => 1, 'max' => 8, 'step' => 1 ),
				),
			),
			'vf_weight' => array(
				'from' => array(
					'type'       => 'slider',
					'label'      => __( 'From weight', 'fw' ),
					'value'      => 300,
					'properties' => array( 'min' => 100, 'max' => 900, 'step' => 50 ),
				),
				'to' => array(
					'type'       => 'slider',
					'label'      => __( 'To weight', 'fw' ),
					'value'      => 800,
					'properties' => array( 'min' => 100, 'max' => 900, 'step' => 50 ),
				),
				'trigger' => array(
					'type'    => 'select',
					'label'   => __( 'Trigger', 'fw' ),
					'value'   => 'hover',
					'choices' => array(
						'hover' => __( 'On hover', 'fw' ),
						'view'  => __( 'When scrolled into view', 'fw' ),
					),
				),
			),
		),
	);

	return $fields;
} );

/* ------------------------------------------------------------------ *
 * 2) Emit the chosen effect onto the element wrapper.
 * ------------------------------------------------------------------ */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	if ( ! upw_text_enabled() ) {
		return $attr;
	}

	$tx     = ( isset( $atts['text_effect'] ) && is_array( $atts['text_effect'] ) ) ? $atts['text_effect'] : array();
	$effect = isset( $tx['effect'] ) ? (string) $tx['effect'] : 'none';

	if ( ! in_array( $effect, upw_text_effects(), true ) ) {
		return $attr;
	}

	$o = ( isset( $tx[ $effect ] ) && is_array( $tx[ $effect ] ) ) ? $tx[ $effect ] : array();

	$cls           = isset( $attr['class'] ) ? trim( (string) $attr['class'] ) : '';
	$attr['class'] = esc_attr( trim( $cls . ' sc-text sc-text--' . sanitize_html_class( $effect ) ) );
	$attr['data-text'] = esc_attr( $effect );

	$add_style = function ( $css ) use ( &$attr ) {
		$existing      = isset( $attr['style'] ) ? rtrim( (string) $attr['style'], '; ' ) . '; ' : '';
		$attr['style'] = esc_attr( $existing . $css );
	};

	switch ( $effect ) {
		case 'split_reveal':
			$attr['data-text-split']    = esc_attr( in_array( ( $o['split_by'] ?? 'words' ), array( 'chars', 'words', 'lines' ), true ) ? $o['split_by'] : 'words' );
			$attr['data-text-dir']      = esc_attr( in_array( ( $o['direction'] ?? 'up' ), array( 'up', 'down', 'left', 'right' ), true ) ? $o['direction'] : 'up' );
			$attr['data-text-stagger']  = esc_attr( (float) ( $o['stagger'] ?? 0.03 ) );
			$attr['data-text-duration'] = esc_attr( (float) ( $o['duration'] ?? 0.6 ) );
			$attr['data-text-trigger']  = esc_attr( ( ( $o['trigger'] ?? 'view' ) === 'load' ) ? 'load' : 'view' );
			break;

		case 'scramble':
			$attr['data-text-duration'] = esc_attr( (float) ( $o['duration'] ?? 1.2 ) );
			$attr['data-text-trigger']  = esc_attr( ( ( $o['trigger'] ?? 'view' ) === 'load' ) ? 'load' : 'view' );
			break;

		case 'typewriter':
			$attr['data-text-speed']   = esc_attr( (int) ( $o['speed'] ?? 55 ) );
			$attr['data-text-caret']   = ( ( $o['caret'] ?? 'yes' ) !== 'no' ) ? '1' : '0';
			$attr['data-text-loop']    = ( ( $o['loop'] ?? 'no' ) === 'yes' ) ? '1' : '0';
			$attr['data-text-trigger'] = esc_attr( ( ( $o['trigger'] ?? 'view' ) === 'load' ) ? 'load' : 'view' );
			break;

		case 'shimmer':
			$ca = upw_text_color( $o['color_a'] ?? '', '#8a8f98' );
			$cb = upw_text_color( $o['color_b'] ?? '', '#ffffff' );
			$add_style( '--text-c1:' . $ca . '; --text-c2:' . $cb . '; --text-speed:' . (float) ( $o['speed'] ?? 3 ) . 's;' );
			break;

		case 'wave':
			$attr['data-text-split'] = 'chars';
			$add_style( '--text-wave-amp:' . (int) ( $o['amplitude'] ?? 6 ) . 'px; --text-wave-speed:' . (float) ( $o['speed'] ?? 1.4 ) . 's;' );
			break;

		case 'glitch':
			$attr['data-text-trigger'] = esc_attr( ( ( $o['trigger'] ?? 'hover' ) === 'always' ) ? 'always' : 'hover' );
			$add_style( '--text-glitch:' . (int) ( $o['intensity'] ?? 3 ) . 'px;' );
			break;

		case 'vf_weight':
			$attr['data-text-trigger'] = esc_attr( ( ( $o['trigger'] ?? 'hover' ) === 'view' ) ? 'view' : 'hover' );
			$add_style( '--text-wght-from:' . (int) ( $o['from'] ?? 300 ) . '; --text-wght-to:' . (int) ( $o['to'] ?? 800 ) . ';' );
			break;
	}

	upw_text_flag( true );
	return $attr;
}, 22, 2 );

/* ------------------------------------------------------------------ *
 * 2b) Force a wrapper when a text effect is the element's only setting
 *     (leaf shortcodes gate their wrapper on sc_needs_wrapper()).
 * ------------------------------------------------------------------ */
add_filter( 'sc_needs_wrapper', function ( $needs, $atts ) {
	if ( $needs || ! upw_text_enabled() ) {
		return $needs;
	}
	$tx     = ( isset( $atts['text_effect'] ) && is_array( $atts['text_effect'] ) ) ? $atts['text_effect'] : array();
	$effect = isset( $tx['effect'] ) ? (string) $tx['effect'] : 'none';
	return in_array( $effect, upw_text_effects(), true );
}, 10, 2 );

/* ------------------------------------------------------------------ *
 * 3) Enqueue the runtime — only on pages that actually used an effect.
 * ------------------------------------------------------------------ */
add_action( 'wp_footer', function () {
	if ( ! upw_text_flag() ) {
		return;
	}
	$ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( ! $ext ) {
		return;
	}
	$base = $ext->get_declared_URI( '/modules/text-effects' );
	$ver  = $ext->manifest->get_version();
	$dir  = __DIR__;
	$jsv  = file_exists( "$dir/static/js/text-effects.js" )  ? $ver . '.' . filemtime( "$dir/static/js/text-effects.js" )  : $ver;
	$cssv = file_exists( "$dir/static/css/text-effects.css" ) ? $ver . '.' . filemtime( "$dir/static/css/text-effects.css" ) : $ver;

	wp_enqueue_style( 'upw-text', $base . '/static/css/text-effects.css', array(), $cssv );
	wp_enqueue_script( 'upw-text', $base . '/static/js/text-effects.js', array(), $jsv, true );

	$cfg = array(
		'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
		'disableMobile' => ( function_exists( 'upw_anim_engine_setting' ) && upw_anim_engine_setting( 'disable_on_mobile', 'no' ) === 'yes' ),
	);
	wp_add_inline_script( 'upw-text', 'window.upwTextCfg=' . wp_json_encode( $cfg ) . ';', 'before' );
}, 5 );

/* ------------------------------------------------------------------ *
 * 4) Global on/off → Theme Settings → Animations → Text sub-tab.
 * ------------------------------------------------------------------ */
add_filter( 'upw_anim_engine_module_tabs', function ( $tabs ) {
	$tabs['text_effects'] = array(
		'title'   => __( 'Text', 'fw' ),
		'type'    => 'tab',
		'options' => array(
			'text_box' => array(
				'title'   => __( 'Text Effects', 'fw' ),
				'type'    => 'box',
				'options' => array(
					'animation_text' => array(
						'type'          => 'multi',
						'label'         => false,
						'inner-options' => array(
							'enable' => array(
								'label'        => __( 'Enable text effects', 'fw' ),
								'desc'         => __( 'Master switch for the per-element Text Effect animations. Off = none load anywhere.', 'fw' ),
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
