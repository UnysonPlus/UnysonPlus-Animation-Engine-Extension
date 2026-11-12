<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Wave Wall (WebGL) — a grid of photos on a rippling surface; each photo tilts with
 * the wave and catches the key light, so highlights roll across the wall. Drawn by gallery-3d-webgl.js. An
 * original Unyson+ design.
 *
 * @var callable $dp
 * @var array    $items, $attr
 * @var bool     $as_bg
 * @var string   $motion_mode, $motion_dir, $motion_hover
 * @var float    $motion_speed
 */

$attr['data-tdg-drive']      = esc_attr( empty( $as_bg ) ? $motion_mode : 'continuous' );
$attr['data-tdg-speed']      = esc_attr( max( 2, min( 40, $motion_speed ) ) );
$attr['data-tdg-dir']        = esc_attr( $motion_dir === 'right' ? -1 : 1 );
$attr['data-tdg-hover']      = esc_attr( $motion_hover );
$attr['data-tdg-allowdrag']  = '0';
$attr['data-tdg-cols']       = esc_attr( (int) max( 2, min( 12, round( (float) $dp( 'columns', 6 ) ) ) ) );
$attr['data-tdg-rows']       = esc_attr( (int) max( 1, min( 8, round( (float) $dp( 'rows', 3 ) ) ) ) );
$attr['data-tdg-amp']        = esc_attr( max( 0, min( 100, (float) $dp( 'wave_height', 40 ) ) ) );
$attr['data-tdg-wavelength'] = esc_attr( max( 20, min( 200, (float) $dp( 'wave_length', 80 ) ) ) );
$attr['data-tdg-view']       = esc_attr( max( 0, min( 60, (float) $dp( 'view_angle', 25 ) ) ) );
$attr['data-tdg-lightang']   = esc_attr( max( -90, min( 90, (float) $dp( 'light_angle', -30 ) ) ) );
$attr['data-tdg-gloss']      = esc_attr( max( 0, min( 100, (float) $dp( 'gloss', 40 ) ) ) );
$attr['data-tdg-gap']        = esc_attr( max( 0, min( 30, (float) $dp( 'gap', 8 ) ) ) );
$attr['data-tdg-light']      = esc_attr( in_array( $dp( 'lighting', 'studio' ), array( 'soft', 'studio', 'dramatic' ), true ) ? $dp( 'lighting', 'studio' ) : 'studio' );

$webgl_count = count( $items );
include dirname( __FILE__ ) . '/webgl-common.php';
