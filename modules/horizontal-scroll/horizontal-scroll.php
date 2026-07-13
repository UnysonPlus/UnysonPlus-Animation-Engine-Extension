<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Horizontal Scroll Section module.
 *
 * Pins a Section for the duration of a scroll and translates its cards (columns) sideways, so the
 * page scrolls DOWN while the content moves ACROSS — the classic gallery / timeline / feature-strip
 * effect. Section-level, injected only into the Section's Animations tab (like Scroll Loop), landing
 * inside the animation-stack organizer as its own card + inserter tile.
 *
 * Pure CSS `position:sticky` + one passive scroll listener for the translate — no library. Assets
 * load only on pages that use it. Global on/off: Theme Settings → Animations → Effects.
 *
 * Saved value shape (multi-picker, picker id `mode`):
 *   [ 'mode' => 'off'|'on', 'on' => [ 'panel_width' => '80vw' ] ]
 */

if ( ! function_exists( 'upw_hscroll_enabled' ) ) :
	function upw_hscroll_enabled() {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return true;
		}
		$v = fw_get_db_settings_option( 'animation_hscroll', array() );
		$e = ( is_array( $v ) && isset( $v['enable'] ) ) ? $v['enable'] : 'yes';
		return $e !== 'no' && $e !== false;
	}
endif;

/** The valid horizontal-scroll style keys (shared by the wrapper filter + needs-wrapper). */
if ( ! function_exists( 'upw_hscroll_styles' ) ) :
	function upw_hscroll_styles() {
		return array( 'standard', 'reverse', 'snap', 'parallax', 'fade', 'coverflow', 'blur', 'grow', 'arc', 'wave', 'zigzag', 'rotate3d', 'wall', 'skew', 'drag' );
	}
endif;

if ( ! function_exists( 'sc_get_hscroll_fields' ) ) :
	function sc_get_hscroll_fields() {
		$ext  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
		$base = $ext ? $ext->get_declared_URI( '/modules/horizontal-scroll/static/img' ) : '';
		$tile = function ( $file, $label ) use ( $base ) {
			return array(
				'small' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 53 ),
				'large' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 132 ),
				'label' => $label,
			);
		};

		// One shared options group for every style: panel width + a single Intensity knob that the
		// JS maps to each style's magnitude (scale / tilt / skew / parallax / …).
		$opts = array(
			'panel_width' => array(
				'type'    => 'select',
				'label'   => __( 'Panel width', 'fw' ),
				'desc'    => __( 'How wide each card is as it scrolls across.', 'fw' ),
				'value'   => '80vw',
				'choices' => array(
					'auto'  => __( 'Natural (content width)', 'fw' ),
					'60vw'  => __( 'Narrow (60% of viewport)', 'fw' ),
					'80vw'  => __( 'Wide (80% of viewport)', 'fw' ),
					'100vw' => __( 'Full screen (100%)', 'fw' ),
				),
			),
			'intensity' => array(
				'type'       => 'slider',
				'label'      => __( 'Intensity', 'fw' ),
				'desc'       => __( 'Strength of the chosen style — how much the panels scale / tilt / skew / parallax.', 'fw' ),
				'value'      => 0.5,
				'properties' => array( 'min' => 0, 'max' => 1, 'step' => 0.05 ),
			),
		);

		$styles = array(
			'standard'  => __( 'Standard', 'fw' ),
			'reverse'   => __( 'Reverse', 'fw' ),
			'snap'      => __( 'Snap', 'fw' ),
			'parallax'  => __( 'Parallax', 'fw' ),
			'fade'      => __( 'Fade', 'fw' ),
			'coverflow' => __( 'Center Focus', 'fw' ),
			'blur'      => __( 'Blur Focus', 'fw' ),
			'grow'      => __( 'Grow In', 'fw' ),
			'arc'       => __( 'Arc', 'fw' ),
			'wave'      => __( 'Wave', 'fw' ),
			'zigzag'    => __( 'Zigzag', 'fw' ),
			'rotate3d'  => __( '3D Carousel', 'fw' ),
			'wall'      => __( 'Perspective Wall', 'fw' ),
			'skew'      => __( 'Velocity Skew', 'fw' ),
			'drag'      => __( 'Drag / Flick', 'fw' ),
		);

		$tiles   = array(  );
		$choices = array();
		foreach ( $styles as $key => $label ) {
			$tiles[ $key ]   = $tile( $key, $label );
			$choices[ $key ] = array( 'group_hscroll_' . $key => array( 'type' => 'group', 'options' => $opts ) );
		}

		// Alphabetize picker tiles by label (None/Off first) for easier scanning.
		uksort( $tiles, function ( $a, $b ) use ( $tiles ) {
			$rank = function ( $k ) { if ( $k === 'none' || $k === 'off' ) { return 0; } return 1; };
			$ra = $rank( $a ); $rb = $rank( $b );
			if ( $ra !== $rb ) { return $ra - $rb; }
			$la = isset( $tiles[ $a ]['label'] ) ? $tiles[ $a ]['label'] : $a;
			$lb = isset( $tiles[ $b ]['label'] ) ? $tiles[ $b ]['label'] : $b;
			return strcasecmp( (string) $la, (string) $lb );
		} );

		return array(
			'horizontal_scroll' => array(
				'type'         => 'multi-picker',
				'label'        => __( 'Horizontal Scroll', 'fw' ),
				'desc'         => __( 'Pin this Section and move its cards (columns) sideways as the visitor scrolls down — a gallery / timeline strip. Pick a style, then tune it. Build the Section with 2+ columns as the panels.', 'fw' ),
				'help'         => __( 'Horizontal Scroll Section (Animation Engine): pins the Section and translates its panel row across on scroll, in several styles — standard, reverse, snap, parallax, center-focus, 3D carousel and velocity-skew — plus a free drag-through mode. Pure sticky + one passive scroll listener, no library. Honours "reduce motion" (panels flow normally) and loads only on pages that use it. Section only.', 'fw' ) . ( function_exists( 'upw_perf_note' ) ? ' ' . upw_perf_note() : '' ),
				'popover'      => true,
				'show_borders' => false,
				'value'        => array( 'mode' => 'off' ),
				'placeholder'  => __( 'Off', 'fw' ),
				'anim_meta'    => array( 'category' => __( 'Scroll', 'fw' ) ),
				'picker'       => array(
					'mode' => array(
						'type'    => 'image-picker',
						'label'   => false,
						'desc'    => __( 'Hover a tile to preview it larger.', 'fw' ),
						'value'   => 'off',
						'search'  => __( 'Search effects…', 'fw' ),
						'choices' => $tiles,
					),
				),
				'choices' => $choices,
			),
		);
	}
