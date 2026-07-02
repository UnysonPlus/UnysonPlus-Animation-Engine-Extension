<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

$ext = fw_ext( 'animation-engine' );
if ( ! $ext ) {
	return;
}

$base    = $ext->get_declared_URI( '/shortcodes/image-sequence' );
$version = $ext->manifest->get_version();

$js_path  = __DIR__ . '/static/js/image-sequence.js';
$css_path = __DIR__ . '/static/css/image-sequence.css';
$js_ver   = file_exists( $js_path ) ? ( $version . '.' . filemtime( $js_path ) ) : $version;
$css_ver  = file_exists( $css_path ) ? ( $version . '.' . filemtime( $css_path ) ) : $version;

wp_enqueue_style( 'fw-shortcode-image-sequence', $base . '/static/css/image-sequence.css', array(), $css_ver );
wp_enqueue_script( 'fw-shortcode-image-sequence', $base . '/static/js/image-sequence.js', array(), $js_ver, true );

$cfg = array(
	'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
);
wp_add_inline_script( 'fw-shortcode-image-sequence', 'window.upwSeqCfg=' . wp_json_encode( $cfg ) . ';', 'before' );
