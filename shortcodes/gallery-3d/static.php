<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery — assets. The base CSS + the one 3D driver load only on pages that use the element
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
		// Before the lightbox gate: enqueue any child-theme-registered design assets, keyed by design
		// key. A theme returns, e.g.:
		//   add_filter( 'upw_gallery_3d_design_assets', fn( $a ) => $a + array(
		//     'my_design' => array( 'js' => $url, 'css' => $url, 'ver' => '1.0' ) ) );
		// The script depends on the base driver so window.upwGallery3d.registerDesign exists first.
		$design = ( isset( $atts['design_settings']['design'] ) && is_string( $atts['design_settings']['design'] ) )
			? $atts['design_settings']['design'] : '';
		if ( $design ) {
			$custom = apply_filters( 'upw_gallery_3d_design_assets', array() );
			if ( isset( $custom[ $design ] ) && is_array( $custom[ $design ] ) ) {
				$a      = $custom[ $design ];
				$handle = 'upw-g3d-design-' . sanitize_key( $design );
				$dver   = isset( $a['ver'] ) ? $a['ver'] : null;
				if ( ! empty( $a['css'] ) ) { wp_enqueue_style( $handle, $a['css'], array( 'fw-shortcode-gallery-3d' ), $dver ); }
				if ( ! empty( $a['js'] ) )  { wp_enqueue_script( $handle, $a['js'], array( 'fw-shortcode-gallery-3d' ), $dver, true ); }
			}
		}
		// Scroll Choreography drives its pin + scrub through GSAP ScrollTrigger (robust pin, scrub). Load
		// the bundled GSAP + ScrollTrigger (same handles the Scroll Motion engine uses, so they dedupe)
		// and make the gallery runtime depend on ScrollTrigger so it loads AFTER it and takes the GSAP path.
		if ( 'scroll_choreography' === $design ) {
			$ae = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
			if ( $ae ) {
				$gver = '3.13.0';
				wp_enqueue_script( 'upw-gsap-core', $ae->get_declared_URI( '/modules/scroll-motion/static/js/vendor/gsap/gsap.min.js' ), array(), $gver, true );
				wp_enqueue_script( 'upw-gsap-scrolltrigger', $ae->get_declared_URI( '/modules/scroll-motion/static/js/vendor/gsap/ScrollTrigger.min.js' ), array( 'upw-gsap-core' ), $gver, true );
				global $wp_scripts;
				if ( isset( $wp_scripts->registered['fw-shortcode-gallery-3d'] ) ) {
					$gdeps =& $wp_scripts->registered['fw-shortcode-gallery-3d']->deps;
					if ( ! in_array( 'upw-gsap-scrolltrigger', $gdeps, true ) ) { $gdeps[] = 'upw-gsap-scrolltrigger'; }
					unset( $gdeps );
				}
			}
		}
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
