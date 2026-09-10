<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Text Scroll Choreography — edit-modal options.
 *
 * TAB 1 Text  — an addable list of foreground TEXT layers. Each: the words + tag + styling, a corner anchor,
 *   a simple APPEAR → HOLD → EXIT choreography (each phase at a scroll %), an optional 3D warp, and an
 *   optional Text Effect on the glyphs. view.php turns the phases into keyframes for the shared engine.
 * TAB 2 Scene — the shared scroll timeline (layer z, travel, smoothing, authoring guide).
 * TAB 3 Advanced — CSS id / class / scoped Custom CSS.
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
// A curated set of the Animation Engine's Text Effects that read well on a single foreground phrase.
$effect_choices = array(
	'none'          => __( '— None —', 'fw' ),
	'scroll_reveal' => __( 'Scroll reveal (read-along) — scrubbed to scroll', 'fw' ),
	'split_reveal'  => __( 'Split reveal', 'fw' ),   'blur'      => __( 'Blur in', 'fw' ),     'mask'     => __( 'Mask up', 'fw' ),
	'slide'         => __( 'Slide in', 'fw' ),       'bounce'    => __( 'Bounce in', 'fw' ),   'scale'    => __( 'Pop (scale)', 'fw' ),
	'flip3d'        => __( 'Flip 3D', 'fw' ),        'typewriter'=> __( 'Typewriter', 'fw' ),  'scramble' => __( 'Scramble', 'fw' ),
	'matrix'        => __( 'Matrix decode', 'fw' ),  'splitflap' => __( 'Split-flap', 'fw' ),
	'gradient_flow' => __( 'Gradient flow', 'fw' ),  'neon'      => __( 'Neon glow', 'fw' ),   'glitch'   => __( 'Glitch', 'fw' ),
	'shimmer'       => __( 'Shimmer', 'fw' ),        'wave'      => __( 'Wave', 'fw' ),
);

