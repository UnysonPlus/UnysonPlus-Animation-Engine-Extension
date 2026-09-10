<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

$ext = fw_ext( 'animation-engine' );
if ( ! $ext ) {
	return;
}

// Shared pose engine + base CSS (handles are shared with the Text element, so they load once).
require_once $ext->get_path( '/includes/scroll-choreo/scroll-choreo-assets.php' );
if ( function_exists( 'fw_scroll_choreo_enqueue_base' ) ) {
	fw_scroll_choreo_enqueue_base();
}

// Image-specific layer CSS.
$base    = $ext->get_declared_URI( '/shortcodes/image-scroll-choreography' );
$version = $ext->manifest->get_version();
$css_path = __DIR__ . '/static/css/image-scroll-choreography.css';
$css_ver  = file_exists( $css_path ) ? ( $version . '.' . filemtime( $css_path ) ) : $version;

wp_enqueue_style(
	'fw-shortcode-image-scroll-choreography',
	$base . '/static/css/image-scroll-choreography.css',
	array( 'fw-scroll-choreo-base' ),
	$css_ver
);
