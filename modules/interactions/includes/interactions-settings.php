<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

if ( ! function_exists( 'upw_interactions_step_options' ) ) :
	/**
	 * The options of one step. $d = this step's defaults (each trigger starts with a useful first step).
	 */
	function upw_interactions_step_options( $d, $first ) {
		$slider = function ( $label, $val, $min, $max, $step, $desc = '' ) {
			return array( 'type' => 'slider', 'label' => $label, 'desc' => $desc, 'value' => $val, 'properties' => array( 'min' => $min, 'max' => $max, 'step' => $step ) );
		};
		$targets = array(
			'self'   => __( 'This element', 'fw' ),
			'inside' => __( 'Elements inside it that match…', 'fw' ),
			'page'   => __( 'Elements on the page that match…', 'fw' ),
		);
		if ( ! $first ) {
			$targets = array( 'none' => __( '— Not used —', 'fw' ) ) + $targets;
		}
		return array(
			'target' => array(
				'type'    => 'select',
				'label'   => __( 'Animate', 'fw' ),
				'value'   => $d['target'],
				'choices' => $targets,
			),
			'selector' => array(
				'type'  => 'text',
				'label' => __( 'That match', 'fw' ),
				'value' => $d['selector'],
				'attr'  => array( 'class' => 'upw-ix-selector', 'placeholder' => '#promo, .card-arrow, img' ),
			),
			'action' => array(
				'type'    => 'select',
				'label'   => __( 'Do', 'fw' ),
				'value'   => 'animate',
				'choices' => array(
					'animate'      => __( 'Animate it with the values below', 'fw' ),
					'state_on'     => __( 'Put it in a state', 'fw' ),
					'state_off'    => __( 'Take it out of a state', 'fw' ),
					'state_toggle' => __( 'Toggle a state', 'fw' ),
					'set'          => __( 'Set something instantly (show, hide, a class…)', 'fw' ),
					'lottie'       => __( 'Control its Lottie animation', 'fw' ),
					'rive'         => __( 'Control its Rive animation', 'fw' ),
					'burst'        => __( 'Burst from it (ring, sparks, lines…)', 'fw' ),
				),
			),
			'state' => array(
				'type'  => 'text',
				'label' => __( 'State', 'fw' ),
				'value' => '',
				'attr'  => array( 'placeholder' => 'open' ),
			),
			// Set (roadmap 6c): an instant change, no animation.
			'set' => array(
				'type'    => 'select',
				'label'   => __( 'Set', 'fw' ),
				'desc'    => __( 'With Do: Set something instantly. Played backwards (hover out, a second click), it is undone.', 'fw' ),
				'value'   => 'hide',
				'choices' => array(
					'show'         => __( 'Show it', 'fw' ),
					'hide'         => __( 'Hide it', 'fw' ),
					'class_add'    => __( 'Add a CSS class', 'fw' ),
					'class_remove' => __( 'Remove a CSS class', 'fw' ),
					'class_toggle' => __( 'Toggle a CSS class', 'fw' ),
					'z_index'      => __( 'Bring it forward (z-index)', 'fw' ),
					'pointer_off'  => __( 'Stop it reacting to the mouse', 'fw' ),
					'pointer_on'   => __( 'Let it react to the mouse again', 'fw' ),
				),
			),
			'set_value' => array(
				'type'  => 'text',
				'label' => __( 'Class or value', 'fw' ),
				'desc'  => __( 'The class name(s) for a class, or the number for z-index.', 'fw' ),
				'value' => '',
				'attr'  => array( 'placeholder' => 'is-open  ·  10' ),
			),
			// Lottie and Rive (roadmap 6b): control the animation inside the target.
			'lottie' => array(
				'type'    => 'select',
				'label'   => __( 'Lottie', 'fw' ),
				'desc'    => __( 'With Do: Control its Lottie animation. The target is a Lottie element, or contains one.', 'fw' ),
				'value'   => 'play',
				'choices' => array(
					'play'    => __( 'Play', 'fw' ),
					'reverse' => __( 'Play backwards', 'fw' ),
					'pause'   => __( 'Pause', 'fw' ),
					'stop'    => __( 'Stop (back to the start)', 'fw' ),
					'frame'   => __( 'Go to a frame and stop', 'fw' ),
					'segment' => __( 'Play from one frame to another', 'fw' ),
				),
			),
			'lottie_from' => array( 'type' => 'text', 'label' => __( 'Frame', 'fw' ), 'desc' => __( 'For “Go to a frame”, and the first frame of “Play from one frame to another”.', 'fw' ), 'value' => '0', 'attr' => array( 'placeholder' => '0' ) ),
			'lottie_to'   => array( 'type' => 'text', 'label' => __( 'To frame', 'fw' ), 'value' => '', 'attr' => array( 'placeholder' => '60' ) ),
			'rive' => array(
				'type'    => 'select',
				'label'   => __( 'Rive', 'fw' ),
				'desc'    => __( 'With Do: Control its Rive animation. The target is a Rive element, or contains one.', 'fw' ),
				'value'   => 'trigger',
				'choices' => array(
					'play'    => __( 'Play', 'fw' ),
					'pause'   => __( 'Pause', 'fw' ),
					'trigger' => __( 'Fire a state-machine trigger', 'fw' ),
					'input'   => __( 'Set a state-machine input', 'fw' ),
				),
			),
			'rive_name'  => array( 'type' => 'text', 'label' => __( 'Trigger or input', 'fw' ), 'desc' => __( 'Its name in the Rive file, exactly as written there.', 'fw' ), 'value' => '', 'attr' => array( 'placeholder' => 'Pressed' ) ),
			'rive_value' => array( 'type' => 'text', 'label' => __( 'Input value', 'fw' ), 'desc' => __( 'A number, or true / false for a boolean input. Played backwards, the input goes back to its previous value.', 'fw' ), 'value' => '', 'attr' => array( 'placeholder' => 'true  ·  50' ) ),
			// Burst (roadmap 5b): a short radial pop from the target (the Confetti module draws it).
			'burst' => array(
				'type'    => 'select',
				'label'   => __( 'Burst', 'fw' ),
				'desc'    => __( 'With Do: Burst from it. Pops from the centre of the target; nothing to undo when played backwards.', 'fw' ),
				'value'   => 'mix',
				'choices' => array(
					'mix'      => __( 'Ring, sparks and lines', 'fw' ),
					'ring'     => __( 'Ring', 'fw' ),
					'sparks'   => __( 'Sparks', 'fw' ),
					'lines'    => __( 'Lines', 'fw' ),
					'confetti' => __( 'Confetti pop', 'fw' ),
				),
			),
			'burst_count' => array( 'type' => 'slider', 'label' => __( 'Burst rays / sparks', 'fw' ), 'value' => 10, 'properties' => array( 'min' => 3, 'max' => 60, 'step' => 1 ) ),
			'burst_size'  => array( 'type' => 'slider', 'label' => __( 'Burst size (px)', 'fw' ), 'value' => 70, 'properties' => array( 'min' => 10, 'max' => 400, 'step' => 5 ) ),
			'burst_colour' => array( 'type' => 'rgba-color-picker', 'label' => __( 'Burst colour', 'fw' ), 'desc' => __( 'Empty = a mix of brand colours.', 'fw' ), 'value' => '' ),
			'direction' => array(
				'type'    => 'select',
				'label'   => __( 'Direction', 'fw' ),
				'value'   => $d['direction'],
				'choices' => array(
					'to'   => __( 'To these values', 'fw' ),
					'from' => __( 'From these values', 'fw' ),
				),
			),
			'x'        => $slider( __( 'Move X (px)', 'fw' ), $d['x'], -600, 600, 1 ),
			'y'        => $slider( __( 'Move Y (px)', 'fw' ), $d['y'], -600, 600, 1 ),
			'scale'    => $slider( __( 'Scale', 'fw' ), $d['scale'], 0, 3, 0.01 ),
			'rotate'   => $slider( __( 'Rotate (°)', 'fw' ), $d['rotate'], -360, 360, 1 ),
			'opacity'  => $slider( __( 'Opacity', 'fw' ), $d['opacity'], 0, 1, 0.01 ),
			'blur'     => $slider( __( 'Blur (px)', 'fw' ), $d['blur'], 0, 30, 1 ),
			'more'     => upw_interactions_more_field(),
			'duration' => $slider( __( 'Duration (seconds)', 'fw' ), $d['duration'], 0, 4, 0.05 ),
			'delay'    => $slider( __( 'Delay (seconds)', 'fw' ), 0, 0, 4, 0.05 ),
			'ease'     => upw_interactions_ease_field( $d['ease'] ),
			'stagger'  => $slider( __( 'Stagger (seconds)', 'fw' ), 0.08, 0, 1, 0.01 ),
			'start'    => array(
				'type'    => 'select',
				'label'   => __( 'Starts', 'fw' ),
				'value'   => 'after',
				'choices' => array(
					'after' => __( 'After the previous step', 'fw' ),
					'with'  => __( 'With the previous step', 'fw' ),
				),
			),
		);
	}
