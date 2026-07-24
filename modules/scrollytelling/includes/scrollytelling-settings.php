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
				'small' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 53 ),
				'large' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 132 ),
				'label' => $label,
			);
		};
		$slider = function ( $label, $val, $min, $max, $step, $desc = '' ) {
			return array( 'type' => 'slider', 'label' => $label, 'desc' => $desc, 'value' => $val, 'properties' => array( 'min' => $min, 'max' => $max, 'step' => $step ) );
		};

		// One shared options group for every style.
		$opts = array(
			'layout' => array(
				'type'    => 'select',
				'label'   => __( 'Layout', 'fw' ),
				'desc'    => __( 'Media Panel pins one column as media while the other column\'s steps scroll (the classic layout). Full-screen Stage turns EVERY column into a full-viewport scene — scene 1, scene 2, … play in order as the visitor scrolls, with an optional Backdrop (image sequence / video / image) scrubbing behind them. Build a Stage story with one column per scene.', 'fw' ),
				'value'   => 'panel',
				'choices' => array(
					'panel' => __( 'Media Panel + Steps', 'fw' ),
					'stage' => __( 'Full-screen Stage (scenes)', 'fw' ),
				),
			),
			'scene_length' => $slider( __( 'Scene length (screens)', 'fw' ), 1, 0.5, 3, 0.25, __( 'Stage layout only — how much scrolling each scene owns. 1 = one screen of scroll per scene; higher = slower, more cinematic pacing.', 'fw' ) ),
			'exit' => array(
				'type'    => 'select',
				'label'   => __( 'Hand-off to next section', 'fw' ),
				'desc'    => __( 'Stage layout only — how the story ends as you scroll past it. Fade to section colour dissolves the ride into this Section\'s background near the end, so it flows into the next section instead of cutting. Tip: set this Section\'s Background colour to match the section that follows for a seamless hand-off.', 'fw' ),
				'help'    => __( 'Under the hood: over the last part of the scroll the pinned stage\'s opacity eases to 0, revealing the Section background behind it; the pin then releases into the next section. "Hold" keeps the last frame fully opaque until the pin releases (a harder cut).', 'fw' ),
				'value'   => 'hold',
				'choices' => array(
					'hold' => __( 'Hold last frame (hard cut)', 'fw' ),
					'fade' => __( 'Fade to section colour (smooth)', 'fw' ),
				),
			),
			'exit_at' => $slider( __( 'Hand-off starts at (%)', 'fw' ), 78, 50, 95, 1, __( 'Fade only — how far through the story the fade begins. 78 = the last ~fifth of the scroll.', 'fw' ) ),
			'backdrop' => array(
				'type'         => 'multi-picker',
				'label'        => false,
				'desc'         => false,
				'value'        => array( 'source' => 'none' ),
				'show_borders' => false,
				'picker'       => array(
					'source' => array(
						'type'    => 'select',
						'label'   => __( 'Backdrop (stage)', 'fw' ),
						'desc'    => __( 'Stage layout only — a media layer behind the scenes, scrubbed by story progress. The camera-ride effect: a numbered frame sequence plays frame-by-frame as the visitor scrolls through the story.', 'fw' ),
						'value'   => 'none',
						'choices' => array(
							'none'     => __( 'None', 'fw' ),
							'frames'   => __( 'Image sequence — uploaded frames', 'fw' ),
							'sequence' => __( 'Image sequence — numbered URL pattern', 'fw' ),
							'video'    => __( 'Video (scrubbed)', 'fw' ),
							'image'    => __( 'Image (fixed)', 'fw' ),
						),
					),
				),
				'choices' => array(
					'frames' => array(
						'frames' => array(
							'type'        => 'multi-upload',
							'label'       => __( 'Frames', 'fw' ),
							'desc'        => __( 'Upload the frames in order (drag to reorder). Use evenly-sized images. Best for short rides; use the URL-pattern source for long (100+) sequences.', 'fw' ),
							'images_only' => true,
						),
						'fit' => array( 'type' => 'select', 'label' => __( 'Fit', 'fw' ), 'value' => 'cover', 'choices' => array( 'cover' => __( 'Cover', 'fw' ), 'contain' => __( 'Contain', 'fw' ) ) ),
					),
					'sequence' => array(
						'url_pattern' => array(
							'type'  => 'text',
							'label' => __( 'Frame URL pattern', 'fw' ),
							'desc'  => __( 'Use %d where the frame number goes, e.g. /wp-content/uploads/ride/%d.webp', 'fw' ),
							'value' => '',
						),
						'count' => array( 'type' => 'text', 'label' => __( 'Frame count', 'fw' ), 'value' => '120' ),
						'start' => array( 'type' => 'text', 'label' => __( 'First frame number', 'fw' ), 'value' => '0' ),
						'pad'   => array( 'type' => 'text', 'label' => __( 'Zero-pad digits', 'fw' ), 'desc' => __( '0 = none; 4 makes frame 7 load as 0007.', 'fw' ), 'value' => '0' ),
						'fit'   => array( 'type' => 'select', 'label' => __( 'Fit', 'fw' ), 'value' => 'cover', 'choices' => array( 'cover' => __( 'Cover', 'fw' ), 'contain' => __( 'Contain', 'fw' ) ) ),
					),
					'video' => array(
						'video_file' => array( 'type' => 'upload', 'label' => __( 'Video file', 'fw' ), 'images_only' => false ),
						'video_url'  => array( 'type' => 'text', 'label' => __( 'or Video URL', 'fw' ), 'desc' => __( 'Used when no file is uploaded. The video is paused and scrubbed by scroll.', 'fw' ), 'value' => '' ),
						'fit'        => array( 'type' => 'select', 'label' => __( 'Fit', 'fw' ), 'value' => 'cover', 'choices' => array( 'cover' => __( 'Cover', 'fw' ), 'contain' => __( 'Contain', 'fw' ) ) ),
					),
					'image' => array(
						'image' => array( 'type' => 'upload', 'label' => __( 'Image', 'fw' ), 'images_only' => true ),
						'fit'   => array( 'type' => 'select', 'label' => __( 'Fit', 'fw' ), 'value' => 'cover', 'choices' => array( 'cover' => __( 'Cover', 'fw' ), 'contain' => __( 'Contain', 'fw' ) ) ),
					),
				),
			),
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

		$tiles   = array(  );
		$choices = array();
		foreach ( upw_scrollytelling_styles() as $key => $label ) {
			$tiles[ $key ]   = $tile( $key, $label );
			$group           = in_array( $key, $directional, true ) ? $opts_dir : $opts;
			$choices[ $key ] = array( 'group_scrollytelling_' . $key => array( 'type' => 'group', 'options' => $group ) );
		}

		// Alphabetize picker tiles by label (Off first) for easier scanning.
		uksort( $tiles, function ( $a, $b ) use ( $tiles ) {
			$rank = function ( $k ) { if ( $k === 'none' || $k === 'off' ) { return 0; } return 1; };
			$ra = $rank( $a ); $rb = $rank( $b );
			if ( $ra !== $rb ) { return $ra - $rb; }
			$la = isset( $tiles[ $a ]['label'] ) ? $tiles[ $a ]['label'] : $a;
			$lb = isset( $tiles[ $b ]['label'] ) ? $tiles[ $b ]['label'] : $b;
			return strcasecmp( (string) $la, (string) $lb );
		} );

		return array(
			'scrollytelling' => array(
				'type'         => 'multi-picker',
				'label'        => __( 'Scrollytelling', 'fw' ),
				'desc'         => __( 'Pin one column as a media panel while the other column\'s steps scroll past — the pinned media swaps to match the active step (Apple / Stripe style). Build the Section with TWO columns: one holds the media layers (stack N images), the other holds N step blocks. Step 1 shows media 1, step 2 shows media 2, and so on.', 'fw' ),
				'help'         => __( 'Scrollytelling (Animation Engine): a pinned-media narrative — one column pins while the other\'s steps scroll, and the media transitions per step (Crossfade, Slide, Zoom, Clip Wipe, Blur, Ken Burns, Parallax Depth, Pixelate). Media layers map to steps by index. Pure CSS sticky + IntersectionObserver; honours "reduce motion" (media shows statically above each step) and loads only on pages that use it. Section only.', 'fw' ) . ( function_exists( 'upw_perf_note' ) ? ' ' . upw_perf_note() : '' ),
				'popover'      => true,
				'show_borders' => false,
				'value'        => array( 'mode' => 'off' ),
				'placeholder'  => __( 'Off', 'fw' ),
				'anim_meta'    => array( 'category' => __( 'Scroll', 'fw' ) ),
				'picker'       => array(
					'mode' => array(
						'type'    => 'image-picker',
						'label'   => false,
						'desc'    => __( 'Hover a tile to preview it larger.', 'fw' ),
						'value'   => 'off',
						'search'  => __( 'Search scenes…', 'fw' ),
						'choices' => $tiles,
					),
				),
				'choices' => $choices,
			),
		);
	}
endif;
