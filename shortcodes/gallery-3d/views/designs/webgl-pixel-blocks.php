<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Pixel Blocks (WebGL) — one photo at a time, built from a grid of 3D blocks
 * that flip over in a wave to show the next photo on their backs; blocks near the pointer sink back. Drawn by gallery-3d-webgl.js. An original Unyson+ design.
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
$attr['data-tdg-pattern'] = esc_attr( in_array( $dp( 'pattern', 'diagonal' ), array( 'diagonal', 'rows', 'center', 'random' ), true ) ? $dp( 'pattern', 'diagonal' ) : 'diagonal' );
$attr['data-tdg-blocks'] = esc_attr( (int) max( 6, min( 30, round( (float) $dp( 'blocks', 14 ) ) ) ) );
$attr['data-tdg-depth'] = esc_attr( max( 10, min( 100, (float) $dp( 'depth', 40 ) ) ) );
$attr['data-tdg-gap'] = esc_attr( max( 0, min( 20, (float) $dp( 'gap', 4 ) ) ) );
$attr['data-tdg-push'] = esc_attr( max( 0, min( 100, (float) $dp( 'pointer_push', 50 ) ) ) );
$attr['data-tdg-movetime'] = esc_attr( max( 10, min( 90, (float) $dp( 'move_time', 50 ) ) ) );
$c_side_color = (string) $dp( 'side_color', '#2b2f38' );
if ( preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\\([0-9.,\\s%]+\\))$/', $c_side_color ) ) { $attr['data-tdg-side'] = esc_attr( $c_side_color ); }
$attr['data-tdg-light'] = esc_attr( in_array( $dp( 'lighting', 'studio' ), array( 'soft', 'studio', 'dramatic' ), true ) ? $dp( 'lighting', 'studio' ) : 'studio' );

$webgl_count = count( $items );
include dirname( __FILE__ ) . '/webgl-common.php';
