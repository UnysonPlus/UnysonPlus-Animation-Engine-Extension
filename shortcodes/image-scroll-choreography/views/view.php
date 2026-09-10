<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Scroll Choreography — frontend render.
 *
 * A zero-height anchor (.fw-choreo) the runtime measures scroll from, holding ONE fixed, pointer-events-none
 * foreground layer (.fw-choreo__fixed) that page sections scroll under. Inside it, each image is a
 * .fw-choreo__layer anchored to a corner; the runtime reads that layer's data-keys (keyframes we generate
 * from the Appear/Hold/Exit phases here) and drives its transform (translate + rotate + scale) and opacity
 * from the SHARED scroll progress. Honours prefers-reduced-motion (rests every image on its held pose).
 *
 * @var array $atts
 */

$layers = ( isset( $atts['layers'] ) && is_array( $atts['layers'] ) ) ? $atts['layers'] : array();
if ( ! $layers ) {
	if ( function_exists( 'fw_is_editor_context' ) && fw_is_editor_context() && function_exists( 'sc_editor_notice' ) ) {
		echo sc_editor_notice( __( 'Add one or more foreground images to choreograph.', 'fw' ) );
	}
	return;
}

$layer_z = max( -5, min( 50, (int) ( $atts['layer_z'] ?? 3 ) ) );
$travel  = max( 1, min( 12, (int) ( $atts['travel'] ?? 3 ) ) );
$smooth  = max( 0, min( 90, (int) ( $atts['smooth'] ?? 16 ) ) );
$guide   = ( $atts['guide'] ?? 'no' ) === 'yes' && is_user_logged_in(); // authoring overlay — logged-in only

$VD = 55.0; // vertical entrance/exit distance (vh)
$HD = 55.0; // horizontal entrance/exit distance (vw)

// Direction → a starting/ending {x,y} offset (vw/vh) for an entrance or exit.
$dir_offset = function ( $dir ) use ( $HD, $VD ) {
	switch ( $dir ) {
		case 'below': return array( 0.0,  $VD );
		case 'above': return array( 0.0, -$VD );
		case 'left':  return array( -$HD, 0.0 );
		case 'right': return array(  $HD, 0.0 );
		default:      return array( 0.0, 0.0 ); // fade / scale / none → no travel
	}
};

// Turn one layer's Appear/Hold/Exit phases into a sorted keyframe list for the shared engine.
$build_keys = function ( $L ) use ( $dir_offset ) {
	$appear_from = (string) ( $L['appear_from'] ?? 'below' );
	$appear_at   = max( 0, min( 100, (float) ( $L['appear_at'] ?? 15 ) ) );
	$lead        = max( 2, min( 50, (float) ( $L['lead'] ?? 12 ) ) );
	$hold_until  = max( 0, min( 100, (float) ( $L['hold_until'] ?? 70 ) ) );
	$exit_to     = (string) ( $L['exit_to'] ?? 'below' );
	$exit_at     = max( 0, min( 100, (float) ( $L['exit_at'] ?? 88 ) ) );
	$hold_scale  = max( 40, min( 160, (float) ( $L['hold_scale'] ?? 100 ) ) );
	$rot         = max( -25, min( 25, (float) ( $L['rotate'] ?? 0 ) ) );

	if ( $hold_until < $appear_at ) { $hold_until = $appear_at; }

	list( $ix, $iy ) = $dir_offset( $appear_from );
	$in_scale = ( 'scale' === $appear_from ) ? $hold_scale * 0.7 : $hold_scale;

	$keys = array();
	// k0 — off its mark, transparent (start of entrance).
	$keys[] = array( 'at' => max( 0, $appear_at - $lead ), 'x' => $ix, 'y' => $iy, 's' => $in_scale, 'r' => 0.0, 'o' => 0.0, 'e' => 'out' );
	// k1 — arrived on anchor, opaque, tilted.
	$keys[] = array( 'at' => $appear_at, 'x' => 0.0, 'y' => 0.0, 's' => $hold_scale, 'r' => $rot, 'o' => 100.0, 'e' => 'out' );
	// k2 — end of the hold (same pose; the "stays there" stretch).
	$keys[] = array( 'at' => $hold_until, 'x' => 0.0, 'y' => 0.0, 's' => $hold_scale, 'r' => $rot, 'o' => 100.0, 'e' => 'inout' );
	// k3 — exit (unless it stays to the end).
	if ( 'none' !== $exit_to ) {
		list( $ex, $ey ) = $dir_offset( $exit_to );
		$out_scale = ( 'scale' === $exit_to ) ? $hold_scale * 0.7 : $hold_scale;
		$eat = max( $hold_until + 1, $exit_at );
		$keys[] = array( 'at' => min( 100, $eat ), 'x' => $ex, 'y' => $ey, 's' => $out_scale, 'r' => $rot, 'o' => 0.0, 'e' => 'in' );
	}
	usort( $keys, function ( $a, $b ) { return ( $a['at'] < $b['at'] ) ? -1 : ( ( $a['at'] > $b['at'] ) ? 1 : 0 ); } );
	return $keys;
};

