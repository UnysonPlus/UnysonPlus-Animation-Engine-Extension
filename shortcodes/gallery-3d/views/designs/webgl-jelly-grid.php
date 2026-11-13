<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Jelly Grid (WebGL) — a lit grid of photos that bulge and wobble like jelly
 * as the pointer passes; a click makes a photo jiggle. Drawn by gallery-3d-webgl.js. An original Unyson+ design.
 *
 * @var callable $dp
 * @var array    $items, $attr
 * @var bool     $as_bg
 * @var string   $motion_mode, $motion_dir, $motion_hover
 * @var float    $motion_speed
 */

$attr['data-tdg-drive'] = esc_attr( empty( $as_bg ) ? $motion_mode : 'continuous' );
$attr['data-tdg-speed'] = esc_attr( max( 2, min( 40, $motion_speed ) ) );
$attr['data-tdg-dir']   = esc_attr( $motion_dir === 'right' ? -1 : 1 );
$attr['data-tdg-hover'] = esc_attr( $motion_hover );
$attr['data-tdg-allowdrag'] = '0';
$attr['data-tdg-cols'] = esc_attr( (int) max( 2, min( 10, round( (float) $dp( 'columns', 5 ) ) ) ) );
$attr['data-tdg-rows'] = esc_attr( (int) max( 1, min( 6, round( (float) $dp( 'rows', 3 ) ) ) ) );
$attr['data-tdg-gap'] = esc_attr( max( 0, min( 30, (float) $dp( 'gap', 6 ) ) ) );
$attr['data-tdg-jelly'] = esc_attr( max( 0, min( 100, (float) $dp( 'jelly', 60 ) ) ) );
$attr['data-tdg-wobble'] = esc_attr( max( 0, min( 100, (float) $dp( 'wobble', 60 ) ) ) );
$attr['data-tdg-wave'] = esc_attr( max( 0, min( 100, (float) $dp( 'wave', 30 ) ) ) );
$attr['data-tdg-view'] = esc_attr( max( 0, min( 45, (float) $dp( 'view_angle', 10 ) ) ) );
$attr['data-tdg-gloss'] = esc_attr( max( 0, min( 100, (float) $dp( 'gloss', 40 ) ) ) );
$attr['data-tdg-light'] = esc_attr( in_array( $dp( 'lighting', 'studio' ), array( 'soft', 'studio', 'dramatic' ), true ) ? $dp( 'lighting', 'studio' ) : 'studio' );

$webgl_count = count( $items );
include dirname( __FILE__ ) . '/webgl-common.php';
