<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Parallax Scene — page-builder registration.
 *
 * A layered "diorama": a stack of absolutely-anchored image LAYERS that (a) parallax at different depths on
 * scroll (or pointer), (b) reveal on entrance as the scene scrolls into view, and (c) gently sway (foliage).
 * Self-contained — its own lightweight runtime, so it works whether or not the Animation Engine is active.
 */

$cfg = array();

$cfg['page_builder'] = array(
	'title'       => __( 'Parallax Scene', 'fw' ),
	'description' => __( 'A layered foreground/background scene — stacked images that drift at different depths on scroll, reveal on entrance, and gently sway. Great for art-directed heroes and diorama backdrops.', 'fw' ),
	'tab'         => __( 'Animation Engine', 'fw' ),
	'popup_size'  => 'large',

	'title_template' => '
		{{ var n = ( o && o["layers"] ) ? o["layers"].length : 0; }}
		<div style="display:flex;gap:6px;align-items:center;margin-top:.4rem;color:#5b6b7b;">
			<span style="font-weight:600;">{{= n }}</span> {{= n === 1 ? "layer" : "layers" }}
		</div>',
);
