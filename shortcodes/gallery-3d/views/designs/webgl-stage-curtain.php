<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Stage Curtain (WebGL) — one photo at a time on a stage; a pleated velvet
 * curtain closes over it and opens again on the next photo. Drawn by gallery-3d-webgl.js. An original Unyson+ design.
 *
 * @var callable $dp
 * @var array    $items, $attr
 * @var bool     $as_bg
 * @var string   $motion_mode, $motion_dir, $motion_hover
 * @var float    $motion_speed
 */

$attr['data-tdg-drive'] = esc_attr( empty( $as_bg ) ? $motion_mode : 'continuous' );
$attr['data-tdg-speed'] = esc_attr( max( 2, min( 20, $motion_speed ) ) );
$attr['data-tdg-dir']   = esc_attr( $motion_dir === 'right' ? -1 : 1 );
$attr['data-tdg-hover'] = esc_attr( $motion_hover );
$attr['data-tdg-allowdrag'] = '0';
$attr['data-tdg-swipe'] = ( empty( $as_bg ) && $dp( 'swipe', 'yes' ) === 'yes' ) ? '1' : '0';
$attr['data-tdg-pleats'] = esc_attr( (int) max( 4, min( 20, round( (float) $dp( 'pleats', 10 ) ) ) ) );
$attr['data-tdg-open'] = esc_attr( max( 50, min( 100, (float) $dp( 'opening', 88 ) ) ) );
$attr['data-tdg-swag'] = esc_attr( max( 0, min( 100, (float) $dp( 'swag', 50 ) ) ) );
$attr['data-tdg-movetime'] = esc_attr( max( 10, min( 90, (float) $dp( 'move_time', 50 ) ) ) );
$attr['data-tdg-valance'] = $dp( 'valance', 'yes' ) === 'yes' ? '1' : '0';
$c_curtain_color = (string) $dp( 'curtain_color', '#7a1424' );
if ( preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\\([0-9.,\\s%]+\\))$/', $c_curtain_color ) ) { $attr['data-tdg-curtain'] = esc_attr( $c_curtain_color ); }

$webgl_count = count( $items );
include dirname( __FILE__ ) . '/webgl-common.php';
