<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

$ext = fw_ext( 'animation-engine' );
if ( ! $ext ) {
	return;
}

$base    = $ext->get_declared_URI( '/shortcodes/svg-draw' );
$version = $ext->manifest->get_version();

$js_path  = __DIR__ . '/static/js/svg-draw.js';
$css_path = __DIR__ . '/static/css/svg-draw.css';
$js_ver   = file_exists( $js_path ) ? ( $version . '.' . filemtime( $js_path ) ) : $version;
$css_ver  = file_exists( $css_path ) ? ( $version . '.' . filemtime( $css_path ) ) : $version;

wp_enqueue_style( 'fw-shortcode-svg-draw', $base . '/static/css/svg-draw.css', array(), $css_ver );
wp_enqueue_script( 'fw-shortcode-svg-draw', $base . '/static/js/svg-draw.js', array(), $js_ver, true );

$cfg = array(
	'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
);
wp_add_inline_script( 'fw-shortcode-svg-draw', 'window.upwSvgDrawCfg=' . wp_json_encode( $cfg ) . ';', 'before' );
