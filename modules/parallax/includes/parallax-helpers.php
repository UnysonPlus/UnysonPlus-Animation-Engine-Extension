<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Parallax module: helpers.
 *
 * The enable reader, the per-page usage flag, and the slider / switch field builders shared by
 * the settings and render parts. Loaded first by parallax.php (the settings + render parts depend
 * on these). All wrapped in function_exists guards.
 */

if ( ! function_exists( 'upw_parallax_enabled' ) ) :
	function upw_parallax_enabled() {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return true;
		}
		$v = fw_get_db_settings_option( 'animation_parallax', array() );
		$e = ( is_array( $v ) && isset( $v['enable'] ) ) ? $v['enable'] : 'yes';
		return $e !== 'no' && $e !== false;
	}
endif;

if ( ! function_exists( 'upw_parallax_flag' ) ) :
	/** Static used-flag for Parallax; sets it when passed true and returns whether the module was used on the page. */
	function upw_parallax_flag( $set = false ) {
		static $used = false;
		if ( $set ) {
			$used = true;
		}
		return $used;
	}
endif;

if ( ! function_exists( 'upw_prlx_slider' ) ) :
	/** Builds a parallax slider option field by delegating to the shared upw_anim_slider builder. */
	function upw_prlx_slider( $label, $val, $min, $max, $step, $desc = '' ) {
		return upw_anim_slider( $label, $val, $min, $max, $step, $desc ); // delegates to the shared builder
	}
endif;
if ( ! function_exists( 'upw_prlx_switch' ) ) :
	/** Returns a parallax on/off switch option field with the given label, description, and default. */
	function upw_prlx_switch( $label, $desc = '', $default_yes = false ) {
		return array(
			'type'         => 'switch',
			'label'        => $label,
			'desc'         => $desc,
			'value'        => $default_yes ? 'yes' : 'no',
			'left-choice'  => array( 'value' => 'no',  'label' => __( 'Off', 'fw' ) ),
			'right-choice' => array( 'value' => 'yes', 'label' => __( 'On', 'fw' ) ),
		);
	}
endif;
