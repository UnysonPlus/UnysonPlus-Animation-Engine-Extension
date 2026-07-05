<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Marquee module (entry).
 *
 * Turns any element's content into a seamless, never-ending ticker (running text, a logo band,
 * scrolling images…). Attach it from the element's Animations tab; at runtime the content is
 * cloned into a doubled track and translated by exactly one set, so the loop has no visible jump.
 * Horizontal (left / right) or vertical (up / down). One shared CSS animation per element — no
 * library — enqueued only on pages that use it. Global on/off: Theme Settings → Animations → Effects.
 *
 * Saved value shape (multi-picker, picker id `mode`):
 *   [ 'mode' => 'left', 'left' => [ 'speed' => 'normal', 'gap' => 40, … ] ]
 *
 * This file is a thin loader — the module is split into includes/ for maintainability
 * (see modules/AGENTS.md, "Splitting a large module"):
 *   - includes/marquee-helpers.php   — enabled reader, per-page used flag, slider field factory
 *   - includes/marquee-settings.php  — the per-element field + the global on/off sub-tab
 *   - includes/marquee-render.php    — the wrapper attrs + the front-end enqueue
 *
 * UPW_MARQUEE_DIR is the module root; the render part uses it (not __DIR__) to resolve static
 * asset paths for filemtime cache-busting, since it lives in includes/.
 */

if ( ! defined( 'UPW_MARQUEE_DIR' ) ) {
	define( 'UPW_MARQUEE_DIR', __DIR__ );
}

require_once __DIR__ . '/includes/marquee-helpers.php';   // must load first (settings + render use it)
require_once __DIR__ . '/includes/marquee-settings.php';
require_once __DIR__ . '/includes/marquee-render.php';
