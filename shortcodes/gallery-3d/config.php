<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery — page-builder config. An Animation Engine element that arranges a set of images into
 * an animated 3D scene. The first design is "Carousel Ring" (a rotating ring / cylinder of cards).
 * Auto-discovered by the shortcodes loader (folder gallery-3d → tag `gallery_3d`).
 */

$cfg = array();

$cfg['page_builder'] = array(
	'title'          => __( 'Motion Gallery', 'fw' ),
	'description'    => __( 'An animated image gallery — 3D carousels, rings and spheres, cover-flow, card scatter, marquee and more. Cards come from Media Library images or a post type\'s featured images, with drag-to-spin, scroll pinning, lightbox or per-card links.', 'fw' ),
	'tab'            => __( 'Animation Engine', 'fw' ),
	'popup_size'     => 'large',
	'title_template' => '
		{{ var gsrc  = ( o["source"] && typeof o["source"] === "object" ) ? o["source"] : null; }}
		{{ var gimgs = ( gsrc && gsrc["media"] && gsrc["media"]["images"] && gsrc["media"]["images"].length ) ? gsrc["media"]["images"] : ( ( o["images"] && o["images"].length ) ? o["images"] : null ); }}
		{{ if ( gsrc && gsrc["kind"] === "posts" ) { }}
			<em>Motion Gallery — cards from post type: <strong>{{- ( gsrc["posts"] && gsrc["posts"]["post_type"] ) || "post" }}</strong></em>
		{{ } else if ( gimgs ) { }}
			<div style="display:flex;flex-wrap:wrap;gap:3px;align-items:center;">
				{{ for ( var i = 0; i < gimgs.length; i++ ) { }}
					{{ var gimg = gimgs[i]; var gurl = ( gimg && typeof gimg === "object" ) ? gimg.url : ""; }}
					{{ if ( gurl ) { }}
						<img src="{{- gurl }}" style="width:100px;height:100px;object-fit:cover;border-radius:4px;" />
					{{ } }}
				{{ } }}
			</div>
		{{ } else { }}
			<em>Motion Gallery — add images</em>
		{{ } }}
	',
);
