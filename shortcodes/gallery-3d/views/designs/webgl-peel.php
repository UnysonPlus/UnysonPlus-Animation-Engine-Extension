<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Peel (WebGL) — one photo at a time; it peels off from a corner like a sticker, curling over
 * to show its back, and reveals the next photo underneath. Drawn by gallery-3d-webgl.js. An original Unyson+ design.
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
$attr['data-tdg-corner'] = esc_attr( in_array( $dp( 'corner', 'cycle' ), array( 'cycle', 'top-right', 'top-left', 'bottom-right', 'bottom-left' ), true ) ? $dp( 'corner', 'cycle' ) : 'cycle' );
$attr['data-tdg-curl'] = esc_attr( max( 0, min( 100, (float) $dp( 'curl', 50 ) ) ) );
$attr['data-tdg-lift'] = esc_attr( max( 0, min( 100, (float) $dp( 'lift', 40 ) ) ) );
$attr['data-tdg-movetime'] = esc_attr( max( 15, min( 90, (float) $dp( 'move_time', 45 ) ) ) );
$c = (string) $dp( 'paper_color', '#f4f1ea' );
if ( preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\\([0-9.,\\s%]+\\))$/', $c ) ) { $attr['data-tdg-paper'] = esc_attr( $c ); }
$attr['data-tdg-light'] = esc_attr( in_array( $dp( 'lighting', 'studio' ), array( 'soft', 'studio', 'dramatic' ), true ) ? $dp( 'lighting', 'studio' ) : 'studio' );

$webgl_count = count( $items );
include dirname( __FILE__ ) . '/webgl-common.php';
