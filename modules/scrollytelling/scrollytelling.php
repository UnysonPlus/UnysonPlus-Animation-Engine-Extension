<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Scrollytelling ("Pinned Steps") module (entry).
 *
 * The Apple / Stripe / Linear scroll pattern: one column of a Section pins as a MEDIA panel while
 * the other column's STEPS scroll past, and the pinned media transitions to match the active step.
 * Media layers map to steps by index (step 1 -> media 1, ...). Section-level, so it is injected only
 * into the Section's Animations tab (like Sticky Card Stack / Scroll Loop), landing in the
 * animation-stack organizer as its own card + inserter tile.
 *
 * Styles: Crossfade, Slide, Zoom, Clip Wipe, Blur Swap, Ken Burns (CSS) + Parallax Depth, Pixelate
 * Resolve (JS). Pure CSS `position:sticky` + IntersectionObserver; the two JS styles add a small
 * on-demand partial. Assets load only on pages that use it. Honours reduced-motion (media shows
 * statically above each step) and disable-on-mobile.
 *
 * Saved value shape (multi-picker, picker id `mode`):
 *   [ 'mode' => 'off'|'<style>', '<style>' => [ pin_side, media_height, pin_offset, activate_at,
 *       transition, intensity, progress ] ]
 *
 * This file is a thin loader — the module is split into includes/ for maintainability
 * (see modules/AGENTS.md, "Splitting a large module"):
 *   - includes/scrollytelling-helpers.php   — enable switch, used-flag, style registry
 *   - includes/scrollytelling-settings.php  — the Section-tab multi-picker (fields builder)
 *   - includes/scrollytelling-render.php    — section injection, wrapper stamp, on-demand assets
 *
 * UPW_SCROLLYTELLING_DIR is the module root; the render part uses it (not __DIR__) to resolve
 * static asset paths for filemtime cache-busting, since it lives in includes/.
 */

if ( ! defined( 'UPW_SCROLLYTELLING_DIR' ) ) {
	define( 'UPW_SCROLLYTELLING_DIR', __DIR__ );
}

require_once __DIR__ . '/includes/scrollytelling-helpers.php';   // must load first
require_once __DIR__ . '/includes/scrollytelling-settings.php';
require_once __DIR__ . '/includes/scrollytelling-render.php';
