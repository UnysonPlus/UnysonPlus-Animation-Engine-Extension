<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Cursor module (entry).
 *
 * A site-wide custom cursor with a rich set of styles (dot / ring / crosshair / brackets / dashed
 * / glow / gradient / blob / spotlight / comet / custom image / glyph …) picked from an image
 * grid, plus cross-cutting modifiers (grow-on-hover, magnetic snap, difference blend,
 * hide-native). Config lives in Theme Settings → Site-wide UX → Cursor; the runtime enqueues on
 * the front end ONLY when enabled. Skips touch devices; honours reduced motion.
 *
 * This file is a thin loader — the module is split into includes/ for maintainability
 * (see modules/AGENTS.md, "Splitting a large module"):
 *   - includes/cursor-helpers.php   — setting reader, style registry, typography helper
 *   - includes/cursor-settings.php  — the Theme Settings → Site-wide UX → Cursor sub-tab
 *   - includes/cursor-enqueue.php   — the front-end enqueue + config bridge
 *
 * UPW_CURSOR_DIR is the module root; the enqueue part uses it (not __DIR__) to resolve static
 * asset paths for filemtime cache-busting, since it lives in includes/.
 */

if ( ! defined( 'UPW_CURSOR_DIR' ) ) {
	define( 'UPW_CURSOR_DIR', __DIR__ );
}

require_once __DIR__ . '/includes/cursor-helpers.php';   // must load first (settings + enqueue use it)
require_once __DIR__ . '/includes/cursor-settings.php';
require_once __DIR__ . '/includes/cursor-enqueue.php';
