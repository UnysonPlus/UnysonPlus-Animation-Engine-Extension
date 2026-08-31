<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Interactive Reveal — a stacked set of image LAYERS that animate from an
 * assembled state (progress 0) to an exploded/peeled state (progress 1). The
 * progress is driven by the chosen Trigger (scroll / hover / click). Optional
 * HOTSPOTS are labelled pins that appear at a chosen progress. PLACEMENT lets the
 * whole thing sit inline, as a section background, or as a foreground overlay.
 *
 * Assets: v1 accepts uploaded layer images. The shared "Reveal Pack" store
 * (uploads/unysonplus/reveal) lands in a follow-up so long sequences / heavy
 * layer sets never clutter the Media Library.
 */

$bg_field = function_exists( 'sc_color_field_compact' )
	? sc_color_field_compact( array( 'label' => __( 'Background', 'fw' ), 'kind' => 'bg', 'value' => array( 'predefined' => '', 'custom' => '' ) ) )
	: array( 'type' => 'color-picker', 'label' => __( 'Background', 'fw' ), 'value' => '' );

$options = array(

	/* ---- Layers -------------------------------------------------------- */
	'tab_layers' => array(
		'title'   => __( 'Layers', 'fw' ),
		'type'    => 'tab',
		'options' => array(
			'group_layers' => array(
				'type'    => 'group',
				'options' => array(
					'layer_source' => array(
						'type'    => 'select',
						'label'   => __( 'Layers from', 'fw' ),
						'desc'    => __( 'Manual = the layer list below. Reveal Pack = a pack stored outside the Media Library (the list below is then ignored).', 'fw' ),
						'value'   => 'manual',
						'choices' => array(
							'manual' => __( 'Manual layers', 'fw' ),
							'pack'   => __( 'Reveal Pack', 'fw' ),
						),
					),
					'pack' => array(
						'type'    => 'select',
						'label'   => __( 'Reveal Pack', 'fw' ),
						'desc'    => __( 'Used when "Layers from" is Reveal Pack. Packs import into uploads/unysonplus/reveal/ — never the Media Library.', 'fw' ),
						'value'   => '',
						'choices' => function_exists( 'fw_ir_pack_choices' ) ? fw_ir_pack_choices( 'layers' ) : array( '' => __( 'No packs installed', 'fw' ) ),
					),
					'layers' => array(
						'type'            => 'addable-box',
						'label'           => false,
						'desc'            => __( 'Each layer is one transparent PNG/WebP part, stacked back-to-front (top of the list = back). At rest they sit together; on reveal each moves by its own offset / rotation / scale.', 'fw' ),
						'value'           => array(),
						'add-button-text' => __( 'Add layer', 'fw' ),
						'box-controls'    => array(),
						'template'        => '{{= o.label || "Layer" }}',
						'box-options'     => array(
							'image' => array(
								'type'        => 'upload',
								'label'       => __( 'Image', 'fw' ),
								'images_only' => true,
							),
							'label' => array(
								'type'  => 'text',
								'label' => __( 'Name', 'fw' ),
								'desc'  => __( 'For your reference in this list only.', 'fw' ),
								'value' => '',
							),
							'dx' => array(
								'type'         => 'number',
								'label'        => __( 'Move X (%)', 'fw' ),
								'desc'         => __( 'Horizontal travel at full reveal, % of the layer\'s own width. Negative = left.', 'fw' ),
								'value'        => 0,
								'min'          => -300,
								'max'          => 300,
								'step'         => 1,
								'numeric_type' => 'integer',
							),
							'dy' => array(
								'type'         => 'number',
								'label'        => __( 'Move Y (%)', 'fw' ),
								'desc'         => __( 'Vertical travel at full reveal, % of the layer\'s own height. Negative = up.', 'fw' ),
								'value'        => 0,
								'min'          => -300,
								'max'          => 300,
								'step'         => 1,
								'numeric_type' => 'integer',
							),
							'rot' => array(
								'type'         => 'number',
								'label'        => __( 'Rotate (deg)', 'fw' ),
								'value'        => 0,
								'min'          => -180,
								'max'          => 180,
								'step'         => 1,
								'numeric_type' => 'integer',
							),
							'scale' => array(
								'type'         => 'number',
								'label'        => __( 'Scale change (%)', 'fw' ),
								'desc'         => __( 'Size change at full reveal. 0 = same size, 20 = grows 20%, -20 = shrinks.', 'fw' ),
								'value'        => 0,
								'min'          => -90,
								'max'          => 200,
								'step'         => 1,
								'numeric_type' => 'integer',
							),
							'fade' => array(
								'type'    => 'switch',
								'label'   => __( 'Fade in on reveal', 'fw' ),
								'desc'    => __( 'Start transparent and fade to full opacity as it separates.', 'fw' ),
								'value'   => false,
							),
						),
					),
				),
			),
		),
	),

	/* ---- Hotspots ------------------------------------------------------ */
	'tab_hotspots' => array(
		'title'   => __( 'Hotspots', 'fw' ),
		'type'    => 'tab',
		'options' => array(
			'group_hotspots' => array(
				'type'    => 'group',
				'options' => array(
					'hotspots' => array(
						'type'            => 'addable-box',
						'label'           => false,
						'desc'            => __( 'Labelled pins over the reveal (e.g. "Neodymium driver"). Each appears once the reveal passes its "Show at" point. Positions are % of the box.', 'fw' ),
						'value'           => array(),
						'add-button-text' => __( 'Add hotspot', 'fw' ),
						'template'        => '{{= o.label || "Hotspot" }}',
						'box-options'     => array(
							'label' => array(
								'type'  => 'text',
								'label' => __( 'Label', 'fw' ),
								'value' => '',
							),
							'x' => array(
								'type'         => 'number',
								'label'        => __( 'X (%)', 'fw' ),
								'value'        => 50,
								'min'          => 0,
								'max'          => 100,
								'step'         => 1,
								'numeric_type' => 'integer',
							),
							'y' => array(
								'type'         => 'number',
								'label'        => __( 'Y (%)', 'fw' ),
								'value'        => 50,
								'min'          => 0,
								'max'          => 100,
								'step'         => 1,
								'numeric_type' => 'integer',
							),
							'reveal_at' => array(
								'type'       => 'slider',
								'label'      => __( 'Show at (%)', 'fw' ),
								'desc'       => __( 'Reveal progress at which this hotspot fades in.', 'fw' ),
								'value'      => 60,
								'properties' => array( 'min' => 0, 'max' => 100, 'step' => 5 ),
							),
							'connector' => array(
								'type'  => 'switch',
								'label' => __( 'Leader line', 'fw' ),
								'desc'  => __( 'Draw a short line between the dot and its label.', 'fw' ),
								'value' => false,
							),
						),
					),
				),
			),
		),
	),

	/* ---- Motion -------------------------------------------------------- */
	'tab_motion' => array(
		'title'   => __( 'Motion', 'fw' ),
		'type'    => 'tab',
		'options' => array(
			'group_motion' => array(
				'type'    => 'group',
				'options' => array(
					'trigger' => array(
						'type'    => 'select',
						'label'   => __( 'Trigger', 'fw' ),
						'desc'    => __( 'What drives the reveal from assembled to exploded.', 'fw' ),
						'value'   => 'scroll',
						'choices' => array(
							'scroll' => __( 'Scroll (as it passes the viewport)', 'fw' ),
							'hover'  => __( 'Hover (mouse over / focus)', 'fw' ),
							'drag'   => __( 'Drag to scrub', 'fw' ),
							'toggle' => __( 'Click to toggle', 'fw' ),
							'auto'   => __( 'Autoplay loop', 'fw' ),
						),
					),
					'drag_axis' => array(
						'type'    => 'select',
						'label'   => __( 'Drag axis (Drag only)', 'fw' ),
						'desc'    => __( 'Which direction the visitor drags to scrub the reveal.', 'fw' ),
						'value'   => 'x',
						'choices' => array( 'x' => __( 'Horizontal', 'fw' ), 'y' => __( 'Vertical', 'fw' ) ),
					),
					'reverse' => array(
						'type'  => 'switch',
						'label' => __( 'Reverse direction', 'fw' ),
						'desc'  => __( 'Play from exploded to assembled instead.', 'fw' ),
						'value' => false,
					),
					'start_at' => array(
						'type'       => 'slider',
						'label'      => __( 'Rest state (%)', 'fw' ),
						'desc'       => __( 'How separated the layers sit before the trigger runs. 0 = fully assembled.', 'fw' ),
						'value'      => 0,
						'properties' => array( 'min' => 0, 'max' => 100, 'step' => 5 ),
					),
					'duration' => array(
						'type'       => 'slider',
						'label'      => __( 'Speed (Hover / Toggle / Autoplay)', 'fw' ),
						'desc'       => __( 'Transition time in milliseconds for non-scroll triggers.', 'fw' ),
						'value'      => 700,
						'properties' => array( 'min' => 150, 'max' => 3000, 'step' => 50 ),
					),
					'scroll_mode' => array(
						'type'    => 'select',
						'label'   => __( 'Scroll behaviour (Scroll only)', 'fw' ),
						'desc'    => __( '"Play in view" runs as the element passes the viewport. "Pin & scrub" holds it full-height and scrubs the reveal as you scroll through it (inline placement only).', 'fw' ),
						'value'   => 'inview',
						'choices' => array(
							'inview'  => __( 'Play in view', 'fw' ),
							'pinned'  => __( 'Pin & scrub (full-screen)', 'fw' ),
						),
					),
					'pin_length' => array(
						'type'       => 'slider',
						'label'      => __( 'Pin length (screens) — Pin & scrub only', 'fw' ),
						'desc'       => __( 'How much scrolling the reveal spans while pinned. Higher = slower scrub.', 'fw' ),
						'value'      => 2,
						'properties' => array( 'min' => 1, 'max' => 6, 'step' => 1 ),
					),
				),
			),
		),
	),

	/* ---- Style / Placement -------------------------------------------- */
	'tab_style' => array(
		'title'   => __( 'Style', 'fw' ),
		'type'    => 'tab',
		'options' => array(
			'group_placement' => array(
				'type'    => 'group',
				'options' => array(
					'placement' => array(
						'type'    => 'select',
						'label'   => __( 'Placement', 'fw' ),
						'desc'    => __( 'Inline flows normally. Background fills the parent Section behind its content (set the Section to position: relative). Foreground overlays it in front.', 'fw' ),
						'value'   => 'inline',
						'choices' => array(
							'inline'     => __( 'Inline (in the content flow)', 'fw' ),
							'background' => __( 'Section background (behind content)', 'fw' ),
							'foreground' => __( 'Foreground overlay (in front)', 'fw' ),
						),
					),
					'pointer' => array(
						'type'    => 'select',
						'label'   => __( 'Pointer events', 'fw' ),
						'desc'    => __( 'Decorative lets clicks pass through to content behind (use for Background / scroll). Interactive is required for Hover / Click but blocks clicks under the overlap.', 'fw' ),
						'value'   => 'decorative',
						'choices' => array(
							'decorative' => __( 'Decorative (pass clicks through)', 'fw' ),
							'interactive'=> __( 'Interactive (captures pointer)', 'fw' ),
						),
					),
				),
			),
			'group_box' => array(
				'type'    => 'group',
				'options' => array(
					'aspect' => array(
						'type'    => 'select',
						'label'   => __( 'Aspect ratio', 'fw' ),
						'desc'    => __( 'Shape of the reveal box (ignored for Background, which fills the Section).', 'fw' ),
						'value'   => '1/1',
						'choices' => array(
							'1/1'  => __( 'Square (1:1)', 'fw' ),
							'4/3'  => __( 'Landscape (4:3)', 'fw' ),
							'16/9' => __( 'Wide (16:9)', 'fw' ),
							'3/4'  => __( 'Portrait (3:4)', 'fw' ),
							'auto' => __( 'Free height', 'fw' ),
						),
					),
					'height' => array(
						'type'         => 'number',
						'label'        => __( 'Height (px) — "Free height" only', 'fw' ),
						'value'        => 520,
						'min'          => 160,
						'step'         => 10,
						'numeric_type' => 'integer',
					),
					'max_width' => array(
						'type'         => 'number',
						'label'        => __( 'Max width (px) — 0 = full', 'fw' ),
						'value'        => 0,
						'min'          => 0,
						'step'         => 10,
						'numeric_type' => 'integer',
					),
					'align' => array(
						'type'    => 'select',
						'label'   => __( 'Alignment', 'fw' ),
						'value'   => 'center',
						'choices' => array( 'left' => __( 'Left', 'fw' ), 'center' => __( 'Center', 'fw' ), 'right' => __( 'Right', 'fw' ) ),
					),
					'bg' => $bg_field,
				),
			),
		),
	),
);