// Anchor → [horizontal, vertical] placement + the base centering translate (% of the image's own box) +
// transform-origin. Explicit table over the nine named anchors (the select's only possible values).
$anchor_map = function ( $anchor ) {
	$table = array(
		'bottom-left'   => array( 'left',   'bottom' ), 'bottom-center' => array( 'center', 'bottom' ), 'bottom-right' => array( 'right', 'bottom' ),
		'center-left'   => array( 'left',   'center' ), 'center'        => array( 'center', 'center' ), 'center-right' => array( 'right', 'center' ),
		'top-left'      => array( 'left',   'top' ),    'top-center'    => array( 'center', 'top' ),    'top-right'    => array( 'right', 'top' ),
	);
	list( $h, $v ) = isset( $table[ $anchor ] ) ? $table[ $anchor ] : array( 'left', 'bottom' );
	$bx = ( 'center' === $h ) ? '-50%' : '0px';
	$by = ( 'center' === $v ) ? '-50%' : '0px';
	return array( $h, $v, $bx, $by, $h . ' ' . $v );
};

$scene_json = wp_json_encode( array(
	'travel' => $travel,
	'smooth' => $smooth,
	'guide'  => $guide,
	'label'  => __( 'Image Choreography', 'fw' ), // shown as the guide HUD title
) );

// Wrapper: fold in the Advanced tab (CSS id / class / scoped Custom CSS) via the shared builder, then add
// our own class, the choreo layer z-index and the scene JSON. No styling/animation atts are defined, so the
// builder only applies the advanced fields here.
$classes = array( 'fw-choreo' );
if ( function_exists( 'sc_build_wrapper_attr' ) ) {
	$atts['base_class']       = 'choreo';
	$atts['unique_id_prefix'] = 'choreo-';
	$atts['css_class']        = trim( implode( ' ', $classes ) . ' ' . ( isset( $atts['css_class'] ) ? $atts['css_class'] : '' ) );
	$attr = sc_build_wrapper_attr( $atts );
} else {
	$attr = array( 'class' => implode( ' ', $classes ) );
}
$attr['style'] = ( isset( $attr['style'] ) && '' !== $attr['style'] ? rtrim( $attr['style'], ';' ) . ';' : '' ) . '--choreo-z:' . $layer_z . ';';
$attr['data-choreo'] = $scene_json;
$attr['aria-hidden'] = 'true';

/* ---- Tier 1 recipe (one-click composition) ---------------------------------------------------
 * A recipe drives every image's anchor + appear/hold/exit choreography from a pattern indexed by the
 * image's position, staggered automatically across the scroll so any number of images composes. The
 * author supplies only the images (+ inset/z/flip, which are preserved); "custom" leaves each image's
 * own controls in charge. Timing is in scroll % (0–100) and maps onto whatever Scroll Length is set. */
