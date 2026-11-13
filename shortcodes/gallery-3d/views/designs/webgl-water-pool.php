<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Water Pool (WebGL) — your photos lie at the bottom of a pool; the pointer
 * (and an occasional drop of rain) sends ripples across the water that bend the photos and catch the light. Drawn by gallery-3d-webgl.js. An original Unyson+ design.
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
$attr['data-tdg-cols'] = esc_attr( (int) max( 2, min( 8, round( (float) $dp( 'columns', 4 ) ) ) ) );
$attr['data-tdg-rows'] = esc_attr( (int) max( 1, min( 5, round( (float) $dp( 'rows', 2 ) ) ) ) );
$attr['data-tdg-gap'] = esc_attr( max( 0, min( 20, (float) $dp( 'gap', 4 ) ) ) );
$attr['data-tdg-refract'] = esc_attr( max( 0, min( 100, (float) $dp( 'refraction', 50 ) ) ) );
$attr['data-tdg-rain'] = esc_attr( max( 0, min( 100, (float) $dp( 'rain', 30 ) ) ) );
$attr['data-tdg-shine'] = esc_attr( max( 0, min( 100, (float) $dp( 'shine', 50 ) ) ) );
$c_water_color = (string) $dp( 'water_color', '#2a8fbf' );
if ( preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\\([0-9.,\\s%]+\\))$/', $c_water_color ) ) { $attr['data-tdg-water'] = esc_attr( $c_water_color ); }

$webgl_count = count( $items );
include dirname( __FILE__ ) . '/webgl-common.php';
