<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

$cfg = array();

$cfg['page_builder'] = array(
	'title'       => __( 'SVG Draw', 'fw' ),
	'description' => __( 'A self-drawing SVG — line art, a signature, an animated divider or icon that traces itself on scroll. Paste SVG code, upload a file, or pick a built-in preset.', 'fw' ),
	'tab'         => __( 'Media Elements', 'fw' ),
	'popup_size'  => 'medium',

	'title_template' => '
		{{ var s = ( o && o["svg"] && o["svg"]["source"] ) ? o["svg"]["source"] : "preset"; }}
		<div style="margin-top:.4rem;color:#555;">
			<span>&#9998; SVG Draw</span> <em style="opacity:.65;">{{- s }}</em>
		</div>
	',
);
