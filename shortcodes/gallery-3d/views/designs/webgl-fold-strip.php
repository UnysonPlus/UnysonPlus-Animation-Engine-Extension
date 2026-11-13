<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Fold Strip (WebGL) — your photos on a paper strip folded like an accordion,
 * flowing sideways; the folds alternate light and shade, and the strip flattens out under the pointer. Drawn by gallery-3d-webgl.js. An original Unyson+ design.
 *
 * @var callable $dp
 * @var array    $items, $attr
 * @var bool     $as_bg
 * @var string   $motion_mode, $motion_dir, $motion_hover
 * @var float    $motion_speed
 */

$attr['data-tdg-drive'] = esc_attr( empty( $as_bg ) ? $motion_mode : 'continuous' );
$attr['data-tdg-speed'] = esc_attr( max( 2, min( 90, $motion_speed ) ) );
$attr['data-tdg-dir']   = esc_attr( $motion_dir === 'right' ? -1 : 1 );
$attr['data-tdg-hover'] = esc_attr( $motion_hover );
$attr['data-tdg-allowdrag'] = ( empty( $as_bg ) && $dp( 'allow_drag', 'yes' ) === 'yes' ) ? '1' : '0';
$attr['data-tdg-momentum']  = $dp( 'drag_momentum', 'yes' ) === 'yes' ? '1' : '0';
$attr['data-tdg-panels']   = esc_attr( (int) max( 3, min( 12, round( (float) $dp( 'panels', 6 ) ) ) ) );
$attr['data-tdg-fold']     = esc_attr( max( 0, min( 70, (float) $dp( 'fold', 35 ) ) ) );
$attr['data-tdg-unfold']   = $dp( 'unfold', 'yes' ) === 'yes' ? '1' : '0';
$attr['data-tdg-view']     = esc_attr( max( 0, min( 45, (float) $dp( 'view_angle', 12 ) ) ) );
$attr['data-tdg-light'] = esc_attr( in_array( $dp( 'lighting', 'studio' ), array( 'soft', 'studio', 'dramatic' ), true ) ? $dp( 'lighting', 'studio' ) : 'studio' );

$webgl_count = count( $items );
include dirname( __FILE__ ) . '/webgl-common.php';
