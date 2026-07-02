<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

// Background color uses the Styling-tab preset selector (predefined-colors-color-picker-
// compact) so it stays tied to the theme palette; view.php resolves it to a hex. Falls
// back to a plain color-picker when the shortcodes helper isn't available.
$upw_model_color = function ( $label, $hex, $desc = '' ) {
	if ( function_exists( 'sc_color_field_compact' ) ) {
		$args = [ 'label' => $label, 'kind' => 'bg', 'value' => [ 'predefined' => '', 'custom' => $hex ] ];
		if ( $desc !== '' ) {
			$args['desc'] = $desc;
		}
		return sc_color_field_compact( $args );
	}
	$f = [ 'type' => 'color-picker', 'label' => $label, 'value' => $hex ];
	if ( $desc !== '' ) {
		$f['desc'] = $desc;
	}
	return $f;
};

$options = [

	/* =============================== MODEL =============================== */
	'tab_model' => [
		'title'   => __( 'Model', 'fw' ),
		'type'    => 'tab',
		'options' => [
			'group_model' => [
				'type'    => 'group',
				'options' => [
					'model_url' => [
						'type'  => 'text',
						'label' => __( 'Model URL (.glb / .gltf)', 'fw' ),
						'desc'  => __( 'Direct link to a GLB or glTF file. The most reliable source — paste a URL from your media library or a CDN. GLB (a single self-contained file) is recommended.', 'fw' ),
						'value' => '',
					],
					'model_file' => [
						'type'             => 'upload',
						'images_only'      => false, // a FILE picker (a .glb/.gltf is not an image — avoids the broken-thumbnail preview)
						'files_ext'        => [ 'glb', 'gltf' ],
						'extra_mime_types' => [ 'model/gltf-binary', 'model/gltf+json' ],
						'label'            => __( '…or pick from Media', 'fw' ),
						'desc'             => __( 'Alternative to the URL above — filters the media library to .glb / .gltf files. If both are set, this wins.', 'fw' ),
					],
					'alt' => [
						'type'  => 'text',
						'label' => __( 'Alt text', 'fw' ),
						'desc'  => __( 'Describes the model for screen readers and when it can\'t load.', 'fw' ),
						'value' => '',
					],
					'poster' => [
						'type'  => 'upload',
						'label' => __( 'Poster image', 'fw' ),
						'desc'  => __( 'Shown while the model streams in, and as the fallback when 3D isn\'t supported or “reduce motion” hides motion. Recommended.', 'fw' ),
					],
					'height' => [
						'type'  => 'text',
						'label' => __( 'Height (px)', 'fw' ),
						'desc'  => __( 'Height of the viewer area.', 'fw' ),
						'value' => '520',
					],
				],
			],
		],
	],

	/* ============================== CAMERA ============================== */
	'tab_camera' => [
		'title'   => __( 'Camera', 'fw' ),
		'type'    => 'tab',
		'options' => [
			'group_camera' => [
				'type'    => 'group',
				'options' => [
					'camera_controls' => [
						'type'         => 'switch',
						'label'        => __( 'Let visitors orbit / drag', 'fw' ),
						'desc'         => __( 'Drag to rotate, scroll / pinch to zoom.', 'fw' ),
						'right-choice' => [ 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ],
						'left-choice'  => [ 'value' => 'no', 'label' => __( 'No', 'fw' ) ],
						'value'        => 'yes',
					],
					'disable_zoom' => [
						'type'         => 'switch',
						'label'        => __( 'Disable zoom', 'fw' ),
						'desc'         => __( 'Keep orbit but prevent scroll / pinch zoom (avoids trapping page scroll).', 'fw' ),
						'right-choice' => [ 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ],
						'left-choice'  => [ 'value' => 'no', 'label' => __( 'No', 'fw' ) ],
						'value'        => 'no',
					],
					'auto_rotate' => [
						'type'         => 'switch',
						'label'        => __( 'Auto-rotate', 'fw' ),
						'desc'         => __( 'Slowly spins the model when idle. Disabled automatically under “reduce motion”.', 'fw' ),
						'right-choice' => [ 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ],
						'left-choice'  => [ 'value' => 'no', 'label' => __( 'No', 'fw' ) ],
						'value'        => 'yes',
					],
					'rotation_speed' => [
						'type'       => 'slider',
						'label'      => __( 'Auto-rotate speed (°/sec)', 'fw' ),
						'value'      => 30,
						'properties' => [ 'min' => 0, 'max' => 120, 'step' => 5 ],
					],
					'auto_rotate_delay' => [
						'type'       => 'slider',
						'label'      => __( 'Auto-rotate delay (ms)', 'fw' ),
						'desc'       => __( 'Idle time before auto-rotate resumes after the visitor interacts.', 'fw' ),
						'value'      => 3000,
						'properties' => [ 'min' => 0, 'max' => 10000, 'step' => 250 ],
					],
					'camera_orbit' => [
						'type'    => 'select',
						'label'   => __( 'Starting angle', 'fw' ),
						'value'   => 'three_quarter',
						'choices' => [
							'three_quarter' => __( 'Three-quarter', 'fw' ),
							'front'         => __( 'Front', 'fw' ),
							'side'          => __( 'Side', 'fw' ),
							'top'           => __( 'Top-down', 'fw' ),
						],
					],
					'field_of_view' => [
						'type'    => 'select',
						'label'   => __( 'Field of view', 'fw' ),
						'desc'    => __( 'Narrower feels more “zoomed / flatter”; wider adds perspective.', 'fw' ),
						'value'   => 'auto',
						'choices' => [
							'auto'   => __( 'Auto (fit)', 'fw' ),
							'narrow' => __( 'Narrow (20°)', 'fw' ),
							'normal' => __( 'Normal (30°)', 'fw' ),
							'wide'   => __( 'Wide (45°)', 'fw' ),
						],
					],
					'disable_pan' => [
						'type'         => 'switch',
						'label'        => __( 'Disable pan', 'fw' ),
						'desc'         => __( 'Prevents two-finger / right-drag panning, so the model stays centered (recommended for product shots).', 'fw' ),
						'right-choice' => [ 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ],
						'left-choice'  => [ 'value' => 'no', 'label' => __( 'No', 'fw' ) ],
						'value'        => 'no',
					],
					'min_fov' => [
						'type'  => 'text',
						'label' => __( 'Zoom-in limit (min °)', 'fw' ),
						'desc'  => __( 'Smallest field of view — how far in they can zoom. e.g. 15deg. Blank = no limit.', 'fw' ),
						'value' => '',
					],
					'max_fov' => [
						'type'  => 'text',
						'label' => __( 'Zoom-out limit (max °)', 'fw' ),
						'desc'  => __( 'Largest field of view — how far out they can zoom. e.g. 45deg. Blank = no limit.', 'fw' ),
						'value' => '',
					],
					'min_orbit' => [
						'type'  => 'text',
						'label' => __( 'Min camera orbit', 'fw' ),
						'desc'  => __( 'Advanced — lower orbit bound as “theta phi radius”, e.g. <code>auto 20deg auto</code> stops the camera going over the top. Blank = no limit.', 'fw' ),
						'value' => '',
					],
					'max_orbit' => [
						'type'  => 'text',
						'label' => __( 'Max camera orbit', 'fw' ),
						'desc'  => __( 'Advanced — upper orbit bound as “theta phi radius”, e.g. <code>auto 90deg auto</code> stops the camera dropping below eye level (no underside). Blank = no limit.', 'fw' ),
						'value' => '',
					],
				],
			],
		],
	],

	/* ============================= LIGHTING ============================= */
	'tab_lighting' => [
		'title'   => __( 'Lighting', 'fw' ),
		'type'    => 'tab',
		'options' => [
			'group_lighting' => [
				'type'    => 'group',
				'options' => [
					'environment' => [
						'type'    => 'select',
						'label'   => __( 'Environment lighting', 'fw' ),
						'desc'    => __( 'Image-based lighting that reflects off the model. “Neutral” suits most PBR models.', 'fw' ),
						'value'   => 'neutral',
						'choices' => [
							'neutral' => __( 'Neutral (default)', 'fw' ),
							'legacy'  => __( 'Legacy (warmer)', 'fw' ),
							'custom'  => __( 'Custom HDR', 'fw' ),
							'none'    => __( 'None', 'fw' ),
						],
					],
					'env_image' => [
						'type'  => 'text',
						'label' => __( 'Custom HDR URL', 'fw' ),
						'desc'  => __( 'A .hdr environment map URL — used only when Environment lighting is “Custom HDR”.', 'fw' ),
						'value' => '',
					],
					'skybox' => [
						'type'         => 'switch',
						'label'        => __( 'Show environment as background', 'fw' ),
						'desc'         => __( 'Use the environment map as an immersive backdrop (skybox). Requires a Custom HDR URL above; ignored otherwise.', 'fw' ),
						'right-choice' => [ 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ],
						'left-choice'  => [ 'value' => 'no', 'label' => __( 'No', 'fw' ) ],
						'value'        => 'no',
					],
					'tone_mapping' => [
						'type'    => 'select',
						'label'   => __( 'Tone mapping', 'fw' ),
						'desc'    => __( 'How bright/HDR colors are mapped to the screen. “Commerce” / “Neutral” suit product shots; “ACES” is filmic.', 'fw' ),
						'value'   => 'auto',
						'choices' => [
							'auto'     => __( 'Auto', 'fw' ),
							'neutral'  => __( 'Neutral', 'fw' ),
							'commerce' => __( 'Commerce', 'fw' ),
							'aces'     => __( 'ACES (filmic)', 'fw' ),
							'agx'      => __( 'AgX', 'fw' ),
						],
					],
					'exposure' => [
						'type'       => 'slider',
						'label'      => __( 'Exposure', 'fw' ),
						'value'      => 1,
						'properties' => [ 'min' => 0, 'max' => 2, 'step' => 0.05 ],
					],
					'shadow_intensity' => [
						'type'       => 'slider',
						'label'      => __( 'Ground shadow', 'fw' ),
						'desc'       => __( 'Soft contact shadow under the model. 0 = none.', 'fw' ),
						'value'      => 0.6,
						'properties' => [ 'min' => 0, 'max' => 1, 'step' => 0.05 ],
					],
					'shadow_softness' => [
						'type'       => 'slider',
						'label'      => __( 'Shadow softness', 'fw' ),
						'value'      => 1,
						'properties' => [ 'min' => 0, 'max' => 1, 'step' => 0.05 ],
					],
				],
			],
		],
	],

	/* ============================= PLAYBACK ============================= */
	'tab_playback' => [
		'title'   => __( 'Playback & AR', 'fw' ),
		'type'    => 'tab',
		'options' => [
			'group_playback' => [
				'type'    => 'group',
				'options' => [
					'animation_autoplay' => [
						'type'         => 'switch',
						'label'        => __( 'Play embedded animation', 'fw' ),
						'desc'         => __( 'If the model has baked animation clips, play them automatically (looping).', 'fw' ),
						'right-choice' => [ 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ],
						'left-choice'  => [ 'value' => 'no', 'label' => __( 'No', 'fw' ) ],
						'value'        => 'no',
					],
					'animation_name' => [
						'type'  => 'text',
						'label' => __( 'Clip name', 'fw' ),
						'desc'  => __( 'Optional. Play a specific named clip; leave blank for the first one.', 'fw' ),
						'value' => '',
					],
					'interaction_prompt' => [
						'type'    => 'select',
						'label'   => __( 'Interaction hint', 'fw' ),
						'desc'    => __( 'The subtle “drag to rotate” finger hint shown when idle.', 'fw' ),
						'value'   => 'auto',
						'choices' => [
							'auto' => __( 'Show when idle', 'fw' ),
							'none' => __( 'Never', 'fw' ),
						],
					],
					'ar' => [
						'type'         => 'switch',
						'label'        => __( 'Enable AR', 'fw' ),
						'desc'         => __( 'Adds a “View in your space” button on supporting phones (WebXR / Scene Viewer / Quick Look).', 'fw' ),
						'right-choice' => [ 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ],
						'left-choice'  => [ 'value' => 'no', 'label' => __( 'No', 'fw' ) ],
						'value'        => 'no',
					],
					'ar_placement' => [
						'type'    => 'select',
						'label'   => __( 'AR placement', 'fw' ),
						'value'   => 'floor',
						'choices' => [
							'floor' => __( 'On the floor', 'fw' ),
							'wall'  => __( 'On a wall', 'fw' ),
						],
					],
					'ar_scale' => [
						'type'    => 'select',
						'label'   => __( 'AR scale', 'fw' ),
						'desc'    => __( '“Fixed” locks the model to its real-world size; “Auto” lets the visitor resize it.', 'fw' ),
						'value'   => 'auto',
						'choices' => [
							'auto'  => __( 'Auto (resizable)', 'fw' ),
							'fixed' => __( 'Fixed (real size)', 'fw' ),
						],
					],
				],
			],
		],
	],

	/* ============================= STYLING ============================= */
	'tab_styling' => [
		'title'   => __( 'Styling', 'fw' ),
		'type'    => 'tab',
		'options' => [
			'group_background' => [
				'type'    => 'group',
				'options' => [
					'background' => [
						'type'    => 'select',
						'label'   => __( 'Background', 'fw' ),
						'value'   => 'transparent',
						'choices' => [
							'transparent' => __( 'Transparent', 'fw' ),
							'solid'       => __( 'Solid color', 'fw' ),
						],
					],
					'bg_color' => $upw_model_color( __( 'Background color', 'fw' ), '#f4f5f7', __( 'Used when Background is “Solid color”.', 'fw' ) ),
				],
			],
			'group_spacings' => [
				'type'    => 'group',
				'options' => [
					'spacing' => [
						'type'  => 'spacing',
						'label' => __( 'Margin & Padding', 'fw' ),
						'help'  => function_exists( 'sc_styling_help_text' ) ? sc_styling_help_text( 'spacing' ) : '',
					],
				],
			],
		],
	],

	'tab_animation' => [
		'title'   => __( 'Animations', 'fw' ),
		'type'    => 'tab',
		'options' => function_exists( 'sc_get_animation_fields' ) ? sc_get_animation_fields() : [],
	],

	'tab_advanced' => [
		'title'   => __( 'Advanced', 'fw' ),
		'type'    => 'tab',
		'options' => [
			'advanced_settings' => [
				'type'    => 'group',
				'options' => function_exists( 'sc_get_advanced_tab' ) ? sc_get_advanced_tab() : [],
			],
		],
	],
];
