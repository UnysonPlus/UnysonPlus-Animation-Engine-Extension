<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

$cfg = array();

$cfg['page_builder'] = array(
	'title'       => __( 'Interactive Reveal', 'fw' ),
	'description' => __( 'Stack image layers and animate them apart — an exploded / peel / assemble product reveal — driven by scroll, hover or click, with labelled hotspots pinned to the parts.', 'fw' ),
	'tab'         => __( 'Media Elements', 'fw' ),
	'popup_size'  => 'large',

	'title_template' => '
		{{
			var layers = ( o && o["layers"] && o["layers"].length ) ? o["layers"].length : 0;
			var trig   = ( o && o["trigger"] ) ? o["trigger"] : "scroll";
		}}
		<div style="margin-top:.4rem;color:#555;">
			<span>&#9673; Interactive Reveal</span>
			<em style="opacity:.65;">{{- layers ? ( layers + " layer(s), " + trig ) : "add layers" }}</em>
		</div>
	',
);
