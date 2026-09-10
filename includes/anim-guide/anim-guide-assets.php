<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Shared enqueue for the Animation Engine authoring-guide HUD (window.upwAnimGuide + its CSS). Used by the
 * Scroll Choreography engine and the Model Viewer so both guides look and behave identically and co-stack.
 * The shared handles mean it loads exactly once per page however many guided elements appear. Cache-busted
 * on file mtime so an edit is picked up on a normal reload.
 */
if ( ! function_exists( 'upw_anim_guide_enqueue' ) ) :
	function upw_anim_guide_enqueue() {
		$ext = fw_ext( 'animation-engine' );
		if ( ! $ext ) {
			return '';
		}
		$dir = dirname( __FILE__ );
		$uri = $ext->get_declared_URI( '/includes/anim-guide' );
		$ver = $ext->manifest->get_version();
		$jsp = $dir . '/anim-guide.js';
		$csp = $dir . '/anim-guide.css';
		$jv  = file_exists( $jsp ) ? ( $ver . '.' . filemtime( $jsp ) ) : $ver;
		$cv  = file_exists( $csp ) ? ( $ver . '.' . filemtime( $csp ) ) : $ver;
		wp_enqueue_style( 'upw-anim-guide', $uri . '/anim-guide.css', array(), $cv );
		wp_enqueue_script( 'upw-anim-guide', $uri . '/anim-guide.js', array(), $jv, true );
		return 'upw-anim-guide'; // handle callers add as a script dependency
	}
endif;
