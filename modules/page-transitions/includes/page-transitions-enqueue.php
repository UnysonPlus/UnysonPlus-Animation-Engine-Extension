<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Page Transitions module: front-end runtime.
 *
 * Injects the full-screen overlay (+ optional loader) at wp_body_open, flags Content Fade-Up on
 * <body>, and enqueues the on-demand CSS (shared base + the chosen transition's partial) + JS with
 * the resolved config — front end only, when enabled. Depends on the helpers.
 *
 * NOTE: uses UPW_PAGE_TRANSITIONS_DIR (defined in page-transitions.php) — NOT __DIR__ — for
 * filemtime cache-busting, because this file lives in includes/ but the static assets are at the
 * module root.
 */

/* ------------------------------------------------------------------ *
 * 1) Inject the overlay (+ optional loader) at the very top of <body>.
 * ------------------------------------------------------------------ */
add_action( 'wp_body_open', function () {
	if ( ! upw_pt_enabled() ) {
		return;
	}
	$r     = upw_pt_resolve();
	$type  = $r['type'];
	$color = function_exists( 'sc_color_to_css' ) ? sc_color_to_css( upw_pt_setting( 'color', '' ), '#0e1524' ) : '#0e1524';
	$style = '--pt-color:' . esc_attr( $color ) . '; --pt-dur:' . esc_attr( $r['dur'] ) . 's;';
	if ( $r['count'] ) { $style .= ' --pt-cells:' . (int) $r['count'] . ';'; }

	$attrs = 'data-pt-type="' . esc_attr( $type ) . '" data-pt-total="' . esc_attr( $r['total'] ) . '"';
	if ( $r['dir'] !== '' ) { $attrs .= ' data-pt-dir="' . esc_attr( $r['dir'] ) . '"'; }
	if ( $r['count'] ) { $attrs .= ' data-pt-count="' . (int) $r['count'] . '"'; }

	// Inner markup: strips for blinds, a cell grid for checkerboard/pixels, else two panels.
	$inner = '';
	if ( $type === 'blinds' ) {
		for ( $i = 0; $i < $r['count']; $i++ ) {
			$inner .= '<span class="upw-pt__strip" style="--i:' . $i . ';"></span>';
		}
	} elseif ( $type === 'checkerboard' || $type === 'pixels' ) {
		$cols = (int) $r['count'];
		$rows = max( 4, (int) ceil( $cols * 9 / 16 ) );
		$style .= ' --pt-cols:' . $cols . '; --pt-rows:' . $rows . ';';
		$n = $cols * $rows;
		for ( $i = 0; $i < $n; $i++ ) {
			$d = ( $type === 'pixels' ) ? ( mt_rand( 0, 100 ) / 100 ) : ( ( ( $i % $cols ) + intval( $i / $cols ) ) % 2 ? 0.14 : 0 );
			$inner .= '<span class="upw-pt__cell" style="--d:' . $d . 's;"></span>';
		}
	} else {
		$inner = '<span class="upw-pt__p upw-pt__p1"></span><span class="upw-pt__p upw-pt__p2"></span>';
	}

	echo '<div class="upw-pt" ' . $attrs . ' style="' . $style . '" aria-hidden="true">' . $inner . '</div>'; // phpcs:ignore -- all values escaped above

	if ( upw_pt_setting( 'loader', 'no' ) === 'yes' ) {
		$lstyle = in_array( upw_pt_setting( 'loader_style', 'spinner' ), array( 'spinner', 'bar', 'dots' ), true ) ? upw_pt_setting( 'loader_style', 'spinner' ) : 'spinner';
		echo '<div class="upw-pt-loader" data-pt-loader="' . esc_attr( $lstyle ) . '" style="' . $style . '" aria-hidden="true"><span class="upw-pt-loader__box"><i></i><i></i><i></i></span></div>';
	}
}, 1 );

// Content Fade-Up rises the page content in on load — flag it on <body> so the CSS runs
// from the first paint (body_class() is emitted just before wp_body_open).
add_filter( 'body_class', function ( $classes ) {
	if ( upw_pt_enabled() ) {
		$r = upw_pt_resolve();
		if ( $r['type'] === 'contentfade' ) { $classes[] = 'upw-pt-cin'; }
	}
	return $classes;
} );

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
	$dir  = UPW_PAGE_TRANSITIONS_DIR;
	$jsv  = file_exists( "$dir/static/js/page-transitions.js" )  ? $ver . '.' . filemtime( "$dir/static/js/page-transitions.js" )  : $ver;

	// On-demand CSS: this is a site-wide single choice, so ship the shared base + ONLY the
	// chosen transition's CSS partial, not all 23 (was one 21 KB bundle → ~3.5 KB).
	$tr   = upw_pt_setting( 'transition', array() );
	$type = ( is_array( $tr ) && ! empty( $tr['transition'] ) ) ? (string) $tr['transition'] : 'fade';
	if ( ! in_array( $type, upw_pt_types(), true ) ) { $type = 'fade'; }
	$basecss = "$dir/static/css/base.css";
	$typecss = "$dir/static/css/effects/$type.css";
	wp_enqueue_style( 'upw-pt-base', $base . '/static/css/base.css', array(), file_exists( $basecss ) ? $ver . '.' . filemtime( $basecss ) : $ver );
	if ( file_exists( $typecss ) ) {
		wp_enqueue_style( 'upw-pt-' . sanitize_html_class( $type ), $base . '/static/css/effects/' . $type . '.css', array( 'upw-pt-base' ), $ver . '.' . filemtime( $typecss ) );
	}
	wp_enqueue_script( 'upw-pt', $base . '/static/js/page-transitions.js', array(), $jsv, true );

	$cfg = array(
		'duration'      => (float) upw_pt_setting( 'duration', 0.6 ),
		'loader'        => upw_pt_setting( 'loader', 'no' ) === 'yes',
		'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
	);
	wp_add_inline_script( 'upw-pt', 'window.upwPtCfg=' . wp_json_encode( $cfg ) . ';', 'before' );
}, 5 );
