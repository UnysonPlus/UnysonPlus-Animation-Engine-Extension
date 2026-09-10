<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

$ext = fw_ext( 'animation-engine' );
if ( ! $ext ) {
	return;
}

// Shared pose engine + base CSS (handles shared with the Image element → load once).
require_once $ext->get_path( '/includes/scroll-choreo/scroll-choreo-assets.php' );
if ( function_exists( 'fw_scroll_choreo_enqueue_base' ) ) {
	fw_scroll_choreo_enqueue_base();
}

// Text-specific CSS (layer max-width, warp wrapper, text reset). The Text Effects assets are enqueued
// on demand by upw_anim_use_asset() in view.php.
$base     = $ext->get_declared_URI( '/shortcodes/text-scroll-choreography' );
$version  = $ext->manifest->get_version();
$css_path = __DIR__ . '/static/css/text-scroll-choreography.css';
$js_path  = __DIR__ . '/static/js/text-scroll-choreography.js';
$css_ver  = file_exists( $css_path ) ? ( $version . '.' . filemtime( $css_path ) ) : $version;
$js_ver   = file_exists( $js_path ) ? ( $version . '.' . filemtime( $js_path ) ) : $version;

wp_enqueue_style(
	'fw-shortcode-text-scroll-choreography',
	$base . '/static/css/text-scroll-choreography.css',
	array( 'fw-scroll-choreo-base' ),
	$css_ver
);

// Curve renderer (only acts on layers that opted into a Curve warp).
wp_enqueue_script(
	'fw-shortcode-text-scroll-choreography',
	$base . '/static/js/text-scroll-choreography.js',
	array(),
	$js_ver,
	true
);