$recipe = (string) ( $atts['recipe'] ?? 'custom' );
$recipes = array(
	// slots cycle by image index; timing: first appear %, entrance length, hold length, exit length, spread across the scene, max per-image step.
	'parallax_rise' => array(
		'timing' => array( 'first' => 12, 'lead' => 12, 'hold' => 30, 'exit_lead' => 14, 'span' => 60, 'max_step' => 16 ),
		'slots'  => array(
			array( 'anchor' => 'bottom-left',   'appear_from' => 'below', 'exit_to' => 'above', 'rotate' => -3, 'hold_scale' => 100, 'width' => 30 ),
			array( 'anchor' => 'bottom-right',  'appear_from' => 'below', 'exit_to' => 'above', 'rotate' =>  3, 'hold_scale' => 90,  'width' => 26 ),
			array( 'anchor' => 'bottom-center', 'appear_from' => 'below', 'exit_to' => 'above', 'rotate' =>  0, 'hold_scale' => 82,  'width' => 22 ),
		),
	),
	'corner_peek' => array(
		'timing' => array( 'first' => 12, 'lead' => 10, 'hold' => 22, 'exit_lead' => 10, 'span' => 66, 'max_step' => 20 ),
		'slots'  => array(
			array( 'anchor' => 'bottom-left',  'appear_from' => 'left',  'exit_to' => 'left',  'rotate' => -4, 'hold_scale' => 96, 'width' => 28 ),
			array( 'anchor' => 'top-right',    'appear_from' => 'right', 'exit_to' => 'right', 'rotate' =>  4, 'hold_scale' => 96, 'width' => 28 ),
			array( 'anchor' => 'top-left',     'appear_from' => 'above', 'exit_to' => 'above', 'rotate' => -3, 'hold_scale' => 90, 'width' => 24 ),
			array( 'anchor' => 'bottom-right', 'appear_from' => 'below', 'exit_to' => 'below', 'rotate' =>  3, 'hold_scale' => 90, 'width' => 24 ),
		),
	),
	'zoom_focus' => array(
		'timing' => array( 'first' => 14, 'lead' => 14, 'hold' => 26, 'exit_lead' => 14, 'span' => 58, 'max_step' => 18 ),
		'slots'  => array(
			array( 'anchor' => 'center', 'appear_from' => 'scale', 'exit_to' => 'scale', 'rotate' => 0, 'hold_scale' => 108, 'width' => 40 ),
			array( 'anchor' => 'center', 'appear_from' => 'scale', 'exit_to' => 'scale', 'rotate' => 0, 'hold_scale' => 96,  'width' => 34 ),
			array( 'anchor' => 'center', 'appear_from' => 'scale', 'exit_to' => 'scale', 'rotate' => 0, 'hold_scale' => 84,  'width' => 28 ),
		),
	),
	'diagonal_sweep' => array(
		'timing' => array( 'first' => 12, 'lead' => 12, 'hold' => 24, 'exit_lead' => 12, 'span' => 64, 'max_step' => 18 ),
		'slots'  => array(
			array( 'anchor' => 'bottom-left',  'appear_from' => 'below', 'exit_to' => 'above', 'rotate' => -2, 'hold_scale' => 96, 'width' => 27 ),
			array( 'anchor' => 'center',       'appear_from' => 'below', 'exit_to' => 'above', 'rotate' =>  0, 'hold_scale' => 96, 'width' => 27 ),
			array( 'anchor' => 'top-right',    'appear_from' => 'below', 'exit_to' => 'above', 'rotate' =>  2, 'hold_scale' => 96, 'width' => 27 ),
		),
	),
	'alternating_sides' => array(
		'timing' => array( 'first' => 12, 'lead' => 12, 'hold' => 28, 'exit_lead' => 12, 'span' => 62, 'max_step' => 17 ),
		'slots'  => array(
			array( 'anchor' => 'center-left',  'appear_from' => 'left',  'exit_to' => 'left',  'rotate' => -3, 'hold_scale' => 94, 'width' => 30 ),
			array( 'anchor' => 'center-right', 'appear_from' => 'right', 'exit_to' => 'right', 'rotate' =>  3, 'hold_scale' => 94, 'width' => 30 ),
		),
	),
);
if ( 'custom' !== $recipe && isset( $recipes[ $recipe ] ) ) {
	$R = $recipes[ $recipe ];
	$slots = $R['slots']; $sc = count( $slots ); $t = $R['timing'];
	$valid = array();
	foreach ( $layers as $k => $L ) { if ( is_array( $L ) && ! empty( $L['image']['url'] ) ) { $valid[] = $k; } }
	$n = count( $valid );
	$step = ( $n > 1 ) ? min( (float) $t['max_step'], (float) $t['span'] / ( $n - 1 ) ) : 0.0;
	foreach ( $valid as $pos => $k ) {
		$slot   = $slots[ $pos % $sc ];
		$appear = min( 88.0, (float) $t['first'] + $pos * $step );
		$hold   = min( 94.0, $appear + (float) $t['hold'] );
		$exit   = min( 99.0, $hold + (float) $t['exit_lead'] );
		$layers[ $k ] = array_merge( $layers[ $k ], array(
			'anchor'      => $slot['anchor'],
			'appear_from' => $slot['appear_from'],
			'exit_to'     => $slot['exit_to'],
			'rotate'      => $slot['rotate'],
			'hold_scale'  => $slot['hold_scale'],
			'width'       => $slot['width'],
			'appear_at'   => round( $appear ),
			'lead'        => (float) $t['lead'],
			'hold_until'  => round( $hold ),
			'exit_at'     => round( $exit ),
		) );
	}
}

