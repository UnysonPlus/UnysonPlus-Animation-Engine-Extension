<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Scroll Choreography — edit-modal options (the saved `atts` schema).
 *
 * TAB 1 Images — an addable list of foreground image LAYERS. Each layer: image, placement (corner anchor +
 *   width + inset + z + flip) and a simple APPEAR → HOLD → EXIT choreography (each phase pinned to a scroll %).
 *   view.php turns those phases into keyframes for the shared scroll engine.
 * TAB 2 Scene  — the shared scroll timeline: the foreground layer's z-index, how many screen-heights the whole
 *   choreography spans (travel), scroll smoothing, and an opt-in authoring guide (markers + live HUD).
 */

$sel = function ( $label, $default, array $choices, $desc = '' ) {
	return array( 'type' => 'select', 'label' => $label, 'desc' => $desc, 'value' => $default, 'choices' => $choices, 'no-validate' => true );
};
$sld = function ( $label, $value, $min, $max, $step = 1, $desc = '' ) {
	return array( 'type' => 'slider', 'label' => $label, 'desc' => $desc, 'value' => $value, 'properties' => array( 'min' => $min, 'max' => $max, 'step' => $step ) );
};
$sw = function ( $label, $value = 'no', $desc = '' ) {
	return array( 'type' => 'switch', 'label' => $label, 'desc' => $desc, 'value' => $value,
		'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
		'left-choice'  => array( 'value' => 'no',  'label' => __( 'No', 'fw' ) ) );
};

$anchor_choices = array(
	'bottom-left'   => __( 'Bottom left', 'fw' ),   'bottom-center' => __( 'Bottom center', 'fw' ), 'bottom-right' => __( 'Bottom right', 'fw' ),
	'center-left'   => __( 'Center left', 'fw' ),   'center'        => __( 'Center', 'fw' ),        'center-right' => __( 'Center right', 'fw' ),
	'top-left'      => __( 'Top left', 'fw' ),      'top-center'    => __( 'Top center', 'fw' ),    'top-right'    => __( 'Top right', 'fw' ),
);
$dir_in = array(
	'below' => __( 'Slide up from below', 'fw' ), 'above' => __( 'Drop down from above', 'fw' ),
	'left'  => __( 'Slide in from left', 'fw' ),  'right' => __( 'Slide in from right', 'fw' ),
	'fade'  => __( 'Fade in (no slide)', 'fw' ),  'scale' => __( 'Scale up + fade', 'fw' ),
);
$dir_out = array(
	'none'  => __( 'Stay to the end (no exit)', 'fw' ),
	'below' => __( 'Slide down / off bottom', 'fw' ), 'above' => __( 'Slide up / off top', 'fw' ),
	'left'  => __( 'Slide off left', 'fw' ),          'right' => __( 'Slide off right', 'fw' ),
	'fade'  => __( 'Fade out', 'fw' ),                'scale' => __( 'Scale down + fade', 'fw' ),
);

