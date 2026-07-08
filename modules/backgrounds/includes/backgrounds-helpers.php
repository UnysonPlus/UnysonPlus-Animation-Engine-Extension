<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Animated Backgrounds module: helpers.
 *
 * Setting reader, the enable flag / used flag, the effect + container registries, and the
 * color field / color-resolver helpers. Loaded first by backgrounds.php (the settings + render
 * parts depend on these). All wrapped in function_exists guards.
 */

if ( ! function_exists( 'upw_bg_enabled' ) ) :
	function upw_bg_enabled() {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return true;
		}
		$v = fw_get_db_settings_option( 'animation_bg', array() );
		$e = ( is_array( $v ) && isset( $v['enable'] ) ) ? $v['enable'] : 'yes';
		return $e !== 'no' && $e !== false;
	}
endif;

if ( ! function_exists( 'upw_bg_effects' ) ) :
	function upw_bg_effects() {
		return array(
			'aurora', 'gradient', 'dots', 'particles', 'constellation', 'waves', 'starfield', 'noise',
			// Wave 1 — CSS-driven
			'mesh', 'grid', 'orbs', 'conic', 'scanlines', 'rays',
			// Wave 2 — canvas particle-family
			'snow', 'confetti', 'bubbles', 'fireflies', 'bokeh', 'rain', 'shapes', 'meteors',
			// Wave 3 — canvas structural / fluid
			'pgrid', 'hexgrid', 'topo', 'circuit', 'halftone', 'blobs', 'ripple', 'flow', 'matrix', 'nebula', 'borealis', 'orbits', 'spotlight',
		);
	}
endif;

if ( ! function_exists( 'upw_bg_containers' ) ) :
	/** Shortcode tags that get the Background Effect option. */
	function upw_bg_containers() {
		return array( 'section', 'bleed-section', 'masonry-section', 'row' );
	}
endif;

if ( ! function_exists( 'upw_bg_color_field' ) ) :
	function upw_bg_color_field( $label, $kind, $default_hex, $desc = '' ) {
		if ( function_exists( 'upw_color_field' ) ) {
			return upw_color_field( $label, $kind, $default_hex, $desc );
		}
		$f = array( 'type' => 'color-picker', 'label' => $label, 'value' => $default_hex );
		if ( $desc !== '' ) { $f['desc'] = $desc; }
		return $f;
	}
endif;

if ( ! function_exists( 'upw_bg_css_color' ) ) :
	/** Resolve a preset/custom color to a CSS string (var() for presets, live-linked). */
	function upw_bg_css_color( $val, $fallback ) {
		if ( function_exists( 'sc_color_to_css' ) ) {
			$c = sc_color_to_css( $val, $fallback );
			return $c !== '' ? $c : $fallback;
		}
		return is_string( $val ) && $val !== '' ? $val : $fallback;
	}
endif;

if ( ! function_exists( 'upw_bg_hex' ) ) :
	/** Resolve a preset/custom color to a real hex (canvas can't use var()). */
	function upw_bg_hex( $val, $fallback ) {
		if ( function_exists( 'sc_color_to_css' ) ) {
			$c = sc_color_to_css( $val, $fallback, true );
			return $c !== '' ? $c : $fallback;
		}
		return is_string( $val ) && $val !== '' ? $val : $fallback;
	}
endif;
