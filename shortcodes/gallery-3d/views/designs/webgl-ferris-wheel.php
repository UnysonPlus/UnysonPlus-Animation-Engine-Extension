<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Ferris Wheel (WebGL) — your photos ride in gondolas on a turning Ferris wheel,
 * staying upright and swinging as it turns, with twinkling bulbs on the rim. Drawn by gallery-3d-webgl.js. An original Unyson+ design.
 *
 * @var callable $dp
 * @var array    $items, $attr
 * @var bool     $as_bg
 * @var string   $motion_mode, $motion_dir, $motion_hover
 * @var float    $motion_speed
 */

$attr['data-tdg-drive'] = esc_attr( empty( $as_bg ) ? $motion_mode : 'continuous' );
$attr['data-tdg-speed'] = esc_attr( max( 2, min( 120, $motion_speed ) ) );
$attr['data-tdg-dir']   = esc_attr( $motion_dir === 'right' ? -1 : 1 );
$attr['data-tdg-hover'] = esc_attr( $motion_hover );
$attr['data-tdg-allowdrag'] = ( empty( $as_bg ) && $dp( 'allow_drag', 'yes' ) === 'yes' ) ? '1' : '0';
$attr['data-tdg-momentum'] = $dp( 'drag_momentum', 'yes' ) === 'yes' ? '1' : '0';
$attr['data-tdg-count'] = esc_attr( (int) max( 6, min( 16, round( (float) $dp( 'gondolas', 10 ) ) ) ) );
$attr['data-tdg-card'] = esc_attr( max( 8, min( 34, (float) $dp( 'card_size', 26 ) ) ) );
$attr['data-tdg-swing'] = esc_attr( max( 0, min( 100, (float) $dp( 'swing', 50 ) ) ) );
$attr['data-tdg-view'] = esc_attr( max( 0, min( 40, (float) $dp( 'view_angle', 12 ) ) ) );
$c_wheel_color = (string) $dp( 'wheel_color', '#e9ecf2' );
if ( preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\\([0-9.,\\s%]+\\))$/', $c_wheel_color ) ) { $attr['data-tdg-wheel'] = esc_attr( $c_wheel_color ); }
$attr['data-tdg-bulbs'] = $dp( 'bulbs', 'yes' ) === 'yes' ? '1' : '0';
$attr['data-tdg-light'] = esc_attr( in_array( $dp( 'lighting', 'studio' ), array( 'soft', 'studio', 'dramatic' ), true ) ? $dp( 'lighting', 'studio' ) : 'studio' );

$webgl_count = count( $items );
include dirname( __FILE__ ) . '/webgl-common.php';
