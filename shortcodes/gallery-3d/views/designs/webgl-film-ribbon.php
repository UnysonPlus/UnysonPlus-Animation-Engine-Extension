<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Film Ribbon (WebGL) — your photos on a strip of film with sprocket holes,
 * flowing along a curving path through space and twisting as it goes. Drawn by gallery-3d-webgl.js. An original Unyson+ design.
 *
 * @var callable $dp
 * @var array    $items, $attr
 * @var bool     $as_bg
 * @var string   $motion_mode, $motion_dir, $motion_hover
 * @var float    $motion_speed
 */

$attr['data-tdg-drive'] = esc_attr( empty( $as_bg ) ? $motion_mode : 'continuous' );
$attr['data-tdg-speed'] = esc_attr( max( 2, min( 120, $motion_speed ) ) );
$attr['data-tdg-dir']   = esc_attr( in_array( $motion_dir, array( 'right', 'down' ), true ) ? -1 : 1 );
$attr['data-tdg-hover'] = esc_attr( $motion_hover );
$attr['data-tdg-allowdrag'] = '0';
$attr['data-tdg-frames'] = esc_attr( (int) max( 3, min( 10, round( (float) $dp( 'frames', 5 ) ) ) ) );
$attr['data-tdg-wave'] = esc_attr( max( 0, min( 100, (float) $dp( 'wave', 50 ) ) ) );
$attr['data-tdg-twist'] = esc_attr( max( 0, min( 100, (float) $dp( 'twist', 40 ) ) ) );
$c_film_color = (string) $dp( 'film_color', '#16171b' );
if ( preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\\([0-9.,\\s%]+\\))$/', $c_film_color ) ) { $attr['data-tdg-film'] = esc_attr( $c_film_color ); }
$attr['data-tdg-holes'] = $dp( 'sprocket_holes', 'yes' ) === 'yes' ? '1' : '0';
$attr['data-tdg-light'] = esc_attr( in_array( $dp( 'lighting', 'studio' ), array( 'soft', 'studio', 'dramatic' ), true ) ? $dp( 'lighting', 'studio' ) : 'studio' );

$webgl_count = count( $items );
include dirname( __FILE__ ) . '/webgl-common.php';
