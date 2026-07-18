<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Sticky Card Stack module.
 *
 * Turns a Section's stacked child cards (its columns) into the "deck of cards" scroll effect
 * (Apple / Stripe): each card pins to the top in turn, and as the next card slides up over it the
 * covered card eases down in scale for depth. Section-level, so it is injected only into the
 * Section's Animations tab (like the Infinite Scroll Loop module), landing inside the
 * animation-stack organizer as its own card + inserter tile.
 *
 * Pure CSS `position:sticky` + one passive scroll listener for the scale — no library. Assets load
 * only on pages that use it. Global on/off: Theme Settings → Animations → Effects.
 *
 * Saved value shape (multi-picker, picker id `mode`):
 *   [ 'mode' => 'off'|'stack', 'stack' => [ 'top_offset' => 40, 'gap' => 18, 'scale_step' => 0.05 ] ]
 */

if ( ! function_exists( 'upw_sticky_stack_enabled' ) ) :
	function upw_sticky_stack_enabled() {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return true;
		}
		$v = fw_get_db_settings_option( 'animation_sticky_stack', array() );
		$e = ( is_array( $v ) && isset( $v['enable'] ) ) ? $v['enable'] : 'yes';
		return $e !== 'no' && $e !== false;
	}
endif;

/**
 * The Sticky Card Stack control (Section only). A popover image-picker multi-picker keyed
 * `sticky_stack`, picker id `mode`, so it stays compact like the other engine controls.
 */
if ( ! function_exists( 'sc_get_sticky_stack_fields' ) ) :
	function sc_get_sticky_stack_fields() {
		$ext  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
		$base = $ext ? $ext->get_declared_URI( '/modules/sticky-stack/static/img' ) : '';
		$tile = function ( $file, $label ) use ( $base ) {
			return array(
				'small' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 53 ),
				'large' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 132 ),
				'label' => $label,
			);
		};
		$slider = function ( $label, $val, $min, $max, $step, $desc ) {
			return array( 'type' => 'slider', 'label' => $label, 'desc' => $desc, 'value' => $val, 'properties' => array( 'min' => $min, 'max' => $max, 'step' => $step ) );
		};

		// One shared options group for every style. A single "Intensity" knob drives whatever the
		// chosen style does (scale / dim / blur / tilt angle / fan spread / offset / …).
		$opts = array(
			'top_offset' => $slider( __( 'Pin offset (px)', 'fw' ), 40, 0, 200, 4, __( 'Gap from the top of the viewport where each card pins.', 'fw' ) ),
			'gap'        => $slider( __( 'Stagger (px)', 'fw' ), 18, 0, 80, 2, __( 'How much each stacked card peeks below the one above.', 'fw' ) ),
			'intensity'  => $slider( __( 'Intensity', 'fw' ), 0.5, 0, 1, 0.05, __( 'Strength of the chosen style — how much the covered cards scale / dim / blur / tilt, how wide the deck fans, etc.', 'fw' ) ),
		);

		// The 11 styles (key => label). Each maps to the same options group and a transform recipe
		// in sticky-stack.js.
		$styles = array(
			'stack'      => __( 'Card Stack', 'fw' ),
			'scale_fade' => __( 'Scale & Fade', 'fw' ),
			'fade'       => __( 'Fade Under', 'fw' ),
			'blur'       => __( 'Blur Under', 'fw' ),
			'tilt'       => __( '3D Tilt Back', 'fw' ),
			'fan'        => __( 'Fan Deck', 'fw' ),
			'messy'      => __( 'Rotate Messy', 'fw' ),
			'side'       => __( 'Side Offset', 'fw' ),
			'peel'       => __( 'Peel Away', 'fw' ),
			'push'       => __( 'Push Conveyor', 'fw' ),
			'grow'       => __( 'Grow In', 'fw' ),
		);

		$tiles   = array(  );
		$choices = array();
		foreach ( $styles as $key => $label ) {
			$tiles[ $key ]   = $tile( $key, $label );
			$choices[ $key ] = array( 'group_sticky_stack_' . $key => array( 'type' => 'group', 'options' => $opts ) );
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
			'sticky_stack' => array(
				'type'         => 'multi-picker',
				'label'        => __( 'Card Stack', 'fw' ),
				'desc'         => __( 'Pin this Section\'s cards (its columns) one after another as you scroll — a stacking "deck of cards". Pick a style, then tune it. Build the Section with 2+ full-width columns as the cards.', 'fw' ),
				'help'         => __( 'Card Stack (Animation Engine): the Apple / Stripe "deck of cards" scroll effect, in 11 styles — Card Stack, Scale & Fade, Fade / Blur / 3D-Tilt under, Fan, Messy, Side offset, Peel away, Push conveyor and Grow in. Each direct card of the Section is position:sticky and pins in turn; a passive scroll listener transforms the cards per style. No library. Honours "reduce motion" (cards just stack normally) and loads only on pages that use it. Section only — it needs its cards stacked vertically, so it isn\'t offered on grid collections like the Gallery.', 'fw' ) . ( function_exists( 'upw_perf_note' ) ? ' ' . upw_perf_note() : '' ),
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

/** The valid style keys (shared by the wrapper filter + needs-wrapper). */
if ( ! function_exists( 'upw_sticky_stack_styles' ) ) :
	function upw_sticky_stack_styles() {
		return array( 'stack', 'scale_fade', 'fade', 'blur', 'tilt', 'fan', 'messy', 'side', 'peel', 'push', 'grow' );
	}
endif;

/**
 * Inject the Card Stack control into the SECTION's Animations tab only, inside the animation-stack
 * organizer (mirrors the Scroll Loop module) so it becomes a card + inserter tile like the other
 * modules. Section only: the effect stacks cards VERTICALLY (position:sticky), which suits a
 * Section's full-width columns but not a grid collection (Gallery, …).
 */
add_filter( 'fw_shortcode_get_options', function ( $options, $tag = '' ) {
	if ( $tag !== 'section' || ! is_array( $options ) || ! function_exists( 'sc_get_sticky_stack_fields' ) || ! upw_sticky_stack_enabled() ) {
		return $options;
	}
	if ( ! isset( $options['tab_animation']['options'] ) || ! is_array( $options['tab_animation']['options'] ) ) {
		return $options;
	}
	$tab =& $options['tab_animation']['options'];
	if ( isset( $tab['animation_stack']['options'] ) && is_array( $tab['animation_stack']['options'] ) ) {
		$tab['animation_stack']['options'] = array_merge( $tab['animation_stack']['options'], sc_get_sticky_stack_fields() );
	} else {
		$tab = array_merge( $tab, sc_get_sticky_stack_fields() );
	}
	unset( $tab );
	return $options;
}, 10, 2 );

/**
 * Stamp the stack data-attributes onto the section wrapper.
 */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	if ( ! upw_sticky_stack_enabled() ) {
		return $attr;
	}
	$s    = ( isset( $atts['sticky_stack'] ) && is_array( $atts['sticky_stack'] ) ) ? $atts['sticky_stack'] : array();
	$mode = isset( $s['mode'] ) ? (string) $s['mode'] : 'off';
	if ( ! in_array( $mode, upw_sticky_stack_styles(), true ) ) {
		return $attr;
	}
	// Card Stack is a Section-only vertical-stack effect — never stamp a grid COLLECTION element
	// (Gallery, …), so any stale sticky_stack atts left from earlier testing render nothing.
	if ( function_exists( 'sc_anim_collection_items' ) ) {
		$base = isset( $atts['base_class'] ) ? (string) $atts['base_class'] : '';
		$map  = sc_anim_collection_items();
		if ( $base !== '' && isset( $map[ $base ] ) ) {
			return $attr;
		}
	}

	$o = ( isset( $s[ $mode ] ) && is_array( $s[ $mode ] ) ) ? $s[ $mode ] : array();

	$cls           = isset( $attr['class'] ) ? trim( (string) $attr['class'] ) : '';
	$attr['class'] = esc_attr( trim( $cls . ' upw-sticky-stack' ) );

	$attr['data-ss-style']     = esc_attr( $mode );
	$attr['data-ss-offset']    = esc_attr( (string) ( isset( $o['top_offset'] ) ? (float) $o['top_offset'] : 40 ) );
	$attr['data-ss-gap']       = esc_attr( (string) ( isset( $o['gap'] ) ? (float) $o['gap'] : 18 ) );
	$attr['data-ss-intensity'] = esc_attr( (string) ( isset( $o['intensity'] ) ? (float) $o['intensity'] : 0.5 ) );

	// On-demand assets: record this style so ONLY its per-card partial loads with the core.
	if ( function_exists( 'upw_anim_use_asset' ) ) {
		upw_anim_use_asset( 'sticky-stack', $mode );
	}
	return $attr;
}, 24, 2 );

