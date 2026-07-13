<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Preloader module: Theme Settings tab.
 *
 * Registers the Theme Settings → Animations → Preloader sub-tab (enable switch, style picker,
 * colours, logo, timing). Depends on the helpers (upw_preloader_styles). Loaded by preloader.php.
 */

/* 1) Theme Settings → Animations → Preloader tab. */
add_filter( 'upw_anim_engine_module_tabs', function ( $tabs ) {
	$ext  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	$base = $ext ? $ext->get_declared_URI( '/modules/preloader/static/img' ) : '';
	$tile = function ( $file, $label ) use ( $base ) {
		return array(
			'small' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 53 ),
			'large' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 132 ),
			'label' => $label,
		);
	};
	$style_choices = array();
	$style_reveals = array();
	foreach ( upw_preloader_styles() as $k => $lbl ) {
		$style_choices[ $k ] = $tile( $k, $lbl );
		// Reserved for per-style options a future style may declare, e.g.:
		//   $style_reveals['bars'] = array( 'group_bars' => array( 'type' => 'group',
		//       'options' => array( 'count' => array( 'type' => 'slider', … ) ) ) );
		// Empty for now — the colour / logo / timing options below are shared by all styles.
		$style_reveals[ $k ] = array();
	}

	// Alphabetize picker tiles by label (None/Off first, Custom last) for easier scanning.
	uksort( $style_choices, function ( $a, $b ) use ( $style_choices ) {
		$rank = function ( $k ) { return 1; };
		$ra = $rank( $a ); $rb = $rank( $b );
		if ( $ra !== $rb ) { return $ra - $rb; }
		$la = isset( $style_choices[ $a ]['label'] ) ? $style_choices[ $a ]['label'] : $a;
		$lb = isset( $style_choices[ $b ]['label'] ) ? $style_choices[ $b ]['label'] : $b;
		return strcasecmp( (string) $la, (string) $lb );
	} );

	$tabs['preloader'] = array(
		'title'   => __( 'Preloader', 'fw' ),
		'type'    => 'tab',
		'options' => array(
			'preloader_box' => array(
				'title'   => __( 'Preloader', 'fw' ),
				'type'    => 'box',
				'options' => array(
					'animation_preloader' => array(
						'type'          => 'multi',
						'label'         => false,
						'inner-options' => array(
							'enable' => array(
								'label'        => __( 'Enable preloader', 'fw' ),
								'desc'         => __( 'Show a full-screen loading screen until the page is ready, then fade it away. Front end only.', 'fw' ),
								'type'         => 'switch',
								'value'        => 'no',
								'left-choice'  => array( 'value' => 'no',  'label' => __( 'No', 'fw' ) ),
								'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
							),
						),
					),
					'preloader_style' => array(
						// Popover multi-picker for consistency with Scroll Progress / Scroll Loop,
						// and so a future style can reveal its own options in its `choices` slot.
						'type'         => 'multi-picker',
						'popover'      => true,
						'label'        => __( 'Style', 'fw' ),
						'desc'         => __( 'How the loading screen looks. Logo pulse needs a logo below.', 'fw' ),
						'show_borders' => false,
						'value'        => array( 'style' => 'spinner' ),
						'picker'       => array(
							'style' => array(
								'type'    => 'image-picker',
								'label'   => false,
								'desc'    => __( 'Hover a tile to preview it larger.', 'fw' ),
								'value'   => 'spinner',
								'search'  => __( 'Search preloaders…', 'fw' ),
								'choices' => $style_choices,
							),
						),
						'choices' => $style_reveals,
					),
					'preloader_bg' => function_exists( 'sc_color_field_compact' )
						? sc_color_field_compact( array( 'label' => __( 'Background', 'fw' ), 'kind' => 'bg', 'value' => array( 'predefined' => '', 'custom' => '#0b1220' ) ) )
						: array( 'type' => 'color-picker', 'label' => __( 'Background', 'fw' ), 'value' => '#0b1220' ),
					'preloader_accent' => function_exists( 'sc_color_field_compact' )
						? sc_color_field_compact( array( 'label' => __( 'Accent', 'fw' ), 'kind' => 'bg', 'desc' => __( 'Spinner / bar / dots / counter colour.', 'fw' ), 'value' => array( 'predefined' => '', 'custom' => '#2f74e6' ) ) )
						: array( 'type' => 'color-picker', 'label' => __( 'Accent', 'fw' ), 'desc' => __( 'Spinner / bar / dots / counter colour.', 'fw' ), 'value' => '#2f74e6' ),
					'preloader_logo' => array(
						'type'  => 'upload',
						'label' => __( 'Logo (optional)', 'fw' ),
						'desc'  => __( 'Shown above the animation (and centred for the Logo pulse style).', 'fw' ),
						'value' => array(),
					),
					'preloader_min' => array(
						'type'       => 'slider',
						'label'      => __( 'Minimum display (s)', 'fw' ),
						'desc'       => __( 'Keep the loader up at least this long so it never just flashes.', 'fw' ),
						'value'      => 0.4,
						'properties' => array( 'min' => 0, 'max' => 4, 'step' => 0.1 ),
					),
					'preloader_fade' => array(
						'type'       => 'slider',
						'label'      => __( 'Fade out (s)', 'fw' ),
						'value'      => 0.5,
						'properties' => array( 'min' => 0.2, 'max' => 1.5, 'step' => 0.05 ),
					),
				),
			),
		),
	);
	return $tabs;
} );
