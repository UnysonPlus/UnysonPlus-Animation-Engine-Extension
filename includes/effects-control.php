<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — central Effects control.
 *
 * The per-element "Add Animation" inserter is the control surface, and every module's assets
 * already load only on pages that use them — so there is no global enable/disable "Effects" Theme
 * Settings tab. Modules therefore no longer register an enable-only tab (there is nothing to strip),
 * and this file just centralises the enable plumbing:
 *
 *  1. Defines `upw_scroll_enabled()` / `upw_scroll_loop_enabled()` — Scroll Motion and Infinite
 *     Scroll Loop ship without an enable of their own (both default to enabled), so the gate passes
 *     below have something to call.
 *
 *  2. Keeps the `upw_*_enabled()` helpers (defaulting to "yes") as the single choke point, plus the
 *     field-hide and runtime-gate passes below, as harmless no-op safety nets: with no UI to turn
 *     anything off they always report enabled. They remain the hook should a programmatic disable
 *     (filter/constant) ever be reintroduced.
 */

/* Scroll Motion enable (it ships without one). Mirrors the other modules' `upw_*_enabled()`. */
if ( ! function_exists( 'upw_scroll_enabled' ) ) :
	function upw_scroll_enabled() {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return true;
		}
		$v = fw_get_db_settings_option( 'animation_scroll', array() );
		$e = ( is_array( $v ) && isset( $v['enable'] ) ) ? $v['enable'] : 'yes';
		return $e !== 'no' && $e !== false;
	}
endif;

/* Infinite Scroll Loop also ships without a global enable (it's per-section) — give it one. */
if ( ! function_exists( 'upw_scroll_loop_enabled' ) ) :
	function upw_scroll_loop_enabled() {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return true;
		}
		$v = fw_get_db_settings_option( 'animation_scroll_loop', array() );
		$e = ( is_array( $v ) && isset( $v['enable'] ) ) ? $v['enable'] : 'yes';
		return $e !== 'no' && $e !== false;
	}
endif;

if ( ! function_exists( 'upw_effects_recursive_unset' ) ) :
	function upw_effects_recursive_unset( &$arr, $key ) {
		if ( ! is_array( $arr ) ) {
			return;
		}
		foreach ( $arr as $k => &$v ) {
			if ( $k === $key ) {
				unset( $arr[ $k ] );
				continue;
			}
			if ( is_array( $v ) ) {
				upw_effects_recursive_unset( $v, $key );
			}
		}
		unset( $v );
	}
endif;

/* ------------------------------------------------------------------ *
 * 1) Hide a disabled module's field from the element Animations tab.
 *    Priority 999 = after every module has added its field.
 * ------------------------------------------------------------------ */
add_filter( 'sc_animation_fields', function ( $fields ) {
	if ( ! is_array( $fields ) ) {
		return $fields;
	}
	$map = array(
		'gsap_motion' => 'upw_scroll_enabled',
		'interaction' => 'upw_hover_enabled',
		'physics'     => 'upw_physics_enabled',
		'parallax'    => 'upw_parallax_enabled',
		'marquee'     => 'upw_marquee_enabled',
		'text_effect' => 'upw_text_enabled',
	);
	foreach ( $map as $key => $fn ) {
		if ( isset( $fields[ $key ] ) && function_exists( $fn ) && ! call_user_func( $fn ) ) {
			unset( $fields[ $key ] );
		}
	}
	return $fields;
}, 999 );

/* Hide the Backgrounds "Background Effect" control (Styling tab) when disabled. */
add_filter( 'fw_shortcode_get_options', function ( $options, $tag ) {
	if ( function_exists( 'upw_bg_enabled' ) && ! upw_bg_enabled() ) {
		upw_effects_recursive_unset( $options, 'bg_effect' );
	}
	if ( function_exists( 'upw_scroll_loop_enabled' ) && ! upw_scroll_loop_enabled() ) {
		upw_effects_recursive_unset( $options, 'scroll_loop' );
	}
	return $options;
}, 20, 2 );

/* ------------------------------------------------------------------ *
 * 3) Gate the Scroll Motion + Infinite Scroll Loop RUNTIMES when disabled. The other
 *    modules self-check their `upw_*_enabled()` before emitting; these two don't, so we
 *    strip their data-attrs here (priority 30 = after both emits, Scroll Motion @25 and
 *    Scroll Loop @26). With the attrs gone the runtime finds no targets and no-ops.
 * ------------------------------------------------------------------ */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	if ( ! is_array( $attr ) ) {
		return $attr;
	}
	if ( function_exists( 'upw_scroll_enabled' ) && ! upw_scroll_enabled() ) {
		foreach ( array_keys( $attr ) as $k ) {
			if ( strpos( $k, 'data-upw-g' ) === 0 ) {
				unset( $attr[ $k ] );
			}
		}
		if ( isset( $attr['class'] ) ) {
			$attr['class'] = trim( preg_replace( '/\bupw-g-pending\b/', '', (string) $attr['class'] ) );
		}
	}
	if ( function_exists( 'upw_scroll_loop_enabled' ) && ! upw_scroll_loop_enabled() ) {
		foreach ( array_keys( $attr ) as $k ) {
			if ( strpos( $k, 'data-upw-loop' ) === 0 ) {
				unset( $attr[ $k ] );
			}
		}
	}
	return $attr;
}, 30, 2 );
