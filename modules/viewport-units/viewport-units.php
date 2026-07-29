<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Viewport Units module.
 *
 * Publishes STABLE viewport-unit CSS custom properties site-wide on
 * document.documentElement: --vh = innerHeight/100 (px) and --vw = innerWidth/100
 * (px). Updated on resize + orientationchange only (never on scroll), so mobile
 * viewport units don't jump when the address bar shows/hides. Use them in custom
 * CSS as calc(var(--vh) * 100) for a jump-free 100vh, or var(--vw) for fluid
 * sizing. Tiny dependency-free JS, enqueued site-wide only when enabled.
 * Configured in Theme Settings → Animations → Viewport Units.
 */

if ( ! function_exists( 'upw_viewport_units_enabled' ) ) :
	function upw_viewport_units_enabled() {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return false;
		}
		$v = fw_get_db_settings_option( 'animation_viewport_units', array() );
		return is_array( $v ) && isset( $v['enable'] ) && $v['enable'] === 'yes';
	}
endif;

/* ------------------------------------------------------------------ *
 * 1) Theme Settings → Animations → Viewport Units.
 * ------------------------------------------------------------------ */
add_filter( 'upw_anim_engine_module_tabs', function ( $tabs ) {
	$tabs['viewport_units'] = array(
		'title'   => __( 'Viewport Units', 'fw' ),
		'type'    => 'tab',
		'options' => array(
			'viewport_units_box' => array(
				'title'   => __( 'Viewport Units', 'fw' ),
				'type'    => 'box',
				'options' => array(
					'animation_viewport_units' => array(
						'type'          => 'multi',
						'label'         => false,
						'inner-options' => array(
							'enable' => array(
								'label'        => __( 'Stable viewport units', 'fw' ),
								'desc'         => __( 'Publish stable <code>--vh</code> and <code>--vw</code> CSS custom properties on the root element (updated on resize/orientation change, not on scroll). Use them in custom CSS — e.g. <code>height: calc(var(--vh) * 100)</code> for a jump-free 100vh on mobile (no address-bar jump), or <code>var(--vw)</code> for fluid sizing. Front end only.', 'fw' ),
								'type'         => 'switch',
								'value'        => 'no',
								'left-choice'  => array( 'value' => 'no',  'label' => __( 'No', 'fw' ) ),
								'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
							),
						),
					),
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
	if ( is_admin() || ! upw_viewport_units_enabled() ) {
		return;
	}
	$ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( ! $ext ) {
		return;
	}
	$base = $ext->get_declared_URI( '/modules/viewport-units' );
	$ver  = $ext->manifest->get_version();
	$dir  = __DIR__;
	$fver = function ( $rel ) use ( $dir, $ver ) { $abs = $dir . $rel; return file_exists( $abs ) ? $ver . '.' . filemtime( $abs ) : $ver; };

	wp_enqueue_script( 'upw-viewport-units', $base . '/static/js/upw-viewport-units.js', array(), $fver( '/static/js/upw-viewport-units.js' ), true );
}, 20 );
