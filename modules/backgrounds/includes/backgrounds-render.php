<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Animated Backgrounds module: runtime.
 *
 * Emits the chosen effect onto the container wrapper (via `sc_build_wrapper_attr`) and registers
 * the module's per-style partial layout with the shared on-demand asset loader. Depends on the
 * registries/helpers in backgrounds-helpers.php.
 *
 * NOTE: uses UPW_BACKGROUNDS_DIR (defined in backgrounds.php) — NOT __DIR__ — for the loader's
 * static asset path, because this file lives in includes/ but the static assets are at the
 * module root.
 */

/* ------------------------------------------------------------------ *
 * 2) Emit the chosen effect onto the container wrapper.
 * ------------------------------------------------------------------ */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	if ( ! upw_bg_enabled() ) {
		return $attr;
	}
	$bg     = ( isset( $atts['bg_effect'] ) && is_array( $atts['bg_effect'] ) ) ? $atts['bg_effect'] : array();
	$effect = isset( $bg['effect'] ) ? (string) $bg['effect'] : 'none';
	if ( ! in_array( $effect, upw_bg_effects(), true ) ) {
		return $attr;
	}
	$o = ( isset( $bg[ $effect ] ) && is_array( $bg[ $effect ] ) ) ? $bg[ $effect ] : array();

	$cls           = isset( $attr['class'] ) ? trim( (string) $attr['class'] ) : '';
	$attr['class'] = esc_attr( trim( $cls . ' sc-bg sc-bg--' . sanitize_html_class( $effect ) ) );
	$attr['data-bg'] = esc_attr( $effect );

	// On-demand assets: record this style so ONLY its JS partial (+ CSS partial for the
	// CSS-only styles) is enqueued, not the whole 35-style bundle.
	if ( function_exists( 'upw_anim_use_asset' ) ) {
		upw_anim_use_asset( 'backgrounds', $effect );
	}

	$add_style = function ( $css ) use ( &$attr ) {
		$existing      = isset( $attr['style'] ) ? rtrim( (string) $attr['style'], '; ' ) . '; ' : '';
		$attr['style'] = esc_attr( $existing . $css );
	};

	switch ( $effect ) {
		case 'aurora':
			$add_style( '--bg-c1:' . upw_bg_css_color( $o['color_a'] ?? '', '#6a8dff' )
				. '; --bg-c2:' . upw_bg_css_color( $o['color_b'] ?? '', '#c56cff' )
				. '; --bg-c3:' . upw_bg_css_color( $o['color_c'] ?? '', '#00d4c8' )
				. '; --bg-speed:' . (float) ( $o['speed'] ?? 8 ) . 's;' );
			break;
		case 'gradient':
			$add_style( '--bg-c1:' . upw_bg_css_color( $o['color_a'] ?? '', '#2f74e6' )
				. '; --bg-c2:' . upw_bg_css_color( $o['color_b'] ?? '', '#7a3cff' )
				. '; --bg-c3:' . upw_bg_css_color( $o['color_c'] ?? '', '#00b2b2' )
				. '; --bg-angle:' . (int) ( $o['angle'] ?? 120 ) . 'deg'
				. '; --bg-speed:' . (float) ( $o['speed'] ?? 10 ) . 's;' );
			break;
		case 'dots':
			$add_style( '--bg-color:' . upw_bg_css_color( $o['color'] ?? '', '#94a3b8' )
				. '; --bg-dot:' . (int) ( $o['size'] ?? 2 ) . 'px; --bg-gap:' . (int) ( $o['gap'] ?? 26 ) . 'px;' );
			break;
		case 'particles':
			$attr['data-bg-color']   = esc_attr( upw_bg_hex( $o['color'] ?? '', '#6aa6ff' ) );
			$attr['data-bg-density'] = esc_attr( (int) ( $o['density'] ?? 60 ) );
			$attr['data-bg-speed']   = esc_attr( (float) ( $o['speed'] ?? 3 ) );
			break;
		case 'constellation':
			$attr['data-bg-color']   = esc_attr( upw_bg_hex( $o['color'] ?? '', '#6aa6ff' ) );
			$attr['data-bg-density'] = esc_attr( (int) ( $o['density'] ?? 55 ) );
			$attr['data-bg-link']    = esc_attr( (int) ( $o['link_dist'] ?? 120 ) );
			break;
		case 'waves':
			$attr['data-bg-color'] = esc_attr( upw_bg_hex( $o['color'] ?? '', '#2f74e6' ) );
			$attr['data-bg-amp']   = esc_attr( (int) ( $o['amplitude'] ?? 30 ) );
			$attr['data-bg-speed'] = esc_attr( (float) ( $o['speed'] ?? 6 ) );
			break;
		case 'starfield':
			$attr['data-bg-color']   = esc_attr( upw_bg_hex( $o['color'] ?? '', '#ffffff' ) );
			$attr['data-bg-density'] = esc_attr( (int) ( $o['density'] ?? 120 ) );
			$attr['data-bg-speed']   = esc_attr( (float) ( $o['speed'] ?? 4 ) );
			break;
		case 'noise':
			$attr['data-bg-opacity'] = esc_attr( (float) ( $o['opacity'] ?? 0.06 ) );
			$attr['data-bg-speed']   = esc_attr( (float) ( $o['speed'] ?? 1 ) );
			break;

		case 'mesh':
			$add_style( '--bg-c1:' . upw_bg_css_color( $o['color_a'] ?? '', '#6a8dff' )
				. '; --bg-c2:' . upw_bg_css_color( $o['color_b'] ?? '', '#ff6ac1' )
				. '; --bg-c3:' . upw_bg_css_color( $o['color_c'] ?? '', '#ffd36a' )
				. '; --bg-c4:' . upw_bg_css_color( $o['color_d'] ?? '', '#00d4c8' )
				. '; --bg-speed:' . (float) ( $o['speed'] ?? 12 ) . 's;' );
			break;
		case 'grid':
			$add_style( '--bg-color:' . upw_bg_css_color( $o['color'] ?? '', '#94a3b8' )
				. '; --bg-gap:' . (int) ( $o['gap'] ?? 40 ) . 'px; --bg-speed:' . (float) ( $o['speed'] ?? 12 ) . 's;' );
			break;
		case 'orbs':
			$add_style( '--bg-c1:' . upw_bg_css_color( $o['color_a'] ?? '', '#6a8dff' )
				. '; --bg-c2:' . upw_bg_css_color( $o['color_b'] ?? '', '#c56cff' )
				. '; --bg-speed:' . (float) ( $o['speed'] ?? 10 ) . 's;' );
			break;
		case 'conic':
			$add_style( '--bg-c1:' . upw_bg_css_color( $o['color_a'] ?? '', '#2f74e6' )
				. '; --bg-c2:' . upw_bg_css_color( $o['color_b'] ?? '', '#7a3cff' )
				. '; --bg-c3:' . upw_bg_css_color( $o['color_c'] ?? '', '#00b2b2' )
				. '; --bg-speed:' . (float) ( $o['speed'] ?? 12 ) . 's;' );
			break;
		case 'scanlines':
			$add_style( '--bg-color:' . upw_bg_css_color( $o['color'] ?? '', '#000000' )
				. '; --bg-opacity:' . (float) ( $o['opacity'] ?? 0.12 ) . '; --bg-speed:' . (float) ( $o['speed'] ?? 6 ) . 's;' );
			break;
		case 'rays':
			$add_style( '--bg-color:' . upw_bg_css_color( $o['color'] ?? '', '#ffffff' )
				. '; --bg-angle:' . (int) ( $o['angle'] ?? 25 ) . 'deg; --bg-speed:' . (float) ( $o['speed'] ?? 10 ) . 's;' );
			break;

		case 'snow':
			$attr['data-bg-variant'] = esc_attr( in_array( ( $o['variant'] ?? 'snow' ), array( 'snow', 'petals', 'embers', 'ash' ), true ) ? $o['variant'] : 'snow' );
			$attr['data-bg-density'] = esc_attr( (int) ( $o['density'] ?? 70 ) );
			$attr['data-bg-speed']   = esc_attr( (float) ( $o['speed'] ?? 3 ) );
			break;
		case 'confetti':
			$attr['data-bg-density'] = esc_attr( (int) ( $o['density'] ?? 60 ) );
			$attr['data-bg-speed']   = esc_attr( (float) ( $o['speed'] ?? 3 ) );
			break;
		case 'bubbles':
		case 'fireflies':
		case 'bokeh':
		case 'rain':
		case 'shapes':
		case 'meteors':
			$defc = ( $effect === 'fireflies' ) ? '#ffd36a' : ( ( $effect === 'meteors' ) ? '#ffffff' : '#6aa6ff' );
			$attr['data-bg-color']   = esc_attr( upw_bg_hex( $o['color'] ?? '', $defc ) );
			$attr['data-bg-density'] = esc_attr( (int) ( $o['density'] ?? 40 ) );
			$attr['data-bg-speed']   = esc_attr( (float) ( $o['speed'] ?? 3 ) );
			break;

		case 'pgrid':
		case 'hexgrid':
		case 'topo':
		case 'circuit':
		case 'ripple':
		case 'matrix':
			$dc = ( $effect === 'pgrid' ) ? '#ff6ac1' : ( ( $effect === 'circuit' ) ? '#00e5a0' : ( ( $effect === 'matrix' ) ? '#19ff7a' : '#6aa6ff' ) );
			$attr['data-bg-color'] = esc_attr( upw_bg_hex( $o['color'] ?? '', $dc ) );
			$attr['data-bg-speed'] = esc_attr( (float) ( $o['speed'] ?? 6 ) );
			break;
		case 'halftone':
			$attr['data-bg-color'] = esc_attr( upw_bg_hex( $o['color'] ?? '', '#6aa6ff' ) );
			$attr['data-bg-gap']   = esc_attr( (int) ( $o['gap'] ?? 16 ) );
			$attr['data-bg-speed'] = esc_attr( (float) ( $o['speed'] ?? 6 ) );
			break;
		case 'flow':
			$attr['data-bg-color']   = esc_attr( upw_bg_hex( $o['color'] ?? '', '#6aa6ff' ) );
			$attr['data-bg-density'] = esc_attr( (int) ( $o['density'] ?? 70 ) );
			$attr['data-bg-speed']   = esc_attr( (float) ( $o['speed'] ?? 6 ) );
			break;
		case 'blobs':
		case 'borealis':
			$d1 = ( $effect === 'borealis' ) ? '#3bffb0' : '#6a8dff';
			$d2 = ( $effect === 'borealis' ) ? '#6a8dff' : '#c56cff';
			$attr['data-bg-color']  = esc_attr( upw_bg_hex( $o['color'] ?? '', $d1 ) );
			$attr['data-bg-color2'] = esc_attr( upw_bg_hex( $o['color2'] ?? '', $d2 ) );
			$attr['data-bg-speed']  = esc_attr( (float) ( $o['speed'] ?? 6 ) );
			break;
		case 'nebula':
			$attr['data-bg-color']  = esc_attr( upw_bg_hex( $o['color'] ?? '', '#3b3fff' ) );
			$attr['data-bg-color2'] = esc_attr( upw_bg_hex( $o['color2'] ?? '', '#c56cff' ) );
			$attr['data-bg-color3'] = esc_attr( upw_bg_hex( $o['color3'] ?? '', '#00d4c8' ) );
			$attr['data-bg-speed']  = esc_attr( (float) ( $o['speed'] ?? 8 ) );
			break;
		case 'orbits':
			$attr['data-bg-color']   = esc_attr( upw_bg_hex( $o['color'] ?? '', '#6aa6ff' ) );
			$attr['data-bg-density'] = esc_attr( (int) ( $o['density'] ?? 4 ) );
			$attr['data-bg-speed']   = esc_attr( (float) ( $o['speed'] ?? 6 ) );
			break;
		case 'spotlight':
			$attr['data-bg-color'] = esc_attr( upw_bg_hex( $o['color'] ?? '', '#6aa6ff' ) );
			$attr['data-bg-size']  = esc_attr( (int) ( $o['size'] ?? 260 ) );
			break;
	}

	return $attr;
}, 23, 2 );

