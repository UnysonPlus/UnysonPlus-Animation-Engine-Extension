<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Text Scroll Choreography — page-builder config.
 *
 * The type sibling of Image Scroll Choreography: pinned foreground TEXT layers that appear, hold and exit at
 * the scroll % you choose, anchored to a corner in a fixed layer the page scrolls under. Each layer can tilt,
 * warp (3D), and play one of the Animation Engine's Text Effects on its glyphs. Reuses the shared pose engine
 * (scroll-choreo-engine.js) — the engine poses the layer; a warp wrapper bends it; a Text Effect animates the
 * glyphs inside; three separate transform layers, no collisions.
 *
 * Auto-discovered by the shortcodes loader (folder text-scroll-choreography → tag `text_scroll_choreography`).
 */

$cfg = array();

$cfg['page_builder'] = array(
	'title'          => __( 'Text Scroll Choreography', 'fw' ),
	'description'    => __( 'Pin foreground TEXT and choreograph each phrase on scroll — it appears, holds on its corner, then exits at the scroll % you set. Each line can tilt, 3D-warp, and play a Text Effect (reveal, typewriter, scramble, gradient, neon…). The flat-text sibling of Image Scroll Choreography.', 'fw' ),
	'tab'            => __( 'Animation Engine', 'fw' ),
	'popup_size'     => 'large',

	'title_template' => '
		{{ var n = ( o && o["layers"] ) ? o["layers"].length : 0; }}
		<div style="display:flex;gap:6px;align-items:center;margin-top:.4rem;color:#5b6b7b;">
			<span style="font-weight:600;">{{= n }}</span> {{= n === 1 ? "line" : "lines" }} choreographed on scroll
		</div>',
);
