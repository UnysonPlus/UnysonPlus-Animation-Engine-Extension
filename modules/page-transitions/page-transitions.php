<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Page Transitions module (entry).
 *
 * A full-screen overlay injected at wp_body_open covers the viewport on first paint and
 * reveals it (entrance, pure CSS so it runs even without JS). On an internal link click the
 * runtime plays the reverse (cover) then navigates, so pages feel connected. An optional
 * first-visit loader shows a spinner/bar/dots until the page finishes loading. Config lives
 * in Theme Settings → Animations → Page Transitions; nothing loads in admin or when disabled.
 *
 * This file is a thin loader — the module is split into includes/ for maintainability
 * (see modules/AGENTS.md, "Splitting a large module"):
 *   - includes/page-transitions-helpers.php   — setting reader, type registry, resolver
 *   - includes/page-transitions-settings.php  — the Theme Settings → Animations → Page Transitions sub-tab
 *   - includes/page-transitions-enqueue.php   — the wp_body_open overlay + front-end enqueue
 *
 * UPW_PAGE_TRANSITIONS_DIR is the module root; the enqueue part uses it (not __DIR__) to resolve
 * static asset paths for filemtime cache-busting, since it lives in includes/.
 */

if ( ! defined( 'UPW_PAGE_TRANSITIONS_DIR' ) ) {
	define( 'UPW_PAGE_TRANSITIONS_DIR', __DIR__ );
}

require_once __DIR__ . '/includes/page-transitions-helpers.php';   // must load first (settings + enqueue use it)
require_once __DIR__ . '/includes/page-transitions-settings.php';
require_once __DIR__ . '/includes/page-transitions-enqueue.php';
