<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Bookshelf (WebGL) — the photos are the covers of books standing on wooden shelves, spines out;
 * one at a time a book slides out and turns to show its cover, and the pointer pulls out the book under it. Drawn by gallery-3d-webgl.js. An original Unyson+ design.
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
$attr['data-tdg-count'] = esc_attr( (int) max( 6, min( 30, round( (float) $dp( 'books', 16 ) ) ) ) );
$attr['data-tdg-shelves'] = esc_attr( (int) max( 1, min( 3, round( (float) $dp( 'shelves', 1 ) ) ) ) );
$attr['data-tdg-pull'] = esc_attr( max( 30, min( 100, (float) $dp( 'pull', 100 ) ) ) );
$attr['data-tdg-movetime'] = esc_attr( max( 15, min( 90, (float) $dp( 'move_time', 45 ) ) ) );
$c = (string) $dp( 'wood_color', '#6b4a32' );
if ( preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\\([0-9.,\\s%]+\\))$/', $c ) ) { $attr['data-tdg-wood'] = esc_attr( $c ); }
$attr['data-tdg-light'] = esc_attr( in_array( $dp( 'lighting', 'studio' ), array( 'soft', 'studio', 'dramatic' ), true ) ? $dp( 'lighting', 'studio' ) : 'studio' );

$webgl_count = count( $items );
include dirname( __FILE__ ) . '/webgl-common.php';
