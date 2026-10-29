<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Parallax Scene — frontend render.
 *
 * @var array $atts
 *
 * A .fw-parallax-scene wrapper (in-flow / fixed / sticky) holding absolutely-anchored layers. Each layer
 * nests four elements so its four transform sources never fight:
 *   .fw-ps-layer  → ANCHOR (left/right/top/bottom + width + z + a static centering translate)
 *     .fw-ps-move → PARALLAX (scripts.js writes transform on scroll/pointer)
 *       .fw-ps-sway → IDLE SWAY (a CSS keyframe transform)
 *         .fw-ps-enter → ENTRANCE (a CSS transition; scripts.js adds .is-in on view)
 *           <img>
 * scripts.js drives the parallax + entrance; all state is data-attrs + CSS. Honours reduce-motion.
 */

$layers    = ( isset( $atts['layers'] ) && is_array( $atts['layers'] ) ) ? $atts['layers'] : array();
$placement = in_array( ( $atts['placement'] ?? 'in_flow' ), array( 'in_flow', 'fixed_bottom', 'sticky' ), true ) ? $atts['placement'] : 'in_flow';
$height    = preg_replace( '/[^0-9a-z.%()+\-\s]/i', '', (string) ( $atts['height'] ?? '60vh' ) );
$source    = in_array( ( $atts['source'] ?? 'scroll' ), array( 'scroll', 'pointer', 'both', 'none' ), true ) ? $atts['source'] : 'scroll';
$intensity = max( 0, min( 240, (int) ( $atts['intensity'] ?? 60 ) ) );
$pass      = ( $atts['pass_clicks'] ?? 'yes' ) === 'yes';
$hold      = max( 1, min( 6, (float) ( $atts['hold'] ?? 2.5 ) ) ); // 'sticky' only: runway = viewport height × hold.

if ( ! $layers ) { return; }

$pinned = ( 'sticky' === $placement );
$vp_h   = '' !== $height ? $height : ( $pinned ? '100vh' : '60vh' );

// PINNED (Kage-style): a TALL outer runway holds a position:sticky inner viewport, so the scene pins
// full-screen and HOLDS while you scroll the runway, then releases + dissolves. Non-pinned scenes put
// the layers straight in the scene box (its height IS the scene height).
$scene_style = 'height:' . ( $pinned ? 'calc(' . $vp_h . ' * ' . rtrim( rtrim( sprintf( '%.3f', $hold ), '0' ), '.' ) . ')' : $vp_h ) . ';';
if ( $pass ) { $scene_style .= 'pointer-events:none;'; }

echo '<div class="fw-parallax-scene ps--' . esc_attr( $placement ) . '" data-source="' . esc_attr( $source ) . '" data-intensity="' . esc_attr( (string) $intensity ) . '" style="' . esc_attr( $scene_style ) . '" aria-hidden="true">';

if ( $pinned ) { echo '<div class="fw-ps-viewport" style="height:' . esc_attr( $vp_h ) . ';">'; }

