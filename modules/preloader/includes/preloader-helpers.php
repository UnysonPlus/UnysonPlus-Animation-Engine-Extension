<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Preloader module: helpers.
 *
 * The style registry, the per-style animator markup, the request-cached settings reader, the
 * enabled flag and the per-style option reader. Loaded first by preloader.php (the settings +
 * render parts depend on these). All wrapped in function_exists guards.
 */

if ( ! function_exists( 'upw_preloader_styles' ) ) :
	function upw_preloader_styles() {
		return array(
			'spinner'       => __( 'Spinner', 'fw' ),
			'dual_ring'     => __( 'Dual ring', 'fw' ),
			'gradient'      => __( 'Gradient ring', 'fw' ),
			'dots'          => __( 'Bouncing dots', 'fw' ),
			'dots_fade'     => __( 'Fading dots', 'fw' ),
			'orbit'         => __( 'Orbit', 'fw' ),
			'bars'          => __( 'Equalizer bars', 'fw' ),
			'grid'          => __( 'Pulsing grid', 'fw' ),
			'pulse'         => __( 'Pulse', 'fw' ),
			'ripple'        => __( 'Ripple', 'fw' ),
			'square'        => __( 'Flip square', 'fw' ),
			'bar'           => __( 'Progress bar', 'fw' ),
			'progress_ring' => __( 'Progress ring (%)', 'fw' ),
			'counter'       => __( 'Counter (%)', 'fw' ),
			'curtain'       => __( 'Curtain', 'fw' ),
			'logo'          => __( 'Logo pulse', 'fw' ),
		);
	}
endif;

/** The animator markup for a preloader style (built into the overlay). */
if ( ! function_exists( 'upw_preloader_inner' ) ) :
	function upw_preloader_inner( $style, $has_logo ) {
		switch ( $style ) {
			case 'dual_ring':     return '<div class="upw-pl-dual"></div>';
			case 'gradient':      return '<div class="upw-pl-grad"></div>';
			case 'dots':          return '<div class="upw-pl-dots"><span></span><span></span><span></span></div>';
			case 'dots_fade':     return '<div class="upw-pl-fade">' . str_repeat( '<i></i>', 8 ) . '</div>';
			case 'orbit':         return '<div class="upw-pl-orbit"></div>';
			case 'bars':          return '<div class="upw-pl-bars">' . str_repeat( '<i></i>', 5 ) . '</div>';
			case 'grid':          return '<div class="upw-pl-grid">' . str_repeat( '<i></i>', 9 ) . '</div>';
			case 'pulse':         return '<div class="upw-pl-pulse"></div>';
			case 'ripple':        return '<div class="upw-pl-ripple"><i></i><i></i></div>';
			case 'square':        return '<div class="upw-pl-square"></div>';
			case 'bar':           return '<div class="upw-pl-track"><div class="upw-pl-bar"></div></div>';
			case 'progress_ring': return '<div class="upw-pl-ring"><span class="upw-pl-num">0</span></div>';
			case 'counter':       return '<div class="upw-pl-count"><span class="upw-pl-num">0</span><span class="upw-pl-pct">%</span></div>';
			case 'curtain':       return '<span class="upw-pl-panel upw-pl-panel--a"></span><span class="upw-pl-panel upw-pl-panel--b"></span>';
			case 'logo':          return $has_logo ? '' : '<div class="upw-pl-spinner"></div>';
			case 'spinner':
			default:              return '<div class="upw-pl-spinner"></div>';
		}
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

		// `preloader_style` is a multi-picker (picker id `style`): [ 'style' => '<style>',
		// '<style>' => [ …per-style options… ] ]. Tolerate the legacy scalar shape too.
		$sp    = $get( 'preloader_style', array() );
		$style = is_array( $sp ) ? ( isset( $sp['style'] ) ? (string) $sp['style'] : 'spinner' ) : (string) $sp;
		if ( ! array_key_exists( $style, upw_preloader_styles() ) ) {
			$style = 'spinner';
		}
		$logo = $get( 'preloader_logo', array() );
		$logo_url = is_array( $logo ) ? ( isset( $logo['url'] ) ? $logo['url'] : '' ) : (string) $logo;

		$s = array(
			'enabled' => ( $on === 'yes' ),
			'style'   => $style,
			// Raw multi-picker value — future styles read their own options from
			// $s['opts'][ $style ][ '<key>' ] (see upw_preloader_style_opt()).
			'opts'    => is_array( $sp ) ? $sp : array(),
			// Colours use the preset-or-custom compact picker; resolve to a CSS
			// color (preset → var(--color-{slug}), custom → hex) for the --pl-*
			// custom properties. sc_color_to_css tolerates a plain-string value too.
			'bg'      => function_exists( 'sc_color_to_css' ) ? sc_color_to_css( $get( 'preloader_bg', '#0b1220' ), '#0b1220' ) : '#0b1220',
			'accent'  => function_exists( 'sc_color_to_css' ) ? sc_color_to_css( $get( 'preloader_accent', '#2f74e6' ), '#2f74e6' ) : '#2f74e6',
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

/**
 * Read a per-style option for the CURRENT style from the `preloader_style` multi-picker
 * reveal. Future preloader styles that ship their own options (declared in the style's
 * `choices` reveal group) read them through this — e.g. upw_preloader_style_opt( 'count', 5 ).
 */
if ( ! function_exists( 'upw_preloader_style_opt' ) ) :
	function upw_preloader_style_opt( $key, $default = null ) {
		$s     = upw_preloader_settings();
		$style = isset( $s['style'] ) ? $s['style'] : '';
		if ( $style !== '' && isset( $s['opts'][ $style ] ) && is_array( $s['opts'][ $style ] ) && array_key_exists( $key, $s['opts'][ $style ] ) ) {
			return $s['opts'][ $style ][ $key ];
		}
		return $default;
	}
endif;
