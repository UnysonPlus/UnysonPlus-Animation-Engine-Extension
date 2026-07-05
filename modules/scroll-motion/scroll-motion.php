<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Scroll Motion module (entry).
 *
 * A GSAP + ScrollTrigger "Scroll Motion" engine appended to every element's Animations tab —
 * a SECOND, independent engine alongside the Animate.css "Entrance Animation" block. GSAP does
 * scroll-driven motion (reveal / stagger / split text / parallax / pin / scrub / zoom / rotate /
 * blur / clip / skew) — the "award-site" vocabulary CSS keyframes cannot express. Bytes ship only
 * on pages that actually use a GSAP effect (mirrors sc_animation_flag()).
 *
 * This file is a thin loader — the module is split into includes/ for maintainability
 * (see modules/AGENTS.md, "Splitting a large module"):
 *   - includes/scroll-motion-helpers.php   — per-request flag + used-effects bookkeeping
 *   - includes/scroll-motion-settings.php  — sc_get_gsap_fields() + the Animations-tab injection
 *   - includes/scroll-motion-render.php    — the wrapper-attr filter + wp_footer enqueue
 */

if ( ! defined( 'UPW_SCROLL_MOTION_DIR' ) ) {
	define( 'UPW_SCROLL_MOTION_DIR', __DIR__ );
}

require_once __DIR__ . '/includes/scroll-motion-helpers.php';   // must load first (settings + render use it)
require_once __DIR__ . '/includes/scroll-motion-settings.php';
require_once __DIR__ . '/includes/scroll-motion-render.php';
