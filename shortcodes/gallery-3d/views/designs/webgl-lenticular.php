<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Lenticular (WebGL) — a ridged card like a lenticular postcard: one photo shows from one side and
 * the next from the other, so it flips between them as it tilts. Drawn by gallery-3d-webgl.js. An original Unyson+ design.
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
$attr['data-tdg-ridges'] = esc_attr( (int) max( 10, min( 60, round( (float) $dp( 'ridges', 28 ) ) ) ) );
$attr['data-tdg-tilt'] = esc_attr( max( 15, min( 50, (float) $dp( 'tilt', 30 ) ) ) );
$attr['data-tdg-card'] = esc_attr( max( 30, min( 95, (float) $dp( 'card_size', 72 ) ) ) );
$attr['data-tdg-movetime'] = esc_attr( max( 10, min( 90, (float) $dp( 'move_time', 40 ) ) ) );
$attr['data-tdg-follow'] = esc_attr( max( 0, min( 100, (float) $dp( 'follow', 50 ) ) ) );
$c = (string) $dp( 'frame_color', '#e9ecf2' );
if ( preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\\([0-9.,\\s%]+\\))$/', $c ) ) { $attr['data-tdg-frame'] = esc_attr( $c ); }
$attr['data-tdg-light'] = esc_attr( in_array( $dp( 'lighting', 'studio' ), array( 'soft', 'studio', 'dramatic' ), true ) ? $dp( 'lighting', 'studio' ) : 'studio' );

$webgl_count = count( $items );
include dirname( __FILE__ ) . '/webgl-common.php';
