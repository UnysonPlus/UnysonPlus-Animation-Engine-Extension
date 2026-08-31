<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

$ext = fw_ext( 'animation-engine' );
if ( ! $ext ) {
	return;
}

$base    = $ext->get_declared_URI( '/shortcodes/interactive-reveal' );
$version = $ext->manifest->get_version();

$js_path  = __DIR__ . '/static/js/interactive-reveal.js';
$css_path = __DIR__ . '/static/css/interactive-reveal.css';
$js_ver   = file_exists( $js_path ) ? ( $version . '.' . filemtime( $js_path ) ) : $version;
$css_ver  = file_exists( $css_path ) ? ( $version . '.' . filemtime( $css_path ) ) : $version;

wp_enqueue_style( 'fw-shortcode-interactive-reveal', $base . '/static/css/interactive-reveal.css', array(), $css_ver );

// Depend on the shared frame scheduler (window.upwAnimRaf + upwScrollProgress + upwReduceMotion) so the
// scroll trigger stays in lockstep with smooth scroll and the other scroll effects.
$raf_dep = function_exists( 'upw_anim_raf_handle' ) ? array( upw_anim_raf_handle() ) : array();
wp_enqueue_script( 'fw-shortcode-interactive-reveal', $base . '/static/js/interactive-reveal.js', $raf_dep, $js_ver, true );

$cfg = array(
	'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
);
wp_add_inline_script( 'fw-shortcode-interactive-reveal', 'window.upwIRevealCfg=' . wp_json_encode( $cfg ) . ';', 'before' );
