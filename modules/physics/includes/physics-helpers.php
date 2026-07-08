<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Physics module: helpers.
 *
 * The setting reader (upw_physics_enabled), the effect-id registry (upw_physics_effects) — the
 * single source of truth for emit + wrapper checks — and the
 * small option-builders (upw_phys_slider / upw_phys_trigger) that keep the choices array readable.
 * Loaded FIRST; the settings + render parts depend on these.
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
