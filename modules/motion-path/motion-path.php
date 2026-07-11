<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Motion Path module (entry).
 *
 * Sends any element travelling along a path — a preset shape (wave / arc / loop / S-curve /
 * zigzag / spiral / circle) or a custom SVG `d` — instead of a straight tween. Three drive modes:
 * scroll-scrubbed (position along the path = scroll progress), loop (travels the path forever),
 * or on-view (plays once when it enters). Optionally rotates the element to the path tangent
 * ("Align to path") so it noses along the curve. Per-element (attaches from the Animations tab).
 * Pure SVG geometry (getPointAtLength) + one runtime; assets load only on pages that use it.
 * Global on/off: Theme Settings → Site-wide UX → Animation Engine … Effects.
 *
 * Saved value shape (multi-picker, picker id `mode` = the path shape):
 *   [ 'mode' => 'wave', 'wave' => [ 'drive', 'duration', 'direction', 'easing', 'start_offset',
 *                                   'path_size', 'align' ] ]
 *   custom shape adds a 'custom_d' field (the raw SVG path data) to that group.
 *
 * Thin loader — the module is split into includes/ (see modules/AGENTS.md):
 *   - includes/motion-path-helpers.php   — enable switch + the preset path library
 *   - includes/motion-path-settings.php  — the per-element Motion Path multi-picker
 *   - includes/motion-path-render.php    — the wrapper emit + on-demand asset registration
 *
 * UPW_MOTION_PATH_DIR is the module root; the render part uses it (not __DIR__) to resolve
 * static asset paths for filemtime cache-busting, since it lives in includes/.
 */

if ( ! defined( 'UPW_MOTION_PATH_DIR' ) ) {
	define( 'UPW_MOTION_PATH_DIR', __DIR__ );
}

require_once __DIR__ . '/includes/motion-path-helpers.php';   // must load first (settings + render use it)
require_once __DIR__ . '/includes/motion-path-settings.php';
require_once __DIR__ . '/includes/motion-path-render.php';
