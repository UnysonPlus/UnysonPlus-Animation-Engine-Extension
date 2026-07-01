<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Cursor module.
 *
 * A site-wide custom cursor with a rich set of styles (dot / ring / crosshair /
 * brackets / dashed / glow / gradient / blob / spotlight / comet / custom image /
 * glyph …) picked from an image grid, plus cross-cutting modifiers (grow-on-hover,
 * magnetic snap, difference blend, hide-native). Config lives in Theme Settings →
 * Animations → Cursor; the runtime enqueues on the front end ONLY when enabled.
 * Skips touch devices; honours reduced motion.
 */

if ( ! function_exists( 'upw_cursor_setting' ) ) :
	/** Read a Cursor setting from the theme-scoped `animation_cursor` bucket. */
	function upw_cursor_setting( $key, $default = '' ) {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return $default;
		}
		$v = fw_get_db_settings_option( 'animation_cursor', array() );
		if ( is_array( $v ) && isset( $v[ $key ] ) && $v[ $key ] !== '' && $v[ $key ] !== null ) {
			return is_bool( $v[ $key ] ) ? ( $v[ $key ] ? 'yes' : 'no' ) : $v[ $key ];
		}
		return $default;
	}
endif;

if ( ! function_exists( 'upw_cursor_styles' ) ) :
	/** style-id => label. Single source of truth for the picker + validation. */
	function upw_cursor_styles() {
		return array(
			'none'     => __( 'None', 'fw' ),
			'dot'      => __( 'Dot', 'fw' ),
			'ring'     => __( 'Ring', 'fw' ),
			'dot_ring' => __( 'Dot + Ring', 'fw' ),
			'crosshair'=> __( 'Crosshair', 'fw' ),
			'brackets' => __( 'Brackets', 'fw' ),
			'square'   => __( 'Square', 'fw' ),
			'dashed'   => __( 'Dashed Ring', 'fw' ),
			'glow'     => __( 'Glow', 'fw' ),
			'gradient' => __( 'Gradient', 'fw' ),
			'blob'     => __( 'Blob', 'fw' ),
			'spotlight'=> __( 'Spotlight', 'fw' ),
			'comet'    => __( 'Comet', 'fw' ),
			'particles'=> __( 'Particle Trail', 'fw' ),
			'elastic'  => __( 'Elastic Ring', 'fw' ),
			'lens'     => __( 'Glass Lens', 'fw' ),
			'arrow'    => __( 'Directional Arrow', 'fw' ),
			'radar'    => __( 'Radar Pulse', 'fw' ),
			'plus'     => __( 'Plus', 'fw' ),
			'star'     => __( 'Sparkle', 'fw' ),
			'diamond'  => __( 'Diamond', 'fw' ),
			'dual_ring'=> __( 'Dual Ring', 'fw' ),
			'bullseye' => __( 'Bullseye', 'fw' ),
			'reticle'  => __( 'Camera Reticle', 'fw' ),
			'invert'   => __( 'Invert Disc', 'fw' ),
			'echo'     => __( 'Afterimage', 'fw' ),
			'firefly'  => __( 'Firefly', 'fw' ),
			'confetti' => __( 'Confetti Trail', 'fw' ),
			'bubble'   => __( 'Bubbles', 'fw' ),
			'spring'   => __( 'Spring Dot', 'fw' ),
			'streak'   => __( 'Motion Streak', 'fw' ),
			'rope'     => __( 'Rubber Band', 'fw' ),
			'metaball' => __( 'Gooey Metaball', 'fw' ),
			'label'    => __( 'Contextual Label', 'fw' ),
			'sticky'   => __( 'Sticky Cursor', 'fw' ),
			'word_trail' => __( 'Word Trail', 'fw' ),
			'reveal'   => __( 'Image Reveal', 'fw' ),
			'magnify'  => __( 'Magnify Lens', 'fw' ),
			'ink'      => __( 'Ink Brush', 'fw' ),
			'fluid'    => __( 'Fluid Smear', 'fw' ),
			'distort'  => __( 'Ripple Trail', 'fw' ),
			'custom'   => __( 'Custom Image', 'fw' ),
			'glyph'    => __( 'Glyph / Emoji', 'fw' ),
		);
	}
