<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

$cfg = array();

$cfg['page_builder'] = array(
	'title'       => __( 'Rive Animation', 'fw' ),
	'description' => __( 'A real, interactive Rive animation — vector graphics that react to hover, click, scroll or view at 60fps in a tiny file. Upload a .riv file, pick a State Machine for interactivity, and choose how it plays. Reduced-motion + no-WebGL safe (falls back to a poster / the first frame).', 'fw' ),
	'tab'         => __( 'Animation Engine', 'fw' ),
	'popup_size'  => 'medium',

	'title_template' => '
		{{ var t = ( o && o["trigger"] ) ? o["trigger"] : "autoplay"; }}
		<div style="margin-top:.4rem;color:#555;">
			<span>&#9673; Rive</span> <em style="opacity:.65;">{{- t }}</em>
		</div>
	',
);
