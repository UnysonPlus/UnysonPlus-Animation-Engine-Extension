<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/* Emit the element's interactions as one JSON attribute, and record the runtime for the footer loader. */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	$list = upw_interactions_of( $atts );
	if ( ! $list ) {
		return $attr;
	}
	$attr['data-upw-ix'] = wp_json_encode( $list );
	if ( function_exists( 'upw_anim_use_asset' ) ) {
		foreach ( $list as $c ) {
			upw_anim_use_asset( 'interactions', $c['t'] );
		}
	}
	return $attr;
}, 22, 2 );

/* The element's named states (I3), for the same runtime. */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	$states = isset( $atts['upw_states'] ) ? upw_states_compile( $atts['upw_states'] ) : array();
	if ( ! $states ) {
		return $attr;
	}
	$attr['data-upw-states'] = wp_json_encode( $states );
	if ( function_exists( 'upw_anim_use_asset' ) ) {
		upw_anim_use_asset( 'interactions', 'states' );
	}
	return $attr;
}, 22, 2 );

/* An element whose only setting is an interaction (or its states) still needs its wrapper to carry it. */
add_filter( 'sc_needs_wrapper', function ( $needs, $atts ) {
	if ( $needs ) {
		return $needs;
	}
	if ( upw_interactions_of( $atts ) ) {
		return true;
	}
	return isset( $atts['upw_states'] ) && (bool) upw_states_compile( $atts['upw_states'] );
}, 10, 2 );

/* On-demand runtime: one small script, only on pages that use an interaction. */
if ( function_exists( 'upw_anim_register_assets' ) ) {
	$upw_ix_ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( $upw_ix_ext ) {
		upw_anim_register_assets( 'interactions', array(
			'path'      => UPW_INTERACTIONS_DIR,
			'uri'       => $upw_ix_ext->get_declared_URI( '/modules/interactions' ),
			'ver'       => $upw_ix_ext->manifest->get_version(),
			'css_dir'   => 'static/css',
			'js_dir'    => 'static/js',
			'base_js'   => 'static/js/upw-interactions.js',
			'js_styles' => array_merge( upw_interactions_modes(), array( 'states', 'site' ) ),
			'js_cfg'    => function () {
				$cfg = array(
					'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
				);
				return 'window.upwIxCfg=' . wp_json_encode( $cfg ) . ';';
			},
		) );
	}
	unset( $upw_ix_ext );
}