$layer_box_options = array(
	'text' => array(
		'type'  => 'textarea',
		'label' => __( 'Text', 'fw' ),
		'desc'  => __( 'The words for this line. Keep it short — a foreground phrase, not a paragraph.', 'fw' ),
		'value' => '',
	),
	'group_type' => array(
		'type'    => 'group',
		'options' => array(
			'tag'    => $sel( __( 'Tag', 'fw' ), 'h2', array( 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6', 'p' => 'Paragraph', 'div' => 'Div' ) ),
			'size'   => $sld( __( 'Size (vw)', 'fw' ), 5, 1.5, 16, 0.5, __( 'Font size as a percentage of viewport width, so it scales with the screen.', 'fw' ) ),
			'weight' => $sel( __( 'Weight', 'fw' ), '700', array( '300' => __( 'Light', 'fw' ), '400' => __( 'Regular', 'fw' ), '500' => __( 'Medium', 'fw' ), '600' => __( 'Semibold', 'fw' ), '700' => __( 'Bold', 'fw' ), '800' => __( 'Extrabold', 'fw' ), '900' => __( 'Black', 'fw' ) ) ),
			'color'  => array( 'type' => 'color-picker', 'label' => __( 'Color', 'fw' ), 'value' => '', 'desc' => __( 'Leave empty to inherit. Ignored by colour effects (Gradient, Neon, Glitch) which set their own.', 'fw' ) ),
			'talign' => $sel( __( 'Text align', 'fw' ), 'left', array( 'left' => __( 'Left', 'fw' ), 'center' => __( 'Center', 'fw' ), 'right' => __( 'Right', 'fw' ) ) ),
		),
	),
	'group_place' => array(
		'type'    => 'group',
		'options' => array(
			'anchor'    => $sel( __( 'Anchor (rest position)', 'fw' ), 'center-left', $anchor_choices,
				__( 'Where the text sits once it has appeared.', 'fw' ) ),
			'max_width' => $sld( __( 'Max width (% of screen)', 'fw' ), 40, 12, 90, 1, __( 'The text wraps within this width.', 'fw' ) ),
			'inset'     => $sld( __( 'Edge inset (%)', 'fw' ), 5, 0, 30, 1, __( 'Gap from the screen edges it is anchored to.', 'fw' ) ),
			'z'         => $sld( __( 'Stacking within the group (z-index)', 'fw' ), 1, 0, 20, 1, __( 'Higher = in front of the other lines in this scene.', 'fw' ) ),
		),
	),
	'group_choreo' => array(
		'type'    => 'group',
		'options' => array(
			'appear_from' => $sel( __( 'Appear by', 'fw' ), 'below', $dir_in, __( 'How the line enters before it settles on its anchor.', 'fw' ) ),
			'appear_at'   => $sld( __( 'Appear at (scroll %)', 'fw' ), 15, 0, 100, 1, __( 'The scroll point where the line has fully arrived and starts holding.', 'fw' ) ),
			'lead'        => $sld( __( 'Entrance length (%)', 'fw' ), 12, 2, 50, 1, __( 'How much scroll the entrance takes, ending at “Appear at”.', 'fw' ) ),
			'hold_until'  => $sld( __( 'Hold until (scroll %)', 'fw' ), 70, 0, 100, 1, __( 'The line stays put on its anchor from “Appear at” to here.', 'fw' ) ),
			'exit_to'     => $sel( __( 'Exit by', 'fw' ), 'above', $dir_out, __( 'How the line leaves after its hold. “Stay to the end” keeps it pinned for the rest of the travel.', 'fw' ) ),
			'exit_at'     => $sld( __( 'Gone by (scroll %)', 'fw' ), 88, 0, 100, 1, __( 'The line has fully left by this scroll point. Ignored when Exit = Stay.', 'fw' ) ),
			'hold_scale'  => $sld( __( 'Hold size (%)', 'fw' ), 100, 40, 160, 1, __( 'Scale while held (100 = its natural Size above).', 'fw' ) ),
			'rotate'      => $sld( __( 'Tilt while held (°)', 'fw' ), 0, -25, 25, 1, __( 'A gentle 2D rotation on the resting line. 0 = upright.', 'fw' ) ),
		),
	),
	'group_warp' => array(
		'type'    => 'group',
		'options' => array(
			'warp_tilt'  => $sld( __( '3D tilt — rotateX (°)', 'fw' ), 0, -60, 60, 1, __( 'Tilts the line back/forward in 3D (with perspective). 0 = flat.', 'fw' ) ),
			'warp_skew'  => $sld( __( 'Skew (°)', 'fw' ), 0, -40, 40, 1, __( 'Shears the line for an italic-lean / speed feel. 0 = none.', 'fw' ) ),
			'warp_curve' => $sld( __( 'Curve (arc)', 'fw' ), 0, -100, 100, 1, __( 'Bends the line onto an arc — positive curves up, negative down. Renders the line as SVG text-on-a-path; a per-glyph Text Effect is skipped while Curve is on (an arc has no separate letters to animate). Tilt / Skew still apply.', 'fw' ) ),
		),
	),
	'group_effect' => array(
		'type'    => 'group',
		'options' => array(
			'effect'  => $sel( __( 'Text effect', 'fw' ), 'none', $effect_choices,
				__( 'An Animation Engine Text Effect played on the glyphs when the line appears. The effect animates the letters; the choreography moves the whole line — they don’t interfere.', 'fw' ) ),
			'split'   => $sel( __( 'Split by', 'fw' ), 'chars', array( 'chars' => __( 'Characters', 'fw' ), 'words' => __( 'Words', 'fw' ), 'lines' => __( 'Lines', 'fw' ) ),
				__( 'How the effect breaks the text up (character-by-character, word, or line).', 'fw' ) ),
			'trigger' => $sel( __( 'Effect plays', 'fw' ), 'view', array( 'view' => __( 'When it scrolls into view', 'fw' ), 'load' => __( 'On page load', 'fw' ) ) ),
		),
	),
);

$options = array(
	'tab_text' => array(
		'type'    => 'tab',
		'title'   => __( 'Text', 'fw' ),
		'options' => array(
			// Tier 1 recipe — one-click composition. Pick a recipe and just add your lines: the recipe
			// anchors and times every line (staggered by position). "Custom" hands each line its own controls.
			'recipe' => $sel( __( 'Recipe (one-click composition)', 'fw' ), 'custom', array(
				'custom'            => __( 'Custom — author each line below', 'fw' ),
				'stacked_rise'      => __( 'Stacked Rise — lines rise in sequence from the left and lift out', 'fw' ),
				'alternating_sides' => __( 'Alternating Sides — odd from the left, even from the right', 'fw' ),
				'center_focus'      => __( 'Center Focus — centred lines scale up one after another', 'fw' ),
				'diagonal_sweep'    => __( 'Diagonal Sweep — a bottom-left → top-right diagonal march', 'fw' ),
			), __( 'Pick a ready-made scroll composition, then just add your lines below — the recipe poses and times them for you (staggered across the Scroll Length on the Scene tab). Choose <strong>Custom</strong> to hand-author each line’s anchor and appear / hold / exit instead.', 'fw' ) ),
			'layers' => array(
				'type'            => 'addable-box',
				'label'           => false,
				'desc'            => __( 'Add foreground lines of text. Anchor each to a corner, set when it appears / holds / exits (keyed to the page scroll), and optionally warp it or give it a Text Effect. Turn on the guide (Scene tab) to find the right scroll %.', 'fw' ),
				'add-button-text' => __( 'Add line', 'fw' ),
				'box-options'     => $layer_box_options,
				'template'        => '{{= ( o && o.text ) ? ( "" + o.text ).replace(/<[^>]*>/g,"").slice(0,42) || "Line" : "Empty line" }}',
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
				__( 'Stacking of the whole pinned foreground. A section with a <em>higher</em> z-index sits in front of these lines; lower, behind.', 'fw' ) ),
			'travel'  => $sld( __( 'Scroll length (screens)', 'fw' ), 3, 1, 12, 1,
				__( 'How many screen-heights the whole choreography spans, measured from where this element sits. Every line’s scroll % (0–100) maps onto this length. Put your “chapter” sections right after this element so they scroll underneath.', 'fw' ) ),
			'smooth'  => $sld( __( 'Scroll smoothing', 'fw' ), 16, 0, 90, 1,
				__( '0 = locked to the scrollbar (frame-perfect); higher adds glide so lines ease toward their scroll target.', 'fw' ) ),
			'guide'   => $sw( __( 'Show choreography guide', 'fw' ), 'no',
				__( 'An on-page authoring aid (turn it OFF before publishing). Shows a marker in the scroll for each line’s appear / hold / exit points plus a live readout — so you can dial in the exact scroll %.', 'fw' ) ),
		),
	),
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
						'desc'  => __( 'Optional. Paste the JSON from the guide’s <strong>Copy keys</strong> button (turn the guide on, open the ✎ editor, retime / tweak, then Copy keys) to drive the lines from exact keyframes — <strong>overriding the Recipe and each line’s Appear / Hold / Exit</strong> above. One entry per line, in order. Leave empty to use the settings.', 'fw' ),
						'value' => '',
					),
				),
			),
			'group_css' => array(
				'type'    => 'group',
				'options' => array(
					'css_id'     => array( 'type' => 'text', 'label' => __( 'CSS ID', 'fw' ), 'desc' => __( 'Useful for anchor links.', 'fw' ) ),
					'css_class'  => array( 'type' => 'text', 'label' => __( 'CSS Class', 'fw' ), 'desc' => __( 'Lands on the scene wrapper; target its lines with <code>.your-class .fw-choreo__layer</code>.', 'fw' ) ),
					'custom_css' => array(
						'type'  => 'code-editor',
						'label' => __( 'Custom CSS', 'fw' ),
						'desc'  => __( 'Scoped to THIS element. Use <code>selector</code> for the scene, e.g. <code>selector .fw-choreo__text { text-shadow: 0 8px 30px rgba(0,0,0,.4); }</code>.', 'fw' ),
						'value' => '',
						'mode'  => 'css',
					),
				),
			),
		),
	),
);
