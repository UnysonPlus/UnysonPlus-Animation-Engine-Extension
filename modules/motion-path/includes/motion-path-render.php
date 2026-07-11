<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Motion Path module: runtime.
 *
 * Emits the chosen path + drive settings onto the element wrapper (via `sc_build_wrapper_attr`):
 * class `sc-motion-path` + `data-mp-*` attrs (the resolved path `d`, drive mode, timing, direction,
 * easing, start offset, path size, align). Forces a wrapper when a motion path is the only setting,
 * and registers the module's on-demand asset layout with the shared loader (one shared base CSS +
 * one runtime — the shape is data-driven, so there are no per-style partials).
 *
 * NOTE: uses UPW_MOTION_PATH_DIR (defined in motion-path.php) — NOT __DIR__ — for the asset-loader
 * path, because this file lives in includes/ while the static assets are at the module root.
 */

/* Emit the motion-path settings onto the element wrapper. */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	if ( ! upw_motion_path_enabled() ) {
		return $attr;
	}
	$mp   = ( isset( $atts['motion_path'] ) && is_array( $atts['motion_path'] ) ) ? $atts['motion_path'] : array();
	$mode = isset( $mp['mode'] ) ? (string) $mp['mode'] : 'none';

	if ( ! in_array( $mode, upw_motion_path_all_modes(), true ) ) {
		return $attr;
	}
	$o = ( isset( $mp[ $mode ] ) && is_array( $mp[ $mode ] ) ) ? $mp[ $mode ] : array();

	// Resolve the path `d`: a preset shape, or the custom field.
	if ( 'custom' === $mode ) {
		$d = isset( $o['custom_d'] ) ? trim( (string) $o['custom_d'] ) : '';
	} else {
		$presets = upw_motion_path_presets();
		$d       = isset( $presets[ $mode ]['d'] ) ? $presets[ $mode ]['d'] : '';
	}
	// Only allow SVG path-data characters (commands + numbers + separators). Bail if empty/unsafe.
	if ( '' === $d || ! preg_match( '/^[MmLlHhVvCcSsQqTtAaZz0-9eE,\.\+\-\s]+$/', $d ) ) {
		return $attr;
	}

	$cls           = isset( $attr['class'] ) ? trim( (string) $attr['class'] ) : '';
	$attr['class'] = esc_attr( trim( $cls . ' sc-motion-path' ) );

	$drive = isset( $o['drive'] ) && in_array( $o['drive'], array( 'scrub', 'loop', 'view' ), true ) ? $o['drive'] : 'scrub';
	$ease  = isset( $o['easing'] ) && in_array( $o['easing'], array( 'linear', 'ease-in', 'ease-out', 'ease-in-out' ), true ) ? $o['easing'] : 'ease-in-out';

	$attr['data-mp-d']      = esc_attr( $d );
	$attr['data-mp-drive']  = esc_attr( $drive );
	$attr['data-mp-dur']    = esc_attr( max( 0.5, min( 20, (float) ( $o['duration'] ?? 4 ) ) ) );
	$attr['data-mp-size']   = esc_attr( max( 40, min( 1200, (int) ( $o['path_size'] ?? 300 ) ) ) );
	$attr['data-mp-offset'] = esc_attr( max( 0, min( 100, (int) ( $o['start_offset'] ?? 0 ) ) ) );
	$attr['data-mp-ease']   = esc_attr( $ease );
	if ( isset( $o['direction'] ) && $o['direction'] === 'yes' ) {
		$attr['data-mp-reverse'] = '1';
	}
	if ( isset( $o['align'] ) && $o['align'] === 'yes' ) {
		$attr['data-mp-align'] = '1';
	}

	// On-demand assets: one shared runtime covers every shape (the path is data-driven).
	if ( function_exists( 'upw_anim_use_asset' ) ) {
		upw_anim_use_asset( 'motion-path', $mode );
	}
	return $attr;
}, 22, 2 );

/* Force a wrapper when an element's ONLY non-default setting is a motion path. */
add_filter( 'sc_needs_wrapper', function ( $needs, $atts ) {
	if ( $needs || ! upw_motion_path_enabled() ) {
		return $needs;
	}
	$mp   = ( isset( $atts['motion_path'] ) && is_array( $atts['motion_path'] ) ) ? $atts['motion_path'] : array();
	$mode = isset( $mp['mode'] ) ? (string) $mp['mode'] : 'none';
	return in_array( $mode, upw_motion_path_all_modes(), true );
}, 10, 2 );

/* On-demand assets — register with the shared loader. One base CSS + one runtime; no per-style
 * partials (the shape rides in a data-attr). Marking any shape "used" ships the base pair. */
if ( function_exists( 'upw_anim_register_assets' ) ) {
	$upw_mp_ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( $upw_mp_ext ) {
		upw_anim_register_assets( 'motion-path', array(
			'path'      => UPW_MOTION_PATH_DIR,
			'uri'       => $upw_mp_ext->get_declared_URI( '/modules/motion-path' ),
			'ver'       => $upw_mp_ext->manifest->get_version(),
			'css_dir'   => 'static/css/effects',
			'js_dir'    => 'static/js/effects',
			'base_css'  => 'static/css/base.css',
			'base_js'   => 'static/js/motion-path.js',
			// Every shape is listed so that using ANY of them marks a "used JS style" → the loader
			// enqueues the shared base_js runtime. There are no per-shape JS partials (the shape rides
			// in data-mp-d), so the loader's file_exists check simply skips the per-shape files and
			// loads only the core runtime. (Same pattern scroll-reveal uses for its clip directions.)
			'js_styles' => upw_motion_path_all_modes(),
			'js_cfg'    => function () {
				$cfg = array(
					'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
				);
				return 'window.upwMotionPathCfg=' . wp_json_encode( $cfg ) . ';';
			},
		) );
	}
	unset( $upw_mp_ext );
}
