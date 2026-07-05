<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Parallax module: options declaration.
 *
 * The per-element "Parallax Layers" control appended to every element's Animations tab (via the
 * shortcodes extension's `sc_animation_fields` filter), plus the global on/off sub-tab under
 * Theme Settings → Animations → Parallax (via `upw_anim_engine_module_tabs`). Depends on the
 * helpers.
 */

/* ------------------------------------------------------------------ *
 * 1) The per-element "Parallax Layers" control, appended to Animations.
 * ------------------------------------------------------------------ */
add_filter( 'sc_animation_fields', function ( $fields ) {
	if ( ! is_array( $fields ) ) {
		return $fields;
	}

	$ext  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	$base = $ext ? $ext->get_declared_URI( '/modules/parallax/static/img/roles' ) : '';
	$tile = function ( $file, $label ) use ( $base ) {
		return array(
			'small' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 96 ),
			'large' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 150 ),
			'label' => $label,
		);
	};

	$fields['parallax'] = array(
		'type'         => 'multi-picker',
		'popover'      => true,
		// Popover multi-picker → the user-visible label lives on the TOP level.
		'label'        => __( 'Parallax Layers', 'fw' ),
		'desc'         => __( 'Multi-layer depth parallax. Set a container to Scene, then give each child a Layer depth — they drift at different speeds as the pointer moves (and/or on scroll). A Layer with no Scene tracks the whole window.', 'fw' ),
		'help'         => __( 'Parallax Depth Layers (Animation Engine): pointer- and scroll-driven multi-layer depth. Mark the stage as a Scene; mark each moving element as a Layer and give it a Depth. One shared render loop, no library. Honours "reduce motion" and is skipped on touch for the pointer source (scroll layers still move).', 'fw' ) . ( function_exists( 'upw_perf_note' ) ? ' ' . upw_perf_note() : '' ),
		'show_borders' => false,
		'value'        => array( 'role' => 'none' ),
		'anim_meta'    => array( 'category' => __( 'Scroll', 'fw' ), 'icon' => '&#127748;' ), // 🌄 (Animations-tab inserter)
		'picker'       => array(
			'role' => array(
				'type'    => 'image-picker',
				'label'   => false,
				'desc'    => __( 'Hover a tile to preview it larger.', 'fw' ),
				'value'   => 'none',
				'choices' => array(
					'none'  => $tile( 'none',  __( 'None', 'fw' ) ),
					'scene' => $tile( 'scene', __( 'Scene', 'fw' ) ),
					'layer' => $tile( 'layer', __( 'Layer', 'fw' ) ),
				),
			),
		),
		'choices' => array(
			'none'  => array(),
			'scene' => array(
				'source' => array(
					'type'    => 'select',
					'label'   => __( 'Driven by', 'fw' ),
					'desc'    => __( 'What moves the layers.', 'fw' ),
					'value'   => 'mouse',
					'choices' => array(
						'mouse'  => __( 'Pointer', 'fw' ),
						'scroll' => __( 'Scroll', 'fw' ),
						'both'   => __( 'Pointer + Scroll', 'fw' ),
					),
				),
				'intensity' => upw_prlx_slider( __( 'Intensity (px)', 'fw' ), 40, 8, 140, 2, __( 'How far the deepest layers travel at full pointer / scroll.', 'fw' ) ),
				'smoothing' => upw_prlx_slider( __( 'Smoothing', 'fw' ), 50, 0, 100, 5, __( 'Higher = smoother, more lag as layers ease to the pointer.', 'fw' ) ),
			),
			'layer' => array(
				'depth' => upw_prlx_slider( __( 'Depth', 'fw' ), 30, 0, 100, 1, __( 'How much this layer moves. 0 = fixed; higher = closer / more movement.', 'fw' ) ),
				'axis'  => array(
					'type'    => 'select',
					'label'   => __( 'Axis', 'fw' ),
					'value'   => 'both',
					'choices' => array( 'both' => __( 'Both', 'fw' ), 'x' => __( 'Horizontal only', 'fw' ), 'y' => __( 'Vertical only', 'fw' ) ),
				),
				'direction' => array(
					'type'    => 'select',
					'label'   => __( 'Direction', 'fw' ),
					'desc'    => __( '"Against" moves opposite the pointer — the classic background-recedes feel.', 'fw' ),
					'value'   => 'with',
					'choices' => array( 'with' => __( 'With the pointer', 'fw' ), 'against' => __( 'Against the pointer', 'fw' ) ),
				),
				'scale_far' => upw_prlx_switch( __( 'Scale with depth', 'fw' ), __( 'Deeper layers sit slightly larger, hiding their edges as they move.', 'fw' ) ),
				'blur_far'  => upw_prlx_switch( __( 'Depth blur', 'fw' ), __( 'A subtle blur that grows with depth (depth-of-field).', 'fw' ) ),
			),
		),
	);

	return $fields;
} );

/* ------------------------------------------------------------------ *
 * 4) Global on/off → Theme Settings → Animations → Parallax sub-tab.
 * ------------------------------------------------------------------ */
add_filter( 'upw_anim_engine_module_tabs', function ( $tabs ) {
	$tabs['parallax_layers'] = array(
		'title'   => __( 'Parallax', 'fw' ),
		'type'    => 'tab',
		'options' => array(
			'parallax_box' => array(
				'title'   => __( 'Parallax Depth Layers', 'fw' ),
				'type'    => 'box',
				'options' => array(
					'animation_parallax' => array(
						'type'          => 'multi',
						'label'         => false,
						'inner-options' => array(
							'enable' => array(
								'label'        => __( 'Enable parallax layers', 'fw' ),
								'desc'         => __( 'Master switch for the per-element Parallax Layers. Off = none load anywhere.', 'fw' ),
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
