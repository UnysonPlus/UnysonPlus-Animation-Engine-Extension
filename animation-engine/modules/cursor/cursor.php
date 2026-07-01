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
								'type'    => 'image-picker',
								'label'   => __( 'Style', 'fw' ),
								'desc'    => __( 'The cursor shape / effect. Hover a tile to preview it larger.', 'fw' ),
								'value'   => 'dot_ring',
								'choices' => $choices,
							),
							'color'  => $color,
							'size'   => array(
								'type'       => 'slider',
								'label'      => __( 'Size (px)', 'fw' ),
								'value'      => 8,
								'properties' => array( 'min' => 4, 'max' => 28, 'step' => 1 ),
							),
							'trail'  => array(
								'type'       => 'slider',
								'label'      => __( 'Trail', 'fw' ),
								'desc'       => __( 'Lag/tail amount for Dot + Ring, Comet and Blob (lower = more trailing).', 'fw' ),
								'value'      => 0.18,
								'properties' => array( 'min' => 0.05, 'max' => 0.5, 'step' => 0.01 ),
							),
							'glyph_char' => array(
								'type'  => 'text',
								'label' => __( 'Glyph / emoji', 'fw' ),
								'desc'  => __( 'Used by the Glyph style — any character or emoji (e.g. → ✦ ✌ 🎯).', 'fw' ),
								'value' => '→',
							),
							'custom_image' => array(
								'type'  => 'upload',
								'label' => __( 'Custom image', 'fw' ),
								'desc'  => __( 'Used by the Custom Image style — a small PNG/SVG.', 'fw' ),
							),
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
							'hover_grow'   => $sw( __( 'Grow on hover', 'fw' ), __( 'The cursor expands over links / buttons.', 'fw' ), true ),
							'magnetic'     => $sw( __( 'Magnetic snap', 'fw' ), __( 'The cursor eases toward the center of the hovered button / link.', 'fw' ), false ),
							'blend'        => $sw( __( 'Difference blend', 'fw' ), __( 'The cursor inverts against whatever is behind it.', 'fw' ), false ),
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
 * 1b) Admin: reveal only the sub-options that apply to the picked style.
 *     Pure client-side — with JS off, all rows stay visible (their descs
 *     already say which style they serve), so nothing is ever lost.
 * ------------------------------------------------------------------ */
add_action( 'admin_enqueue_scripts', function () {
	$slug = function_exists( 'apply_filters' ) ? apply_filters( 'fw_get_settings_page_slug', 'fw-settings' ) : 'fw-settings';
	if ( ! isset( $_GET['page'] ) || $_GET['page'] !== $slug ) {
		return;
	}
	$ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( ! $ext ) {
		return;
	}
	$file = __DIR__ . '/static/js/cursor-settings.js';
	$ver  = $ext->manifest->get_version();
	$v    = file_exists( $file ) ? $ver . '.' . filemtime( $file ) : $ver;
	wp_enqueue_script( 'upw-cursor-settings', $ext->get_declared_URI( '/modules/cursor/static/js/cursor-settings.js' ), array( 'jquery' ), $v, true );
} );

/* ------------------------------------------------------------------ *
 * 2) Enqueue the runtime — front end, only when the cursor is enabled.
 * ------------------------------------------------------------------ */
add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() || upw_cursor_setting( 'enable', 'no' ) !== 'yes' ) {
		return;
	}
	$style = (string) upw_cursor_setting( 'style', 'dot_ring' );
	if ( ! array_key_exists( $style, upw_cursor_styles() ) || $style === 'none' ) {
		return;
	}
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

	$color = function_exists( 'sc_color_to_css' ) ? sc_color_to_css( upw_cursor_setting( 'color', '' ), '#2f74e6' ) : '#2f74e6';
	$img   = upw_cursor_setting( 'custom_image', array() );
	$img   = ( is_array( $img ) && ! empty( $img['url'] ) ) ? esc_url_raw( $img['url'] ) : '';

	$cfg = array(
		'style'         => $style,
		'color'         => $color !== '' ? $color : '#2f74e6',
		'size'          => (int) upw_cursor_setting( 'size', 8 ),
		'trail'         => (float) upw_cursor_setting( 'trail', 0.18 ),
		'glyph'         => (string) upw_cursor_setting( 'glyph_char', '→' ),
		'image'         => $img,
		'spotRadius'    => (int) upw_cursor_setting( 'spot_radius', 160 ),
		'spotDim'       => (float) upw_cursor_setting( 'spot_dim', 0.6 ),
		'hoverGrow'     => upw_cursor_setting( 'hover_grow', 'yes' ) === 'yes',
		'magnetic'      => upw_cursor_setting( 'magnetic', 'no' ) === 'yes',
		'blend'         => upw_cursor_setting( 'blend', 'no' ) === 'yes',
		'hideDefault'   => upw_cursor_setting( 'hide_default', 'yes' ) === 'yes',
		'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
	);
	wp_add_inline_script( 'upw-cursor', 'window.upwCursorCfg=' . wp_json_encode( $cfg ) . ';', 'before' );
} );
