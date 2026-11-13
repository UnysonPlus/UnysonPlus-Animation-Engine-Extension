<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Flip Book (WebGL) — an open 3D book; its pages bend and catch the light as they
 * turn, two photos to a spread. Drawn by gallery-3d-webgl.js. An original Unyson+ design.
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
$attr['data-tdg-card']     = esc_attr( max( 20, min( 95, (float) $dp( 'card_size', 60 ) ) ) );
$attr['data-tdg-tilt']     = esc_attr( max( 0, min( 60, (float) $dp( 'tilt', 25 ) ) ) );
$attr['data-tdg-curl']     = esc_attr( max( 0, min( 100, (float) $dp( 'curl', 60 ) ) ) );
$attr['data-tdg-movetime'] = esc_attr( max( 10, min( 90, (float) $dp( 'move_time', 45 ) ) ) );
$fb_cover = (string) $dp( 'cover_color', '#2b303b' );
if ( preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\([0-9.,\s%]+\))$/', $fb_cover ) ) { $attr['data-tdg-cover'] = esc_attr( $fb_cover ); }
$attr['data-tdg-light']    = esc_attr( in_array( $dp( 'lighting', 'studio' ), array( 'soft', 'studio', 'dramatic' ), true ) ? $dp( 'lighting', 'studio' ) : 'studio' );

$webgl_count = count( $items );
include dirname( __FILE__ ) . '/webgl-common.php';
