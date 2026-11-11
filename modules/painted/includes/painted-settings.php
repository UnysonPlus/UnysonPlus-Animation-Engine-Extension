<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Painted module: options.
 *
 * The per-element "Painted" multi-picker on every element's Animations tab (`sc_animation_fields`).
 * Each style has its own option group, built from one function so the styles share their fields and
 * differ only in their defaults (and in the few fields that only apply to one: bleed and paper texture
 * for the watercolour wash, gap and angle for hatching, an outline for the fill styles).
 */

add_filter( 'sc_animation_fields', function ( $fields ) {
	if ( ! is_array( $fields ) ) {
		return $fields;
	}

	$ext  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	$base = $ext ? $ext->get_declared_URI( '/modules/painted/static/img' ) : '';
	$tile = function ( $file, $label ) use ( $base ) {
		return array(
			'small' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 53 ),
			'large' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 132 ),
			'label' => $label,
		);
	};
	$color = function ( $label, $hex, $desc = '' ) {
		if ( function_exists( 'upw_color_field' ) ) {
			return upw_color_field( $label, 'bg', $hex, $desc );
		}
		$f = array( 'type' => 'color-picker', 'label' => $label, 'value' => $hex );
		if ( $desc !== '' ) {
			$f['desc'] = $desc;
		}
		return $f;
	};
	$switch = function ( $label, $value, $desc = '' ) {
		return array(
			'type'         => 'switch',
			'label'        => $label,
			'desc'         => $desc,
			'value'        => $value,
			'left-choice'  => array( 'value' => 'no', 'label' => __( 'Off', 'fw' ) ),
			'right-choice' => array( 'value' => 'yes', 'label' => __( 'On', 'fw' ) ),
		);
	};
	$media = array(
		'pencil'          => __( 'Pencil', 'fw' ),
		'ink'             => __( 'Ink pen', 'fw' ),
		'fineliner'       => __( 'Fineliner', 'fw' ),
		'marker'          => __( 'Marker', 'fw' ),
		'charcoal'        => __( 'Charcoal', 'fw' ),
		'crayon'          => __( 'Crayon', 'fw' ),
		'pastel'          => __( 'Pastel', 'fw' ),
		'coloured pencil' => __( 'Coloured pencil', 'fw' ),
	);

	$tiles  = array();
	$groups = array( 'none' => array() );
	foreach ( upw_painted_styles() as $key => $s ) {
		$fill = $s['role'] === 'fill';
		$o    = array(
			'medium' => array(
				'type'    => 'select',
				'label'   => $fill ? __( 'Brush (outline and hatching)', 'fw' ) : __( 'Brush', 'fw' ),
				'value'   => $s['medium'],
				'choices' => $media,
			),
			'color'  => $color( $fill ? __( 'Paint colour', 'fw' ) : __( 'Line colour', 'fw' ), $s['color'] ),
		);
		if ( $fill ) {
			$o['outline']    = $switch( __( 'Outline', 'fw' ), $s['outline'], __( 'Also draw a brush outline round the paint.', 'fw' ) );
			$o['line_color'] = $color( __( 'Outline colour', 'fw' ), '#1c1e22' );
		}
		$o['size'] = array( 'type' => 'slider', 'label' => __( 'Brush size', 'fw' ), 'value' => $s['size'], 'properties' => array( 'min' => 0.2, 'max' => 6, 'step' => 0.1 ) );
		if ( 'wash' === $key ) {
			$o['bleed']   = array( 'type' => 'slider', 'label' => __( 'Watercolour bleed', 'fw' ), 'desc' => __( 'How far the wash spreads past its edge.', 'fw' ), 'value' => $s['bleed'], 'properties' => array( 'min' => 0, 'max' => 1, 'step' => 0.05 ) );
			$o['texture'] = array( 'type' => 'slider', 'label' => __( 'Paper texture', 'fw' ), 'desc' => __( 'How much the pigment settles into the grain of the paper.', 'fw' ), 'value' => 0.5, 'properties' => array( 'min' => 0, 'max' => 1, 'step' => 0.05 ) );
		}
		if ( 'hatch' === $key ) {
			$o['gap']   = array( 'type' => 'slider', 'label' => __( 'Hatching gap (px)', 'fw' ), 'value' => 7, 'properties' => array( 'min' => 2, 'max' => 30, 'step' => 0.5 ) );
			$o['angle'] = array( 'type' => 'slider', 'label' => __( 'Hatching angle', 'fw' ), 'value' => -35, 'properties' => array( 'min' => -90, 'max' => 90, 'step' => 1 ) );
		}
		$o['strength'] = array( 'type' => 'slider', 'label' => __( 'Paint strength', 'fw' ), 'desc' => __( 'Lower is paler and more see-through.', 'fw' ), 'value' => isset( $s['strength'] ) ? $s['strength'] : 1, 'properties' => array( 'min' => 0.1, 'max' => 1, 'step' => 0.05 ) );
		$o['spread']   = array( 'type' => 'slider', 'label' => __( 'Spread (px)', 'fw' ), 'desc' => __( 'How far the painting reaches past the element\'s edges.', 'fw' ), 'value' => $s['spread'], 'properties' => array( 'min' => 0, 'max' => 80, 'step' => 1 ) );
		$o['fit']      = array(
			'type'    => 'select',
			'label'   => __( 'Fit to', 'fw' ),
			'desc'    => __( 'Content paints round the words, buttons and pictures themselves; Whole element paints round the full box of the element. Automatic picks Content for the highlight, underline and circle.', 'fw' ),
			'value'   => 'auto',
			'choices' => array(
				'auto'    => __( 'Automatic', 'fw' ),
				'content' => __( 'Content', 'fw' ),
				'element' => __( 'Whole element', 'fw' ),
			),
		);
		$o['duration'] = array( 'type' => 'slider', 'label' => __( 'Paint-on time (s)', 'fw' ), 'desc' => __( 'How long it takes to paint once the element scrolls into view. 0 shows it already painted.', 'fw' ), 'value' => 1.2, 'properties' => array( 'min' => 0, 'max' => 4, 'step' => 0.1 ) );
		$o['delay']    = array( 'type' => 'number', 'label' => __( 'Delay (s)', 'fw' ), 'value' => 0, 'min' => 0, 'step' => 0.1, 'numeric_type' => 'float' );
		$o['layer']    = array(
			'type'    => 'select',
			'label'   => __( 'Layer', 'fw' ),
			'desc'    => __( 'Behind keeps the element\'s content on top. In front lays the paint over it, blended in like ink on paper.', 'fw' ),
			'value'   => 'behind',
			'choices' => array(
				'behind' => __( 'Behind the content', 'fw' ),
				'front'  => __( 'In front (blended)', 'fw' ),
			),
		);
		$o['seed'] = array( 'type' => 'number', 'label' => __( 'Variation', 'fw' ), 'desc' => __( 'A number that gives this element a different set of brush marks. 0 picks one for you.', 'fw' ), 'value' => 0, 'min' => 0, 'max' => 9999, 'step' => 1 );

		$tiles[ $key ]  = $tile( $key, $s['label'] );
		$groups[ $key ] = array( 'group_painted_' . $key => array( 'type' => 'group', 'options' => $o ) );
	}

	$fields['painted'] = array(
		'type'         => 'multi-picker',
		'popover'      => true,
		'label'        => __( 'Painted', 'fw' ),
		'desc'         => __( 'Paint a decoration onto this element with real brushes as it scrolls into view: a watercolour wash, a sketched frame, a marker highlight, a brush underline, an ink circle or hatching.', 'fw' ),
		'help'         => __( 'Painted (Animation Engine): real brush marks (pencil, ink, marker, charcoal, crayon, pastel) and watercolour, painted onto the element once when it scrolls into view and drawn on over the paint-on time. Each element keeps its own marks on every visit. The paint engine (p5.brush) loads only on pages that use it, and only when a painted element comes near the screen. With "reduce motion" on, the painting is shown already finished.', 'fw' ) . ( function_exists( 'upw_perf_note' ) ? ' ' . upw_perf_note() : '' ),
		'show_borders' => false,
		'value'        => array( 'mode' => 'none' ),
		'placeholder'  => __( 'None', 'fw' ),
		'anim_meta'    => array( 'category' => __( 'Ambient', 'fw' ) ),
		'picker'       => array(
			'mode' => array(
				'type'    => 'image-picker',
				'label'   => false,
				'desc'    => __( 'Hover a tile to preview it larger.', 'fw' ),
				'value'   => 'none',
				'choices' => $tiles,
			),
		),
		'choices'      => $groups,
	);

	return $fields;
} );
