<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Marquee module: helpers.
 *
 * The enabled-state reader, the per-page "used" flag, and the slider field factory. Loaded first
 * by marquee.php (the settings + render parts depend on these). All wrapped in function_exists
 * guards for partial-upgrade double-load safety.
 */

if ( ! function_exists( 'upw_marquee_enabled' ) ) :
	function upw_marquee_enabled() {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return true;
		}
		$v = fw_get_db_settings_option( 'animation_marquee', array() );
		$e = ( is_array( $v ) && isset( $v['enable'] ) ) ? $v['enable'] : 'yes';
		return $e !== 'no' && $e !== false;
	}
endif;

if ( ! function_exists( 'upw_marquee_flag' ) ) :
	function upw_marquee_flag( $set = false ) {
		static $used = false;
		if ( $set ) {
			$used = true;
		}
		return $used;
	}
endif;

if ( ! function_exists( 'upw_mq_slider' ) ) :
	function upw_mq_slider( $label, $val, $min, $max, $step, $desc = '' ) {
		$f = array( 'type' => 'slider', 'label' => $label, 'value' => $val, 'properties' => array( 'min' => $min, 'max' => $max, 'step' => $step ) );
		if ( $desc !== '' ) { $f['desc'] = $desc; }
		return $f;
	}
endif;
