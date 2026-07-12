<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

// Colour field helper — the shortcodes Styling-tab preset selector when available.
if ( ! function_exists( 'sc_svg_morph_color' ) ) {
	function sc_svg_morph_color( $label, $kind, $default_hex, $desc = '' ) {
		if ( function_exists( 'sc_color_field_compact' ) ) {
			return sc_color_field_compact( array( 'label' => $label, 'kind' => $kind, 'value' => array( 'predefined' => '', 'custom' => $default_hex ), 'desc' => $desc ) );
		}
		$f = array( 'type' => 'color-picker', 'label' => $label, 'value' => $default_hex );
		if ( $desc !== '' ) { $f['desc'] = $desc; }
		return $f;
	}
}

// Per-shape tiles for the shape-library picker.
$svg_morph_ext        = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
$svg_morph_shape_base = $svg_morph_ext ? $svg_morph_ext->get_declared_URI( '/shortcodes/svg-morph/static/img/shapes' ) : '';
$svg_morph_shape_tile = function ( $file, $label ) use ( $svg_morph_shape_base ) {
	return array(
		'small' => array( 'src' => $svg_morph_shape_base . '/' . $file . '.svg', 'height' => 58 ),
		'large' => array( 'src' => $svg_morph_shape_base . '/' . $file . '.svg', 'height' => 108 ),
		'label' => $label,
	);
};
$svg_morph_shape_choices = array(
	'blob1'    => $svg_morph_shape_tile( 'blob1',    __( 'Blob 1', 'fw' ) ),
	'blob2'    => $svg_morph_shape_tile( 'blob2',    __( 'Blob 2', 'fw' ) ),
	'blob3'    => $svg_morph_shape_tile( 'blob3',    __( 'Blob 3', 'fw' ) ),
	'circle'   => $svg_morph_shape_tile( 'circle',   __( 'Circle', 'fw' ) ),
	'square'   => $svg_morph_shape_tile( 'square',   __( 'Square', 'fw' ) ),
	'triangle' => $svg_morph_shape_tile( 'triangle', __( 'Triangle', 'fw' ) ),
	'diamond'  => $svg_morph_shape_tile( 'diamond',  __( 'Diamond', 'fw' ) ),
	'pentagon' => $svg_morph_shape_tile( 'pentagon', __( 'Pentagon', 'fw' ) ),
	'hexagon'  => $svg_morph_shape_tile( 'hexagon',  __( 'Hexagon', 'fw' ) ),
	'star'     => $svg_morph_shape_tile( 'star',     __( 'Star', 'fw' ) ),
	'heart'    => $svg_morph_shape_tile( 'heart',    __( 'Heart', 'fw' ) ),
	'droplet'  => $svg_morph_shape_tile( 'droplet',  __( 'Droplet', 'fw' ) ),
);

