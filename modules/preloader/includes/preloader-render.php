<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Preloader module: front-end enqueue + overlay markup.
 *
 * Enqueues the runtime (front end only, when enabled) and prints the overlay at wp_body_open so it
 * covers content from the first paint. Depends on the helpers (settings / enabled / inner markup).
 *
 * NOTE: uses UPW_PRELOADER_DIR (defined in preloader.php) — NOT __DIR__ — for filemtime
 * cache-busting, because this file lives in includes/ but the static assets are at the module root.
 */

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
	$dir  = UPW_PRELOADER_DIR;
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
	$inner = upw_preloader_inner( $style, $s['logo'] !== '' );

	// For curtain, the panels ARE the cover and the logo sits centred above them.
	$content = ( $style === 'curtain' )
		? $inner . '<div class="upw-pl-center">' . $logo . '</div>'
		: '<div class="upw-pl-center">' . $logo . $inner . '</div>';

	echo '<div class="upw-preloader upw-pl--' . esc_attr( $style ) . '" style="' . esc_attr( $vars ) . '" role="status" aria-label="' . esc_attr__( 'Loading', 'fw' ) . '">'
		. $content // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built from esc_url + whitelisted markup
		. '</div>'
		. '<script>document.documentElement.classList.add("upw-pl-lock");</script>';
}, 1 );
