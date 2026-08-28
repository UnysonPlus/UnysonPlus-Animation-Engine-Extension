<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Smooth (inertia) Scroll module.
 *
 * Site-wide inertia smooth-scrolling powered by Lenis. Distinct from the theme's
 * "Smooth Scroll for Anchor Links" (a CSS scroll-behavior toggle) — this adds real
 * momentum wheel/trackpad smoothing across the whole page. Tiny self-contained JS,
 * enqueued site-wide only when enabled. Configured in Theme Settings → Site-wide UX
 * → Smooth Scroll (its own tab — a page-level behaviour, not a per-element effect).
 *
 * Coordinates with the Scroll Loop module through the shared window.__upwLenis
 * singleton: whichever boots first owns the single Lenis instance, so enabling both
 * never spawns two. Reuses the vendored Lenis lib under the SAME 'upw-lenis' script
 * handle, so the library file loads once even when both features are active.
 */

if ( ! function_exists( 'upw_smoothscroll_enabled' ) ) :
	/** Returns whether smooth scrolling is enabled in Theme Settings (defaults to off). */
	function upw_smoothscroll_enabled() {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return false;
		}
		$v = fw_get_db_settings_option( 'animation_smoothscroll', array() );
		return is_array( $v ) && isset( $v['enable'] ) && $v['enable'] === 'yes';
	}
endif;

/* ------------------------------------------------------------------ *
 * 1) Theme Settings → Site-wide UX → Smooth Scroll.
 * ------------------------------------------------------------------ */
add_filter( 'upw_anim_engine_module_tabs', function ( $tabs ) {
	$slider = function ( $label, $desc, $val, $min, $max, $step = 1 ) {
		return array( 'type' => 'slider', 'label' => $label, 'desc' => $desc, 'value' => $val, 'properties' => array( 'min' => $min, 'max' => $max, 'step' => $step ) );
	};

	$tabs['smooth_scroll'] = array(
		'title'   => __( 'Smooth Scroll', 'fw' ),
		'type'    => 'tab',
		'options' => array(
			'smooth_scroll_box' => array(
				'title'   => __( 'Smooth Scroll', 'fw' ),
				'type'    => 'box',
				'options' => array(
					// Grouped (show_borders => false) so the rows read as one panel with no
					// divider between each option. Visual only — the group flattens on save,
					// so every leaf key stays top-level (no migration).
					'smooth_scroll_group' => array(
						'type'         => 'group',
						'show_borders' => false,
						'options'      => array(
							'animation_smoothscroll' => array(
								'type'          => 'multi',
								'label'         => false,
								'inner-options' => array(
									'enable' => array(
										'label'        => __( 'Smooth (inertia) scrolling', 'fw' ),
										'desc'         => __( 'Add momentum wheel/trackpad smoothing across the whole page. This is separate from the theme\'s anchor-link "Smooth Scroll" (which only eases jumps to #anchors). Front end only, and automatically disabled for visitors who prefer reduced motion.', 'fw' ),
										'type'         => 'switch',
										'value'        => 'no',
										'left-choice'  => array( 'value' => 'no',  'label' => __( 'No', 'fw' ) ),
										'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
									),
								),
							),
							'ss_smoothness' => $slider(
								__( 'Smoothness', 'fw' ),
								__( 'How floaty the scroll feels. Lower is snappier (closer to native); higher glides longer.', 'fw' ),
								50, 0, 100, 1
							),
							'ss_speed' => $slider(
								__( 'Scroll speed (%)', 'fw' ),
								__( 'How far one wheel notch travels, relative to normal.', 'fw' ),
								100, 50, 200, 5
							),
							'ss_touch' => array(
								'type'         => 'switch',
								'label'        => __( 'Enable on touch devices', 'fw' ),
								'desc'         => __( 'Off by default — smoothing touch scrolling can feel wrong. Phones/tablets keep native scrolling unless you turn this on.', 'fw' ),
								'value'        => 'no',
								'left-choice'  => array( 'value' => 'no',  'label' => __( 'No', 'fw' ) ),
								'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
							),
						), // close group 'options'
					), // close smooth_scroll_group
				),
			),
		),
	);
	return $tabs;
} );

/* ------------------------------------------------------------------ *
 * 2) Enqueue site-wide (front end only) when enabled.
 * ------------------------------------------------------------------ */
add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() || ! upw_smoothscroll_enabled() ) {
		return;
	}
	$ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( ! $ext ) {
		return;
	}
	$base = $ext->get_declared_URI( '/modules/smooth-scroll' );
	$ver  = $ext->manifest->get_version();
	$dir  = __DIR__;
	$fver = function ( $rel ) use ( $dir, $ver ) { $abs = $dir . $rel; return file_exists( $abs ) ? $ver . '.' . filemtime( $abs ) : $ver; };

	// Lenis lib under the SHARED 'upw-lenis' handle (idempotent: if Scroll Loop already
	// registered it, this no-ops and the single copy is reused).
	if ( ! wp_script_is( 'upw-lenis', 'registered' ) ) {
		wp_register_script( 'upw-lenis', $base . '/static/js/vendor/lenis/lenis.min.js', array(), '1.1.18', true );
	}
	wp_enqueue_script( 'upw-lenis' );
	wp_enqueue_script( 'upw-smooth-scroll', $base . '/static/js/upw-smooth-scroll.js', array( 'upw-lenis' ), $fver( '/static/js/upw-smooth-scroll.js' ), true );

	// Smoothness slider → Lenis lerp (higher smoothness = lower lerp = floatier).
	$s      = (int) fw_get_db_settings_option( 'ss_smoothness', 50 );
	$s      = max( 0, min( 100, $s ) );
	$lerp   = round( 0.14 - ( $s / 100 ) * 0.10, 3 ); // 0.14 (snappy) … 0.04 (floaty)

	$speed  = (int) fw_get_db_settings_option( 'ss_speed', 100 );
	$speed  = max( 50, min( 200, $speed ) );
	$wheel  = round( $speed / 100, 2 );

	$touch  = fw_get_db_settings_option( 'ss_touch', 'no' ) === 'yes';
	$reduced = function_exists( 'upw_anim_engine_setting' ) ? ( upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ) : true;

	$cfg = array(
		'lerp'            => $lerp,
		'wheelMultiplier' => $wheel,
		'smoothTouch'     => $touch,
		'reducedMotion'   => $reduced,
	);
	wp_add_inline_script( 'upw-smooth-scroll', 'window.upwSmoothScrollCfg=' . wp_json_encode( $cfg ) . ';', 'before' );
}, 20 );
