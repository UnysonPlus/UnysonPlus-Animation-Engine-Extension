<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — on-demand, per-style asset loader.
 *
 * The engine's anti-bloat contract: a style's CSS/JS ships ONLY on a page that
 * actually uses that style — never a per-module bundle. This scales to hundreds
 * of styles/modules without any page paying for effects it doesn't use.
 *
 * How a module opts in (three small calls — see hover.php for the canonical use):
 *
 *   1. At load time, declare the module's asset layout ONCE:
 *        upw_anim_register_assets( 'hover', array(
 *            'path'      => __DIR__,                 // module folder (absolute)
 *            'uri'       => $ext->get_declared_URI( '/modules/hover' ),
 *            'ver'       => $ext->manifest->get_version(),
 *            'css_dir'   => 'static/css/effects',    // per-style CSS lives here as <style>.css
 *            'js_dir'    => 'static/js/effects',     // per-style JS  lives here as <style>.js
 *            'base_css'  => '',                      // optional shared CSS (relative), '' = none
 *            'base_js'   => 'static/js/hover-core.js',// optional shared JS scaffolding loaded first
 *            'js_styles' => array( 'magnetic', 'tilt', … ), // ONLY these styles have a JS partial
 *            'js_cfg'    => function () { return 'window.upwHoverCfg=' . wp_json_encode( … ) . ';'; },
 *        ) );
 *
 *   2. In the wrapper filter, as each style is emitted onto an element, record it:
 *        upw_anim_use_asset( 'hover', 'jelly' );
 *
 *   3. Nothing else — this file's single wp_footer pass enqueues exactly the used
 *      styles' partials (+ base, + core JS only when a JS-backed style is used).
 *
 * A page that used no styles from any module enqueues nothing. Per-style files are
 * independently browser-cacheable; the asset-optimizer extension can concatenate
 * them for sites that prefer fewer requests.
 */

if ( ! function_exists( 'upw_anim_asset_registry' ) ) :
	/** Shared by-reference registry: ['modules'=>[…defs…], 'used'=>[module=>[style=>true]]]. */
	function &upw_anim_asset_registry() {
		static $r = array( 'modules' => array(), 'used' => array() );
		return $r;
	}
endif;

if ( ! function_exists( 'upw_anim_register_assets' ) ) :
	/** Declare a module's on-demand asset layout (call once, at module load). */
	function upw_anim_register_assets( $module, $args ) {
		$r =& upw_anim_asset_registry();
		$r['modules'][ $module ] = array_merge( array(
			'path'      => '',
			'uri'       => '',
			'ver'       => '1.0.0',
			'css_dir'   => 'static/css/effects',
			'js_dir'    => 'static/js/effects',
			'base_css'  => '',
			'base_js'   => '',
			'js_styles' => array(),
			'js_cfg'    => null,   // callable() => string of inline JS, printed before the core script
			'js_deps'   => array(),
			'js_core_first' => false, // true: core loads BEFORE partials (partials depend on it) —
			                          // for modules whose per-style partials alias shared helpers the
			                          // core defines at load time (e.g. backgrounds' canvas engine).
			                          // false (default): partials load first, core depends on them
			                          // (core runs the dispatch last — the hover model).
			'handle'    => 'upw-' . $module,
		), (array) $args );
	}
endif;

if ( ! function_exists( 'upw_anim_use_asset' ) ) :
	/** Record that $module used $style on this request → its partial(s) get enqueued. */
	function upw_anim_use_asset( $module, $style ) {
		if ( ! $module || ! $style ) {
			return;
		}
		$r =& upw_anim_asset_registry();
		$r['used'][ $module ][ $style ] = true;
	}
endif;

if ( ! function_exists( 'upw_anim_used_styles' ) ) :
	/** The distinct styles a module emitted this request (for tests / conditionals). */
	function upw_anim_used_styles( $module ) {
		$r =& upw_anim_asset_registry();
		return isset( $r['used'][ $module ] ) ? array_keys( $r['used'][ $module ] ) : array();
	}
endif;

if ( ! function_exists( 'upw_anim_asset_ver' ) ) :
	/** Version + filemtime cache-buster for a file that exists (else the bare version). */
	function upw_anim_asset_ver( $ver, $abs ) {
		return ( $abs && file_exists( $abs ) ) ? $ver . '.' . filemtime( $abs ) : $ver;
	}
