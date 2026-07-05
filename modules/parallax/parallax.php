<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Parallax Depth Layers module (entry).
 *
 * Multi-layer depth parallax: mark a container as a **Scene** (the tracking stage), then set a
 * **Depth** on each child **Layer**. Layers drift at different speeds as the pointer moves over
 * the scene (and/or as the scene scrolls), creating a sense of depth — hero scenes, floating
 * shapes, layered illustrations. A Layer with no explicit Scene falls back to tracking the whole
 * window, so a few depth layers "just work" without marking a stage.
 *
 * Adds a "Parallax Layers" control to EVERY element's Animations tab (via the shortcodes
 * extension's `sc_animation_fields` filter), emits the role + settings onto the element wrapper
 * (via `sc_build_wrapper_attr`), and ships a self-contained vanilla-JS runtime (one shared RAF
 * ticker, no library) enqueued only on pages that use it. Global on/off lives in Theme Settings →
 * Animations → Parallax.
 *
 * Saved value shape (multi-picker, picker id `role`):
 *   [ 'role' => 'layer', 'layer' => [ 'depth' => 30, 'axis' => 'both', … ] ]
 *
 * This file is a thin loader — the module is split into includes/ for maintainability
 * (see modules/AGENTS.md, "Splitting a large module"):
 *   - includes/parallax-helpers.php  — enable reader, usage flag, slider/switch field builders
 *   - includes/parallax-settings.php — the Animations-tab control + the Theme Settings sub-tab
 *   - includes/parallax-render.php   — the wrapper attrs + the front-end enqueue
 *
 * UPW_PARALLAX_DIR is the module root; the render part uses it (not __DIR__) to resolve static
 * asset paths for filemtime cache-busting, since it lives in includes/.
 */

if ( ! defined( 'UPW_PARALLAX_DIR' ) ) {
	define( 'UPW_PARALLAX_DIR', __DIR__ );
}

require_once __DIR__ . '/includes/parallax-helpers.php';   // must load first (settings + render use it)
require_once __DIR__ . '/includes/parallax-settings.php';
require_once __DIR__ . '/includes/parallax-render.php';
