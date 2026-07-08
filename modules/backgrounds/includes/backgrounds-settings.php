<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Animated Backgrounds module: option declarations.
 *
 * Declares the "Background Effect" picker injected into the Styling tab of CONTAINER shortcodes
 * (via `fw_shortcode_get_options`), plus the global on/off sub-tab in Theme Settings → Animations
 * → Backgrounds (via `upw_anim_engine_module_tabs`). Depends on the registries/helpers in
 * backgrounds-helpers.php.
 */

/* ------------------------------------------------------------------ *
 * 1) The "Background Effect" picker, injected into container styling tabs.
 * ------------------------------------------------------------------ */
add_filter( 'fw_shortcode_get_options', function ( $options, $tag ) {
	if ( ! is_array( $options ) || ! in_array( $tag, upw_bg_containers(), true ) ) {
		return $options;
	}
	if ( ! isset( $options['tab_styling'] ) || ! isset( $options['tab_styling']['options'] ) || ! is_array( $options['tab_styling']['options'] ) ) {
		return $options;
	}

	$ext  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	$base = $ext ? $ext->get_declared_URI( '/modules/backgrounds/static/img/effects' ) : '';
	$bg   = function ( $file, $label ) use ( $base ) {
		return array(
			'small' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 66 ),
			'large' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 132 ),
			'label' => $label,
		);
	};
	$speed = function ( $default, $min = 1, $max = 12 ) {
		return array( 'type' => 'slider', 'label' => __( 'Speed (s)', 'fw' ), 'value' => $default, 'properties' => array( 'min' => $min, 'max' => $max, 'step' => 0.5 ) );
	};

	$bg_field = array(
		'type'         => 'multi-picker',
		'label'        => __( 'Background Effect', 'fw' ),
		'desc'         => __( 'An animated background layered behind this container’s content.', 'fw' ),
		'help'         => __( 'Animated Backgrounds (Animation Engine): aurora, gradient, dot grid, floating particles, constellation, waves, starfield or grain — rendered behind the section content. Honours "reduce motion" (static frame), pauses when off-screen.', 'fw' ) . ( function_exists( 'upw_perf_note' ) ? ' ' . upw_perf_note() : '' ),
		'popover'      => true,
		'show_borders' => false,
		'value'        => array( 'effect' => 'none' ),
		'picker'       => array(
			'effect' => array(
				'type'    => 'image-picker',
				'label'   => false,
				'desc'    => __( 'Hover a tile to preview it larger.', 'fw' ),
				'value'   => 'none',
				'choices' => array(
					'none'          => $bg( 'none',          __( 'None', 'fw' ) ),
					'aurora'        => $bg( 'aurora',        __( 'Aurora', 'fw' ) ),
					'gradient'      => $bg( 'gradient',      __( 'Gradient', 'fw' ) ),
					'dots'          => $bg( 'dots',          __( 'Dot Grid', 'fw' ) ),
					'particles'     => $bg( 'particles',     __( 'Particles', 'fw' ) ),
					'constellation' => $bg( 'constellation', __( 'Constellation', 'fw' ) ),
					'waves'         => $bg( 'waves',         __( 'Waves', 'fw' ) ),
					'starfield'     => $bg( 'starfield',     __( 'Starfield', 'fw' ) ),
					'noise'         => $bg( 'noise',         __( 'Grain', 'fw' ) ),
					'mesh'          => $bg( 'mesh',          __( 'Mesh Gradient', 'fw' ) ),
					'grid'          => $bg( 'grid',          __( 'Grid Lines', 'fw' ) ),
					'orbs'          => $bg( 'orbs',          __( 'Glow Orbs', 'fw' ) ),
					'conic'         => $bg( 'conic',         __( 'Conic', 'fw' ) ),
					'scanlines'     => $bg( 'scanlines',     __( 'Scanlines', 'fw' ) ),
					'rays'          => $bg( 'rays',          __( 'Light Rays', 'fw' ) ),
					'snow'          => $bg( 'snow',          __( 'Snow / Petals', 'fw' ) ),
					'confetti'      => $bg( 'confetti',      __( 'Confetti', 'fw' ) ),
					'bubbles'       => $bg( 'bubbles',       __( 'Bubbles', 'fw' ) ),
					'fireflies'     => $bg( 'fireflies',     __( 'Fireflies', 'fw' ) ),
					'bokeh'         => $bg( 'bokeh',         __( 'Bokeh', 'fw' ) ),
					'rain'          => $bg( 'rain',          __( 'Rain', 'fw' ) ),
					'shapes'        => $bg( 'shapes',        __( 'Floating Shapes', 'fw' ) ),
					'meteors'       => $bg( 'meteors',       __( 'Shooting Stars', 'fw' ) ),
					'pgrid'         => $bg( 'pgrid',         __( 'Perspective Grid', 'fw' ) ),
					'hexgrid'       => $bg( 'hexgrid',       __( 'Hex Grid', 'fw' ) ),
					'topo'          => $bg( 'topo',          __( 'Topographic', 'fw' ) ),
					'circuit'       => $bg( 'circuit',       __( 'Circuit Board', 'fw' ) ),
					'halftone'      => $bg( 'halftone',      __( 'Halftone', 'fw' ) ),
					'blobs'         => $bg( 'blobs',         __( 'Metaballs', 'fw' ) ),
					'ripple'        => $bg( 'ripple',        __( 'Ripple', 'fw' ) ),
					'flow'          => $bg( 'flow',          __( 'Flow Field', 'fw' ) ),
					'matrix'        => $bg( 'matrix',        __( 'Matrix Rain', 'fw' ) ),
					'nebula'        => $bg( 'nebula',        __( 'Nebula', 'fw' ) ),
					'borealis'      => $bg( 'borealis',      __( 'Aurora Borealis', 'fw' ) ),
					'orbits'        => $bg( 'orbits',        __( 'Orbits', 'fw' ) ),
					'spotlight'     => $bg( 'spotlight',     __( 'Cursor Spotlight', 'fw' ) ),
				),
			),
		),
		'choices' => array(
			'aurora' => array(
				'color_a' => upw_bg_color_field( __( 'Color 1', 'fw' ), 'bg', '#6a8dff' ),
				'color_b' => upw_bg_color_field( __( 'Color 2', 'fw' ), 'bg', '#c56cff' ),
				'color_c' => upw_bg_color_field( __( 'Color 3', 'fw' ), 'bg', '#00d4c8' ),
				'speed'   => $speed( 8, 3, 20 ),
			),
			'gradient' => array(
				'color_a' => upw_bg_color_field( __( 'Color 1', 'fw' ), 'bg', '#2f74e6' ),
				'color_b' => upw_bg_color_field( __( 'Color 2', 'fw' ), 'bg', '#7a3cff' ),
				'color_c' => upw_bg_color_field( __( 'Color 3', 'fw' ), 'bg', '#00b2b2' ),
				'angle'   => array( 'type' => 'slider', 'label' => __( 'Angle (°)', 'fw' ), 'value' => 120, 'properties' => array( 'min' => 0, 'max' => 360, 'step' => 5 ) ),
				'speed'   => $speed( 10, 3, 24 ),
			),
			'dots' => array(
				'color' => upw_bg_color_field( __( 'Dot color', 'fw' ), 'bg', '#94a3b8' ),
				'size'  => array( 'type' => 'slider', 'label' => __( 'Dot size (px)', 'fw' ), 'value' => 2, 'properties' => array( 'min' => 1, 'max' => 5, 'step' => 1 ) ),
				'gap'   => array( 'type' => 'slider', 'label' => __( 'Gap (px)', 'fw' ), 'value' => 26, 'properties' => array( 'min' => 12, 'max' => 60, 'step' => 2 ) ),
			),
			'particles' => array(
				'color'   => upw_bg_color_field( __( 'Particle color', 'fw' ), 'bg', '#6aa6ff' ),
				'density' => array( 'type' => 'slider', 'label' => __( 'Density', 'fw' ), 'value' => 60, 'properties' => array( 'min' => 15, 'max' => 160, 'step' => 5 ) ),
				'speed'   => $speed( 3, 1, 8 ),
			),
			'constellation' => array(
				'color'     => upw_bg_color_field( __( 'Color', 'fw' ), 'bg', '#6aa6ff' ),
				'density'   => array( 'type' => 'slider', 'label' => __( 'Density', 'fw' ), 'value' => 55, 'properties' => array( 'min' => 15, 'max' => 140, 'step' => 5 ) ),
				'link_dist' => array( 'type' => 'slider', 'label' => __( 'Link distance (px)', 'fw' ), 'value' => 120, 'properties' => array( 'min' => 60, 'max' => 220, 'step' => 10 ) ),
			),
			'waves' => array(
				'color'     => upw_bg_color_field( __( 'Wave color', 'fw' ), 'bg', '#2f74e6' ),
				'amplitude' => array( 'type' => 'slider', 'label' => __( 'Amplitude', 'fw' ), 'value' => 30, 'properties' => array( 'min' => 8, 'max' => 80, 'step' => 2 ) ),
				'speed'     => $speed( 6, 2, 16 ),
			),
			'starfield' => array(
				'color'   => upw_bg_color_field( __( 'Star color', 'fw' ), 'bg', '#ffffff' ),
				'density' => array( 'type' => 'slider', 'label' => __( 'Density', 'fw' ), 'value' => 120, 'properties' => array( 'min' => 30, 'max' => 300, 'step' => 10 ) ),
				'speed'   => $speed( 4, 1, 12 ),
			),
			'noise' => array(
				'opacity' => array( 'type' => 'slider', 'label' => __( 'Opacity', 'fw' ), 'value' => 0.06, 'properties' => array( 'min' => 0.02, 'max' => 0.25, 'step' => 0.01 ) ),
				'speed'   => $speed( 1, 0.5, 4 ),
			),
			'mesh' => array(
				'color_a' => upw_bg_color_field( __( 'Color 1', 'fw' ), 'bg', '#6a8dff' ),
				'color_b' => upw_bg_color_field( __( 'Color 2', 'fw' ), 'bg', '#ff6ac1' ),
				'color_c' => upw_bg_color_field( __( 'Color 3', 'fw' ), 'bg', '#ffd36a' ),
				'color_d' => upw_bg_color_field( __( 'Color 4', 'fw' ), 'bg', '#00d4c8' ),
				'speed'   => $speed( 12, 4, 24 ),
			),
			'grid' => array(
				'color' => upw_bg_color_field( __( 'Line color', 'fw' ), 'bg', '#94a3b8' ),
				'gap'   => array( 'type' => 'slider', 'label' => __( 'Gap (px)', 'fw' ), 'value' => 40, 'properties' => array( 'min' => 16, 'max' => 100, 'step' => 4 ) ),
				'speed' => $speed( 12, 4, 30 ),
			),
			'orbs' => array(
				'color_a' => upw_bg_color_field( __( 'Color 1', 'fw' ), 'bg', '#6a8dff' ),
				'color_b' => upw_bg_color_field( __( 'Color 2', 'fw' ), 'bg', '#c56cff' ),
				'speed'   => $speed( 10, 4, 24 ),
			),
			'conic' => array(
				'color_a' => upw_bg_color_field( __( 'Color 1', 'fw' ), 'bg', '#2f74e6' ),
				'color_b' => upw_bg_color_field( __( 'Color 2', 'fw' ), 'bg', '#7a3cff' ),
				'color_c' => upw_bg_color_field( __( 'Color 3', 'fw' ), 'bg', '#00b2b2' ),
				'speed'   => $speed( 12, 4, 30 ),
			),
			'scanlines' => array(
				'color'   => upw_bg_color_field( __( 'Line color', 'fw' ), 'bg', '#000000' ),
				'opacity' => array( 'type' => 'slider', 'label' => __( 'Opacity', 'fw' ), 'value' => 0.12, 'properties' => array( 'min' => 0.04, 'max' => 0.4, 'step' => 0.02 ) ),
				'speed'   => $speed( 6, 1, 16 ),
			),
			'rays' => array(
				'color' => upw_bg_color_field( __( 'Ray color', 'fw' ), 'bg', '#ffffff' ),
				'angle' => array( 'type' => 'slider', 'label' => __( 'Angle (°)', 'fw' ), 'value' => 25, 'properties' => array( 'min' => 0, 'max' => 90, 'step' => 5 ) ),
				'speed' => $speed( 10, 4, 24 ),
			),
			'snow' => array(
				'variant' => array( 'type' => 'select', 'label' => __( 'Style', 'fw' ), 'value' => 'snow', 'choices' => array(
					'snow' => __( 'Snowflakes', 'fw' ), 'petals' => __( 'Petals', 'fw' ), 'embers' => __( 'Embers (rise)', 'fw' ), 'ash' => __( 'Ash', 'fw' ),
				) ),
				'density' => array( 'type' => 'slider', 'label' => __( 'Density', 'fw' ), 'value' => 70, 'properties' => array( 'min' => 20, 'max' => 200, 'step' => 10 ) ),
				'speed'   => $speed( 3, 1, 8 ),
			),
			'confetti' => array(
				'density' => array( 'type' => 'slider', 'label' => __( 'Density', 'fw' ), 'value' => 60, 'properties' => array( 'min' => 20, 'max' => 200, 'step' => 10 ) ),
				'speed'   => $speed( 3, 1, 8 ),
			),
			'bubbles' => array(
				'color'   => upw_bg_color_field( __( 'Bubble color', 'fw' ), 'bg', '#8fd0ff' ),
				'density' => array( 'type' => 'slider', 'label' => __( 'Density', 'fw' ), 'value' => 40, 'properties' => array( 'min' => 10, 'max' => 120, 'step' => 5 ) ),
				'speed'   => $speed( 3, 1, 8 ),
			),
			'fireflies' => array(
				'color'   => upw_bg_color_field( __( 'Glow color', 'fw' ), 'bg', '#ffd36a' ),
				'density' => array( 'type' => 'slider', 'label' => __( 'Density', 'fw' ), 'value' => 50, 'properties' => array( 'min' => 15, 'max' => 140, 'step' => 5 ) ),
				'speed'   => $speed( 3, 1, 8 ),
			),
			'bokeh' => array(
				'color'   => upw_bg_color_field( __( 'Color', 'fw' ), 'bg', '#6aa6ff' ),
				'density' => array( 'type' => 'slider', 'label' => __( 'Density', 'fw' ), 'value' => 20, 'properties' => array( 'min' => 6, 'max' => 40, 'step' => 2 ) ),
				'speed'   => $speed( 3, 1, 8 ),
			),
			'rain' => array(
				'color'   => upw_bg_color_field( __( 'Rain color', 'fw' ), 'bg', '#9db4d0' ),
				'density' => array( 'type' => 'slider', 'label' => __( 'Density', 'fw' ), 'value' => 90, 'properties' => array( 'min' => 30, 'max' => 240, 'step' => 10 ) ),
				'speed'   => $speed( 4, 1, 10 ),
			),
			'shapes' => array(
				'color'   => upw_bg_color_field( __( 'Shape color', 'fw' ), 'bg', '#94a3b8' ),
				'density' => array( 'type' => 'slider', 'label' => __( 'Density', 'fw' ), 'value' => 24, 'properties' => array( 'min' => 8, 'max' => 60, 'step' => 2 ) ),
				'speed'   => $speed( 3, 1, 8 ),
			),
			'meteors' => array(
				'color'   => upw_bg_color_field( __( 'Star color', 'fw' ), 'bg', '#ffffff' ),
				'density' => array( 'type' => 'slider', 'label' => __( 'Frequency', 'fw' ), 'value' => 50, 'properties' => array( 'min' => 20, 'max' => 120, 'step' => 10 ) ),
				'speed'   => $speed( 4, 1, 10 ),
			),
			'pgrid' => array(
				'color' => upw_bg_color_field( __( 'Line color', 'fw' ), 'bg', '#ff6ac1' ),
				'speed' => $speed( 6, 2, 16 ),
			),
			'hexgrid' => array(
				'color' => upw_bg_color_field( __( 'Line color', 'fw' ), 'bg', '#6aa6ff' ),
				'speed' => $speed( 6, 2, 16 ),
			),
			'topo' => array(
				'color' => upw_bg_color_field( __( 'Line color', 'fw' ), 'bg', '#6aa6ff' ),
				'speed' => $speed( 6, 2, 16 ),
			),
			'circuit' => array(
				'color' => upw_bg_color_field( __( 'Trace color', 'fw' ), 'bg', '#00e5a0' ),
				'speed' => $speed( 6, 2, 16 ),
			),
			'halftone' => array(
				'color' => upw_bg_color_field( __( 'Dot color', 'fw' ), 'bg', '#6aa6ff' ),
				'gap'   => array( 'type' => 'slider', 'label' => __( 'Gap (px)', 'fw' ), 'value' => 16, 'properties' => array( 'min' => 10, 'max' => 40, 'step' => 2 ) ),
				'speed' => $speed( 6, 2, 16 ),
			),
			'blobs' => array(
				'color'  => upw_bg_color_field( __( 'Color 1', 'fw' ), 'bg', '#6a8dff' ),
				'color2' => upw_bg_color_field( __( 'Color 2', 'fw' ), 'bg', '#c56cff' ),
				'speed'  => $speed( 6, 2, 16 ),
			),
			'ripple' => array(
				'color' => upw_bg_color_field( __( 'Ripple color', 'fw' ), 'bg', '#6aa6ff' ),
				'speed' => $speed( 6, 2, 16 ),
			),
			'flow' => array(
				'color'   => upw_bg_color_field( __( 'Color', 'fw' ), 'bg', '#6aa6ff' ),
				'density' => array( 'type' => 'slider', 'label' => __( 'Density', 'fw' ), 'value' => 70, 'properties' => array( 'min' => 20, 'max' => 200, 'step' => 10 ) ),
				'speed'   => $speed( 6, 2, 16 ),
			),
			'matrix' => array(
				'color' => upw_bg_color_field( __( 'Rain color', 'fw' ), 'bg', '#19ff7a' ),
				'speed' => $speed( 6, 2, 16 ),
			),
			'nebula' => array(
				'color'  => upw_bg_color_field( __( 'Color 1', 'fw' ), 'bg', '#3b3fff' ),
				'color2' => upw_bg_color_field( __( 'Color 2', 'fw' ), 'bg', '#c56cff' ),
				'color3' => upw_bg_color_field( __( 'Color 3', 'fw' ), 'bg', '#00d4c8' ),
				'speed'  => $speed( 8, 3, 20 ),
			),
			'borealis' => array(
				'color'  => upw_bg_color_field( __( 'Color 1', 'fw' ), 'bg', '#3bffb0' ),
				'color2' => upw_bg_color_field( __( 'Color 2', 'fw' ), 'bg', '#6a8dff' ),
				'speed'  => $speed( 6, 2, 16 ),
			),
			'orbits' => array(
				'color'   => upw_bg_color_field( __( 'Color', 'fw' ), 'bg', '#6aa6ff' ),
				'density' => array( 'type' => 'slider', 'label' => __( 'Systems', 'fw' ), 'value' => 4, 'properties' => array( 'min' => 1, 'max' => 6, 'step' => 1 ) ),
				'speed'   => $speed( 6, 2, 16 ),
			),
			'spotlight' => array(
				'color' => upw_bg_color_field( __( 'Glow color', 'fw' ), 'bg', '#6aa6ff' ),
				'size'  => array( 'type' => 'slider', 'label' => __( 'Radius (px)', 'fw' ), 'value' => 260, 'properties' => array( 'min' => 100, 'max' => 500, 'step' => 20 ) ),
			),
		),
	);

	// Place the Background Effect right after the Background control (same group), so the
	// two sit together. Containers use different group ids (section: group_background,
	// bleed/masonry: group_styling), so locate the group that actually holds `background`.
	$placed = false;
	foreach ( $options['tab_styling']['options'] as &$grp ) {
		if ( is_array( $grp ) && isset( $grp['options'] ) && is_array( $grp['options'] ) && isset( $grp['options']['background'] ) ) {
			$grp['options']['bg_effect'] = $bg_field;
			$placed = true;
			break;
		}
	}
	unset( $grp );
	if ( ! $placed ) {
		$options['tab_styling']['options']['group_bg_effect'] = array(
			'type'    => 'group',
			'options' => array( 'bg_effect' => $bg_field ),
		);
	}

	return $options;
}, 20, 2 );
