<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Photo Cube (WebGL) — one photo at a time on a lit cube that turns a quarter-turn
 * (sideways or rolling forward) for the next photo, casting a soft shadow. Drawn by gallery-3d-webgl.js. An original Unyson+ design.
 *
 * @var callable $dp
 * @var array    $items, $attr
 * @var bool     $as_bg
 * @var string   $motion_mode, $motion_dir, $motion_hover
 * @var float    $motion_speed
 */

$attr['data-tdg-drive'] = esc_attr( empty( $as_bg ) ? $motion_mode : 'continuous' );
$attr['data-tdg-speed'] = esc_attr( max( 2, min( 20, $motion_speed ) ) );
$attr['data-tdg-dir']   = esc_attr( in_array( $motion_dir, array( 'right', 'down' ), true ) ? -1 : 1 );
$attr['data-tdg-hover'] = esc_attr( $motion_hover );
$attr['data-tdg-allowdrag'] = '0';
$attr['data-tdg-swipe'] = ( empty( $as_bg ) && $dp( 'swipe', 'yes' ) === 'yes' ) ? '1' : '0';
$attr['data-tdg-tumble'] = esc_attr( $dp( 'tumble', 'turn' ) === 'roll' ? 'roll' : 'turn' );
$attr['data-tdg-card'] = esc_attr( max( 20, min( 90, (float) $dp( 'card_size', 55 ) ) ) );
$attr['data-tdg-tilt'] = esc_attr( max( 0, min( 45, (float) $dp( 'tilt', 18 ) ) ) );
$attr['data-tdg-movetime'] = esc_attr( max( 10, min( 90, (float) $dp( 'move_time', 40 ) ) ) );
$attr['data-tdg-float'] = esc_attr( max( 0, min( 100, (float) $dp( 'float', 40 ) ) ) );
$c_cube_color = (string) $dp( 'cube_color', '#22252c' );
if ( preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\\([0-9.,\\s%]+\\))$/', $c_cube_color ) ) { $attr['data-tdg-body'] = esc_attr( $c_cube_color ); }
$attr['data-tdg-shadows'] = $dp( 'shadows', 'yes' ) === 'yes' ? '1' : '0';
$attr['data-tdg-light'] = esc_attr( in_array( $dp( 'lighting', 'studio' ), array( 'soft', 'studio', 'dramatic' ), true ) ? $dp( 'lighting', 'studio' ) : 'studio' );

$webgl_count = count( $items );
include dirname( __FILE__ ) . '/webgl-common.php';
