<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Shared enqueue for the Scroll Choreography pose engine + base CSS. Both the Image and Text Scroll
 * Choreography elements call this from their own static.php; the shared handles mean the engine + base
 * load exactly once even when both elements appear on a page.
 */
if ( ! function_exists( 'fw_scroll_choreo_enqueue_base' ) ) :
	function fw_scroll_choreo_enqueue_base() {
		$ext = fw_ext( 'animation-engine' );
		if ( ! $ext ) {
			return;
		}
		$dir = dirname( __FILE__ );
		$uri = $ext->get_declared_URI( '/includes/scroll-choreo' );
		$ver = $ext->manifest->get_version();
		$jsp = $dir . '/scroll-choreo-engine.js';
		$csp = $dir . '/scroll-choreo-base.css';
		$jv  = file_exists( $jsp ) ? ( $ver . '.' . filemtime( $jsp ) ) : $ver;
		$cv  = file_exists( $csp ) ? ( $ver . '.' . filemtime( $csp ) ) : $ver;

		// Shared authoring-guide HUD (window.upwAnimGuide) — the engine calls it when the guide is on.
		require_once dirname( __DIR__ ) . '/anim-guide/anim-guide-assets.php';
		$guide_dep = function_exists( 'upw_anim_guide_enqueue' ) ? upw_anim_guide_enqueue() : '';
		$deps      = $guide_dep ? array( $guide_dep ) : array();

		wp_enqueue_style( 'fw-scroll-choreo-base', $uri . '/scroll-choreo-base.css', array(), $cv );
		wp_enqueue_script( 'fw-scroll-choreo-engine', $uri . '/scroll-choreo-engine.js', $deps, $jv, true );
	}
endif;
