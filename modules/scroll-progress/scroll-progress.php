<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Scroll Progress module.
 *
 * A site-wide reading-progress indicator with 16 styles — bars (solid/gradient/glow/segmented/
 * pill/labelled/under-nav/liquid), a side edge bar, corner ring / ring-with-number / gauge /
 * battery, a %-counter or reading-time chip, and section scroll-spy dots. Tiny self-contained
 * JS/CSS, enqueued site-wide only when enabled. Configured in Theme Settings → Animations →
 * Scroll Progress (its own tab — a page-level indicator, not a per-element effect).
 */

if ( ! function_exists( 'upw_scrollprog_enabled' ) ) :
	function upw_scrollprog_enabled() {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return false;
		}
		$v = fw_get_db_settings_option( 'animation_scrollprog', array() );
		return is_array( $v ) && isset( $v['enable'] ) && $v['enable'] === 'yes';
	}
endif;

if ( ! function_exists( 'upw_sp_color' ) ) :
	/** Returns a compact color option field for the scroll-progress bar, defaulting to the given hex. */
	function upw_sp_color( $default_hex, $label = null ) {
		$label = $label ?: __( 'Color', 'fw' );
		if ( function_exists( 'sc_color_field_compact' ) ) {
			return sc_color_field_compact( array( 'label' => $label, 'kind' => 'bg', 'value' => array( 'predefined' => '', 'custom' => $default_hex ) ) );
		}
		return array( 'type' => 'color-picker', 'label' => $label, 'value' => $default_hex );
	}
endif;

/* ------------------------------------------------------------------ *
 * 1) Theme Settings → Animations → Scroll Progress.
 * ------------------------------------------------------------------ */
