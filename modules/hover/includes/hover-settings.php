<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Hover module: options declaration.
 *
 * The per-element "Interaction" group appended to every element's Animations tab (via the
 * shortcodes extension's `sc_animation_fields` filter), plus the global on/off sub-tab in
 * Theme Settings → Animations → Interactions (via `upw_anim_engine_module_tabs`). Depends on
 * upw_color_field() from hover-helpers.php.
 */

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
						'none'             => $ix( 'none'            , __( 'None', 'fw' ) ),
						'tilt'             => $ix( 'tilt'            , __( '3D Tilt', 'fw' ) ),
						'arrow_slide'      => $ix( 'arrow-slide'     , __( 'Arrow Slide', 'fw' ) ),
						'bg_pan'           => $ix( 'bg-pan'          , __( 'Background Pan', 'fw' ) ),
						'blur'             => $ix( 'blur'            , __( 'Blur Focus', 'fw' ) ),
						'border_draw'      => $ix( 'border-draw'     , __( 'Border Draw', 'fw' ) ),
						'bounce'           => $ix( 'bounce'          , __( 'Bounce', 'fw' ) ),
						'brightness'       => $ix( 'brightness'      , __( 'Brightness', 'fw' ) ),
						'color_shift'      => $ix( 'color-shift'     , __( 'Color Shift', 'fw' ) ),
						'corner_brackets'  => $ix( 'corner-brackets' , __( 'Corner Brackets', 'fw' ) ),
						'blob'             => $ix( 'blob'            , __( 'Cursor Blob', 'fw' ) ),
						'cursor_trail'     => $ix( 'cursor-trail'    , __( 'Cursor Trail', 'fw' ) ),
						'depth_layers'     => $ix( 'depth-layers'    , __( 'Depth Layers', 'fw' ) ),
						'fill_sweep'       => $ix( 'fill-sweep'      , __( 'Fill Sweep', 'fw' ) ),
						'flashlight'       => $ix( 'flashlight'      , __( 'Flashlight', 'fw' ) ),
						'glitch'           => $ix( 'glitch'          , __( 'Glitch', 'fw' ) ),
						'glow_border'      => $ix( 'glow-border'     , __( 'Glow Border', 'fw' ) ),
						'gradient_border'  => $ix( 'gradient-border' , __( 'Gradient Border', 'fw' ) ),
						'grayscale'        => $ix( 'grayscale'       , __( 'Grayscale', 'fw' ) ),
						'image_reveal'     => $ix( 'image-reveal'    , __( 'Image Reveal', 'fw' ) ),
						'letter_spacing'   => $ix( 'letter-spacing'  , __( 'Letter Spacing', 'fw' ) ),
						'lift'             => $ix( 'lift'            , __( 'Lift', 'fw' ) ),
						'goo'              => $ix( 'goo'             , __( 'Liquid Goo', 'fw' ) ),
						'magnetic'         => $ix( 'magnetic'        , __( 'Magnetic', 'fw' ) ),
						'magnetic_letters' => $ix( 'magnetic-letters', __( 'Magnetic Letters', 'fw' ) ),
						'marching_ants'    => $ix( 'marching-ants'   , __( 'Marching Ants', 'fw' ) ),
						'outline'          => $ix( 'outline'         , __( 'Outline Expand', 'fw' ) ),
						'peel'             => $ix( 'peel'            , __( 'Peel Corner', 'fw' ) ),
						'jelly'            => $ix( 'jelly'           , __( 'Pop / Jelly', 'fw' ) ),
						'pulse'            => $ix( 'pulse'           , __( 'Pulse', 'fw' ) ),
						'push'             => $ix( 'push'            , __( 'Push', 'fw' ) ),
						'ripple'           => $ix( 'ripple'          , __( 'Ripple', 'fw' ) ),
						'rotate'           => $ix( 'rotate'          , __( 'Rotate', 'fw' ) ),
						'scale'            => $ix( 'scale'           , __( 'Scale / Zoom', 'fw' ) ),
						'shake'            => $ix( 'shake'           , __( 'Shake / Buzz', 'fw' ) ),
						'shine'            => $ix( 'shine'           , __( 'Shine Sweep', 'fw' ) ),
						'shockwave'        => $ix( 'shockwave'       , __( 'Shockwave', 'fw' ) ),
						'skew'             => $ix( 'skew'            , __( 'Skew', 'fw' ) ),
						'spotlight'        => $ix( 'spotlight'       , __( 'Spotlight', 'fw' ) ),
						'squash'           => $ix( 'squash'          , __( 'Squash & Stretch', 'fw' ) ),
						'text_scramble'    => $ix( 'text-scramble'   , __( 'Text Scramble', 'fw' ) ),
						'text_swap'        => $ix( 'text-swap'       , __( 'Text Swap', 'fw' ) ),
						'underline_grow'   => $ix( 'underline-grow'  , __( 'Underline Grow', 'fw' ) ),
						'webgl_displace'   => $ix( 'webgl-displace'  , __( 'WebGL Refract', 'fw' ) ),
				),
			),
		),
		'choices' => array(
			'magnetic' => array(
				'mode' => array( 'type' => 'select', 'label' => __( 'Mode', 'fw' ), 'value' => 'pull', 'choices' => array( 'pull' => __( 'Pull toward cursor', 'fw' ), 'push' => __( 'Push away', 'fw' ) ) ),
				'strength' => array(
					'type'       => 'slider',
					'label'      => __( 'Strength', 'fw' ),
					'desc'       => __( 'How far the element is pulled toward (or pushed from) the cursor.', 'fw' ),
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
				'invert' => array(
					'type'         => 'switch',
					'label'        => __( 'Invert', 'fw' ),
					'desc'         => __( 'Tilt away from the cursor instead of toward it.', 'fw' ),
					'value'        => 'no',
					'left-choice'  => array( 'value' => 'no',  'label' => __( 'Off', 'fw' ) ),
					'right-choice' => array( 'value' => 'yes', 'label' => __( 'On', 'fw' ) ),
				),
			),
			'spotlight' => array(
				'style' => array(
					'type'    => 'select',
					'label'   => __( 'Style', 'fw' ),
					'desc'    => __( 'Glow: a soft light follows the cursor. Gradient tint: a 2-colour gradient follows it.', 'fw' ),
					'value'   => 'glow',
					'choices' => array( 'glow' => __( 'Glow', 'fw' ), 'gradient' => __( 'Gradient tint', 'fw' ) ),
				),
				'glow_color' => upw_color_field( __( 'Color', 'fw' ), 'bg', '#6aa6ff' ),
				'color_b'    => upw_color_field( __( 'Second color', 'fw' ), 'bg', '#a06bff', __( 'Used by the Gradient tint style.', 'fw' ) ),
				'glow_size' => array(
					'type'       => 'slider',
					'label'      => __( 'Size (%)', 'fw' ),
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
							'blur'       => __( 'Blur → sharp', 'fw' ),
							'duotone'    => __( 'Duotone → color', 'fw' ),
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
				'mode' => array( 'type' => 'select', 'label' => __( 'Mode', 'fw' ), 'value' => 'steady', 'choices' => array( 'steady' => __( 'Steady', 'fw' ), 'pulse' => __( 'Pulse', 'fw' ) ) ),
			),
			'underline_grow' => array(
				'line_color' => upw_color_field( __( 'Line color', 'fw' ), 'text', '', __( 'Defaults to the text color when left blank.', 'fw' ) ),
				'position' => array(
					'type'    => 'select',
					'label'   => __( 'Line position', 'fw' ),
					'value'   => 'under',
					'choices' => array(
						'under'   => __( 'Underline', 'fw' ),
						'over'    => __( 'Overline', 'fw' ),
						'through' => __( 'Strikethrough', 'fw' ),
					),
				),
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
				'origin' => array( 'type' => 'select', 'label' => __( 'Ripple from', 'fw' ), 'value' => 'pointer', 'choices' => array( 'pointer' => __( 'Cursor entry point', 'fw' ), 'center' => __( 'Center', 'fw' ) ) ),
			),
			'lift' => array(
				'style' => array( 'type' => 'select', 'label' => __( 'Style', 'fw' ), 'value' => 'lift', 'choices' => array( 'lift' => __( 'Lift', 'fw' ), 'tilt' => __( 'Lift + tilt', 'fw' ), 'sink' => __( 'Sink (press down)', 'fw' ) ) ),
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
				'shift_color' => upw_color_field( __( 'Hover color', 'fw' ), 'bg', '#6aa6ff' ),
				'target' => array( 'type' => 'select', 'label' => __( 'Apply to', 'fw' ), 'value' => 'background', 'choices' => array( 'background' => __( 'Background', 'fw' ), 'text' => __( 'Text', 'fw' ), 'border' => __( 'Border', 'fw' ) ) ),
			),
			'scale' => array(
				'direction' => array( 'type' => 'select', 'label' => __( 'Direction', 'fw' ), 'value' => 'in', 'choices' => array( 'in' => __( 'Zoom in', 'fw' ), 'out' => __( 'Zoom out (shrink)', 'fw' ) ) ),
				'scale_to' => array( 'type' => 'slider', 'label' => __( 'Amount', 'fw' ), 'value' => 1.04, 'properties' => array( 'min' => 1, 'max' => 1.2, 'step' => 0.01 ) ),
			),
			'push' => array(
				'style' => array( 'type' => 'select', 'label' => __( 'Style', 'fw' ), 'value' => 'press', 'choices' => array( 'press' => __( 'Press down', 'fw' ), 'inz' => __( 'Into screen (3D)', 'fw' ) ) ),
				'depth' => array( 'type' => 'slider', 'label' => __( 'Press depth (px)', 'fw' ), 'value' => 5, 'properties' => array( 'min' => 1, 'max' => 12, 'step' => 1 ) ),
			),
			'jelly' => array(
				'strength' => array( 'type' => 'slider', 'label' => __( 'Bounciness', 'fw' ), 'value' => 1, 'properties' => array( 'min' => 0.3, 'max' => 2, 'step' => 0.1 ) ),
			),
			'skew' => array(
				'axis' => array( 'type' => 'select', 'label' => __( 'Axis', 'fw' ), 'value' => 'x', 'choices' => array( 'x' => __( 'Horizontal (X)', 'fw' ), 'y' => __( 'Vertical (Y)', 'fw' ), 'both' => __( 'Both', 'fw' ) ) ),
				'angle' => array( 'type' => 'slider', 'label' => __( 'Skew angle', 'fw' ), 'value' => -6, 'properties' => array( 'min' => -14, 'max' => 14, 'step' => 1 ) ),
			),
			'shine' => array(
				'style' => array(
					'type'    => 'select',
					'label'   => __( 'Style', 'fw' ),
					'desc'    => __( 'Sheen: a single-colour light band sweeps once. Holographic: a rainbow sheen keeps sweeping.', 'fw' ),
					'value'   => 'sheen',
					'choices' => array( 'sheen' => __( 'Sheen', 'fw' ), 'holographic' => __( 'Holographic', 'fw' ) ),
				),
				'shine_color' => upw_color_field( __( 'Shine color', 'fw' ), 'bg', '#ffffff', __( 'Used by the Sheen style.', 'fw' ) ),
				'speed' => array( 'type' => 'slider', 'label' => __( 'Holographic speed (s)', 'fw' ), 'value' => 3, 'properties' => array( 'min' => 1, 'max' => 8, 'step' => 0.5 ) ),
			),
			'gradient_border' => array(
				'color_a' => upw_color_field( __( 'Gradient color A', 'fw' ), 'bg', '#6aa6ff' ),
				'color_b' => upw_color_field( __( 'Gradient color B', 'fw' ), 'bg', '#a06bff' ),
				'speed' => array( 'type' => 'slider', 'label' => __( 'Flow speed (s)', 'fw' ), 'value' => 3, 'properties' => array( 'min' => 1, 'max' => 8, 'step' => 0.5 ) ),
			),
			'corner_brackets' => array(
				'bracket_color' => upw_color_field( __( 'Bracket color', 'fw' ), 'bg', '#6aa6ff' ),
				'style' => array( 'type' => 'select', 'label' => __( 'Style', 'fw' ), 'value' => 'pop', 'choices' => array( 'pop' => __( 'Pop in', 'fw' ), 'draw' => __( 'Draw in', 'fw' ) ) ),
				'bracket_size' => array( 'type' => 'slider', 'label' => __( 'Bracket size (px)', 'fw' ), 'value' => 18, 'properties' => array( 'min' => 8, 'max' => 40, 'step' => 2 ) ),
			),
			'fill_sweep' => array(
				'fill_color' => upw_color_field( __( 'Fill color', 'fw' ), 'bg', '#2f74e6' ),
				'direction' => array( 'type' => 'select', 'label' => __( 'Fill from', 'fw' ), 'value' => 'left', 'choices' => array( 'left' => __( 'Left', 'fw' ), 'right' => __( 'Right', 'fw' ), 'up' => __( 'Bottom', 'fw' ), 'center' => __( 'Center', 'fw' ), 'diagonal' => __( 'Diagonal', 'fw' ) ) ),
			),
			'border_draw' => array(
				'line_color' => upw_color_field( __( 'Line color', 'fw' ), 'bg', '#6aa6ff' ),
				'start' => array( 'type' => 'select', 'label' => __( 'Draw from', 'fw' ), 'value' => 'corner', 'choices' => array( 'corner' => __( 'Corner', 'fw' ), 'center' => __( 'Center out', 'fw' ) ) ),
				'thickness' => array( 'type' => 'slider', 'label' => __( 'Thickness (px)', 'fw' ), 'value' => 2, 'properties' => array( 'min' => 1, 'max' => 6, 'step' => 1 ) ),
			),
			'glitch' => array(
				'style' => array( 'type' => 'select', 'label' => __( 'Style', 'fw' ), 'value' => 'rgb', 'choices' => array( 'rgb' => __( 'RGB split', 'fw' ), 'slice' => __( 'Slice', 'fw' ), 'jitter' => __( 'Jitter', 'fw' ) ) ),
				'strength' => array( 'type' => 'slider', 'label' => __( 'Intensity', 'fw' ), 'value' => 1, 'properties' => array( 'min' => 0.3, 'max' => 2, 'step' => 0.1 ) ),
			),
			'text_swap' => array(
				'swap_text' => array( 'type' => 'text', 'label' => __( 'Swap-to text', 'fw' ), 'desc' => __( 'Revealed on hover. Blank = reuse the original text.', 'fw' ), 'value' => '' ),
				'mode' => array( 'type' => 'select', 'label' => __( 'Transition', 'fw' ), 'value' => 'slide', 'choices' => array( 'slide' => __( 'Slide', 'fw' ), 'fade' => __( 'Fade', 'fw' ), 'flip' => __( '3D flip', 'fw' ) ) ),
				'direction' => array( 'type' => 'select', 'label' => __( 'Slide direction', 'fw' ), 'value' => 'up', 'choices' => array( 'up' => __( 'Up', 'fw' ), 'down' => __( 'Down', 'fw' ) ) ),
			),
			'rotate' => array(
				'style' => array( 'type' => 'select', 'label' => __( 'Style', 'fw' ), 'value' => 'flat', 'choices' => array( 'flat' => __( 'Flat (2D)', 'fw' ), 'flip3d' => __( '3D flip', 'fw' ) ) ),
				'angle' => array( 'type' => 'slider', 'label' => __( 'Rotation', 'fw' ), 'value' => 6, 'properties' => array( 'min' => -45, 'max' => 45, 'step' => 1 ) ),
			),
			'pulse' => array(
				'style' => array( 'type' => 'select', 'label' => __( 'Style', 'fw' ), 'value' => 'scale', 'choices' => array( 'scale' => __( 'Scale', 'fw' ), 'glow' => __( 'Glow ring', 'fw' ), 'opacity' => __( 'Opacity', 'fw' ) ) ),
				'strength' => array( 'type' => 'slider', 'label' => __( 'Pulse size', 'fw' ), 'value' => 1, 'properties' => array( 'min' => 0.3, 'max' => 2, 'step' => 0.1 ) ),
			),
			'shake' => array(
				'style' => array( 'type' => 'select', 'label' => __( 'Style', 'fw' ), 'value' => 'horizontal', 'choices' => array( 'horizontal' => __( 'Horizontal', 'fw' ), 'vertical' => __( 'Vertical', 'fw' ), 'rotate' => __( 'Rotate wobble', 'fw' ) ) ),
				'strength' => array( 'type' => 'slider', 'label' => __( 'Intensity', 'fw' ), 'value' => 1, 'properties' => array( 'min' => 0.3, 'max' => 2, 'step' => 0.1 ) ),
			),
			'bounce' => array(
				'style' => array( 'type' => 'select', 'label' => __( 'Style', 'fw' ), 'value' => 'up', 'choices' => array( 'up' => __( 'Bounce up', 'fw' ), 'drop' => __( 'Drop in', 'fw' ), 'squash' => __( 'Squash land', 'fw' ) ) ),
				'height' => array( 'type' => 'slider', 'label' => __( 'Bounce height (px)', 'fw' ), 'value' => 10, 'properties' => array( 'min' => 4, 'max' => 30, 'step' => 1 ) ),
			),
			'grayscale' => array(
				'filter' => array( 'type' => 'select', 'label' => __( 'Filter', 'fw' ), 'desc' => __( 'The filter applied at rest — it clears on hover.', 'fw' ), 'value' => 'grayscale', 'choices' => array( 'grayscale' => __( 'Grayscale', 'fw' ), 'sepia' => __( 'Sepia', 'fw' ), 'invert' => __( 'Invert', 'fw' ), 'hue' => __( 'Hue shift', 'fw' ), 'saturate' => __( 'Over-saturate', 'fw' ) ) ),
				'amount' => array( 'type' => 'slider', 'label' => __( 'Strength (%)', 'fw' ), 'desc' => __( 'Filtered when idle, normal on hover.', 'fw' ), 'value' => 100, 'properties' => array( 'min' => 20, 'max' => 100, 'step' => 5 ) ),
			),
			'blur' => array(
				'amount' => array( 'type' => 'slider', 'label' => __( 'Blur amount (px)', 'fw' ), 'value' => 4, 'properties' => array( 'min' => 1, 'max' => 12, 'step' => 1 ) ),
				'direction' => array( 'type' => 'select', 'label' => __( 'Blur', 'fw' ), 'value' => 'rest', 'choices' => array( 'rest' => __( 'Blurred at rest → sharp on hover', 'fw' ), 'hover' => __( 'Sharp at rest → blurred on hover', 'fw' ) ) ),
			),
			'brightness' => array(
				'filter' => array( 'type' => 'select', 'label' => __( 'Filter', 'fw' ), 'value' => 'brightness', 'choices' => array( 'brightness' => __( 'Brightness', 'fw' ), 'contrast' => __( 'Contrast', 'fw' ), 'saturate' => __( 'Saturation', 'fw' ) ) ),
				'mode' => array( 'type' => 'select', 'label' => __( 'On hover', 'fw' ), 'value' => 'brighten', 'choices' => array( 'brighten' => __( 'Increase', 'fw' ), 'dim' => __( 'Decrease', 'fw' ) ) ),
				'amount' => array( 'type' => 'slider', 'label' => __( 'Amount (%)', 'fw' ), 'value' => 20, 'properties' => array( 'min' => 5, 'max' => 60, 'step' => 5 ) ),
			),
			'bg_pan' => array(
				'color_a' => upw_color_field( __( 'Gradient color A', 'fw' ), 'bg', '#2f74e6' ),
				'color_b' => upw_color_field( __( 'Gradient color B', 'fw' ), 'bg', '#a06bff' ),
				'angle' => array( 'type' => 'slider', 'label' => __( 'Gradient angle (°)', 'fw' ), 'value' => 120, 'properties' => array( 'min' => 0, 'max' => 360, 'step' => 15 ) ),
				'speed' => array( 'type' => 'slider', 'label' => __( 'Pan speed (s)', 'fw' ), 'value' => 3, 'properties' => array( 'min' => 1, 'max' => 8, 'step' => 0.5 ) ),
			),
			'outline' => array(
				'line_color' => upw_color_field( __( 'Outline color', 'fw' ), 'bg', '#6aa6ff' ),
				'style' => array( 'type' => 'select', 'label' => __( 'Line style', 'fw' ), 'value' => 'solid', 'choices' => array( 'solid' => __( 'Solid', 'fw' ), 'dashed' => __( 'Dashed', 'fw' ), 'double' => __( 'Double', 'fw' ) ) ),
				'offset' => array( 'type' => 'slider', 'label' => __( 'Offset (px)', 'fw' ), 'value' => 6, 'properties' => array( 'min' => 2, 'max' => 20, 'step' => 1 ) ),
				'thickness' => array( 'type' => 'slider', 'label' => __( 'Thickness (px)', 'fw' ), 'value' => 2, 'properties' => array( 'min' => 1, 'max' => 6, 'step' => 1 ) ),
			),
			'letter_spacing' => array(
				'amount' => array( 'type' => 'slider', 'label' => __( 'Extra spacing (px)', 'fw' ), 'value' => 3, 'properties' => array( 'min' => 1, 'max' => 12, 'step' => 1 ) ),
			),
			'webgl_displace' => array(
				'style' => array(
					'type'    => 'select',
					'label'   => __( 'Effect', 'fw' ),
					'desc'    => __( 'Refract = chromatic RGB split; Liquid = flowing displacement; Both = the full effect.', 'fw' ),
					'value'   => 'both',
					'choices' => array(
						'both'    => __( 'Both (refract + liquid)', 'fw' ),
						'refract' => __( 'Refract only', 'fw' ),
						'liquid'  => __( 'Liquid only', 'fw' ),
					),
				),
				'strength' => array( 'type' => 'slider', 'label' => __( 'Displacement', 'fw' ), 'value' => 0.35, 'properties' => array( 'min' => 0.1, 'max' => 1, 'step' => 0.05 ) ),
				'chroma'   => array( 'type' => 'slider', 'label' => __( 'RGB split', 'fw' ), 'value' => 0.4, 'properties' => array( 'min' => 0, 'max' => 1, 'step' => 0.05 ) ),
				'speed'    => array( 'type' => 'slider', 'label' => __( 'Flow speed', 'fw' ), 'value' => 0.6, 'properties' => array( 'min' => 0.2, 'max' => 2, 'step' => 0.1 ) ),
				'trigger'  => array( 'type' => 'select', 'label' => __( 'Runs', 'fw' ), 'value' => 'hover', 'choices' => array( 'hover' => __( 'On hover only', 'fw' ), 'always' => __( 'Always animating', 'fw' ) ) ),
			),
			'goo' => array(
				'speed' => array( 'type' => 'slider', 'label' => __( 'Morph speed (s)', 'fw' ), 'value' => 4, 'properties' => array( 'min' => 1.5, 'max' => 10, 'step' => 0.5 ) ),
			),
			'squash' => array(
				'strength' => array( 'type' => 'slider', 'label' => __( 'Bounciness', 'fw' ), 'value' => 1, 'properties' => array( 'min' => 0.3, 'max' => 2, 'step' => 0.1 ) ),
			),
			'arrow_slide' => array(
				'arrow_color' => upw_color_field( __( 'Arrow color', 'fw' ), 'text', '', __( 'Defaults to the text color when left blank.', 'fw' ) ),
			),
			'depth_layers' => array(
				'strength' => array( 'type' => 'slider', 'label' => __( 'Depth', 'fw' ), 'desc' => __( 'How far the inner elements shift. Best on an element with several children (an icon box, a card).', 'fw' ), 'value' => 1, 'properties' => array( 'min' => 0.3, 'max' => 3, 'step' => 0.1 ) ),
			),
			'marching_ants' => array(
				'line_color' => upw_color_field( __( 'Line color', 'fw' ), 'bg', '#6aa6ff' ),
				'speed' => array( 'type' => 'slider', 'label' => __( 'Speed (s)', 'fw' ), 'value' => 0.5, 'properties' => array( 'min' => 0.2, 'max' => 2, 'step' => 0.1 ) ),
			),
			'flashlight' => array(
				'size' => array( 'type' => 'slider', 'label' => __( 'Torch size (px)', 'fw' ), 'value' => 90, 'properties' => array( 'min' => 40, 'max' => 200, 'step' => 5 ) ),
				'darkness' => array( 'type' => 'slider', 'label' => __( 'Darkness (%)', 'fw' ), 'value' => 82, 'properties' => array( 'min' => 30, 'max' => 95, 'step' => 5 ) ),
			),
			'blob' => array(
				'color' => upw_color_field( __( 'Blob color', 'fw' ), 'bg', '#6aa6ff' ),
				'size' => array( 'type' => 'slider', 'label' => __( 'Blob size (px)', 'fw' ), 'value' => 70, 'properties' => array( 'min' => 30, 'max' => 160, 'step' => 5 ) ),
			),
			'cursor_trail' => array(
				'color' => upw_color_field( __( 'Trail color', 'fw' ), 'bg', '#6aa6ff' ),
				'size' => array( 'type' => 'slider', 'label' => __( 'Dot size (px)', 'fw' ), 'value' => 10, 'properties' => array( 'min' => 4, 'max' => 24, 'step' => 1 ) ),
			),
			'magnetic_letters' => array(
				'strength' => array( 'type' => 'slider', 'label' => __( 'Strength', 'fw' ), 'value' => 1, 'properties' => array( 'min' => 0.3, 'max' => 2, 'step' => 0.1 ) ),
			),
			'shockwave' => array(
				'color' => upw_color_field( __( 'Ring color', 'fw' ), 'bg', '#6aa6ff' ),
			),
			'peel' => array(
				'color' => upw_color_field( __( 'Fold shadow', 'fw' ), 'bg', '' ),
				'size' => array( 'type' => 'slider', 'label' => __( 'Fold size (px)', 'fw' ), 'value' => 22, 'properties' => array( 'min' => 10, 'max' => 50, 'step' => 2 ) ),
			),
		),
	);

	return $fields;
} );
