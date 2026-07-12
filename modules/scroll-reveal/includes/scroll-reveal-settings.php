<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Scroll Reveal module: options declaration.
 *
 * The per-element "Scroll Reveal" multi-picker appended to every element's Animations tab (via the
 * shortcodes extension's `sc_animation_fields` filter). The clip-wipe directions (left / right /
 * up / down / iris / diagonal) share one option group; the canvas-backed "Pixelate In" style has
 * its own group (block coarseness / steps / speed) because a stepped pixel-resolve has no easing.
 */

add_filter( 'sc_animation_fields', function ( $fields ) {
	if ( ! is_array( $fields ) ) {
		return $fields;
	}

	// Shared reveal options for the clip-wipe directions (built once, mapped onto each direction).
	$opts = array(
		'duration' => array( 'type' => 'slider', 'label' => __( 'Duration (s)', 'fw' ), 'value' => 0.7, 'properties' => array( 'min' => 0.2, 'max' => 2, 'step' => 0.05 ) ),
		'delay'    => array( 'type' => 'number', 'label' => __( 'Delay (s)', 'fw' ), 'desc' => __( 'Wait before the wipe starts after the element enters view.', 'fw' ), 'value' => 0, 'min' => 0, 'step' => 0.1, 'numeric_type' => 'float' ),
		'easing'   => array(
			'type'    => 'select',
			'label'   => __( 'Easing', 'fw' ),
			'value'   => 'cubic-bezier(0.22, 1, 0.36, 1)',
			'choices' => array(
				'ease'                                => __( 'Ease', 'fw' ),
				'ease-out'                            => __( 'Ease Out', 'fw' ),
				'ease-in-out'                         => __( 'Ease In Out', 'fw' ),
				'linear'                              => __( 'Linear', 'fw' ),
				'cubic-bezier(0.22, 1, 0.36, 1)'      => __( 'Smooth out (default)', 'fw' ),
				'cubic-bezier(0.68, -0.55, 0.27, 1.55)' => __( 'Overshoot', 'fw' ),
			),
		),
		'replay'   => array(
			'type'         => 'switch',
			'label'        => __( 'Replay on scroll', 'fw' ),
			'desc'         => __( 'Re-run the wipe every time the element re-enters the viewport.', 'fw' ),
			'value'        => 'no',
			'left-choice'  => array( 'value' => 'no',  'label' => __( 'Off', 'fw' ) ),
			'right-choice' => array( 'value' => 'yes', 'label' => __( 'On', 'fw' ) ),
		),
	);

	$ext  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	$base = $ext ? $ext->get_declared_URI( '/modules/scroll-reveal/static/img' ) : '';
	$tile = function ( $file, $label ) use ( $base ) {
		return array(
			'small' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 66 ),
			'large' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 132 ),
			'label' => $label,
		);
	};

	$dirs = array(
		'left'     => __( 'Wipe Left', 'fw' ),
		'right'    => __( 'Wipe Right', 'fw' ),
		'up'       => __( 'Wipe Up', 'fw' ),
		'down'     => __( 'Wipe Down', 'fw' ),
		'iris'     => __( 'Iris (circle)', 'fw' ),
		'diagonal' => __( 'Diagonal', 'fw' ),
	);
	$choices_tiles = array( 'none' => $tile( 'none', __( 'None', 'fw' ) ) );
	$reveal        = array( 'none' => array() );
	foreach ( $dirs as $k => $lbl ) {
		$choices_tiles[ $k ] = $tile( $k, $lbl );
		$reveal[ $k ]        = array( 'group_scroll_reveal_' . $k => array( 'type' => 'group', 'options' => $opts ) );
	}

	// Pixelate In — a Canvas 2D pixel-resolve (blocks → sharp) on scroll-into-view. Its own option
	// group: a stepped resolve has no easing/duration, so it exposes coarseness / steps / speed.
	$choices_tiles['pixelate'] = $tile( 'pixelate', __( 'Pixelate In', 'fw' ) );
	$reveal['pixelate']        = array(
		'group_scroll_reveal_pixelate' => array(
			'type'    => 'group',
			'options' => array(
				'coarseness' => array( 'type' => 'slider', 'label' => __( 'Block coarseness (px)', 'fw' ), 'desc' => __( 'Size of the initial pixel blocks — larger = chunkier start.', 'fw' ), 'value' => 100, 'properties' => array( 'min' => 20, 'max' => 200, 'step' => 5 ) ),
				'steps'      => array( 'type' => 'slider', 'label' => __( 'Steps', 'fw' ), 'desc' => __( 'How many resolution steps from blocks to sharp.', 'fw' ), 'value' => 5, 'properties' => array( 'min' => 3, 'max' => 8, 'step' => 1 ) ),
				'speed'      => array( 'type' => 'slider', 'label' => __( 'Step speed (ms)', 'fw' ), 'value' => 80, 'properties' => array( 'min' => 40, 'max' => 300, 'step' => 10 ) ),
				'replay'     => array(
					'type'         => 'switch',
					'label'        => __( 'Replay on scroll', 'fw' ),
					'desc'         => __( 'Re-run the pixel-resolve every time the element re-enters the viewport.', 'fw' ),
					'value'        => 'no',
					'left-choice'  => array( 'value' => 'no',  'label' => __( 'Off', 'fw' ) ),
					'right-choice' => array( 'value' => 'yes', 'label' => __( 'On', 'fw' ) ),
				),
			),
		),
	);

	// Alphabetize picker tiles by label (None/Off first, Custom last) for easier scanning.
	uksort( $choices_tiles, function ( $a, $b ) use ( $choices_tiles ) {
		$rank = function ( $k ) { if ( $k === 'none' || $k === 'off' ) { return 0; } return 1; };
		$ra = $rank( $a ); $rb = $rank( $b );
		if ( $ra !== $rb ) { return $ra - $rb; }
		$la = isset( $choices_tiles[ $a ]['label'] ) ? $choices_tiles[ $a ]['label'] : $a;
		$lb = isset( $choices_tiles[ $b ]['label'] ) ? $choices_tiles[ $b ]['label'] : $b;
		return strcasecmp( (string) $la, (string) $lb );
	} );

	$fields['scroll_reveal'] = array(
		'type'         => 'multi-picker',
		'popover'      => true,
		'label'        => __( 'Scroll Reveal', 'fw' ),
		'desc'         => __( 'Un-mask this element as it scrolls into view — an animated clip-path wipe, or a Canvas pixel-resolve for images.', 'fw' ),
		'help'         => __( 'Scroll Reveal (Animation Engine): a directional clip-path wipe (left / right / up / down), an iris or a diagonal, or "Pixelate In" — an image that resolves from pixel blocks to sharp (Canvas 2D). Triggered by a passive scroll check when the element enters view. Honours "reduce motion" (shows instantly) and loads only on pages that use it.', 'fw' ) . ( function_exists( 'upw_perf_note' ) ? ' ' . upw_perf_note() : '' ),
		'show_borders' => false,
		'value'        => array( 'mode' => 'none' ),
		'anim_meta'    => array( 'category' => __( 'Scroll', 'fw' ) ),
		'picker'       => array(
			'mode' => array(
				'type'    => 'image-picker',
				'label'   => false,
				'desc'    => __( 'Hover a tile to preview it larger.', 'fw' ),
				'value'   => 'none',
				'choices' => $choices_tiles,
			),
		),
		'choices' => $reveal,
	);

	return $fields;
} );
