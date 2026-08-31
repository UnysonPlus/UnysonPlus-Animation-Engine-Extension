<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/** @var array $atts */

if ( ! function_exists( 'sc_ir_get' ) ) {
	function sc_ir_get( $path, $atts, $default = '' ) {
		if ( function_exists( 'fw_akg' ) ) {
			$v = fw_akg( $path, $atts, null );
			if ( $v !== null ) { return $v; }
		}
		return $default;
	}
}

/** Resolve an `upload` option value (array with attachment_id / url) to a URL. */
if ( ! function_exists( 'sc_ir_img_url' ) ) {
	function sc_ir_img_url( $img ) {
		if ( ! is_array( $img ) ) { return ''; }
		$id = isset( $img['attachment_id'] ) ? (int) $img['attachment_id'] : 0;
		if ( $id ) {
			$src = wp_get_attachment_image_src( $id, 'full' );
			if ( $src && ! empty( $src[0] ) ) { return $src[0]; }
		}
		return ! empty( $img['url'] ) ? (string) $img['url'] : '';
	}
}

$layers_raw = sc_ir_get( 'layers', $atts, array() );
$layers     = array();
if ( is_array( $layers_raw ) ) {
	foreach ( $layers_raw as $l ) {
		if ( ! is_array( $l ) ) { continue; }
		$url = sc_ir_img_url( isset( $l['image'] ) ? $l['image'] : array() );
		if ( $url === '' ) { continue; }
		$layers[] = array(
			'url'   => esc_url( $url ),
			'label' => isset( $l['label'] ) ? (string) $l['label'] : '',
			'dx'    => (float) ( isset( $l['dx'] ) ? $l['dx'] : 0 ),
			'dy'    => (float) ( isset( $l['dy'] ) ? $l['dy'] : 0 ),
			'rot'   => (float) ( isset( $l['rot'] ) ? $l['rot'] : 0 ),
			'scale' => (float) ( isset( $l['scale'] ) ? $l['scale'] : 0 ),
			'fade'  => ! empty( $l['fade'] ),
		);
	}
}

/* Reveal Pack source overrides the manual layer list (assets live outside the Media Library). */
if ( sc_ir_get( 'layer_source', $atts, 'manual' ) === 'pack' && function_exists( 'fw_ir_get_pack' ) ) {
	$pack = fw_ir_get_pack( (string) sc_ir_get( 'pack', $atts, '' ) );
	if ( $pack && ! empty( $pack['layers'] ) ) {
		$layers = array();
		foreach ( $pack['layers'] as $pl ) {
			$layers[] = array(
				'url'   => esc_url( $pl['url'] ),
				'label' => $pl['label'],
				'dx'    => (float) $pl['dx'],
				'dy'    => (float) $pl['dy'],
				'rot'   => (float) $pl['rot'],
				'scale' => (float) $pl['scale'],
				'fade'  => ! empty( $pl['fade'] ),
			);
		}
	}
}

if ( empty( $layers ) ) {
	if ( function_exists( 'fw_is_editor_context' ) && fw_is_editor_context() ) {
		echo '<div class="sc-ireveal__empty" style="padding:2rem;text-align:center;color:#888;border:1px dashed #ccd;border-radius:8px;">'
			. esc_html__( 'Interactive Reveal — add image layers to preview.', 'fw' ) . '</div>';
	}
	return;
}

/* Motion */
$trigger  = sc_ir_get( 'trigger', $atts, 'scroll' );
$trigger  = in_array( $trigger, array( 'scroll', 'hover', 'drag', 'toggle', 'auto' ), true ) ? $trigger : 'scroll';
$dragaxis = sc_ir_get( 'drag_axis', $atts, 'x' ) === 'y' ? 'y' : 'x';
$reverse  = ! empty( sc_ir_get( 'reverse', $atts, false ) );
$start_at = max( 0, min( 100, (int) sc_ir_get( 'start_at', $atts, 0 ) ) );
$duration = max( 150, min( 3000, (int) sc_ir_get( 'duration', $atts, 700 ) ) );

/* Placement / style */
$placement = sc_ir_get( 'placement', $atts, 'inline' );
$placement = in_array( $placement, array( 'inline', 'background', 'foreground' ), true ) ? $placement : 'inline';
$pointer   = sc_ir_get( 'pointer', $atts, 'decorative' ) === 'interactive' ? 'interactive' : 'decorative';
$aspect    = sc_ir_get( 'aspect', $atts, '1/1' );
$aspect    = in_array( $aspect, array( '1/1', '4/3', '16/9', '3/4', 'auto' ), true ) ? $aspect : '1/1';
$height    = max( 160, (int) sc_ir_get( 'height', $atts, 520 ) );
$max_width = max( 0, (int) sc_ir_get( 'max_width', $atts, 0 ) );
$align     = sc_ir_get( 'align', $atts, 'center' );
$align     = in_array( $align, array( 'left', 'center', 'right' ), true ) ? $align : 'center';
$bg        = function_exists( 'sc_color_to_css' ) ? sc_color_to_css( sc_ir_get( 'bg', $atts, '' ), '' ) : '';

