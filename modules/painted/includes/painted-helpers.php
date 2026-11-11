<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Painted module: helpers (the enable switch and the style list).
 */

if ( ! function_exists( 'upw_painted_enabled' ) ) :
	function upw_painted_enabled() {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return true;
		}
		$v = fw_get_db_settings_option( 'animation_painted', array() );
		$e = ( is_array( $v ) && isset( $v['enable'] ) ) ? $v['enable'] : 'yes';
		return $e !== 'no' && $e !== false;
	}
endif;

if ( ! function_exists( 'upw_painted_styles' ) ) :
	/**
	 * The styles and their defaults. `role` says what the main colour paints: the fill (a wash, a
	 * highlight, hatching) or the line (a frame, an underline, a circle).
	 */
	function upw_painted_styles() {
		return array(
			'wash'      => array( 'label' => __( 'Watercolour Wash', 'fw' ), 'role' => 'fill', 'medium' => 'pencil', 'color' => '#8ecae6', 'spread' => 18, 'outline' => 'no',  'size' => 1,   'bleed' => 0.2 ),
			'frame'     => array( 'label' => __( 'Sketched Frame', 'fw' ),   'role' => 'line', 'medium' => 'pencil', 'color' => '#1c1e22', 'spread' => 10, 'outline' => 'yes', 'size' => 1.4, 'bleed' => 0 ),
			'highlight' => array( 'label' => __( 'Marker Highlight', 'fw' ), 'role' => 'fill', 'medium' => 'marker', 'color' => '#ffd84d', 'spread' => 6,  'outline' => 'no',  'size' => 1,   'bleed' => 0 ),
			'underline' => array( 'label' => __( 'Brush Underline', 'fw' ),  'role' => 'line', 'medium' => 'crayon', 'color' => '#e63946', 'spread' => 8,  'outline' => 'yes', 'size' => 1.2, 'bleed' => 0 ),
			'circle'    => array( 'label' => __( 'Ink Circle', 'fw' ),       'role' => 'line', 'medium' => 'ink',    'color' => '#e63946', 'spread' => 16, 'outline' => 'yes', 'size' => 1.4, 'bleed' => 0 ),
			'hatch'     => array( 'label' => __( 'Hatched', 'fw' ),          'role' => 'fill', 'medium' => 'pencil', 'color' => '#2a9d8f', 'spread' => 12, 'outline' => 'no',  'size' => 1,   'bleed' => 0, 'strength' => 0.45 ),
		);
	}
endif;

if ( ! function_exists( 'upw_painted_hex' ) ) :
	/** A colour option's value as a real hex (the brushes cannot use a CSS variable). */
	function upw_painted_hex( $val, $fallback ) {
		if ( function_exists( 'sc_color_to_css' ) ) {
			$c = sc_color_to_css( $val, $fallback, true );
		} else {
			$c = is_string( $val ) && $val !== '' ? $val : $fallback;
		}
		return preg_match( '/^#[0-9a-fA-F]{3,8}$/', (string) $c ) ? $c : $fallback;
	}
endif;
