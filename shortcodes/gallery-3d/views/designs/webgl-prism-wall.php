<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Prism Wall (WebGL) — a row of three-sided prisms, each holding a slice of the
 * photo, turning in a wave to show the next one; the pointer turns the prisms near it.
 * Drawn by gallery-3d-webgl.js. An original Unyson+ design.
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
$attr['data-tdg-count'] = esc_attr( (int) max( 4, min( 40, round( (float) $dp( 'prisms', 14 ) ) ) ) );
$attr['data-tdg-gap'] = esc_attr( max( 0, min( 40, (float) $dp( 'gap', 6 ) ) ) );
$attr['data-tdg-from'] = esc_attr( in_array( $dp( 'wave_from', 'left' ), array( 'left', 'right', 'center', 'random' ), true ) ? $dp( 'wave_from', 'left' ) : 'left' );
$attr['data-tdg-wave'] = esc_attr( max( 0, min( 100, (float) $dp( 'wave', 50 ) ) ) );
$attr['data-tdg-peek'] = esc_attr( max( 0, min( 100, (float) $dp( 'peek', 50 ) ) ) );
$attr['data-tdg-movetime'] = esc_attr( max( 20, min( 90, (float) $dp( 'move_time', 55 ) ) ) );
$attr['data-tdg-card'] = esc_attr( max( 50, min( 100, (float) $dp( 'card_size', 88 ) ) ) );

$webgl_count = count( $items );
include dirname( __FILE__ ) . '/webgl-common.php';
