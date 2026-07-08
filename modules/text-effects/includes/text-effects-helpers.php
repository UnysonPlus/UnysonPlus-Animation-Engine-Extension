<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Text Effects module: helpers.
 *
 * The master-switch reader, the per-request "used" flag, the effect-id registry (single source
 * of truth for emit + wrapper checks) and the palette-preset color helpers. Loaded first by
 * text-effects.php (the settings + render parts depend on these). All wrapped in
 * function_exists guards.
 */

if ( ! function_exists( 'upw_text_enabled' ) ) :
	/** Global master switch (Theme Settings → Animations → Text). */
	function upw_text_enabled() {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return true;
		}
		$v = fw_get_db_settings_option( 'animation_text', array() );
		$e = ( is_array( $v ) && isset( $v['enable'] ) ) ? $v['enable'] : 'yes';
		return $e !== 'no' && $e !== false;
	}
endif;

if ( ! function_exists( 'upw_text_effects' ) ) :
	/** The valid text-effect ids — single source of truth for emit + wrapper checks. */
	function upw_text_effects() {
		return array(
			'split_reveal', 'scramble', 'typewriter', 'shimmer', 'wave', 'glitch', 'vf_weight',
			// Wave A — reveal variants
			'blur', 'mask', 'flip3d', 'scale', 'slide', 'bounce', 'random', 'skew',
			// Wave B — CSS-driven (continuous + emphasis)
			'gradient_flow', 'rainbow', 'neon', 'breathing', 'jitter', 'float',
			'marker', 'strikebox', 'outline_fill', 'chromatic', 'width_sweep',
			// Wave C — JS-driven (type/decode + interactive + media)
			'rotating_words', 'countup', 'splitflap', 'matrix', 'fill_sweep',
			'letter_jump', 'expand_spacing', 'color_wave', 'magnetic', 'image_mask', 'kinetic',
		);
	}
endif;

if ( ! function_exists( 'upw_text_color_field' ) ) :
	/** A palette-preset color field, reusing the hover module's helper when present. */
	function upw_text_color_field( $label, $kind = 'text', $default_hex = '', $desc = '' ) {
		if ( function_exists( 'upw_color_field' ) ) {
			return upw_color_field( $label, $kind, $default_hex, $desc );
		}
		$f = array( 'type' => 'color-picker', 'label' => $label, 'value' => $default_hex );
		if ( $desc !== '' ) { $f['desc'] = $desc; }
		return $f;
	}
endif;

if ( ! function_exists( 'upw_text_color' ) ) :
	/** Resolve a preset-or-custom color to a CSS string (reuses the hover resolver). */
	function upw_text_color( $val, $fallback = '' ) {
		if ( function_exists( 'upw_hover_color' ) ) {
			$c = upw_hover_color( $val );
			return $c !== '' ? $c : $fallback;
		}
		return is_string( $val ) && $val !== '' ? $val : $fallback;
	}
endif;
