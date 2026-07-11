<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Motion Path module: options declaration.
 *
 * The per-element "Motion Path" multi-picker appended to every element's Animations tab (via the
 * shortcodes extension's `sc_animation_fields` filter). The picker chooses the path SHAPE (wave /
 * arc / loop / … / custom); every shape reveals one shared options group (drive mode, timing,
 * direction, easing, start offset, path size, align-to-path). The "custom" shape adds a raw SVG
 * path-data field on top of the shared group.
 */

add_filter( 'sc_animation_fields', function ( $fields ) {
	if ( ! is_array( $fields ) ) {
		return $fields;
	}

	// Shared options for every shape (built once, mapped onto each shape + custom).
	$shared = array(
		'drive' => array(
			'type'    => 'select',
			'label'   => __( 'Drive', 'fw' ),
			'desc'    => __( 'What moves the element along the path.', 'fw' ),
			'value'   => 'scrub',
			'choices' => array(
				'scrub' => __( 'Scroll (scrubbed) — tied to scroll position', 'fw' ),
				'loop'  => __( 'Loop — travels the path forever', 'fw' ),
				'view'  => __( 'On view — plays once when it enters', 'fw' ),
			),
		),
		'duration' => array(
			'type'       => 'slider',
			'label'      => __( 'Duration (s)', 'fw' ),
			'desc'       => __( 'For Loop / On-view. One full pass along the path.', 'fw' ),
			'value'      => 4,
			'properties' => array( 'min' => 0.5, 'max' => 20, 'step' => 0.5 ),
		),
		'path_size' => array(
			'type'       => 'slider',
			'label'      => __( 'Path size (px)', 'fw' ),
			'desc'       => __( 'How large the path is — the box the shape travels within.', 'fw' ),
			'value'      => 300,
			'properties' => array( 'min' => 40, 'max' => 1200, 'step' => 10 ),
		),
		'start_offset' => array(
			'type'       => 'slider',
			'label'      => __( 'Start offset (%)', 'fw' ),
			'desc'       => __( 'Begin part-way along the path.', 'fw' ),
			'value'      => 0,
			'properties' => array( 'min' => 0, 'max' => 100, 'step' => 1 ),
		),
		'direction' => array(
			'type'         => 'switch',
			'label'        => __( 'Reverse', 'fw' ),
			'desc'         => __( 'Travel the path backwards.', 'fw' ),
			'value'        => 'no',
			'left-choice'  => array( 'value' => 'no',  'label' => __( 'Forward', 'fw' ) ),
			'right-choice' => array( 'value' => 'yes', 'label' => __( 'Reverse', 'fw' ) ),
		),
		'align' => array(
			'type'         => 'switch',
			'label'        => __( 'Align to path', 'fw' ),
			'desc'         => __( 'Rotate the element to follow the path tangent — it noses along the curve.', 'fw' ),
			'value'        => 'no',
			'left-choice'  => array( 'value' => 'no',  'label' => __( 'Off', 'fw' ) ),
			'right-choice' => array( 'value' => 'yes', 'label' => __( 'On', 'fw' ) ),
		),
		'easing' => array(
			'type'    => 'select',
			'label'   => __( 'Easing', 'fw' ),
			'desc'    => __( 'For Loop / On-view (Scroll drive stays linear with the scrollbar).', 'fw' ),
			'value'   => 'ease-in-out',
			'choices' => array(
				'linear'      => __( 'Linear', 'fw' ),
				'ease-in'     => __( 'Ease In', 'fw' ),
				'ease-out'    => __( 'Ease Out', 'fw' ),
				'ease-in-out' => __( 'Ease In Out', 'fw' ),
			),
		),
	);

	$ext  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	$base = $ext ? $ext->get_declared_URI( '/modules/motion-path/static/img' ) : '';
	$tile = function ( $file, $label ) use ( $base ) {
		return array(
			'small' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 66 ),
			'large' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 132 ),
			'label' => $label,
		);
	};

	$choices_tiles = array( 'none' => $tile( 'none', __( 'None', 'fw' ) ) );
	$reveal        = array( 'none' => array() );

	foreach ( upw_motion_path_presets() as $key => $preset ) {
		$choices_tiles[ $key ] = $tile( $key, $preset['label'] );
		$reveal[ $key ]        = array( 'group_motion_path_' . $key => array( 'type' => 'group', 'options' => $shared ) );
	}

	// Custom shape — the shared group plus a raw SVG path-data field.
	$choices_tiles['custom'] = $tile( 'custom', __( 'Custom path', 'fw' ) );
	$custom_opts             = array(
		'custom_d' => array(
			'type'  => 'textarea',
			'label' => __( 'SVG path data (d)', 'fw' ),
			'desc'  => __( 'Paste an SVG path "d" in a 0–100 coordinate box, e.g. M0,50 C25,0 75,100 100,50', 'fw' ),
			'value' => 'M0,50 C25,0 75,100 100,50',
		),
	) + $shared;
	$reveal['custom'] = array( 'group_motion_path_custom' => array( 'type' => 'group', 'options' => $custom_opts ) );

	$fields['motion_path'] = array(
		'type'         => 'multi-picker',
		'popover'      => true,
		'label'        => __( 'Motion Path', 'fw' ),
		'desc'         => __( 'Send this element travelling along a path instead of a straight line — scrubbed by scroll, looped, or played on view.', 'fw' ),
		'help'         => __( 'Motion Path (Animation Engine): the element follows an SVG path — a preset shape (wave / arc / loop / S-curve / zigzag / spiral / circle / incline) or your own path data. Drive it by scroll (scrubbed), on a loop, or once on view; optionally rotate it to the path tangent ("Align to path"). Pure SVG geometry + one runtime; honours "reduce motion" (stays put) and loads only on pages that use it.', 'fw' ) . ( function_exists( 'upw_perf_note' ) ? ' ' . upw_perf_note() : '' ),
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
