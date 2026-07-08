<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Scrollytelling module: helpers.
 *
 * Global master-switch reader, per-request used-flag, and the style registry (single source of
 * truth for the picker + the wrapper checks). Loaded first by scrollytelling.php. All wrapped in
 * function_exists guards.
 */

if ( ! function_exists( 'upw_scrollytelling_enabled' ) ) :
	function upw_scrollytelling_enabled() {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return true;
		}
		$v = fw_get_db_settings_option( 'animation_scrollytelling', array() );
		$e = ( is_array( $v ) && isset( $v['enable'] ) ) ? $v['enable'] : 'yes';
		return $e !== 'no' && $e !== false;
	}
endif;

if ( ! function_exists( 'upw_scrollytelling_styles' ) ) :
	/** The valid style keys (label registry) — shared by the picker + the wrapper filter. */
	function upw_scrollytelling_styles() {
		return array(
			'crossfade' => __( 'Crossfade', 'fw' ),
			'slide'     => __( 'Slide', 'fw' ),
			'zoom'      => __( 'Zoom', 'fw' ),
			'clip_wipe' => __( 'Clip Wipe', 'fw' ),
			'blur'      => __( 'Blur Swap', 'fw' ),
			'ken_burns' => __( 'Ken Burns', 'fw' ),
			'parallax'  => __( 'Parallax Depth', 'fw' ),
			'pixelate'  => __( 'Pixelate Resolve', 'fw' ),
			// Motion / slide
			'push'      => __( 'Push', 'fw' ),
			'cover'     => __( 'Cover', 'fw' ),
			'curtain'   => __( 'Curtain', 'fw' ),
			'split'     => __( 'Split', 'fw' ),
			// 3D
			'flip'      => __( 'Flip (3D)', 'fw' ),
			'cube'      => __( 'Cube (tumble)', 'fw' ),
			'tilt'      => __( 'Tilt 3D', 'fw' ),
			// Reveal / mask
			'iris'      => __( 'Iris', 'fw' ),
			'barn'      => __( 'Barn Doors', 'fw' ),
			'blinds'    => __( 'Blinds', 'fw' ),
			'dissolve'  => __( 'Dissolve', 'fw' ),
			// FX
			'glitch'    => __( 'Glitch', 'fw' ),
			'flash'     => __( 'Flash', 'fw' ),
			'duotone'   => __( 'Duotone', 'fw' ),
			'zoom_blur' => __( 'Zoom Blur', 'fw' ),
			'page_turn' => __( 'Page Turn', 'fw' ),
			'scan'      => __( 'Scan (CRT)', 'fw' ),
			// New-category (JS)
			'color_shift'      => __( 'Color Shift', 'fw' ),
			'frame_sequence'   => __( 'Frame Sequence', 'fw' ),
			'horizontal_track' => __( 'Horizontal Track', 'fw' ),
			'liquid'           => __( 'Liquid (WebGL)', 'fw' ),
		);
	}
endif;

if ( ! function_exists( 'upw_scrollytelling_directional' ) ) :
	/** Styles that expose a Direction sub-option (up / down / left / right). */
	function upw_scrollytelling_directional() {
		return array( 'slide', 'push', 'cover', 'clip_wipe', 'curtain' );
	}
endif;

if ( ! function_exists( 'upw_scrollytelling_style_keys' ) ) :
	function upw_scrollytelling_style_keys() {
		return array_keys( upw_scrollytelling_styles() );
	}
endif;
