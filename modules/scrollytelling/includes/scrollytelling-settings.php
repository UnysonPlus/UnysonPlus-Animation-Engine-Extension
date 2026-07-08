<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Scrollytelling module: options declaration.
 *
 * Builds the Section-only "Scrollytelling" multi-picker (a popover image-picker keyed
 * `scrollytelling`, picker id `mode`), mirroring the Sticky Card Stack control. One shared options
 * group is revealed under every style; a single `intensity` knob drives whatever that style does.
 * Injected into the Section's Animations tab by scrollytelling-render.php.
 */

if ( ! function_exists( 'sc_get_scrollytelling_fields' ) ) :
	function sc_get_scrollytelling_fields() {
		$ext  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
		$base = $ext ? $ext->get_declared_URI( '/modules/scrollytelling/static/img' ) : '';
		$tile = function ( $file, $label ) use ( $base ) {
			return array(
				'small' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 66 ),
				'large' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 132 ),
				'label' => $label,
			);
		};
		$slider = function ( $label, $val, $min, $max, $step, $desc = '' ) {
			return array( 'type' => 'slider', 'label' => $label, 'desc' => $desc, 'value' => $val, 'properties' => array( 'min' => $min, 'max' => $max, 'step' => $step ) );
		};

		// One shared options group for every style.
		$opts = array(
			'pin_side' => array(
				'type'    => 'select',
				'label'   => __( 'Media side', 'fw' ),
				'desc'    => __( 'Which column is the pinned media panel — the other column holds the steps. "Top" pins the media as a full-width panel with the steps below it.', 'fw' ),
				'value'   => 'left',
				'choices' => array(
					'left'  => __( 'Media Left', 'fw' ),
					'right' => __( 'Media Right', 'fw' ),
					'top'   => __( 'Media Top (stacked)', 'fw' ),
				),
			),
			'media_height' => $slider( __( 'Media height (vh)', 'fw' ), 100, 60, 100, 5, __( 'Height of the pinned panel as a fraction of the viewport.', 'fw' ) ),
			'pin_offset'   => $slider( __( 'Pin offset (px)', 'fw' ), 0, 0, 160, 4, __( 'Gap from the top of the viewport where the panel pins (clear a sticky header).', 'fw' ) ),
			'activate_at'  => $slider( __( 'Activate at (%)', 'fw' ), 50, 20, 80, 5, __( 'Where in the viewport a step becomes active — its trigger line.', 'fw' ) ),
			'transition'   => $slider( __( 'Transition (s)', 'fw' ), 0.6, 0.2, 1.2, 0.05, __( 'Crossfade / transition duration between media states.', 'fw' ) ),
			'intensity'    => $slider( __( 'Intensity', 'fw' ), 0.5, 0, 1, 0.05, __( 'Strength of the chosen style — zoom amount, parallax rate, blur radius, drift distance, etc.', 'fw' ) ),
			'progress'     => array(
				'type'    => 'select',
				'label'   => __( 'Progress indicator', 'fw' ),
				'value'   => 'dots',
				'choices' => array(
					'dots' => __( 'Dots', 'fw' ),
					'bar'  => __( 'Bar', 'fw' ),
					'none' => __( 'None', 'fw' ),
				),
			),
		);

		// Directional styles (Slide / Push / Cover / Clip Wipe / Curtain) get a Direction picker on
		// top of the shared options; "Default" keeps each style's natural direction.
		$opts_dir     = array_merge( array(
			'direction' => array(
				'type'    => 'select',
				'label'   => __( 'Direction', 'fw' ),
				'desc'    => __( 'Which way the transition travels. Default keeps the style\'s natural direction.', 'fw' ),
				'value'   => 'auto',
				'choices' => array(
					'auto'  => __( 'Default', 'fw' ),
					'up'    => __( 'Up', 'fw' ),
					'down'  => __( 'Down', 'fw' ),
					'left'  => __( 'Left', 'fw' ),
					'right' => __( 'Right', 'fw' ),
				),
			),
		), $opts );
		$directional = upw_scrollytelling_directional();

		$tiles   = array( 'off' => $tile( 'off', __( 'Off', 'fw' ) ) );
		$choices = array();
		foreach ( upw_scrollytelling_styles() as $key => $label ) {
			$tiles[ $key ]   = $tile( $key, $label );
			$group           = in_array( $key, $directional, true ) ? $opts_dir : $opts;
			$choices[ $key ] = array( 'group_scrollytelling_' . $key => array( 'type' => 'group', 'options' => $group ) );
		}

		return array(
			'scrollytelling' => array(
				'type'         => 'multi-picker',
				'label'        => __( 'Scrollytelling', 'fw' ),
				'desc'         => __( 'Pin one column as a media panel while the other column\'s steps scroll past — the pinned media swaps to match the active step (Apple / Stripe style). Build the Section with TWO columns: one holds the media layers (stack N images), the other holds N step blocks. Step 1 shows media 1, step 2 shows media 2, and so on.', 'fw' ),
				'help'         => __( 'Scrollytelling (Animation Engine): a pinned-media narrative — one column pins while the other\'s steps scroll, and the media transitions per step (Crossfade, Slide, Zoom, Clip Wipe, Blur, Ken Burns, Parallax Depth, Pixelate). Media layers map to steps by index. Pure CSS sticky + IntersectionObserver; honours "reduce motion" (media shows statically above each step) and loads only on pages that use it. Section only.', 'fw' ) . ( function_exists( 'upw_perf_note' ) ? ' ' . upw_perf_note() : '' ),
				'popover'      => true,
				'show_borders' => false,
				'value'        => array( 'mode' => 'off' ),
				'anim_meta'    => array( 'category' => __( 'Scroll', 'fw' ) ),
				'picker'       => array(
					'mode' => array(
						'type'    => 'image-picker',
						'label'   => false,
						'desc'    => __( 'Hover a tile to preview it larger.', 'fw' ),
						'value'   => 'off',
						'choices' => $tiles,
					),
				),
				'choices' => $choices,
			),
		);
	}
endif;
