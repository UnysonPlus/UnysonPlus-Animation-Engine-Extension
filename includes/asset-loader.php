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
			'js_shared' => array(),// [ 'chunk' => ['styleA','styleB',…] ]: a shared sub-engine used by
			                       // ONLY some styles. The chunk file lives at js_dir/_<chunk>.js and is
			                       // enqueued only when one of its styles is used; each such style's
			                       // partial depends on it. Keeps rarely-used engines out of the core.
			'js_cfg'    => null,   // callable() => string of inline JS, printed before the core script
			'js_deps'   => array(),
			'js_core_first' => false, // true: core loads BEFORE partials (partials depend on it) —
			                          // for modules whose per-style partials alias shared helpers the
			                          // core defines at load time (e.g. backgrounds' canvas engine).
			                          // false (default): partials load first, core depends on them
			                          // (core runs the dispatch last — the hover model).
			'handle'    => 'upw-' . $module,
			'needs_raf' => false,  // true: this module's JS uses the shared frame scheduler
			                       // (window.upwAnimRaf) — the loader adds upw-anim-raf as a dep.
		), (array) $args );
	}
endif;

if ( ! function_exists( 'upw_anim_raf_handle' ) ) :
	/**
	 * Register + enqueue the shared frame scheduler (static/js/upw-raf.js) once per request and
	 * return its handle, so modules whose JS uses window.upwAnimRaf can depend on it. One rAF loop
	 * drives every subscribed animation and pauses while the tab is hidden.
	 */
	function upw_anim_raf_handle() {
		$h   = 'upw-anim-raf';
		$ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
		if ( ! $ext ) {
			return $h;
		}
		if ( ! wp_script_is( $h, 'registered' ) ) {
			$abs = $ext->get_declared_path( '/static/js/upw-raf.js' );
			$ver = $ext->manifest->get_version();
			wp_register_script( $h, $ext->get_declared_URI( '/static/js/upw-raf.js' ), array(), upw_anim_asset_ver( $ver, $abs ), true );
		}
		wp_enqueue_script( $h );
		return $h;
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
 * Keep the asset-optimizer's SITE-WIDE CSS combiner from absorbing our on-demand
 * per-style partials (and our own per-page combined output). Its site-wide bundle
 * is built from every handle ever discovered, so absorbing these would ship every
 * style on every page - exactly the bloat the on-demand loader exists to avoid.
 * We fold our partials PER-PAGE ourselves (in the footer pass below), so the
 * generic combiner must leave every `upw-<module>-…` handle alone. (JS needs no
 * such guard: the optimizer's JS pass runs at wp_enqueue_scripts:99999, long
 * before this footer:5 enqueue, so it never sees our scripts.)
 */
add_filter( 'fw:ext:asset-optimizer:css_exclude_handles', function ( $excluded, $known ) {
	$r =& upw_anim_asset_registry();
	if ( empty( $r['modules'] ) || ! is_array( $known ) ) {
		return $excluded;
	}
	$prefixes = array();
	foreach ( $r['modules'] as $m ) {
		if ( ! empty( $m['handle'] ) ) {
			$prefixes[] = $m['handle'] . '-';
		}
	}
	foreach ( array_keys( $known ) as $handle ) {
		foreach ( $prefixes as $p ) {
			if ( strpos( $handle, $p ) === 0 ) {
				$excluded[] = $handle;
				break;
			}
		}
	}
	return $excluded;
}, 10, 2 );

/**
 * The single footer pass: enqueue exactly the used styles' partials for every
 * module. When the asset-optimizer is active AND combining is enabled (honoring
 * its master switches / logged-out-only / URL exclusions), each module's used
 * partials are folded into ONE combined CSS + ONE combined JS (cache-keyed by the
 * used-style set, so pages with different styles still get correct files, and only
 * the page's used styles are ever included). Otherwise the partials load
 * individually, exactly as before. Load order, the inline cfg and the shared rAF
 * dependency are preserved either way.
 */
add_action( 'wp_footer', function () {
	$r =& upw_anim_asset_registry();
	if ( empty( $r['used'] ) ) {
		return;
	}

	// Ask the asset-optimizer (if active) whether to fold our per-page partials.
	$ao          = function_exists( 'fw' ) ? fw()->extensions->get( 'asset-optimizer' ) : null;
	$can_combine = $ao && method_exists( $ao, 'is_combine_enabled' ) && method_exists( $ao, 'combine_files' );
	$combine_css = $can_combine && $ao->is_combine_enabled( 'css' );
	$combine_js  = $can_combine && $ao->is_combine_enabled( 'js' );

	foreach ( $r['used'] as $module => $styles_map ) {
		if ( empty( $r['modules'][ $module ] ) ) {
			continue;
		}
		$m      = $r['modules'][ $module ];
		$styles = array_keys( $styles_map );
		$path   = rtrim( (string) $m['path'], '/\\' );
		$uri    = rtrim( (string) $m['uri'], '/' );
		$h      = $m['handle'];

		/* ---- CSS file list: optional shared base, then one partial per used style ---- */
		$css_files = array();
		if ( $m['base_css'] ) {
			$abs = $path . '/' . $m['base_css'];
			if ( file_exists( $abs ) ) {
				$css_files[] = array( 'handle' => $h . '-base', 'abs' => $abs, 'src' => $uri . '/' . $m['base_css'], 'ver' => upw_anim_asset_ver( $m['ver'], $abs ) );
			}
		}
		foreach ( $styles as $s ) {
			$rel = $m['css_dir'] . '/' . $s . '.css';
			$abs = $path . '/' . $rel;
			if ( file_exists( $abs ) ) {
				$css_files[] = array( 'handle' => $h . '-' . $s, 'abs' => $abs, 'src' => $uri . '/' . $rel, 'ver' => upw_anim_asset_ver( $m['ver'], $abs ) );
			}
		}

		if ( $combine_css && count( $css_files ) >= 2 && ( $url = $ao->combine_files( $css_files, 'css' ) ) ) {
			wp_enqueue_style( $h . '-combined', $url, array(), null );
		} else {
			foreach ( $css_files as $f ) {
				wp_enqueue_style( $f['handle'], $f['src'], array(), $f['ver'] );
			}
		}

		/* ---- JS: only styles that actually ship a JS partial ---- */
		$js_used = array_values( array_intersect( $styles, (array) $m['js_styles'] ) );
		if ( empty( $js_used ) ) {
			continue; // CSS-only page for this module → NO JavaScript at all.
		}

		// Modules whose JS uses the shared frame scheduler depend on it (loads once per page).
		$base_deps = (array) $m['js_deps'];
		if ( $m['needs_raf'] ) {
			$base_deps[] = upw_anim_raf_handle();
		}

		$core_handle = $h . '-core';
		$has_core    = $m['base_js'] && file_exists( $path . '/' . $m['base_js'] );
		$core_abs    = $has_core ? $path . '/' . $m['base_js'] : '';
		$core_src    = $has_core ? $uri . '/' . $m['base_js'] : '';
		$cfg         = is_callable( $m['js_cfg'] ) ? call_user_func( $m['js_cfg'] ) : '';
		if ( ! is_string( $cfg ) ) {
			$cfg = '';
		}

		// Shared JS chunks (core_first only): a sub-engine (js_dir/_<chunk>.js) used by ONLY some
		// styles loads when one of its styles is used; each such style's partial depends on it, and
		// it sits between core and the partials (it aliases core's API; the partials alias it).
		$chunk_files = array();          // ordered rows: handle/abs/src
		$style_chunks = array();         // style => [chunk handles]
		if ( $m['js_core_first'] && $has_core && ! empty( $m['js_shared'] ) ) {
			foreach ( (array) $m['js_shared'] as $chunk => $chunk_styles ) {
				$needed = array_intersect( $js_used, (array) $chunk_styles );
				if ( empty( $needed ) ) {
					continue;
				}
				$rel = $m['js_dir'] . '/_' . $chunk . '.js';
				$abs = $path . '/' . $rel;
				if ( ! file_exists( $abs ) ) {
					continue;
				}
				$ch_handle     = $h . '-chunk-' . $chunk;
				$chunk_files[] = array( 'handle' => $ch_handle, 'abs' => $abs, 'src' => $uri . '/' . $rel );
				foreach ( $needed as $s ) {
					$style_chunks[ $s ][] = $ch_handle;
				}
			}
		}

		// Ordered JS file list, matching the separate-enqueue order exactly:
		//   core_first: [core, …chunks, …partials]   |   default: […partials, core]
		$js_files = array();
		if ( $m['js_core_first'] && $has_core ) {
			$js_files[] = array( 'handle' => $core_handle, 'abs' => $core_abs, 'src' => $core_src );
			foreach ( $chunk_files as $cf ) {
				$js_files[] = $cf;
			}
			foreach ( $js_used as $s ) {
				$abs = $path . '/' . $m['js_dir'] . '/' . $s . '.js';
				if ( file_exists( $abs ) ) {
					$js_files[] = array( 'handle' => $h . '-js-' . $s, 'abs' => $abs, 'src' => $uri . '/' . $m['js_dir'] . '/' . $s . '.js' );
				}
			}
		} else {
			foreach ( $js_used as $s ) {
				$abs = $path . '/' . $m['js_dir'] . '/' . $s . '.js';
				if ( file_exists( $abs ) ) {
					$js_files[] = array( 'handle' => $h . '-js-' . $s, 'abs' => $abs, 'src' => $uri . '/' . $m['js_dir'] . '/' . $s . '.js' );
				}
			}
			if ( $has_core ) {
				$js_files[] = array( 'handle' => $core_handle, 'abs' => $core_abs, 'src' => $core_src );
			}
		}

		if ( $combine_js && count( $js_files ) >= 2 && ( $url = $ao->combine_files( $js_files, 'js' ) ) ) {
			// One combined script; the inline cfg rides on it (kept inline, never cached).
			wp_enqueue_script( $h . '-js-combined', $url, $base_deps, null, true );
			if ( $cfg !== '' ) {
				wp_add_inline_script( $h . '-js-combined', $cfg, 'before' );
			}
		} elseif ( $m['js_core_first'] && $has_core ) {
			// Separate — Core FIRST: it defines the shared helpers/registry; shared chunks load next
			// (depend on core); each per-style partial then loads after them and aliases those helpers.
			wp_enqueue_script( $core_handle, $core_src, $base_deps, upw_anim_asset_ver( $m['ver'], $core_abs ), true );
			if ( $cfg !== '' ) {
				wp_add_inline_script( $core_handle, $cfg, 'before' );
			}
			foreach ( $chunk_files as $cf ) {
				wp_enqueue_script( $cf['handle'], $cf['src'], array( $core_handle ), upw_anim_asset_ver( $m['ver'], $cf['abs'] ), true );
			}
			foreach ( $js_used as $s ) {
				$rel = $m['js_dir'] . '/' . $s . '.js';
				$abs = $path . '/' . $rel;
				if ( file_exists( $abs ) ) {
					$deps = array_merge( array( $core_handle ), isset( $style_chunks[ $s ] ) ? $style_chunks[ $s ] : array() );
					wp_enqueue_script( $h . '-js-' . $s, $uri . '/' . $rel, $deps, upw_anim_asset_ver( $m['ver'], $abs ), true );
				}
			}
		} else {
			// Separate — Default (hover model): partials first (they register into the runtime
			// registry), then the core dispatcher last (depends on them, so it inits immediately).
			$eff_handles = array();
			foreach ( $js_used as $s ) {
				$rel = $m['js_dir'] . '/' . $s . '.js';
				$abs = $path . '/' . $rel;
				if ( file_exists( $abs ) ) {
					$eh = $h . '-js-' . $s;
					wp_enqueue_script( $eh, $uri . '/' . $rel, $base_deps, upw_anim_asset_ver( $m['ver'], $abs ), true );
					$eff_handles[] = $eh;
				}
			}
			if ( $has_core ) {
				wp_enqueue_script( $core_handle, $core_src, array_merge( $base_deps, $eff_handles ), upw_anim_asset_ver( $m['ver'], $core_abs ), true );
			}
			if ( $cfg !== '' ) {
				// Attach the module config to the FIRST-loading handle. In this branch the partials
				// load BEFORE the core, so a partial that reads window.upw*Cfg at load-time must see the
				// inline printed first (attaching it to the core — which loads last — would give it {}).
				$cfg_handle = ! empty( $eff_handles ) ? $eff_handles[0] : ( $has_core ? $core_handle : '' );
				if ( $cfg_handle !== '' ) {
					wp_add_inline_script( $cfg_handle, $cfg, 'before' );
				}
			}
		}
	}
}, 5 );
