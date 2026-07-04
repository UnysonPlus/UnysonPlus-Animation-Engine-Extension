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
			// Wave A — reveal variants
			'blur', 'mask', 'flip3d', 'scale', 'slide', 'bounce', 'random', 'skew',
			// Wave B — CSS-driven (continuous + emphasis)
			'gradient_flow', 'rainbow', 'neon', 'breathing', 'jitter', 'float',
			'marker', 'strikebox', 'outline_fill', 'chromatic', 'width_sweep',
			// Wave C — JS-driven (type/decode + interactive + media)
			'rotating_words', 'countup', 'splitflap', 'matrix', 'fill_sweep',
			'letter_jump', 'expand_spacing', 'color_wave', 'magnetic', 'image_mask', 'kinetic',
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

	// Shared option group for the reveal-family effects (split_reveal + Wave-A variants).
	$reveal_group = function ( $split = 'chars', $with_dir = false ) use ( $trigger_view_load ) {
		$g = array(
			'split_by' => array(
				'type'    => 'select',
				'label'   => __( 'Split by', 'fw' ),
				'value'   => $split,
				'choices' => array(
					'chars' => __( 'Characters', 'fw' ),
					'words' => __( 'Words', 'fw' ),
					'lines' => __( 'Lines', 'fw' ),
				),
			),
			'stagger' => array(
				'type'       => 'slider',
				'label'      => __( 'Stagger (s)', 'fw' ),
				'value'      => 0.03,
				'properties' => array( 'min' => 0.005, 'max' => 0.12, 'step' => 0.005 ),
			),
			'duration' => array(
				'type'       => 'slider',
				'label'      => __( 'Duration (s)', 'fw' ),
				'value'      => 0.6,
				'properties' => array( 'min' => 0.2, 'max' => 1.6, 'step' => 0.1 ),
			),
			'sequence' => array(
				'type'    => 'select',
				'label'   => __( 'Sequence', 'fw' ),
				'desc'    => __( 'When the element has several paragraphs / lines: reveal them all at once, or one after another (the stagger cascades from the very first word to the last).', 'fw' ),
				'value'   => 'together',
				'choices' => array(
					'together' => __( 'All together', 'fw' ),
					'cascade'  => __( 'One after another', 'fw' ),
				),
			),
			'trigger' => $trigger_view_load,
		);
		if ( $with_dir ) {
			$g = array( 'direction' => array(
				'type'    => 'select',
				'label'   => __( 'From', 'fw' ),
				'value'   => 'left',
				'choices' => array(
					'left'  => __( 'Left', 'fw' ),
					'right' => __( 'Right', 'fw' ),
					'up'    => __( 'Below', 'fw' ),
					'down'  => __( 'Above', 'fw' ),
				),
			) ) + $g;
		}
		return $g;
	};

	$fields['text_effect'] = array(
		'type'         => 'multi-picker',
		'label'        => __( 'Text Effect', 'fw' ),
		'desc'         => __( 'A typographic animation applied to this element’s text.', 'fw' ),
		'help'         => __( 'Text Effects (Animation Engine): split-text reveal, scramble/decode, typewriter, gradient shimmer, wave, glitch and variable-font weight. Self-contained (no GSAP), honours "reduce motion".', 'fw' ) . ( function_exists( 'upw_perf_note' ) ? ' ' . upw_perf_note() : '' ),
		'popover'      => true,
		'show_borders' => false,
		'value'        => array( 'effect' => 'none' ),
		'anim_meta'    => array( 'category' => __( 'Text', 'fw' ), 'icon' => '&#128221;' ), // 📝 (Animations-tab inserter)
		'picker'       => array(
			'effect' => array(
				'type'    => 'image-picker',
				'label'   => false,
				'desc'    => __( 'Hover a tile to preview it larger.', 'fw' ),
				'value'   => 'none',
				'choices' => array(
					'none'         => $tx( 'none',         __( 'None', 'fw' ) ),
					'split_reveal' => $tx( 'split-reveal', __( 'Split Reveal', 'fw' ) ),
					'blur'         => $tx( 'blur',         __( 'Blur Reveal', 'fw' ) ),
					'mask'         => $tx( 'mask',         __( 'Mask Reveal', 'fw' ) ),
					'flip3d'       => $tx( 'flip3d',       __( 'Flip 3D', 'fw' ) ),
					'scale'        => $tx( 'scale',        __( 'Scale Pop', 'fw' ) ),
					'slide'        => $tx( 'slide',        __( 'Slide', 'fw' ) ),
					'bounce'       => $tx( 'bounce',       __( 'Bounce In', 'fw' ) ),
					'random'       => $tx( 'random',       __( 'Random Order', 'fw' ) ),
					'skew'         => $tx( 'skew',         __( 'Skew Reveal', 'fw' ) ),
					'scramble'     => $tx( 'scramble',     __( 'Scramble', 'fw' ) ),
					'typewriter'   => $tx( 'typewriter',   __( 'Typewriter', 'fw' ) ),
					'shimmer'      => $tx( 'shimmer',      __( 'Shimmer', 'fw' ) ),
					'wave'         => $tx( 'wave',         __( 'Wave', 'fw' ) ),
					'glitch'       => $tx( 'glitch',       __( 'Glitch', 'fw' ) ),
					'vf_weight'    => $tx( 'vf-weight',    __( 'Weight Sweep', 'fw' ) ),
					'gradient_flow'=> $tx( 'gradient-flow', __( 'Gradient Flow', 'fw' ) ),
					'rainbow'      => $tx( 'rainbow',      __( 'Rainbow', 'fw' ) ),
					'neon'         => $tx( 'neon',         __( 'Neon Flicker', 'fw' ) ),
					'breathing'    => $tx( 'breathing',    __( 'Breathing', 'fw' ) ),
					'jitter'       => $tx( 'jitter',       __( 'Jitter', 'fw' ) ),
					'float'        => $tx( 'float',        __( 'Float', 'fw' ) ),
					'marker'       => $tx( 'marker',       __( 'Marker Highlight', 'fw' ) ),
					'strikebox'    => $tx( 'strikebox',    __( 'Strike / Box', 'fw' ) ),
					'outline_fill' => $tx( 'outline-fill', __( 'Outline → Fill', 'fw' ) ),
					'chromatic'    => $tx( 'chromatic',    __( 'Chromatic', 'fw' ) ),
					'width_sweep'  => $tx( 'width-sweep',  __( 'Width Sweep', 'fw' ) ),
					'rotating_words' => $tx( 'rotating-words', __( 'Rotating Words', 'fw' ) ),
					'countup'      => $tx( 'countup',      __( 'Count Up', 'fw' ) ),
					'splitflap'    => $tx( 'splitflap',    __( 'Split-Flap', 'fw' ) ),
					'matrix'       => $tx( 'matrix',       __( 'Matrix Decode', 'fw' ) ),
					'fill_sweep'   => $tx( 'fill-sweep',   __( 'Fill Sweep', 'fw' ) ),
					'letter_jump'  => $tx( 'letter-jump',  __( 'Letter Jump', 'fw' ) ),
					'expand_spacing' => $tx( 'expand-spacing', __( 'Expand Spacing', 'fw' ) ),
					'color_wave'   => $tx( 'color-wave',   __( 'Color Wave', 'fw' ) ),
					'magnetic'     => $tx( 'magnetic',     __( 'Magnetic Letters', 'fw' ) ),
					'image_mask'   => $tx( 'image-mask',   __( 'Image Mask', 'fw' ) ),
					'kinetic'      => $tx( 'kinetic',      __( 'Kinetic Scroll', 'fw' ) ),
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
			'blur'   => $reveal_group( 'chars' ),
			'mask'   => $reveal_group( 'lines' ),
			'flip3d' => $reveal_group( 'chars' ),
			'scale'  => $reveal_group( 'chars' ),
			'slide'  => $reveal_group( 'words', true ),
			'bounce' => $reveal_group( 'chars' ),
			'random' => $reveal_group( 'chars' ),
			'skew'   => $reveal_group( 'words' ),
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
			'gradient_flow' => array(
				'color_a' => upw_text_color_field( __( 'Color 1', 'fw' ), 'text', '#ff6b6b' ),
				'color_b' => upw_text_color_field( __( 'Color 2', 'fw' ), 'text', '#6a8dff' ),
				'color_c' => upw_text_color_field( __( 'Color 3', 'fw' ), 'text', '#17c964' ),
				'speed'   => array( 'type' => 'slider', 'label' => __( 'Speed (s)', 'fw' ), 'value' => 4, 'properties' => array( 'min' => 1, 'max' => 8, 'step' => 0.5 ) ),
			),
			'rainbow' => array(
				'speed' => array( 'type' => 'slider', 'label' => __( 'Speed (s)', 'fw' ), 'value' => 4, 'properties' => array( 'min' => 1, 'max' => 8, 'step' => 0.5 ) ),
			),
			'neon' => array(
				'glow_color' => upw_text_color_field( __( 'Glow color', 'fw' ), 'bg', '#6aa6ff' ),
				'speed'      => array( 'type' => 'slider', 'label' => __( 'Flicker speed (s)', 'fw' ), 'value' => 2.5, 'properties' => array( 'min' => 1, 'max' => 5, 'step' => 0.5 ) ),
			),
			'breathing' => array(
				'speed' => array( 'type' => 'slider', 'label' => __( 'Speed (s)', 'fw' ), 'value' => 3, 'properties' => array( 'min' => 1.5, 'max' => 6, 'step' => 0.5 ) ),
			),
			'jitter' => array(
				'intensity' => array( 'type' => 'slider', 'label' => __( 'Intensity (px)', 'fw' ), 'value' => 2, 'properties' => array( 'min' => 1, 'max' => 6, 'step' => 1 ) ),
			),
			'float' => array(
				'distance' => array( 'type' => 'slider', 'label' => __( 'Distance (px)', 'fw' ), 'value' => 8, 'properties' => array( 'min' => 3, 'max' => 24, 'step' => 1 ) ),
				'speed'    => array( 'type' => 'slider', 'label' => __( 'Speed (s)', 'fw' ), 'value' => 3, 'properties' => array( 'min' => 1.5, 'max' => 6, 'step' => 0.5 ) ),
			),
			'marker' => array(
				'color'   => upw_text_color_field( __( 'Highlight color', 'fw' ), 'bg', '#ffe066' ),
				'trigger' => array( 'type' => 'select', 'label' => __( 'Trigger', 'fw' ), 'value' => 'view', 'choices' => array( 'view' => __( 'When scrolled into view', 'fw' ), 'hover' => __( 'On hover', 'fw' ) ) ),
			),
			'strikebox' => array(
				'shape'   => array( 'type' => 'select', 'label' => __( 'Shape', 'fw' ), 'value' => 'strike', 'choices' => array( 'strike' => __( 'Strike-through', 'fw' ), 'underline' => __( 'Underline', 'fw' ), 'box' => __( 'Box', 'fw' ) ) ),
				'color'   => upw_text_color_field( __( 'Line color', 'fw' ), 'text', '', __( 'Defaults to the text color when blank.', 'fw' ) ),
				'trigger' => array( 'type' => 'select', 'label' => __( 'Trigger', 'fw' ), 'value' => 'view', 'choices' => array( 'view' => __( 'When scrolled into view', 'fw' ), 'hover' => __( 'On hover', 'fw' ) ) ),
			),
			'outline_fill' => array(
				'color'   => upw_text_color_field( __( 'Fill color', 'fw' ), 'text', '', __( 'Defaults to the text color when blank.', 'fw' ) ),
				'trigger' => array( 'type' => 'select', 'label' => __( 'Trigger', 'fw' ), 'value' => 'view', 'choices' => array( 'view' => __( 'When scrolled into view', 'fw' ), 'hover' => __( 'On hover', 'fw' ) ) ),
			),
			'chromatic' => array(
				'intensity' => array( 'type' => 'slider', 'label' => __( 'Offset (px)', 'fw' ), 'value' => 2, 'properties' => array( 'min' => 1, 'max' => 6, 'step' => 1 ) ),
			),
			'width_sweep' => array(
				'from'    => array( 'type' => 'slider', 'label' => __( 'From width', 'fw' ), 'value' => 75, 'properties' => array( 'min' => 25, 'max' => 200, 'step' => 5 ) ),
				'to'      => array( 'type' => 'slider', 'label' => __( 'To width', 'fw' ), 'value' => 125, 'properties' => array( 'min' => 25, 'max' => 200, 'step' => 5 ) ),
				'trigger' => array( 'type' => 'select', 'label' => __( 'Trigger', 'fw' ), 'value' => 'hover', 'choices' => array( 'hover' => __( 'On hover', 'fw' ), 'view' => __( 'When scrolled into view', 'fw' ) ) ),
			),
			'rotating_words' => array(
				'words'    => array( 'type' => 'text', 'label' => __( 'Words', 'fw' ), 'desc' => __( 'Comma-separated words to cycle through, after the element’s own text. e.g. <em>designer, developer, dreamer</em>', 'fw' ), 'value' => '' ),
				'interval' => array( 'type' => 'slider', 'label' => __( 'Interval (s)', 'fw' ), 'value' => 1.8, 'properties' => array( 'min' => 0.6, 'max' => 5, 'step' => 0.2 ) ),
			),
			'countup' => array(
				'duration' => array( 'type' => 'slider', 'label' => __( 'Duration (s)', 'fw' ), 'value' => 1.6, 'properties' => array( 'min' => 0.5, 'max' => 5, 'step' => 0.1 ) ),
				'trigger'  => $trigger_view_load,
			),
			'splitflap' => array(
				'duration' => array( 'type' => 'slider', 'label' => __( 'Duration (s)', 'fw' ), 'value' => 1.4, 'properties' => array( 'min' => 0.4, 'max' => 3, 'step' => 0.1 ) ),
				'trigger'  => $trigger_view_load,
			),
			'matrix' => array(
				'duration' => array( 'type' => 'slider', 'label' => __( 'Duration (s)', 'fw' ), 'value' => 1.4, 'properties' => array( 'min' => 0.6, 'max' => 3, 'step' => 0.1 ) ),
				'trigger'  => $trigger_view_load,
			),
			'fill_sweep' => array(
				'color'   => upw_text_color_field( __( 'Fill color', 'fw' ), 'text', '#2f74e6' ),
				'trigger' => array( 'type' => 'select', 'label' => __( 'Trigger', 'fw' ), 'value' => 'hover', 'choices' => array( 'hover' => __( 'On hover', 'fw' ), 'view' => __( 'When scrolled into view', 'fw' ) ) ),
			),
			'letter_jump' => array(
				'height' => array( 'type' => 'slider', 'label' => __( 'Jump height (px)', 'fw' ), 'desc' => __( 'Each letter hops on hover.', 'fw' ), 'value' => 6, 'properties' => array( 'min' => 2, 'max' => 18, 'step' => 1 ) ),
			),
			'expand_spacing' => array(
				'amount' => array( 'type' => 'slider', 'label' => __( 'Extra spacing (px)', 'fw' ), 'desc' => __( 'Letter-spacing widens on hover.', 'fw' ), 'value' => 6, 'properties' => array( 'min' => 1, 'max' => 20, 'step' => 1 ) ),
			),
			'color_wave' => array(
				'color'   => upw_text_color_field( __( 'Wave color', 'fw' ), 'text', '#2f74e6' ),
				'trigger' => array( 'type' => 'select', 'label' => __( 'Trigger', 'fw' ), 'value' => 'hover', 'choices' => array( 'hover' => __( 'On hover', 'fw' ), 'view' => __( 'When scrolled into view', 'fw' ) ) ),
			),
			'magnetic' => array(
				'strength' => array( 'type' => 'slider', 'label' => __( 'Strength', 'fw' ), 'desc' => __( 'How far each letter nudges toward the pointer.', 'fw' ), 'value' => 0.4, 'properties' => array( 'min' => 0.1, 'max' => 1, 'step' => 0.05 ) ),
			),
			'image_mask' => array(
				'image' => array( 'type' => 'upload', 'label' => __( 'Image', 'fw' ), 'desc' => __( 'The text becomes a window onto this image. Use bold, large text for the best effect.', 'fw' ) ),
			),
			'kinetic' => array(
				'intensity' => array( 'type' => 'slider', 'label' => __( 'Intensity', 'fw' ), 'desc' => __( 'How much the letters spread/skew with scroll speed.', 'fw' ), 'value' => 4, 'properties' => array( 'min' => 1, 'max' => 10, 'step' => 1 ) ),
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

	// On-demand assets: record this effect so ONLY its JS partial (+ CSS partial if it has
	// one) is enqueued, not the whole 37-effect bundle.
	if ( function_exists( 'upw_anim_use_asset' ) ) {
		upw_anim_use_asset( 'text-effects', $effect );
	}

	$add_style = function ( $css ) use ( &$attr ) {
		$existing      = isset( $attr['style'] ) ? rtrim( (string) $attr['style'], '; ' ) . '; ' : '';
		$attr['style'] = esc_attr( $existing . $css );
	};

	// The reveal family (split_reveal + Wave-A variants) all emit the same attrs;
	// the JS routes by the effect id to the right initial state.
	$reveal_ids = array( 'split_reveal', 'blur', 'mask', 'flip3d', 'scale', 'slide', 'bounce', 'random', 'skew' );
	if ( in_array( $effect, $reveal_ids, true ) ) {
		$attr['data-text-split']    = esc_attr( in_array( ( $o['split_by'] ?? 'chars' ), array( 'chars', 'words', 'lines' ), true ) ? $o['split_by'] : 'chars' );
		$attr['data-text-stagger']  = esc_attr( (float) ( $o['stagger'] ?? 0.03 ) );
		$attr['data-text-duration'] = esc_attr( (float) ( $o['duration'] ?? 0.6 ) );
		$attr['data-text-trigger']  = esc_attr( ( ( $o['trigger'] ?? 'view' ) === 'load' ) ? 'load' : 'view' );
		$attr['data-text-seq']      = esc_attr( ( ( $o['sequence'] ?? 'together' ) === 'cascade' ) ? 'cascade' : 'together' );
		if ( isset( $o['direction'] ) ) {
			$attr['data-text-dir'] = esc_attr( in_array( $o['direction'], array( 'up', 'down', 'left', 'right' ), true ) ? $o['direction'] : 'left' );
		}
		upw_text_flag( true );
		return $attr;
	}

	switch ( $effect ) {
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

		case 'gradient_flow':
			$add_style( '--text-c1:' . upw_text_color( $o['color_a'] ?? '', '#ff6b6b' )
				. '; --text-c2:' . upw_text_color( $o['color_b'] ?? '', '#6a8dff' )
				. '; --text-c3:' . upw_text_color( $o['color_c'] ?? '', '#17c964' )
				. '; --text-speed:' . (float) ( $o['speed'] ?? 4 ) . 's;' );
			break;

		case 'rainbow':
			$add_style( '--text-speed:' . (float) ( $o['speed'] ?? 4 ) . 's;' );
			break;

		case 'neon':
			$add_style( '--text-neon:' . upw_text_color( $o['glow_color'] ?? '', '#6aa6ff' ) . '; --text-speed:' . (float) ( $o['speed'] ?? 2.5 ) . 's;' );
			break;

		case 'breathing':
			$add_style( '--text-speed:' . (float) ( $o['speed'] ?? 3 ) . 's;' );
			break;

		case 'jitter':
			$add_style( '--text-jitter:' . (int) ( $o['intensity'] ?? 2 ) . 'px;' );
			break;

		case 'float':
			$add_style( '--text-float:' . (int) ( $o['distance'] ?? 8 ) . 'px; --text-speed:' . (float) ( $o['speed'] ?? 3 ) . 's;' );
			break;

		case 'marker':
			$attr['data-text-trigger'] = esc_attr( ( ( $o['trigger'] ?? 'view' ) === 'hover' ) ? 'hover' : 'view' );
			$add_style( '--text-marker:' . upw_text_color( $o['color'] ?? '', '#ffe066' ) . ';' );
			break;

		case 'strikebox':
			$attr['data-text-shape']   = esc_attr( in_array( ( $o['shape'] ?? 'strike' ), array( 'strike', 'underline', 'box' ), true ) ? $o['shape'] : 'strike' );
			$attr['data-text-trigger'] = esc_attr( ( ( $o['trigger'] ?? 'view' ) === 'hover' ) ? 'hover' : 'view' );
			$lc = upw_text_color( $o['color'] ?? '', '' );
			if ( $lc !== '' ) { $add_style( '--text-line:' . $lc . ';' ); }
			break;

		case 'outline_fill':
			$attr['data-text-trigger'] = esc_attr( ( ( $o['trigger'] ?? 'view' ) === 'hover' ) ? 'hover' : 'view' );
			$fc = upw_text_color( $o['color'] ?? '', '' );
			if ( $fc !== '' ) { $add_style( '--text-fill:' . $fc . ';' ); }
			break;

		case 'chromatic':
			$add_style( '--text-chroma:' . (int) ( $o['intensity'] ?? 2 ) . 'px;' );
			break;

		case 'width_sweep':
			$attr['data-text-trigger'] = esc_attr( ( ( $o['trigger'] ?? 'hover' ) === 'view' ) ? 'view' : 'hover' );
			$add_style( '--text-wdth-from:' . (int) ( $o['from'] ?? 75 ) . '; --text-wdth-to:' . (int) ( $o['to'] ?? 125 ) . ';' );
			break;

		case 'rotating_words':
			$attr['data-text-words']    = esc_attr( (string) ( $o['words'] ?? '' ) );
			$attr['data-text-interval'] = esc_attr( (float) ( $o['interval'] ?? 1.8 ) );
			break;

		case 'countup':
		case 'splitflap':
		case 'matrix':
			$attr['data-text-duration'] = esc_attr( (float) ( $o['duration'] ?? 1.4 ) );
			$attr['data-text-trigger']  = esc_attr( ( ( $o['trigger'] ?? 'view' ) === 'load' ) ? 'load' : 'view' );
			break;

		case 'fill_sweep':
			$attr['data-text-trigger'] = esc_attr( ( ( $o['trigger'] ?? 'hover' ) === 'view' ) ? 'view' : 'hover' );
			$add_style( '--text-fill:' . upw_text_color( $o['color'] ?? '', '#2f74e6' ) . ';' );
			break;

		case 'letter_jump':
			$add_style( '--text-jump:' . (int) ( $o['height'] ?? 6 ) . 'px;' );
			break;

		case 'expand_spacing':
			$add_style( '--text-spacing:' . (int) ( $o['amount'] ?? 6 ) . 'px;' );
			break;

		case 'color_wave':
			$attr['data-text-trigger'] = esc_attr( ( ( $o['trigger'] ?? 'hover' ) === 'view' ) ? 'view' : 'hover' );
			$add_style( '--text-wavecolor:' . upw_text_color( $o['color'] ?? '', '#2f74e6' ) . ';' );
			break;

		case 'magnetic':
			$attr['data-text-strength'] = esc_attr( (float) ( $o['strength'] ?? 0.4 ) );
			break;

		case 'image_mask':
			$mi = ( isset( $o['image'] ) && is_array( $o['image'] ) && ! empty( $o['image']['url'] ) ) ? esc_url_raw( $o['image']['url'] ) : '';
			if ( $mi !== '' ) { $attr['data-text-img'] = esc_url( $mi ); }
			break;

		case 'kinetic':
			$add_style( '--text-kinetic:' . (int) ( $o['intensity'] ?? 4 ) . ';' );
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
 * 3) On-demand assets. Register the module's per-effect partial layout with the shared
 *    loader; a page ships ONLY the shared core (text engine) + the used effects' partials
 *    — recorded per element in the wrapper filter via upw_anim_use_asset(). js_core_first:
 *    the core defines the shared engine that each effect partial aliases.
 * ------------------------------------------------------------------ */
if ( function_exists( 'upw_anim_register_assets' ) ) {
	$upw_text_ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( $upw_text_ext ) {
		upw_anim_register_assets( 'text-effects', array(
			'path'          => __DIR__,
			'uri'           => $upw_text_ext->get_declared_URI( '/modules/text-effects' ),
			'ver'           => $upw_text_ext->manifest->get_version(),
			'css_dir'       => 'static/css/effects',   // effects with a CSS class have a partial here; JS-only ones have none
			'js_dir'        => 'static/js/effects',
			'base_css'      => 'static/css/base.css',   // the split-piece / line spans, always needed
			'base_js'       => 'static/js/text-effects-core.js',
			'js_core_first' => true,                    // core (engine) loads before the effect partials
			'js_styles'     => upw_text_effects(),      // every effect ships a JS partial (registers into window.upwText)
			// The split/mask reveal engine (piece staggering, presets, cascade) is kept OUT of the
			// core — loaded only when one of these entrance effects is on the page.
			'js_shared'     => array(
				'reveal' => array( 'split_reveal', 'blur', 'flip3d', 'scale', 'slide', 'bounce', 'random', 'skew', 'mask' ),
			),
			'js_cfg'        => function () {
				$cfg = array(
					'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
					'disableMobile' => ( function_exists( 'upw_anim_engine_setting' ) && upw_anim_engine_setting( 'disable_on_mobile', 'no' ) === 'yes' ),
				);
				return 'window.upwTextCfg=' . wp_json_encode( $cfg ) . ';';
			},
		) );
	}
	unset( $upw_text_ext );
}

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
