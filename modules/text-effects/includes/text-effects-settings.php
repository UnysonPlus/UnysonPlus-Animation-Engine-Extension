<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Text Effects module: option declarations.
 *
 * The per-element "Text Effect" group appended to every element's Animations tab (via the
 * shortcodes extension's `sc_animation_fields` filter), plus the global on/off sub-tab under
 * Theme Settings → Animations → Text (`upw_anim_engine_module_tabs`). Depends on the helpers.
 */

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
			'small' => array( 'src' => $tx_base . '/' . $file . '.svg', 'height' => 53 ),
			'large' => array( 'src' => $tx_base . '/' . $file . '.svg', 'height' => 132 ),
			'label' => $label,
		);
	};

	// Shared MULTI-SELECT trigger for the one-shot effects (reveal family, scramble, typewriter,
	// countup, splitflap, matrix) — the SAME tiles + tooltip UI as
	// the Entrance Animation / Confetti triggers, so "when does it play" is consistent across the
	// engine. view/load reveal once; click/hover replay. Tiles live in the shortcodes extension.
	$sc_ext        = function_exists( 'fw_ext' ) ? fw_ext( 'shortcodes' ) : null;
	$trig_base     = $sc_ext ? $sc_ext->get_declared_URI( '/static/img/triggers' ) : '';
	$trig_tile     = function ( $key, $label ) use ( $trig_base ) {
		return array( 'small' => array( 'src' => $trig_base . '/' . $key . '.svg', 'height' => 30, 'title' => $label ), 'label' => $label );
	};
	$trigger_multi = array(
		'type'       => 'image-picker',
		'multiple'   => true,
		'show_label' => false,
		'label'      => __( 'Trigger', 'fw' ),
		'desc'       => __( 'When the reveal plays — pick one or more. Scroll into view / Page load reveal the text once; Click / Hover replay it.', 'fw' ),
		'value'      => array( 'view' ),
		'choices'    => array(
			'view'  => $trig_tile( 'view',  __( 'Scroll into view', 'fw' ) ),
			'load'  => $trig_tile( 'load',  __( 'Page load', 'fw' ) ),
			'click' => $trig_tile( 'click', __( 'Click', 'fw' ) ),
			'hover' => $trig_tile( 'hover', __( 'Hover', 'fw' ) ),
		),
	);

	// Single-select trigger image-picker (view / hover) for the effects whose trigger is a choice
	// of ONE, not a combination (marker, strikebox, outline_fill, width_sweep, fill_sweep,
	// color_wave). Same tiles as the multi trigger, so the control is visually consistent across
	// every Text Effect; the value stays a scalar, so render/runtime are unchanged. Not `multiple`
	// — for these effects "view" and "hover" are mutually exclusive.
	$trigger_vh = function ( $default ) use ( $trig_tile ) {
		return array(
			'type'       => 'image-picker',
			'show_label' => false,
			'label'      => __( 'Trigger', 'fw' ),
			'value'      => $default,
			'choices'    => array(
				'view'  => $trig_tile( 'view',  __( 'Scroll into view', 'fw' ) ),
				'hover' => $trig_tile( 'hover', __( 'Hover', 'fw' ) ),
			),
		);
	};

	// Shared option group for the reveal-family effects (split_reveal + Wave-A variants).
	$reveal_group = function ( $split = 'chars', $with_dir = false ) use ( $trigger_multi ) {
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
			'trigger' => $trigger_multi,
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
		'placeholder'  => __( 'None', 'fw' ),
		'anim_meta'    => array( 'category' => __( 'Entrance', 'fw' ), 'icon' => '&#128221;' ), // 📝 (Animations-tab inserter)
		'picker'       => array(
			'effect' => array(
				'type'    => 'image-picker',
				'label'   => false,
				'desc'    => __( 'Hover a tile to preview it larger.', 'fw' ),
				'value'   => 'none',
				'search'  => __( 'Search text effects…', 'fw' ),
				'layout'  => 'tabs',
				'choices' => upw_ae_group_tiles(
					array(
						'none'         => $tx( 'none',         __( 'None', 'fw' ) ),
						'blur'         => $tx( 'blur',         __( 'Blur Reveal', 'fw' ) ),
						'bounce'       => $tx( 'bounce',       __( 'Bounce In', 'fw' ) ),
						'breathing'    => $tx( 'breathing',    __( 'Breathing', 'fw' ) ),
						'chromatic'    => $tx( 'chromatic',    __( 'Chromatic', 'fw' ) ),
						'color_wave'   => $tx( 'color-wave',   __( 'Color Wave', 'fw' ) ),
						'countup'      => $tx( 'countup',      __( 'Count Up', 'fw' ) ),
						'expand_spacing' => $tx( 'expand-spacing', __( 'Expand Spacing', 'fw' ) ),
						'fill_sweep'   => $tx( 'fill-sweep',   __( 'Fill Sweep', 'fw' ) ),
						'flip3d'       => $tx( 'flip3d',       __( 'Flip 3D', 'fw' ) ),
						'float'        => $tx( 'float',        __( 'Float', 'fw' ) ),
						'glitch'       => $tx( 'glitch',       __( 'Glitch', 'fw' ) ),
						'gradient_flow'=> $tx( 'gradient-flow', __( 'Gradient Flow', 'fw' ) ),
						'image_mask'   => $tx( 'image-mask',   __( 'Image Mask', 'fw' ) ),
						'jitter'       => $tx( 'jitter',       __( 'Jitter', 'fw' ) ),
						'kinetic'      => $tx( 'kinetic',      __( 'Kinetic Scroll', 'fw' ) ),
						'letter_jump'  => $tx( 'letter-jump',  __( 'Letter Jump', 'fw' ) ),
						'magnetic'     => $tx( 'magnetic',     __( 'Magnetic Letters', 'fw' ) ),
						'marker'       => $tx( 'marker',       __( 'Marker Highlight', 'fw' ) ),
						'mask'         => $tx( 'mask',         __( 'Mask Reveal', 'fw' ) ),
						'matrix'       => $tx( 'matrix',       __( 'Matrix Decode', 'fw' ) ),
						'neon'         => $tx( 'neon',         __( 'Neon Flicker', 'fw' ) ),
						'outline_fill' => $tx( 'outline-fill', __( 'Outline → Fill', 'fw' ) ),
						'rainbow'      => $tx( 'rainbow',      __( 'Rainbow', 'fw' ) ),
						'random'       => $tx( 'random',       __( 'Random Order', 'fw' ) ),
						'rotating_words' => $tx( 'rotating-words', __( 'Rotating Words', 'fw' ) ),
						'scale'        => $tx( 'scale',        __( 'Scale Pop', 'fw' ) ),
						'scramble'     => $tx( 'scramble',     __( 'Scramble', 'fw' ) ),
						'shimmer'      => $tx( 'shimmer',      __( 'Shimmer', 'fw' ) ),
						'skew'         => $tx( 'skew',         __( 'Skew Reveal', 'fw' ) ),
						'slide'        => $tx( 'slide',        __( 'Slide', 'fw' ) ),
						'split_reveal' => $tx( 'split-reveal', __( 'Split Reveal', 'fw' ) ),
						'splitflap'    => $tx( 'splitflap',    __( 'Split-Flap', 'fw' ) ),
						'strikebox'    => $tx( 'strikebox',    __( 'Strike / Box', 'fw' ) ),
						'typewriter'   => $tx( 'typewriter',   __( 'Typewriter', 'fw' ) ),
						'wave'         => $tx( 'wave',         __( 'Wave', 'fw' ) ),
						'vf_weight'    => $tx( 'vf-weight',    __( 'Weight Sweep', 'fw' ) ),
						'width_sweep'  => $tx( 'width-sweep',  __( 'Width Sweep', 'fw' ) ),
					),
					array(
						'grp_reveal' => array( 'label' => __( 'Reveal', 'fw' ), 'ids' => array( 'blur', 'mask', 'split_reveal', 'slide', 'skew', 'fill_sweep', 'image_mask', 'outline_fill', 'strikebox', 'marker' ) ),
						'grp_motion' => array( 'label' => __( 'Motion', 'fw' ), 'ids' => array( 'bounce', 'float', 'wave', 'jitter', 'letter_jump', 'scale', 'breathing', 'kinetic', 'magnetic' ) ),
						'grp_decode' => array( 'label' => __( 'Decode & Type', 'fw' ), 'ids' => array( 'scramble', 'random', 'matrix', 'typewriter', 'splitflap', 'countup', 'rotating_words' ) ),
						'grp_color' => array( 'label' => __( 'Color & Glow', 'fw' ), 'ids' => array( 'chromatic', 'color_wave', 'gradient_flow', 'neon', 'rainbow', 'shimmer', 'glitch' ) ),
						'grp_type' => array( 'label' => __( 'Type & 3D', 'fw' ), 'ids' => array( 'expand_spacing', 'vf_weight', 'width_sweep', 'flip3d' ) ),
					),
					array( 'none' )
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
				'trigger' => $trigger_multi,
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
				'trigger' => $trigger_multi,
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
				'trigger' => $trigger_multi,
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
					'type'       => 'image-picker',
					'show_label' => false,
					'label'      => __( 'Trigger', 'fw' ),
					'value'      => 'hover',
					'choices'    => array(
						'hover'  => $trig_tile( 'hover',  __( 'Hover', 'fw' ) ),
						'always' => $trig_tile( 'always', __( 'Always', 'fw' ) ),
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
				'trigger' => $trigger_vh( 'hover' ),
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
				'trigger' => $trigger_vh( 'view' ),
			),
			'strikebox' => array(
				'shape'   => array( 'type' => 'select', 'label' => __( 'Shape', 'fw' ), 'value' => 'strike', 'choices' => array( 'strike' => __( 'Strike-through', 'fw' ), 'underline' => __( 'Underline', 'fw' ), 'box' => __( 'Box', 'fw' ) ) ),
				'color'   => upw_text_color_field( __( 'Line color', 'fw' ), 'text', '', __( 'Defaults to the text color when blank.', 'fw' ) ),
				'trigger' => $trigger_vh( 'view' ),
			),
			'outline_fill' => array(
				'color'   => upw_text_color_field( __( 'Fill color', 'fw' ), 'text', '', __( 'Defaults to the text color when blank.', 'fw' ) ),
				'trigger' => $trigger_vh( 'view' ),
			),
			'chromatic' => array(
				'intensity' => array( 'type' => 'slider', 'label' => __( 'Offset (px)', 'fw' ), 'value' => 2, 'properties' => array( 'min' => 1, 'max' => 6, 'step' => 1 ) ),
			),
			'width_sweep' => array(
				'from'    => array( 'type' => 'slider', 'label' => __( 'From width', 'fw' ), 'value' => 75, 'properties' => array( 'min' => 25, 'max' => 200, 'step' => 5 ) ),
				'to'      => array( 'type' => 'slider', 'label' => __( 'To width', 'fw' ), 'value' => 125, 'properties' => array( 'min' => 25, 'max' => 200, 'step' => 5 ) ),
				'trigger' => $trigger_vh( 'hover' ),
			),
			'rotating_words' => array(
				'words'    => array( 'type' => 'text', 'label' => __( 'Words', 'fw' ), 'desc' => __( 'Comma-separated words to cycle through, after the element’s own text. e.g. <em>designer, developer, dreamer</em>', 'fw' ), 'value' => '' ),
				'interval' => array( 'type' => 'slider', 'label' => __( 'Interval (s)', 'fw' ), 'value' => 1.8, 'properties' => array( 'min' => 0.6, 'max' => 5, 'step' => 0.2 ) ),
			),
			'countup' => array(
				'duration' => array( 'type' => 'slider', 'label' => __( 'Duration (s)', 'fw' ), 'value' => 1.6, 'properties' => array( 'min' => 0.5, 'max' => 5, 'step' => 0.1 ) ),
				'trigger'  => $trigger_multi,
			),
			'splitflap' => array(
				'duration' => array( 'type' => 'slider', 'label' => __( 'Duration (s)', 'fw' ), 'value' => 1.4, 'properties' => array( 'min' => 0.4, 'max' => 3, 'step' => 0.1 ) ),
				'trigger'  => $trigger_multi,
			),
			'matrix' => array(
				'duration' => array( 'type' => 'slider', 'label' => __( 'Duration (s)', 'fw' ), 'value' => 1.4, 'properties' => array( 'min' => 0.6, 'max' => 3, 'step' => 0.1 ) ),
				'trigger'  => $trigger_multi,
			),
			'fill_sweep' => array(
				'color'   => upw_text_color_field( __( 'Fill color', 'fw' ), 'text', '#2f74e6' ),
				'trigger' => $trigger_vh( 'hover' ),
			),
			'letter_jump' => array(
				'height' => array( 'type' => 'slider', 'label' => __( 'Jump height (px)', 'fw' ), 'desc' => __( 'Each letter hops on hover.', 'fw' ), 'value' => 6, 'properties' => array( 'min' => 2, 'max' => 18, 'step' => 1 ) ),
			),
			'expand_spacing' => array(
				'amount' => array( 'type' => 'slider', 'label' => __( 'Extra spacing (px)', 'fw' ), 'desc' => __( 'Letter-spacing widens on hover.', 'fw' ), 'value' => 6, 'properties' => array( 'min' => 1, 'max' => 20, 'step' => 1 ) ),
			),
			'color_wave' => array(
				'color'   => upw_text_color_field( __( 'Wave color', 'fw' ), 'text', '#2f74e6' ),
				'trigger' => $trigger_vh( 'hover' ),
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
