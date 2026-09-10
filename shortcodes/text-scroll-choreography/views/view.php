<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Text Scroll Choreography — frontend render.
 *
 * Same scene as Image Scroll Choreography (.fw-choreo anchor + one fixed .fw-choreo__fixed layer the page
 * scrolls under), but each .fw-choreo__layer holds TEXT, nested three deep so three systems never fight:
 *   .fw-choreo__layer  → POSE   (the shared engine writes translate/scale/rotate/opacity from scroll keys)
 *     .fw-choreo__warp → WARP   (a static perspective/rotateX/skew from the layer's Warp options)
 *       .fw-choreo__text → the styled text wrapper carrying data-text (a Text Effect animates its glyphs)
 *         <tag>…</tag>
 * We call upw_anim_use_asset('text-effects', $effect) so the effect's assets load; its runtime auto-binds
 * [data-text] on view/load. Honours reduced-motion via both the pose engine and the text-effects runtime.
 *
 * @var array $atts
 */

$layers = ( isset( $atts['layers'] ) && is_array( $atts['layers'] ) ) ? $atts['layers'] : array();
if ( ! $layers ) {
	if ( function_exists( 'fw_is_editor_context' ) && fw_is_editor_context() && function_exists( 'sc_editor_notice' ) ) {
		echo sc_editor_notice( __( 'Add one or more lines of text to choreograph.', 'fw' ) );
	}
	return;
}

$layer_z = max( -5, min( 50, (int) ( $atts['layer_z'] ?? 3 ) ) );
$travel  = max( 1, min( 12, (int) ( $atts['travel'] ?? 3 ) ) );
$smooth  = max( 0, min( 90, (int) ( $atts['smooth'] ?? 16 ) ) );
$guide   = ( $atts['guide'] ?? 'no' ) === 'yes' && is_user_logged_in(); // authoring overlay — logged-in only

$VD = 55.0; $HD = 55.0;

$dir_offset = function ( $dir ) use ( $HD, $VD ) {
	switch ( $dir ) {
		case 'below': return array( 0.0,  $VD );
		case 'above': return array( 0.0, -$VD );
		case 'left':  return array( -$HD, 0.0 );
		case 'right': return array(  $HD, 0.0 );
		default:      return array( 0.0, 0.0 );
	}
};

$build_keys = function ( $L ) use ( $dir_offset ) {
	$appear_from = (string) ( $L['appear_from'] ?? 'below' );
	$appear_at   = max( 0, min( 100, (float) ( $L['appear_at'] ?? 15 ) ) );
	$lead        = max( 2, min( 50, (float) ( $L['lead'] ?? 12 ) ) );
	$hold_until  = max( 0, min( 100, (float) ( $L['hold_until'] ?? 70 ) ) );
	$exit_to     = (string) ( $L['exit_to'] ?? 'above' );
	$exit_at     = max( 0, min( 100, (float) ( $L['exit_at'] ?? 88 ) ) );
	$hold_scale  = max( 40, min( 160, (float) ( $L['hold_scale'] ?? 100 ) ) );
	$rot         = max( -25, min( 25, (float) ( $L['rotate'] ?? 0 ) ) );
	if ( $hold_until < $appear_at ) { $hold_until = $appear_at; }

	list( $ix, $iy ) = $dir_offset( $appear_from );
	$in_scale = ( 'scale' === $appear_from ) ? $hold_scale * 0.7 : $hold_scale;

	$keys = array();
	$keys[] = array( 'at' => max( 0, $appear_at - $lead ), 'x' => $ix, 'y' => $iy, 's' => $in_scale, 'r' => 0.0, 'o' => 0.0, 'e' => 'out' );
	$keys[] = array( 'at' => $appear_at, 'x' => 0.0, 'y' => 0.0, 's' => $hold_scale, 'r' => $rot, 'o' => 100.0, 'e' => 'out' );
	$keys[] = array( 'at' => $hold_until, 'x' => 0.0, 'y' => 0.0, 's' => $hold_scale, 'r' => $rot, 'o' => 100.0, 'e' => 'inout' );
	if ( 'none' !== $exit_to ) {
		list( $ex, $ey ) = $dir_offset( $exit_to );
		$out_scale = ( 'scale' === $exit_to ) ? $hold_scale * 0.7 : $hold_scale;
		$eat = max( $hold_until + 1, $exit_at );
		$keys[] = array( 'at' => min( 100, $eat ), 'x' => $ex, 'y' => $ey, 's' => $out_scale, 'r' => $rot, 'o' => 0.0, 'e' => 'in' );
	}
	usort( $keys, function ( $a, $b ) { return ( $a['at'] < $b['at'] ) ? -1 : ( ( $a['at'] > $b['at'] ) ? 1 : 0 ); } );
	return $keys;
};

$anchor_map = function ( $anchor ) {
	$table = array(
		'bottom-left'   => array( 'left',   'bottom' ), 'bottom-center' => array( 'center', 'bottom' ), 'bottom-right' => array( 'right', 'bottom' ),
		'center-left'   => array( 'left',   'center' ), 'center'        => array( 'center', 'center' ), 'center-right' => array( 'right', 'center' ),
		'top-left'      => array( 'left',   'top' ),    'top-center'    => array( 'center', 'top' ),    'top-right'    => array( 'right', 'top' ),
	);
	list( $h, $v ) = isset( $table[ $anchor ] ) ? $table[ $anchor ] : array( 'left', 'center' );
	$bx = ( 'center' === $h ) ? '-50%' : '0px';
	$by = ( 'center' === $v ) ? '-50%' : '0px';
	return array( $h, $v, $bx, $by, $h . ' ' . $v );
};

$allowed_tags    = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div' );
$allowed_effects = array( 'split_reveal', 'blur', 'mask', 'slide', 'bounce', 'scale', 'flip3d', 'typewriter', 'scramble', 'matrix', 'splitflap', 'gradient_flow', 'neon', 'glitch', 'shimmer', 'wave' );

$scene_json = wp_json_encode( array( 'travel' => $travel, 'smooth' => $smooth, 'guide' => $guide, 'label' => __( 'Text Choreography', 'fw' ) ) );

$classes = array( 'fw-choreo', 'fw-choreo--text' );
if ( function_exists( 'sc_build_wrapper_attr' ) ) {
	$atts['base_class']       = 'choreo';
	$atts['unique_id_prefix'] = 'tchoreo-';
	$atts['css_class']        = trim( implode( ' ', $classes ) . ' ' . ( isset( $atts['css_class'] ) ? $atts['css_class'] : '' ) );
	$attr = sc_build_wrapper_attr( $atts );
} else {
	$attr = array( 'class' => implode( ' ', $classes ) );
}
$attr['style'] = ( isset( $attr['style'] ) && '' !== $attr['style'] ? rtrim( $attr['style'], ';' ) . ';' : '' ) . '--choreo-z:' . $layer_z . ';';
$attr['data-choreo'] = $scene_json;
$attr['aria-hidden'] = 'true';

/* ---- Tier 1 recipe (one-click composition) ---------------------------------------------------
 * A recipe drives every line's anchor + appear/hold/exit choreography from a pattern indexed by the
 * line's position, staggered automatically across the scroll. The author supplies only the words (+
 * styling/warp/effect, all preserved); "custom" leaves each line's own controls in charge. Timing is
 * in scroll % (0–100) and maps onto whatever Scroll Length is set. */
$recipe = (string) ( $atts['recipe'] ?? 'custom' );
$recipes = array(
	'stacked_rise' => array(
		'timing' => array( 'first' => 12, 'lead' => 12, 'hold' => 30, 'exit_lead' => 14, 'span' => 62, 'max_step' => 16 ),
		'slots'  => array(
			array( 'anchor' => 'center-left', 'appear_from' => 'below', 'exit_to' => 'above', 'rotate' => 0, 'hold_scale' => 100, 'max_width' => 46 ),
			array( 'anchor' => 'center-left', 'appear_from' => 'below', 'exit_to' => 'above', 'rotate' => 0, 'hold_scale' => 88,  'max_width' => 40 ),
			array( 'anchor' => 'center-left', 'appear_from' => 'below', 'exit_to' => 'above', 'rotate' => 0, 'hold_scale' => 78,  'max_width' => 36 ),
		),
	),
	'alternating_sides' => array(
		'timing' => array( 'first' => 12, 'lead' => 12, 'hold' => 26, 'exit_lead' => 12, 'span' => 62, 'max_step' => 17 ),
		'slots'  => array(
			array( 'anchor' => 'center-left',  'appear_from' => 'left',  'exit_to' => 'left',  'rotate' => -2, 'hold_scale' => 96, 'max_width' => 42 ),
			array( 'anchor' => 'center-right', 'appear_from' => 'right', 'exit_to' => 'right', 'rotate' =>  2, 'hold_scale' => 96, 'max_width' => 42 ),
		),
	),
	'center_focus' => array(
		'timing' => array( 'first' => 14, 'lead' => 14, 'hold' => 24, 'exit_lead' => 14, 'span' => 58, 'max_step' => 18 ),
		'slots'  => array(
			array( 'anchor' => 'center', 'appear_from' => 'scale', 'exit_to' => 'scale', 'rotate' => 0, 'hold_scale' => 112, 'max_width' => 60 ),
			array( 'anchor' => 'center', 'appear_from' => 'scale', 'exit_to' => 'scale', 'rotate' => 0, 'hold_scale' => 96,  'max_width' => 52 ),
			array( 'anchor' => 'center', 'appear_from' => 'scale', 'exit_to' => 'scale', 'rotate' => 0, 'hold_scale' => 82,  'max_width' => 46 ),
		),
	),
	'diagonal_sweep' => array(
		'timing' => array( 'first' => 12, 'lead' => 12, 'hold' => 24, 'exit_lead' => 12, 'span' => 64, 'max_step' => 18 ),
		'slots'  => array(
			array( 'anchor' => 'bottom-left', 'appear_from' => 'below', 'exit_to' => 'above', 'rotate' => -2, 'hold_scale' => 96, 'max_width' => 44 ),
			array( 'anchor' => 'center',      'appear_from' => 'below', 'exit_to' => 'above', 'rotate' =>  0, 'hold_scale' => 96, 'max_width' => 44 ),
			array( 'anchor' => 'top-right',   'appear_from' => 'below', 'exit_to' => 'above', 'rotate' =>  2, 'hold_scale' => 96, 'max_width' => 44 ),
		),
	),
);
if ( 'custom' !== $recipe && isset( $recipes[ $recipe ] ) ) {
	$R = $recipes[ $recipe ];
	$slots = $R['slots']; $sc = count( $slots ); $t = $R['timing'];
	$valid = array();
	foreach ( $layers as $k => $L ) { if ( is_array( $L ) && '' !== trim( (string) ( $L['text'] ?? '' ) ) ) { $valid[] = $k; } }
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
			'max_width'   => $slot['max_width'],
			'appear_at'   => round( $appear ),
			'lead'        => (float) $t['lead'],
			'hold_until'  => round( $hold ),
			'exit_at'     => round( $exit ),
		) );
	}
}