/* Pinned scroll sub-mode — only meaningful for the scroll trigger on an inline element. */
$scroll_mode = sc_ir_get( 'scroll_mode', $atts, 'inview' ) === 'pinned' ? 'pinned' : 'inview';
$pin_length  = max( 1, min( 6, (int) sc_ir_get( 'pin_length', $atts, 2 ) ) );
$pinned      = ( $trigger === 'scroll' && $scroll_mode === 'pinned' && $placement === 'inline' );

/* Container classes + style */
$classes = array( 'sc-ireveal', 'sc-ireveal--' . $placement, 'sc-ireveal--pointer-' . $pointer, 'sc-ireveal--trigger-' . $trigger );
if ( $pinned ) { $classes[] = 'sc-ireveal--pinned'; }
$style   = array();
if ( $bg !== '' ) { $style[] = 'background:' . $bg; }
if ( ! $pinned && ( $placement === 'inline' || $placement === 'foreground' ) ) {
	if ( $aspect === 'auto' ) { $style[] = 'height:' . $height . 'px'; }
	else { $style[] = 'aspect-ratio:' . str_replace( '/', ' / ', $aspect ); }
}
if ( ! $pinned && $placement === 'inline' && $max_width > 0 ) {
	$style[] = 'max-width:' . $max_width . 'px';
	if ( $align === 'center' ) { $style[] = 'margin-left:auto;margin-right:auto'; }
	elseif ( $align === 'right' ) { $style[] = 'margin-left:auto'; }
}

$data  = ' data-ir-trigger="' . esc_attr( $trigger ) . '"';
$data .= ' data-ir-reverse="' . ( $reverse ? '1' : '0' ) . '"';
$data .= ' data-ir-start="' . esc_attr( $start_at ) . '"';
$data .= ' data-ir-duration="' . esc_attr( $duration ) . '"';
$data .= ' data-ir-drag-axis="' . esc_attr( $dragaxis ) . '"';
if ( $pinned ) { $data .= ' data-ir-pin="1"'; }

/* Pinned wrapper: a tall spacer with a sticky full-height stage the reveal fills. */
if ( $pinned ) {
	echo '<div class="sc-ireveal-pin" style="height:' . ( $pin_length + 1 ) . '00vh">'
		. '<div class="sc-ireveal-pin__stick">';
}

echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" style="' . esc_attr( implode( ';', $style ) ) . '"' . $data . '>';

/* Layers — list order is back → front (first = furthest back). */
$z = 1;
foreach ( $layers as $l ) {
	$lstyle = 'z-index:' . $z . ';';
	$ldata  = ' data-dx="' . esc_attr( $l['dx'] ) . '" data-dy="' . esc_attr( $l['dy'] ) . '"'
		. ' data-rot="' . esc_attr( $l['rot'] ) . '" data-scale="' . esc_attr( $l['scale'] ) . '"'
		. ' data-fade="' . ( $l['fade'] ? '1' : '0' ) . '"';
	echo '<div class="sc-ireveal__layer" style="' . esc_attr( $lstyle ) . '"' . $ldata . '>'
		. '<img src="' . $l['url'] . '" alt="' . esc_attr( $l['label'] ) . '" loading="lazy" decoding="async" />'
		. '</div>';
	$z++;
}

/* Hotspots */
$hotspots = sc_ir_get( 'hotspots', $atts, array() );
if ( is_array( $hotspots ) && $hotspots ) {
	echo '<div class="sc-ireveal__spots" aria-hidden="false">';
	foreach ( $hotspots as $h ) {
		if ( ! is_array( $h ) ) { continue; }
		$label = isset( $h['label'] ) ? trim( (string) $h['label'] ) : '';
		if ( $label === '' ) { continue; }
		$x    = max( 0, min( 100, (float) ( isset( $h['x'] ) ? $h['x'] : 50 ) ) );
		$y    = max( 0, min( 100, (float) ( isset( $h['y'] ) ? $h['y'] : 50 ) ) );
		$at   = max( 0, min( 100, (int) ( isset( $h['reveal_at'] ) ? $h['reveal_at'] : 60 ) ) );
		$conn = ! empty( $h['connector'] );
		$cls  = 'sc-ireveal__spot' . ( $conn ? ' has-connector' : '' );
		echo '<div class="' . esc_attr( $cls ) . '" style="left:' . esc_attr( $x ) . '%;top:' . esc_attr( $y ) . '%" data-at="' . esc_attr( $at ) . '">'
			. '<span class="sc-ireveal__dot" aria-hidden="true"></span>'
			. ( $conn ? '<span class="sc-ireveal__line" aria-hidden="true"></span>' : '' )
			. '<span class="sc-ireveal__label">' . esc_html( $label ) . '</span>'
			. '</div>';
	}
	echo '</div>';
}

echo '</div>'; // .sc-ireveal

if ( $pinned ) {
	echo '</div></div>'; // .sc-ireveal-pin__stick + .sc-ireveal-pin
}
