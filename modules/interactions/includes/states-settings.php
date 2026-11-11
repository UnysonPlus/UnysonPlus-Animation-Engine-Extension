<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/* The per-element "States" card (roadmap I3): named looks (Open, Active, Hidden…) this element can be put in by
 * an Interactions step on any element, with "start in this state" and exclusive groups (tabs, accordions). */
add_filter( 'sc_animation_fields', function ( $fields ) {
	if ( ! is_array( $fields ) ) {
		return $fields;
	}
	$ext    = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	$base   = $ext ? $ext->get_declared_URI( '/modules/interactions/static/img' ) : '';
	$slider = function ( $label, $val, $min, $max, $step ) {
		return array( 'type' => 'slider', 'label' => $label, 'value' => $val, 'properties' => array( 'min' => $min, 'max' => $max, 'step' => $step ) );
	};
	$yes_no = function ( $label, $desc ) {
		return array(
			'type'         => 'switch',
			'label'        => $label,
			'desc'         => $desc,
			'value'        => 'no',
			'left-choice'  => array( 'value' => 'no', 'label' => __( 'No', 'fw' ) ),
			'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
		);
	};
	$tabs = array();
	foreach ( array( 1 => 'open', 2 => '', 3 => '' ) as $i => $default_name ) {
		/* translators: %d: state number */
		$tabs[ 's' . $i ] = array(
			'title'   => sprintf( __( 'State %d', 'fw' ), $i ),
			'options' => array(
				'name'     => array( 'type' => 'text', 'label' => __( 'Name', 'fw' ), 'value' => $default_name, 'attr' => array( 'placeholder' => $i === 1 ? 'open' : __( '(not used)', 'fw' ), 'class' => 'upw-st-name' ) ),
				'x'        => $slider( __( 'Move X (px)', 'fw' ), 0, -600, 600, 1 ),
				'y'        => $slider( __( 'Move Y (px)', 'fw' ), 0, -600, 600, 1 ),
				'scale'    => $slider( __( 'Scale', 'fw' ), 1, 0, 3, 0.01 ),
				'rotate'   => $slider( __( 'Rotate (°)', 'fw' ), 0, -360, 360, 1 ),
				'opacity'  => $slider( __( 'Opacity', 'fw' ), 1, 0, 1, 0.01 ),
				'blur'     => $slider( __( 'Blur (px)', 'fw' ), 0, 0, 30, 1 ),
				'class'    => array( 'type' => 'text', 'label' => __( 'Also add CSS class', 'fw' ), 'value' => '' ),
				'duration' => $slider( __( 'Duration (seconds)', 'fw' ), 0.4, 0, 4, 0.05 ),
				'ease'     => array(
					'type'    => 'select',
					'label'   => __( 'Easing', 'fw' ),
					'value'   => 'out',
					'choices' => array(
						'out'    => __( 'Smooth stop', 'fw' ),
						'inout'  => __( 'Smooth start and stop', 'fw' ),
						'in'     => __( 'Speed up', 'fw' ),
						'back'   => __( 'Overshoot', 'fw' ),
						'linear' => __( 'Linear', 'fw' ),
					),
				),
				'start_in' => $yes_no( __( 'Start in this state', 'fw' ), '' ),
				'group'    => array( 'type' => 'text', 'label' => __( 'Group', 'fw' ), 'value' => '', 'attr' => array( 'placeholder' => 'tabs' ) ),
				'inert'    => $yes_no( __( 'Inactive in this state', 'fw' ), __( 'Can’t be clicked, focused or read by screen readers while in it (for a hidden panel).', 'fw' ) ),
			),
		);
	}

	$fields['upw_states'] = array(
		'type'         => 'multi-picker',
		'popover'      => true,
		'label'        => __( 'States', 'fw' ),
		'desc'         => __( 'Named looks this element can switch to (Open, Active, Hidden…). An Interactions step on any element puts it in a state, takes it out, or toggles it: one button opens a panel and another closes it; tabs show one panel at a time.', 'fw' ),
		'help'         => __( 'States (Animation Engine): up to three named states, each a set of values (move, scale, rotate, opacity, blur) and an optional CSS class. In a state the element animates to its values and gets the class upw-is-<name> plus any class you add, so Custom CSS can restyle it (colours, borders, anything). “Start in this state” applies it as the page loads. A Group makes the state exclusive: putting one element of the group in the state takes it away from the others (tabs, accordions). “Inactive” makes a hidden element unclickable and hidden from screen readers. Change states from an Interactions step: Do → Put it in a state, Take it out of a state, or Toggle a state.', 'fw' ),
		'show_borders' => false,
		'value'        => array( 'mode' => 'off' ),
		'placeholder'  => __( 'Off', 'fw' ),
		'anim_meta'    => array( 'category' => __( 'Interaction', 'fw' ) ),
		'picker'       => array(
			'mode' => array(
				'type'    => 'image-picker',
				'label'   => false,
				'value'   => 'off',
				'choices' => array(
					'on' => array(
						'small' => array( 'src' => $base . '/states.svg', 'height' => 66 ),
						'large' => array( 'src' => $base . '/states.svg', 'height' => 132 ),
						'label' => __( 'States', 'fw' ),
					),
				),
			),
		),
		'choices' => array(
			'off' => array(),
			'on'  => array(
				'note'   => array(
					'type'  => 'html',
					'label' => false,
					'desc'  => false,
					'html'  => '<p style="margin:0;color:#646970;font-size:12px;line-height:1.5;">'
						. esc_html__( 'Each state is a look this element can switch to; leave a name empty to not use it. Values are relative to how it normally looks. In a state the element also gets the class upw-is-<name> (e.g. upw-is-open) for Custom CSS. Switch states from an Interactions step on any element (Do → Put it in a state).', 'fw' )
						. ' ' . esc_html__( 'Group: states with the same group name are exclusive, so only one element at a time is in that state (tabs, accordions).', 'fw' )
						. '</p>',
				),
				'states' => array( 'type' => 'tabs', 'label' => false, 'value' => array(), 'tabs' => $tabs ),
			),
		),
	);
	return $fields;
} );