$layer_box_options = array(
	'image' => array(
		'type'  => 'upload',
		'label' => __( 'Foreground image', 'fw' ),
		'desc'  => __( 'A transparent PNG/WebP works best (it floats over your page).', 'fw' ),
		'value' => array(),
	),
	'group_place' => array(
		'type'    => 'group',
		'options' => array(
			'anchor' => $sel( __( 'Anchor (rest position)', 'fw' ), 'bottom-left', $anchor_choices,
				__( 'Where the image sits once it has appeared. The visitor’s clicks always pass through it to the page.', 'fw' ) ),
			'width'  => $sld( __( 'Width (% of screen)', 'fw' ), 26, 6, 90, 1, __( 'Image width as a percentage of the viewport width.', 'fw' ) ),
			'inset'  => $sld( __( 'Edge inset (%)', 'fw' ), 4, 0, 30, 1, __( 'Gap between the image and the screen edges it is anchored to.', 'fw' ) ),
			'z'      => $sld( __( 'Stacking within the group (z-index)', 'fw' ), 1, 0, 20, 1, __( 'Higher = in front of the other images in this scene.', 'fw' ) ),
			'flip'   => $sw( __( 'Flip horizontally', 'fw' ) ),
		),
	),
	'group_choreo' => array(
		'type'    => 'group',
		'options' => array(
			'appear_from' => $sel( __( 'Appear by', 'fw' ), 'below', $dir_in,
				__( 'How the image enters before it settles on its anchor.', 'fw' ) ),
			'appear_at'   => $sld( __( 'Appear at (scroll %)', 'fw' ), 15, 0, 100, 1, __( 'The scroll point where the image has fully arrived and starts holding.', 'fw' ) ),
			'lead'        => $sld( __( 'Entrance length (%)', 'fw' ), 12, 2, 50, 1, __( 'How much scroll the entrance takes, ending at “Appear at”.', 'fw' ) ),
			'hold_until'  => $sld( __( 'Hold until (scroll %)', 'fw' ), 70, 0, 100, 1, __( 'The image stays put on its anchor from “Appear at” to here.', 'fw' ) ),
			'exit_to'     => $sel( __( 'Exit by', 'fw' ), 'below', $dir_out,
				__( 'How the image leaves after its hold. “Stay to the end” keeps it pinned for the rest of the travel.', 'fw' ) ),
			'exit_at'     => $sld( __( 'Gone by (scroll %)', 'fw' ), 88, 0, 100, 1, __( 'The image has fully left by this scroll point. Ignored when Exit = Stay.', 'fw' ) ),
			'hold_scale'  => $sld( __( 'Hold size (%)', 'fw' ), 100, 40, 160, 1, __( 'Size while held (100 = its natural Width above).', 'fw' ) ),
			'rotate'      => $sld( __( 'Tilt while held (°)', 'fw' ), 0, -25, 25, 1, __( 'A gentle rotation on the resting image. 0 = upright.', 'fw' ) ),
		),
	),
);

