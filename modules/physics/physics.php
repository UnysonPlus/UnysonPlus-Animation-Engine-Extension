<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Physics Effects module.
 *
 * Adds a "Physics" group to EVERY element's Animations tab (via the shortcodes
 * extension's `sc_animation_fields` filter), emits the chosen effect onto the element
 * wrapper (via `sc_build_wrapper_attr`), and ships a self-contained vanilla-JS runtime
 * (a tiny spring/verlet integrator) enqueued only on pages that use an effect. Global
 * on/off lives in Theme Settings → Animations → Physics.
 *
 * Effects (picker id `effect`), grouped:
 *   Pointer   : spring · attract · repel · orbit_cursor · rubber_band · tilt_inertia
 *   Drag      : draggable · slingshot
 *   Ambient   : float · levitate · sway · pendulum · wobble · breathing · drift · orbit
 *   Entrance  : gravity · rise · sag · ragdoll · pop
 *   Container : bounded
 *   Reaction  : jelly · squash · recoil · shake · spin
 * Saved value shape (multi-picker): [ 'effect' => 'float', 'float' => [ 'amount' => 12, … ] ]
 */

if ( ! function_exists( 'upw_physics_enabled' ) ) :
	function upw_physics_enabled() {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return true;
		}
		$v = fw_get_db_settings_option( 'animation_physics', array() );
		$e = ( is_array( $v ) && isset( $v['enable'] ) ) ? $v['enable'] : 'yes';
		return $e !== 'no' && $e !== false;
	}
endif;

if ( ! function_exists( 'upw_physics_flag' ) ) :
	function upw_physics_flag( $set = false ) {
		static $used = false;
		if ( $set ) {
			$used = true;
		}
		return $used;
	}
endif;

if ( ! function_exists( 'upw_physics_effects' ) ) :
	/** Valid physics-effect ids — single source of truth for emit + wrapper checks. */
	function upw_physics_effects() {
		return array(
			'draggable', 'slingshot', 'spring', 'attract', 'repel', 'orbit_cursor', 'rubber_band', 'tilt_inertia',
			'float', 'levitate', 'sway', 'pendulum', 'wobble', 'breathing', 'drift', 'orbit',
			'gravity', 'rise', 'sag', 'ragdoll', 'pop', 'bounded',
			'jelly', 'squash', 'recoil', 'shake', 'spin',
		);
	}
endif;

/* small option-builders to keep the (large) choices array readable */
if ( ! function_exists( 'upw_phys_slider' ) ) :
	function upw_phys_slider( $label, $val, $min, $max, $step, $desc = '' ) {
		$f = array( 'type' => 'slider', 'label' => $label, 'value' => $val, 'properties' => array( 'min' => $min, 'max' => $max, 'step' => $step ) );
		if ( $desc !== '' ) { $f['desc'] = $desc; }
		return $f;
	}
endif;
if ( ! function_exists( 'upw_phys_trigger' ) ) :
	function upw_phys_trigger( $default = 'hover' ) {
		return array(
			'type'    => 'select',
			'label'   => __( 'Trigger', 'fw' ),
			'value'   => $default,
			'choices' => array( 'hover' => __( 'On hover', 'fw' ), 'click' => __( 'On click / tap', 'fw' ) ),
		);
	}
