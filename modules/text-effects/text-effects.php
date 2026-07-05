<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Text Effects module (entry).
 *
 * Adds a "Text Effect" group to EVERY element's Animations tab (via the shortcodes extension's
 * `sc_animation_fields` filter), emits the chosen effect onto the element wrapper (via
 * `sc_build_wrapper_attr`), and ships a self-contained vanilla-JS runtime (no GSAP) enqueued
 * only on pages that actually use an effect. Global on/off lives in Theme Settings → Animations →
 * Text.
 *
 * Effects: split_reveal · scramble · typewriter · shimmer · wave · glitch · vf_weight (plus the
 * Wave-A reveal variants and Wave-B/C CSS + JS effects). Saved value shape (multi-picker, picker
 * id `effect`): [ 'effect' => 'split_reveal', 'split_reveal' => [ 'split_by' => 'words', … ] ].
 *
 * This file is a thin loader — the module is split into includes/ for maintainability
 * (see modules/AGENTS.md, "Splitting a large module"):
 *   - includes/text-effects-helpers.php   — master switch, used-flag, effect registry, color helpers
 *   - includes/text-effects-settings.php  — the per-element field + the Theme Settings → Text sub-tab
 *   - includes/text-effects-render.php    — the wrapper emit, needs-wrapper force, on-demand assets
 *
 * UPW_TEXT_EFFECTS_DIR is the module root; the render part uses it (not __DIR__) to resolve the
 * static asset path for the on-demand loader, since it lives in includes/.
 */

if ( ! defined( 'UPW_TEXT_EFFECTS_DIR' ) ) {
	define( 'UPW_TEXT_EFFECTS_DIR', __DIR__ );
}

require_once __DIR__ . '/includes/text-effects-helpers.php';   // must load first (settings + render use it)
require_once __DIR__ . '/includes/text-effects-settings.php';
require_once __DIR__ . '/includes/text-effects-render.php';
