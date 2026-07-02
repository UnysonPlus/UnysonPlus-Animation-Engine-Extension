<?php
/**
 * PHP Version: 7.4 or higher
 *
 * Animation Engine — Seamless / Infinite Scroll Loop module.
 *
 * Turns a run of consecutive full-height [section]s into a never-ending, snapping
 * scroll loop — the "Infinite Scroll with Parallax" experience (Codrops/Tympanus),
 * powered by Lenis (`infinite: true`) + Lenis Snap. Reuses the existing Scroll
 * Motion `parallax` effect for the media drift; this module supplies only the
 * missing half: the seamless loop + section snapping.
 *
 * Design mirrors scroll-motion.php exactly, so the feature stays self-contained in
 * the (inactive-by-default) Animation Engine and touches NO base-plugin file:
 *
 *   1. sc_get_scroll_loop_fields() — a `multi` block keyed `scroll_loop`, appended
 *      to every element's Animations tab via the `sc_animation_fields` filter
 *      (priority 8, so it sits just before Scroll Motion at 9). Only meaningful on
 *      Sections; on other elements it simply does nothing.
 *   2. A filter on `sc_build_wrapper_attr` (priority 26, after Scroll Motion at 25)
 *      that stamps clean `data-upw-loop*` attributes onto the section wrapper. The
 *      section already routes through sc_build_wrapper_attr(), so no view.php change.
 *   3. A `sc_needs_wrapper` force so a section whose ONLY non-default setting is the
 *      loop flag still gets a wrapper for the attributes to land on.
 *   4. Conditional enqueue: Lenis (+ Lenis Snap when snapping is used) + the
 *      initializer + CSS load only when at least one loop section rendered. Pages
 *      with no loop ship ZERO of these bytes.
 */
if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}


/**
 * The Scroll Loop control appended to the Animations tab.
 *
 * A popover image-picker multi-picker (keyed `scroll_loop`, picker id `mode`) so it
 * stays compact in the tab like the other engine controls (Scroll Motion, Page
 * Transitions, Hover) rather than laying its fields out inline.
 *
 * Saved value shape:
 *
 *     [ 'mode' => 'off'|'loop',
 *       'loop' => [ 'snap' => 'yes'|'no', 'snap_duration' => 0.8, 'run_on_mobile' => 'yes'|'no' ] ]
 */
if ( ! function_exists( 'sc_get_scroll_loop_fields' ) ) :
function sc_get_scroll_loop_fields() {

	$sw = function ( $label, $desc, $default_yes ) {
		return array(
			'type'         => 'switch',
			'label'        => $label,
			'desc'         => $desc,
			'value'        => $default_yes ? 'yes' : 'no',
			'left-choice'  => array( 'value' => 'no',  'label' => __( 'No', 'fw' ) ),
			'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
		);
	};

	// Preview tiles under static/img/loop/. Same shape as the Scroll Motion / Page
	// Transitions picker tiles.
	$ext  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	$base = $ext ? $ext->get_declared_URI( '/modules/scroll-loop/static/img/loop' ) : '';
	$tile = function ( $file, $label ) use ( $base ) {
		return array(
			'small' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 66 ),
			'large' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 132 ),
			'label' => $label,
		);
	};

	return array(
		'scroll_loop' => array(
			'type'         => 'multi-picker',
			// Popover multi-picker → label lives on the TOP level (picker label false).
			'label'        => __( 'Infinite Scroll Loop', 'fw' ),
			'desc'         => __( 'Loop a run of full-height Sections into a seamless, never-ending scroll — mark 2 or more in a row and the first re-appears seamlessly after the last. Applies to Sections only.', 'fw' ),
			'help'         => __( 'Seamless Infinite Scroll (Lenis): smooth-scrolls the page and loops the marked sections forever, with optional section snapping. Add the Scroll Motion → Parallax effect to the media inside for the classic depth look. Front end only; ignored in the editor and for "reduce motion" visitors.', 'fw' ) . ( function_exists( 'upw_perf_note' ) ? ' ' . upw_perf_note() : '' ),
			'popover'      => true,
			'show_borders' => false,
			'value'        => array( 'mode' => 'off' ),
			'picker'       => array(
				'mode' => array(
					'type'    => 'image-picker',
					'label'   => false,
					'desc'    => __( 'Hover a tile to preview it larger.', 'fw' ),
					'value'   => 'off',
					'choices' => array(
						'off'  => $tile( 'off',  __( 'Off', 'fw' ) ),
						'loop' => $tile( 'loop', __( 'Infinite Loop', 'fw' ) ),
					),
				),
			),
			'choices' => array(
				'loop' => array(
					'group_scroll_loop' => array(
						'type'    => 'group',
						'options' => array(
							'snap'          => $sw(
								__( 'Snap to each section', 'fw' ),
								__( 'On = one section per scroll gesture, eased into place (the classic look). Off = free continuous smooth scrolling.', 'fw' ),
								true
							),
							'snap_duration' => array(
								'type'       => 'slider',
								'label'      => __( 'Snap duration (s)', 'fw' ),
								'desc'       => __( 'How long the eased glide to each section takes.', 'fw' ),
								'value'      => 0.8,
								'properties' => array( 'min' => 0.4, 'max' => 1.5, 'step' => 0.1 ),
							),
							'run_on_mobile' => $sw(
								__( 'Run on mobile', 'fw' ),
								__( 'Disable the loop + smooth scroll on phones (< 768px) if it feels heavy.', 'fw' ),
								true
							),
						),
					),
				),
			),
		),
	);
}
endif;

