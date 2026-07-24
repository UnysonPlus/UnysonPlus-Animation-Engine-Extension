<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * 3D Gallery — assets. The base CSS + the one 3D driver load only on pages that use the element
 * (the shortcodes enqueue mechanism includes this file only for present shortcodes). The shared
 * Gallery lightbox is reused (same handle → deduped) and enqueued PER INSTANCE only when a card's
 * click action is Lightbox (opt-in, off by default) — so by default a 3D gallery ships no lightbox code.
 */

$upw_ae = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
if ( $upw_ae ) {
	$ver  = $upw_ae->manifest->get_version();
	$base = '/shortcodes/gallery-3d/static';
	wp_enqueue_style( 'fw-shortcode-gallery-3d', $upw_ae->get_declared_URI( $base . '/css/gallery-3d.css' ), array(), $ver );
	wp_enqueue_script( 'fw-shortcode-gallery-3d', $upw_ae->get_declared_URI( $base . '/js/gallery-3d.js' ), array(), $ver, true );
}

if ( ! function_exists( '_fw_gallery_3d_enqueue_instance' ) ) :
	function _fw_gallery_3d_enqueue_instance( $data ) {
		$atts = isset( $data['atts_string'] ) ? shortcode_parse_atts( $data['atts_string'] ) : null;
		if ( ! is_array( $atts ) ) { return; }
		$post_id = ( isset( $data['post'] ) && isset( $data['post']->ID ) ) ? $data['post']->ID : 0;
		if ( function_exists( 'fw_ext_shortcodes_decode_attr' ) ) {
			$atts = fw_ext_shortcodes_decode_attr( $atts, 'gallery_3d', $post_id );
			if ( is_wp_error( $atts ) || ! is_array( $atts ) ) { return; }
		}
		// Opt-in, and the fallback MUST match views/view.php: only an explicit 'lightbox' enqueues,
		// otherwise an element with no saved value would ship lightbox.js the view never links to.
		// Reads the NEW `click` multi-picker first, then the legacy flat `click_action` scalar.
		$click = function_exists( 'sc_get' )
			? sc_get( 'click/action', $atts, sc_get( 'click_action', $atts, 'none' ) )
			: 'none';
		if ( $click !== 'lightbox' ) { return; }
		$sc = function_exists( 'fw_ext' ) ? fw_ext( 'shortcodes' ) : null;
		if ( ! $sc ) { return; }
		$gv = $sc->manifest->get_version();
		wp_enqueue_style( 'fw-shortcode-gallery', $sc->get_declared_URI( '/shortcodes/gallery/static/css/styles.css' ), array( 'fw-ext-builder-frontend-grid' ), $gv );
		wp_enqueue_script( 'fw-shortcode-gallery', $sc->get_declared_URI( '/shortcodes/gallery/static/js/lightbox.js' ), array(), $gv, true );
	}
	add_action( 'fw_ext_shortcodes_enqueue_static:gallery_3d', '_fw_gallery_3d_enqueue_instance' );
endif;
