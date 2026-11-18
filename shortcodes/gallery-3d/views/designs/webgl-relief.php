<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Photo Relief (WebGL) — the photo rises into a lit 3D relief, its bright (or dark)
 * parts standing up; a crest runs across as it reshapes into the next photo, and it tilts toward the pointer.
 * Drawn by gallery-3d-webgl.js. An original Unyson+ design.
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
$attr['data-tdg-depth'] = esc_attr( max( 0, min( 100, (float) $dp( 'relief_depth', 50 ) ) ) );
$attr['data-tdg-from'] = esc_attr( in_array( $dp( 'height_from', 'bright' ), array( 'bright', 'dark' ), true ) ? $dp( 'height_from', 'bright' ) : 'bright' );
$attr['data-tdg-smooth'] = esc_attr( max( 0, min( 100, (float) $dp( 'smoothness', 40 ) ) ) );
$attr['data-tdg-tilt'] = esc_attr( max( 0, min( 100, (float) $dp( 'tilt', 50 ) ) ) );
$attr['data-tdg-sweep'] = esc_attr( max( 0, min( 100, (float) $dp( 'light_sweep', 40 ) ) ) );
$attr['data-tdg-gloss'] = esc_attr( max( 0, min( 100, (float) $dp( 'gloss', 30 ) ) ) );
$attr['data-tdg-movetime'] = esc_attr( max( 15, min( 90, (float) $dp( 'move_time', 50 ) ) ) );
$attr['data-tdg-card'] = esc_attr( max( 50, min( 100, (float) $dp( 'card_size', 86 ) ) ) );

$webgl_count = count( $items );
include dirname( __FILE__ ) . '/webgl-common.php';
