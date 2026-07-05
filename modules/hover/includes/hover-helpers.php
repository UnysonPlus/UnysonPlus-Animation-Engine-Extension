<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Hover module: helpers.
 *
 * Global switch reader, the per-request used-flag, the effect registry (single source of truth
 * for emit + wrapper checks), the color-field builder + resolver, and the multi-instance
 * collector. Loaded first by hover.php (the settings + render parts depend on these). All
 * wrapped in function_exists guards.
 */

if ( ! function_exists( 'upw_hover_enabled' ) ) :
	/** Global master switch (Theme Settings → Animations → Interactions). */
	function upw_hover_enabled() {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return true;
		}
		$v = fw_get_db_settings_option( 'animation_hover', array() );
		$e = ( is_array( $v ) && isset( $v['enable'] ) ) ? $v['enable'] : 'yes';
		return $e !== 'no' && $e !== false;
	}
endif;

if ( ! function_exists( 'upw_hover_flag' ) ) :
	/** Per-request "a hover effect rendered" flag → gates the footer enqueue. */
	function upw_hover_flag( $set = false ) {
		static $used = false;
		if ( $set ) {
			$used = true;
		}
		return $used;
	}
endif;

if ( ! function_exists( 'upw_hover_effects' ) ) :
	/** The valid hover-effect ids — single source of truth for emit + wrapper checks. */
	function upw_hover_effects() {
		return array(
			'magnetic', 'tilt', 'spotlight', 'image_reveal', 'text_scramble',
			'glow_border', 'underline_grow', 'ripple', 'lift', 'color_shift',
			'scale', 'push', 'jelly', 'skew', 'shine', 'gradient_border',
			'corner_brackets', 'fill_sweep', 'border_draw', 'glitch', 'text_swap',
			'rotate', 'pulse', 'shake', 'bounce', 'grayscale', 'blur',
			'brightness', 'bg_pan', 'outline', 'letter_spacing',
		);
	}
endif;

if ( ! function_exists( 'upw_color_field' ) ) :
	/**
	 * Build a color option using the shortcodes Styling-tab preset selector
	 * (predefined-colors-color-picker-compact) instead of a raw color-picker, so
	 * element colors stay tied to the theme palette. Falls back to a plain
	 * color-picker if the helper isn't available (engine without shortcodes).
	 */
	function upw_color_field( $label, $kind = 'bg', $default_hex = '', $desc = '' ) {
		if ( function_exists( 'sc_color_field_compact' ) ) {
			return sc_color_field_compact( array(
				'label' => $label,
				'kind'  => $kind,
				'value' => $default_hex !== '' ? array( 'predefined' => '', 'custom' => $default_hex ) : '',
				'desc'  => $desc,
			) );
		}
		$f = array( 'type' => 'color-picker', 'label' => $label, 'value' => $default_hex );
		if ( $desc !== '' ) {
			$f['desc'] = $desc;
		}
		return $f;
	}
endif;

if ( ! function_exists( 'upw_hover_color' ) ) :
	/**
	 * Resolve a preset-or-custom color value (from upw_color_field) to a CSS color
	 * string: a preset → var(--color-{slug}) (live-linked to Theme Settings); a
	 * custom color → its hex; a legacy plain string → passed through.
	 */
	function upw_hover_color( $val ) {
		if ( is_string( $val ) ) {
			return $val;
		}
		if ( ! is_array( $val ) ) {
			return '';
		}
		$pre = isset( $val['predefined'] ) ? trim( (string) $val['predefined'] ) : '';
		$cus = isset( $val['custom'] )     ? trim( (string) $val['custom'] )     : '';
		if ( $pre !== '' ) {
			$slug = preg_replace( '/[^a-z0-9\-]/', '', preg_replace( '/^(text|bg)-/', '', $pre ) );
			return $slug !== '' ? 'var(--color-' . $slug . ')' : '';
		}
		if ( $cus !== '' ) {
			return preg_replace( '/[^A-Za-z0-9#\(\),.%\s]/', '', $cus );
		}
		return '';
	}
endif;

/**
 * Collect every hover instance saved on an element — the base `interaction` plus any `interaction__N`
 * slots (multi-instance). Returns a list of [ 'effect' => key, 'settings' => array ] for the active
 * ones only, so a user can combine several hover effects (Lift + Ripple, …) on one element.
 */
if ( ! function_exists( 'upw_hover_instances' ) ) :
	function upw_hover_instances( $atts ) {
		$out = array();
		if ( ! is_array( $atts ) ) {
			return $out;
		}
		foreach ( $atts as $k => $v ) {
			if ( $k !== 'interaction' && ! preg_match( '/^interaction__\d+$/', (string) $k ) ) {
				continue;
			}
			if ( ! is_array( $v ) ) {
				continue;
			}
			$eff = isset( $v['effect'] ) ? (string) $v['effect'] : 'none';
			if ( ! in_array( $eff, upw_hover_effects(), true ) ) {
				continue;
			}
			$out[] = array(
				'effect'   => $eff,
				'settings' => ( isset( $v[ $eff ] ) && is_array( $v[ $eff ] ) ) ? $v[ $eff ] : array(),
			);
		}
		return $out;
	}
endif;
