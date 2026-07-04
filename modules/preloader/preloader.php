<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Preloader / Page Loader module (site-wide).
 *
 * Shows a full-screen loading screen until the page is ready, then animates it away — six styles
 * (spinner, bar, dots, counter, curtain, logo). Configured in Theme Settings → Animations →
 * Preloader. The overlay is printed at wp_body_open (so it covers content from the first paint) and
 * removed on window `load` (after a minimum display time). Front end only; assets load only when
 * enabled. Distinct from Page Transitions (which animates route changes, not the first load).
 */

if ( ! function_exists( 'upw_preloader_styles' ) ) :
	function upw_preloader_styles() {
		return array(
			'spinner' => __( 'Spinner', 'fw' ),
			'bar'     => __( 'Progress bar', 'fw' ),
			'dots'    => __( 'Bouncing dots', 'fw' ),
			'counter' => __( 'Counter (%)', 'fw' ),
			'curtain' => __( 'Curtain', 'fw' ),
			'logo'    => __( 'Logo pulse', 'fw' ),
		);
	}
endif;

/** Resolve + cache the preloader settings for this request. */
if ( ! function_exists( 'upw_preloader_settings' ) ) :
	function upw_preloader_settings() {
		static $s = null;
		if ( $s !== null ) {
			return $s;
		}
		$get = function ( $id, $def = '' ) {
			return function_exists( 'fw_get_db_settings_option' ) ? fw_get_db_settings_option( $id, $def ) : $def;
		};
		$enable = $get( 'animation_preloader', array() );
		$on     = ( is_array( $enable ) && isset( $enable['enable'] ) ) ? $enable['enable'] : 'no';

		$style = (string) $get( 'preloader_style', 'spinner' );
		if ( ! array_key_exists( $style, upw_preloader_styles() ) ) {
			$style = 'spinner';
		}
		$logo = $get( 'preloader_logo', array() );
		$logo_url = is_array( $logo ) ? ( isset( $logo['url'] ) ? $logo['url'] : '' ) : (string) $logo;

		$s = array(
			'enabled' => ( $on === 'yes' ),
			'style'   => $style,
			'bg'      => (string) $get( 'preloader_bg', '#0b1220' ),
			'accent'  => (string) $get( 'preloader_accent', '#2f74e6' ),
			'logo'    => $logo_url,
			'min'     => (float) $get( 'preloader_min', 0.4 ),
			'fade'    => (float) $get( 'preloader_fade', 0.5 ),
		);
		return $s;
	}
endif;

if ( ! function_exists( 'upw_preloader_enabled' ) ) :
	function upw_preloader_enabled() {
		$s = upw_preloader_settings();
		return ! empty( $s['enabled'] );
	}
endif;

/* 1) Theme Settings → Animations → Preloader tab. */
add_filter( 'upw_anim_engine_module_tabs', function ( $tabs ) {
	$ext  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	$base = $ext ? $ext->get_declared_URI( '/modules/preloader/static/img' ) : '';
	$tile = function ( $file, $label ) use ( $base ) {
		return array(
			'small' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 66 ),
			'large' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 132 ),
			'label' => $label,
		);
	};
	$style_choices = array();
	foreach ( upw_preloader_styles() as $k => $lbl ) {
		$style_choices[ $k ] = $tile( $k, $lbl );
	}

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
						'type'    => 'image-picker',
						'label'   => __( 'Style', 'fw' ),
						'desc'    => __( 'How the loading screen looks. Logo pulse needs a logo below.', 'fw' ),
						'value'   => 'spinner',
						'choices' => $style_choices,
					),
					'preloader_bg' => array(
						'type'  => 'color-picker',
						'label' => __( 'Background', 'fw' ),
						'value' => '#0b1220',
					),
					'preloader_accent' => array(
						'type'  => 'color-picker',
						'label' => __( 'Accent', 'fw' ),
						'desc'  => __( 'Spinner / bar / dots / counter colour.', 'fw' ),
						'value' => '#2f74e6',
					),
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

