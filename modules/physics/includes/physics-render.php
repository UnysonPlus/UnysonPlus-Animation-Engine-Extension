<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Physics module: runtime / render.
 *
 * Emits the chosen effect onto the element wrapper (`sc_build_wrapper_attr`), forces a wrapper
 * when the only non-default setting is a physics effect (`sc_needs_wrapper`), and registers the
 * module's on-demand per-effect asset layout with the shared loader (`upw_anim_register_assets`).
 *
 * Uses UPW_PHYSICS_DIR (the module root) — not __DIR__ — to resolve the static-asset base path,
 * since this file lives in includes/.
 */

/* ------------------------------------------------------------------ *
 * 2) Emit the chosen effect onto the element wrapper.
 * ------------------------------------------------------------------ */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	if ( ! upw_physics_enabled() ) {
		return $attr;
	}
	$px     = ( isset( $atts['physics'] ) && is_array( $atts['physics'] ) ) ? $atts['physics'] : array();
	$effect = isset( $px['effect'] ) ? (string) $px['effect'] : 'none';
	if ( ! in_array( $effect, upw_physics_effects(), true ) ) {
		return $attr;
	}
	$o = ( isset( $px[ $effect ] ) && is_array( $px[ $effect ] ) ) ? $px[ $effect ] : array();

	$cls           = isset( $attr['class'] ) ? trim( (string) $attr['class'] ) : '';
	$attr['class'] = esc_attr( trim( $cls . ' sc-phys sc-phys--' . sanitize_html_class( $effect ) ) );
	$attr['data-phys'] = esc_attr( $effect );

	// Stamp every option present as data-phys-<key> (numbers/strings). Keeps the emit
	// compact now that there are many effects; the JS reads only the ones it needs.
	foreach ( $o as $k => $v ) {
		if ( is_array( $v ) ) { continue; }
		$attr[ 'data-phys-' . sanitize_html_class( str_replace( '_', '-', $k ) ) ] = esc_attr( (string) $v );
	}

	upw_physics_flag( true );
	// On-demand assets: record this effect so ONLY its JS partial (+ the tiny base CSS) is
	// enqueued, not the whole 27-effect bundle.
	if ( function_exists( 'upw_anim_use_asset' ) ) {
		upw_anim_use_asset( 'physics', $effect );
	}
	return $attr;
}, 22, 2 );

/* ------------------------------------------------------------------ *
 * 2b) Force a wrapper when an element's ONLY non-default setting is a physics effect.
 * ------------------------------------------------------------------ */
add_filter( 'sc_needs_wrapper', function ( $needs, $atts ) {
	if ( $needs || ! upw_physics_enabled() ) {
		return $needs;
	}
	$px     = ( isset( $atts['physics'] ) && is_array( $atts['physics'] ) ) ? $atts['physics'] : array();
	$effect = isset( $px['effect'] ) ? (string) $px['effect'] : 'none';
	return in_array( $effect, upw_physics_effects(), true );
}, 10, 2 );

/* ------------------------------------------------------------------ *
 * 3) On-demand assets. Register the module's per-effect partial layout with the shared
 *    loader; a page ships ONLY the shared core (integrator) + the used effects' partials
 *    — recorded per element in the wrapper filter via upw_anim_use_asset(). js_core_first:
 *    the core defines the shared integrator/helpers that each effect partial aliases.
 * ------------------------------------------------------------------ */
if ( function_exists( 'upw_anim_register_assets' ) ) {
	$upw_phys_ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( $upw_phys_ext ) {
		upw_anim_register_assets( 'physics', array(
			'path'          => UPW_PHYSICS_DIR,
			'uri'           => $upw_phys_ext->get_declared_URI( '/modules/physics' ),
			'ver'           => $upw_phys_ext->manifest->get_version(),
			'js_dir'        => 'static/js/effects',
			'base_css'      => 'static/css/physics.css',   // tiny, all-base (drag affordances) — no per-effect CSS
			'base_js'       => 'static/js/physics-core.js',
			'needs_raf'     => true,                        // uses the shared frame scheduler (window.upwAnimRaf)
			'js_core_first' => true,                        // core (integrator) loads before the effect partials
			'js_styles'     => upw_physics_effects(),       // every effect ships a JS partial (registers into window.upwPhys)
			// Pointer/drag/reaction helpers kept OUT of the core — loaded only when a pointer- or
			// trigger-driven effect (drag / spring / jelly / recoil / …) is on the page.
			'js_shared'     => array(
				'pointer' => array( 'draggable', 'slingshot', 'spring', 'attract', 'jelly', 'squash', 'recoil', 'shake', 'spin' ),
			),
			'js_cfg'        => function () {
				$cfg = array(
					'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
					'disableMobile' => ( function_exists( 'upw_anim_engine_setting' ) && upw_anim_engine_setting( 'disable_on_mobile', 'no' ) === 'yes' ),
				);
				return 'window.upwPhysicsCfg=' . wp_json_encode( $cfg ) . ';';
			},
		) );
	}
	unset( $upw_phys_ext );
}
