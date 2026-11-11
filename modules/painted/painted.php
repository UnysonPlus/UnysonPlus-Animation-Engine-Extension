<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Painted module (entry).
 *
 * Paints a decoration onto any element with real brushes when it scrolls into view: a watercolour
 * wash behind it, a sketched pencil frame, a marker highlight, a brush underline, an ink circle round
 * it, or hatching. Per-element (Animations tab). The paint engine is p5.brush (MIT, (c) Alejandro
 * Campos Uribe), the same file the Kinetic Scene element ships (shortcodes/studio-scene/static/js/
 * vendor/scene-paint.js), loaded lazily, only on pages that use the effect and only once an element
 * nears the viewport. Each element is painted ONCE (two brush renders); the paint-on is a CSS mask, so
 * nothing is repainted per frame.
 *
 * Saved value shape (multi-picker, picker id `mode`):
 *   [ 'mode' => 'wash', 'wash' => [ 'medium', 'color', 'outline', 'line_color', 'size', 'bleed', … ] ]
 *
 *   - includes/painted-helpers.php   — the enable switch + the style list
 *   - includes/painted-settings.php  — the per-element Painted multi-picker
 *   - includes/painted-render.php    — the wrapper emit + the on-demand asset registration
 */

if ( ! defined( 'UPW_PAINTED_DIR' ) ) {
	define( 'UPW_PAINTED_DIR', __DIR__ );
}

require_once __DIR__ . '/includes/painted-helpers.php';
require_once __DIR__ . '/includes/painted-settings.php';
require_once __DIR__ . '/includes/painted-render.php';
