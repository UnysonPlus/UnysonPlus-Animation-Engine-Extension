<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: TV Wall (WebGL) — a stacked wall of retro TVs, each showing a photo with scanlines on a curved
 * screen and flickering through static to the next photo on its own beat. Drawn by gallery-3d-webgl.js. An original Unyson+ design.
 *
 * @var callable $dp
 * @var array    $items, $attr
 * @var bool     $as_bg
 * @var string   $motion_mode, $motion_dir, $motion_hover
 * @var float    $motion_speed
 */

$attr['data-tdg-drive'] = esc_attr( empty( $as_bg ) ? $motion_mode : 'continuous' );
$attr['data-tdg-speed'] = esc_attr( max( 2, min( 30, $motion_speed ) ) );
$attr['data-tdg-dir']   = esc_attr( $motion_dir === 'right' ? -1 : 1 );
$attr['data-tdg-hover'] = esc_attr( $motion_hover );
$attr['data-tdg-allowdrag'] = '0';
$attr['data-tdg-count'] = esc_attr( (int) max( 4, min( 20, round( (float) $dp( 'tvs', 9 ) ) ) ) );
$c = (string) $dp( 'body_color', '#d8c9ad' );
if ( preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\\([0-9.,\\s%]+\\))$/', $c ) ) { $attr['data-tdg-body'] = esc_attr( $c ); }
$attr['data-tdg-mixed'] = $dp( 'mixed_colors', 'yes' ) === 'no' ? '0' : '1';
$attr['data-tdg-scan'] = esc_attr( max( 0, min( 100, (float) $dp( 'scanlines', 50 ) ) ) );
$attr['data-tdg-static'] = esc_attr( max( 0, min( 100, (float) $dp( 'static', 50 ) ) ) );
$attr['data-tdg-curve'] = esc_attr( max( 0, min( 100, (float) $dp( 'curve', 50 ) ) ) );
$attr['data-tdg-light'] = esc_attr( in_array( $dp( 'lighting', 'studio' ), array( 'soft', 'studio', 'dramatic' ), true ) ? $dp( 'lighting', 'studio' ) : 'studio' );

$webgl_count = count( $items );
include dirname( __FILE__ ) . '/webgl-common.php';
