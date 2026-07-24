<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Scrollytelling module: runtime.
 *
 * Injects the Scrollytelling control into the SECTION's Animations tab (inside the animation-stack
 * organizer, like Sticky Card Stack / Scroll Loop), stamps the data-attributes onto the Section
 * wrapper, forces a wrapper when it's the only setting, and registers the on-demand asset layout.
 *
 * NOTE: uses UPW_SCROLLYTELLING_DIR (defined in scrollytelling.php) — NOT __DIR__ — for the
 * asset-loader path, because this file lives in includes/ while the static assets are at the
 * module root.
 */

/* 1) Inject into the SECTION's Animations tab only, inside the animation-stack organizer. */
add_filter( 'fw_shortcode_get_options', function ( $options, $tag = '' ) {
	if ( $tag !== 'section' || ! is_array( $options ) || ! function_exists( 'sc_get_scrollytelling_fields' ) || ! upw_scrollytelling_enabled() ) {
		return $options;
	}
	if ( ! isset( $options['tab_animation']['options'] ) || ! is_array( $options['tab_animation']['options'] ) ) {
		return $options;
	}
	$tab =& $options['tab_animation']['options'];
	if ( isset( $tab['animation_stack']['options'] ) && is_array( $tab['animation_stack']['options'] ) ) {
		$tab['animation_stack']['options'] = array_merge( $tab['animation_stack']['options'], sc_get_scrollytelling_fields() );
	} else {
		$tab = array_merge( $tab, sc_get_scrollytelling_fields() );
	}
	unset( $tab );
	return $options;
}, 10, 2 );

/* 2) Stamp the scrollytelling data-attributes onto the section wrapper. */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	if ( ! upw_scrollytelling_enabled() ) {
		return $attr;
	}
	$s    = ( isset( $atts['scrollytelling'] ) && is_array( $atts['scrollytelling'] ) ) ? $atts['scrollytelling'] : array();
	$mode = isset( $s['mode'] ) ? (string) $s['mode'] : 'off';
	if ( ! in_array( $mode, upw_scrollytelling_style_keys(), true ) ) {
		return $attr;
	}
	$o = ( isset( $s[ $mode ] ) && is_array( $s[ $mode ] ) ) ? $s[ $mode ] : array();

	$side = ( isset( $o['pin_side'] ) && in_array( $o['pin_side'], array( 'left', 'right', 'top' ), true ) ) ? $o['pin_side'] : 'left';
	$prog = ( isset( $o['progress'] ) && in_array( $o['progress'], array( 'dots', 'bar', 'none' ), true ) ) ? $o['progress'] : 'dots';

	$cls           = isset( $attr['class'] ) ? trim( (string) $attr['class'] ) : '';
	$attr['class'] = esc_attr( trim( $cls . ' upw-story' ) );

	$attr['data-story-style']     = esc_attr( $mode );
	$attr['data-story-side']      = esc_attr( $side );
	$attr['data-story-h']         = esc_attr( (string) max( 40, min( 100, (int) ( $o['media_height'] ?? 100 ) ) ) );
	$attr['data-story-top']       = esc_attr( (string) max( 0, min( 300, (int) ( $o['pin_offset'] ?? 0 ) ) ) );
	$attr['data-story-at']        = esc_attr( (string) max( 10, min( 90, (int) ( $o['activate_at'] ?? 50 ) ) ) );
	$attr['data-story-trans']     = esc_attr( (string) max( 0.1, min( 2, (float) ( $o['transition'] ?? 0.6 ) ) ) );
	$attr['data-story-intensity'] = esc_attr( (string) max( 0, min( 1, (float) ( $o['intensity'] ?? 0.5 ) ) ) );
	$attr['data-story-progress']  = esc_attr( $prog );

	// Full-screen Stage layout: every column is a scene; optional scrubbed backdrop behind them.
	$layout = ( isset( $o['layout'] ) && $o['layout'] === 'stage' ) ? 'stage' : 'panel';
	$attr['data-story-layout'] = esc_attr( $layout );
	if ( $layout === 'stage' ) {
		$attr['data-story-scenelen'] = esc_attr( (string) max( 0.5, min( 3, (float) ( $o['scene_length'] ?? 1 ) ) ) );
		// Exit hand-off — fade the pinned stage into the Section background near the end.
		if ( isset( $o['exit'] ) && $o['exit'] === 'fade' ) {
			$attr['data-story-exit']    = 'fade';
			$attr['data-story-exit-at'] = esc_attr( (string) ( max( 50, min( 95, (int) ( $o['exit_at'] ?? 78 ) ) ) / 100 ) );
		}
		$b   = ( isset( $o['backdrop'] ) && is_array( $o['backdrop'] ) ) ? $o['backdrop'] : array();
		$src = isset( $b['source'] ) ? (string) $b['source'] : 'none';
		if ( in_array( $src, array( 'frames', 'sequence', 'video', 'image' ), true ) ) {
			$bo  = ( isset( $b[ $src ] ) && is_array( $b[ $src ] ) ) ? $b[ $src ] : array();
			$url = function ( $v ) { // upload value -> url string
				if ( is_array( $v ) && isset( $v['url'] ) ) { return (string) $v['url']; }
				return is_string( $v ) ? $v : '';
			};
			$cfg = array( 'type' => $src, 'fit' => ( isset( $bo['fit'] ) && $bo['fit'] === 'contain' ) ? 'contain' : 'cover' );
			if ( $src === 'frames' ) {
				// Media-Library frames (multi-upload) — the user-replaceable default.
				$cfg['type']   = 'sequence';
				$cfg['frames'] = array();
				if ( isset( $bo['frames'] ) && is_array( $bo['frames'] ) ) {
					foreach ( $bo['frames'] as $f ) {
						$u = is_array( $f ) && isset( $f['url'] ) ? (string) $f['url'] : '';
						if ( $u !== '' ) { $cfg['frames'][] = $u; }
					}
				}
			} elseif ( $src === 'sequence' ) {
				$cfg['pattern'] = isset( $bo['url_pattern'] ) ? (string) $bo['url_pattern'] : '';
				$cfg['count']   = max( 2, (int) ( $bo['count'] ?? 120 ) );
				$cfg['start']   = max( 0, (int) ( $bo['start'] ?? 0 ) );
				$cfg['pad']     = max( 0, min( 8, (int) ( $bo['pad'] ?? 0 ) ) );
			} elseif ( $src === 'video' ) {
				$cfg['url'] = $url( $bo['video_file'] ?? '' );
				if ( $cfg['url'] === '' ) { $cfg['url'] = isset( $bo['video_url'] ) ? (string) $bo['video_url'] : ''; }
			} else {
				$cfg['url'] = $url( $bo['image'] ?? '' );
			}
			$has_media = ( $src === 'frames' )
				? count( $cfg['frames'] ) >= 2
				: ( ( $src === 'sequence' ) ? $cfg['pattern'] !== '' : $cfg['url'] !== '' );
			if ( $has_media ) {
				// base64 — quote-free, so it survives the wrapper printer's own escaping.
				$attr['data-story-backdrop'] = base64_encode( wp_json_encode( $cfg ) );
				// A backdrop is almost always photographic/dark — so scene copy defaults to a
				// legible light treatment (white + soft shadow + a low bottom scrim). This is a
				// DEFAULT only: any explicit per-element colour option still wins. Authors who set
				// a light backdrop can override the scene text colour as usual.
				$cls           = isset( $attr['class'] ) ? trim( (string) $attr['class'] ) : '';
				$attr['class'] = esc_attr( trim( $cls . ' upw-story--has-backdrop' ) );
			}
		}
	}

	// Directional styles: stamp the chosen direction (only when not the style default).
	$dir = isset( $o['direction'] ) ? (string) $o['direction'] : 'auto';
	if ( in_array( $mode, upw_scrollytelling_directional(), true ) && in_array( $dir, array( 'up', 'down', 'left', 'right' ), true ) ) {
		$attr['data-story-dir'] = esc_attr( $dir );
	}

	// On-demand assets: record this style so ONLY its partials load with the core + base.
	if ( function_exists( 'upw_anim_use_asset' ) ) {
		upw_anim_use_asset( 'scrollytelling', $mode );
	}
	return $attr;
}, 24, 2 );

