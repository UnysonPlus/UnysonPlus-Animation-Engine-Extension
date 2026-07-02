<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

$options = array(
	'tab_content' => array(
		'title'   => __( 'Frames', 'fw' ),
		'type'    => 'tab',
		'options' => array(
			'group_frames' => array(
				'type'    => 'group',
				'options' => array(
					// Canonical multi-picker: top-level label/desc false; label lives on the picker.
					'frames_source' => array(
						'type'         => 'multi-picker',
						'label'        => false,
						'desc'         => false,
						'show_borders' => false,
						'value'        => array( 'source' => 'upload' ),
						'picker'       => array(
							'source' => array(
								'type'    => 'select',
								'label'   => __( 'Frames from', 'fw' ),
								'desc'    => __( 'Where the sequence frames come from. A URL pattern is best for long (100+) sequences; uploads are easiest for short ones.', 'fw' ),
								'choices' => array(
									'upload'  => __( 'Uploaded frames', 'fw' ),
									'pattern' => __( 'Numbered URL pattern', 'fw' ),
								),
							),
						),
						'choices' => array(
							'upload' => array(
								'frames' => array(
									'label'       => __( 'Frames', 'fw' ),
									'desc'        => __( 'Upload the frames in order (drag to reorder). Use evenly-sized images.', 'fw' ),
									'type'        => 'multi-upload',
									'images_only' => true,
								),
							),
							'pattern' => array(
								'url_pattern' => array(
									'type'  => 'text',
									'label' => __( 'URL pattern', 'fw' ),
									'desc'  => __( 'Use %d where the frame number goes, e.g. https://site.com/seq/frame_%d.jpg', 'fw' ),
									'value' => '',
								),
								'count' => array(
									'type'         => 'number',
									'label'        => __( 'Frame count', 'fw' ),
									'value'        => 60,
									'min'          => 1,
									'step'         => 1,
									'numeric_type' => 'integer',
								),
								'start' => array(
									'type'         => 'number',
									'label'        => __( 'Start number', 'fw' ),
									'desc'         => __( 'The first frame number (often 0 or 1).', 'fw' ),
									'value'        => 1,
									'min'          => 0,
									'step'         => 1,
									'numeric_type' => 'integer',
								),
								'pad' => array(
									'type'         => 'number',
									'label'        => __( 'Zero-pad digits', 'fw' ),
									'desc'         => __( '0 = none. e.g. 4 makes frame_0007.jpg.', 'fw' ),
									'value'        => 0,
									'min'          => 0,
									'max'          => 8,
									'step'         => 1,
									'numeric_type' => 'integer',
								),
							),
						),
					),
				),
			),
		),
	),

	'tab_playback' => array(
		'title'   => __( 'Playback', 'fw' ),
		'type'    => 'tab',
		'options' => array(
			'group_playback' => array(
				'type'    => 'group',
				'options' => array(
					'mode' => array(
						'type'    => 'select',
						'label'   => __( 'Mode', 'fw' ),
						'desc'    => __( '"Pin & scrub" holds the sequence full-screen while it plays with scroll; "Scrub in view" plays it as the element passes the viewport.', 'fw' ),
						'value'   => 'pin',
						'choices' => array(
							'pin'    => __( 'Pin & scrub (full-screen)', 'fw' ),
							'inview' => __( 'Scrub while in view', 'fw' ),
						),
					),
					'pin_length' => array(
						'type'       => 'slider',
						'label'      => __( 'Scroll length (screens)', 'fw' ),
						'desc'       => __( 'How much scrolling the sequence spans while pinned. Higher = slower scrub.', 'fw' ),
						'value'      => 2,
						'properties' => array( 'min' => 1, 'max' => 6, 'step' => 1 ),
					),
					'direction' => array(
						'type'    => 'select',
						'label'   => __( 'Direction', 'fw' ),
						'value'   => 'forward',
						'choices' => array( 'forward' => __( 'Forward', 'fw' ), 'reverse' => __( 'Reverse', 'fw' ) ),
					),
				),
			),
		),
	),

	'tab_style' => array(
		'title'   => __( 'Style', 'fw' ),
		'type'    => 'tab',
		'options' => array(
			'group_style' => array(
				'type'    => 'group',
				'options' => array(
					'fit' => array(
						'type'    => 'select',
						'label'   => __( 'Fit', 'fw' ),
						'value'   => 'cover',
						'choices' => array( 'cover' => __( 'Cover (fill, crop)', 'fw' ), 'contain' => __( 'Contain (letterbox)', 'fw' ) ),
					),
					'height' => array(
						'type'         => 'number',
						'label'        => __( 'Height (px) — "Scrub in view" only', 'fw' ),
						'desc'         => __( 'The element height when Mode is "Scrub in view". Pinned mode is always full-screen.', 'fw' ),
						'value'        => 520,
						'min'          => 160,
						'step'         => 10,
						'numeric_type' => 'integer',
					),
					'bg' => function_exists( 'sc_color_field_compact' )
						? sc_color_field_compact( array( 'label' => __( 'Background', 'fw' ), 'kind' => 'bg', 'value' => array( 'predefined' => '', 'custom' => '#0b0f1a' ) ) )
						: array( 'type' => 'color-picker', 'label' => __( 'Background', 'fw' ), 'value' => '#0b0f1a' ),
				),
			),
		),
	),
);
