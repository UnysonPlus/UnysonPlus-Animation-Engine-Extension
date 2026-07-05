<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — 3D Flip Card module (entry).
 *
 * Flips any element front-to-back in 3D to reveal a back face with your own heading, text, image,
 * button and colours. Per-element (attaches from the Animations tab). At runtime the element's
 * existing content becomes the FRONT face and a BACK face is built from the options. Pure CSS 3D
 * transforms, no library. Assets load only on pages that use it. Global on/off: Theme Settings →
 * Animations → Effects.
 *
 * FLIP STYLES (picker id `mode`): flip · cube · fold · door · diagonal · pop · carousel (+ off).
 * Each style reveals the same shared settings group.
 *
 * Saved value shape (multi-picker):
 *   [ 'mode' => 'off'|'<style>', '<style>' => [ trigger, auto_interval, direction, min_height,
 *       duration, perspective, easing, radius, back_align, back_heading, back_text, back_image,
 *       back_btn_text, back_btn_url, back_bg, back_color ] ]
 *
 * This file is a thin loader — the module is split into includes/ for maintainability
 * (see modules/AGENTS.md, "Splitting a large module"):
 *   - includes/flip-card-helpers.php   — setting reader, style registry, color + options helpers
 *   - includes/flip-card-settings.php  — the per-element Animations-tab field declaration
 *   - includes/flip-card-render.php    — the wrapper attrs + front-end enqueue
 *
 * UPW_FLIP_CARD_DIR is the module root; the render part uses it (not __DIR__) to resolve static
 * asset paths for filemtime cache-busting, since it lives in includes/.
 */

if ( ! defined( 'UPW_FLIP_CARD_DIR' ) ) {
	define( 'UPW_FLIP_CARD_DIR', __DIR__ );
}

require_once __DIR__ . '/includes/flip-card-helpers.php';   // must load first (settings + render use it)
require_once __DIR__ . '/includes/flip-card-settings.php';
require_once __DIR__ . '/includes/flip-card-render.php';
