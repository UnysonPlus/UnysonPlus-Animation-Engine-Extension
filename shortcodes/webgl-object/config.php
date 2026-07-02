<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

$cfg = array();

$cfg['page_builder'] = array(
	'title'       => __( 'WebGL Object', 'fw' ),
	'description' => __( 'A real-time WebGL element — a refractive glass blob, liquid metal, distorted sphere or particle field — that reacts to the pointer and scroll. Built with Three.js.', 'fw' ),
	'tab'         => __( 'Media Elements', 'fw' ),
	'popup_size'  => 'medium',

	'title_template' => '
		{{ var p = ( o && o["style_preset"] && o["style_preset"]["preset"] ) ? o["style_preset"]["preset"] : "glass"; }}
		<div style="margin-top:.4rem;color:#555;">
			<span>&#9673; WebGL</span> <em style="opacity:.65;">{{- p }}</em>
		</div>
	',
);
