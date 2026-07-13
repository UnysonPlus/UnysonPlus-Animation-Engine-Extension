<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — 3D Flip Card module: field declaration.
 *
 * Appends the per-element "3D Flip Card" popover multi-picker to the Animations tab
 * (sc_animation_fields): one tile per flip style (+ off), each revealing the shared back-face
 * options group. Depends on the helpers.
 */

/* 1) The per-element "Flip Card" control, appended to the Animations tab. */
add_filter( 'sc_animation_fields', function ( $fields ) {
	if ( ! is_array( $fields ) ) {
		return $fields;
	}

	$ext  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	$base = $ext ? $ext->get_declared_URI( '/modules/flip-card/static/img' ) : '';
	$tile = function ( $file, $label ) use ( $base ) {
		return array(
			'small' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 53 ),
			'large' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 132 ),
			'label' => $label,
		);
	};

	$opts    = upw_flip_card_options();
	$choices = array(  );
	$reveals = array();
	foreach ( upw_flip_card_styles() as $key => $label ) {
		$choices[ $key ] = $tile( $key, $label );
		$reveals[ $key ] = array( 'group_flip_' . $key => array( 'type' => 'group', 'options' => $opts ) );
	}

	// Alphabetize picker tiles by label (None/Off first, Custom last) for easier scanning.
	uksort( $choices, function ( $a, $b ) use ( $choices ) {
		$rank = function ( $k ) { if ( $k === 'none' || $k === 'off' ) { return 0; } return 1; };
		$ra = $rank( $a ); $rb = $rank( $b );
		if ( $ra !== $rb ) { return $ra - $rb; }
		$la = isset( $choices[ $a ]['label'] ) ? $choices[ $a ]['label'] : $a;
		$lb = isset( $choices[ $b ]['label'] ) ? $choices[ $b ]['label'] : $b;
		return strcasecmp( (string) $la, (string) $lb );
	} );

	$fields['flip_card'] = array(
		'type'         => 'multi-picker',
		'popover'      => true,
		'label'        => __( '3D Flip Card', 'fw' ),
		'desc'         => __( 'Flip this element in 3D to reveal a back face — pick from seven flip styles (Flip, Cube, Fold, Door, Diagonal, Pop, Carousel).', 'fw' ),
		'help'         => __( '3D Flip Card (Animation Engine): the element\'s content becomes the front face and a back face is built from the options below (heading, text, image, button, colours). Flips on hover, click, scroll-into-view or an auto loop (click is keyboard-accessible). Pure CSS 3D transforms, no library. Loads only on pages that use it.', 'fw' ) . ( function_exists( 'upw_perf_note' ) ? ' ' . upw_perf_note() : '' ),
		'show_borders' => false,
		'value'        => array( 'mode' => 'off' ),
		'placeholder'  => __( 'Off', 'fw' ),
		'anim_meta'    => array( 'category' => __( 'Interaction', 'fw' ) ),
		'picker'       => array(
			'mode' => array(
				'type'    => 'image-picker',
				'label'   => false,
				'desc'    => __( 'Hover a tile to preview it larger.', 'fw' ),
				'value'   => 'off',
				'search'  => __( 'Search flip styles…', 'fw' ),
				'choices' => $choices,
			),
		),
		'choices' => $reveals,
	);

	return $fields;
} );