endif;

/* ------------------------------------------------------------------ *
 * 1) Theme Settings → Animations → Cursor sub-tab.
 * ------------------------------------------------------------------ */
add_filter( 'upw_anim_engine_module_tabs', function ( $tabs ) {
	$sw = function ( $label, $desc, $default_yes ) {
		return array(
			'type'         => 'switch',
			'label'        => $label,
			'desc'         => $desc,
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
								__( 'Replace the pointer with a custom cursor site-wide. Automatically disabled on touch screens.', 'fw' ) . ( function_exists( 'upw_perf_note' ) ? ' ' . upw_perf_note( 'site' ) : '' ),
								false
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

/* ------------------------------------------------------------------ *
 * 2) Enqueue the runtime — front end, only when the cursor is enabled.
 * ------------------------------------------------------------------ */
add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() || upw_cursor_setting( 'enable', 'no' ) !== 'yes' ) {
		return;
	}
	// Style is a multi-picker: { shape: 'dot_ring', dot_ring: {trail}, glyph: {glyph_char}, … }.
	$style_mp = upw_cursor_setting( 'style', array() );
	$style    = ( is_array( $style_mp ) && ! empty( $style_mp['shape'] ) ) ? (string) $style_mp['shape'] : 'dot_ring';
	if ( ! array_key_exists( $style, upw_cursor_styles() ) || $style === 'none' ) {
		return;
	}
	$sub = ( is_array( $style_mp ) && isset( $style_mp[ $style ] ) && is_array( $style_mp[ $style ] ) ) ? $style_mp[ $style ] : array();
	$ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( ! $ext ) {
		return;
	}
	$base = $ext->get_declared_URI( '/modules/cursor' );
	$ver  = $ext->manifest->get_version();
	$dir  = __DIR__;
	$jsv  = file_exists( "$dir/static/js/cursor.js" )  ? $ver . '.' . filemtime( "$dir/static/js/cursor.js" )  : $ver;
	$cssv = file_exists( "$dir/static/css/cursor.css" ) ? $ver . '.' . filemtime( "$dir/static/css/cursor.css" ) : $ver;

	wp_enqueue_style( 'upw-cursor', $base . '/static/css/cursor.css', array(), $cssv );
	wp_enqueue_script( 'upw-cursor', $base . '/static/js/cursor.js', array(), $jsv, true );

	$color     = function_exists( 'sc_color_to_css' ) ? sc_color_to_css( upw_cursor_setting( 'color', '' ), '#2f74e6' ) : '#2f74e6';
	// Canvas (2D context) can't use a CSS var() — resolve a real hex for ink/fluid/ripple.
	$color_hex = function_exists( 'sc_color_to_css' ) ? sc_color_to_css( upw_cursor_setting( 'color', '' ), '#2f74e6', true ) : '#2f74e6';
	$img   = isset( $sub['custom_image'] ) ? $sub['custom_image'] : array();
	$img   = ( is_array( $img ) && ! empty( $img['url'] ) ) ? esc_url_raw( $img['url'] ) : '';
	$rimg  = isset( $sub['reveal_image'] ) ? $sub['reveal_image'] : array();
	$rimg  = ( is_array( $rimg ) && ! empty( $rimg['url'] ) ) ? esc_url_raw( $rimg['url'] ) : '';

	// Word Trail typography (typography-v2 → JS style props + Google-font enqueue).
	$wf       = ( isset( $sub['word_font'] ) && is_array( $sub['word_font'] ) ) ? $sub['word_font'] : array();
	$word_font = array(
		'family'        => isset( $wf['family'] ) ? (string) $wf['family'] : '',
		'weight'        => isset( $wf['weight'] ) ? (string) $wf['weight'] : '',
		'size'          => isset( $wf['size'] ) ? (int) $wf['size'] : 13,
		'lineHeight'    => ( isset( $wf['line-height'] ) && (int) $wf['line-height'] > 0 ) ? (int) $wf['line-height'] : 0,
		'letterSpacing' => isset( $wf['letter-spacing'] ) ? (int) $wf['letter-spacing'] : 0,
		'style'         => isset( $wf['style'] ) ? (string) $wf['style'] : '',
	);
	if ( ! empty( $wf['google_font'] ) && ! empty( $wf['family'] ) ) {
		wp_enqueue_style(
			'upw-cursor-word-font-' . sanitize_title( $wf['family'] . ( isset( $wf['weight'] ) ? $wf['weight'] : '' ) ),
			'https://fonts.googleapis.com/css?family=' . str_replace( ' ', '+', $wf['family'] ) . ( ! empty( $wf['weight'] ) ? ':' . $wf['weight'] : '' ) . '&display=swap',
			array(),
			null
		);
	}

	$cfg = array(
		'style'         => $style,
		'color'         => $color !== '' ? $color : '#2f74e6',
		'colorHex'      => $color_hex !== '' ? $color_hex : '#2f74e6',
		'canvasFollowScroll' => ( isset( $sub['follow_scroll'] ) ? $sub['follow_scroll'] : 'yes' ) === 'yes',
		'size'          => (int) upw_cursor_setting( 'size', 8 ),
		'trail'         => (float) ( isset( $sub['trail'] ) ? $sub['trail'] : 0.18 ),
		'glyph'         => (string) ( isset( $sub['glyph_char'] ) ? $sub['glyph_char'] : '→' ),
		'image'         => $img,
		'spotRadius'    => (int) ( isset( $sub['spot_radius'] ) ? $sub['spot_radius'] : 160 ),
		'spotDim'       => (float) ( isset( $sub['spot_dim'] ) ? $sub['spot_dim'] : 0.6 ),
		'count'         => (int) ( isset( $sub['count'] ) ? $sub['count'] : 8 ),
		'elastic'       => (float) ( isset( $sub['elastic'] ) ? $sub['elastic'] : 0.5 ),
		'lensRadius'    => (int) ( isset( $sub['lens_radius'] ) ? $sub['lens_radius'] : 70 ),
		'lensBlur'      => (float) ( isset( $sub['lens_blur'] ) ? $sub['lens_blur'] : 4 ),
		'radarSpeed'    => (float) ( isset( $sub['radar_speed'] ) ? $sub['radar_speed'] : 1.6 ),
		'label'         => (string) ( isset( $sub['default_label'] ) ? $sub['default_label'] : 'View' ),
		'word'          => (string) ( isset( $sub['word'] ) ? $sub['word'] : 'scroll' ),
		'wordFont'      => $word_font,
		'revealImage'   => $rimg,
		'revealRadius'  => (int) ( isset( $sub['reveal_radius'] ) ? $sub['reveal_radius'] : 80 ),
		'zoom'          => (float) ( isset( $sub['zoom'] ) ? $sub['zoom'] : 2 ),
		'magnifyScope'  => (string) ( isset( $sub['magnify_scope'] ) ? $sub['magnify_scope'] : 'images' ),
		'inkWidth'      => (int) ( isset( $sub['ink_width'] ) ? $sub['ink_width'] : 6 ),
		'hoverGrow'     => upw_cursor_setting( 'hover_grow', 'yes' ) === 'yes',
		'magnetic'      => upw_cursor_setting( 'magnetic', 'no' ) === 'yes',
		'blend'         => upw_cursor_setting( 'blend', 'no' ) === 'yes',
		'clickRipple'   => upw_cursor_setting( 'click_ripple', 'no' ) === 'yes',
		'clickBurst'    => upw_cursor_setting( 'click_burst', 'no' ) === 'yes',
		'hideDefault'   => upw_cursor_setting( 'hide_default', 'yes' ) === 'yes',
		'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
	);
	wp_add_inline_script( 'upw-cursor', 'window.upwCursorCfg=' . wp_json_encode( $cfg ) . ';', 'before' );
} );
