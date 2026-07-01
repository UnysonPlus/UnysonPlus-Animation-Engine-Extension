<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Page Transitions module (site-wide).
 *
 * A full-screen overlay injected at wp_body_open covers the viewport on first paint and
 * reveals it (entrance, pure CSS so it runs even without JS). On an internal link click the
 * runtime plays the reverse (cover) then navigates, so pages feel connected. An optional
 * first-visit loader shows a spinner/bar/dots until the page finishes loading. Config lives
 * in Theme Settings → Animations → Page Transitions; nothing loads in admin or when disabled.
 */

if ( ! function_exists( 'upw_pt_setting' ) ) :
	function upw_pt_setting( $key, $default = '' ) {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return $default;
		}
		$v = fw_get_db_settings_option( 'animation_pt', array() );
		if ( is_array( $v ) && isset( $v[ $key ] ) && $v[ $key ] !== '' && $v[ $key ] !== null ) {
			return is_bool( $v[ $key ] ) ? ( $v[ $key ] ? 'yes' : 'no' ) : $v[ $key ];
		}
		return $default;
	}
endif;

if ( ! function_exists( 'upw_pt_enabled' ) ) :
	function upw_pt_enabled() {
		return upw_pt_setting( 'enable', 'no' ) === 'yes' && ! is_admin();
	}
endif;

if ( ! function_exists( 'upw_pt_types' ) ) :
	function upw_pt_types() {
		return array( 'fade', 'slide', 'curtain', 'wipe', 'reveal' );
	}
endif;

/* ------------------------------------------------------------------ *
 * 1) Inject the overlay (+ optional loader) at the very top of <body>.
 * ------------------------------------------------------------------ */
add_action( 'wp_body_open', function () {
	if ( ! upw_pt_enabled() ) {
		return;
	}
	$type  = in_array( upw_pt_setting( 'type', 'fade' ), upw_pt_types(), true ) ? upw_pt_setting( 'type', 'fade' ) : 'fade';
	$color = function_exists( 'sc_color_to_css' ) ? sc_color_to_css( upw_pt_setting( 'color', '' ), '#0e1524' ) : '#0e1524';
	$dur   = (float) upw_pt_setting( 'duration', 0.6 );
	$style = '--pt-color:' . esc_attr( $color ) . '; --pt-dur:' . esc_attr( $dur ) . 's;';

	echo '<div class="upw-pt" data-pt-type="' . esc_attr( $type ) . '" style="' . $style . '" aria-hidden="true"><span class="upw-pt__p upw-pt__p1"></span><span class="upw-pt__p upw-pt__p2"></span></div>';

	if ( upw_pt_setting( 'loader', 'no' ) === 'yes' ) {
		$lstyle = in_array( upw_pt_setting( 'loader_style', 'spinner' ), array( 'spinner', 'bar', 'dots' ), true ) ? upw_pt_setting( 'loader_style', 'spinner' ) : 'spinner';
		echo '<div class="upw-pt-loader" data-pt-loader="' . esc_attr( $lstyle ) . '" style="' . $style . '" aria-hidden="true"><span class="upw-pt-loader__box"><i></i><i></i><i></i></span></div>';
	}
}, 1 );

/* ------------------------------------------------------------------ *
 * 2) Enqueue CSS (head) + JS (footer) — front end only, when enabled.
 * ------------------------------------------------------------------ */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! upw_pt_enabled() ) {
		return;
	}
	$ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( ! $ext ) {
		return;
	}
	$base = $ext->get_declared_URI( '/modules/page-transitions' );
	$ver  = $ext->manifest->get_version();
	$dir  = __DIR__;
	$jsv  = file_exists( "$dir/static/js/page-transitions.js" )  ? $ver . '.' . filemtime( "$dir/static/js/page-transitions.js" )  : $ver;
	$cssv = file_exists( "$dir/static/css/page-transitions.css" ) ? $ver . '.' . filemtime( "$dir/static/css/page-transitions.css" ) : $ver;

	wp_enqueue_style( 'upw-pt', $base . '/static/css/page-transitions.css', array(), $cssv );
	wp_enqueue_script( 'upw-pt', $base . '/static/js/page-transitions.js', array(), $jsv, true );

	$cfg = array(
		'duration'      => (float) upw_pt_setting( 'duration', 0.6 ),
		'loader'        => upw_pt_setting( 'loader', 'no' ) === 'yes',
		'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
	);
	wp_add_inline_script( 'upw-pt', 'window.upwPtCfg=' . wp_json_encode( $cfg ) . ';', 'before' );
}, 5 );

/* ------------------------------------------------------------------ *
 * 3) Theme Settings → Animations → Page Transitions sub-tab.
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
		? sc_color_field_compact( array( 'label' => __( 'Overlay color', 'fw' ), 'kind' => 'bg', 'value' => array( 'predefined' => '', 'custom' => '#0e1524' ) ) )
		: array( 'type' => 'color-picker', 'label' => __( 'Overlay color', 'fw' ), 'value' => '#0e1524' );

	$tabs['page_transitions'] = array(
		'title'   => __( 'Page Transitions', 'fw' ),
		'type'    => 'tab',
		'options' => array(
			'pt_box' => array(
				'title'   => __( 'Page Transitions', 'fw' ),
				'type'    => 'box',
				'options' => array(
					'animation_pt' => array(
						'type'          => 'multi',
						'label'         => false,
						'inner-options' => array(
							'enable' => $sw(
								__( 'Enable page transitions', 'fw' ),
								__( 'A full-screen overlay reveals each page on load and covers it when you navigate — so pages feel connected. Front end only.', 'fw' ) . ( function_exists( 'upw_perf_note' ) ? ' ' . upw_perf_note( 'site' ) : '' ),
								false
							),
							'type' => array(
								'type'    => 'select',
								'label'   => __( 'Transition', 'fw' ),
								'value'   => 'fade',
								'choices' => array(
									'fade'    => __( 'Fade', 'fw' ),
									'slide'   => __( 'Slide up', 'fw' ),
									'curtain' => __( 'Curtain (split)', 'fw' ),
									'wipe'    => __( 'Wipe', 'fw' ),
									'reveal'  => __( 'Circle reveal', 'fw' ),
								),
							),
							'color'    => $color,
							'duration' => array(
								'type'       => 'slider',
								'label'      => __( 'Duration (s)', 'fw' ),
								'value'      => 0.6,
								'properties' => array( 'min' => 0.2, 'max' => 1.5, 'step' => 0.1 ),
							),
							'loader'       => $sw( __( 'First-visit loader', 'fw' ), __( 'Show a loading indicator on the visitor’s first page of the session, until it finishes loading.', 'fw' ), false ),
							'loader_style' => array(
								'type'    => 'select',
								'label'   => __( 'Loader style', 'fw' ),
								'value'   => 'spinner',
								'choices' => array( 'spinner' => __( 'Spinner', 'fw' ), 'bar' => __( 'Bar', 'fw' ), 'dots' => __( 'Dots', 'fw' ) ),
							),
						),
					),
				),
			),
		),
	);
	return $tabs;
} );
