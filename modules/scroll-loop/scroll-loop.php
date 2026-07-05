<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * PHP Version: 7.4 or higher
 *
 * Animation Engine — Seamless / Infinite Scroll Loop module (entry).
 *
 * Turns a run of consecutive full-height [section]s into a never-ending, snapping
 * scroll loop — the "Infinite Scroll with Parallax" experience (Codrops/Tympanus),
 * powered by Lenis (`infinite: true`) + Lenis Snap. Reuses the existing Scroll
 * Motion `parallax` effect for the media drift; this module supplies only the
 * missing half: the seamless loop + section snapping.
 *
 * Design mirrors scroll-motion.php exactly, so the feature stays self-contained in
 * the (inactive-by-default) Animation Engine and touches NO base-plugin file:
 *
 *   1. sc_get_scroll_loop_fields() — a `multi` block keyed `scroll_loop`, appended
 *      to every element's Animations tab via the `sc_animation_fields` filter
 *      (priority 8, so it sits just before Scroll Motion at 9). Only meaningful on
 *      Sections; on other elements it simply does nothing.
 *   2. A filter on `sc_build_wrapper_attr` (priority 26, after Scroll Motion at 25)
 *      that stamps clean `data-upw-loop*` attributes onto the section wrapper. The
 *      section already routes through sc_build_wrapper_attr(), so no view.php change.
 *   3. A `sc_needs_wrapper` force so a section whose ONLY non-default setting is the
 *      loop flag still gets a wrapper for the attributes to land on.
 *   4. Conditional enqueue: Lenis (+ Lenis Snap when snapping is used) + the
 *      initializer + CSS load only when at least one loop section rendered. Pages
 *      with no loop ship ZERO of these bytes.
 *
 * This file is a thin loader — the module is split into includes/ for maintainability
 * (see modules/AGENTS.md, "Splitting a large module"):
 *   - includes/scroll-loop-helpers.php  — field builder + the per-request flags
 *   - includes/scroll-loop-settings.php — injects the field into the Section's Animations tab
 *   - includes/scroll-loop-render.php   — wrapper attrs + needs-wrapper + footer enqueue
 *
 * UPW_SCROLL_LOOP_DIR is the module root; the includes use it (not __DIR__) to resolve
 * static asset paths, since they live in includes/.
 */

if ( ! defined( 'UPW_SCROLL_LOOP_DIR' ) ) {
	define( 'UPW_SCROLL_LOOP_DIR', __DIR__ );
}

require_once __DIR__ . '/includes/scroll-loop-helpers.php';   // must load first (settings + render use it)
require_once __DIR__ . '/includes/scroll-loop-settings.php';
require_once __DIR__ . '/includes/scroll-loop-render.php';
