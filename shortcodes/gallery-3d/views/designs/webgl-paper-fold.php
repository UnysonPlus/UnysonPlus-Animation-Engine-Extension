<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Paper Fold (WebGL) — one photo at a time; the sheet folds in half and in half again into a small
 * note, which unfolds as the next photo. Drawn by gallery-3d-webgl.js. An original Unyson+ design.
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
$attr['data-tdg-order'] = esc_attr( in_array( $dp( 'order', 'side' ), array( 'side', 'top' ), true ) ? $dp( 'order', 'side' ) : 'side' );
$attr['data-tdg-card'] = esc_attr( max( 40, min( 95, (float) $dp( 'card_size', 80 ) ) ) );
$attr['data-tdg-movetime'] = esc_attr( max( 20, min( 90, (float) $dp( 'move_time', 60 ) ) ) );
$attr['data-tdg-follow'] = esc_attr( max( 0, min( 100, (float) $dp( 'follow', 40 ) ) ) );
$c = (string) $dp( 'paper_color', '#f4f1ea' );
if ( preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\\([0-9.,\\s%]+\\))$/', $c ) ) { $attr['data-tdg-paper'] = esc_attr( $c ); }
$attr['data-tdg-light'] = esc_attr( in_array( $dp( 'lighting', 'studio' ), array( 'soft', 'studio', 'dramatic' ), true ) ? $dp( 'lighting', 'studio' ) : 'studio' );

$webgl_count = count( $items );
include dirname( __FILE__ ) . '/webgl-common.php';
