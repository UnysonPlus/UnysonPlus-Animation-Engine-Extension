<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

$cfg = array();

$cfg['page_builder'] = array(
	'title'       => __( 'SVG Morph', 'fw' ),
	'description' => __( 'An SVG shape that morphs into another — a liquid blob loop, circle to square, heart to star, or your own paths. Fills or outlines; loops, on hover, on scroll-in, or on click.', 'fw' ),
	'tab'         => __( 'Media Elements', 'fw' ),
	'popup_size'  => 'medium',

	'title_template' => '
		{{ var n = ( o && o["shapes_list"] && o["shapes_list"].length ) ? o["shapes_list"].length : 0; }}
		<div style="margin-top:.4rem;color:#555;">
			<span>&#9711; SVG Morph</span> <em style="opacity:.65;">{{- n }} shapes</em>
		</div>
	',
);
