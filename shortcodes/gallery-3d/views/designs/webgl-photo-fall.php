<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Photo Fall (WebGL) — your photos drift down through space, tumbling and fluttering like leaves;
 * sweeping the pointer across them is a gust that blows them about. Drawn by gallery-3d-webgl.js. An original Unyson+ design.
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
$attr['data-tdg-count'] = esc_attr( (int) max( 6, min( 40, round( (float) $dp( 'photos', 18 ) ) ) ) );
$attr['data-tdg-card'] = esc_attr( max( 6, min( 30, (float) $dp( 'card_size', 16 ) ) ) );
$attr['data-tdg-flutter'] = esc_attr( max( 0, min( 100, (float) $dp( 'flutter', 50 ) ) ) );
$attr['data-tdg-wind'] = esc_attr( max( -100, min( 100, (float) $dp( 'wind', 0 ) ) ) );
$attr['data-tdg-depth'] = esc_attr( max( 0, min( 100, (float) $dp( 'depth', 50 ) ) ) );
$attr['data-tdg-push'] = esc_attr( max( 0, min( 100, (float) $dp( 'pointer_push', 50 ) ) ) );
$attr['data-tdg-light'] = esc_attr( in_array( $dp( 'lighting', 'studio' ), array( 'soft', 'studio', 'dramatic' ), true ) ? $dp( 'lighting', 'studio' ) : 'studio' );

$webgl_count = count( $items );
include dirname( __FILE__ ) . '/webgl-common.php';
