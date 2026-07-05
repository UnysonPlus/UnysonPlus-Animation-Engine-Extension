<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Scroll Loop module: helpers.
 *
 * The field-array builder (sc_get_scroll_loop_fields) plus the per-request flags
 * (sc_scroll_loop_flag / sc_scroll_loop_snap_used) that gate the footer enqueue.
 * Loaded first — the settings + render parts call these.
 */

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
			'anim_meta'    => array( 'category' => __( 'Scroll', 'fw' ), 'icon' => '&#128257;' ), // 🔁 (Animations-tab inserter)
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
