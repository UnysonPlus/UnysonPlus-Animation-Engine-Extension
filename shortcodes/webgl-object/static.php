<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

// The extension was renamed webgl → animation-engine; this is its machine name.
$ext = fw_ext( 'animation-engine' );
if ( ! $ext ) {
	return;
}

$base    = $ext->get_declared_URI( '/shortcodes/webgl-object' );
$version = $ext->manifest->get_version();

// Cache-bust on the actual file mtime so any code change forces a re-download
// even if the manifest version is cached. static.php sits in this folder, so
// __DIR__ is the shortcode dir.
$js_path  = __DIR__ . '/static/js/webgl-object.js';
$css_path = __DIR__ . '/static/css/webgl-object.css';
$js_ver   = file_exists( $js_path ) ? ( $version . '.' . filemtime( $js_path ) ) : $version;
$css_ver  = file_exists( $css_path ) ? ( $version . '.' . filemtime( $css_path ) ) : $version;

// Vendored Three.js (UMD global `THREE`). Filterable so a site can swap in a CDN.
$three_src = apply_filters(
	'fw_shortcode_webgl_three_src',
	$base . '/static/js/vendor/three.min.js'
);

wp_enqueue_style(
	'fw-shortcode-webgl-object',
	$base . '/static/css/webgl-object.css',
	array(),
	$css_ver
);

wp_enqueue_script( 'three', $three_src, array(), '0.149.0', true );

wp_enqueue_script(
	'fw-shortcode-webgl-object',
	$base . '/static/js/webgl-object.js',
	array( 'three' ),
	$js_ver,
	true
);
