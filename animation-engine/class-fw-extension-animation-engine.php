<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine extension.
 *
 * The home for UnysonPlus's animation capabilities. Its first module is WebGL — the
 * `[webgl_object]` leaf shortcode under shortcodes/webgl-object/, which the shortcodes
 * loader auto-discovers for any active extension. Future modules (more shaders, hover
 * effects, scroll motion) plug in the same way.
 *
 * Also adds an "Animations" section to Appearance → Theme Settings (available under
 * any active theme) — the home for the engine's global options.
 */
class FW_Extension_Animation_Engine extends FW_Extension {

	/**
	 * @internal
	 */
	public function _init() {
		// The WebGL shortcode is auto-discovered; here we register the engine's Theme
		// Settings "Animations" section. Loaded always — the fw_settings_options filter
		// is consumed on the front end too, not just in admin.
		require_once dirname( __FILE__ ) . '/includes/theme-settings.php';

		// Modules. Each plugs into the shared Animations tab / wrapper / Theme Settings.
		require_once dirname( __FILE__ ) . '/modules/scroll-motion/scroll-motion.php'; // Scroll Motion (GSAP)
		require_once dirname( __FILE__ ) . '/modules/hover/hover.php';                  // Hover Interactions
		require_once dirname( __FILE__ ) . '/modules/cursor/cursor.php';                // Custom Cursor (site-wide)
		require_once dirname( __FILE__ ) . '/modules/text-effects/text-effects.php';    // Text Effects (per-element)
		require_once dirname( __FILE__ ) . '/modules/backgrounds/backgrounds.php';      // Animated Backgrounds (sections/rows)
	}
}
