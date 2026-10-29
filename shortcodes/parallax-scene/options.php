<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Parallax Scene — edit-modal options (the saved `atts` schema).
 *
 * TAB 1 Layers — an addable list of image layers (unlimited). Each layer: image, position (anchor + offset +
 *   width + z-order + flip), parallax depth, entrance reveal, and idle sway.
 * TAB 2 Scene  — the container: placement (in-flow / fixed / sticky), height, motion source + intensity,
 *   pointer-events, reduce-motion.
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

$layer_box_options = array(
	'image' => array(
		'type'  => 'upload',
		'label' => __( 'Layer image', 'fw' ),
		'desc'  => __( 'A transparent PNG/WebP works best (the layers overlap).', 'fw' ),
		'value' => array(),
	),
	'group_pos' => array(
		'type'    => 'group',
		'options' => array(
			'h_anchor' => $sel( __( 'Horizontal anchor', 'fw' ), 'center', array(
				'left' => __( 'Left', 'fw' ), 'center' => __( 'Center', 'fw' ), 'right' => __( 'Right', 'fw' ), 'stretch' => __( 'Full width', 'fw' ) ) ),
			'offset_x' => $sld( __( 'Offset X (%)', 'fw' ), 0, -50, 50, 1 ),
			'v_anchor' => $sel( __( 'Vertical anchor', 'fw' ), 'bottom', array(
				'bottom' => __( 'Bottom', 'fw' ), 'center' => __( 'Center', 'fw' ), 'top' => __( 'Top', 'fw' ) ) ),
			'offset_y' => $sld( __( 'Offset Y (%)', 'fw' ), 0, -50, 50, 1 ),
			'width'    => $sld( __( 'Width (% of scene)', 'fw' ), 40, 5, 140, 1, __( 'Ignored when anchor = Full width.', 'fw' ) ),
			'z'        => $sld( __( 'Stacking (z-index)', 'fw' ), 1, 0, 20, 1, __( 'Higher = in front.', 'fw' ) ),
			'flip'     => $sw( __( 'Flip horizontally', 'fw' ) ),
			'opacity'  => $sld( __( 'Opacity', 'fw' ), 100, 0, 100, 1 ),
		),
	),
	'group_motion' => array(
		'type'    => 'group',
		'options' => array(
			'depth'    => $sld( __( 'Parallax depth', 'fw' ), 30, 0, 100, 1, __( '0 = fixed; higher = travels more on scroll (closer/foreground).', 'fw' ) ),
			'entrance' => $sel( __( 'Entrance', 'fw' ), 'up', array(
				'none' => __( 'None', 'fw' ), 'up' => __( 'Rise up', 'fw' ), 'down' => __( 'Drop down', 'fw' ),
				'left' => __( 'From left', 'fw' ), 'right' => __( 'From right', 'fw' ), 'fade' => __( 'Fade', 'fw' ), 'scale' => __( 'Scale in', 'fw' ) ),
				__( 'How the layer reveals when the scene scrolls into view.', 'fw' ) ),
			'exit'     => $sel( __( 'Exit', 'fw' ), 'auto', array(
				'auto' => __( 'Leave the way it entered', 'fw' ), 'none' => __( 'Stay (parallax only)', 'fw' ),
				'up' => __( 'Rise up', 'fw' ), 'down' => __( 'Drop down', 'fw' ),
				'left' => __( 'To left', 'fw' ), 'right' => __( 'To right', 'fw' ), 'fade' => __( 'Fade', 'fw' ), 'scale' => __( 'Scale out', 'fw' ) ),
				__( 'How the layer leaves as the scene scrolls back OUT of view. “Leave the way it entered” mirrors the entrance; “Stay” keeps it in place and only parallaxes.', 'fw' ) ),
			'delay'    => $sld( __( 'Entrance / exit delay (ms)', 'fw' ), 0, 0, 1200, 20, __( 'Stagger layers by giving each a bigger delay (applies to both entrance and exit).', 'fw' ) ),
			'sway'     => $sel( __( 'Idle sway', 'fw' ), 'none', array(
				'none' => __( 'None', 'fw' ), 'sway' => __( 'Sway (rotate)', 'fw' ), 'bob' => __( 'Bob (up/down)', 'fw' ), 'drift' => __( 'Drift (side)', 'fw' ) ),
				__( 'A gentle, always-on motion — good for foliage.', 'fw' ) ),
			'sway_amt' => $sld( __( 'Sway amount', 'fw' ), 3, 0, 12, 0.5 ),
		),
	),
);

$options = array(
	'tab_layers' => array(
		'type'    => 'tab',
		'title'   => __( 'Layers', 'fw' ),
		'options' => array(
			'layers' => array(
				'type'            => 'addable-box',
				'label'           => false,
				'desc'            => __( 'Add image layers back-to-front. Anchor each (usually to the bottom edge), give it a parallax depth, an entrance, and optional sway.', 'fw' ),
				'add-button-text' => __( 'Add layer', 'fw' ),
				'box-options'     => $layer_box_options,
				'template'        => '{{= ( o && o.image && o.image.url ) ? "Layer" : "Empty layer" }}',
			),
		),
	),
	'tab_scene' => array(
		'type'    => 'tab',
		'title'   => __( 'Scene', 'fw' ),
		'options' => array(
			'placement'      => $sel( __( 'Placement', 'fw' ), 'in_flow', array(
				'in_flow'      => __( 'In flow (a band on the page)', 'fw' ),
				'fixed_bottom' => __( 'Fixed to viewport bottom (content scrolls over it)', 'fw' ),
				'sticky'       => __( 'Pinned — holds full-screen while you scroll, then dissolves', 'fw' ) ),
				__( 'How the whole scene sits relative to the page. <strong>Pinned</strong> gives the “Kage” effect: the scene locks to the viewport and holds while you scroll through it, then the layers scroll up and blur away.', 'fw' ) ),
			'height'         => array( 'type' => 'text', 'label' => __( 'Scene height', 'fw' ), 'value' => '60vh',
				'desc' => __( 'CSS length — e.g. <code>60vh</code>, <code>480px</code>, or <code>100vh</code> for a full-screen hero. For <strong>Pinned</strong> this is the held viewport height (use <code>100vh</code>).', 'fw' ) ),
			'hold'           => $sel( __( 'Hold length (Pinned only)', 'fw' ), '2.5', array(
				'1.5' => __( 'Short (1.5×)', 'fw' ), '2' => __( 'Medium (2×)', 'fw' ),
				'2.5' => __( 'Comfortable (2.5×)', 'fw' ), '3.5' => __( 'Long (3.5×)', 'fw' ), '5' => __( 'Epic (5×)', 'fw' ) ),
				__( 'How long the scene stays pinned, as multiples of its height. Ignored unless Placement is Pinned.', 'fw' ) ),
			'source'         => $sel( __( 'Motion source', 'fw' ), 'scroll', array(
				'scroll' => __( 'Scroll', 'fw' ), 'pointer' => __( 'Pointer', 'fw' ), 'both' => __( 'Scroll + Pointer', 'fw' ), 'none' => __( 'None (static)', 'fw' ) ) ),
			'intensity'      => $sld( __( 'Intensity (px)', 'fw' ), 60, 0, 240, 5, __( 'How far the deepest layers travel at full scroll / pointer.', 'fw' ) ),
			'pass_clicks'    => $sw( __( 'Let clicks pass through', 'fw' ), 'yes', __( 'Recommended when the scene sits behind content.', 'fw' ) ),
		),
	),
);
