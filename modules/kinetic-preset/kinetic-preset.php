<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Kinetic Scenes preset module (entry).
 *
 * Plays a Kinetic Scenes preset (Gravity drop, Rise, Slam, Strike, Jiggle…) on an ordinary page element, using the
 * same player the Kinetic Scene element ships (its playPreset()), so an effect is built once and used both in
 * scenes and on a page's own headings, text, pictures and buttons. Per-element (the Animations tab).
 *
 * Saved value shape (multi-picker, picker id `mode` = a preset group):
 *   [ 'mode' => 'text', 'text' => [ 'preset', 'duration', 'delay', 'trigger', 'direction', 'replay', 'settings' ] ]
 *
 * Parts (modules/AGENTS.md, "Splitting a large module"):
 *   - includes/kinetic-preset-presets.php  — the presets a page element can use (generated from the catalogue)
 *   - includes/kinetic-preset-settings.php — the per-element picker
 *   - includes/kinetic-preset-render.php   — the wrapper emit + the on-demand asset registration
 */

if ( ! defined( 'UPW_KINETIC_PRESET_DIR' ) ) {
	define( 'UPW_KINETIC_PRESET_DIR', __DIR__ );
}

if ( ! function_exists( 'upw_kinetic_preset_enabled' ) ) :
	function upw_kinetic_preset_enabled() {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return true;
		}
		$v = fw_get_db_settings_option( 'animation_kinetic_preset', array() );
		$e = ( is_array( $v ) && isset( $v['enable'] ) ) ? $v['enable'] : 'yes';
		return $e !== 'no' && $e !== false;
	}
endif;

require_once __DIR__ . '/includes/kinetic-preset-presets.php';
require_once __DIR__ . '/includes/kinetic-preset-settings.php';
require_once __DIR__ . '/includes/kinetic-preset-render.php';