foreach ( $layers as $L ) {
	if ( ! is_array( $L ) ) { continue; }
	$url = ( isset( $L['image']['url'] ) ) ? (string) $L['image']['url'] : '';
	if ( '' === $url ) { continue; }

	$h   = in_array( ( $L['h_anchor'] ?? 'center' ), array( 'left', 'center', 'right', 'stretch' ), true ) ? $L['h_anchor'] : 'center';
	$v   = in_array( ( $L['v_anchor'] ?? 'bottom' ), array( 'bottom', 'center', 'top' ), true ) ? $L['v_anchor'] : 'bottom';
	$ox  = (float) ( $L['offset_x'] ?? 0 );
	$oy  = (float) ( $L['offset_y'] ?? 0 );
	$w   = max( 5, min( 140, (float) ( $L['width'] ?? 40 ) ) );
	$z   = max( 0, min( 20, (int) ( $L['z'] ?? 1 ) ) );
	$op  = max( 0, min( 100, (int) ( $L['opacity'] ?? 100 ) ) );
	$flip = ( $L['flip'] ?? 'no' ) === 'yes';

	$depth    = max( 0, min( 100, (int) ( $L['depth'] ?? 30 ) ) );
	$entrance = in_array( ( $L['entrance'] ?? 'up' ), array( 'none', 'up', 'down', 'left', 'right', 'fade', 'scale' ), true ) ? $L['entrance'] : 'up';
	$exit     = ( isset( $L['exit'] ) && in_array( $L['exit'], array( 'auto', 'none', 'up', 'down', 'left', 'right', 'fade', 'scale' ), true ) ) ? $L['exit'] : 'auto';
	// 'auto' = leave the way it entered; a layer with no entrance has nothing to reverse → no exit.
	$exit_dir = ( 'auto' === $exit ) ? $entrance : $exit;
	$delay    = max( 0, min( 2000, (int) ( $L['delay'] ?? 0 ) ) );
	$sway     = in_array( ( $L['sway'] ?? 'none' ), array( 'none', 'sway', 'bob', 'drift' ), true ) ? $L['sway'] : 'none';
	$sway_amt = max( 0, min( 12, (float) ( $L['sway_amt'] ?? 3 ) ) );

	// ANCHOR positioning + a static centering translate (kept off the motion elements).
	$ls  = 'z-index:' . $z . ';';
	$cx  = '0px'; $cy = '0px';
	if ( 'stretch' === $h ) { $ls .= 'left:0;right:0;width:100%;'; }
	else {
		$ls .= 'width:' . rtrim( rtrim( (string) $w, '0' ), '.' ) . '%;';
		if ( 'left' === $h )       { $ls .= 'left:' . $ox . '%;'; }
		elseif ( 'right' === $h )  { $ls .= 'right:' . ( -$ox ) . '%;'; }
		else { $ls .= 'left:calc(50% + ' . $ox . '%);'; $cx = '-50%'; }
	}
	if ( 'bottom' === $v )     { $ls .= 'bottom:' . $oy . '%;'; }
	elseif ( 'top' === $v )    { $ls .= 'top:' . $oy . '%;'; }
	else { $ls .= 'top:calc(50% + ' . $oy . '%);'; $cy = '-50%'; }
	if ( '-50%' !== $cx || '-50%' !== $cy ) { $ls .= 'transform:translate(' . $cx . ',' . $cy . ');'; }
	if ( 100 !== $op ) { $ls .= 'opacity:' . ( $op / 100 ) . ';'; }

	$sway_cls   = ( 'none' !== $sway ) ? ' ps-sway ps-sway--' . $sway : '';
	$sway_style = ( 'none' !== $sway ) ? ' style="--ps-sway:' . esc_attr( (string) $sway_amt ) . '"' : '';

	$enter_cls = 'fw-ps-enter ps-enter--' . $entrance;
	if ( 'none' !== $exit_dir ) { $enter_cls .= ' ps-exit--' . $exit_dir; }
	$exit_attr = ( 'none' === $exit_dir ) ? ' data-exit="none"' : '';

	echo '<div class="fw-ps-layer" style="' . esc_attr( $ls ) . '">'
		. '<div class="fw-ps-move" data-depth="' . esc_attr( (string) $depth ) . '">'
		. '<div class="fw-ps-sway' . esc_attr( $sway_cls ) . '"' . $sway_style . '>'
		. '<div class="' . esc_attr( $enter_cls ) . '" data-delay="' . esc_attr( (string) $delay ) . '"' . $exit_attr . '>'
		. '<img src="' . esc_url( $url ) . '" alt="" loading="lazy" decoding="async" class="' . ( $flip ? 'ps-flip' : '' ) . '">'
		. '</div></div></div></div>';
}

if ( $pinned ) { echo '</div>'; } // .fw-ps-viewport

echo '</div>';
