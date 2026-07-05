<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Hover Interactions module (entry).
 *
 * - Adds an "Interaction" hover-effect group to EVERY element's Animations tab
 *   (via the shortcodes extension's `sc_animation_fields` filter).
 * - Emits the chosen effect onto the element wrapper (via `sc_build_wrapper_attr`).
 * - Ships the runtime JS/CSS, enqueued only on pages that actually use an effect.
 * - Global on/off lives in Theme Settings → Animations → Interactions.
 *
 * Effects: magnetic · tilt (3D) · spotlight · image_reveal · text_scramble · glow_border ·
 *   underline_grow · ripple · lift · color_shift · scale · push · jelly · skew · shine ·
 *   gradient_border · corner_brackets · fill_sweep · border_draw · glitch · text_swap ·
 *   rotate · pulse · shake · bounce · grayscale · blur · brightness · bg_pan · outline ·
 *   letter_spacing.
 * Multi-instance: several effects combine on one element (see upw_hover_instances).
 * Saved value shape (multi-picker, picker id `effect`):
 *   [ 'effect' => 'tilt', 'tilt' => [ 'max_tilt' => 12, … ] ]
 *
 * This file is a thin loader — the module is split into includes/ for maintainability
 * (see modules/AGENTS.md, "Splitting a large module"):
 *   - includes/hover-helpers.php   — switch reader, effect registry, color helpers, instance collector
 *   - includes/hover-settings.php  — the per-element Interaction field + the Interactions sub-tab
 *   - includes/hover-render.php    — the wrapper emit + on-demand asset registration
 *
 * UPW_HOVER_DIR is the module root; the render part uses it (not __DIR__) to resolve the static
 * asset path for the on-demand loader, since it lives in includes/.
 */

if ( ! defined( 'UPW_HOVER_DIR' ) ) {
	define( 'UPW_HOVER_DIR', __DIR__ );
}

require_once __DIR__ . '/includes/hover-helpers.php';   // must load first (settings + render use it)
require_once __DIR__ . '/includes/hover-settings.php';
require_once __DIR__ . '/includes/hover-render.php';