/* ------------------------------------------------------------------ *
 * 3) On-demand assets. Register the module's per-style partial layout with the shared
 *    loader; a page ships ONLY the shared core (canvas engine) + the used styles'
 *    partials — recorded per element in the wrapper filter via upw_anim_use_asset().
 *    js_core_first: the core defines the shared engine that each style partial aliases.
 * ------------------------------------------------------------------ */
if ( function_exists( 'upw_anim_register_assets' ) ) {
	$upw_bg_ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( $upw_bg_ext ) {
		upw_anim_register_assets( 'backgrounds', array(
			'path'          => UPW_BACKGROUNDS_DIR,
			'uri'           => $upw_bg_ext->get_declared_URI( '/modules/backgrounds' ),
			'ver'           => $upw_bg_ext->manifest->get_version(),
			'css_dir'       => 'static/css/effects',   // CSS-only styles have a partial here; canvas styles have none
			'js_dir'        => 'static/js/effects',
			'base_css'      => 'static/css/base.css',   // the injected layer + canvas, always needed
			'base_js'       => 'static/js/backgrounds-core.js',
			'needs_raf'     => true,                    // uses the shared frame scheduler (window.upwAnimRaf)
			'js_core_first' => true,                    // core (engine) loads before the style partials
			'js_styles'     => upw_bg_effects(),        // every style ships a JS partial (dispatch injects the layer)
			// Shared canvas sub-engines kept OUT of the core — loaded only when a style that needs
			// them is on the page (the particle field: 8 styles; the metaball blob: 2 styles).
			'js_shared'     => array(
				'field' => array( 'snow', 'confetti', 'bubbles', 'fireflies', 'bokeh', 'rain', 'shapes', 'meteors' ),
				'blob'  => array( 'blobs', 'nebula' ),
			),
			'js_cfg'        => function () {
				$cfg = array(
					'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
					'disableMobile' => ( function_exists( 'upw_anim_engine_setting' ) && upw_anim_engine_setting( 'disable_on_mobile', 'no' ) === 'yes' ),
				);
				return 'window.upwBgCfg=' . wp_json_encode( $cfg ) . ';';
			},
		) );
	}
	unset( $upw_bg_ext );
}
