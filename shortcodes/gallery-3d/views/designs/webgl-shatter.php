<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Shatter (WebGL) — one photo at a time; the photo breaks into shards that
 * turn and catch the light as they fly toward you, fall or burst outward, revealing the next photo. Drawn by gallery-3d-webgl.js. An original Unyson+ design.
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
$attr['data-tdg-shape']    = esc_attr( $dp( 'shape', 'shards' ) === 'tiles' ? 'tiles' : 'shards' );
$attr['data-tdg-burst']    = esc_attr( in_array( $dp( 'burst', 'toward' ), array( 'toward', 'fall', 'outward' ), true ) ? $dp( 'burst', 'toward' ) : 'toward' );
$attr['data-tdg-origin']   = esc_attr( in_array( $dp( 'origin', 'center' ), array( 'center', 'left', 'top', 'random' ), true ) ? $dp( 'origin', 'center' ) : 'center' );
$attr['data-tdg-pieces']   = esc_attr( (int) max( 4, min( 30, round( (float) $dp( 'pieces', 14 ) ) ) ) );
$attr['data-tdg-spin']     = esc_attr( max( 0, min( 100, (float) $dp( 'spin', 60 ) ) ) );
$attr['data-tdg-force']    = esc_attr( max( 0, min( 100, (float) $dp( 'force', 60 ) ) ) );
$attr['data-tdg-movetime'] = esc_attr( max( 10, min( 90, (float) $dp( 'move_time', 45 ) ) ) );

$webgl_count = count( $items );
include dirname( __FILE__ ) . '/webgl-common.php';
