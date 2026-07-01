<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

// Colour field helper — the shortcodes Styling-tab preset selector when available.
if ( ! function_exists( 'sc_svg_draw_color' ) ) {
	function sc_svg_draw_color( $label, $kind, $default_hex, $desc = '' ) {
		if ( function_exists( 'sc_color_field_compact' ) ) {
			return sc_color_field_compact( array( 'label' => $label, 'kind' => $kind, 'value' => array( 'predefined' => '', 'custom' => $default_hex ), 'desc' => $desc ) );
		}
		$f = array( 'type' => 'color-picker', 'label' => $label, 'value' => $default_hex );
		if ( $desc !== '' ) { $f['desc'] = $desc; }
		return $f;
	}
}

$options = array(
	'tab_content' => array(
		'title'   => __( 'Content', 'fw' ),
		'type'    => 'tab',
		'options' => array(
			'group_source' => array(
				'type'    => 'group',
				'options' => array(
					'svg' => array(
						'type'         => 'multi-picker',
						'label'        => __( 'SVG source', 'fw' ),
						'desc'         => __( 'Where the artwork comes from. For the cleanest draw, use single-colour outline (stroke) SVGs.', 'fw' ),
						'show_borders' => false,
						'value'        => array( 'source' => 'preset' ),
						'picker'       => array(
							'source' => array(
								'type'    => 'select',
								'label'   => false,
								'choices' => array(
									'preset' => __( 'Built-in preset', 'fw' ),
									'code'   => __( 'Paste SVG code', 'fw' ),
									'upload' => __( 'Upload .svg file', 'fw' ),
								),
							),
						),
						'choices' => array(
							'preset' => array(
								'preset' => array(
									'type'    => 'select',
									'label'   => __( 'Preset', 'fw' ),
									'value'   => 'signature',
									'choices' => array(
										'signature' => __( 'Signature', 'fw' ),
										'underline' => __( 'Underline flourish', 'fw' ),
										'arrow'     => __( 'Arrow', 'fw' ),
										'check'     => __( 'Checkmark', 'fw' ),
										'wave'      => __( 'Wave divider', 'fw' ),
										'star'      => __( 'Star', 'fw' ),
										'heart'     => __( 'Heart', 'fw' ),
										'circle'    => __( 'Circle', 'fw' ),
									),
								),
							),
							'code' => array(
								'code' => array(
									'type'  => 'textarea',
									'label' => __( 'SVG code', 'fw' ),
									'desc'  => __( 'Paste the full <code>&lt;svg&gt;…&lt;/svg&gt;</code> markup. Scripts and event handlers are stripped. Outline (stroke) paths draw best.', 'fw' ),
									'value' => '',
								),
							),
							'upload' => array(
								'file' => array(
									'type'    => 'upload',
									'label'   => __( 'SVG file', 'fw' ),
									'desc'    => __( 'Upload an .svg file — it is inlined so its paths can animate.', 'fw' ),
									'files_ext' => array( 'svg' ),
								),
							),
						),
					),
				),
			),
			'group_draw' => array(
				'type'    => 'group',
				'options' => array(
					'trigger' => array(
						'type'    => 'select',
						'label'   => __( 'Trigger', 'fw' ),
						'value'   => 'view',
						'choices' => array(
							'view'  => __( 'When scrolled into view', 'fw' ),
							'load'  => __( 'On page load', 'fw' ),
							'hover' => __( 'On hover', 'fw' ),
						),
					),
					'duration' => array(
						'type'       => 'slider',
						'label'      => __( 'Draw duration (s)', 'fw' ),
						'value'      => 1.6,
						'properties' => array( 'min' => 0.3, 'max' => 6, 'step' => 0.1 ),
					),
					'stagger' => array(
						'type'       => 'slider',
						'label'      => __( 'Stagger between paths (s)', 'fw' ),
						'value'      => 0.15,
						'properties' => array( 'min' => 0, 'max' => 1, 'step' => 0.05 ),
					),
					'direction' => array(
						'type'    => 'select',
						'label'   => __( 'Direction', 'fw' ),
						'value'   => 'normal',
						'choices' => array( 'normal' => __( 'Normal', 'fw' ), 'reverse' => __( 'Reverse', 'fw' ) ),
					),
					'loop' => array(
						'type'         => 'switch',
						'label'        => __( 'Loop', 'fw' ),
						'desc'         => __( 'Draw, erase and redraw forever.', 'fw' ),
						'value'        => 'no',
						'left-choice'  => array( 'value' => 'no',  'label' => __( 'Off', 'fw' ) ),
						'right-choice' => array( 'value' => 'yes', 'label' => __( 'On', 'fw' ) ),
					),
				),
			),
			'group_stroke' => array(
				'type'    => 'group',
				'options' => array(
					'stroke_width' => array(
						'type'       => 'slider',
						'label'      => __( 'Stroke width (px)', 'fw' ),
						'value'      => 2,
						'properties' => array( 'min' => 1, 'max' => 12, 'step' => 0.5 ),
					),
					'stroke_color' => sc_svg_draw_color( __( 'Stroke color', 'fw' ), 'text', '#2f74e6' ),
					'fill_after' => array(
						'type'         => 'switch',
						'label'        => __( 'Fill after drawing', 'fw' ),
						'desc'         => __( 'Fade in a fill colour once the outline finishes drawing.', 'fw' ),
						'value'        => 'no',
						'left-choice'  => array( 'value' => 'no',  'label' => __( 'Off', 'fw' ) ),
						'right-choice' => array( 'value' => 'yes', 'label' => __( 'On', 'fw' ) ),
					),
					'fill_color' => sc_svg_draw_color( __( 'Fill color', 'fw' ), 'bg', '#2f74e6' ),
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
						'label'      => __( 'Max width (px)', 'fw' ),
						'desc'       => __( 'Constrain the artwork width. 0 = the SVG’s natural size.', 'fw' ),
						'value'      => 320,
						'properties' => array( 'min' => 0, 'max' => 1200, 'step' => 10 ),
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
