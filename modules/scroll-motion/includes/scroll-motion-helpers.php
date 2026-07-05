<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Scroll Motion module: helpers.
 *
 * Per-request bookkeeping for the GSAP engine: sc_gsap_flag() records that at least one
 * GSAP-animated shortcode rendered (gates the wp_footer enqueue), and sc_gsap_used() records
 * which effects rendered (so heavier per-effect plugins load only when used). Loaded first by
 * scroll-motion.php (the settings + render parts depend on these). Wrapped in function_exists guards.
 */


/**
 * Per-request flag: "at least one GSAP-animated shortcode has rendered".
 * Gates the wp_footer enqueue so zero GSAP bytes ship on un-animated pages.
 */
if ( ! function_exists( 'sc_gsap_flag' ) ) :
function sc_gsap_flag( $set = false ) {
    static $used = false;
    if ( $set ) $used = true;
    return $used;
}
endif;


/**
 * Records which GSAP effects rendered on this request, so wp_footer can load
 * the heavier per-effect plugins (e.g. SplitText) ONLY when they're used.
 */
if ( ! function_exists( 'sc_gsap_used' ) ) :
function sc_gsap_used( $effect = null ) {
    static $set = [];
    if ( $effect !== null ) { $set[ (string) $effect ] = true; }
    return $set;
}
endif;
