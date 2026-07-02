<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

$cfg = array();

$cfg['page_builder'] = array(
	'title'       => __( 'Model Viewer', 'fw' ),
	'description' => __( 'An interactive 3D model (glTF / GLB) the visitor can orbit, zoom and inspect — with auto-rotate, image-based lighting, ground shadow, a poster placeholder and optional AR. Powered by <model-viewer>.', 'fw' ),
	'tab'         => __( 'Media Elements', 'fw' ),
	'popup_size'  => 'medium',

	'title_template' => '
		<div style="margin-top:.4rem;color:#555;">
			<span>&#9632; 3D</span> <em style="opacity:.65;">model</em>
		</div>
	',
);
