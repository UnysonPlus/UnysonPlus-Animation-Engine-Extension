<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Scroll Loop module: settings.
 *
 * Injects the Scroll Loop control (from sc_get_scroll_loop_fields) into the
 * Section's Animations tab via the per-shortcode fw_shortcode_get_options filter.
 */

/**
 * Inject the Scroll Loop control into the SECTION's Animations tab only.
 *
 * The shared `sc_animation_fields` filter is context-free (it hits every element),
 * but an infinite scroll loop only makes sense on a full-height Section — putting it
 * on columns / leaf elements is meaningless. So instead we hook the per-shortcode
 * `fw_shortcode_get_options` filter (class-fw-shortcode.php passes the `$tag`, the
 * same mechanism sc_filter_styling_options uses) and append the control to the
 * section's `tab_animation` only. The section builder item pulls its options via
 * get_shortcode('section')->get_options(), so this fires for its modal too.
 *
 * The Animations tab wraps every module field in the `animation-stack` container (the
 * "Add Animation" inserter). So we append the Scroll Loop control INSIDE that
 * container's options — not beside it — so it becomes a card + inserter tile exactly
 * like the other modules (Hover, Physics, …), hidden until used. A fallback appends
 * flat if the container isn't present (older shortcodes ext without the organizer).
 */
add_filter( 'fw_shortcode_get_options', function ( $options, $tag = '' ) {
	if ( $tag !== 'section' || ! is_array( $options ) || ! function_exists( 'sc_get_scroll_loop_fields' ) ) {
		return $options;
	}
	if ( ! isset( $options['tab_animation']['options'] ) || ! is_array( $options['tab_animation']['options'] ) ) {
		return $options;
	}

	$tab =& $options['tab_animation']['options'];

	if ( isset( $tab['animation_stack']['options'] ) && is_array( $tab['animation_stack']['options'] ) ) {
		$tab['animation_stack']['options'] = array_merge(
			$tab['animation_stack']['options'],
			sc_get_scroll_loop_fields()
		);
	} else {
		$tab = array_merge( $tab, sc_get_scroll_loop_fields() );
	}

	unset( $tab );
	return $options;
}, 10, 2 );
