<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Motion Path module: helpers.
 *
 * The global master-switch reader, the per-request used-flag path, and the preset path library
 * (each entry is a normalized SVG `d` in a 0..100 box + a viewBox). Loaded first by
 * motion-path.php — the settings + render parts depend on these. All function_exists-guarded.
 */

if ( ! function_exists( 'upw_motion_path_enabled' ) ) :
	function upw_motion_path_enabled() {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return true;
		}
		$v = fw_get_db_settings_option( 'animation_motion_path', array() );
		$e = ( is_array( $v ) && isset( $v['enable'] ) ) ? $v['enable'] : 'yes';
		return $e !== 'no' && $e !== false;
	}
endif;

if ( ! function_exists( 'upw_motion_path_presets' ) ) :
	/**
	 * The built-in path shapes. Each `d` lives in a normalized 0..100 × 0..100 box; the runtime
	 * scales it to the element's chosen Path size (px) and moves the element RELATIVE to its first
	 * point, so it starts at its natural layout position and travels the shape from there.
	 *
	 * @return array<string,array{label:string,d:string}>
	 */
	function upw_motion_path_presets() {
		return array(
			// --- Curves & organic (the originals) ---
			'wave'        => array( 'label' => __( 'Wave', 'fw' ),        'd' => 'M0,50 C15,8 35,8 50,50 S85,92 100,50' ),
			'arc'         => array( 'label' => __( 'Arc', 'fw' ),         'd' => 'M0,85 Q50,-10 100,85' ),
			'loop'        => array( 'label' => __( 'Loop', 'fw' ),        'd' => 'M2,62 C22,62 30,14 50,14 C70,14 72,60 52,60 C34,60 40,90 62,90 C82,90 98,62 98,62' ),
			's_curve'     => array( 'label' => __( 'S-Curve', 'fw' ),     'd' => 'M22,2 C82,26 18,74 78,98' ),
			'zigzag'      => array( 'label' => __( 'Zigzag', 'fw' ),      'd' => 'M0,20 L25,80 L50,20 L75,80 L100,20' ),
			'spiral'      => array( 'label' => __( 'Spiral', 'fw' ),      'd' => 'M92,50 A42,42 0 1,1 50,8 A34,34 0 1,1 84,50 A26,26 0 1,1 50,24 A18,18 0 1,1 68,50 A10,10 0 1,1 50,40' ),
			'circle'      => array( 'label' => __( 'Circle', 'fw' ),      'd' => 'M50,5 A45,45 0 1,1 49.9,5' ),
			'incline'     => array( 'label' => __( 'Incline', 'fw' ),     'd' => 'M0,95 C30,92 40,55 60,45 C78,36 88,14 100,5' ),

			// --- Loops & knots ---
			'figure8'     => array( 'label' => __( 'Figure 8', 'fw' ),    'd' => 'M50,50 C68,28 92,28 92,50 C92,72 68,72 50,50 C32,28 8,28 8,50 C8,72 32,72 50,50 Z' ),
			'double_loop' => array( 'label' => __( 'Double Loop', 'fw' ), 'd' => 'M6,62 C6,32 28,32 28,52 C28,68 14,68 18,52 C22,30 48,28 56,50 C61,68 80,68 80,50 C80,34 68,34 68,48' ),
			'knot'        => array( 'label' => __( 'Knot', 'fw' ),        'd' => 'M50,14 C24,14 24,50 50,50 C76,50 76,86 50,86 C24,86 24,50 50,50 C76,50 76,14 50,14' ),

			// --- Geometric perimeters ---
			'triangle'    => array( 'label' => __( 'Triangle', 'fw' ),    'd' => 'M50,8 L92,86 L8,86 Z' ),
			'square'      => array( 'label' => __( 'Square', 'fw' ),      'd' => 'M14,14 H86 V86 H14 Z' ),
			'diamond'     => array( 'label' => __( 'Diamond', 'fw' ),     'd' => 'M50,6 L94,50 L50,94 L6,50 Z' ),
			'pentagon'    => array( 'label' => __( 'Pentagon', 'fw' ),    'd' => 'M50,8 L91,38 L75,88 L25,88 L9,38 Z' ),
			'hexagon'     => array( 'label' => __( 'Hexagon', 'fw' ),     'd' => 'M50,6 L88,28 L88,72 L50,94 L12,72 L12,28 Z' ),
			'octagon'     => array( 'label' => __( 'Octagon', 'fw' ),     'd' => 'M32,8 H68 L92,32 V68 L68,92 H32 L8,68 V32 Z' ),
			'star'        => array( 'label' => __( 'Star', 'fw' ),        'd' => 'M50,6 L61,39 L96,39 L68,60 L79,94 L50,73 L21,94 L32,60 L4,39 L39,39 Z' ),

			// --- Angular / mechanical ---
			'stairs'      => array( 'label' => __( 'Stairs', 'fw' ),      'd' => 'M6,92 H30 V68 H54 V44 H78 V20 H96' ),
			'steps_down'  => array( 'label' => __( 'Steps Down', 'fw' ),  'd' => 'M6,12 H30 V36 H54 V60 H78 V84 H96' ),
			'l_corner'    => array( 'label' => __( 'L-Corner', 'fw' ),    'd' => 'M12,10 V88 H92' ),
			'chevron'     => array( 'label' => __( 'Chevron', 'fw' ),     'd' => 'M8,80 L50,20 L92,80' ),
			'lightning'   => array( 'label' => __( 'Lightning', 'fw' ),   'd' => 'M62,6 L30,46 L52,50 L40,94' ),
			'u_turn'      => array( 'label' => __( 'U-Turn', 'fw' ),      'd' => 'M22,10 V64 A28,28 0 0 0 78,64 V10' ),

			// --- Organic / physics-like ---
			'bounce'      => array( 'label' => __( 'Bounce', 'fw' ),      'd' => 'M4,90 Q16,18 28,90 Q37,44 46,90 Q53,60 60,90 Q65,74 70,90 T96,90' ),
			'pendulum'    => array( 'label' => __( 'Pendulum', 'fw' ),    'd' => 'M16,24 Q50,96 84,24' ),
			'helix'       => array( 'label' => __( 'Helix', 'fw' ),       'd' => 'M6,50 C14,24 24,24 30,50 C36,76 46,76 52,50 C58,24 68,24 74,50 C80,76 90,76 94,50' ),
			'corkscrew'   => array( 'label' => __( 'Corkscrew', 'fw' ),   'd' => 'M4,50 C9,26 17,26 22,50 C27,74 35,74 40,50 C45,26 53,26 58,50 C63,74 71,74 76,50 C81,26 89,26 94,50' ),
			'swoosh'      => array( 'label' => __( 'Swoosh', 'fw' ),       'd' => 'M8,52 L38,82 L94,14' ),
			'comet'       => array( 'label' => __( 'Comet', 'fw' ),       'd' => 'M8,8 C30,12 46,40 50,60 C54,80 70,86 94,86' ),
			'ricochet'    => array( 'label' => __( 'Ricochet', 'fw' ),    'd' => 'M6,72 L38,14 L58,72 L84,20 L94,80' ),

			// --- Decorative / brand ---
			'heart'       => array( 'label' => __( 'Heart', 'fw' ),       'd' => 'M50,86 C12,58 10,28 31,20 C43,15 50,26 50,33 C50,26 57,15 69,20 C90,28 88,58 50,86 Z' ),
			'teardrop'    => array( 'label' => __( 'Teardrop', 'fw' ),    'd' => 'M50,6 C50,6 82,44 82,62 A32,32 0 1 1 18,62 C18,44 50,6 50,6 Z' ),
			'petal'       => array( 'label' => __( 'Petal', 'fw' ),       'd' => 'M50,90 C22,62 30,22 50,10 C70,22 78,62 50,90 Z' ),
			'ribbon'      => array( 'label' => __( 'Ribbon', 'fw' ),      'd' => 'M2,42 C14,22 26,58 40,44 C54,30 66,66 80,48 C88,38 94,46 98,50' ),

			// --- Straight variants ---
			'line'        => array( 'label' => __( 'Line', 'fw' ),        'd' => 'M6,50 H94' ),
			'drift'       => array( 'label' => __( 'Drift', 'fw' ),       'd' => 'M6,54 Q50,38 94,50' ),
		);
	}
endif;

if ( ! function_exists( 'upw_motion_path_shape_keys' ) ) :
	function upw_motion_path_shape_keys() {
		return array_keys( upw_motion_path_presets() );
	}
endif;

if ( ! function_exists( 'upw_motion_path_all_modes' ) ) :
	function upw_motion_path_all_modes() {
		return array_merge( upw_motion_path_shape_keys(), array( 'custom' ) );
	}
endif;