/* 2) Enqueue the runtime (front end only, when enabled). */
add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() || ! upw_preloader_enabled() ) {
		return;
	}
	$ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( ! $ext ) {
		return;
	}
	$base = $ext->get_declared_URI( '/modules/preloader' );
	$ver  = $ext->manifest->get_version();
	$dir  = __DIR__;
	$fver = function ( $rel ) use ( $dir, $ver ) { $abs = $dir . $rel; return file_exists( $abs ) ? $ver . '.' . filemtime( $abs ) : $ver; };

	wp_enqueue_style( 'upw-preloader', $base . '/static/css/preloader.css', array(), $fver( '/static/css/preloader.css' ) );
	wp_enqueue_script( 'upw-preloader', $base . '/static/js/preloader.js', array(), $fver( '/static/js/preloader.js' ), true );

	$s   = upw_preloader_settings();
	$cfg = array(
		'style'      => $s['style'],
		'minDisplay' => max( 0, $s['min'] ),
		'fadeOut'    => max( 0.1, $s['fade'] ),
	);
	wp_add_inline_script( 'upw-preloader', 'window.upwPreloaderCfg=' . wp_json_encode( $cfg ) . ';', 'before' );
}, 20 );

/* 3) Print the overlay as early as possible so it covers content from the first paint. */
add_action( 'wp_body_open', function () {
	if ( is_admin() || ! upw_preloader_enabled() ) {
		return;
	}
	$s      = upw_preloader_settings();
	$style  = $s['style'];
	$accent = preg_replace( '/[^#0-9a-zA-Z(),.%\s-]/', '', $s['accent'] );
	$bg     = preg_replace( '/[^#0-9a-zA-Z(),.%\s-]/', '', $s['bg'] );

	$vars = '--pl-bg:' . $bg . ';--pl-accent:' . $accent . ';--pl-fade:' . rtrim( rtrim( number_format( max( 0.1, $s['fade'] ), 2, '.', '' ), '0' ), '.' ) . 's;';

	$logo = '';
	if ( $s['logo'] !== '' ) {
		$logo = '<img class="upw-pl-logo" src="' . esc_url( $s['logo'] ) . '" alt="" />';
	}

	// Per-style animator markup.
	$inner = '';
	switch ( $style ) {
		case 'bar':
			$inner = '<div class="upw-pl-track"><div class="upw-pl-bar"></div></div>';
			break;
		case 'dots':
			$inner = '<div class="upw-pl-dots"><span></span><span></span><span></span></div>';
			break;
		case 'counter':
			$inner = '<div class="upw-pl-count"><span class="upw-pl-num">0</span><span class="upw-pl-pct">%</span></div>';
			break;
		case 'curtain':
			$inner = '<span class="upw-pl-panel upw-pl-panel--a"></span><span class="upw-pl-panel upw-pl-panel--b"></span>';
			break;
		case 'logo':
			$inner = ''; // the logo itself is the animator; if no logo, fall through to a spinner
			if ( $s['logo'] === '' ) {
				$inner = '<div class="upw-pl-spinner"></div>';
			}
			break;
		case 'spinner':
		default:
			$inner = '<div class="upw-pl-spinner"></div>';
			break;
	}

	// For curtain, the panels ARE the cover and the logo sits centred above them.
	$content = ( $style === 'curtain' )
		? $inner . '<div class="upw-pl-center">' . $logo . '</div>'
		: '<div class="upw-pl-center">' . $logo . $inner . '</div>';

	echo '<div class="upw-preloader upw-pl--' . esc_attr( $style ) . '" style="' . esc_attr( $vars ) . '" role="status" aria-label="' . esc_attr__( 'Loading', 'fw' ) . '">'
		. $content // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built from esc_url + whitelisted markup
		. '</div>'
		. '<script>document.documentElement.classList.add("upw-pl-lock");</script>';
}, 1 );
