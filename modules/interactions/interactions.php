<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Interactions module (roadmap I1 · Targets).
 *
 * One trigger on this element (hover, click, page load, scrolled into view, scrolled past) plays a short
 * list of steps, and each step can animate a DIFFERENT element: this one, elements inside it that match a
 * selector, or elements anywhere on the page. "Hover the card → lift the card, zoom its image, slide its
 * arrow"; "click the button → open that panel"; "scroll past the hero → shrink the header".
 *
 * Runtime: the browser's Web Animations API on the individual transform properties (translate / scale /
 * rotate), so it composes with any transform the target already has (Entrance, Hover, Scroll Motion), plus
 * opacity and blur. No library. Loads only on pages that use it.
 *
 * Saved value (picker id `mode`):
 *   [ 'mode' => 'off'|'hover'|'click'|'load'|'view'|'past',
 *     '<mode>' => [ 'again'|'leave'|'offset' => …, 'steps' => [ 's1' => step, 's2' => step, 's3' => step ] ] ]
 *   step = [ target self|inside|page|none, selector, direction to|from, x, y, scale, rotate, opacity, blur,
 *            duration, delay, ease, stagger, start after|with ]
 */

if ( ! defined( 'UPW_INTERACTIONS_DIR' ) ) {
	define( 'UPW_INTERACTIONS_DIR', __DIR__ );
}

require_once __DIR__ . '/includes/interactions-helpers.php';
require_once __DIR__ . '/includes/interactions-settings.php';
require_once __DIR__ . '/includes/states-settings.php';       // the States card (I3)
require_once __DIR__ . '/includes/interactions-render.php';
require_once __DIR__ . '/includes/interactions-sitewide.php';   // "Use on every page" (I5 reuse across pages)
