<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Photo Bubbles (WebGL) — your photos inside soap bubbles that rise and wobble,
 * their rims shimmering with thin-film colours; the pointer nudges them aside. Drawn by gallery-3d-webgl.js. An original Unyson+ design.
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
$attr['data-tdg-count'] = esc_attr( (int) max( 4, min( 30, round( (float) $dp( 'bubbles', 12 ) ) ) ) );
$attr['data-tdg-card'] = esc_attr( max( 8, min( 45, (float) $dp( 'card_size', 22 ) ) ) );
$attr['data-tdg-irid'] = esc_attr( max( 0, min( 100, (float) $dp( 'iridescence', 70 ) ) ) );
$attr['data-tdg-depth'] = esc_attr( max( 0, min( 100, (float) $dp( 'depth', 50 ) ) ) );
$attr['data-tdg-wobble'] = esc_attr( max( 0, min( 100, (float) $dp( 'wobble', 50 ) ) ) );
$attr['data-tdg-push'] = esc_attr( max( 0, min( 100, (float) $dp( 'pointer_push', 50 ) ) ) );

$webgl_count = count( $items );
include dirname( __FILE__ ) . '/webgl-common.php';
