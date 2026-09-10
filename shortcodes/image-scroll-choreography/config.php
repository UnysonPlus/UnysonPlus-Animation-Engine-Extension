<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Image Scroll Choreography — page-builder registration.
 *
 * A foreground "scene" that pins a set of image LAYERS in a fixed, transparent, z-indexed layer and drives
 * each layer's position / scale / rotation / opacity FROM SCROLL, per keyframe — so images can appear at the
 * bottom-left / -right, HOLD full-screen while the visitor scrolls through a chapter, then exit. All layers
 * share ONE scroll timeline (travel + guide), the way a "kage" foreground group is choreographed together.
 *
 * The flat-image sibling of the Model Viewer's Scroll Choreography — it lives here in the Animation Engine
 * next to Model Viewer (the two scroll-choreography elements grouped together) and reuses the same
 * fixed-layer + keyframe + guide pattern, minus the 3D camera. Its own runtime (no hard AE dependency).
 */

$cfg = array();

$cfg['page_builder'] = array(
	'title'       => __( 'Image Scroll Choreography', 'fw' ),
	'description' => __( 'Pin foreground images and choreograph them on scroll — each image appears, holds, and exits at the scroll % you choose, anchored to a corner, while the page scrolls underneath. The flat-image sibling of the Model Viewer’s Scroll Choreography; great for "kage"-style foreground layers.', 'fw' ),
	'tab'         => __( 'Animation Engine', 'fw' ),
	'popup_size'  => 'large',

	'title_template' => '
		{{ var n = ( o && o["layers"] ) ? o["layers"].length : 0; }}
		<div style="display:flex;gap:6px;align-items:center;margin-top:.4rem;color:#5b6b7b;">
			<span style="font-weight:600;">{{= n }}</span> {{= n === 1 ? "image" : "images" }} choreographed on scroll
		</div>',
);