endif;

/**
 * The single footer pass: enqueue exactly the used styles' partials for every module.
 */
add_action( 'wp_footer', function () {
	$r =& upw_anim_asset_registry();
	if ( empty( $r['used'] ) ) {
		return;
	}

	foreach ( $r['used'] as $module => $styles_map ) {
		if ( empty( $r['modules'][ $module ] ) ) {
			continue;
		}
		$m      = $r['modules'][ $module ];
		$styles = array_keys( $styles_map );
		$path   = rtrim( (string) $m['path'], '/\\' );
		$uri    = rtrim( (string) $m['uri'], '/' );
		$h      = $m['handle'];

		/* ---- CSS: optional shared base, then one partial per used style ---- */
		if ( $m['base_css'] ) {
			$abs = $path . '/' . $m['base_css'];
			if ( file_exists( $abs ) ) {
				wp_enqueue_style( $h . '-base', $uri . '/' . $m['base_css'], array(), upw_anim_asset_ver( $m['ver'], $abs ) );
			}
		}
		foreach ( $styles as $s ) {
			$rel = $m['css_dir'] . '/' . $s . '.css';
			$abs = $path . '/' . $rel;
			if ( file_exists( $abs ) ) {
				wp_enqueue_style( $h . '-' . $s, $uri . '/' . $rel, array(), upw_anim_asset_ver( $m['ver'], $abs ) );
			}
		}

		/* ---- JS: only styles that actually ship a JS partial ---- */
		$js_used = array_values( array_intersect( $styles, (array) $m['js_styles'] ) );
		if ( empty( $js_used ) ) {
			continue; // CSS-only page for this module → NO JavaScript at all.
		}

		$core_handle = $h . '-core';
		$has_core    = $m['base_js'] && file_exists( $path . '/' . $m['base_js'] );

		if ( $m['js_core_first'] && $has_core ) {
			// Core FIRST: it defines the shared helpers/registry; each per-style partial then
			// loads after it (depends on it) and aliases those helpers at load time.
			wp_enqueue_script( $core_handle, $uri . '/' . $m['base_js'], (array) $m['js_deps'], upw_anim_asset_ver( $m['ver'], $path . '/' . $m['base_js'] ), true );
			if ( is_callable( $m['js_cfg'] ) ) {
				$cfg = call_user_func( $m['js_cfg'] );
				if ( is_string( $cfg ) && $cfg !== '' ) {
					wp_add_inline_script( $core_handle, $cfg, 'before' );
				}
			}
			foreach ( $js_used as $s ) {
				$rel = $m['js_dir'] . '/' . $s . '.js';
				$abs = $path . '/' . $rel;
				if ( file_exists( $abs ) ) {
					wp_enqueue_script( $h . '-js-' . $s, $uri . '/' . $rel, array( $core_handle ), upw_anim_asset_ver( $m['ver'], $abs ), true );
				}
			}
			continue;
		}

		// Default (hover model): partials first (they register into the runtime registry),
		// then the core dispatcher last (depends on them, so it can init immediately).
		$eff_handles = array();
		foreach ( $js_used as $s ) {
			$rel = $m['js_dir'] . '/' . $s . '.js';
			$abs = $path . '/' . $rel;
			if ( file_exists( $abs ) ) {
				$eh = $h . '-js-' . $s;
				wp_enqueue_script( $eh, $uri . '/' . $rel, (array) $m['js_deps'], upw_anim_asset_ver( $m['ver'], $abs ), true );
				$eff_handles[] = $eh;
			}
		}
		if ( $has_core ) {
			wp_enqueue_script( $core_handle, $uri . '/' . $m['base_js'], array_merge( (array) $m['js_deps'], $eff_handles ), upw_anim_asset_ver( $m['ver'], $path . '/' . $m['base_js'] ), true );
			if ( is_callable( $m['js_cfg'] ) ) {
				$cfg = call_user_func( $m['js_cfg'] );
				if ( is_string( $cfg ) && $cfg !== '' ) {
					wp_add_inline_script( $core_handle, $cfg, 'before' );
				}
			}
		}
	}
}, 5 );