/**
 * Inject the Scroll Loop control into the SECTION's Animations tab only.
 *
 * The shared `sc_animation_fields` filter is context-free (it hits every element),
 * but an infinite scroll loop only makes sense on a full-height Section — putting it
 * on columns / leaf elements is meaningless. So instead we hook the per-shortcode
 * `fw_shortcode_get_options` filter (class-fw-shortcode.php passes the `$tag`, the
 * same mechanism sc_filter_styling_options uses) and append the control to the
 * section's `tab_animation` group only. The section builder item pulls its options
 * via get_shortcode('section')->get_options(), so this fires for its modal too.
 */
add_filter( 'fw_shortcode_get_options', function ( $options, $tag = '' ) {
	if ( $tag !== 'section' || ! is_array( $options ) || ! function_exists( 'sc_get_scroll_loop_fields' ) ) {
		return $options;
	}
	if ( isset( $options['tab_animation']['options'] ) && is_array( $options['tab_animation']['options'] ) ) {
		$options['tab_animation']['options'] = array_merge(
			$options['tab_animation']['options'],
			sc_get_scroll_loop_fields()
		);
	}
	return $options;
}, 10, 2 );


/**
 * Per-request flag: "at least one loop section rendered". Gates the wp_footer
 * enqueue so zero loop bytes ship on pages without a loop group.
 */
if ( ! function_exists( 'sc_scroll_loop_flag' ) ) :
function sc_scroll_loop_flag( $set = false ) {
	static $used = false;
	if ( $set ) {
		$used = true;
	}
	return $used;
}
endif;

/**
 * Per-request flag: "at least one loop section requested snapping". Gates loading
 * the separate Lenis Snap build.
 */
if ( ! function_exists( 'sc_scroll_loop_snap_used' ) ) :
function sc_scroll_loop_snap_used( $set = false ) {
	static $used = false;
	if ( $set ) {
		$used = true;
	}
	return $used;
}
endif;


