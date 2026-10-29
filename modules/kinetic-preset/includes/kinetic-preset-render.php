<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Kinetic Scenes preset module: runtime.
 *
 * Emits the chosen preset onto the element wrapper as `data-upw-kp` (preset, length, delay, trigger, replay and the
 * preset's settings), forces a wrapper when it is the only setting, and registers the on-demand asset: one small
 * runtime that loads the Kinetic Scene element's own player (no second copy) and calls its playPreset().
 *
 * NOTE: uses UPW_KINETIC_PRESET_DIR (the module root), not __DIR__, for asset paths (this file lives in includes/).
 */

if ( ! function_exists( 'upw_kp_value' ) ) :
	/** The element's preset settings, cleaned, or null when none is set. */
	function upw_kp_value( $atts ) {
		$v     = ( isset( $atts['kinetic_preset'] ) && is_array( $atts['kinetic_preset'] ) ) ? $atts['kinetic_preset'] : array();
		$group = isset( $v['mode'] ) ? (string) $v['mode'] : 'off';
		$all   = function_exists( 'upw_kp_presets' ) ? upw_kp_presets() : array();
		if ( 'off' === $group || ! isset( $all[ $group ] ) ) {
			return null;
		}
		$o      = ( isset( $v[ $group ] ) && is_array( $v[ $group ] ) ) ? $v[ $group ] : array();
		$preset = isset( $o['preset'] ) ? (string) $o['preset'] : '';
		if ( ! isset( $all[ $group ][ $preset ] ) ) {
			return null;
		}
		$params = array();
		if ( ! empty( $o['settings'] ) ) {
			$decoded = json_decode( (string) $o['settings'], true );
			if ( is_array( $decoded ) ) {
				// Plain values only (numbers, words, true/false): settings never carry code.
				foreach ( $decoded as $k => $val ) {
					if ( is_string( $k ) && preg_match( '/^[a-zA-Z][a-zA-Z0-9_]{0,30}$/', $k ) && ( is_scalar( $val ) || null === $val ) ) {
						$params[ $k ] = is_string( $val ) ? substr( $val, 0, 200 ) : $val;
					}
				}
			}
		}
		if ( $all[ $group ][ $preset ][2] ) {
			$params['direction'] = ( isset( $o['direction'] ) && 'out' === $o['direction'] ) ? 'out' : 'in';
		}
		$trigger = isset( $o['trigger'] ) && in_array( $o['trigger'], array( 'view', 'load', 'hover', 'click' ), true ) ? $o['trigger'] : 'view';
		$out     = array( 'p' => $preset, 't' => $trigger );
		$dur     = isset( $o['duration'] ) ? (float) $o['duration'] : 0;
		if ( $dur > 0 ) {
			$out['d'] = min( 20, $dur );
		}
		$delay = isset( $o['delay'] ) ? (float) $o['delay'] : 0;
		if ( $delay > 0 ) {
			$out['w'] = min( 20, $delay );
		}
		if ( isset( $o['replay'] ) && 'yes' === $o['replay'] ) {
			$out['r'] = 1;
		}
		if ( $params ) {
			$out['s'] = $params;
		}
		return array( 'group' => $group, 'config' => $out );
	}
endif;

add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	if ( ! upw_kinetic_preset_enabled() ) {
		return $attr;
	}
	$kp = upw_kp_value( $atts );
	if ( ! $kp ) {
		return $attr;
	}
	$cls                = isset( $attr['class'] ) ? trim( (string) $attr['class'] ) : '';
	$attr['class']      = esc_attr( trim( $cls . ' upw-kp' ) );
	$attr['data-upw-kp'] = esc_attr( wp_json_encode( $kp['config'] ) );
	if ( function_exists( 'upw_anim_use_asset' ) ) {
		upw_anim_use_asset( 'kinetic-preset', $kp['group'] );
	}
	return $attr;
}, 22, 2 );

add_filter( 'sc_needs_wrapper', function ( $needs, $atts ) {
	if ( $needs || ! upw_kinetic_preset_enabled() ) {
		return $needs;
	}
	return null !== upw_kp_value( $atts );
}, 10, 2 );

if ( function_exists( 'upw_anim_register_assets' ) ) {
	$upw_kp_ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( $upw_kp_ext ) {
		upw_anim_register_assets( 'kinetic-preset', array(
			'path'      => UPW_KINETIC_PRESET_DIR,
			'uri'       => $upw_kp_ext->get_declared_URI( '/modules/kinetic-preset' ),
			'ver'       => $upw_kp_ext->manifest->get_version(),
			'css_dir'   => 'static/css/effects',
			'js_dir'    => 'static/js/effects',
			'base_js'   => 'static/js/kinetic-preset.js',
			'js_styles' => array(),
			'js_cfg'    => function () use ( $upw_kp_ext ) {
				// The Kinetic Scene element's own copy of the player (the same file, so a page with both loads it once).
				$player = $upw_kp_ext->get_declared_URI( '/shortcodes/studio-scene/static/js/vendor/scene-player.js' );
				$cfg    = array(
					'player'        => $player,
					'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
				);
				return 'window.upwKineticPresetCfg=' . wp_json_encode( $cfg ) . ';';
			},
		) );
	}
	unset( $upw_kp_ext );
}