endif;

/**
 * Inject into the SECTION's Animations tab only, inside the animation-stack organizer.
 */
add_filter( 'fw_shortcode_get_options', function ( $options, $tag = '' ) {
	if ( $tag !== 'section' || ! is_array( $options ) || ! function_exists( 'sc_get_hscroll_fields' ) || ! upw_hscroll_enabled() ) {
		return $options;
	}
	if ( ! isset( $options['tab_animation']['options'] ) || ! is_array( $options['tab_animation']['options'] ) ) {
		return $options;
	}
	$tab =& $options['tab_animation']['options'];
	if ( isset( $tab['animation_stack']['options'] ) && is_array( $tab['animation_stack']['options'] ) ) {
		$tab['animation_stack']['options'] = array_merge( $tab['animation_stack']['options'], sc_get_hscroll_fields() );
	} else {
		$tab = array_merge( $tab, sc_get_hscroll_fields() );
	}
	unset( $tab );
	return $options;
}, 10, 2 );

/**
 * Stamp the horizontal-scroll data-attributes onto the section wrapper.
 */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	if ( ! upw_hscroll_enabled() ) {
		return $attr;
	}
	$h    = ( isset( $atts['horizontal_scroll'] ) && is_array( $atts['horizontal_scroll'] ) ) ? $atts['horizontal_scroll'] : array();
	$mode = isset( $h['mode'] ) ? (string) $h['mode'] : 'off';
	if ( ! in_array( $mode, upw_hscroll_styles(), true ) ) {
		return $attr;
	}
	$o     = ( isset( $h[ $mode ] ) && is_array( $h[ $mode ] ) ) ? $h[ $mode ] : array();
	$panel = isset( $o['panel_width'] ) && in_array( $o['panel_width'], array( 'auto', '60vw', '80vw', '100vw' ), true ) ? $o['panel_width'] : '80vw';

	$cls           = isset( $attr['class'] ) ? trim( (string) $attr['class'] ) : '';
	$attr['class'] = esc_attr( trim( $cls . ' upw-hscroll' ) );
	$attr['data-hs-style']     = esc_attr( $mode );
	$attr['data-hs-panel']     = esc_attr( $panel );
	$attr['data-hs-intensity'] = esc_attr( (string) ( isset( $o['intensity'] ) ? (float) $o['intensity'] : 0.5 ) );

	// On-demand assets: record this style so ONLY its per-panel partial (if any) loads with the
	// core; the track-level styles (standard/reverse/snap/wall/skew/drag) need only the core.
	if ( function_exists( 'upw_anim_use_asset' ) ) {
		upw_anim_use_asset( 'horizontal-scroll', $mode );
	}
	return $attr;
}, 24, 2 );

/**
 * Force a wrapper when a section's ONLY non-default setting is horizontal scroll.
 */
add_filter( 'sc_needs_wrapper', function ( $needs, $atts ) {
	if ( $needs || ! upw_hscroll_enabled() ) {
		return $needs;
	}
	$h = ( isset( $atts['horizontal_scroll'] ) && is_array( $atts['horizontal_scroll'] ) ) ? $atts['horizontal_scroll'] : array();
	return ( isset( $h['mode'] ) && in_array( $h['mode'], upw_hscroll_styles(), true ) );
}, 10, 2 );

/**
 * On-demand assets — register the per-panel-style partial layout with the shared loader; a page
 * ships the shared core + CSS base + ONLY the used style's per-panel partial (track-level styles
 * need no partial). Recorded per element in the wrapper filter via upw_anim_use_asset().
 */
if ( function_exists( 'upw_anim_register_assets' ) ) {
	$upw_hs_ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( $upw_hs_ext ) {
		upw_anim_register_assets( 'horizontal-scroll', array(
			'path'      => __DIR__,
			'uri'       => $upw_hs_ext->get_declared_URI( '/modules/horizontal-scroll' ),
			'ver'       => $upw_hs_ext->manifest->get_version(),
			'js_dir'    => 'static/js/effects',
			'base_css'  => 'static/css/horizontal-scroll.css', // small, all-base (pin/track/panel) — no per-style CSS
			'base_js'   => 'static/js/horizontal-scroll-core.js',
			'needs_raf' => true,
			// All styles trigger the core; only the 9 per-panel styles have a partial file (the
			// track-level standard/reverse/snap/wall/skew/drag are handled inside the core).
			'js_styles' => upw_hscroll_styles(),
			'js_cfg'    => function () {
				$cfg = array(
					'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
					'disableMobile' => ( function_exists( 'upw_anim_engine_setting' ) && upw_anim_engine_setting( 'disable_on_mobile', 'no' ) === 'yes' ),
				);
				return 'window.upwHScrollCfg=' . wp_json_encode( $cfg ) . ';';
			},
		) );
	}
	unset( $upw_hs_ext );
}
