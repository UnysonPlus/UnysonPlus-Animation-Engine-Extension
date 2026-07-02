<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

$cfg = array();

$cfg['page_builder'] = array(
	'title'       => __( 'Image Sequence', 'fw' ),
	'description' => __( 'Scrub through a sequence of frames as the visitor scrolls — the "product-reveal" effect. Upload frames or point at a numbered URL pattern; pin it full-screen or play it as it passes.', 'fw' ),
	'tab'         => __( 'Media Elements', 'fw' ),
	'popup_size'  => 'medium',

	'title_template' => '
		{{ var m = ( o && o["mode"] ) ? o["mode"] : "pin"; }}
		<div style="margin-top:.4rem;color:#555;">
			<span>&#9636; Image Sequence</span> <em style="opacity:.65;">{{- m === "inview" ? "scrub in view" : "pin & scrub" }}</em>
		</div>
	',
);
