<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Cursor module.
 *
 * A site-wide custom cursor (dot / ring / dot+trailing-ring) with optional
 * hover-grow, difference blend, and native-cursor hiding. Because it's global (not
 * per-element), its config lives in Theme Settings → Animations → Cursor (added via
 * the engine's upw_anim_engine_module_tabs hook), and the runtime enqueues on the
 * front end ONLY when enabled. Skips touch devices; honours reduced motion.
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
								false
							),
							'style' => array(
								'type'    => 'select',
								'label'   => __( 'Style', 'fw' ),
								'value'   => 'dot_ring',
								'choices' => array(
									'dot'      => __( 'Dot', 'fw' ),
									'ring'     => __( 'Ring', 'fw' ),
									'dot_ring' => __( 'Dot + trailing ring', 'fw' ),
								),
							),
							'color'    => $color,
							'size'     => array(
								'type'       => 'slider',
								'label'      => __( 'Dot size (px)', 'fw' ),
								'value'      => 8,
								'properties' => array( 'min' => 4, 'max' => 20, 'step' => 1 ),
							),
							'ring_lag' => array(
								'type'       => 'slider',
								'label'      => __( 'Trail', 'fw' ),
								'desc'       => __( 'How much the ring lags behind the dot (lower = more trailing).', 'fw' ),
								'value'      => 0.18,
								'properties' => array( 'min' => 0.05, 'max' => 0.5, 'step' => 0.01 ),
							),
							'hover_grow'   => $sw( __( 'Grow on hover', 'fw' ), __( 'The ring expands over links / buttons.', 'fw' ), true ),
							'blend'        => $sw( __( 'Difference blend', 'fw' ), __( 'The cursor inverts against whatever is behind it (looks best on strong colors).', 'fw' ), false ),
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

	$color = function_exists( 'sc_color_to_css' )
		? sc_color_to_css( upw_cursor_setting( 'color', '' ), '#2f74e6' )
		: '#2f74e6';

	$cfg = array(
		'style'         => (string) upw_cursor_setting( 'style', 'dot_ring' ),
		'color'         => $color !== '' ? $color : '#2f74e6',
		'size'          => (int) upw_cursor_setting( 'size', 8 ),
		'ringLag'       => (float) upw_cursor_setting( 'ring_lag', 0.18 ),
		'hoverGrow'     => upw_cursor_setting( 'hover_grow', 'yes' ) === 'yes',
		'blend'         => upw_cursor_setting( 'blend', 'no' ) === 'yes',
		'hideDefault'   => upw_cursor_setting( 'hide_default', 'yes' ) === 'yes',
		'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
	);
	wp_add_inline_script( 'upw-cursor', 'window.upwCursorCfg=' . wp_json_encode( $cfg ) . ';', 'before' );
} );
