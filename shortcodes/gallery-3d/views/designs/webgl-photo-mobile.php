<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Photo Mobile (WebGL) — your photos hang from a balanced mobile whose arms turn
 * slowly; moving the pointer across it is a breeze that sets them spinning. Drawn by gallery-3d-webgl.js. An original Unyson+ design.
 *
 * @var callable $dp
 * @var array    $items, $attr
 * @var bool     $as_bg
 * @var string   $motion_mode, $motion_dir, $motion_hover
 * @var float    $motion_speed
 */

$attr['data-tdg-drive'] = esc_attr( empty( $as_bg ) ? $motion_mode : 'continuous' );
$attr['data-tdg-speed'] = esc_attr( max( 2, min( 120, $motion_speed ) ) );
$attr['data-tdg-dir']   = esc_attr( $motion_dir === 'right' ? -1 : 1 );
$attr['data-tdg-hover'] = esc_attr( $motion_hover );
$attr['data-tdg-allowdrag'] = '0';
$attr['data-tdg-count'] = esc_attr( (int) max( 3, min( 12, round( (float) $dp( 'photos', 7 ) ) ) ) );
$attr['data-tdg-card'] = esc_attr( max( 8, min( 30, (float) $dp( 'card_size', 18 ) ) ) );
$attr['data-tdg-breeze'] = esc_attr( max( 0, min( 100, (float) $dp( 'breeze', 50 ) ) ) );
$attr['data-tdg-sway'] = esc_attr( max( 0, min( 100, (float) $dp( 'sway', 50 ) ) ) );
$c_arm_color = (string) $dp( 'arm_color', '#3a3c41' );
if ( preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\\([0-9.,\\s%]+\\))$/', $c_arm_color ) ) { $attr['data-tdg-arm'] = esc_attr( $c_arm_color ); }
$attr['data-tdg-light'] = esc_attr( in_array( $dp( 'lighting', 'studio' ), array( 'soft', 'studio', 'dramatic' ), true ) ? $dp( 'lighting', 'studio' ) : 'studio' );

$webgl_count = count( $items );
include dirname( __FILE__ ) . '/webgl-common.php';