$options = array(
	'tab_content' => array(
		'title'   => __( 'Content', 'fw' ),
		'type'    => 'tab',
		'options' => array(
			'group_shapes' => array(
				'type'    => 'group',
				'options' => array(
					'shapes_list' => array(
						'type'            => 'addable-popup',
						'label'           => __( 'Shapes', 'fw' ),
						'desc'            => __( 'The element morphs through these shapes in order (add 2 or more; drag to reorder). Each shape carries its own timing — pick it from the library, upload an SVG, or paste a path.', 'fw' ),
						'popup-title'     => __( 'Shape', 'fw' ),
						'add-button-text' => __( 'Add shape', 'fw' ),
						'size'            => 'medium',
						'template'        => '{{ var p = ( pick || {} ); var s = p.source || "library"; if ( s === "custom" ) { print( "Custom path" ); } else if ( s === "upload" ) { print( "Uploaded SVG" ); } else { print( ( p.library && p.library.shape ) ? p.library.shape : "Shape" ); } }}',
						'value'           => array(
							array( 'pick' => array( 'source' => 'library', 'library' => array( 'shape' => 'blob1' ) ), 'morph_dur' => 1.2, 'hold' => 0.6 ),
							array( 'pick' => array( 'source' => 'library', 'library' => array( 'shape' => 'blob2' ) ), 'morph_dur' => 1.2, 'hold' => 0.6 ),
							array( 'pick' => array( 'source' => 'library', 'library' => array( 'shape' => 'blob3' ) ), 'morph_dur' => 1.2, 'hold' => 0.6 ),
						),
						'popup-options'   => array(
							'pick' => array(
								'type'         => 'multi-picker',
								'label'        => false,
								'desc'         => false,
								'show_borders' => false,
								'value'        => array( 'source' => 'library' ),
								'picker'       => array(
									'source' => array(
										'type'    => 'select',
										'label'   => __( 'Shape source', 'fw' ),
										'choices' => array(
											'library' => __( 'Shape library', 'fw' ),
											'upload'  => __( 'Upload / paste SVG', 'fw' ),
											'custom'  => __( 'Custom path', 'fw' ),
										),
									),
								),
								'choices' => array(
									'library' => array(
										'shape' => array(
											'type'    => 'image-picker',
											'label'   => __( 'Shape', 'fw' ),
											'value'   => 'blob1',
											'choices' => $svg_morph_shape_choices,
										),
									),
									'upload' => array(
										'markup' => array(
											'type'  => 'svg-code',
											'label' => __( 'SVG file or code', 'fw' ),
											'desc'  => __( 'Upload an .svg or paste <svg>…</svg> — the main outline is extracted and auto-fitted. Best with a single-shape silhouette; complex multi-part art won’t morph cleanly.', 'fw' ),
										),
									),
									'custom' => array(
										'd' => array(
											'type'  => 'textarea',
											'label' => __( 'SVG path (d)', 'fw' ),
											'desc'  => __( 'Advanced — paste one path “d”. Any coordinate space; it’s auto-fitted. Single closed shape.', 'fw' ),
											'value' => '',
										),
									),
								),
							),
							'morph_dur' => array(
								'type'       => 'slider',
								'label'      => __( 'Morph duration (s)', 'fw' ),
								'desc'       => __( 'How long the morph from this shape to the next takes.', 'fw' ),
								'value'      => 1.2,
								'properties' => array( 'min' => 0.2, 'max' => 8, 'step' => 0.1 ),
							),
							'hold' => array(
								'type'       => 'slider',
								'label'      => __( 'Hold before morphing (s)', 'fw' ),
								'desc'       => __( 'Loop trigger only — how long this shape holds before it morphs to the next.', 'fw' ),
								'value'      => 0.6,
								'properties' => array( 'min' => 0, 'max' => 6, 'step' => 0.1 ),
							),
						),
					),
					'loopback' => array(
						'type'         => 'switch',
						'label'        => __( 'Loop back to first', 'fw' ),
						'desc'         => __( 'Morph from the last shape back to the first for a seamless cycle.', 'fw' ),
						'value'        => 'yes',
						'left-choice'  => array( 'value' => 'no',  'label' => __( 'Off', 'fw' ) ),
						'right-choice' => array( 'value' => 'yes', 'label' => __( 'On', 'fw' ) ),
					),
				),
			),
			'group_motion' => array(
				'type'    => 'group',
				'options' => array(
					'render_mode' => array(
						'type'    => 'select',
						'label'   => __( 'Render as', 'fw' ),
						'value'   => 'fill',
						'choices' => array( 'fill' => __( 'Filled shape', 'fw' ), 'stroke' => __( 'Outline (stroke)', 'fw' ) ),
					),
					'trigger' => array(
						'type'    => 'select',
						'label'   => __( 'Trigger', 'fw' ),
						'value'   => 'loop',
						'choices' => array(
							'loop'  => __( 'Loop — morph forever', 'fw' ),
							'hover' => __( 'On hover — morph to the next shape', 'fw' ),
							'view'  => __( 'On view — morph once when scrolled in', 'fw' ),
							'click' => __( 'On click — morph on each click', 'fw' ),
						),
					),
					'easing' => array(
						'type'    => 'select',
						'label'   => __( 'Easing', 'fw' ),
						'value'   => 'ease-in-out',
						'choices' => array(
							'linear'      => __( 'Linear', 'fw' ),
							'ease-in'     => __( 'Ease In', 'fw' ),
							'ease-out'    => __( 'Ease Out', 'fw' ),
							'ease-in-out' => __( 'Ease In Out', 'fw' ),
						),
					),
				),
			),
			'group_paint' => array(
				'type'    => 'group',
				'options' => array(
					'fill_color'   => sc_svg_morph_color( __( 'Fill color', 'fw' ), 'bg', '#2f74e6', __( 'Used in Filled mode.', 'fw' ) ),
					'stroke_color' => sc_svg_morph_color( __( 'Stroke color', 'fw' ), 'text', '#2f74e6', __( 'Used in Outline mode.', 'fw' ) ),
					'stroke_width' => array(
						'type'       => 'slider',
						'label'      => __( 'Stroke width (px)', 'fw' ),
						'value'      => 3,
						'properties' => array( 'min' => 1, 'max' => 16, 'step' => 0.5 ),
					),
				),
			),
		),
	),
	'tab_style' => array(
		'title'   => __( 'Style', 'fw' ),
		'type'    => 'tab',
		'options' => array(
			'group_size' => array(
				'type'    => 'group',
				'options' => array(
					'max_width' => array(
						'type'       => 'slider',
						'label'      => __( 'Size (px)', 'fw' ),
						'desc'       => __( 'The morphing shape is square; this is its width/height.', 'fw' ),
						'value'      => 200,
						'properties' => array( 'min' => 40, 'max' => 900, 'step' => 10 ),
					),
					'align' => array(
						'type'    => 'select',
						'label'   => __( 'Alignment', 'fw' ),
						'value'   => 'center',
						'choices' => array( 'left' => __( 'Left', 'fw' ), 'center' => __( 'Center', 'fw' ), 'right' => __( 'Right', 'fw' ) ),
					),
				),
			),
		),
	),
	'tab_animation' => array(
		'title'   => __( 'Animations', 'fw' ),
		'type'    => 'tab',
		'options' => function_exists( 'sc_get_animation_fields' ) ? sc_get_animation_fields() : array(),
	),
	'tab_advanced' => array(
		'title'   => __( 'Advanced', 'fw' ),
		'type'    => 'tab',
		'options' => array(
			'advanced_settings' => array(
				'type'    => 'group',
				'options' => function_exists( 'sc_get_advanced_tab' ) ? sc_get_advanced_tab() : array(),
			),
		),
	),
);
