<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Particle Morph (WebGL) — each photo drawn as thousands of particles that scatter and
 * gather into the next photo (explode, swirl, drift or rain); the particles move away from the pointer. Drawn by gallery-3d-webgl.js. An original Unyson+ design.
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
$attr['data-tdg-effect']   = esc_attr( in_array( $dp( 'effect', 'explode' ), array( 'explode', 'swirl', 'drift', 'rain' ), true ) ? $dp( 'effect', 'explode' ) : 'explode' );
$attr['data-tdg-density']  = esc_attr( (int) max( 40, min( 240, round( (float) $dp( 'density', 140 ) ) ) ) );
$attr['data-tdg-psize']    = esc_attr( max( 40, min( 160, (float) $dp( 'particle', 90 ) ) ) );
$attr['data-tdg-scatter']  = esc_attr( max( 0, min( 100, (float) $dp( 'scatter', 50 ) ) ) );
$attr['data-tdg-movetime'] = esc_attr( max( 10, min( 90, (float) $dp( 'move_time', 50 ) ) ) );
$attr['data-tdg-repel']    = esc_attr( max( 0, min( 100, (float) $dp( 'repel', 40 ) ) ) );

$webgl_count = count( $items );
include dirname( __FILE__ ) . '/webgl-common.php';
