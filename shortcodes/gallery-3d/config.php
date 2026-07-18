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
	'description'    => __( 'An animated 3D image showcase — a rotating ring of cards (Carousel Ring), fully customizable: drive, tilt, radius, card size/ratio, back-fade, perspective, shadow and more.', 'fw' ),
	'tab'            => __( 'Media Elements', 'fw' ),
	'popup_size'     => 'large',
	'title_template' => '
		{{ if ( o["images"] && o["images"].length > 0 ) { }}
			<div style="display:flex;flex-wrap:wrap;gap:3px;align-items:center;">
				{{ for ( var i = 0; i < o["images"].length; i++ ) { }}
					{{ var gimg = o["images"][i]; var gurl = ( gimg && typeof gimg === "object" ) ? gimg.url : ""; }}
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