endif;

if ( ! function_exists( 'upw_interactions_steps_field' ) ) :
	/** Steps 1–3 as tabs (the Live Editor adds a “Pick on the page” button beside each selector). $first = defaults for step 1; later steps start "Not used". */
	function upw_interactions_steps_field( $first ) {
		$base = array( 'target' => 'self', 'selector' => '', 'direction' => 'to', 'x' => 0, 'y' => 0, 'scale' => 1, 'rotate' => 0, 'opacity' => 1, 'blur' => 0, 'duration' => 0.5, 'ease' => 'out' );
		$tabs = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$d = $i === 1 ? array_merge( $base, $first ) : array_merge( $base, array( 'target' => 'none' ) );
			/* translators: %d: step number */
			$tabs[ 's' . $i ] = array( 'title' => sprintf( __( 'Step %d', 'fw' ), $i ), 'options' => upw_interactions_step_options( $d, $i === 1 ) );
		}
		return array( 'type' => 'tabs', 'label' => false, 'value' => array(), 'tabs' => $tabs );
	}
endif;

/* The per-element "Interactions" card (multi: up to two on one element — e.g. a hover AND a click). */
add_filter( 'sc_animation_fields', function ( $fields ) {
	if ( ! is_array( $fields ) ) {
		return $fields;
	}
	$ext  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	$base = $ext ? $ext->get_declared_URI( '/modules/interactions/static/img' ) : '';
	$tile = function ( $file, $label ) use ( $base ) {
		return array(
			'small' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 66 ),
			'large' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 132 ),
			'label' => $label,
		);
	};
	$steps_note = array(
		'type'  => 'html',
		'label' => false,
		'desc'  => false,
		'html'  => '<p class="upw-ix-note" style="margin:0;color:#646970;font-size:12px;line-height:1.5;">'
			. esc_html__( 'Each step animates this element or other ones, one after another (or together, with “Starts: With the previous step”).', 'fw' ) . ' '
			. esc_html__( '“That match” takes a CSS ID (#promo), a class (.card-arrow) or a tag (img): give the element an ID or class in its Advanced tab, or in the Live Editor click “Pick on the page”. “Inside” with nothing typed animates its direct children.', 'fw' ) . ' '
			. esc_html__( '“To” moves it from how it looks now to the values; “From” starts at the values and returns to how it looks now (a reveal). Stagger is the gap between several matched elements.', 'fw' ) . ' '
			. esc_html__( '“Do: Put it in a state” (or take it out, or toggle it) switches the target to a named state set up in its own States card (Open, Active…); the values below are then not used.', 'fw' ) . ' '
			. esc_html__( '“Set something instantly” shows, hides, adds a class or changes z-index with no animation; “Control its Lottie / Rive animation” plays, pauses or steers the animation inside the target.', 'fw' )
			. '</p>',
	);

	// Reuse and breakpoints (roadmap I5): which devices it runs on, and whether it runs on this element only
	// or on every element on the page that matches a selector (e.g. every .card, set up once).
	$devices = array(
		'type'    => 'checkboxes',
		'label'   => __( 'Runs on', 'fw' ),
		'value'   => array( 'desktop' => true, 'tablet' => true, 'phone' => true ),
		'choices' => array(
			'desktop' => __( 'Desktop', 'fw' ),
			'tablet'  => __( 'Tablet', 'fw' ),
			'phone'   => __( 'Phone', 'fw' ),
		),
		'inline'  => true,
	);
	$amount_t = array(
		'type'       => 'slider',
		'label'      => __( 'Amount on tablet (%)', 'fw' ),
		'desc'       => __( 'How far the steps go on a tablet, compared with desktop: movement, rotation, blur, and the scale and fade changes. 100 = the same; 50 = half as far.', 'fw' ),
		'value'      => 100,
		'properties' => array( 'min' => 0, 'max' => 200, 'step' => 5 ),
	);
	$amount_p = array(
		'type'       => 'slider',
		'label'      => __( 'Amount on phone (%)', 'fw' ),
		'value'      => 100,
		'properties' => array( 'min' => 0, 'max' => 200, 'step' => 5 ),
	);
	$scope = array(
		'type'    => 'select',
		'label'   => __( 'Runs for', 'fw' ),
		'value'   => 'self',
		'choices' => array(
			'self'    => __( 'This element', 'fw' ),
			'matches' => __( 'Every element on the page that matches…', 'fw' ),
		),
	);
	$scope_sel = array(
		'type'  => 'text',
		'label' => __( 'Matches', 'fw' ),
		'desc'  => __( 'e.g. .card: each matching element gets this interaction, and “This element” / “inside it” in the steps mean that element.', 'fw' ),
		'value' => '',
		'attr'  => array( 'class' => 'upw-ix-scope', 'placeholder' => '.card' ),
	);

	$sitewide = array(
		'type'         => 'switch',
		'label'        => __( 'Use on every page', 'fw' ),
		'desc'         => __( 'With “Every element on the page that matches…”: also run it on the matching elements of every page of the site, once this page is published. Theme Settings → Site-wide UX → Interactions lists them.', 'fw' ),
		'value'        => 'no',
		'left-choice'  => array( 'value' => 'no', 'label' => __( 'No', 'fw' ) ),
		'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
	);

	$fields['upw_interactions'] = array(
		'type'         => 'multi-picker',
		'popover'      => true,
		'label'        => __( 'Interactions', 'fw' ),
		'desc'         => __( 'When this element is hovered, clicked, loaded or scrolled to, animate it or other elements: hover a card to lift it and zoom its image, click a button to slide in a panel, scroll past the hero to shrink the header.', 'fw' ),
		'help'         => __( 'Interactions (Animation Engine): a trigger on this element plays up to three steps, and each step animates this element, elements inside it, or any elements on the page you name with a CSS ID, class or tag (or pick in the Live Editor). Hover plays on the way in and reverses on the way out; Click can reverse, replay or play once; In view can reverse when it leaves; Scrolled past reverses when you scroll back. Moves, scales, rotates, fades and blurs on top of any other effect the target has. Honours “reduce motion” (jumps straight to the end). Loads only on pages that use it.', 'fw' ),
		'show_borders' => false,
		'value'        => array( 'mode' => 'off' ),
		'placeholder'  => __( 'Off', 'fw' ),
		'anim_meta'    => array( 'category' => __( 'Interaction', 'fw' ), 'multi' => true, 'multi_max' => 2 ),
		'picker'       => array(
			'mode' => array(
				'type'    => 'image-picker',
				'label'   => false,
				'value'   => 'off',
				'choices' => array(
					'hover' => $tile( 'hover', __( 'On hover', 'fw' ) ),
					'click' => $tile( 'click', __( 'On click', 'fw' ) ),
					'load'  => $tile( 'load', __( 'On page load', 'fw' ) ),
					'view'  => $tile( 'view', __( 'In view', 'fw' ) ),
					'past'  => $tile( 'past', __( 'Scrolled past', 'fw' ) ),
				),
			),
		),
		'choices' => array(
			'off'   => array(),
			'hover' => array(
				'devices' => $devices,
				'amount_tablet' => $amount_t,
				'amount_phone'  => $amount_p,
				'scope'   => $scope,
				'scope_selector' => $scope_sel,
				'sitewide' => $sitewide,
				'note'  => $steps_note,
				'steps' => upw_interactions_steps_field( array( 'y' => -8, 'duration' => 0.35 ) ),
			),
			'click' => array(
				'again' => array(
					'type'    => 'select',
					'label'   => __( 'Clicking again', 'fw' ),
					'value'   => 'reverse',
					'choices' => array(
						'reverse' => __( 'Plays it backwards (a toggle)', 'fw' ),
						'replay'  => __( 'Plays it again from the start', 'fw' ),
						'none'    => __( 'Does nothing (plays once)', 'fw' ),
					),
				),
				'devices' => $devices,
				'amount_tablet' => $amount_t,
				'amount_phone'  => $amount_p,
				'scope'   => $scope,
				'scope_selector' => $scope_sel,
				'sitewide' => $sitewide,
				'note'  => $steps_note,
				'steps' => upw_interactions_steps_field( array( 'scale' => 1.06, 'duration' => 0.3, 'ease' => 'back' ) ),
			),
			'load'  => array(
				'devices' => $devices,
				'amount_tablet' => $amount_t,
				'amount_phone'  => $amount_p,
				'scope'   => $scope,
				'scope_selector' => $scope_sel,
				'sitewide' => $sitewide,
				'note'  => $steps_note,
				'steps' => upw_interactions_steps_field( array( 'direction' => 'from', 'y' => 30, 'opacity' => 0, 'duration' => 0.7 ) ),
			),
			'view'  => array(
				'leave' => array(
					'type'    => 'select',
					'label'   => __( 'When it leaves the screen', 'fw' ),
					'value'   => 'reverse',
					'choices' => array(
						'reverse' => __( 'Play it backwards (plays again next time)', 'fw' ),
						'keep'    => __( 'Keep the end state (plays once)', 'fw' ),
					),
				),
				'devices' => $devices,
				'amount_tablet' => $amount_t,
				'amount_phone'  => $amount_p,
				'scope'   => $scope,
				'scope_selector' => $scope_sel,
				'sitewide' => $sitewide,
				'note'  => $steps_note,
				'steps' => upw_interactions_steps_field( array( 'direction' => 'from', 'y' => 40, 'opacity' => 0, 'duration' => 0.7 ) ),
			),
			'past'  => array(
				'offset' => array(
					'type'       => 'slider',
					'label'      => __( 'Plays when its bottom passes (px from the top of the screen)', 'fw' ),
					'desc'       => __( 'Plays once the bottom of this element has scrolled above this line, and reverses when you scroll back. Put it on the hero and target the header (e.g. header) to shrink the header after the hero.', 'fw' ),
					'value'      => 80,
					'properties' => array( 'min' => 0, 'max' => 600, 'step' => 1 ),
				),
				'devices' => $devices,
				'amount_tablet' => $amount_t,
				'amount_phone'  => $amount_p,
				'scope'   => $scope,
				'scope_selector' => $scope_sel,
				'sitewide' => $sitewide,
				'note'  => $steps_note,
				'steps' => upw_interactions_steps_field( array( 'target' => 'page', 'selector' => 'header', 'scale' => 0.96, 'opacity' => 0.95, 'duration' => 0.3 ) ),
			),
		),
	);
	return $fields;
} );
