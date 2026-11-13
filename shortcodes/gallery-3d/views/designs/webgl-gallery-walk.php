<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Gallery Walk (WebGL) — a slow walk down an art-gallery corridor, your photos
 * framed on both walls in pools of light, the far end lost in haze; the pointer looks around. Drawn by gallery-3d-webgl.js. An original Unyson+ design.
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
$attr['data-tdg-allowdrag'] = '0';
$c_wall_color = (string) $dp( 'wall_color', '#e9e4dc' );
if ( preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\\([0-9.,\\s%]+\\))$/', $c_wall_color ) ) { $attr['data-tdg-wall'] = esc_attr( $c_wall_color ); }
$c_floor_color = (string) $dp( 'floor_color', '#3a3029' );
if ( preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\\([0-9.,\\s%]+\\))$/', $c_floor_color ) ) { $attr['data-tdg-floor'] = esc_attr( $c_floor_color ); }
$c_frame_color = (string) $dp( 'frame_color', '#1c1c1c' );
if ( preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\\([0-9.,\\s%]+\\))$/', $c_frame_color ) ) { $attr['data-tdg-frame'] = esc_attr( $c_frame_color ); }
$attr['data-tdg-mat'] = $dp( 'mat', 'yes' ) === 'yes' ? '1' : '0';
$attr['data-tdg-pools'] = esc_attr( max( 0, min( 100, (float) $dp( 'light_pools', 60 ) ) ) );
$attr['data-tdg-spacing'] = esc_attr( max( 60, min( 180, (float) $dp( 'spacing', 100 ) ) ) );
$attr['data-tdg-look'] = esc_attr( max( 0, min( 100, (float) $dp( 'look_around', 40 ) ) ) );

$webgl_count = count( $items );
include dirname( __FILE__ ) . '/webgl-common.php';
