<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Physics module: settings / options declarations.
 *
 * The per-element "Physics" group appended to the Animations tab (via `sc_animation_fields`),
 * and the global on/off sub-tab under Theme Settings → Animations → Physics (via
 * `upw_anim_engine_module_tabs`).
 */

/* ------------------------------------------------------------------ *
 * 1) The per-element "Physics" group, appended to the Animations tab.
 * ------------------------------------------------------------------ */
add_filter( 'sc_animation_fields', function ( $fields ) {
	if ( ! is_array( $fields ) ) {
		return $fields;
	}

	$ext  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	$base = $ext ? $ext->get_declared_URI( '/modules/physics/static/img/effects' ) : '';
	$tile = function ( $file, $label ) use ( $base ) {
		return array(
			// These swatches use the taller viewBox 100×118 with a baked-in name label; 107px reads
			// clearly at 5-per-row in the medium modal.
			'small' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 86 ),
			'large' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 190 ),
			'label' => $label,
		);
	};

	$fields['physics'] = array(
		'type'         => 'multi-picker',
		'label'        => __( 'Physics', 'fw' ),
		'desc'         => __( 'A physics-driven motion applied to this element.', 'fw' ),
		'help'         => __( 'Physics Effects (Animation Engine): a catalog of spring/verlet motions — drag & throw, cursor spring/attract/repel/orbit, float/sway/pendulum/breathe, gravity drop/rise/sag/ragdoll, pop, jelly/squash/recoil/shake/spin. No library. Honours "reduce motion"; pointer-following effects are skipped on touch.', 'fw' ) . ( function_exists( 'upw_perf_note' ) ? ' ' . upw_perf_note() : '' ),
		'popover'      => true,
		'show_borders' => false,
		'value'        => array( 'effect' => 'none' ),
		'placeholder'  => __( 'None', 'fw' ),
		'anim_meta'    => array( 'category' => __( 'Interaction', 'fw' ), 'icon' => '&#9883;' ), // ⚛ (Animations-tab inserter)
		'picker'       => array(
			'effect' => array(
				'type'    => 'image-picker',
				'label'   => false,
				'desc'    => __( 'Hover a tile to preview it larger.', 'fw' ),
				'value'   => 'none',
				'search'  => __( 'Search physics effects…', 'fw' ),
				'layout'  => 'tabs',
				'choices' => upw_ae_group_tiles(
					array(
						'none'         => $tile( 'none',         __( 'None', 'fw' ) ),
						// Drag
						'draggable'    => $tile( 'draggable',    __( 'Draggable', 'fw' ) ),
						'slingshot'    => $tile( 'slingshot',    __( 'Slingshot', 'fw' ) ),
						// Pointer
						'attract'      => $tile( 'attract',      __( 'Attract', 'fw' ) ),
						'tilt_inertia' => $tile( 'tilt_inertia', __( 'Inertia Tilt', 'fw' ) ),
						'orbit_cursor' => $tile( 'orbit_cursor', __( 'Orbit Cursor', 'fw' ) ),
						'repel'        => $tile( 'repel',        __( 'Repel', 'fw' ) ),
						'rubber_band'  => $tile( 'rubber_band',  __( 'Rubber Band', 'fw' ) ),
						'spring'       => $tile( 'spring',       __( 'Spring Follow', 'fw' ) ),
						// Ambient
						'breathing'    => $tile( 'breathing',    __( 'Breathing', 'fw' ) ),
						'drift'        => $tile( 'drift',        __( 'Drift', 'fw' ) ),
						'float'        => $tile( 'float',        __( 'Float', 'fw' ) ),
						'levitate'     => $tile( 'levitate',     __( 'Levitate', 'fw' ) ),
						'orbit'        => $tile( 'orbit',        __( 'Orbit Point', 'fw' ) ),
						'pendulum'     => $tile( 'pendulum',     __( 'Pendulum', 'fw' ) ),
						'sway'         => $tile( 'sway',         __( 'Wind Sway', 'fw' ) ),
						'wobble'       => $tile( 'wobble',       __( 'Wobble', 'fw' ) ),
						// Entrance
						'gravity'      => $tile( 'gravity',      __( 'Gravity Drop', 'fw' ) ),
						'rise'         => $tile( 'rise',         __( 'Gravity Rise', 'fw' ) ),
						'pop'          => $tile( 'pop',          __( 'Pop In', 'fw' ) ),
						'ragdoll'      => $tile( 'ragdoll',      __( 'Ragdoll', 'fw' ) ),
						'sag'          => $tile( 'sag',          __( 'Weight Sag', 'fw' ) ),
						// Container
						'bounded'      => $tile( 'bounded',      __( 'Bounce Box', 'fw' ) ),
						// Reaction
						'jelly'        => $tile( 'jelly',        __( 'Jelly', 'fw' ) ),
						'spin'         => $tile( 'spin',         __( 'Momentum Spin', 'fw' ) ),
						'recoil'       => $tile( 'recoil',       __( 'Recoil', 'fw' ) ),
						'shake'        => $tile( 'shake',        __( 'Shake', 'fw' ) ),
						'squash'       => $tile( 'squash',       __( 'Squash & Stretch', 'fw' ) ),
					),
					array(
						'grp_drag' => array( 'label' => __( 'Drag', 'fw' ), 'ids' => array( 'draggable', 'slingshot' ) ),
						'grp_pointer' => array( 'label' => __( 'Pointer', 'fw' ), 'ids' => array( 'attract', 'tilt_inertia', 'orbit_cursor', 'repel', 'rubber_band', 'spring' ) ),
						'grp_ambient' => array( 'label' => __( 'Ambient', 'fw' ), 'ids' => array( 'breathing', 'drift', 'float', 'levitate', 'orbit', 'pendulum', 'sway', 'wobble' ) ),
						'grp_entrance' => array( 'label' => __( 'Entrance', 'fw' ), 'ids' => array( 'gravity', 'rise', 'pop', 'ragdoll', 'sag' ) ),
						'grp_reaction' => array( 'label' => __( 'Reaction', 'fw' ), 'ids' => array( 'bounded', 'jelly', 'spin', 'recoil', 'shake', 'squash' ) ),
					),
					array( 'none' )
				),
			),
		),
		'choices' => array(
			'draggable' => array(
				'return' => array(
					'type'    => 'select',
					'label'   => __( 'On release', 'fw' ),
					'value'   => 'spring',
					'choices' => array( 'spring' => __( 'Spring back to place', 'fw' ), 'free' => __( 'Stay (glide to a stop)', 'fw' ) ),
				),
				'stiffness' => upw_phys_slider( __( 'Springiness', 'fw' ), 0.15, 0.03, 0.4, 0.01 ),
				'axis' => array(
					'type'    => 'select',
					'label'   => __( 'Axis', 'fw' ),
					'value'   => 'both',
					'choices' => array( 'both' => __( 'Both', 'fw' ), 'x' => __( 'Horizontal only', 'fw' ), 'y' => __( 'Vertical only', 'fw' ) ),
				),
			),
			'slingshot' => array(
				'power' => upw_phys_slider( __( 'Bounciness', 'fw' ), 0.7, 0.3, 0.95, 0.05, __( 'Higher = more overshoot before it settles.', 'fw' ) ),
			),
			'spring' => array(
				'strength'  => upw_phys_slider( __( 'Reach', 'fw' ), 0.25, 0.05, 0.6, 0.05, __( 'How far it leans toward the cursor.', 'fw' ) ),
				'stiffness' => upw_phys_slider( __( 'Springiness', 'fw' ), 0.12, 0.03, 0.35, 0.01 ),
			),
			'attract' => array(
				'strength'  => upw_phys_slider( __( 'Pull', 'fw' ), 0.6, 0.2, 1, 0.05, __( 'How strongly it follows the cursor.', 'fw' ) ),
				'stiffness' => upw_phys_slider( __( 'Springiness', 'fw' ), 0.15, 0.03, 0.4, 0.01 ),
			),
			'repel' => array(
				'radius'   => upw_phys_slider( __( 'Radius (px)', 'fw' ), 120, 40, 300, 10 ),
				'strength' => upw_phys_slider( __( 'Push strength', 'fw' ), 0.6, 0.1, 1.5, 0.1 ),
			),
			'orbit_cursor' => array(
				'radius' => upw_phys_slider( __( 'Radius (px)', 'fw' ), 26, 10, 80, 2 ),
				'speed'  => upw_phys_slider( __( 'Speed', 'fw' ), 1, 0.3, 3, 0.1 ),
			),
			'rubber_band' => array(
				'strength' => upw_phys_slider( __( 'Stretch', 'fw' ), 0.4, 0.1, 0.9, 0.05 ),
			),
			'tilt_inertia' => array(
				'max_tilt' => upw_phys_slider( __( 'Max tilt (°)', 'fw' ), 14, 4, 30, 1 ),
			),
			'float' => array(
				'amount' => upw_phys_slider( __( 'Amount (px)', 'fw' ), 12, 2, 40, 1 ),
				'speed'  => upw_phys_slider( __( 'Speed', 'fw' ), 1, 0.2, 2.5, 0.1 ),
				'rotate' => array( 'type' => 'switch', 'label' => __( 'Add a gentle sway', 'fw' ), 'value' => 'yes', 'left-choice' => array( 'value' => 'no', 'label' => __( 'Off', 'fw' ) ), 'right-choice' => array( 'value' => 'yes', 'label' => __( 'On', 'fw' ) ) ),
			),
			'levitate' => array(
				'rise' => upw_phys_slider( __( 'Lift (px)', 'fw' ), 20, 6, 60, 2 ),
				'bob'  => upw_phys_slider( __( 'Bob (px)', 'fw' ), 8, 2, 24, 1 ),
			),
			'sway' => array(
				'angle' => upw_phys_slider( __( 'Sway (°)', 'fw' ), 6, 2, 20, 1 ),
				'speed' => upw_phys_slider( __( 'Speed', 'fw' ), 1, 0.3, 2.5, 0.1 ),
			),
			'pendulum' => array(
				'angle'  => upw_phys_slider( __( 'Swing (°)', 'fw' ), 8, 2, 30, 1 ),
				'speed'  => upw_phys_slider( __( 'Speed', 'fw' ), 1, 0.3, 2.5, 0.1 ),
				'anchor' => array( 'type' => 'select', 'label' => __( 'Hang from', 'fw' ), 'value' => 'top', 'choices' => array( 'top' => __( 'Top center', 'fw' ), 'left' => __( 'Top left', 'fw' ) ) ),
			),
			'wobble' => array(
				'amount' => upw_phys_slider( __( 'Amount (°)', 'fw' ), 3, 1, 12, 0.5 ),
				'speed'  => upw_phys_slider( __( 'Speed', 'fw' ), 1, 0.3, 3, 0.1 ),
			),
			'breathing' => array(
				'amount' => upw_phys_slider( __( 'Amount', 'fw' ), 0.06, 0.02, 0.2, 0.01 ),
				'speed'  => upw_phys_slider( __( 'Speed', 'fw' ), 1, 0.3, 2.5, 0.1 ),
			),
			'drift' => array(
				'amount' => upw_phys_slider( __( 'Amount (px)', 'fw' ), 14, 4, 50, 1 ),
				'speed'  => upw_phys_slider( __( 'Speed', 'fw' ), 1, 0.3, 2.5, 0.1 ),
			),
			'orbit' => array(
				'radius' => upw_phys_slider( __( 'Radius (px)', 'fw' ), 20, 5, 60, 1 ),
				'speed'  => upw_phys_slider( __( 'Speed', 'fw' ), 1, 0.3, 3, 0.1 ),
			),
			'gravity' => array(
				'drop'   => upw_phys_slider( __( 'Drop from (px)', 'fw' ), 120, 30, 400, 10 ),
				'bounce' => upw_phys_slider( __( 'Bounciness', 'fw' ), 0.5, 0, 0.85, 0.05 ),
			),
			'rise' => array(
				'drop'   => upw_phys_slider( __( 'Rise from (px)', 'fw' ), 120, 30, 400, 10 ),
				'bounce' => upw_phys_slider( __( 'Bounciness', 'fw' ), 0.5, 0, 0.85, 0.05 ),
			),
			'sag' => array(
				'drop' => upw_phys_slider( __( 'Sag from (px)', 'fw' ), 60, 20, 300, 10 ),
			),
			'ragdoll' => array(
				'drop' => upw_phys_slider( __( 'Drop from (px)', 'fw' ), 120, 30, 400, 10 ),
			),
			'pop' => array(
				'bounce' => upw_phys_slider( __( 'Bounciness', 'fw' ), 0.6, 0.1, 1, 0.05 ),
			),
			'bounded' => array(
				'speed' => upw_phys_slider( __( 'Speed', 'fw' ), 1, 0.3, 3, 0.1 ),
			),
			'jelly' => array(
				'intensity' => upw_phys_slider( __( 'Wobble', 'fw' ), 0.5, 0.15, 1, 0.05 ),
				'trigger'   => upw_phys_trigger( 'hover' ),
			),
			'squash' => array(
				'intensity' => upw_phys_slider( __( 'Intensity', 'fw' ), 0.5, 0.15, 1, 0.05 ),
				'trigger'   => upw_phys_trigger( 'hover' ),
			),
			'recoil' => array(
				'distance' => upw_phys_slider( __( 'Kick (px)', 'fw' ), 14, 4, 40, 1 ),
				'trigger'  => upw_phys_trigger( 'click' ),
			),
			'shake' => array(
				'intensity' => upw_phys_slider( __( 'Intensity', 'fw' ), 0.5, 0.15, 1, 0.05 ),
				'trigger'   => upw_phys_trigger( 'hover' ),
			),
			'spin' => array(
				'speed'   => upw_phys_slider( __( 'Spin speed', 'fw' ), 1, 0.3, 3, 0.1 ),
				'trigger' => upw_phys_trigger( 'hover' ),
			),
		),
	);

	return $fields;
} );
