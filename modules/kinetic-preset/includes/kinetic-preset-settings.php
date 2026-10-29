<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Kinetic Scenes preset module: options declaration.
 *
 * One tile per preset group (Text, Reveal, Move, Emphasis); each group's options (its preset menu, length, delay,
 * trigger, direction, replay and advanced settings) live inside the picker's choices, so they render only when the
 * group is picked (modules/AGENTS.md §1b).
 */

add_filter( 'sc_animation_fields', function ( $fields ) {
	if ( ! is_array( $fields ) || ! function_exists( 'upw_kp_presets' ) ) {
		return $fields;
	}

	$ext  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	$base = $ext ? $ext->get_declared_URI( '/modules/kinetic-preset/static/img' ) : '';
	$tile = function ( $file, $label ) use ( $base ) {
		return array(
			'small' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 66 ),
			'large' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 132 ),
			'label' => $label,
		);
	};

	$groups = array(
		'text'     => array( __( 'Text', 'fw' ), __( 'Words and letters: rise, slam, drop, type, flip, count…', 'fw' ) ),
		'reveal'   => array( __( 'Reveal', 'fw' ), __( 'In and out: fade, wipe, slide, grow, stack, blinds…', 'fw' ) ),
		'motion'   => array( __( 'Move', 'fw' ), __( 'Pop, zoom, spin, turn…', 'fw' ) ),
		'emphasis' => array( __( 'Emphasis', 'fw' ), __( 'Draw the eye: pulse, shake, glow, burst, strike, jiggle, tick…', 'fw' ) ),
	);

	$tiles   = array( 'off' => $tile( 'off', __( 'None', 'fw' ) ) );
	$choices = array( 'off' => array() );
	foreach ( upw_kp_presets() as $group => $list ) {
		if ( empty( $list ) || ! isset( $groups[ $group ] ) ) {
			continue;
		}
		$menu = array();
		foreach ( $list as $id => $p ) {
			$menu[ $id ] = $p[0];
		}
		$tiles[ $group ]   = $tile( $group, $groups[ $group ][0] );
		$choices[ $group ] = array(
			'group_kinetic_preset_' . $group => array(
				'type'    => 'group',
				'options' => array(
					'preset'    => array(
						'type'    => 'select',
						'label'   => __( 'Preset', 'fw' ),
						'desc'    => $groups[ $group ][1],
						'value'   => array_key_first( $menu ),
						'choices' => $menu,
					),
					'trigger'   => array(
						'type'    => 'select',
						'label'   => __( 'Plays', 'fw' ),
						'value'   => 'view',
						'choices' => array(
							'view'  => __( 'When it scrolls into view', 'fw' ),
							'load'  => __( 'When the page loads', 'fw' ),
							'hover' => __( 'On hover', 'fw' ),
							'click' => __( 'On click', 'fw' ),
						),
					),
					'direction' => array(
						'type'    => 'select',
						'label'   => __( 'Direction', 'fw' ),
						'desc'    => __( 'In brings it in; Out takes it away (for presets that have both).', 'fw' ),
						'value'   => 'in',
						'choices' => array( 'in' => __( 'In', 'fw' ), 'out' => __( 'Out', 'fw' ) ),
					),
					'duration'  => array( 'type' => 'number', 'label' => __( 'Length (s)', 'fw' ), 'desc' => __( 'Leave at 0 for the preset\'s own length.', 'fw' ), 'value' => 0, 'min' => 0, 'step' => 0.05, 'numeric_type' => 'float' ),
					'delay'     => array( 'type' => 'number', 'label' => __( 'Delay (s)', 'fw' ), 'value' => 0, 'min' => 0, 'step' => 0.05, 'numeric_type' => 'float' ),
					'replay'    => array(
						'type'         => 'switch',
						'label'        => __( 'Replay', 'fw' ),
						'desc'         => __( 'Play again each time it comes back into view (or on every hover).', 'fw' ),
						'value'        => 'no',
						'left-choice'  => array( 'value' => 'no', 'label' => __( 'Off', 'fw' ) ),
						'right-choice' => array( 'value' => 'yes', 'label' => __( 'On', 'fw' ) ),
					),
					'settings'  => array(
						'type'  => 'textarea',
						'label' => __( 'Settings (advanced)', 'fw' ),
						'desc'  => __( 'The preset\'s own settings as JSON, the same as in Kinetic Scenes, e.g. {"by": "words"} or {"from": "left"}. See the preset list on docs.unysonplus.com.', 'fw' ),
						'value' => '',
					),
				),
			),
		);
	}

	$fields['kinetic_preset'] = array(
		'type'         => 'multi-picker',
		'popover'      => true,
		'label'        => __( 'Kinetic Scenes Preset', 'fw' ),
		'desc'         => __( 'A Kinetic Scenes animation on this element: the same presets as the Kinetic Scenes Studio.', 'fw' ),
		'help'         => __( 'Runs one of Kinetic Scenes\' presets (Gravity drop, Rise, Slam, Strike, Jiggle…) on this element with the same player the Kinetic Scene element uses. Text presets animate the element\'s words and letters, so use them on headings and short text.', 'fw' ),
		'show_borders' => false,
		'value'        => array( 'mode' => 'off' ),
		'anim_meta'    => array( 'category' => __( 'Entrance', 'fw' ) ),
		'picker'       => array(
			'mode' => array( 'type' => 'image-picker', 'label' => false, 'value' => 'off', 'choices' => $tiles ),
		),
		'choices'      => $choices,
	);
	return $fields;
} );
