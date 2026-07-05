<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Cursor module: Theme Settings → Site-wide UX → Cursor sub-tab.
 *
 * Registers the Cursor sub-tab (an enable switch + a popover style multi-picker with per-style
 * option reveals + shared modifiers) via the engine's `upw_anim_engine_module_tabs` filter.
 * Depends on upw_cursor_styles() from cursor-helpers.php.
 */

add_filter( 'upw_anim_engine_module_tabs', function ( $tabs ) {
	$sw = function ( $label, $desc, $default_yes, $help = '' ) {
		return array(
			'type'         => 'switch',
			'label'        => $label,
			'desc'         => $desc,
			'help'         => $help,
			'value'        => $default_yes ? 'yes' : 'no',
			'left-choice'  => array( 'value' => 'no',  'label' => __( 'No', 'fw' ) ),
			'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
		);
	};

	// Style picker — an image grid (animated SVG tiles per style).
	$ext  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	$base = $ext ? $ext->get_declared_URI( '/modules/cursor/static/img/cursors' ) : '';
	$choices = array();
	foreach ( upw_cursor_styles() as $id => $label ) {
		$choices[ $id ] = array(
			'small' => array( 'src' => $base . '/' . str_replace( '_', '-', $id ) . '.svg', 'height' => 66 ),
			'large' => array( 'src' => $base . '/' . str_replace( '_', '-', $id ) . '.svg', 'height' => 132 ),
			'label' => $label,
		);
	}

	$color = function_exists( 'sc_color_field_compact' )
		? sc_color_field_compact( array( 'label' => __( 'Cursor color', 'fw' ), 'kind' => 'bg', 'value' => array( 'predefined' => '', 'custom' => '#2f74e6' ) ) )
		: array( 'type' => 'color-picker', 'label' => __( 'Cursor color', 'fw' ), 'value' => '#2f74e6' );

	$tabs['cursor'] = array(
		'title'   => __( 'Cursor', 'fw' ),
		'type'    => 'tab',
		'options' => array(
			'cursor_box' => array(
				'title'   => __( 'Custom Cursor', 'fw' ),
				'type'    => 'box',
				'options' => array(
					'animation_cursor' => array(
						'type'          => 'multi',
						'label'         => false,
						'inner-options' => array(
							'enable' => $sw(
								__( 'Enable custom cursor', 'fw' ),
								__( 'Replace the pointer with a custom cursor site-wide. Automatically disabled on touch screens.', 'fw' ),
								false,
								function_exists( 'upw_perf_note' ) ? upw_perf_note( 'site' ) : ''
							),
							'style' => array(
								'type'         => 'multi-picker',
								'label'        => __( 'Style', 'fw' ),
								'desc'         => __( 'The cursor shape / effect — pick one and its options appear below.', 'fw' ),
								'popover'      => true,
								'show_borders' => false,
								'value'        => array( 'shape' => 'dot_ring' ),
								'picker'       => array(
									'shape' => array(
										'type'    => 'image-picker',
										'label'   => false,
										'desc'    => __( 'Hover a tile to preview it larger.', 'fw' ),
										'value'   => 'dot_ring',
										'choices' => $choices,
									),
								),
								'choices' => array(
									'dot_ring' => array(
										'trail' => array(
											'type'       => 'slider',
											'label'      => __( 'Ring trail', 'fw' ),
											'desc'       => __( 'How much the ring lags behind the dot (lower = more trailing).', 'fw' ),
											'value'      => 0.18,
											'properties' => array( 'min' => 0.05, 'max' => 0.5, 'step' => 0.01 ),
										),
									),
									'comet' => array(
										'trail' => array(
											'type'       => 'slider',
											'label'      => __( 'Tail follow', 'fw' ),
											'desc'       => __( 'How tightly the tail follows (lower = longer tail).', 'fw' ),
											'value'      => 0.18,
											'properties' => array( 'min' => 0.05, 'max' => 0.5, 'step' => 0.01 ),
										),
									),
									'particles' => array(
										'count' => array(
											'type'       => 'slider',
											'label'      => __( 'Density', 'fw' ),
											'desc'       => __( 'How many particles trail the pointer.', 'fw' ),
											'value'      => 8,
											'properties' => array( 'min' => 3, 'max' => 24, 'step' => 1 ),
										),
									),
									'elastic' => array(
										'elastic' => array(
											'type'       => 'slider',
											'label'      => __( 'Stretchiness', 'fw' ),
											'desc'       => __( 'How much the ring squashes & stretches with speed.', 'fw' ),
											'value'      => 0.5,
											'properties' => array( 'min' => 0.1, 'max' => 1, 'step' => 0.05 ),
										),
									),
									'lens' => array(
										'lens_radius' => array(
											'type'       => 'slider',
											'label'      => __( 'Lens radius (px)', 'fw' ),
											'value'      => 70,
											'properties' => array( 'min' => 30, 'max' => 140, 'step' => 5 ),
										),
										'lens_blur' => array(
											'type'       => 'slider',
											'label'      => __( 'Blur (px)', 'fw' ),
											'desc'       => __( 'Frosted-glass blur of whatever is behind the lens.', 'fw' ),
											'value'      => 4,
											'properties' => array( 'min' => 0, 'max' => 10, 'step' => 0.5 ),
										),
									),
									'radar' => array(
										'radar_speed' => array(
											'type'       => 'slider',
											'label'      => __( 'Pulse interval (s)', 'fw' ),
											'desc'       => __( 'Seconds between emitted rings (lower = faster).', 'fw' ),
											'value'      => 1.6,
											'properties' => array( 'min' => 0.6, 'max' => 3, 'step' => 0.1 ),
										),
									),
									'echo' => array(
										'count' => array(
											'type'       => 'slider',
											'label'      => __( 'Echoes', 'fw' ),
											'desc'       => __( 'How many fading copies trail behind.', 'fw' ),
											'value'      => 8,
											'properties' => array( 'min' => 3, 'max' => 20, 'step' => 1 ),
										),
									),
									'firefly' => array(
										'count' => array(
											'type'       => 'slider',
											'label'      => __( 'Fireflies', 'fw' ),
											'value'      => 10,
											'properties' => array( 'min' => 4, 'max' => 24, 'step' => 1 ),
										),
									),
									'confetti' => array(
										'count' => array(
											'type'       => 'slider',
											'label'      => __( 'Confetti', 'fw' ),
											'value'      => 14,
											'properties' => array( 'min' => 6, 'max' => 30, 'step' => 1 ),
										),
										'multicolor' => $sw( __( 'Multi-colored', 'fw' ), __( 'Give each confetti piece a random festive color instead of the single cursor color.', 'fw' ), true ),
									),
									'bubble' => array(
										'count' => array(
											'type'       => 'slider',
											'label'      => __( 'Bubbles', 'fw' ),
											'value'      => 8,
											'properties' => array( 'min' => 3, 'max' => 20, 'step' => 1 ),
										),
									),
									'metaball' => array(
										'trail' => array(
											'type'       => 'slider',
											'label'      => __( 'Ring lag', 'fw' ),
											'desc'       => __( 'How much the second blob lags (lower = more gooey stretch).', 'fw' ),
											'value'      => 0.18,
											'properties' => array( 'min' => 0.05, 'max' => 0.5, 'step' => 0.01 ),
										),
									),
									'label' => array(
										'default_label' => array(
											'type'  => 'text',
											'label' => __( 'Default label', 'fw' ),
											'desc'  => __( 'Text shown persistently in the pill as it follows the pointer. Any element can override it on hover with a <code>data-cursor-label="…"</code> attribute (e.g. “View” on a gallery, “Drag” on a slider). <strong>Leave blank</strong> to show just a small dot that expands into a label only over elements that set data-cursor-label.', 'fw' ),
											'value' => 'View',
										),
										'label_font' => array(
											'type'       => 'typography-v2',
											'label'      => __( 'Font', 'fw' ),
											'desc'       => __( 'Family, weight, size, line-height & letter-spacing for the label. The pill background uses the Cursor color option above; label text stays white.', 'fw' ),
											'components' => array( 'subset' => false, 'color' => false ),
											'value'      => array(
												'family'         => '',
												'style'          => 'normal',
												'weight'         => '600',
												'size'           => 12,
												'line-height'    => 12,
												'letter-spacing' => 0,
											),
										),
									),
									'word_trail' => array(
										'word' => array(
											'type'  => 'text',
											'label' => __( 'Word', 'fw' ),
											'desc'  => __( 'The word that trails the pointer.', 'fw' ),
											'value' => 'scroll',
										),
										'word_font' => array(
											'type'       => 'typography-v2',
											'label'      => __( 'Font', 'fw' ),
											'desc'       => __( 'Family, weight, size, line-height & letter-spacing for the trailing word. Colour comes from the Cursor color option above.', 'fw' ),
											'components' => array( 'subset' => false, 'color' => false ),
											'value'      => array(
												'family'         => '',
												'style'          => 'normal',
												'weight'         => '700',
												'size'           => 13,
												'line-height'    => 13,
												'letter-spacing' => 0,
											),
										),
									),
									'reveal' => array(
										'reveal_image' => array(
											'type'  => 'upload',
											'label' => __( 'Reveal image', 'fw' ),
											'desc'  => __( 'The image the cursor window reveals as it moves (it stays fixed to the viewport).', 'fw' ),
										),
										'reveal_radius' => array(
											'type'       => 'slider',
											'label'      => __( 'Window radius (px)', 'fw' ),
											'value'      => 80,
											'properties' => array( 'min' => 40, 'max' => 160, 'step' => 5 ),
										),
									),
									'magnify' => array(
										'magnify_scope' => array(
											'type'    => 'select',
											'label'   => __( 'Magnify', 'fw' ),
											'desc'    => __( 'What the lens magnifies as it passes over the page.', 'fw' ),
											'help'    => __( '“Everything (incl. text)” is the total-maximization mode: it clones the whole page into the lens and scales it, so text, buttons and backgrounds all magnify — not just images. Trade-offs: it roughly DOUBLES the page’s DOM in memory and works from a one-time snapshot, so dynamic/lazy content, videos, sliders and iframes won’t update inside the lens. Great for aesthetic / portfolio sites; heavier on very large pages. The two “light” modes only reposition an existing image, so they cost almost nothing but can’t magnify text.', 'fw' ),
											'value'   => 'images',
											'choices' => array(
												'images' => __( 'Images only (light)', 'fw' ),
												'media'  => __( 'Images + backgrounds (light)', 'fw' ),
												'all'    => __( 'Everything, incl. text (heavy)', 'fw' ),
											),
										),
										'zoom' => array(
											'type'       => 'slider',
											'label'      => __( 'Zoom', 'fw' ),
											'desc'       => __( 'Magnification factor inside the lens.', 'fw' ),
											'value'      => 2,
											'properties' => array( 'min' => 1.5, 'max' => 4, 'step' => 0.1 ),
										),
									),
									'ink' => array(
										'ink_width' => array(
											'type'       => 'slider',
											'label'      => __( 'Brush width (px)', 'fw' ),
											'value'      => 6,
											'properties' => array( 'min' => 2, 'max' => 18, 'step' => 1 ),
										),
										'follow_scroll' => $sw( __( 'Follow page scroll', 'fw' ), __( 'Ink sticks to the page and scrolls with it. Off = fixed to the screen.', 'fw' ), true ),
									),
									'fluid' => array(
										'follow_scroll' => $sw( __( 'Follow page scroll', 'fw' ), __( 'The smear sticks to the page and scrolls with it. Off = fixed to the screen.', 'fw' ), true ),
									),
									'distort' => array(
										'follow_scroll' => $sw( __( 'Follow page scroll', 'fw' ), __( 'Ripples stick to the page and scroll with it. Off = fixed to the screen.', 'fw' ), true ),
									),
									'glyph' => array(
										'glyph_char' => array(
											'type'  => 'text',
											'label' => __( 'Glyph / emoji', 'fw' ),
											'desc'  => __( 'Any character or emoji (e.g. → ✦ ✌ 🎯).', 'fw' ),
											'value' => '→',
										),
									),
									'custom' => array(
										'custom_image' => array(
											'type'  => 'upload',
											'label' => __( 'Custom image', 'fw' ),
											'desc'  => __( 'A small PNG / SVG.', 'fw' ),
										),
									),
									'spotlight' => array(
										'spot_radius' => array(
											'type'       => 'slider',
											'label'      => __( 'Spotlight radius (px)', 'fw' ),
											'value'      => 160,
											'properties' => array( 'min' => 60, 'max' => 400, 'step' => 10 ),
										),
										'spot_dim' => array(
											'type'       => 'slider',
											'label'      => __( 'Spotlight dim', 'fw' ),
											'desc'       => __( 'How dark the rest of the page gets (0 = none).', 'fw' ),
											'value'      => 0.6,
											'properties' => array( 'min' => 0, 'max' => 0.9, 'step' => 0.05 ),
										),
									),
								),
							),
							'color'  => $color,
							'size'   => array(
								'type'       => 'slider',
								'label'      => __( 'Size (px)', 'fw' ),
								'value'      => 8,
								'properties' => array( 'min' => 4, 'max' => 28, 'step' => 1 ),
							),
							'hover_grow'   => $sw( __( 'Grow on hover', 'fw' ), __( 'The cursor expands over links / buttons.', 'fw' ), true ),
							'magnetic'     => $sw( __( 'Magnetic snap', 'fw' ), __( 'The cursor eases toward the center of the hovered button / link.', 'fw' ), false ),
							'blend'        => $sw( __( 'Difference blend', 'fw' ), __( 'The cursor inverts against whatever is behind it.', 'fw' ), false ),
							'click_ripple' => $sw( __( 'Click ripple', 'fw' ), __( 'Emit an expanding ring wherever you click. Works with any style.', 'fw' ), false ),
							'click_burst'  => $sw( __( 'Click burst', 'fw' ), __( 'Spark a small particle burst on click. Works with any style.', 'fw' ), false ),
							'hide_default' => $sw( __( 'Hide the native cursor', 'fw' ), __( 'Hide the OS pointer while the custom cursor is shown.', 'fw' ), true ),
						),
					),
				),
			),
		),
	);
	return $tabs;
} );