/* ---- Custom keyframes (Advanced tab) — paste from the live editor's "Copy keys" to drive each line
 * from exact keyframes, overriding the Recipe and Appear/Hold/Exit. Coerced + re-sorted; JSON-encoded
 * + escaped on output. */
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

$ck_i = -1;
foreach ( $layers as $L ) {
	if ( ! is_array( $L ) ) { continue; }
	$text = isset( $L['text'] ) ? trim( (string) $L['text'] ) : '';
	if ( '' === $text ) { continue; }
	$ck_i++;

	$tag       = in_array( ( $L['tag'] ?? 'h2' ), $allowed_tags, true ) ? $L['tag'] : 'h2';
	$size      = max( 1.5, min( 16, (float) ( $L['size'] ?? 5 ) ) );
	$weight    = preg_replace( '/[^0-9]/', '', (string) ( $L['weight'] ?? '700' ) );
	$color     = trim( (string) ( $L['color'] ?? '' ) );
	$talign    = in_array( ( $L['talign'] ?? 'left' ), array( 'left', 'center', 'right' ), true ) ? $L['talign'] : 'left';
	$anchor    = (string) ( $L['anchor'] ?? 'center-left' );
	$max_width = max( 12, min( 90, (float) ( $L['max_width'] ?? 40 ) ) );
	$inset     = max( 0, min( 30, (float) ( $L['inset'] ?? 5 ) ) );
	$z         = max( 0, min( 20, (int) ( $L['z'] ?? 1 ) ) );
	$warp_tilt  = max( -60, min( 60, (float) ( $L['warp_tilt'] ?? 0 ) ) );
	$warp_skew  = max( -40, min( 40, (float) ( $L['warp_skew'] ?? 0 ) ) );
	$warp_curve  = max( -100, min( 100, (float) ( $L['warp_curve'] ?? 0 ) ) );
	$raw_effect  = (string) ( $L['effect'] ?? 'none' );
	$scroll_rev  = ( 'scroll_reveal' === $raw_effect );
	$effect      = in_array( $raw_effect, $allowed_effects, true ) ? $raw_effect : '';
	if ( 0.0 !== $warp_curve ) { $effect = ''; $scroll_rev = false; } // Curve (SVG arc) has no glyphs to animate.
	$split     = in_array( ( $L['split'] ?? 'chars' ), array( 'chars', 'words', 'lines' ), true ) ? $L['split'] : 'chars';
	$trigger   = in_array( ( $L['trigger'] ?? 'view' ), array( 'view', 'load' ), true ) ? $L['trigger'] : 'view';

	list( $h, $v, $bx, $by, $origin ) = $anchor_map( $anchor );
	$keys = $build_keys( $L );
	if ( null !== $custom_keys && isset( $custom_keys[ $ck_i ]['keys'] ) && is_array( $custom_keys[ $ck_i ]['keys'] ) ) {
		$ov = $sanitize_keys( $custom_keys[ $ck_i ]['keys'] );
		if ( count( $ov ) >= 2 ) { $keys = $ov; }
	}

	$layer_style = '--w:' . rtrim( rtrim( sprintf( '%.2f', $max_width ), '0' ), '.' ) . ';'
		. '--inset:' . rtrim( rtrim( sprintf( '%.2f', $inset ), '0' ), '.' ) . ';'
		. 'z-index:' . $z . ';transform-origin:' . $origin . ';';

	// WARP wrapper transform (identity when both are 0).
	$warp_style = '';
	if ( 0.0 !== $warp_tilt || 0.0 !== $warp_skew ) {
		$warp_style = 'transform:perspective(800px) rotateX(' . rtrim( rtrim( sprintf( '%.1f', $warp_tilt ), '0' ), '.' ) . 'deg) skewX(' . rtrim( rtrim( sprintf( '%.1f', $warp_skew ), '0' ), '.' ) . 'deg);';
	}

	// TEXT wrapper style + Text Effect data-attrs.
	$text_style = 'font-size:' . rtrim( rtrim( sprintf( '%.2f', $size ), '0' ), '.' ) . 'vw;font-weight:' . ( '' !== $weight ? $weight : '700' ) . ';text-align:' . $talign . ';';
	if ( '' !== $color ) { $text_style .= 'color:' . esc_attr( $color ) . ';'; }

	$fx_attr = '';
	if ( $scroll_rev ) {
		// Scroll-scrubbed read-along: glyphs light across the layer's appear → hold window (our own JS).
		$s_from = max( 0, min( 100, (float) ( $L['appear_at'] ?? 15 ) ) );
		$s_to   = max( $s_from + 1, min( 100, (float) ( $L['hold_until'] ?? 70 ) ) );
		$fx_attr = ' data-scrub="1" data-scrub-split="' . esc_attr( $split ) . '"'
			. ' data-scrub-from="' . esc_attr( rtrim( rtrim( sprintf( '%.1f', $s_from ), '0' ), '.' ) ) . '"'
			. ' data-scrub-to="' . esc_attr( rtrim( rtrim( sprintf( '%.1f', $s_to ), '0' ), '.' ) ) . '"';
	} elseif ( '' !== $effect ) {
		if ( function_exists( 'upw_anim_use_asset' ) ) { upw_anim_use_asset( 'text-effects', $effect ); }
		$fx_attr = ' data-text="' . esc_attr( $effect ) . '" data-text-split="' . esc_attr( $split ) . '" data-text-trigger="' . esc_attr( $trigger ) . '"';
	} elseif ( 0.0 !== $warp_curve ) {
		$fx_attr = ' data-curve="' . esc_attr( rtrim( rtrim( sprintf( '%.1f', $warp_curve ), '0' ), '.' ) ) . '"';
	}

	$cls = 'fw-choreo__layer a-h-' . $h . ' a-v-' . $v;
	// Guide layer label: a short snippet of the actual text.
	$layer_name = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $text ) ) );
	if ( function_exists( 'mb_strlen' ) && mb_strlen( $layer_name ) > 22 ) { $layer_name = mb_substr( $layer_name, 0, 20 ) . '…'; }
	elseif ( strlen( $layer_name ) > 22 ) { $layer_name = substr( $layer_name, 0, 20 ) . '…'; }
	echo '<div class="' . esc_attr( $cls ) . '" style="' . esc_attr( $layer_style ) . '"'
		. ' data-bx="' . esc_attr( $bx ) . '" data-by="' . esc_attr( $by ) . '"'
		. ' data-name="' . esc_attr( '' !== $layer_name ? $layer_name : __( 'Text', 'fw' ) ) . '"'
		. ' data-keys="' . esc_attr( wp_json_encode( $keys ) ) . '">'
		. '<div class="fw-choreo__warp"' . ( '' !== $warp_style ? ' style="' . esc_attr( $warp_style ) . '"' : '' ) . '>'
		. '<div class="fw-choreo__text"' . $fx_attr . ' style="' . esc_attr( $text_style ) . '">'
		. '<' . $tag . ' class="fw-choreo__t">' . esc_html( $text ) . '</' . $tag . '>'
		. '</div></div></div>';
}

echo '</div></div>';
