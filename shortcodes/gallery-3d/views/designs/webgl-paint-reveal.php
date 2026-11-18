<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Paint Reveal (WebGL) — the next photo is painted over the current one in broad
 * brush strokes (soft, dry brush or ink), and visitors can paint it in with the pointer.
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
$attr['data-tdg-brush'] = esc_attr( in_array( $dp( 'brush_style', 'soft' ), array( 'soft', 'dry', 'ink' ), true ) ? $dp( 'brush_style', 'soft' ) : 'soft' );
$attr['data-tdg-size'] = esc_attr( max( 5, min( 40, (float) $dp( 'brush_size', 16 ) ) ) );
$attr['data-tdg-thick'] = esc_attr( max( 0, min( 100, (float) $dp( 'thickness', 50 ) ) ) );
$attr['data-tdg-ptrpaint'] = $dp( 'pointer_paints', 'yes' ) === 'no' ? '0' : '1';
$attr['data-tdg-movetime'] = esc_attr( max( 20, min( 90, (float) $dp( 'move_time', 60 ) ) ) );

$webgl_count = count( $items );
include dirname( __FILE__ ) . '/webgl-common.php';
