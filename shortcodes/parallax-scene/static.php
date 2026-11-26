<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

$ext = fw_ext( 'animation-engine' );
if ( ! $ext ) {
	return;
}

wp_enqueue_style(
	'fw-shortcode-parallax-scene',
	$ext->get_declared_URI( '/shortcodes/parallax-scene/static/css/styles.css' ),
	array(),
	$ext->manifest->get_version()
);

wp_enqueue_script(
	'fw-shortcode-parallax-scene',
	$ext->get_declared_URI( '/shortcodes/parallax-scene/static/js/scripts.js' ),
	function_exists( 'upw_anim_raf_handle' ) ? array( upw_anim_raf_handle() ) : array(), // the shared frame scheduler (window.upwAnimRaf)
	$ext->manifest->get_version(),
	true
);