$options = array(
	'tab_images' => array(
		'type'    => 'tab',
		'title'   => __( 'Images', 'fw' ),
		'options' => array(
			// Tier 1 recipe — one-click Awwwards composition. Pick a recipe and just add your images: the
			// recipe drives every image's anchor + appear/hold/exit choreography (staggered by position),
			// so a polished scroll story needs no hand-tuning. "Custom" hands each image its own controls below.
			'recipe' => $sel( __( 'Recipe (one-click composition)', 'fw' ), 'custom', array(
				'custom'            => __( 'Custom — author each image below', 'fw' ),
				'parallax_rise'     => __( 'Parallax Rise — images rise in sequence and drift up & out', 'fw' ),
				'corner_peek'       => __( 'Corner Peek-a-boo — each slides in from its own corner', 'fw' ),
				'zoom_focus'        => __( 'Zoom Focus — centred, scale-up focus pulls, one after another', 'fw' ),
				'diagonal_sweep'    => __( 'Diagonal Sweep — a bottom-left → top-right diagonal march', 'fw' ),
				'alternating_sides' => __( 'Alternating Sides — odd from the left, even from the right', 'fw' ),
			), __( 'Pick a ready-made scroll composition, then just add your images below — the recipe poses and times them for you (staggered across the Scroll Length on the Scene tab). Choose <strong>Custom</strong> to hand-author each image’s anchor and appear / hold / exit instead.', 'fw' ) ),
			'layers' => array(
				'type'            => 'addable-box',
				'label'           => false,
				'desc'            => __( 'Add foreground images. Anchor each to a corner, then set when it appears, how long it holds, and when it exits — all keyed to the page scroll. Turn on the guide (Scene tab) to find the right scroll %.', 'fw' ),
				'add-button-text' => __( 'Add image', 'fw' ),
				'box-options'     => $layer_box_options,
				'template'        => '{{= ( o && o.image && o.image.url ) ? "Image" : "Empty image" }}',
				// Fill the popup instead of the default fixed 428px column (which left the wide "large" modal
				// mostly empty), and lay each field out as a compact label-left / input-right row.
				'width'           => 'full',
				'inline'          => true,
			),
		),
	),
	'tab_scene' => array(
		'type'    => 'tab',
		'title'   => __( 'Scene', 'fw' ),
		'options' => array(
			'layer_z' => $sld( __( 'Foreground layer (z-index)', 'fw' ), 3, -5, 50, 1,
				__( 'Stacking of the whole pinned foreground. Give a section a <em>higher</em> z-index (in its own settings) to have its text sit <strong>in front</strong> of these images; lower to sit behind.', 'fw' ) ),
			'travel'  => $sld( __( 'Scroll length (screens)', 'fw' ), 3, 1, 12, 1,
				__( 'How many screen-heights of scrolling the whole choreography spans, measured from where this element sits on the page. Every image’s scroll % (0–100) maps onto this length. Put your “chapter” sections right after this element so they scroll underneath.', 'fw' ) ),
			'smooth'  => $sld( __( 'Scroll smoothing', 'fw' ), 16, 0, 90, 1,
				__( '0 = locked to the scrollbar (frame-perfect); higher adds glide so images ease toward their scroll target.', 'fw' ) ),
			'guide'   => $sw( __( 'Show choreography guide', 'fw' ), 'no',
				__( 'An on-page authoring aid (turn it OFF before publishing). Shows a <strong>marker in the scroll for each image’s appear / hold / exit points</strong> plus a live readout of the current scroll % and each image’s state — so you can dial in the exact scroll %.', 'fw' ) ),
		),
	),
	// Advanced only — a focused subset. No Styling tab (this element is an invisible, zero-height fixed
	// overlay: there is no box to give a background / padding). No Animation tab (the element IS a scroll
	// animation). No Position group (position on the scroll-measuring anchor would break the choreography).
	// What remains genuinely useful: a CSS id / class to target it, and per-element scoped Custom CSS.
	'tab_advanced' => array(
		'type'    => 'tab',
		'title'   => __( 'Advanced', 'fw' ),
		'options' => array(
			'unique_id' => array( 'type' => 'unique' ),
			'group_keys' => array(
				'type'    => 'group',
				'options' => array(
					'custom_keys' => array(
						'type'  => 'textarea',
						'label' => __( 'Custom keyframes (from the live editor)', 'fw' ),
						'desc'  => __( 'Optional. Paste the JSON from the guide’s <strong>Copy keys</strong> button (turn the guide on, open the ✎ editor, retime / tweak, then Copy keys) to drive the images from exact keyframes — <strong>overriding the Recipe and each image’s Appear / Hold / Exit</strong> above. One entry per image, in order. Leave empty to use the settings.', 'fw' ),
						'value' => '',
					),
				),
			),
			'group_css' => array(
				'type'    => 'group',
				'options' => array(
					'css_id'     => array( 'type' => 'text', 'label' => __( 'CSS ID', 'fw' ), 'desc' => __( 'Useful for anchor links.', 'fw' ) ),
					'css_class'  => array( 'type' => 'text', 'label' => __( 'CSS Class', 'fw' ), 'desc' => __( 'Lands on the scene wrapper; target its images with <code>.your-class .fw-choreo__layer</code>.', 'fw' ) ),
					'custom_css' => array(
						'type'  => 'code-editor',
						'label' => __( 'Custom CSS', 'fw' ),
						'desc'  => __( 'Scoped to THIS element. Use the keyword <code>selector</code> for the scene, e.g. <code>selector .fw-choreo__layer img { filter: drop-shadow(0 10px 24px rgba(0,0,0,.4)); }</code>.', 'fw' ),
						'value' => '',
						'mode'  => 'css',
					),
				),
			),
		),
	),
);