/**
 * Stamp the loop data-attributes onto the section wrapper. Runs at priority 26 —
 * after the Scroll Motion filter (25) — so a section can carry both a scroll
 * effect and the loop flag without either clobbering the other's attributes.
 */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	$l = ( isset( $atts['scroll_loop'] ) && is_array( $atts['scroll_loop'] ) ) ? $atts['scroll_loop'] : array();

	$mode = isset( $l['mode'] ) ? (string) $l['mode'] : 'off';
	if ( $mode !== 'loop' ) {
		return $attr;
	}

	$s = ( isset( $l['loop'] ) && is_array( $l['loop'] ) ) ? $l['loop'] : array();

	$snap = ! isset( $s['snap'] ) || (string) $s['snap'] === 'yes'; // default on
	$dur  = isset( $s['snap_duration'] ) && is_numeric( $s['snap_duration'] )
		? rtrim( rtrim( number_format( (float) $s['snap_duration'], 2, '.', '' ), '0' ), '.' )
		: '0.8';
	$mobile = ! isset( $s['run_on_mobile'] ) || (string) $s['run_on_mobile'] === 'yes'; // default on

	$attr['data-upw-loop']      = '1';
	$attr['data-upw-loop-snap'] = $snap ? '1' : '0';
	if ( $snap ) {
		$attr['data-upw-loop-snap-dur'] = esc_attr( $dur );
		sc_scroll_loop_snap_used( true );
	}
	if ( ! $mobile ) {
		$attr['data-upw-loop-mobile'] = '0';
	}

	sc_scroll_loop_flag( true );

	return $attr;
}, 26, 2 );

/**
 * Force a wrapper when a section's ONLY non-default setting is the loop flag, so
 * the data-upw-loop* attributes have somewhere to land (mirrors the Scroll Motion
 * needs-wrapper force). Kept here so the engine stays self-contained.
 */
add_filter( 'sc_needs_wrapper', function ( $needs, $atts ) {
	if ( $needs ) {
		return $needs;
	}
	$l = ( isset( $atts['scroll_loop'] ) && is_array( $atts['scroll_loop'] ) ) ? $atts['scroll_loop'] : array();
	return isset( $l['mode'] ) && (string) $l['mode'] === 'loop';
}, 10, 2 );


/**
 * Conditionally enqueue Lenis (+ Snap when used) + the initializer + CSS at the
 * start of wp_footer. Priority 6 so it runs AFTER Scroll Motion's GSAP enqueue
 * (priority 5): when GSAP is present on the page, the loop init is ordered after
 * it (for the ScrollTrigger bridge); when it isn't, the runtime drives Lenis with
 * its own rAF loop instead (feature-detected client-side).
 */
add_action( 'wp_footer', function () {
	if ( ! sc_scroll_loop_flag() ) {
		return;
	}

	$ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( ! $ext ) {
		return;
	}

	$ver      = $ext->manifest->get_version();
	$lenis_ver = '1.1.18';
	$base     = '/modules/scroll-loop';

	// Vendor files are already minified — reference them directly.
	wp_enqueue_script(
		'upw-lenis',
		$ext->get_declared_URI( $base . '/static/js/vendor/lenis/lenis.min.js' ),
		array(),
		$lenis_ver,
		true
	);
	$init_deps = array( 'upw-lenis' );

	// Lenis Snap is a separate build — load only when a loop section uses snapping.
	if ( sc_scroll_loop_snap_used() ) {
		wp_enqueue_script(
			'upw-lenis-snap',
			$ext->get_declared_URI( $base . '/static/js/vendor/lenis/lenis-snap.min.js' ),
			array( 'upw-lenis' ),
			$lenis_ver,
			true
		);
		$init_deps[] = 'upw-lenis-snap';
	}

	// If Scroll Motion enqueued its GSAP initializer on this page, order the loop
	// init after it so the Lenis↔ScrollTrigger bridge wires against a ready GSAP.
	if ( function_exists( 'wp_script_is' ) && wp_script_is( 'upw-gsap-init', 'enqueued' ) ) {
		$init_deps[] = 'upw-gsap-init';
	}

	wp_enqueue_script(
		'upw-scroll-loop',
		$ext->get_declared_URI( $base . '/static/js/upw-scroll-loop.js' ),
		$init_deps,
		$ver,
		true
	);

	// Honour the engine's global "respect reduce motion" policy (like Page Transitions).
	$cfg = array(
		'respectReducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
	);
	wp_add_inline_script( 'upw-scroll-loop', 'window.upwLoopCfg=' . wp_json_encode( $cfg ) . ';', 'before' );

	wp_enqueue_style(
		'upw-scroll-loop',
		$ext->get_declared_URI( $base . '/static/css/upw-scroll-loop.css' ),
		array(),
		$ver
	);
}, 6 );
