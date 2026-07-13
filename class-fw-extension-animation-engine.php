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

		// Allow .glb / .gltf model uploads in the Media Library (for [model_viewer]).
		require_once dirname( __FILE__ ) . '/includes/glb-upload.php';

		// Shared on-demand, per-style asset loader (a style's CSS/JS ships only on
		// pages that use it). Loaded before modules so they can register at load.
		require_once dirname( __FILE__ ) . '/includes/asset-loader.php';

		// Modules. Each plugs into the shared Animations tab / wrapper / Theme Settings.
		require_once dirname( __FILE__ ) . '/modules/scroll-motion/scroll-motion.php'; // Scroll Motion (GSAP)
		require_once dirname( __FILE__ ) . '/modules/hover/hover.php';                  // Hover Interactions
		require_once dirname( __FILE__ ) . '/modules/physics/physics.php';              // Physics Effects (per-element)
		require_once dirname( __FILE__ ) . '/modules/parallax/parallax.php';            // Parallax Depth Layers (per-element)
		require_once dirname( __FILE__ ) . '/modules/marquee/marquee.php';              // Marquee (per-element seamless ticker)
		require_once dirname( __FILE__ ) . '/modules/cursor/cursor.php';                // Custom Cursor (site-wide)
		require_once dirname( __FILE__ ) . '/modules/text-effects/text-effects.php';    // Text Effects (per-element)
		require_once dirname( __FILE__ ) . '/modules/backgrounds/backgrounds.php';      // Animated Backgrounds (sections/rows)
		require_once dirname( __FILE__ ) . '/modules/page-transitions/page-transitions.php'; // Page Transitions (site-wide)
		require_once dirname( __FILE__ ) . '/modules/scroll-loop/scroll-loop.php';      // Seamless / Infinite Scroll Loop (Lenis)
		require_once dirname( __FILE__ ) . '/modules/scroll-progress/scroll-progress.php'; // Scroll Progress indicator (site-wide)
		require_once dirname( __FILE__ ) . '/modules/sticky-stack/sticky-stack.php';    // Sticky Card Stack (Section-level, scroll)
		require_once dirname( __FILE__ ) . '/modules/horizontal-scroll/horizontal-scroll.php'; // Horizontal Scroll Section (Section-level, scroll)
		require_once dirname( __FILE__ ) . '/modules/scroll-reveal/scroll-reveal.php';    // Scroll Reveal (per-element clip wipe)
		require_once dirname( __FILE__ ) . '/modules/flip-card/flip-card.php';          // 3D Flip Card (per-element, pointer)
		require_once dirname( __FILE__ ) . '/modules/scroll-text-highlight/scroll-text-highlight.php'; // Scroll Text Highlight (per-element, scroll)
		require_once dirname( __FILE__ ) . '/modules/scroll-color-shift/scroll-color-shift.php'; // Scroll Color Shift (Section-level, scroll)
		require_once dirname( __FILE__ ) . '/modules/scrollytelling/scrollytelling.php'; // Scrollytelling / Pinned Steps (Section-level, scroll)
		require_once dirname( __FILE__ ) . '/modules/preloader/preloader.php';                 // Preloader / Page Loader (site-wide)
		require_once dirname( __FILE__ ) . '/modules/motion-path/motion-path.php';       // Motion Path (per-element — element travels a path)
		require_once dirname( __FILE__ ) . '/modules/confetti/confetti.php';             // Confetti (per-element — celebratory particle burst)

		// Central effects control — consolidates the per-module enable switches into one
		// "Effects" tab and hides a disabled module's options. Loaded last (after modules).
		require_once dirname( __FILE__ ) . '/includes/effects-control.php';

		// Tools → Animation Diagnostics — inspect/reset a page's saved per-element animation values.
		require_once dirname( __FILE__ ) . '/includes/animation-diagnostics.php';
	}
}
