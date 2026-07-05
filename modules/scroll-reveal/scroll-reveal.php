<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Scroll Reveal module (entry).
 *
 * Un-masks any element as it scrolls into view: an animated clip-path wipe (left / right / up /
 * down), an iris (circle), a diagonal — or "Pixelate In", a Canvas 2D pixel-resolve for images
 * (blocks → sharp, the Codrops "image pixel loading" look). Per-element (attaches from the
 * Animations tab). Clip wipes are pure CSS + one IntersectionObserver; Pixelate is a small
 * Canvas 2D partial. Assets load only on pages that use it. Global on/off: Theme Settings →
 * Site-wide UX → Animation Engine … Effects.
 *
 * Saved value shape (multi-picker, picker id `mode`):
 *   clip:     [ 'mode' => 'left', 'left' => [ 'duration', 'delay', 'easing', 'replay' ] ]
 *   pixelate: [ 'mode' => 'pixelate', 'pixelate' => [ 'coarseness', 'steps', 'speed', 'replay' ] ]
 *
 * This file is a thin loader — the module is split into includes/ for maintainability
 * (see modules/AGENTS.md, "Splitting a large module"):
 *   - includes/scroll-reveal-helpers.php   — the enable switch + the per-request used-flag
 *   - includes/scroll-reveal-settings.php  — the per-element Scroll Reveal multi-picker
 *   - includes/scroll-reveal-render.php    — the wrapper emit + the on-demand asset registration
 *
 * UPW_SCROLL_REVEAL_DIR is the module root; the render part uses it (not __DIR__) to resolve
 * static asset paths for filemtime cache-busting, since it lives in includes/.
 */

if ( ! defined( 'UPW_SCROLL_REVEAL_DIR' ) ) {
	define( 'UPW_SCROLL_REVEAL_DIR', __DIR__ );
}

require_once __DIR__ . '/includes/scroll-reveal-helpers.php';   // must load first (settings + render use it)
require_once __DIR__ . '/includes/scroll-reveal-settings.php';
require_once __DIR__ . '/includes/scroll-reveal-render.php';
