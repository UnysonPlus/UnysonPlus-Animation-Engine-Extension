<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — central Effects control.
 *
 * The per-element "Add Animation" inserter is now the control surface, and every module's assets
 * already load only on pages that use them — so the old global enable/disable "Effects" Theme
 * Settings tab was redundant and has been removed. This file now, centrally (no per-module file
 * touched):
 *
 *  1. STRIPS the enable-only module tabs from Theme Settings → Animations (they used to be folded
 *     into the "Effects" tab; now nothing is built). The full-config site-wide tabs (Cursor, Page
 *     Transitions, Scroll Progress, Engine) keep their own tabs — they carry real settings.
 *
 *  2. Keeps the `upw_*_enabled()` helpers (defaulting to "yes"), plus the field-hide and runtime-
 *     gate passes below, as harmless no-op safety nets: with no UI to turn anything off they always
 *     report enabled, so nothing is hidden or stripped. They remain the single choke point should a
 *     programmatic disable (filter/constant) ever be reintroduced.
 *
 * Scroll Motion and Infinite Scroll Loop ship without an enable of their own — this file defines
 * `upw_scroll_enabled()` / `upw_scroll_loop_enabled()` so the gate passes below have something to
 * call (both default to enabled).
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

/* A standard "enable" multi (same shape the modules use, so saved values round-trip). */
if ( ! function_exists( 'upw_effects_enable_multi' ) ) :
	function upw_effects_enable_multi( $label, $desc ) {
		return array(
			'type'          => 'multi',
			'label'         => false,
			'inner-options' => array(
				'enable' => array(
					'label'        => $label,
					'desc'         => $desc,
					'type'         => 'switch',
					'value'        => 'yes',
					'left-choice'  => array( 'value' => 'no',  'label' => __( 'No', 'fw' ) ),
					'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
				),
			),
		);
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
 * 1) Consolidate the enable-only module tabs into one "Effects" sub-tab.
 *    Runs after the modules register theirs (default 10) at priority 100.
 * ------------------------------------------------------------------ */
add_filter( 'upw_anim_engine_module_tabs', function ( $tabs ) {
	if ( ! is_array( $tabs ) ) {
		return $tabs;
	}

	// The enable switches we pull into the shared tab. Cursor / Page Transitions have full
	// config tabs (not just an enable), and Scroll Loop belongs to another module — all left as-is.
	$ids = array( 'animation_hover', 'animation_physics', 'animation_parallax', 'animation_marquee', 'animation_text', 'animation_bg' );

	$collected = array();
	foreach ( $tabs as $tab_key => &$tab ) {
		if ( empty( $tab['options'] ) || ! is_array( $tab['options'] ) ) {
			continue;
		}
		foreach ( $tab['options'] as $box_key => &$box ) {
			if ( empty( $box['options'] ) || ! is_array( $box['options'] ) ) {
				continue;
			}
			foreach ( $ids as $id ) {
				if ( isset( $box['options'][ $id ] ) ) {
					$collected[ $id ] = $box['options'][ $id ];
					unset( $box['options'][ $id ] );
				}
			}
			if ( empty( $box['options'] ) ) {
				unset( $tab['options'][ $box_key ] );
			}
		}
		unset( $box );
		if ( empty( $tab['options'] ) ) {
			unset( $tabs[ $tab_key ] );
		}
	}
	unset( $tab );
	unset( $collected );

	// The "Add Animation" inserter (per-element) is now the control surface, and every module's
	// assets already load only on pages that use them, so a global enable/disable Effects tab is
	// redundant. We therefore DROP the enable-only module tabs (stripped above) and DON'T build an
	// Effects tab. The `upw_*_enabled()` helpers stay and default to "yes", so every module remains
	// active. The full-config site-wide tabs (Cursor, Page Transitions, Scroll Progress, Engine)
	// are untouched — they carry real settings, not just an enable switch.
	return $tabs;
}, 100 );

/* ------------------------------------------------------------------ *
 * 2) Hide a disabled module's field from the element Animations tab.
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