endif;

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
			'small' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 107 ),
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
		'anim_meta'    => array( 'category' => __( 'Physics', 'fw' ), 'icon' => '&#9883;' ), // ⚛ (Animations-tab inserter)
		'picker'       => array(
			'effect' => array(
				'type'    => 'image-picker',
				'label'   => false,
				'desc'    => __( 'Hover a tile to preview it larger.', 'fw' ),
				'value'   => 'none',
				'choices' => array(
					'none'         => $tile( 'none',         __( 'None', 'fw' ) ),
					// Drag
					'draggable'    => $tile( 'draggable',    __( 'Draggable', 'fw' ) ),
					'slingshot'    => $tile( 'slingshot',    __( 'Slingshot', 'fw' ) ),
					// Pointer
					'spring'       => $tile( 'spring',       __( 'Spring Follow', 'fw' ) ),
					'attract'      => $tile( 'attract',      __( 'Attract', 'fw' ) ),
					'repel'        => $tile( 'repel',        __( 'Repel', 'fw' ) ),
					'orbit_cursor' => $tile( 'orbit_cursor', __( 'Orbit Cursor', 'fw' ) ),
					'rubber_band'  => $tile( 'rubber_band',  __( 'Rubber Band', 'fw' ) ),
					'tilt_inertia' => $tile( 'tilt_inertia', __( 'Inertia Tilt', 'fw' ) ),
					// Ambient
					'float'        => $tile( 'float',        __( 'Float', 'fw' ) ),
					'levitate'     => $tile( 'levitate',     __( 'Levitate', 'fw' ) ),
					'sway'         => $tile( 'sway',         __( 'Wind Sway', 'fw' ) ),
					'pendulum'     => $tile( 'pendulum',     __( 'Pendulum', 'fw' ) ),
					'wobble'       => $tile( 'wobble',       __( 'Wobble', 'fw' ) ),
					'breathing'    => $tile( 'breathing',    __( 'Breathing', 'fw' ) ),
					'drift'        => $tile( 'drift',        __( 'Drift', 'fw' ) ),
					'orbit'        => $tile( 'orbit',        __( 'Orbit Point', 'fw' ) ),
					// Entrance
					'gravity'      => $tile( 'gravity',      __( 'Gravity Drop', 'fw' ) ),
					'rise'         => $tile( 'rise',         __( 'Gravity Rise', 'fw' ) ),
					'sag'          => $tile( 'sag',          __( 'Weight Sag', 'fw' ) ),
					'ragdoll'      => $tile( 'ragdoll',      __( 'Ragdoll', 'fw' ) ),
					'pop'          => $tile( 'pop',          __( 'Pop In', 'fw' ) ),
					// Container
					'bounded'      => $tile( 'bounded',      __( 'Bounce Box', 'fw' ) ),
					// Reaction
					'jelly'        => $tile( 'jelly',        __( 'Jelly', 'fw' ) ),
					'squash'       => $tile( 'squash',       __( 'Squash & Stretch', 'fw' ) ),
					'recoil'       => $tile( 'recoil',       __( 'Recoil', 'fw' ) ),
					'shake'        => $tile( 'shake',        __( 'Shake', 'fw' ) ),
					'spin'         => $tile( 'spin',         __( 'Momentum Spin', 'fw' ) ),
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

/* ------------------------------------------------------------------ *
 * 2) Emit the chosen effect onto the element wrapper.
 * ------------------------------------------------------------------ */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	if ( ! upw_physics_enabled() ) {
		return $attr;
	}
	$px     = ( isset( $atts['physics'] ) && is_array( $atts['physics'] ) ) ? $atts['physics'] : array();
	$effect = isset( $px['effect'] ) ? (string) $px['effect'] : 'none';
	if ( ! in_array( $effect, upw_physics_effects(), true ) ) {
		return $attr;
	}
	$o = ( isset( $px[ $effect ] ) && is_array( $px[ $effect ] ) ) ? $px[ $effect ] : array();

	$cls           = isset( $attr['class'] ) ? trim( (string) $attr['class'] ) : '';
	$attr['class'] = esc_attr( trim( $cls . ' sc-phys sc-phys--' . sanitize_html_class( $effect ) ) );
	$attr['data-phys'] = esc_attr( $effect );

	// Stamp every option present as data-phys-<key> (numbers/strings). Keeps the emit
	// compact now that there are many effects; the JS reads only the ones it needs.
	foreach ( $o as $k => $v ) {
		if ( is_array( $v ) ) { continue; }
		$attr[ 'data-phys-' . sanitize_html_class( str_replace( '_', '-', $k ) ) ] = esc_attr( (string) $v );
	}

	upw_physics_flag( true );
	// On-demand assets: record this effect so ONLY its JS partial (+ the tiny base CSS) is
	// enqueued, not the whole 27-effect bundle.
	if ( function_exists( 'upw_anim_use_asset' ) ) {
		upw_anim_use_asset( 'physics', $effect );
	}
	return $attr;
}, 22, 2 );

/* ------------------------------------------------------------------ *
 * 2b) Force a wrapper when an element's ONLY non-default setting is a physics effect.
 * ------------------------------------------------------------------ */
add_filter( 'sc_needs_wrapper', function ( $needs, $atts ) {
	if ( $needs || ! upw_physics_enabled() ) {
		return $needs;
	}
	$px     = ( isset( $atts['physics'] ) && is_array( $atts['physics'] ) ) ? $atts['physics'] : array();
	$effect = isset( $px['effect'] ) ? (string) $px['effect'] : 'none';
	return in_array( $effect, upw_physics_effects(), true );
}, 10, 2 );

/* ------------------------------------------------------------------ *
 * 3) On-demand assets. Register the module's per-effect partial layout with the shared
 *    loader; a page ships ONLY the shared core (integrator) + the used effects' partials
 *    — recorded per element in the wrapper filter via upw_anim_use_asset(). js_core_first:
 *    the core defines the shared integrator/helpers that each effect partial aliases.
 * ------------------------------------------------------------------ */
if ( function_exists( 'upw_anim_register_assets' ) ) {
	$upw_phys_ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( $upw_phys_ext ) {
		upw_anim_register_assets( 'physics', array(
			'path'          => __DIR__,
			'uri'           => $upw_phys_ext->get_declared_URI( '/modules/physics' ),
			'ver'           => $upw_phys_ext->manifest->get_version(),
			'js_dir'        => 'static/js/effects',
			'base_css'      => 'static/css/physics.css',   // tiny, all-base (drag affordances) — no per-effect CSS
			'base_js'       => 'static/js/physics-core.js',
			'needs_raf'     => true,                        // uses the shared frame scheduler (window.upwAnimRaf)
			'js_core_first' => true,                        // core (integrator) loads before the effect partials
			'js_styles'     => upw_physics_effects(),       // every effect ships a JS partial (registers into window.upwPhys)
			// Pointer/drag/reaction helpers kept OUT of the core — loaded only when a pointer- or
			// trigger-driven effect (drag / spring / jelly / recoil / …) is on the page.
			'js_shared'     => array(
				'pointer' => array( 'draggable', 'slingshot', 'spring', 'attract', 'jelly', 'squash', 'recoil', 'shake', 'spin' ),
			),
			'js_cfg'        => function () {
				$cfg = array(
					'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
					'disableMobile' => ( function_exists( 'upw_anim_engine_setting' ) && upw_anim_engine_setting( 'disable_on_mobile', 'no' ) === 'yes' ),
				);
				return 'window.upwPhysicsCfg=' . wp_json_encode( $cfg ) . ';';
			},
		) );
	}
	unset( $upw_phys_ext );
}

/* ------------------------------------------------------------------ *
 * 4) Global on/off → Theme Settings → Animations → Physics sub-tab.
 * ------------------------------------------------------------------ */
add_filter( 'upw_anim_engine_module_tabs', function ( $tabs ) {
	$tabs['physics_effects'] = array(
		'title'   => __( 'Physics', 'fw' ),
		'type'    => 'tab',
		'options' => array(
			'physics_box' => array(
				'title'   => __( 'Physics Effects', 'fw' ),
				'type'    => 'box',
				'options' => array(
					'animation_physics' => array(
						'type'          => 'multi',
						'label'         => false,
						'inner-options' => array(
							'enable' => array(
								'label'        => __( 'Enable physics effects', 'fw' ),
								'desc'         => __( 'Master switch for the per-element Physics effects. Off = none load anywhere.', 'fw' ),
								'type'         => 'switch',
								'value'        => 'yes',
								'left-choice'  => array( 'value' => 'no',  'label' => __( 'No', 'fw' ) ),
								'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
							),
						),
					),
				),
			),
		),
	);
	return $tabs;
} );
