<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * 3D Gallery — page-builder config. An Animation Engine element that arranges a set of images into
 * an animated 3D scene. The first design is "Carousel Ring" (a rotating ring / cylinder of cards).
 * Auto-discovered by the shortcodes loader (folder gallery-3d → tag `gallery_3d`).
 */

$cfg = array();

$cfg['page_builder'] = array(
	'title'          => __( '3D Gallery', 'fw' ),
	'description'    => __( 'An animated 3D image showcase — Carousel Ring, Panorama Wall, Card Sphere or Orbit Globe. Cards come from Media Library images or a post type\'s featured images, with drag-to-spin, scroll pinning, lightbox or per-card links.', 'fw' ),
	'tab'            => __( 'Media Elements', 'fw' ),
	'popup_size'     => 'large',
	'title_template' => '
		{{ var gsrc  = ( o["source"] && typeof o["source"] === "object" ) ? o["source"] : null; }}
		{{ var gimgs = ( gsrc && gsrc["media"] && gsrc["media"]["images"] && gsrc["media"]["images"].length ) ? gsrc["media"]["images"] : ( ( o["images"] && o["images"].length ) ? o["images"] : null ); }}
		{{ if ( gsrc && gsrc["kind"] === "posts" ) { }}
			<em>3D Gallery — cards from post type: <strong>{{- ( gsrc["posts"] && gsrc["posts"]["post_type"] ) || "post" }}</strong></em>
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
			<em>3D Gallery — add images</em>
		{{ } }}
	',
);
