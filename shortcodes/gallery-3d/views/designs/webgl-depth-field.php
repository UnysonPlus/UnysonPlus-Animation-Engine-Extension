<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Depth Field (WebGL) — the photos float at real depth in fog and drift toward (or
 * away from) the viewer; the camera follows the pointer, so near photos shift more than far ones. Drawn by
 * gallery-3d-webgl.js. An original Unyson+ design.
 *
 * @var callable $dp
 * @var array    $items, $attr
 * @var bool     $as_bg
 * @var string   $motion_mode, $motion_dir, $motion_hover
 * @var float    $motion_speed
 */

$attr['data-tdg-drive']     = esc_attr( empty( $as_bg ) ? $motion_mode : 'continuous' );
$attr['data-tdg-speed']     = esc_attr( max( 4, min( 90, $motion_speed ) ) );
$attr['data-tdg-dir']       = esc_attr( $motion_dir === 'away' ? -1 : 1 );
$attr['data-tdg-hover']     = esc_attr( $motion_hover );
$attr['data-tdg-allowdrag'] = '0';
$attr['data-tdg-count']     = esc_attr( (int) max( 4, min( 60, round( (float) $dp( 'cards', 18 ) ) ) ) );
$attr['data-tdg-depth']     = esc_attr( max( 0, min( 100, (float) $dp( 'depth', 60 ) ) ) );
$attr['data-tdg-spread']    = esc_attr( max( 40, min( 160, (float) $dp( 'spread', 100 ) ) ) );
$attr['data-tdg-fog']       = esc_attr( max( 0, min( 100, (float) $dp( 'fog', 55 ) ) ) );
$attr['data-tdg-parallax']  = esc_attr( empty( $as_bg ) ? max( 0, min( 100, (float) $dp( 'parallax', 40 ) ) ) : 0 );
$attr['data-tdg-light']     = esc_attr( in_array( $dp( 'lighting', 'studio' ), array( 'soft', 'studio', 'dramatic' ), true ) ? $dp( 'lighting', 'studio' ) : 'studio' );
$fog = $dp( 'fog_color', '' );
if ( is_string( $fog ) && preg_match( '/^#[0-9a-fA-F]{3,8}$|^rgba?\([\d.,\s%]+\)$/', $fog ) ) { $attr['data-tdg-fogcolor'] = esc_attr( $fog ); }

$webgl_count = count( $items );
include dirname( __FILE__ ) . '/webgl-common.php';
