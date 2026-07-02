<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — central Effects control.
 *
 * Two jobs, done centrally so no per-module file is touched:
 *
 *  1. CONSOLIDATE the scattered per-module "Enable X" switches into a single
 *     Theme Settings → Animations → **Effects** sub-tab (the enable-only effect modules:
 *     Scroll Motion, Hover, Physics, Parallax, Text, Backgrounds). The full-config tabs
 *     (Cursor, Page Transitions) and the other session's Scroll Loop keep their own tabs.
 *
 *  2. HIDE a module's options when its effect is disabled — so a switched-off module no
 *     longer clutters the element Animations tab (or the Styling tab, for Backgrounds).
 *     Previously the enable only gated the runtime; the picker still showed. Now it's gone.
 *
 * Scroll Motion had no enable of its own (always on) — this file gives it one (`animation_scroll`)
 * and gates both its field and its runtime, matching every other module.
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

	// Build the Effects box: Scroll Motion first (freshly added), then the collected switches
	// in a stable order. Skip any that weren't present (module inactive).
	$effects = array(
		'animation_scroll' => upw_effects_enable_multi(
			__( 'Enable Scroll Motion', 'fw' ),
			__( 'Scroll-driven GSAP motion (reveal, stagger, parallax, pin, scrub…). Off hides the Scroll Effect picker.', 'fw' )
		),
	);
	foreach ( array( 'animation_hover', 'animation_physics', 'animation_parallax', 'animation_marquee', 'animation_text', 'animation_bg' ) as $id ) {
		if ( isset( $collected[ $id ] ) ) {
			$effects[ $id ] = $collected[ $id ];
		}
	}
	// Infinite Scroll Loop has no tab of its own (it's a per-section control) — add its switch here.
	$effects['animation_scroll_loop'] = upw_effects_enable_multi(
		__( 'Enable Infinite Scroll Loop', 'fw' ),
		__( 'The seamless / infinite scroll loop for full-height sections. Off hides the Scroll Loop control on sections.', 'fw' )
	);

	$effects_tab = array(
		'title'   => __( 'Effects', 'fw' ),
		'type'    => 'tab',
		'options' => array(
			'upw_effects_box' => array(
				'title'   => __( 'Enable / disable effects', 'fw' ),
				'desc'    => __( 'Turn each engine effect on or off. A disabled effect is removed from every element\'s Animations tab and loads nothing on the front end.', 'fw' ),
				'type'    => 'box',
				// A `group` wraps the switches so they read as one borderless set (house style).
				'options' => array(
					'group_effects' => array(
						'type'    => 'group',
						'options' => $effects,
					),
				),
			),
		),
	);

	return array_merge( array( 'upw_effects' => $effects_tab ), $tabs );
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
