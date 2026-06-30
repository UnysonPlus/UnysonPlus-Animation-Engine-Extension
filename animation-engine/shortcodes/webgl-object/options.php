<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

// Color fields use the Styling-tab preset selector (predefined-colors-color-picker-
// compact) so WebGL tints stay tied to the theme palette; view.php resolves the
// preset/custom value to a hex (Three.js can't read a CSS var). Falls back to a plain
// color-picker if the shortcodes helper isn't available.
$upw_webgl_color = function ( $label, $hex, $desc = '' ) {
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

	/* ============================== OBJECT ============================== */
	'tab_object' => [
		'title'   => __( 'Object', 'fw' ),
		'type'    => 'tab',
		'options' => [
			'group_object' => [
				'type'    => 'group',
				'options' => [
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
								'desc'    => __( 'The look of the WebGL element — a 3D object or a full-screen shader.', 'fw' ),
								'choices' => [
									[
										'attr'    => [ 'label' => __( '3D Objects', 'fw' ) ],
										'choices' => [
											'glass'     => __( 'Glass Blob', 'fw' ),
											'metal'     => __( 'Liquid Metal', 'fw' ),
											'sphere'    => __( 'Distorted Sphere', 'fw' ),
											'particles' => __( 'Particle Field', 'fw' ),
										],
									],
									[
										'attr'    => [ 'label' => __( 'Shaders (full-screen)', 'fw' ) ],
										'choices' => [
											'gradient_mesh' => __( 'Gradient Mesh', 'fw' ),
											'plasma'        => __( 'Plasma', 'fw' ),
											'aurora'        => __( 'Aurora', 'fw' ),
											'fluid'         => __( 'Fluid (pointer-reactive)', 'fw' ),
											'dots'          => __( 'Dot Matrix / Halftone', 'fw' ),
											'image_distort' => __( 'Image Distortion', 'fw' ),
										],
									],
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

							/* ---- Full-screen shader presets ---- */
							'gradient_mesh' => [
								'blend_speed' => [
									'type'       => 'slider',
									'label'      => __( 'Blend speed', 'fw' ),
									'value'      => 0.4,
									'properties' => [ 'min' => 0, 'max' => 1, 'step' => 0.05 ],
								],
								'grain' => [
									'type'       => 'slider',
									'label'      => __( 'Grain', 'fw' ),
									'value'      => 0.15,
									'properties' => [ 'min' => 0, 'max' => 1, 'step' => 0.05 ],
								],
							],
							'plasma' => [
								'scale' => [
									'type'       => 'slider',
									'label'      => __( 'Scale', 'fw' ),
									'value'      => 3,
									'properties' => [ 'min' => 1, 'max' => 8, 'step' => 0.5 ],
								],
								'flow_speed' => [
									'type'       => 'slider',
									'label'      => __( 'Flow speed', 'fw' ),
									'value'      => 0.5,
									'properties' => [ 'min' => 0, 'max' => 1, 'step' => 0.05 ],
								],
								'contrast' => [
									'type'       => 'slider',
									'label'      => __( 'Contrast', 'fw' ),
									'value'      => 0.6,
									'properties' => [ 'min' => 0, 'max' => 1, 'step' => 0.05 ],
								],
							],
							'aurora' => [
								'band_count' => [
									'type'       => 'slider',
									'label'      => __( 'Bands', 'fw' ),
									'value'      => 3,
									'properties' => [ 'min' => 1, 'max' => 6, 'step' => 1 ],
								],
								'drift_speed' => [
									'type'       => 'slider',
									'label'      => __( 'Drift speed', 'fw' ),
									'value'      => 0.4,
									'properties' => [ 'min' => 0, 'max' => 1, 'step' => 0.05 ],
								],
								'softness' => [
									'type'       => 'slider',
									'label'      => __( 'Softness', 'fw' ),
									'value'      => 0.5,
									'properties' => [ 'min' => 0, 'max' => 1, 'step' => 0.05 ],
								],
							],
							'fluid' => [
								'viscosity' => [
									'type'       => 'slider',
									'label'      => __( 'Viscosity', 'fw' ),
									'value'      => 0.5,
									'properties' => [ 'min' => 0, 'max' => 1, 'step' => 0.05 ],
								],
								'splat_strength' => [
									'type'       => 'slider',
									'label'      => __( 'Pointer strength', 'fw' ),
									'value'      => 0.6,
									'properties' => [ 'min' => 0, 'max' => 1, 'step' => 0.05 ],
								],
							],
							'dots' => [
								'dot_style' => [
									'type'    => 'select',
									'label'   => __( 'Style', 'fw' ),
									'value'   => 'dot',
									'choices' => [
										'dot'      => __( 'Dot matrix', 'fw' ),
										'halftone' => __( 'Halftone', 'fw' ),
									],
								],
								'grid_density' => [
									'type'       => 'slider',
									'label'      => __( 'Density', 'fw' ),
									'value'      => 40,
									'properties' => [ 'min' => 10, 'max' => 120, 'step' => 5 ],
								],
								'dot_size' => [
									'type'       => 'slider',
									'label'      => __( 'Dot size', 'fw' ),
									'value'      => 0.5,
									'properties' => [ 'min' => 0.1, 'max' => 1, 'step' => 0.05 ],
								],
							],
							'image_distort' => [
								'image' => [
									'type'  => 'upload',
									'label' => __( 'Image', 'fw' ),
									'desc'  => __( 'The image to ripple / displace.', 'fw' ),
								],
								'strength' => [
									'type'       => 'slider',
									'label'      => __( 'Strength', 'fw' ),
									'value'      => 0.3,
									'properties' => [ 'min' => 0, 'max' => 1, 'step' => 0.05 ],
								],
								'hover_only' => [
									'type'         => 'switch',
									'label'        => __( 'Distort on hover only', 'fw' ),
									'right-choice' => [ 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ],
									'left-choice'  => [ 'value' => 'no',  'label' => __( 'No', 'fw' ) ],
									'value'        => 'yes',
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
					// Placement sits last: pick the look first, then how it sits on the
					// page. A multi-picker so Height appears ONLY for an inline element —
					// in Section-background mode the parent Section's Min Height sizes it.
					'placement' => [
						'type'         => 'multi-picker',
						'label'        => false,
						'desc'         => false,
						'show_borders' => false,
						'value'        => [ 'mode' => 'inline' ],
						'picker'       => [
							'mode' => [
								'type'    => 'select',
								'label'   => __( 'Placement', 'fw' ),
								'desc'    => __( 'Inline = a normal element in the column (set its Height below). Section background = the canvas fills the parent Section and sits behind its content — drop this element into that Section, size it with the Section\'s Min Height, then add your heading / button in the same Section.', 'fw' ),
								'choices' => [
									'inline'     => __( 'Inline element', 'fw' ),
									'background' => __( 'Section background', 'fw' ),
								],
							],
						],
						'choices' => [
							'inline' => [
								'height' => [
									'type'  => 'text',
									'label' => __( 'Height (px)', 'fw' ),
									'desc'  => __( 'Height of the canvas area.', 'fw' ),
									'value' => '520',
								],
							],
						// 'background' reveals nothing — the Section owns its height.
						],
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
					'color_a' => $upw_webgl_color( __( 'Primary color', 'fw' ), '#6aa6ff' ),
					'color_b' => $upw_webgl_color( __( 'Secondary color', 'fw' ), '#b388ff', __( 'Drives the reflections / environment tint and the gradient background.', 'fw' ) ),
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
					'bg_color' => $upw_webgl_color( __( 'Solid background color', 'fw' ), '#0b0f1a', __( 'Used when Background is “Solid color”.', 'fw' ) ),
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
