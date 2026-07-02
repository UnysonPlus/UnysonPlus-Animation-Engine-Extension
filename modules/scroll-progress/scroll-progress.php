<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Scroll Progress module.
 *
 * A site-wide reading-progress indicator: a thin bar at the top/bottom of the page, or a circular
 * ring in a corner (optionally a scroll-to-top button), that fills as the visitor scrolls. Tiny
 * self-contained JS/CSS, enqueued site-wide only when enabled. Configured in Theme Settings →
 * Animations → Scroll Progress (its own tab — it's a page-level indicator, not a per-element effect).
 */

if ( ! function_exists( 'upw_scrollprog_enabled' ) ) :
	function upw_scrollprog_enabled() {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return false; // off by default until switched on
		}
		$v = fw_get_db_settings_option( 'animation_scrollprog', array() );
		return is_array( $v ) && isset( $v['enable'] ) && $v['enable'] === 'yes';
	}
endif;

if ( ! function_exists( 'upw_sp_color' ) ) :
	function upw_sp_color( $default_hex ) {
		if ( function_exists( 'sc_color_field_compact' ) ) {
			return sc_color_field_compact( array( 'label' => __( 'Color', 'fw' ), 'kind' => 'bg', 'value' => array( 'predefined' => '', 'custom' => $default_hex ) ) );
		}
		return array( 'type' => 'color-picker', 'label' => __( 'Color', 'fw' ), 'value' => $default_hex );
	}
endif;

/* ------------------------------------------------------------------ *
 * 1) Theme Settings → Animations → Scroll Progress.
 * ------------------------------------------------------------------ */
add_filter( 'upw_anim_engine_module_tabs', function ( $tabs ) {
	$hide = array(
		'type'         => 'switch',
		'label'        => __( 'Hide at the top', 'fw' ),
		'desc'         => __( 'Fade the indicator in only after the visitor starts scrolling.', 'fw' ),
		'value'        => 'yes',
		'left-choice'  => array( 'value' => 'no',  'label' => __( 'No', 'fw' ) ),
		'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
	);
	$bar_opts = array(
		'color'     => upw_sp_color( '#2f74e6' ),
		'thickness' => array( 'type' => 'slider', 'label' => __( 'Thickness (px)', 'fw' ), 'value' => 4, 'properties' => array( 'min' => 2, 'max' => 12, 'step' => 1 ) ),
		'hide_top'  => $hide,
	);
	$circle_opts = array(
		'color'    => upw_sp_color( '#2f74e6' ),
		'size'     => array( 'type' => 'slider', 'label' => __( 'Size (px)', 'fw' ), 'value' => 52, 'properties' => array( 'min' => 32, 'max' => 90, 'step' => 2 ) ),
		'position' => array(
			'type'    => 'select',
			'label'   => __( 'Position', 'fw' ),
			'value'   => 'br',
			'choices' => array( 'br' => __( 'Bottom-right', 'fw' ), 'bl' => __( 'Bottom-left', 'fw' ) ),
		),
		'click_top' => array(
			'type'         => 'switch',
			'label'        => __( 'Click to scroll to top', 'fw' ),
			'value'        => 'yes',
			'left-choice'  => array( 'value' => 'no',  'label' => __( 'No', 'fw' ) ),
			'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
		),
		'hide_top' => $hide,
	);

	$tabs['scroll_progress'] = array(
		'title'   => __( 'Scroll Progress', 'fw' ),
		'type'    => 'tab',
		'options' => array(
			'scroll_progress_box' => array(
				'title'   => __( 'Scroll Progress', 'fw' ),
				'type'    => 'box',
				'options' => array(
					'animation_scrollprog' => array(
						'type'          => 'multi',
						'label'         => false,
						'inner-options' => array(
							'enable' => array(
								'label'        => __( 'Enable scroll progress', 'fw' ),
								'desc'         => __( 'Show a reading-progress indicator site-wide. Front end only.', 'fw' ),
								'type'         => 'switch',
								'value'        => 'no',
								'left-choice'  => array( 'value' => 'no',  'label' => __( 'No', 'fw' ) ),
								'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
							),
						),
					),
					'scrollprog' => array(
						// Inline (non-popover) multi-picker → label/desc live on the PICKER
						// sub-option, top-level false (CLAUDE.md rule #1). Putting them on the
						// top level makes the desc float to the bottom, below the revealed rows.
						'type'         => 'multi-picker',
						'label'        => false,
						'desc'         => false,
						'show_borders' => false,
						'value'        => array( 'kind' => 'bar_top' ),
						'picker'       => array(
							'kind' => array(
								'type'    => 'select',
								'label'   => __( 'Style', 'fw' ),
								'desc'    => __( 'How the progress reads.', 'fw' ),
								'value'   => 'bar_top',
								'choices' => array(
									'bar_top'    => __( 'Bar — top of page', 'fw' ),
									'bar_bottom' => __( 'Bar — bottom of page', 'fw' ),
									'circle'     => __( 'Circle — corner', 'fw' ),
								),
							),
						),
						'choices' => array(
							'bar_top'    => $bar_opts,
							'bar_bottom' => $bar_opts,
							'circle'     => $circle_opts,
						),
					),
				),
			),
		),
	);
	return $tabs;
} );

/* ------------------------------------------------------------------ *
 * 2) Enqueue site-wide (front end only) when enabled.
 * ------------------------------------------------------------------ */
add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() || ! upw_scrollprog_enabled() ) {
		return;
	}
	$ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( ! $ext ) {
		return;
	}
	$base = $ext->get_declared_URI( '/modules/scroll-progress' );
	$ver  = $ext->manifest->get_version();
	$dir  = __DIR__;
	$jsv  = file_exists( "$dir/static/js/scroll-progress.js" )  ? $ver . '.' . filemtime( "$dir/static/js/scroll-progress.js" )  : $ver;
	$cssv = file_exists( "$dir/static/css/scroll-progress.css" ) ? $ver . '.' . filemtime( "$dir/static/css/scroll-progress.css" ) : $ver;

	wp_enqueue_style( 'upw-scroll-progress', $base . '/static/css/scroll-progress.css', array(), $cssv );
	wp_enqueue_script( 'upw-scroll-progress', $base . '/static/js/scroll-progress.js', array(), $jsv, true );

	$s    = fw_get_db_settings_option( 'scrollprog', array() );
	$kind = ( is_array( $s ) && isset( $s['kind'] ) && in_array( $s['kind'], array( 'bar_top', 'bar_bottom', 'circle' ), true ) ) ? $s['kind'] : 'bar_top';
	$o    = ( is_array( $s ) && isset( $s[ $kind ] ) && is_array( $s[ $kind ] ) ) ? $s[ $kind ] : array();
	$color = function_exists( 'sc_color_to_css' ) ? sc_color_to_css( isset( $o['color'] ) ? $o['color'] : '', '#2f74e6' ) : '#2f74e6';

	$cfg = array(
		'kind'      => $kind,
		'color'     => $color,
		'thickness' => isset( $o['thickness'] ) ? (int) $o['thickness'] : 4,
		'size'      => isset( $o['size'] ) ? (int) $o['size'] : 52,
		'position'  => ( isset( $o['position'] ) && $o['position'] === 'bl' ) ? 'bl' : 'br',
		'clickTop'  => ! isset( $o['click_top'] ) || $o['click_top'] === 'yes',
		'hideTop'   => ! isset( $o['hide_top'] ) || $o['hide_top'] === 'yes',
	);
	wp_add_inline_script( 'upw-scroll-progress', 'window.upwScrollProgCfg=' . wp_json_encode( $cfg ) . ';', 'before' );
}, 20 );
