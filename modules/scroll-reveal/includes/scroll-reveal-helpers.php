<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Scroll Reveal module: helpers.
 *
 * The global master-switch reader and the per-request used-flag (gates the footer enqueue).
 * Loaded first by scroll-reveal.php — the settings + render parts depend on these. All wrapped
 * in function_exists guards.
 */

if ( ! function_exists( 'upw_scroll_reveal_enabled' ) ) :
	function upw_scroll_reveal_enabled() {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return true;
		}
		$v = fw_get_db_settings_option( 'animation_scroll_reveal', array() );
		$e = ( is_array( $v ) && isset( $v['enable'] ) ) ? $v['enable'] : 'yes';
		return $e !== 'no' && $e !== false;
	}
endif;

if ( ! function_exists( 'upw_scroll_reveal_flag' ) ) :
	function upw_scroll_reveal_flag( $set = false ) {
		static $used = false;
		if ( $set ) {
			$used = true;
		}
		return $used;
	}
endif;
