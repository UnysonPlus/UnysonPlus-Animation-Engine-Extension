<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Holo Cards (WebGL) — your photos as collectible cards with a holographic foil;
 * a card tilts toward the pointer, the foil shifts colour as it turns and a glare follows the pointer. Drawn by gallery-3d-webgl.js. An original Unyson+ design.
 *
 * @var callable $dp
 * @var array    $items, $attr
 * @var bool     $as_bg
 * @var string   $motion_mode, $motion_dir, $motion_hover
 * @var float    $motion_speed
 */

$attr['data-tdg-drive'] = esc_attr( empty( $as_bg ) ? $motion_mode : 'continuous' );
$attr['data-tdg-speed'] = esc_attr( max( 2, min( 30, $motion_speed ) ) );
$attr['data-tdg-dir']   = esc_attr( $motion_dir === 'right' ? -1 : 1 );
$attr['data-tdg-hover'] = esc_attr( $motion_hover );
$attr['data-tdg-allowdrag'] = '0';
$attr['data-tdg-cols'] = esc_attr( (int) max( 1, min( 8, round( (float) $dp( 'columns', 4 ) ) ) ) );
$attr['data-tdg-rows'] = esc_attr( (int) max( 1, min( 4, round( (float) $dp( 'rows', 1 ) ) ) ) );
$attr['data-tdg-gap'] = esc_attr( max( 0, min( 40, (float) $dp( 'gap', 10 ) ) ) );
$attr['data-tdg-foil'] = esc_attr( max( 0, min( 100, (float) $dp( 'foil', 70 ) ) ) );
$attr['data-tdg-tilt'] = esc_attr( max( 0, min( 35, (float) $dp( 'tilt', 18 ) ) ) );
$attr['data-tdg-glare'] = esc_attr( max( 0, min( 100, (float) $dp( 'glare', 50 ) ) ) );
$attr['data-tdg-pattern'] = esc_attr( in_array( $dp( 'pattern', 'rainbow' ), array( 'rainbow', 'sparkle', 'lines' ), true ) ? $dp( 'pattern', 'rainbow' ) : 'rainbow' );

$webgl_count = count( $items );
include dirname( __FILE__ ) . '/webgl-common.php';
