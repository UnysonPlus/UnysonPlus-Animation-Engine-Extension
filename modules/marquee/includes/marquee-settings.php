<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Marquee module: options declaration.
 *
 * The per-element "Marquee" control appended to the Animations tab (sc_animation_fields), plus the
 * global on/off sub-tab under Theme Settings → Animations (upw_anim_engine_module_tabs). Depends on
 * the helpers (upw_mq_slider).
 */

/* ------------------------------------------------------------------ *
 * 1) The per-element "Marquee" control, appended to the Animations tab.
 * ------------------------------------------------------------------ */
add_filter( 'sc_animation_fields', function ( $fields ) {
	if ( ! is_array( $fields ) ) {
		return $fields;
	}

	// Options shared by every direction (built once, mapped onto each below).
	$opts = array(
		'speed' => array(
			'type'    => 'select',
			'label'   => __( 'Speed', 'fw' ),
			'value'   => 'normal',
			'choices' => array( 'slow' => __( 'Slow', 'fw' ), 'normal' => __( 'Normal', 'fw' ), 'fast' => __( 'Fast', 'fw' ) ),
		),
		'gap' => upw_mq_slider( __( 'Gap (px)', 'fw' ), 40, 0, 200, 4, __( 'Space between each repeat.', 'fw' ) ),
		'separator' => array(
			'type'  => 'text',
			'label' => __( 'Separator', 'fw' ),
			'value' => '',
			'desc'  => __( 'Optional text shown between repeats — e.g. • or —. Leave blank for none.', 'fw' ),
		),
		'pause_on_hover' => array(
			'type'         => 'switch',
			'label'        => __( 'Pause on hover', 'fw' ),
			'value'        => 'yes',
			'left-choice'  => array( 'value' => 'no',  'label' => __( 'No', 'fw' ) ),
			'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
		),
		'edge_fade' => array(
			'type'         => 'switch',
			'label'        => __( 'Fade edges', 'fw' ),
			'desc'         => __( 'Softly fade the content in / out at the container edges.', 'fw' ),
			'value'        => 'no',
			'left-choice'  => array( 'value' => 'no',  'label' => __( 'Off', 'fw' ) ),
			'right-choice' => array( 'value' => 'yes', 'label' => __( 'On', 'fw' ) ),
		),
		'scroll_reactive' => array(
			'type'         => 'switch',
			'label'        => __( 'React to scroll', 'fw' ),
			'desc'         => __( 'Speed up as the visitor scrolls faster (settles back when they stop).', 'fw' ),
			'value'        => 'no',
			'left-choice'  => array( 'value' => 'no',  'label' => __( 'Off', 'fw' ) ),
			'right-choice' => array( 'value' => 'yes', 'label' => __( 'On', 'fw' ) ),
		),
		'draggable' => array(
			'type'         => 'switch',
			'label'        => __( 'Draggable', 'fw' ),
			'desc'         => __( 'Let visitors grab and flick the ticker (with momentum).', 'fw' ),
			'value'        => 'no',
			'left-choice'  => array( 'value' => 'no',  'label' => __( 'Off', 'fw' ) ),
			'right-choice' => array( 'value' => 'yes', 'label' => __( 'On', 'fw' ) ),
		),
		'custom_speed' => array(
			'type'         => 'number',
			'label'        => __( 'Custom speed (px/s)', 'fw' ),
			'desc'         => __( 'Override the preset above. 0 = use the preset.', 'fw' ),
			'value'        => 0,
			'min'          => 0,
			'step'         => 5,
			'numeric_type' => 'integer',
		),
		'text_style' => array(
			'type'    => 'select',
			'label'   => __( 'Text style', 'fw' ),
			'desc'    => __( 'For text content — hollow outline letters.', 'fw' ),
			'value'   => 'normal',
			'choices' => array( 'normal' => __( 'Normal', 'fw' ), 'outline' => __( 'Outline (hollow)', 'fw' ) ),
		),
		'warp_heading' => array(
			'type'  => 'html',
			'label' => false,
			'desc'  => false,
			'html'  => '<h4 style="margin:24px 0 4px;font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#666;">' . esc_html__( 'Warp & Distortion', 'fw' ) . '</h4>',
		),
		'skew_h' => upw_mq_slider( __( 'Skew horizontal', 'fw' ), 0, -100, 100, 1, __( 'Slant the ticker left / right.', 'fw' ) ),
		'skew_v' => upw_mq_slider( __( 'Skew vertical', 'fw' ), 0, -100, 100, 1, __( 'Slant the ticker up / down.', 'fw' ) ),
		'tilt'   => upw_mq_slider( __( 'Tilt (angle)', 'fw' ), 0, -100, 100, 1, __( 'Rotate the whole ticker — an angled banner.', 'fw' ) ),
		'bend'   => upw_mq_slider( __( 'Bend (3D tilt)', 'fw' ), 0, -100, 100, 1, __( 'Tilt the ticker in 3D perspective. Works on any content.', 'fw' ) ),
		'curve'  => upw_mq_slider( __( 'Curve (arc text)', 'fw' ), 0, -100, 100, 1, __( 'Bend the TEXT along a real arc — a true curve (like on a circle). Text content only; overrides Bend for text.', 'fw' ) ),
		'wave'   => upw_mq_slider( __( 'Wave', 'fw' ), 0, 0, 100, 1, __( 'Make the content undulate up / down as it scrolls.', 'fw' ) ),
	);

	// Popover image-picker tiles — animated direction swatches (consistent with the other
	// engine effects). Popover multi-picker → the user-visible label lives on the TOP level.
	$ext  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	$base = $ext ? $ext->get_declared_URI( '/modules/marquee/static/img/directions' ) : '';
	$tile = function ( $file, $label ) use ( $base ) {
		return array(
			'small' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 96 ),
			'large' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 150 ),
			'label' => $label,
		);
	};

	$fields['marquee'] = array(
		'type'         => 'multi-picker',
		'popover'      => true,
		'label'        => __( 'Marquee', 'fw' ),
		'desc'         => __( 'Scroll this element\'s content in a seamless, never-ending loop — a ticker or running-text banner. The content is cloned so the loop has no visible jump. Works best on a heading or text with large type.', 'fw' ),
		'help'         => __( 'Marquee (Animation Engine): a self-running seamless ticker for any element. The content is doubled and translated by exactly one set (no jump), pauses on hover, and honours "reduce motion". Pure CSS animation, no library; loads only on pages that use it.', 'fw' ) . ( function_exists( 'upw_perf_note' ) ? ' ' . upw_perf_note() : '' ),
		'show_borders' => false,
		'value'        => array( 'mode' => 'none' ),
		'anim_meta'    => array( 'category' => __( 'Motion', 'fw' ), 'icon' => '&#127916;' ), // 🎞 (Animations-tab inserter)
		'picker'       => array(
			'mode' => array(
				'type'    => 'image-picker',
				'label'   => false,
				'desc'    => __( 'Hover a tile to preview it larger.', 'fw' ),
				'value'   => 'none',
				'choices' => array(
					'none'  => $tile( 'none',  __( 'None', 'fw' ) ),
					'left'  => $tile( 'left',  __( 'Left', 'fw' ) ),
					'right' => $tile( 'right', __( 'Right', 'fw' ) ),
					'up'    => $tile( 'up',    __( 'Up', 'fw' ) ),
					'down'  => $tile( 'down',  __( 'Down', 'fw' ) ),
				),
			),
		),
		'choices' => array(
			'none'  => array(),
			'left'  => $opts,
			'right' => $opts,
			'up'    => $opts,
			'down'  => $opts,
		),
	);

	return $fields;
} );
