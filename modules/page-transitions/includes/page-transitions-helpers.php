<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Page Transitions module: helpers.
 *
 * Setting reader, the transition-type registry, and the multi-picker resolver (single source of
 * truth for the picker + the runtime's normalized [ type, dir, count, total ]). Loaded first by
 * page-transitions.php (the settings + enqueue parts depend on these). All wrapped in
 * function_exists guards.
 */

if ( ! function_exists( 'upw_pt_setting' ) ) :
	function upw_pt_setting( $key, $default = '' ) {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return $default;
		}
		$v = fw_get_db_settings_option( 'animation_pt', array() );
		if ( is_array( $v ) && isset( $v[ $key ] ) && $v[ $key ] !== '' && $v[ $key ] !== null ) {
			return is_bool( $v[ $key ] ) ? ( $v[ $key ] ? 'yes' : 'no' ) : $v[ $key ];
		}
		return $default;
	}
endif;

if ( ! function_exists( 'upw_pt_enabled' ) ) :
	function upw_pt_enabled() {
		return upw_pt_setting( 'enable', 'no' ) === 'yes' && ! is_admin();
	}
endif;

if ( ! function_exists( 'upw_pt_types' ) ) :
	function upw_pt_types() {
		return array(
			'fade', 'slide', 'zoom', 'rotate', 'curtain', 'doors', 'split', 'wipe', 'diagonal',
			'bars', 'stripes', 'blinds', 'reveal', 'shape', 'iris', 'glitch', 'flip',
			'checkerboard', 'pixels', 'ripple', 'conic', 'morph', 'contentfade',
		);
	}
endif;

if ( ! function_exists( 'upw_pt_resolve' ) ) :
	/** Read the transition multi-picker into a normalized [ type, dir, count, total ]. */
	function upw_pt_resolve() {
		$tr   = upw_pt_setting( 'transition', array() );
		$type = ( is_array( $tr ) && ! empty( $tr['transition'] ) ) ? (string) $tr['transition'] : 'fade';
		if ( ! in_array( $type, upw_pt_types(), true ) ) { $type = 'fade'; }
		$sub  = ( is_array( $tr ) && isset( $tr[ $type ] ) && is_array( $tr[ $type ] ) ) ? $tr[ $type ] : array();
		$dur  = (float) upw_pt_setting( 'duration', 0.6 );
		$dir   = '';
		$count = 0;
		$total = $dur;
		switch ( $type ) {
			case 'slide':
				$dir = in_array( ( $sub['direction'] ?? 'up' ), array( 'up', 'down', 'left', 'right' ), true ) ? $sub['direction'] : 'up';
				break;
			case 'wipe':
				$dir = in_array( ( $sub['direction'] ?? 'left' ), array( 'left', 'right', 'up', 'down' ), true ) ? $sub['direction'] : 'left';
				break;
			case 'curtain':
				$dir = ( ( $sub['split'] ?? 'vertical' ) === 'horizontal' ) ? 'horizontal' : 'vertical';
				break;
			case 'reveal':
				$dir = in_array( ( $sub['origin'] ?? 'center' ), array( 'center', 'tl', 'tr', 'bl', 'br' ), true ) ? $sub['origin'] : 'center';
				break;
			case 'diagonal':
				$dir = ( ( $sub['direction'] ?? 'tlbr' ) === 'trbl' ) ? 'trbl' : 'tlbr';
				break;
			case 'split':
				$dir = ( ( $sub['direction'] ?? 'vertical' ) === 'horizontal' ) ? 'horizontal' : 'vertical';
				break;
			case 'shape':
				$dir = in_array( ( $sub['shape'] ?? 'circle' ), array( 'circle', 'square', 'diamond' ), true ) ? $sub['shape'] : 'circle';
				break;
			case 'flip':
				$dir = ( ( $sub['axis'] ?? 'y' ) === 'x' ) ? 'x' : 'y';
				break;
			case 'blinds':
				$dir   = ( ( $sub['direction'] ?? 'vertical' ) === 'horizontal' ) ? 'horizontal' : 'vertical';
				$count = max( 3, min( 10, (int) ( $sub['count'] ?? 6 ) ) );
				$total = $dur + ( $count - 1 ) * 0.07; // staggered strips
				break;
			case 'checkerboard':
			case 'pixels':
				$count = max( 8, min( 20, (int) ( $sub['density'] ?? 12 ) ) );
				$total = $dur + 0.5; // grid stagger
				break;
		}
		return array( 'type' => $type, 'dir' => $dir, 'count' => $count, 'dur' => $dur, 'total' => $total );
	}
endif;
