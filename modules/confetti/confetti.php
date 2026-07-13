<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Confetti module.
 *
 * Fires a celebratory particle burst from an element on a trigger (scroll-into-view, click, page
 * load or hover) — confetti, stars, fireworks, streamers, hearts or snow. Per-element (attaches from
 * the Animations tab). One shared full-viewport <canvas> renders every burst; pure Canvas 2D, no
 * library. Honours "reduce motion" (no burst) and loads only on pages that use it.
 *
 * Saved value shape (multi-picker, picker id `style`):
 *   [ 'style' => 'none'|'<style>', '<style>' => [ trigger, count, spread, power, duration, palette, replay ] ]
 */

if ( ! function_exists( 'upw_confetti_enabled' ) ) :
	function upw_confetti_enabled() {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return true;
		}
		$v = fw_get_db_settings_option( 'animation_confetti', array() );
		$e = ( is_array( $v ) && isset( $v['enable'] ) ) ? $v['enable'] : 'yes';
		return $e !== 'no' && $e !== false;
	}
endif;

if ( ! function_exists( 'upw_confetti_styles' ) ) :
	function upw_confetti_styles() {
		return array(
			'confetti'  => __( 'Confetti', 'fw' ),
			'stars'     => __( 'Stars', 'fw' ),
			'fireworks' => __( 'Fireworks', 'fw' ),
			'streamers' => __( 'Streamers', 'fw' ),
			'hearts'    => __( 'Hearts', 'fw' ),
			'snow'      => __( 'Snow', 'fw' ),
		);
	}
endif;

/* 1) The per-element "Confetti" control, appended to the Animations tab. */
add_filter( 'sc_animation_fields', function ( $fields ) {
	if ( ! is_array( $fields ) || ! upw_confetti_enabled() ) {
		return $fields;
	}

	$ext  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	$base = $ext ? $ext->get_declared_URI( '/modules/confetti/static/img' ) : '';
	$tile = function ( $file, $label ) use ( $base ) {
		return array(
			'small' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 53 ),
			'large' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 132 ),
			'label' => $label,
		);
	};

	// Trigger tiles — the SHARED multi-select trigger UI (same tiles + tooltip styling as the
	// Entrance Animation's Trigger). Tiles live in the shortcodes extension; the animation-stack
	// container's CSS gives any image-picker inside a card's reveal the icon+tooltip treatment.
	$sc_ext    = function_exists( 'fw_ext' ) ? fw_ext( 'shortcodes' ) : null;
	$trig_base = $sc_ext ? $sc_ext->get_declared_URI( '/static/img/triggers' ) : '';
	$trig_tile = function ( $key, $label ) use ( $trig_base ) {
		return array( 'small' => array( 'src' => $trig_base . '/' . $key . '.svg', 'height' => 30, 'title' => $label ), 'label' => $label );
	};

	// Shared option group revealed for every style.
	$opts = array(
		'trigger' => array(
			'type'       => 'image-picker',
			'multiple'   => true,
			'show_label' => false,
			'label'      => __( 'Fire on', 'fw' ),
			'desc'       => __( 'What sets off the burst — pick one or more (e.g. on scroll AND on click).', 'fw' ),
			'value'      => array( 'view' ),
			'choices'    => array(
				'view'  => $trig_tile( 'view',  __( 'Scroll into view', 'fw' ) ),
				'click' => $trig_tile( 'click', __( 'Click', 'fw' ) ),
				'load'  => $trig_tile( 'load',  __( 'Page load', 'fw' ) ),
				'hover' => $trig_tile( 'hover', __( 'Hover', 'fw' ) ),
			),
		),
		'count'    => array( 'type' => 'slider', 'label' => __( 'Particle count', 'fw' ), 'value' => 90, 'properties' => array( 'min' => 20, 'max' => 400, 'step' => 10 ) ),
		'spread'   => array( 'type' => 'slider', 'label' => __( 'Spread (°)', 'fw' ), 'desc' => __( 'How wide the burst fans out (360 = all directions).', 'fw' ), 'value' => 70, 'properties' => array( 'min' => 20, 'max' => 360, 'step' => 5 ) ),
		'power'    => array( 'type' => 'slider', 'label' => __( 'Power', 'fw' ), 'desc' => __( 'Initial launch velocity.', 'fw' ), 'value' => 45, 'properties' => array( 'min' => 15, 'max' => 100, 'step' => 5 ) ),
		'duration' => array( 'type' => 'slider', 'label' => __( 'Lifetime (s)', 'fw' ), 'value' => 3, 'properties' => array( 'min' => 1, 'max' => 7, 'step' => 0.5 ) ),
		'palette'  => array(
			'type'    => 'select',
			'label'   => __( 'Colours', 'fw' ),
			'value'   => 'brand',
			'choices' => array(
				'brand'   => __( 'Brand', 'fw' ),
				'rainbow' => __( 'Rainbow', 'fw' ),
				'gold'    => __( 'Gold', 'fw' ),
				'pastel'  => __( 'Pastel', 'fw' ),
				'mono'    => __( 'Monochrome', 'fw' ),
			),
		),
		'replay'   => array(
			'type'         => 'switch',
			'label'        => __( 'Replay on scroll', 'fw' ),
			'desc'         => __( 'Re-fire every time the element re-enters the viewport (Scroll-into-view trigger).', 'fw' ),
			'value'        => 'no',
			'left-choice'  => array( 'value' => 'no',  'label' => __( 'Off', 'fw' ) ),
			'right-choice' => array( 'value' => 'yes', 'label' => __( 'On', 'fw' ) ),
		),
	);

	$choices_tiles = array();
	$reveal        = array( 'none' => array() );
	foreach ( upw_confetti_styles() as $k => $lbl ) {
		$choices_tiles[ $k ] = $tile( $k, $lbl );
		$reveal[ $k ]        = array( 'group_confetti_' . $k => array( 'type' => 'group', 'options' => $opts ) );
	}

	$fields['confetti'] = array(
		'type'         => 'multi-picker',
		'popover'      => true,
		'label'        => __( 'Confetti', 'fw' ),
		'desc'         => __( 'A celebratory particle burst fired from this element on a trigger.', 'fw' ),
		'help'         => __( 'Confetti (Animation Engine): a Canvas 2D particle burst — confetti, stars, fireworks, streamers, hearts or snow — fired on scroll-into-view, click, load or hover. One shared viewport canvas, no library. Honours "reduce motion" and loads only on pages that use it.', 'fw' ) . ( function_exists( 'upw_perf_note' ) ? ' ' . upw_perf_note() : '' ),
		'show_borders' => false,
		'value'        => array( 'style' => 'none' ),
		'placeholder'  => __( 'None', 'fw' ),
		'anim_meta'    => array( 'category' => __( 'Ambient', 'fw' ), 'icon' => '&#127881;' ), // 🎉
		'picker'       => array(
			'style' => array(
				'type'       => 'image-picker',
				'label'      => false,
				'desc'       => __( 'Hover a tile to preview it larger.', 'fw' ),
				'value'      => 'none',
				'search'     => __( 'Search confetti styles…', 'fw' ),
				'choices'    => $choices_tiles,
			),
		),
		'choices' => $reveal,
	);

	return $fields;
}, 10 );

