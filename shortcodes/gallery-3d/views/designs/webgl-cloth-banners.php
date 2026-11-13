<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Cloth Banners (WebGL) — your photos printed on fabric banners hanging from a
 * rod; the wind ripples them and the folds catch the light. They can drift along the rod. Drawn by gallery-3d-webgl.js. An original Unyson+ design.
 *
 * @var callable $dp
 * @var array    $items, $attr
 * @var bool     $as_bg
 * @var string   $motion_mode, $motion_dir, $motion_hover
 * @var float    $motion_speed
 */

$attr['data-tdg-drive'] = esc_attr( empty( $as_bg ) ? $motion_mode : 'continuous' );
$attr['data-tdg-speed'] = esc_attr( max( 2, min( 90, $motion_speed ) ) );
$attr['data-tdg-dir']   = esc_attr( $motion_dir === 'right' ? -1 : 1 );
$attr['data-tdg-hover'] = esc_attr( $motion_hover );
$attr['data-tdg-allowdrag'] = '0';
$attr['data-tdg-banners']  = esc_attr( (int) max( 2, min( 8, round( (float) $dp( 'banners', 4 ) ) ) ) );
$attr['data-tdg-wind']     = esc_attr( max( 0, min( 100, (float) $dp( 'wind', 50 ) ) ) );
$attr['data-tdg-ripples']  = esc_attr( max( 0.5, min( 5, (float) $dp( 'ripples', 2 ) ) ) );
$attr['data-tdg-gap']      = esc_attr( max( 0, min( 60, (float) $dp( 'gap', 14 ) ) ) );
$attr['data-tdg-view']     = esc_attr( max( 0, min( 40, (float) $dp( 'view_angle', 8 ) ) ) );
$c_rod_color = (string) $dp( 'rod_color', '#3a3c41' );
if ( preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\\([0-9.,\\s%]+\\))$/', $c_rod_color ) ) { $attr['data-tdg-rod'] = esc_attr( $c_rod_color ); }
$attr['data-tdg-light'] = esc_attr( in_array( $dp( 'lighting', 'studio' ), array( 'soft', 'studio', 'dramatic' ), true ) ? $dp( 'lighting', 'studio' ) : 'studio' );

$webgl_count = count( $items );
include dirname( __FILE__ ) . '/webgl-common.php';
