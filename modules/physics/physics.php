<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Physics Effects module (entry).
 *
 * Adds a "Physics" group to EVERY element's Animations tab (via the shortcodes
 * extension's `sc_animation_fields` filter), emits the chosen effect onto the element
 * wrapper (via `sc_build_wrapper_attr`), and ships a self-contained vanilla-JS runtime
 * (a tiny spring/verlet integrator) enqueued only on pages that use an effect. Global
 * on/off lives in Theme Settings → Animations → Physics.
 *
 * Effects (picker id `effect`), grouped:
 *   Pointer   : spring · attract · repel · orbit_cursor · rubber_band · tilt_inertia
 *   Drag      : draggable · slingshot
 *   Ambient   : float · levitate · sway · pendulum · wobble · breathing · drift · orbit
 *   Entrance  : gravity · rise · sag · ragdoll · pop
 *   Container : bounded
 *   Reaction  : jelly · squash · recoil · shake · spin
 * Saved value shape (multi-picker): [ 'effect' => 'float', 'float' => [ 'amount' => 12, … ] ]
 *
 * This file is a thin loader — the module is split into includes/ for maintainability
 * (see modules/AGENTS.md, "Splitting a large module"):
 *   - includes/physics-helpers.php   — setting reader, used-flag, effect registry, option-builders
 *   - includes/physics-settings.php  — the Animations-tab Physics group + Theme Settings sub-tab
 *   - includes/physics-render.php    — the wrapper emit + on-demand asset registration
 *
 * UPW_PHYSICS_DIR is the module root; the render part uses it (not __DIR__) to resolve static
 * asset paths, since it lives in includes/.
 */

if ( ! defined( 'UPW_PHYSICS_DIR' ) ) {
	define( 'UPW_PHYSICS_DIR', __DIR__ );
}

require_once __DIR__ . '/includes/physics-helpers.php';   // must load first (settings + render use it)
require_once __DIR__ . '/includes/physics-settings.php';
require_once __DIR__ . '/includes/physics-render.php';