add_filter( 'upw_anim_engine_module_tabs', function ( $tabs ) {
	// Shared field builders.
	$sw = function ( $label, $desc, $default_yes ) {
		return array(
			'type' => 'switch', 'label' => $label, 'desc' => $desc,
			'value' => $default_yes ? 'yes' : 'no',
			'left-choice' => array( 'value' => 'no', 'label' => __( 'No', 'fw' ) ),
			'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
		);
	};
	$sel = function ( $label, $default, $choices ) {
		return array( 'type' => 'select', 'label' => $label, 'value' => $default, 'choices' => $choices );
	};
	$slider = function ( $label, $val, $min, $max, $step = 1 ) {
		return array( 'type' => 'slider', 'label' => $label, 'value' => $val, 'properties' => array( 'min' => $min, 'max' => $max, 'step' => $step ) );
	};

	$hide      = $sw( __( 'Hide at the top', 'fw' ), __( 'Fade the indicator in only after the visitor starts scrolling.', 'fw' ), true );
	$thick     = $slider( __( 'Thickness (px)', 'fw' ), 4, 2, 14 );
	$size      = $slider( __( 'Size (px)', 'fw' ), 56, 32, 96, 2 );
	$click     = $sw( __( 'Click to scroll to top', 'fw' ), '', true );
	$pos_tb    = $sel( __( 'Position', 'fw' ), 'top', array( 'top' => __( 'Top of page', 'fw' ), 'bottom' => __( 'Bottom of page', 'fw' ) ) );
	$pos_lr    = $sel( __( 'Side', 'fw' ), 'right', array( 'right' => __( 'Right edge', 'fw' ), 'left' => __( 'Left edge', 'fw' ) ) );
	$pos_cnr   = $sel( __( 'Position', 'fw' ), 'br', array( 'br' => __( 'Bottom-right', 'fw' ), 'bl' => __( 'Bottom-left', 'fw' ) ) );
	$pos_cnr4  = $sel( __( 'Position', 'fw' ), 'br', array( 'br' => __( 'Bottom-right', 'fw' ), 'bl' => __( 'Bottom-left', 'fw' ), 'tr' => __( 'Top-right', 'fw' ), 'tl' => __( 'Top-left', 'fw' ) ) );

	$bar    = array( 'position' => $pos_tb, 'color' => upw_sp_color( '#2f74e6' ), 'thickness' => $thick, 'hide_top' => $hide );
	$corner = array( 'color' => upw_sp_color( '#2f74e6' ), 'size' => $size, 'position' => $pos_cnr, 'hide_top' => $hide );

	// Picker tiles.
	$ext  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	$base = $ext ? $ext->get_declared_URI( '/modules/scroll-progress/static/img/styles' ) : '';
	$tile = function ( $file, $label ) use ( $base ) {
		return array(
			'small' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 78 ),
			'large' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 150 ),
			'label' => $label,
		);
	};

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
						// Popover image-picker → label lives on the TOP level (popover rule).
						'type'         => 'multi-picker',
						'popover'      => true,
						'label'        => __( 'Style', 'fw' ),
						'desc'         => __( 'How the progress reads.', 'fw' ),
						'show_borders' => false,
						'value'        => array( 'kind' => 'bar' ),
						'picker'       => array(
							'kind' => array(
								'type'    => 'image-picker',
								'label'   => false,
								'desc'    => __( 'Hover a tile to preview it larger.', 'fw' ),
								'value'   => 'bar',
								'search'  => __( 'Search styles…', 'fw' ),
								'choices' => array(
									'counter'      => $tile( 'counter',      __( '% Counter', 'fw' ) ),
									'labeled'      => $tile( 'labeled',      __( '% label bar', 'fw' ) ),
									'bar'          => $tile( 'bar',          __( 'Bar', 'fw' ) ),
									'battery'      => $tile( 'battery',      __( 'Battery', 'fw' ) ),
									'gauge'        => $tile( 'gauge',        __( 'Gauge', 'fw' ) ),
									'glow'         => $tile( 'glow',         __( 'Glow edge', 'fw' ) ),
									'gradient'     => $tile( 'gradient',     __( 'Gradient bar', 'fw' ) ),
									'liquid'       => $tile( 'liquid',       __( 'Liquid bar', 'fw' ) ),
									'pill'         => $tile( 'pill',         __( 'Pill', 'fw' ) ),
									'ring'         => $tile( 'ring',         __( 'Ring', 'fw' ) ),
									'ring_number'  => $tile( 'ring_number',  __( 'Ring + %', 'fw' ) ),
									'dots'         => $tile( 'dots',         __( 'Section dots', 'fw' ) ),
									'segments'     => $tile( 'segments',     __( 'Segments', 'fw' ) ),
									'edge'         => $tile( 'edge',         __( 'Side edge', 'fw' ) ),
									'reading_time' => $tile( 'reading_time', __( 'Time left', 'fw' ) ),
									'under_nav'    => $tile( 'under_nav',    __( 'Under-nav bar', 'fw' ) ),
									'custom'       => $tile( 'custom',       __( 'Custom (code)', 'fw' ) ),
								),
							),
						),
						'choices' => array(
							'bar'       => $bar,
							'gradient'  => array( 'position' => $pos_tb, 'color' => upw_sp_color( '#6a8dff', __( 'Color (start)', 'fw' ) ), 'color2' => upw_sp_color( '#c56cff', __( 'Color (end)', 'fw' ) ), 'thickness' => $thick, 'hide_top' => $hide ),
							'glow'      => $bar,
							'segments'  => array( 'position' => $pos_tb, 'color' => upw_sp_color( '#2f74e6' ), 'thickness' => $thick, 'segments' => $slider( __( 'Segments', 'fw' ), 12, 4, 30 ), 'hide_top' => $hide ),
							'pill'      => $bar,
							'labeled'   => $bar,
							'under_nav' => array( 'color' => upw_sp_color( '#2f74e6' ), 'thickness' => $thick, 'offset' => $slider( __( 'Top offset (px)', 'fw' ), 60, 0, 200, 2 ), 'hide_top' => $hide ),
							'liquid'    => $bar,
							'edge'      => array( 'position' => $pos_lr, 'color' => upw_sp_color( '#2f74e6' ), 'thickness' => $thick, 'hide_top' => $hide ),
							'ring'         => array( 'color' => upw_sp_color( '#2f74e6' ), 'size' => $size, 'position' => $pos_cnr, 'click_top' => $click, 'hide_top' => $hide ),
							'ring_number'  => array( 'color' => upw_sp_color( '#2f74e6' ), 'size' => $size, 'position' => $pos_cnr, 'click_top' => $click, 'hide_top' => $hide ),
							'gauge'        => $corner,
							'battery'      => $corner,
							'counter'      => array( 'color' => upw_sp_color( '#2f74e6' ), 'position' => $pos_cnr4, 'hide_top' => $hide ),
							'reading_time' => array( 'color' => upw_sp_color( '#0e1524' ), 'position' => $pos_cnr4, 'wpm' => $slider( __( 'Words per minute', 'fw' ), 220, 120, 400, 10 ), 'hide_top' => $hide ),
							'dots'         => array( 'position' => $pos_lr, 'color' => upw_sp_color( '#2f74e6' ), 'hide_top' => $hide ),
							// The "Custom (code)" style reveals three code editors — paste any scroll-progress
							// indicator's HTML / CSS / JS to reproduce an art-directed rail the built-in styles can't
							// (e.g. a vertical side rail with per-chapter markers/labels). The runtime exposes the
							// current progress as `window.upwScrollProgress` (0..1), a `--sp-progress` CSS var on the
							// `.upw-sp--custom` container (and :root), and a `upw:scroll` CustomEvent — so a CSS-only or
							// JS rail can bind to scroll without re-implementing the math. Admin-authored, output verbatim.
							'custom' => array(
								'html' => array(
									'type'  => 'code-editor',
									'mode'  => 'htmlmixed',
									'label' => __( 'Custom HTML', 'fw' ),
									'desc'  => __( 'Markup placed inside the fixed <code>.upw-sp upw-sp--custom</code> container. Output verbatim — admin only. Use the <code>--sp-progress</code> CSS var (0–1) to drive fills/positions.', 'fw' ),
									'value' => '',
								),
								'css'  => array(
									'type'  => 'code-editor',
									'mode'  => 'css',
									'label' => __( 'Custom CSS', 'fw' ),
									'desc'  => __( 'Injected in a &lt;style&gt; tag on the front end. Target <code>.upw-sp--custom</code>; drive motion from <code>var(--sp-progress)</code> — e.g. <code>transform:scaleY(var(--sp-progress))</code>.', 'fw' ),
									'value' => '',
								),
								'js'   => array(
									'type'  => 'code-editor',
									'mode'  => 'js',
									'label' => __( 'Custom JS', 'fw' ),
									'desc'  => __( 'Runs after the markup. Read <code>window.upwScrollProgress</code> (0–1) or listen for <code>window.addEventListener("upw:scroll", e =&gt; e.detail)</code>.', 'fw' ),
									'value' => '',
								),
							),
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
	$valid = array( 'bar', 'gradient', 'glow', 'segments', 'pill', 'labeled', 'under_nav', 'liquid', 'edge', 'ring', 'ring_number', 'gauge', 'battery', 'counter', 'reading_time', 'dots', 'custom' );
	$s     = fw_get_db_settings_option( 'scrollprog', array() );
	$kind  = ( is_array( $s ) && isset( $s['kind'] ) && in_array( $s['kind'], $valid, true ) ) ? $s['kind'] : 'bar';

	// On-demand assets: map the chosen kind to its asset family and ship ONLY that family's
	// CSS+JS (this is a site-wide single choice) — was one 13 KB bundle → ~4-6 KB.
	$fam_map = array(
		'bar' => 'bar', 'gradient' => 'bar', 'glow' => 'bar', 'segments' => 'bar', 'pill' => 'bar',
		'labeled' => 'bar', 'under_nav' => 'bar', 'liquid' => 'bar', 'edge' => 'bar',
		'ring' => 'circle', 'ring_number' => 'circle', 'gauge' => 'circle',
		'battery' => 'battery', 'counter' => 'chip', 'reading_time' => 'chip', 'dots' => 'dots',
		'custom' => 'custom',
	);
	$fam  = isset( $fam_map[ $kind ] ) ? $fam_map[ $kind ] : 'bar';
	$fver = function ( $rel ) use ( $dir, $ver ) { $abs = $dir . $rel; return file_exists( $abs ) ? $ver . '.' . filemtime( $abs ) : $ver; };

	wp_enqueue_style( 'upw-sp-base', $base . '/static/css/base.css', array(), $fver( '/static/css/base.css' ) );
	wp_enqueue_style( 'upw-sp-css-' . $fam, $base . '/static/css/families/' . $fam . '.css', array( 'upw-sp-base' ), $fver( '/static/css/families/' . $fam . '.css' ) );
	wp_enqueue_script( 'upw-scroll-progress', $base . '/static/js/families/' . $fam . '.js', array(), $fver( '/static/js/families/' . $fam . '.js' ), true );

	$o     = ( is_array( $s ) && isset( $s[ $kind ] ) && is_array( $s[ $kind ] ) ) ? $s[ $kind ] : array();
	$col   = function ( $v, $d ) { return function_exists( 'sc_color_to_css' ) ? sc_color_to_css( $v, $d ) : $d; };

	$cfg = array(
		'kind'      => $kind,
		'color'     => $col( isset( $o['color'] ) ? $o['color'] : '', '#2f74e6' ),
		'color2'    => $col( isset( $o['color2'] ) ? $o['color2'] : '', '#c56cff' ),
		'thickness' => isset( $o['thickness'] ) ? (int) $o['thickness'] : 4,
		'size'      => isset( $o['size'] ) ? (int) $o['size'] : 56,
		'segments'  => isset( $o['segments'] ) ? (int) $o['segments'] : 12,
		'offset'    => isset( $o['offset'] ) ? (int) $o['offset'] : 60,
		'wpm'       => isset( $o['wpm'] ) ? max( 60, (int) $o['wpm'] ) : 220,
		'position'  => isset( $o['position'] ) ? (string) $o['position'] : '',
		'clickTop'  => ! isset( $o['click_top'] ) || $o['click_top'] === 'yes',
		'hideTop'   => ! isset( $o['hide_top'] ) || $o['hide_top'] === 'yes',
	);
	wp_add_inline_script( 'upw-scroll-progress', 'window.upwScrollProgCfg=' . wp_json_encode( $cfg ) . ';', 'before' );

	// CUSTOM (code) style — inject the admin-authored CSS + JS. CSS rides the family stylesheet
	// (upw-sp-css-custom); JS is appended AFTER custom.js so the progress driver (window.upwScrollProgress /
	// the --sp-progress CSS var / the 'upw:scroll' event) is already running. Markup is printed in wp_footer.
	if ( 'custom' === $kind ) {
		$c_css = isset( $o['css'] ) ? (string) $o['css'] : '';
		$c_js  = isset( $o['js'] )  ? (string) $o['js']  : '';
		if ( '' !== trim( $c_css ) ) { wp_add_inline_style( 'upw-sp-css-custom', $c_css ); }
		if ( '' !== trim( $c_js ) )  { wp_add_inline_script( 'upw-scroll-progress', $c_js ); }
	}
}, 20 );

/* ------------------------------------------------------------------ *
 * 3) Print the Custom (code) rail markup near the end of <body>.
 * ------------------------------------------------------------------ */
add_action( 'wp_footer', function () {
	if ( is_admin() || ! upw_scrollprog_enabled() ) {
		return;
	}
	$s = fw_get_db_settings_option( 'scrollprog', array() );
	if ( ! is_array( $s ) || ( isset( $s['kind'] ) ? $s['kind'] : 'bar' ) !== 'custom' ) {
		return;
	}
	$o    = ( isset( $s['custom'] ) && is_array( $s['custom'] ) ) ? $s['custom'] : array();
	$html = isset( $o['html'] ) ? (string) $o['html'] : '';
	if ( '' === trim( $html ) ) {
		return;
	}
	// Admin-authored markup, output verbatim (like the Customizer's Additional CSS / a code snippet). The
	// fixed container carries --sp-progress (0..1) so a CSS-only rail can drive fills/positions from it.
	echo '<div class="upw-sp upw-sp--custom">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
}, 5 );
