<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

$ext = fw_ext( 'animation-engine' );
if ( ! $ext ) {
	return;
}

$base    = $ext->get_declared_URI( '/shortcodes/rive' );
$version = $ext->manifest->get_version();

$js_path  = __DIR__ . '/static/js/rive.js';
$css_path = __DIR__ . '/static/css/rive.css';
$js_ver   = file_exists( $js_path ) ? ( $version . '.' . filemtime( $js_path ) ) : $version;
$css_ver  = file_exists( $css_path ) ? ( $version . '.' . filemtime( $css_path ) ) : $version;

// Vendored Rive canvas runtime (UMD global `rive`) + its WASM. Both filterable so a site can swap a CDN.
/** Filters the URL of the vendored Rive canvas runtime script. */
$rive_src  = apply_filters( 'fw_shortcode_rive_src',  $base . '/static/js/vendor/rive.min.js' );
/** Filters the URL of the vendored Rive WASM binary the runtime loads. */
$wasm_url  = apply_filters( 'fw_shortcode_rive_wasm', $base . '/static/js/vendor/rive.wasm' );

wp_enqueue_style( 'fw-shortcode-rive', $base . '/static/css/rive.css', array(), $css_ver );

wp_enqueue_script( 'rive-canvas', $rive_src, array(), '2.30.0', true );

wp_enqueue_script( 'fw-shortcode-rive', $base . '/static/js/rive.js', array( 'rive-canvas' ), $js_ver, true );

// Tell the runtime where the vendored WASM lives (Rive otherwise fetches it from unpkg — which the
// CSP / offline sites can't reach). Printed BEFORE rive.js runs.
wp_add_inline_script( 'fw-shortcode-rive', 'window.__fwRiveWasm=' . wp_json_encode( $wasm_url ) . ';', 'before' );
