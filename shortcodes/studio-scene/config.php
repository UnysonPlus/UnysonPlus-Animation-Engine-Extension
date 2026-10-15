<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

$cfg = array();

$cfg['page_builder'] = array(
	'title'       => __( 'Studio Scene', 'fw' ),
	'description' => __( 'A timeline motion-graphics scene made in the Unyson+ Animation Studio: kinetic type, shapes and images that play as live HTML, with its own layout for desktop, tablet and mobile. Paste the scene (or open it in the Studio and save it straight back), then choose how tall it sits and when it plays.', 'fw' ),
	'tab'         => __( 'Animation Engine', 'fw' ),
	'popup_size'  => 'medium',

	'title_template' => '
		{{ var raw = ( o && o["scene"] ) ? String( o["scene"] ) : ""; var t = ""; var m = raw.match( /"title"\s*:\s*"([^"]{0,80})"/ ); if ( m ) { t = m[1]; } }}
		<div style="margin-top:.4rem;color:#555;">
			<span>&#9654; Studio Scene</span> <em style="opacity:.65;">{{- raw ? ( t || "untitled scene" ) : "no scene yet" }}</em>
		</div>
	',
);
