<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Preloader / Page Loader module (entry).
 *
 * Shows a full-screen loading screen until the page is ready, then animates it away — six styles
 * (spinner, bar, dots, counter, curtain, logo). Configured in Theme Settings → Animations →
 * Preloader. The overlay is printed at wp_body_open (so it covers content from the first paint) and
 * removed on window `load` (after a minimum display time). Front end only; assets load only when
 * enabled. Distinct from Page Transitions (which animates route changes, not the first load).
 *
 * This file is a thin loader — the module is split into includes/ for maintainability
 * (see modules/AGENTS.md, "Splitting a large module"):
 *   - includes/preloader-helpers.php   — style registry, animator markup, settings reader
 *   - includes/preloader-settings.php  — the Theme Settings → Animations → Preloader tab
 *   - includes/preloader-render.php    — the front-end enqueue + the wp_body_open overlay markup
 *
 * UPW_PRELOADER_DIR is the module root; the render part uses it (not __DIR__) to resolve static
 * asset paths for filemtime cache-busting, since it lives in includes/.
 */

if ( ! defined( 'UPW_PRELOADER_DIR' ) ) {
	define( 'UPW_PRELOADER_DIR', __DIR__ );
}

require_once __DIR__ . '/includes/preloader-helpers.php';   // must load first (settings + render use it)
require_once __DIR__ . '/includes/preloader-settings.php';
require_once __DIR__ . '/includes/preloader-render.php';