/**
 * Force a wrapper when a section's ONLY non-default setting is the card stack.
 */
add_filter( 'sc_needs_wrapper', function ( $needs, $atts ) {
	if ( $needs || ! upw_sticky_stack_enabled() ) {
		return $needs;
	}
	$s = ( isset( $atts['sticky_stack'] ) && is_array( $atts['sticky_stack'] ) ) ? $atts['sticky_stack'] : array();
	return ( isset( $s['mode'] ) && in_array( $s['mode'], upw_sticky_stack_styles(), true ) );
}, 10, 2 );

/**
 * On-demand assets — register the per-style partial layout with the shared loader; a page ships
 * the shared core + CSS base + ONLY the used style's per-card partial. Recorded per element in
 * the wrapper filter via upw_anim_use_asset().
 */
if ( function_exists( 'upw_anim_register_assets' ) ) {
	$upw_ss_ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( $upw_ss_ext ) {
		upw_anim_register_assets( 'sticky-stack', array(
			'path'      => __DIR__,
			'uri'       => $upw_ss_ext->get_declared_URI( '/modules/sticky-stack' ),
			'ver'       => $upw_ss_ext->manifest->get_version(),
			'js_dir'    => 'static/js/effects',
			'base_css'  => 'static/css/sticky-stack.css', // tiny, all-base (sticky card) — no per-style CSS
			'base_js'   => 'static/js/sticky-stack-core.js',
			'js_styles' => upw_sticky_stack_styles(), // every style has a per-card partial
			'js_cfg'    => function () {
				$cfg = array(
					'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
					'disableMobile' => ( function_exists( 'upw_anim_engine_setting' ) && upw_anim_engine_setting( 'disable_on_mobile', 'no' ) === 'yes' ),
				);
				return 'window.upwStickyStackCfg=' . wp_json_encode( $cfg ) . ';';
			},
		) );
	}
	unset( $upw_ss_ext );
}
