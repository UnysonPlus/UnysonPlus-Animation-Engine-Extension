<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Motion Sequence module.
 *
 * Choreography WITHOUT code. Turn a Section into a Motion Sequence and its child elements' Scroll
 * Motion entrance animations (Reveal / Stagger) play as ONE gsap.timeline() on a single trigger, in
 * document order — instead of each firing independently as it scrolls into view. This is the "timeline"
 * concept translated into a builder option: the steps ARE the children, and one knob (Overlap) tunes
 * how they flow one into the next. Section-level, injected only into the Section's Animations tab
 * (like Sticky Card Stack / Scroll Loop). Reuses the Scroll Motion runtime (upw-gsap.js) — no new
 * front-end asset. Global on/off: Theme Settings → Animations → Effects.
 *
 * Saved value shape (multi-picker, picker id `mode`):
 *   [ 'mode' => 'off'|'sequence', 'sequence' => [ 'trigger'=>'view'|'scrub', 'overlap'=>0.35,
 *     'start'=>'top 80%', 'run_on_mobile'=>'yes' ] ]
 */

if ( ! function_exists( 'upw_motion_sequence_enabled' ) ) :
	function upw_motion_sequence_enabled() {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return true;
		}
		$v = fw_get_db_settings_option( 'animation_motion_sequence', array() );
		$e = ( is_array( $v ) && isset( $v['enable'] ) ) ? $v['enable'] : 'yes';
		return $e !== 'no' && $e !== false;
	}
endif;

/**
 * The Motion Sequence control (Section only). An INLINE multi-picker keyed `motion_sequence`,
 * picker id `mode` (Off / On) — one row until switched on, matching the engine's other controls.
 */
if ( ! function_exists( 'sc_get_motion_sequence_fields' ) ) :
	function sc_get_motion_sequence_fields() {
		$slider = function ( $label, $val, $min, $max, $step, $desc, $help = '' ) {
			$f = array( 'type' => 'slider', 'label' => $label, 'desc' => $desc, 'value' => $val, 'properties' => array( 'min' => $min, 'max' => $max, 'step' => $step ) );
			if ( $help !== '' ) { $f['help'] = $help; }
			return $f;
		};
		$on_opts = array(
			'trigger' => array(
				'type'    => 'select',
				'label'   => __( 'Play the sequence', 'fw' ),
				'desc'    => __( 'On view = the whole sequence plays once when the Section scrolls in. Scrub = the visitor\'s scrolling scrubs through the sequence, forward and back.', 'fw' ),
				'help'    => __( 'Under the hood: a single gsap.timeline() with a ScrollTrigger. "On view" uses toggleActions; "Scrub" sets scrub:true and pins the Section.', 'fw' ),
				'value'   => 'view',
				'choices' => array(
					'view'  => __( 'On view (play once)', 'fw' ),
					'scrub' => __( 'Scrub with scroll', 'fw' ),
				),
			),
			'overlap' => $slider(
				__( 'Overlap between steps (s)', 'fw' ), 0.35, 0, 1.5, 0.05,
				__( 'How much each step starts BEFORE the previous one finishes. 0 = strictly one-after-another; higher = they blend together.', 'fw' ),
				__( 'Under the hood: each child tween is added to the timeline at a position like ">-0.35" — 0.35s before the previous tween ends.', 'fw' )
			),
			'start' => array(
				'type'    => 'select',
				'label'   => __( 'Start point', 'fw' ),
				'desc'    => __( 'How far the Section is into view before the sequence begins (On view only).', 'fw' ),
				'value'   => 'top 80%',
				'choices' => array(
					'top 85%'    => __( 'Default — near bottom of screen', 'fw' ),
					'top 100%'   => __( 'As soon as it enters', 'fw' ),
					'top 80%'    => __( 'A little in (80%)', 'fw' ),
					'top center' => __( 'When it reaches the middle', 'fw' ),
				),
			),
			'run_on_mobile' => array(
				'type'         => 'switch',
				'label'        => __( 'Run on mobile', 'fw' ),
				'desc'         => __( 'Off = on phones the children animate independently (the simpler, lighter fallback) instead of as a sequence.', 'fw' ),
				'value'        => 'yes',
				'left-choice'  => array( 'value' => 'no',  'label' => __( 'No',  'fw' ) ),
				'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
			),
		);

		return array(
			'motion_sequence' => array(
				'type'         => 'multi-picker',
				'label'        => __( 'Motion Sequence', 'fw' ),
				'desc'         => __( 'Play this Section\'s child animations as one choreographed sequence, in order, on a single trigger — instead of each firing on its own. Give the children (heading, text, button, cards…) a Scroll Motion Reveal/Stagger, then turn this on.', 'fw' ),
				'help'         => __( 'Motion Sequence (Animation Engine): assembles the descendant Reveal/Stagger animations into ONE gsap.timeline() on the Section, in document order, with an Overlap knob for how they flow together. Other Scroll Motion effects inside the Section keep firing independently. Reuses the Scroll Motion runtime; honours "reduce motion".', 'fw' ) . ( function_exists( 'upw_perf_note' ) ? ' ' . upw_perf_note() : '' ),
				'show_borders' => false,
				'value'        => array( 'mode' => 'off' ),
				'anim_meta'    => array( 'category' => __( 'Scroll', 'fw' ) ),
				'picker'       => array(
					'mode' => array(
						'type'    => 'select',
						'label'   => __( 'Motion Sequence', 'fw' ),
						'desc'    => __( 'Off = children animate independently. On = they play as one sequence.', 'fw' ),
						'value'   => 'off',
						'choices' => array(
							'off'      => __( 'Off', 'fw' ),
							'sequence' => __( 'On — play in sequence', 'fw' ),
						),
					),
				),
				'choices' => array( 'sequence' => $on_opts ),
			),
		);
	}