/* ---- Custom keyframes (Advanced tab) --------------------------------------------------------
 * Paste from the live editor's "Copy keys". When present it drives each image from exact keyframes,
 * overriding the Recipe and the Appear/Hold/Exit derivation. Values are coerced + re-sorted; the
 * data-keys attribute is JSON-encoded + escaped on output, so pasted content can't inject markup. */
$custom_keys = null;
$ck_raw = isset( $atts['custom_keys'] ) ? trim( (string) $atts['custom_keys'] ) : '';
if ( '' !== $ck_raw ) { $ck = json_decode( $ck_raw, true ); if ( is_array( $ck ) ) { $custom_keys = $ck; } }
$sanitize_keys = function ( $arr ) {
	$ease = array( 'in', 'out', 'inout', 'in-out', 'linear' );
	$out = array();
	foreach ( (array) $arr as $k ) {
		if ( ! is_array( $k ) ) { continue; }
		$out[] = array(
			'at' => max( 0.0, min( 100.0, (float) ( $k['at'] ?? 0 ) ) ),
			'x'  => (float) ( $k['x'] ?? 0 ), 'y' => (float) ( $k['y'] ?? 0 ),
			's'  => (float) ( $k['s'] ?? 100 ), 'r' => (float) ( $k['r'] ?? 0 ),
			'o'  => max( 0.0, min( 100.0, (float) ( $k['o'] ?? 100 ) ) ),
			'e'  => in_array( ( $k['e'] ?? 'inout' ), $ease, true ) ? $k['e'] : 'inout',
		);
	}
	usort( $out, function ( $a, $b ) { return ( $a['at'] < $b['at'] ) ? -1 : ( ( $a['at'] > $b['at'] ) ? 1 : 0 ); } );
	return $out;
};

echo '<div ' . fw_attr_to_html( $attr ) . '>';
echo '<div class="fw-choreo__fixed">';

$idx = 0;
foreach ( $layers as $L ) {
	if ( ! is_array( $L ) ) { continue; }
	$url = ( isset( $L['image']['url'] ) ) ? (string) $L['image']['url'] : '';
	if ( '' === $url ) { continue; }
	$idx++;

	$anchor = (string) ( $L['anchor'] ?? 'bottom-left' );
	$width  = max( 6, min( 90, (float) ( $L['width'] ?? 26 ) ) );
	$inset  = max( 0, min( 30, (float) ( $L['inset'] ?? 4 ) ) );
	$z      = max( 0, min( 20, (int) ( $L['z'] ?? 1 ) ) );
	$flip   = ( ( $L['flip'] ?? 'no' ) === 'yes' ) ? 1 : 0;

	list( $h, $v, $bx, $by, $origin ) = $anchor_map( $anchor );
	$keys = $build_keys( $L );
	if ( null !== $custom_keys ) {
		$ci = $idx - 1; // valid-image order (Copy keys emits layers in the same render order)
		if ( isset( $custom_keys[ $ci ]['keys'] ) && is_array( $custom_keys[ $ci ]['keys'] ) ) {
			$ov = $sanitize_keys( $custom_keys[ $ci ]['keys'] );
			if ( count( $ov ) >= 2 ) { $keys = $ov; }
		}
	}

	$style = '--w:' . rtrim( rtrim( sprintf( '%.2f', $width ), '0' ), '.' ) . ';'
		. '--inset:' . rtrim( rtrim( sprintf( '%.2f', $inset ), '0' ), '.' ) . ';'
		. 'z-index:' . $z . ';transform-origin:' . $origin . ';';

	$cls = 'fw-choreo__layer a-h-' . $h . ' a-v-' . $v;
	// Flip is applied on the <img> (its own centre-origin transform), NOT folded into the layer's pose
	// transform — the layer's transform-origin is the anchor corner, so mirroring there would throw the
	// box off-screen. Keeping them separate lets the pose scale from the corner while the image mirrors in place.
	$img_cls = $flip ? ' class="is-flip"' : '';
	$layer_name = ucfirst( str_replace( '-', ' ', $anchor ) ); // guide layer label, e.g. "Bottom left"
	echo '<div class="' . esc_attr( $cls ) . '" style="' . esc_attr( $style ) . '"'
		. ' data-bx="' . esc_attr( $bx ) . '" data-by="' . esc_attr( $by ) . '"'
		. ' data-name="' . esc_attr( $layer_name ) . '"'
		. ' data-keys="' . esc_attr( wp_json_encode( $keys ) ) . '">'
		. '<img src="' . esc_url( $url ) . '"' . $img_cls . ' alt="" loading="lazy" decoding="async" draggable="false">'
		. '</div>';
}

echo '</div></div>';
