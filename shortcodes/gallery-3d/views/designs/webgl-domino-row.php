<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Domino Row (WebGL) — your photos stand in a row like dominoes, casting real
 * shadows; they topple one after another and stand back up. Drawn by gallery-3d-webgl.js. An original Unyson+ design.
 *
 * @var callable $dp
 * @var array    $items, $attr
 * @var bool     $as_bg
 * @var string   $motion_mode, $motion_dir, $motion_hover
 * @var float    $motion_speed
 */

$attr['data-tdg-drive'] = esc_attr( empty( $as_bg ) ? $motion_mode : 'continuous' );
$attr['data-tdg-speed'] = esc_attr( max( 2, min( 40, $motion_speed ) ) );
$attr['data-tdg-dir']   = esc_attr( $motion_dir === 'right' ? -1 : 1 );
$attr['data-tdg-hover'] = esc_attr( $motion_hover );
$attr['data-tdg-allowdrag'] = '0';
$attr['data-tdg-count'] = esc_attr( (int) max( 4, min( 16, round( (float) $dp( 'dominoes', 8 ) ) ) ) );
$attr['data-tdg-spacing'] = esc_attr( max( 25, min( 90, (float) $dp( 'spacing', 50 ) ) ) );
$attr['data-tdg-view'] = esc_attr( max( 0, min( 70, (float) $dp( 'view_angle', 55 ) ) ) );
$attr['data-tdg-camh'] = esc_attr( max( 0, min( 50, (float) $dp( 'camera_height', 18 ) ) ) );
$c_domino_color = (string) $dp( 'domino_color', '#f4f2ee' );
if ( preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\\([0-9.,\\s%]+\\))$/', $c_domino_color ) ) { $attr['data-tdg-body'] = esc_attr( $c_domino_color ); }
$attr['data-tdg-shadows'] = $dp( 'shadows', 'yes' ) === 'yes' ? '1' : '0';

$webgl_count = count( $items );
include dirname( __FILE__ ) . '/webgl-common.php';
