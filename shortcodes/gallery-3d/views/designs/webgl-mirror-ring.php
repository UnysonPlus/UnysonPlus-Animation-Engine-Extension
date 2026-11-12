<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Mirror Ring (WebGL) — the photos stand on a turning ring above a glossy floor that
 * reflects them, lit by real lights. Drawn by gallery-3d-webgl.js. An original Unyson+ design.
 *
 * @var callable $dp
 * @var array    $items, $attr
 * @var bool     $as_bg, $motion_legacy_drag
 * @var string   $motion_mode, $motion_dir, $motion_hover
 * @var float    $motion_speed
 */

$drive     = empty( $as_bg ) ? $motion_mode : 'continuous';
$allowdrag = ( empty( $as_bg ) && ( $dp( 'allow_drag', 'yes' ) === 'yes' || ! empty( $motion_legacy_drag ) ) ) ? '1' : '0';
if ( count( $items ) > 40 ) { $items = array_slice( $items, 0, 40 ); }

$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-speed']     = esc_attr( max( 4, min( 90, $motion_speed ) ) );
$attr['data-tdg-dir']       = esc_attr( $motion_dir === 'right' ? -1 : 1 );
$attr['data-tdg-hover']     = esc_attr( $motion_hover );
$attr['data-tdg-allowdrag'] = $allowdrag;
$attr['data-tdg-momentum']  = ( $dp( 'drag_momentum', 'yes' ) === 'yes' ) ? '1' : '0';
$attr['data-tdg-ring']      = esc_attr( max( 40, min( 160, (float) $dp( 'ring_size', 90 ) ) ) );
$attr['data-tdg-camh']      = esc_attr( max( 0, min( 45, (float) $dp( 'camera_height', 14 ) ) ) );
$attr['data-tdg-reflect']   = esc_attr( max( 0, min( 100, (float) $dp( 'reflection', 45 ) ) ) );
$attr['data-tdg-card']      = esc_attr( max( 6, min( 50, (float) $dp( 'card_size', 20 ) ) ) );
$attr['data-tdg-light']     = esc_attr( in_array( $dp( 'lighting', 'studio' ), array( 'soft', 'studio', 'dramatic' ), true ) ? $dp( 'lighting', 'studio' ) : 'studio' );
$floor = $dp( 'floor_color', '' );
if ( is_string( $floor ) && preg_match( '/^#[0-9a-fA-F]{3,8}$|^rgba?\([\d.,\s%]+\)$/', $floor ) ) { $attr['data-tdg-floor'] = esc_attr( $floor ); }

$webgl_count = count( $items );
include dirname( __FILE__ ) . '/webgl-common.php';