/* 3) Force a wrapper when a section's ONLY non-default setting is scrollytelling. */
add_filter( 'sc_needs_wrapper', function ( $needs, $atts ) {
	if ( $needs || ! upw_scrollytelling_enabled() ) {
		return $needs;
	}
	$s = ( isset( $atts['scrollytelling'] ) && is_array( $atts['scrollytelling'] ) ) ? $atts['scrollytelling'] : array();
	return ( isset( $s['mode'] ) && in_array( $s['mode'], upw_scrollytelling_style_keys(), true ) );
}, 10, 2 );

/* 4) On-demand assets — the shared core + base CSS always ship when used; each style adds ONLY its
 *    own CSS partial (the 6 CSS-driven styles) and/or JS partial (parallax + pixelate). */
if ( function_exists( 'upw_anim_register_assets' ) ) {
	$upw_story_ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( $upw_story_ext ) {
		upw_anim_register_assets( 'scrollytelling', array(
			'path'      => UPW_SCROLLYTELLING_DIR,
			'uri'       => $upw_story_ext->get_declared_URI( '/modules/scrollytelling' ),
			'ver'       => $upw_story_ext->manifest->get_version(),
			'css_dir'   => 'static/css/effects',
			'js_dir'    => 'static/js/effects',
			'base_css'  => 'static/css/base.css',
			'base_js'   => 'static/js/scrollytelling-core.js',
			// Only 'parallax' + 'pixelate' ship a JS partial; the loader skips absent files, so the
			// CSS-driven styles pull no JS. Listing all keys keeps it future-proof.
			'js_styles' => upw_scrollytelling_style_keys(),
			'js_cfg'    => function () {
				$cfg = array(
					'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
					'disableMobile' => ( function_exists( 'upw_anim_engine_setting' ) && upw_anim_engine_setting( 'disable_on_mobile', 'no' ) === 'yes' ),
				);
				return 'window.upwStoryCfg=' . wp_json_encode( $cfg ) . ';';
			},
		) );
	}
	unset( $upw_story_ext );
}
