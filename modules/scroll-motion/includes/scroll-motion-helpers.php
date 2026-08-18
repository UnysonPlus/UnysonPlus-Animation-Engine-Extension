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

if ( ! function_exists( 'upw_gsap_effects' ) ) :
	/**
	 * Canonical Scroll-Motion effect list — the SINGLE source of truth for the effect KEYS + labels.
	 * The picker tiles (settings.php) and the render-time allow-list (render.php) both derive from this,
	 * so those two can never drift again (the drift is what shipped the data-upw-g-each stagger bug).
	 * The tile SVG slug is the key with `_` -> `-`. NOTE: the JS BUILDERS map (upw-gsap.js) implements
	 * each key and must carry the SAME keys (it can't share a PHP literal) — keep it in step. `custom`
	 * (the code snippet) is handled separately (own tile + gated runtime), so it is NOT listed here.
	 *
	 * @return array<string,string> key => translated label, in tile order.
	 */
	function upw_gsap_effects() {
		return array(
			'blur'          => __( 'Blur In', 'fw' ),
			'clip'          => __( 'Clip Wipe', 'fw' ),
			'color_scrub'   => __( 'Color Scrub', 'fw' ),
			'counter'       => __( 'Count Up', 'fw' ),
			'expand'        => __( 'Expand / Grow', 'fw' ),
			'flip'          => __( 'Flip In (3D)', 'fw' ),
			'mask_wipe'     => __( 'Mask Wipe', 'fw' ),
			'parallax'      => __( 'Parallax', 'fw' ),
			'pin'           => __( 'Pin', 'fw' ),
			'reveal'        => __( 'Reveal', 'fw' ),
			'rotate'        => __( 'Rotate In', 'fw' ),
			'scroll_spin'   => __( 'Scroll Spin', 'fw' ),
			'scrub'         => __( 'Scrub', 'fw' ),
			'skew'          => __( 'Skew Settle', 'fw' ),
			'splittext'     => __( 'Split Text', 'fw' ),
			'stagger'       => __( 'Stagger', 'fw' ),
			'tilt_scrub'    => __( 'Tilt Scrub (3D)', 'fw' ),
			'velocity_skew' => __( 'Velocity Skew', 'fw' ),
			'zoom'          => __( 'Zoom In', 'fw' ),
		);
	}
endif;