endif;

/* Inject into the SECTION's Animations tab only, inside the animation-stack organizer. */
add_filter( 'fw_shortcode_get_options', function ( $options, $tag = '' ) {
	if ( $tag !== 'section' || ! is_array( $options ) || ! function_exists( 'sc_get_motion_sequence_fields' ) || ! upw_motion_sequence_enabled() ) {
		return $options;
	}
	if ( ! isset( $options['tab_animation']['options'] ) || ! is_array( $options['tab_animation']['options'] ) ) {
		return $options;
	}
	$tab =& $options['tab_animation']['options'];
	if ( isset( $tab['animation_stack']['options'] ) && is_array( $tab['animation_stack']['options'] ) ) {
		$tab['animation_stack']['options'] = array_merge( $tab['animation_stack']['options'], sc_get_motion_sequence_fields() );
	} else {
		$tab = array_merge( $tab, sc_get_motion_sequence_fields() );
	}
	unset( $tab );
	return $options;
}, 10, 2 );

/* Stamp the sequence data-attributes onto the section wrapper. */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	if ( ! upw_motion_sequence_enabled() ) {
		return $attr;
	}
	$s    = ( isset( $atts['motion_sequence'] ) && is_array( $atts['motion_sequence'] ) ) ? $atts['motion_sequence'] : array();
	$mode = isset( $s['mode'] ) ? (string) $s['mode'] : 'off';
	if ( $mode !== 'sequence' ) {
		return $attr;
	}
	$o = ( isset( $s['sequence'] ) && is_array( $s['sequence'] ) ) ? $s['sequence'] : array();

	$cls           = isset( $attr['class'] ) ? trim( (string) $attr['class'] ) : '';
	$attr['class'] = esc_attr( trim( $cls . ' upw-seq' ) );

	$trigger = ( isset( $o['trigger'] ) && $o['trigger'] === 'scrub' ) ? 'scrub' : 'view';
	$start   = isset( $o['start'] ) ? (string) $o['start'] : 'top 80%';
	if ( ! preg_match( '/^[a-z]+ [a-z0-9%]+$/i', $start ) ) { $start = 'top 80%'; }

	$attr['data-upw-seq-trigger'] = esc_attr( $trigger );
	$attr['data-upw-seq-overlap'] = esc_attr( (string) max( 0, min( 1.5, (float) ( isset( $o['overlap'] ) ? $o['overlap'] : 0.35 ) ) ) );
	$attr['data-upw-seq-start']   = esc_attr( $start );
	if ( isset( $o['run_on_mobile'] ) && $o['run_on_mobile'] === 'no' ) {
		$attr['data-upw-seq-mobile'] = '0';
	}
	return $attr;
}, 23, 2 ); // before sticky-stack (24) — order is irrelevant, but keep sequence attrs early

/* Force a wrapper when a section's ONLY non-default setting is the sequence. */
add_filter( 'sc_needs_wrapper', function ( $needs, $atts ) {
	if ( $needs || ! upw_motion_sequence_enabled() ) {
		return $needs;
	}
	$s = ( isset( $atts['motion_sequence'] ) && is_array( $atts['motion_sequence'] ) ) ? $atts['motion_sequence'] : array();
	return ( isset( $s['mode'] ) && $s['mode'] === 'sequence' );
}, 10, 2 );
