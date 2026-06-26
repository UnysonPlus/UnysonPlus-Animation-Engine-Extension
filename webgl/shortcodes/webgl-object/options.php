<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

$options = [

	/* ============================== OBJECT ============================== */
	'tab_object' => [
		'title'   => __( 'Object', 'fw' ),
		'type'    => 'tab',
		'options' => [
			'group_object' => [
				'type'    => 'group',
				'options' => [
					'display_mode' => [
						'type'    => 'select',
						'label'   => __( 'Placement', 'fw' ),
						'desc'    => __( 'Inline = a normal element in the column. Section background = the canvas fills the parent Section and sits behind its content (drop this element inside the Section you want it to fill, then add your heading / button in the same Section).', 'fw' ),
						'value'   => 'inline',
						'choices' => [
							'inline'     => __( 'Inline element', 'fw' ),
							'background' => __( 'Section background', 'fw' ),
						],
					],
					'style_preset' => [
						'type'         => 'multi-picker',
						'label'        => false,
						'desc'         => false,
						'show_borders' => false,
						'value'        => [ 'preset' => 'glass' ],
						'picker'       => [
							'preset' => [
								'type'    => 'select',
								'label'   => __( 'Style', 'fw' ),
								'desc'    => __( 'The look of the WebGL object.', 'fw' ),
								'choices' => [
									'glass'     => __( 'Glass Blob', 'fw' ),
									'metal'     => __( 'Liquid Metal', 'fw' ),
									'sphere'    => __( 'Distorted Sphere', 'fw' ),
									'particles' => __( 'Particle Field', 'fw' ),
								],
							],
						],
						'choices' => [
							'glass' => [
								'ior' => [
									'type'       => 'slider',
									'label'      => __( 'Refraction (IOR)', 'fw' ),
									'desc'       => __( 'Index of refraction — how much light bends through the glass.', 'fw' ),
									'value'      => 1.45,
									'properties' => [ 'min' => 1, 'max' => 2.33, 'step' => 0.01 ],
								],
								'iridescence' => [
									'type'       => 'slider',
									'label'      => __( 'Iridescence', 'fw' ),
									'desc'       => __( 'Soap-bubble / oil-slick sheen on the surface.', 'fw' ),
									'value'      => 0.3,
									'properties' => [ 'min' => 0, 'max' => 1, 'step' => 0.05 ],
								],
							],
							'metal' => [
								'metalness' => [
									'type'       => 'slider',
									'label'      => __( 'Metalness', 'fw' ),
									'value'      => 1,
									'properties' => [ 'min' => 0, 'max' => 1, 'step' => 0.05 ],
								],
								'roughness' => [
									'type'       => 'slider',
									'label'      => __( 'Roughness', 'fw' ),
									'value'      => 0.15,
									'properties' => [ 'min' => 0, 'max' => 1, 'step' => 0.05 ],
								],
							],
							'sphere' => [
								'roughness' => [
									'type'       => 'slider',
									'label'      => __( 'Roughness', 'fw' ),
									'value'      => 0.6,
									'properties' => [ 'min' => 0, 'max' => 1, 'step' => 0.05 ],
								],
							],
							'particles' => [
								'particle_count' => [
									'type'       => 'slider',
									'label'      => __( 'Particle count', 'fw' ),
									'value'      => 4000,
									'properties' => [ 'min' => 500, 'max' => 12000, 'step' => 500 ],
								],
								'particle_size' => [
									'type'       => 'slider',
									'label'      => __( 'Particle size', 'fw' ),
									'value'      => 0.02,
									'properties' => [ 'min' => 0.005, 'max' => 0.08, 'step' => 0.005 ],
								],
							],
						],
					],
					'scale' => [
						'type'       => 'slider',
						'label'      => __( 'Object size', 'fw' ),
						'value'      => 1,
						'properties' => [ 'min' => 0.5, 'max' => 1.6, 'step' => 0.05 ],
					],
					'height' => [
						'type'  => 'text',
						'label' => __( 'Height (px)', 'fw' ),
						'desc'  => __( 'Height of the canvas area.', 'fw' ),
						'value' => '520',
					],
				],
			],
		],
	],

	/* ============================ APPEARANCE ============================ */
	'tab_appearance' => [
		'title'   => __( 'Appearance', 'fw' ),
		'type'    => 'tab',
		'options' => [
			'group_appearance' => [
				'type'    => 'group',
				'options' => [
					'color_a' => [
						'type'  => 'color-picker',
						'label' => __( 'Primary color', 'fw' ),
						'value' => '#6aa6ff',
					],
					'color_b' => [
						'type'  => 'color-picker',
						'label' => __( 'Secondary color', 'fw' ),
						'desc'  => __( 'Drives the reflections / environment tint and the gradient background.', 'fw' ),
						'value' => '#b388ff',
					],
					'background' => [
						'type'    => 'select',
						'label'   => __( 'Background', 'fw' ),
						'value'   => 'gradient',
						'choices' => [
							'transparent' => __( 'Transparent', 'fw' ),
							'solid'       => __( 'Solid color', 'fw' ),
							'gradient'    => __( 'Gradient (primary → secondary)', 'fw' ),
						],
					],
					'bg_color' => [
						'type'  => 'color-picker',
						'label' => __( 'Solid background color', 'fw' ),
						'desc'  => __( 'Used when Background is “Solid color”.', 'fw' ),
						'value' => '#0b0f1a',
					],
				],
			],
		],
	],

	/* ============================== MOTION ============================== */
	'tab_motion' => [
		'title'   => __( 'Motion', 'fw' ),
		'type'    => 'tab',
		'options' => [
			'group_motion' => [
				'type'    => 'group',
				'options' => [
					'auto_rotate' => [
						'type'       => 'slider',
						'label'      => __( 'Auto-rotate speed', 'fw' ),
						'value'      => 0.3,
						'properties' => [ 'min' => 0, 'max' => 1, 'step' => 0.05 ],
					],
					'noise_amount' => [
						'type'       => 'slider',
						'label'      => __( 'Wobble amount', 'fw' ),
						'desc'       => __( 'How much the surface breathes / deforms.', 'fw' ),
						'value'      => 0.45,
						'properties' => [ 'min' => 0, 'max' => 1, 'step' => 0.05 ],
					],
					'noise_speed' => [
						'type'       => 'slider',
						'label'      => __( 'Wobble speed', 'fw' ),
						'value'      => 0.5,
						'properties' => [ 'min' => 0, 'max' => 1, 'step' => 0.05 ],
					],
					'scroll_link' => [
						'type'         => 'switch',
						'label'        => __( 'React to scroll', 'fw' ),
						'desc'         => __( 'Subtly rotate / scale the object as the page scrolls.', 'fw' ),
						'right-choice' => [ 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ],
						'left-choice'  => [ 'value' => 'no', 'label' => __( 'No', 'fw' ) ],
						'value'        => 'yes',
					],
				],
			],
		],
	],

	/* ============================ INTERACTION =========================== */
	'tab_interaction' => [
		'title'   => __( 'Interaction', 'fw' ),
		'type'    => 'tab',
		'options' => [
			'group_interaction' => [
				'type'    => 'group',
				'options' => [
					'pointer_follow' => [
						'type'         => 'switch',
						'label'        => __( 'Follow pointer', 'fw' ),
						'right-choice' => [ 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ],
						'left-choice'  => [ 'value' => 'no', 'label' => __( 'No', 'fw' ) ],
						'value'        => 'yes',
					],
					'pointer_strength' => [
						'type'       => 'slider',
						'label'      => __( 'Pointer strength', 'fw' ),
						'value'      => 0.5,
						'properties' => [ 'min' => 0, 'max' => 1, 'step' => 0.05 ],
					],
					'parallax' => [
						'type'       => 'slider',
						'label'      => __( 'Parallax', 'fw' ),
						'value'      => 0.3,
						'properties' => [ 'min' => 0, 'max' => 1, 'step' => 0.05 ],
					],
				],
			],
		],
	],

	/* =========================== PERFORMANCE =========================== */
	'tab_performance' => [
		'title'   => __( 'Performance', 'fw' ),
		'type'    => 'tab',
		'options' => [
			'group_performance' => [
				'type'    => 'group',
				'options' => [
					'quality' => [
						'type'    => 'select',
						'label'   => __( 'Quality', 'fw' ),
						'desc'    => __( '“Auto” lowers detail and drops glass transmission on weak / mobile GPUs.', 'fw' ),
						'value'   => 'auto',
						'choices' => [
							'auto' => __( 'Auto', 'fw' ),
							'high' => __( 'High', 'fw' ),
							'low'  => __( 'Low', 'fw' ),
						],
					],
					'dpr_cap' => [
						'type'    => 'select',
						'label'   => __( 'Pixel-ratio cap', 'fw' ),
						'desc'    => __( 'Caps rendering resolution on high-DPI screens (lower = faster).', 'fw' ),
						'value'   => '2',
						'choices' => [
							'1'   => '1x',
							'1.5' => '1.5x',
							'2'   => '2x',
						],
					],
					'poster' => [
						'type'  => 'upload',
						'label' => __( 'Fallback image', 'fw' ),
						'desc'  => __( 'Shown when WebGL is unavailable or “reduce motion” is on. Recommended.', 'fw' ),
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
