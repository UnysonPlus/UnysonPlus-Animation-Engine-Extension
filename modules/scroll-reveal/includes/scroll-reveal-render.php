<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Scroll Reveal module: runtime.
 *
 * Emits the chosen reveal onto the element wrapper (via `sc_build_wrapper_attr`): the clip-wipe
 * directions get `sc-clip-reveal sc-clip--<mode>` + `--cr-*` CSS vars; the "Pixelate In" style
 * gets `sc-pixel-reveal` + `data-px-*` attrs. Forces a wrapper when a reveal is the only setting,
 * and registers the module's on-demand asset layout with the shared loader.
 *
 * NOTE: uses UPW_SCROLL_REVEAL_DIR (defined in scroll-reveal.php) — NOT __DIR__ — for the
 * asset-loader path, because this file lives in includes/ while the static assets are at the
 * module root.
 */

$upw_sr_clip_modes = array( 'left', 'right', 'up', 'down', 'iris', 'diagonal' );
$upw_sr_all_modes  = array_merge( $upw_sr_clip_modes, array( 'pixelate' ) );

/* 2) Emit the reveal settings onto the element wrapper. */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) use ( $upw_sr_clip_modes ) {
	if ( ! upw_scroll_reveal_enabled() ) {
		return $attr;
	}
	$cr   = ( isset( $atts['scroll_reveal'] ) && is_array( $atts['scroll_reveal'] ) ) ? $atts['scroll_reveal'] : array();
	$mode = isset( $cr['mode'] ) ? (string) $cr['mode'] : 'none';

	// ---- Pixelate In (Canvas 2D pixel-resolve). ----
	if ( 'pixelate' === $mode ) {
		$o = ( isset( $cr['pixelate'] ) && is_array( $cr['pixelate'] ) ) ? $cr['pixelate'] : array();

		$cls           = isset( $attr['class'] ) ? trim( (string) $attr['class'] ) : '';
		$attr['class'] = esc_attr( trim( $cls . ' sc-pixel-reveal' ) );

		$attr['data-px-coarse'] = esc_attr( max( 20, min( 200, (int) ( $o['coarseness'] ?? 100 ) ) ) );
		$attr['data-px-steps']  = esc_attr( max( 3, min( 8, (int) ( $o['steps'] ?? 5 ) ) ) );
		$attr['data-px-speed']  = esc_attr( max( 40, min( 300, (int) ( $o['speed'] ?? 80 ) ) ) );
		if ( isset( $o['replay'] ) && $o['replay'] === 'yes' ) {
			$attr['data-px-replay'] = '1';
		}

		upw_scroll_reveal_flag( true );
		if ( function_exists( 'upw_anim_use_asset' ) ) {
			upw_anim_use_asset( 'scroll-reveal', 'pixelate' );
		}
		return $attr;
	}

	// ---- Clip-path wipe directions. ----
	if ( ! in_array( $mode, $upw_sr_clip_modes, true ) ) {
		return $attr;
	}
	$o = ( isset( $cr[ $mode ] ) && is_array( $cr[ $mode ] ) ) ? $cr[ $mode ] : array();

	$cls           = isset( $attr['class'] ) ? trim( (string) $attr['class'] ) : '';
	$attr['class'] = esc_attr( trim( $cls . ' sc-clip-reveal sc-clip--' . $mode ) );

	$dur   = isset( $o['duration'] ) ? (float) $o['duration'] : 0.7;
	$delay = isset( $o['delay'] ) ? (float) $o['delay'] : 0;
	$ease  = isset( $o['easing'] ) ? (string) $o['easing'] : 'cubic-bezier(0.22, 1, 0.36, 1)';

	$styles = array();
	$styles[] = '--cr-dur: ' . rtrim( rtrim( number_format( $dur, 2, '.', '' ), '0' ), '.' ) . 's';
	if ( $delay > 0 ) {
		$styles[] = '--cr-delay: ' . rtrim( rtrim( number_format( $delay, 2, '.', '' ), '0' ), '.' ) . 's';
	}
	if ( $ease && preg_match( '/^[a-zA-Z0-9\.,\-\(\)\s]+$/', $ease ) ) {
		$styles[] = '--cr-ease: ' . $ease;
	}
	$existing_style = isset( $attr['style'] ) ? trim( (string) $attr['style'] ) : '';
	$css            = implode( '; ', $styles ) . ';';
	$attr['style']  = esc_attr( $existing_style === '' ? $css : rtrim( $existing_style, '; ' ) . '; ' . $css );

	if ( isset( $o['replay'] ) && $o['replay'] === 'yes' ) {
		$attr['data-cr-replay'] = '1';
	}

	upw_scroll_reveal_flag( true );
	// On-demand assets: record this direction so ONLY its clip CSS partial loads (+ the shared
	// base + the single scroll runtime). The runtime itself is one generic behavior.
	if ( function_exists( 'upw_anim_use_asset' ) ) {
		upw_anim_use_asset( 'scroll-reveal', $mode );
	}
	return $attr;
}, 22, 2 );

/* 2b) Force a wrapper when an element's ONLY non-default setting is a scroll reveal. */
add_filter( 'sc_needs_wrapper', function ( $needs, $atts ) use ( $upw_sr_all_modes ) {
	if ( $needs || ! upw_scroll_reveal_enabled() ) {
		return $needs;
	}
	$cr   = ( isset( $atts['scroll_reveal'] ) && is_array( $atts['scroll_reveal'] ) ) ? $atts['scroll_reveal'] : array();
	$mode = isset( $cr['mode'] ) ? (string) $cr['mode'] : 'none';
	return in_array( $mode, $upw_sr_all_modes, true );
}, 10, 2 );

/* 3) On-demand assets — register with the shared loader. A page ships the shared base CSS + the
 *    single clip runtime + ONLY the used styles' partials: each clip direction's CSS, and (for
 *    Pixelate) its own CSS + JS (static/{css,js}/effects/pixelate.*), loaded only when used. */
if ( function_exists( 'upw_anim_register_assets' ) ) {
	$upw_sr_ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( $upw_sr_ext ) {
		upw_anim_register_assets( 'scroll-reveal', array(
			'path'      => UPW_SCROLL_REVEAL_DIR,
			'uri'       => $upw_sr_ext->get_declared_URI( '/modules/scroll-reveal' ),
			'ver'       => $upw_sr_ext->manifest->get_version(),
			'css_dir'   => 'static/css/effects',
			'js_dir'    => 'static/js/effects',
			'base_css'  => 'static/css/base.css',
			'base_js'   => 'static/js/scroll-reveal.js', // clip runtime; Pixelate ships its own JS partial
			// Directions have no JS partial (the loader only enqueues a per-style file that EXISTS);
			// 'pixelate' DOES have static/js/effects/pixelate.js, so it loads only when used.
			'js_styles' => array( 'left', 'right', 'up', 'down', 'iris', 'diagonal', 'pixelate' ),
			'js_cfg'    => function () {
				$cfg = array(
					'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
				);
				return 'window.upwScrollRevealCfg=' . wp_json_encode( $cfg ) . ';';
			},
		) );
	}
	unset( $upw_sr_ext );
}
