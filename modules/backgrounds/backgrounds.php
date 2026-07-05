<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Animated Backgrounds module (entry).
 *
 * - Injects a "Background Effect" picker into the Styling tab of CONTAINER shortcodes
 *   only (section / bleed-section / masonry-section / row) via `fw_shortcode_get_options`,
 *   so it never clutters text/leaf elements.
 * - Emits the chosen effect onto the container wrapper (via `sc_build_wrapper_attr`); a
 *   self-contained runtime injects a canvas / CSS layer behind the content.
 * - Runtime (JS/CSS) is enqueued only on pages that actually use a background.
 * - Global on/off lives in Theme Settings → Animations → Backgrounds.
 *
 * Effects: aurora · gradient · dots (CSS) · particles · constellation · waves · starfield · noise (canvas).
 * Saved value shape (multi-picker, picker id `effect`):
 *   [ 'effect' => 'particles', 'particles' => [ 'density' => 60, … ] ]
 *
 * This file is a thin loader — the module is split into includes/ for maintainability
 * (see modules/AGENTS.md, "Splitting a large module"):
 *   - includes/backgrounds-helpers.php   — setting/enable readers, effect + container registries, color helpers
 *   - includes/backgrounds-settings.php  — the container styling-tab picker + Theme Settings sub-tab
 *   - includes/backgrounds-render.php    — the wrapper filter + on-demand asset registration
 *
 * UPW_BACKGROUNDS_DIR is the module root; the render part uses it (not __DIR__) to resolve the
 * static asset path for the on-demand loader, since it lives in includes/.
 */

if ( ! defined( 'UPW_BACKGROUNDS_DIR' ) ) {
	define( 'UPW_BACKGROUNDS_DIR', __DIR__ );
}

require_once __DIR__ . '/includes/backgrounds-helpers.php';   // must load first (settings + render use it)
require_once __DIR__ . '/includes/backgrounds-settings.php';
require_once __DIR__ . '/includes/backgrounds-render.php';
