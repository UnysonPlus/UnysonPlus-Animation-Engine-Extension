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
						'type'        => 'upload',
						'images_only' => false, // a FILE picker (a .glb/.gltf is not an image — avoids the broken-thumbnail preview)
						'files_ext'   => [ 'glb', 'gltf' ], // filters the media library to these; mime resolved via the engine's mime_types filter
						'label'       => __( '…or pick from Media', 'fw' ),
						'desc'        => __( 'Alternative to the URL above — opens the media library filtered to .glb / .gltf files. If both are set, this wins.', 'fw' ),
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
			'group_variants' => [
				'type'    => 'group',
				'options' => [
					'variants_show' => [
						'type'         => 'switch',
						'label'        => __( 'Material / color switcher', 'fw' ),
						'desc'         => __( 'If the model ships built-in material variants (glTF <code>KHR_materials_variants</code> — e.g. colorways), show a row of swatches so visitors can switch between them.', 'fw' ),
						'right-choice' => [ 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ],
						'left-choice'  => [ 'value' => 'no', 'label' => __( 'No', 'fw' ) ],
						'value'        => 'no',
					],
					'variant_default' => [
						'type'  => 'text',
						'label' => __( 'Default variant', 'fw' ),
						'desc'  => __( 'Optional. The variant name to show first (must match a name baked into the model). Blank = the model\'s default.', 'fw' ),
						'value' => '',
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

	/* ============================= HOTSPOTS ============================= */
	'tab_hotspots' => [
		'title'   => __( 'Hotspots', 'fw' ),
		'type'    => 'tab',
		'options' => [
			'group_hotspots' => [
				'type'    => 'group',
				'options' => [
					'hotspots' => [
						'type'          => 'addable-popup',
						'label'         => __( 'Hotspots', 'fw' ),
						'popup-title'   => __( 'Add / Edit Hotspot', 'fw' ),
						'size'          => 'medium',
						'desc'          => __( 'Pinned callouts on the model (e.g. feature markers). Each needs a 3D <strong>position</strong> — get one by opening your model in the free <a href="https://modelviewer.dev/editor/" target="_blank" rel="noopener noreferrer">model-viewer editor</a>, clicking the surface, and copying the <code>data-position</code> (and optional <code>data-normal</code>) values.', 'fw' ),
						'template'      => '{{=label}}',
						'popup-options' => [
							'label' => [
								'type'  => 'text',
								'label' => __( 'Label', 'fw' ),
								'desc'  => __( 'Short caption shown in the callout.', 'fw' ),
							],
							'detail' => [
								'type'  => 'textarea',
								'label' => __( 'Detail', 'fw' ),
								'desc'  => __( 'Optional longer text under the label.', 'fw' ),
							],
							'link' => [
								'type'  => 'text',
								'label' => __( 'Link URL', 'fw' ),
								'desc'  => __( 'Optional. Makes the callout clickable.', 'fw' ),
							],
							'position' => [
								'type'  => 'text',
								'label' => __( 'Position (x y z)', 'fw' ),
								'desc'  => __( 'Required. e.g. <code>0.1 0.25 0.05</code> — from the model-viewer editor.', 'fw' ),
							],
							'normal' => [
								'type'  => 'text',
								'label' => __( 'Normal (x y z)', 'fw' ),
								'desc'  => __( 'Optional. Lets the hotspot hide when it faces away from the camera.', 'fw' ),
							],
						],
					],
					'hotspot_hide_backside' => [
						'type'         => 'switch',
						'label'        => __( 'Fade hotspots behind the model', 'fw' ),
						'desc'         => __( 'Dim hotspots that rotate to the far side (needs a Normal on each). Off keeps them fully visible.', 'fw' ),
						'right-choice' => [ 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ],
						'left-choice'  => [ 'value' => 'no', 'label' => __( 'No', 'fw' ) ],
						'value'        => 'yes',
					],
				],
			],
		],
	],

	/* ======================= SCROLL CHOREOGRAPHY ======================= */
	'tab_scroll' => [
		'title'   => __( 'Scroll Choreography', 'fw' ),
		'type'    => 'tab',
		'options' => [
			'group_choreo' => [
				'type'    => 'group',
				'options' => [
					'choreo_enable' => [
						'type'         => 'switch',
						'label'        => __( 'Choreograph on scroll', 'fw' ),
						'desc'         => __( 'Pins the model in a fixed, transparent layer and drives its <strong>position, rotation, scale and opacity from scroll</strong> using the keyframes below — so it can drop in, glide corner-to-corner, spin, and fade as the visitor scrolls. Other sections scroll over / under it (see “Layer” below). When on, drag-to-orbit and auto-rotate are turned off (scroll owns the motion).', 'fw' ),
						'right-choice' => [ 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ],
						'left-choice'  => [ 'value' => 'no', 'label' => __( 'No', 'fw' ) ],
						'value'        => 'no',
					],
					'choreo_layer' => [
						'type'       => 'slider',
						'label'      => __( 'Layer (z-index)', 'fw' ),
						'desc'       => __( 'Stacking order of the 3D layer. Give a section a <em>higher</em> z-index (in its own settings) to put its text/elements <strong>in front</strong> of the model; a lower one to sit <strong>behind</strong> it. Negative values push the model behind normal content.', 'fw' ),
						'value'      => 2,
						'properties' => [ 'min' => -5, 'max' => 50, 'step' => 1 ],
					],
					'choreo_size' => [
						'type'       => 'slider',
						'label'      => __( 'Model size (%)', 'fw' ),
						'desc'       => __( 'Base size of the model box, as a percentage of the smaller screen side. Each keyframe’s Scale multiplies this.', 'fw' ),
						'value'      => 60,
						'properties' => [ 'min' => 15, 'max' => 100, 'step' => 1 ],
					],
					'choreo_travel' => [
						'type'       => 'slider',
						'label'      => __( 'Scroll length (screens)', 'fw' ),
						'desc'       => __( 'How many screen-heights of scrolling the whole choreography spans, measured from where this element sits on the page. Roughly match it to how many sections the model should travel across.', 'fw' ),
						'value'      => 3,
						'properties' => [ 'min' => 1, 'max' => 10, 'step' => 1 ],
					],
					'choreo_smooth' => [
						'type'       => 'slider',
						'label'      => __( 'Scroll smoothing', 'fw' ),
						'desc'       => __( '0 = locked to the scrollbar (frame-perfect); higher adds glide so the model eases toward its scroll target.', 'fw' ),
						'value'      => 18,
						'properties' => [ 'min' => 0, 'max' => 90, 'step' => 1 ],
					],
					'choreo_guide' => [
						'type'         => 'switch',
						'label'        => __( 'Show choreography guide', 'fw' ),
						'desc'         => __( 'An on-page authoring aid (turn it OFF before publishing). While on, the front end shows a <strong>marker in the scroll for each keyframe</strong> (“① 0%”, “② 22%”…) so you can see which keyframe lines up with which of your real sections, plus a live <strong>readout</strong> of the current scroll % and the model’s interpolated position, scale, rotation and opacity. Scroll your page with this on to find the exact “At %” for each pose, then switch it off.', 'fw' ),
						'right-choice' => [ 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ],
						'left-choice'  => [ 'value' => 'no', 'label' => __( 'No', 'fw' ) ],
						'value'        => 'no',
					],
					'choreo_keys' => [
						'type'          => 'addable-popup',
						'label'         => __( 'Keyframes', 'fw' ),
						'popup-title'   => __( 'Add / Edit Keyframe', 'fw' ),
						'size'          => 'medium',
						'desc'          => __( 'Each keyframe is the model’s pose at a point in the scroll (0% = start, 100% = end of the Scroll length above). The model smoothly interpolates between them. Order doesn’t matter — they’re sorted by “At”. Example: a keyframe at 0% up high & transparent, one at 25% centered & solid (drops in), one at 60% moved left + rotated, one at 100% bottom-right & smaller.', 'fw' ),
						'template'      => '{{=at}}%  ·  x {{=x}} y {{=y}}  ·  scale {{=scale}}%  ·  turn {{=yaw}}° tilt {{=pitch}}°  ·  fade {{=opacity}}%  ·  {{=ease}}',
						'popup-options' => [
							'at' => [
								'type'       => 'slider',
								'label'      => __( 'At (scroll %)', 'fw' ),
								'desc'       => __( 'Where in the scroll this pose is reached. 0 = start, 100 = end.', 'fw' ),
								'value'      => 0,
								'properties' => [ 'min' => 0, 'max' => 100, 'step' => 1 ],
							],
							'x' => [
								'type'       => 'slider',
								'label'      => __( 'X position (%)', 'fw' ),
								'desc'       => __( 'Horizontal offset from center, as % of viewport width. Negative = left, positive = right.', 'fw' ),
								'value'      => 0,
								'properties' => [ 'min' => -100, 'max' => 100, 'step' => 1 ],
							],
							'y' => [
								'type'       => 'slider',
								'label'      => __( 'Y position (%)', 'fw' ),
								'desc'       => __( 'Vertical offset from center, as % of viewport height. Negative = up, positive = down.', 'fw' ),
								'value'      => 0,
								'properties' => [ 'min' => -100, 'max' => 100, 'step' => 1 ],
							],
							'scale' => [
								'type'       => 'slider',
								'label'      => __( 'Scale (%)', 'fw' ),
								'desc'       => __( 'Size multiplier at this keyframe (100 = the base Model size).', 'fw' ),
								'value'      => 100,
								'properties' => [ 'min' => 10, 'max' => 300, 'step' => 1 ],
							],
							'yaw' => [
								'type'       => 'slider',
								'label'      => __( 'Rotate — turntable (°)', 'fw' ),
								'desc'       => __( 'Spins the model left/right around its vertical axis (camera orbit). Go past ±360 across keyframes for multiple turns.', 'fw' ),
								'value'      => 0,
								'properties' => [ 'min' => -720, 'max' => 720, 'step' => 5 ],
							],
							'pitch' => [
								'type'       => 'slider',
								'label'      => __( 'Rotate — tilt (°)', 'fw' ),
								'desc'       => __( 'Tips the view up/down. Positive looks down from above, negative looks up from below.', 'fw' ),
								'value'      => 0,
								'properties' => [ 'min' => -80, 'max' => 80, 'step' => 5 ],
							],
							'opacity' => [
								'type'       => 'slider',
								'label'      => __( 'Opacity (%)', 'fw' ),
								'desc'       => __( 'Fade the model in or out at this keyframe.', 'fw' ),
								'value'      => 100,
								'properties' => [ 'min' => 0, 'max' => 100, 'step' => 1 ],
							],
							'ease' => [
								'type'    => 'select',
								'label'   => __( 'Easing (into this pose)', 'fw' ),
								'value'   => 'inout',
								'choices' => [
									'linear' => __( 'Linear (constant)', 'fw' ),
									'inout'  => __( 'Smooth (ease in-out)', 'fw' ),
									'out'    => __( 'Ease out (fast → slow)', 'fw' ),
									'in'     => __( 'Ease in (slow → fast)', 'fw' ),
								],
							],
						],
						'value'         => [
							[ 'at' => 0,   'x' => 0,   'y' => -55, 'scale' => 80,  'yaw' => -40, 'pitch' => 12, 'opacity' => 0,   'ease' => 'out' ],
							[ 'at' => 25,  'x' => 0,   'y' => 0,   'scale' => 100, 'yaw' => 0,   'pitch' => 0,  'opacity' => 100, 'ease' => 'out' ],
							[ 'at' => 60,  'x' => -28, 'y' => 8,   'scale' => 92,  'yaw' => 130, 'pitch' => -4, 'opacity' => 100, 'ease' => 'inout' ],
							[ 'at' => 100, 'x' => 34,  'y' => 30,  'scale' => 66,  'yaw' => 240, 'pitch' => 0,  'opacity' => 100, 'ease' => 'inout' ],
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
