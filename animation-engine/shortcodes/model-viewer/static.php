<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

$ext = fw_ext( 'animation-engine' );
if ( ! $ext ) {
	return;
}

$base    = $ext->get_declared_URI( '/shortcodes/model-viewer' );
$version = $ext->manifest->get_version();

// Cache-bust on the actual file mtime so any code change forces a re-download even if
// the manifest version is cached. static.php sits in this folder, so __DIR__ is the
// shortcode dir.
$js_path  = __DIR__ . '/static/js/model-viewer.js';
$css_path = __DIR__ . '/static/css/model-viewer.css';
$js_ver   = file_exists( $js_path ) ? ( $version . '.' . filemtime( $js_path ) ) : $version;
$css_ver  = file_exists( $css_path ) ? ( $version . '.' . filemtime( $css_path ) ) : $version;

// Vendored <model-viewer> UMD bundle (registers the custom element; bundles its own
// Three.js internally). Filterable so a site can swap in a CDN / different version.
$mv_src = apply_filters(
	'fw_shortcode_model_viewer_src',
	$base . '/static/js/vendor/model-viewer-umd.min.js'
);

wp_enqueue_style(
	'fw-shortcode-model-viewer',
	$base . '/static/css/model-viewer.css',
	array(),
	$css_ver
);

wp_enqueue_script( 'google-model-viewer', $mv_src, array(), '3.5.0', true );

wp_enqueue_script(
	'fw-shortcode-model-viewer',
	$base . '/static/js/model-viewer.js',
	array(),
	$js_ver,
	true
);
