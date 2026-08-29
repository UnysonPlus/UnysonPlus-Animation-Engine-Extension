<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Animated Backgrounds module: runtime.
 *
 * Emits the chosen effect(s) onto the element wrapper (via `sc_build_wrapper_attr`) and registers
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
/**
 * Resolve ONE background effect + its options into a render config: the data-attrs the JS engine reads +
 * any CSS-var `style` string. Shared by the wrapper filter (single effect on the wrapper) and the layer
 * stack (multiple effects → one child layer each). Returns array( 'attrs' => [ 'data-bg-*' => v ], 'style' => '' ).
 */
if ( ! function_exists( 'upw_bg_effect_config' ) ) :
function upw_bg_effect_config( $effect, $o ) {
	$attr = array();
	$style = '';
	$add_style = function ( $css ) use ( &$style ) {
		$style .= ( '' !== $style ? '; ' : '' ) . rtrim( (string) $css, '; ' );
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

	return array( 'attrs' => $attr, 'style' => $style );
}
endif;

/**
 * Collect the ACTIVE background-effect slots on a container (bg_effect, bg_effect__2, bg_effect__3) → an
 * ordered list of resolved layer configs. One effect stays on the wrapper (back-compat, lighter); two or
 * more are emitted as a `data-bg-layers` JSON the JS engine expands into stacked child layers.
 */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	if ( ! upw_bg_enabled() ) {
		return $attr;
	}
	// Collect EVERY background slot present on this element (bg_effect, bg_effect__2 … __N) — no fixed
	// cap, so users can stack as many layers as they like. Ordered back-to-front by slot index.
	$slot_keys = array();
	foreach ( $atts as $k => $v ) {
		if ( 'bg_effect' === $k ) {
			$slot_keys[1] = $k;
		} elseif ( preg_match( '/^bg_effect__(\d+)$/', $k, $m ) ) {
			$slot_keys[ (int) $m[1] ] = $k;
		}
	}
	ksort( $slot_keys );

	$layers = array();
	foreach ( $slot_keys as $slot ) {
		$bg     = ( isset( $atts[ $slot ] ) && is_array( $atts[ $slot ] ) ) ? $atts[ $slot ] : array();
		$effect = isset( $bg['effect'] ) ? (string) $bg['effect'] : 'none';
		if ( 'none' === $effect || ! in_array( $effect, upw_bg_effects(), true ) ) {
			continue;
		}
		$o   = ( isset( $bg[ $effect ] ) && is_array( $bg[ $effect ] ) ) ? $bg[ $effect ] : array();
		$cfg = upw_bg_effect_config( $effect, $o );
		$layers[] = array( 'effect' => $effect, 'attrs' => $cfg['attrs'], 'style' => $cfg['style'] );
		// On-demand assets: record each used style so ONLY its partial ships (not the whole bundle).
		if ( function_exists( 'upw_anim_use_asset' ) ) {
			upw_anim_use_asset( 'backgrounds', $effect );
		}
	}
	if ( ! $layers ) {
		return $attr;
	}
	$cls = isset( $attr['class'] ) ? trim( (string) $attr['class'] ) : '';
	if ( count( $layers ) === 1 ) {
		// SINGLE effect → keep it on the wrapper itself (the JS renders the host directly; lighter markup).
		$L = $layers[0];
		$attr['class'] = esc_attr( trim( $cls . ' sc-bg sc-bg--' . sanitize_html_class( $L['effect'] ) ) );
		$attr['data-bg'] = esc_attr( $L['effect'] );
		foreach ( $L['attrs'] as $k => $v ) { $attr[ $k ] = $v; }
		if ( '' !== $L['style'] ) {
			$ex = isset( $attr['style'] ) ? rtrim( (string) $attr['style'], '; ' ) . '; ' : '';
			$attr['style'] = esc_attr( $ex . $L['style'] );
		}
	} else {
		// MULTIPLE effects → the JS expands this into one stacked child layer per effect (back-to-front).
		// NOTE: pass the JSON RAW — the wrapper printer (fw_attr_to_html → fw_htmlspecialchars) escapes
		// every attr value once. esc_attr() here would DOUBLE-encode the quotes (&amp;quot;) and break
		// JSON.parse in the browser (the effects would silently never render).
		$attr['class'] = trim( $cls . ' sc-bg sc-bg--stack' );
		$attr['data-bg-layers'] = wp_json_encode( $layers );
	}
	return $attr;
}, 23, 2 );

/**
 * Build a PAGE-WIDE background host — a full-viewport element (fixed by default, so content scrolls over a
 * still ambient scene; or absolute for a page-height parallax) carrying the same `data-bg` / `data-bg-layers`
 * the JS engine reads. Used by the theme's Page Settings → Animations tab. $slots = an ordered list of
 * multi-picker values (`[ 'effect'=>'snow', 'snow'=>[…] ]`). Records each used style so its partial ships.
 * Returns '' when nothing renders (module off / no valid effect). Escaping is done here (manual attributes),
 * so it is NOT passed through fw_attr_to_html — a single esc_attr on the JSON is correct.
 */
if ( ! function_exists( 'upw_bg_page_host' ) ) :
function upw_bg_page_host( $slots, $fixed = true ) {
	if ( ! upw_bg_enabled() || ! is_array( $slots ) ) { return ''; }
	$layers = array();
	foreach ( $slots as $bg ) {
		if ( ! is_array( $bg ) ) { continue; }
		$effect = isset( $bg['effect'] ) ? (string) $bg['effect'] : 'none';
		if ( 'none' === $effect || ! in_array( $effect, upw_bg_effects(), true ) ) { continue; }
		$o   = ( isset( $bg[ $effect ] ) && is_array( $bg[ $effect ] ) ) ? $bg[ $effect ] : array();
		$cfg = upw_bg_effect_config( $effect, $o );
		$layers[] = array( 'effect' => $effect, 'attrs' => $cfg['attrs'], 'style' => $cfg['style'] );
		if ( function_exists( 'upw_anim_use_asset' ) ) { upw_anim_use_asset( 'backgrounds', $effect ); }
	}
	if ( ! $layers ) { return ''; }
	$style = 'position:' . ( $fixed ? 'fixed' : 'absolute' ) . ';inset:0;z-index:-1;pointer-events:none;';
	if ( count( $layers ) === 1 ) {
		$L    = $layers[0];
		$cls  = 'upw-page-bg sc-bg sc-bg--' . sanitize_html_class( $L['effect'] );
		$attr = ' data-bg="' . esc_attr( $L['effect'] ) . '"';
		foreach ( $L['attrs'] as $k => $v ) { $attr .= ' ' . $k . '="' . $v . '"'; } // values already esc_attr'd in config
		if ( '' !== $L['style'] ) { $style .= $L['style']; }
		return '<div class="' . esc_attr( $cls ) . '" style="' . esc_attr( $style ) . '"' . $attr . ' aria-hidden="true"></div>';
	}
	return '<div class="upw-page-bg sc-bg sc-bg--stack" style="' . esc_attr( $style ) . '" data-bg-layers="' . esc_attr( wp_json_encode( $layers ) ) . '" aria-hidden="true"></div>';
}
endif;

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
