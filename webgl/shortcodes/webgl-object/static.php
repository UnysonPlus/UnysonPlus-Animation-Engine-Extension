<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

$ext = fw_ext( 'webgl' );
if ( ! $ext ) {
	return;
}

$base    = $ext->get_declared_URI( '/shortcodes/webgl-object' );
$version = $ext->manifest->get_version();

// Vendored Three.js (UMD global `THREE`). Filterable so a site can swap in a CDN.
$three_src = apply_filters(
	'fw_shortcode_webgl_three_src',
	$base . '/static/js/vendor/three.min.js'
);

wp_enqueue_style(
	'fw-shortcode-webgl-object',
	$base . '/static/css/webgl-object.css',
	array(),
	$version
);

wp_enqueue_script( 'three', $three_src, array(), '0.149.0', true );

wp_enqueue_script(
	'fw-shortcode-webgl-object',
	$base . '/static/js/webgl-object.js',
	array( 'three' ),
	$version,
	true
);
