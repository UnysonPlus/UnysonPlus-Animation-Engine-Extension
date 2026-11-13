<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Liquid Slider (WebGL) — one photo at a time; the next flows in through a shader
 * transition (liquid, ripple, slices or swirl), with a soft ripple under the pointer and a slow zoom. Drawn by gallery-3d-webgl.js. An original Unyson+ design.
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
$attr['data-tdg-effect']   = esc_attr( in_array( $dp( 'effect', 'liquid' ), array( 'liquid', 'ripple', 'slices', 'swirl', 'pixelate', 'glitch', 'kaleido', 'tear', 'burn', 'zoom', 'mosaic', 'ink', 'spiral', 'leak', 'stretch', 'halftone', 'pinch', 'wind', 'focus', 'shutter' ), true ) ? $dp( 'effect', 'liquid' ) : 'liquid' );
$attr['data-tdg-strength'] = esc_attr( max( 0, min( 100, (float) $dp( 'strength', 50 ) ) ) );
$attr['data-tdg-movetime'] = esc_attr( max( 10, min( 90, (float) $dp( 'move_time', 35 ) ) ) );
$attr['data-tdg-ptr']      = esc_attr( max( 0, min( 100, (float) $dp( 'pointer', 30 ) ) ) );
$attr['data-tdg-zoom']     = esc_attr( max( 0, min( 20, (float) $dp( 'zoom', 6 ) ) ) );

$webgl_count = count( $items );
include dirname( __FILE__ ) . '/webgl-common.php';