/* 2) Emit the confetti settings onto the element wrapper. */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	if ( ! upw_confetti_enabled() ) {
		return $attr;
	}
	$cf    = ( isset( $atts['confetti'] ) && is_array( $atts['confetti'] ) ) ? $atts['confetti'] : array();
	$style = isset( $cf['style'] ) ? (string) $cf['style'] : 'none';
	if ( ! array_key_exists( $style, upw_confetti_styles() ) ) {
		return $attr;
	}
	$o = ( isset( $cf[ $style ] ) && is_array( $cf[ $style ] ) ) ? $cf[ $style ] : array();

	$cls           = isset( $attr['class'] ) ? trim( (string) $attr['class'] ) : '';
	$attr['class'] = esc_attr( trim( $cls . ' sc-confetti' ) );

	// Trigger is now MULTI-SELECT (array). Tolerate the legacy scalar save + empty → default 'view'.
	$raw_trigger = isset( $o['trigger'] ) ? $o['trigger'] : array( 'view' );
	$triggers    = is_array( $raw_trigger ) ? $raw_trigger : ( $raw_trigger === '' ? array() : array( (string) $raw_trigger ) );
	$triggers    = array_values( array_intersect( array_map( 'strval', $triggers ), array( 'view', 'click', 'load', 'hover' ) ) );
	if ( empty( $triggers ) ) { $triggers = array( 'view' ); }
	$palette = isset( $o['palette'] ) && in_array( $o['palette'], array( 'brand', 'rainbow', 'gold', 'pastel', 'mono' ), true ) ? $o['palette'] : 'brand';

	$attr['data-cf-style']    = esc_attr( $style );
	$attr['data-cf-trigger']  = esc_attr( implode( ' ', $triggers ) );
	$attr['data-cf-count']    = esc_attr( max( 20, min( 400, (int) ( $o['count'] ?? 90 ) ) ) );
	$attr['data-cf-spread']   = esc_attr( max( 20, min( 360, (int) ( $o['spread'] ?? 70 ) ) ) );
	$attr['data-cf-power']    = esc_attr( max( 15, min( 100, (int) ( $o['power'] ?? 45 ) ) ) );
	$attr['data-cf-duration'] = esc_attr( max( 1, min( 7, (float) ( $o['duration'] ?? 3 ) ) ) );
	$attr['data-cf-palette']  = esc_attr( $palette );
	if ( isset( $o['replay'] ) && $o['replay'] === 'yes' ) {
		$attr['data-cf-replay'] = '1';
	}

	if ( function_exists( 'upw_anim_use_asset' ) ) {
		upw_anim_use_asset( 'confetti', $style );
	}
	return $attr;
}, 22, 2 );

/* 2b) Force a wrapper when an element's ONLY non-default setting is a confetti burst. */
add_filter( 'sc_needs_wrapper', function ( $needs, $atts ) {
	if ( $needs || ! upw_confetti_enabled() ) {
		return $needs;
	}
	$cf    = ( isset( $atts['confetti'] ) && is_array( $atts['confetti'] ) ) ? $atts['confetti'] : array();
	$style = isset( $cf['style'] ) ? (string) $cf['style'] : 'none';
	return array_key_exists( $style, upw_confetti_styles() );
}, 10, 2 );

/* 3) On-demand assets — shared base CSS + the single runtime. */
if ( function_exists( 'upw_anim_register_assets' ) ) {
	$upw_cf_ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( $upw_cf_ext ) {
		upw_anim_register_assets( 'confetti', array(
			'path'      => __DIR__,
			'uri'       => $upw_cf_ext->get_declared_URI( '/modules/confetti' ),
			'ver'       => $upw_cf_ext->manifest->get_version(),
			'css_dir'   => 'static/css/effects', // no per-style partials; only the shared base loads
			'js_dir'    => 'static/js/effects',
			'base_css'  => 'static/css/confetti.css',
			'base_js'   => 'static/js/confetti.js',
			'js_styles' => array_keys( upw_confetti_styles() ), // every style needs the one runtime
			'js_cfg'    => function () {
				$cfg = array(
					'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
				);
				return 'window.upwConfettiCfg=' . wp_json_encode( $cfg ) . ';';
			},
		) );
	}
	unset( $upw_cf_ext );
}
