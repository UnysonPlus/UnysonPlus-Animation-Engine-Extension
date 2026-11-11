<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Painted module: runtime.
 *
 * Stamps the chosen painting onto the element wrapper (`sc_build_wrapper_attr`) as one JSON attribute,
 * `data-upw-paint`, plus the class `upw-painted`; forces a wrapper when the painting is the element's
 * only setting; and registers the module's assets with the shared loader: a small base CSS and one
 * runtime (static/js/painted.js). The runtime loads the paint engine itself, lazily, from the Kinetic
 * Scene element's vendor folder (the same scene-paint.js, so a page with both loads it once).
 *
 * NOTE: uses UPW_PAINTED_DIR (not __DIR__) for the asset path, because this file lives in includes/.
 */

if ( ! function_exists( 'upw_painted_value' ) ) :
	/** The element's painting as the runtime's config, or null when it has none. */
	function upw_painted_value( $atts ) {
		$v    = ( isset( $atts['painted'] ) && is_array( $atts['painted'] ) ) ? $atts['painted'] : array();
		$mode = isset( $v['mode'] ) ? (string) $v['mode'] : 'none';
		$all  = upw_painted_styles();
		if ( ! isset( $all[ $mode ] ) ) {
			return null;
		}
		$s   = $all[ $mode ];
		$o   = ( isset( $v[ $mode ] ) && is_array( $v[ $mode ] ) ) ? $v[ $mode ] : array();
		$num = function ( $k, $def, $min, $max ) use ( $o ) {
			$n = isset( $o[ $k ] ) && is_numeric( $o[ $k ] ) ? (float) $o[ $k ] : (float) $def;
			return round( max( $min, min( $max, $n ) ), 3 );
		};
		$media  = array( 'pencil', 'ink', 'fineliner', 'marker', 'charcoal', 'crayon', 'pastel', 'coloured pencil' );
		$medium = isset( $o['medium'] ) && in_array( $o['medium'], $media, true ) ? $o['medium'] : $s['medium'];
		$color  = upw_painted_hex( isset( $o['color'] ) ? $o['color'] : '', $s['color'] );
		$fill   = $s['role'] === 'fill';
		$cfg    = array(
			'style'    => $mode,
			'medium'   => $medium,
			'size'     => $num( 'size', $s['size'], 0.2, 6 ),
			'strength' => $num( 'strength', isset( $s['strength'] ) ? $s['strength'] : 1, 0.1, 1 ),
			'spread'   => $num( 'spread', $s['spread'], 0, 80 ),
			'duration' => $num( 'duration', 1.2, 0, 4 ),
			'delay'    => $num( 'delay', 0, 0, 10 ),
			'front'    => ( isset( $o['layer'] ) && 'front' === $o['layer'] ) ? 1 : 0,
			'seed'     => (int) $num( 'seed', 0, 0, 9999 ),
			'fit'      => ( isset( $o['fit'] ) && in_array( $o['fit'], array( 'content', 'element' ), true ) ) ? $o['fit'] : ( in_array( $mode, array( 'highlight', 'underline', 'circle' ), true ) ? 'content' : 'element' ),
		);
		if ( $fill ) {
			$cfg['fillColor'] = $color;
			$cfg['line']      = ( isset( $o['outline'] ) ? $o['outline'] : $s['outline'] ) === 'yes' ? 1 : 0;
			$cfg['lineColor'] = upw_painted_hex( isset( $o['line_color'] ) ? $o['line_color'] : '', '#1c1e22' );
		} else {
			$cfg['line']      = 1;
			$cfg['lineColor'] = $color;
		}
		if ( 'wash' === $mode ) {
			$cfg['bleed']   = $num( 'bleed', $s['bleed'], 0, 1 );
			$cfg['texture'] = $num( 'texture', 0.5, 0, 1 );
		}
		if ( 'hatch' === $mode ) {
			$cfg['gap']   = $num( 'gap', 7, 2, 30 );
			$cfg['angle'] = $num( 'angle', -35, -90, 90 );
		}
		return $cfg;
	}
endif;

add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	if ( ! upw_painted_enabled() ) {
		return $attr;
	}
	$cfg = upw_painted_value( $atts );
	if ( null === $cfg ) {
		return $attr;
	}
	$cls                     = isset( $attr['class'] ) ? trim( (string) $attr['class'] ) : '';
	$attr['class']           = esc_attr( trim( $cls . ' upw-painted' ) );
	$attr['data-upw-paint']  = wp_json_encode( $cfg ); // escaped once, when the wrapper prints its attributes
	if ( function_exists( 'upw_anim_use_asset' ) ) {
		upw_anim_use_asset( 'painted', $cfg['style'] );
	}
	return $attr;
}, 22, 2 );

add_filter( 'sc_needs_wrapper', function ( $needs, $atts ) {
	if ( $needs || ! upw_painted_enabled() ) {
		return $needs;
	}
	return null !== upw_painted_value( $atts );
}, 10, 2 );

if ( function_exists( 'upw_anim_register_assets' ) ) {
	$upw_pt_ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( $upw_pt_ext ) {
		upw_anim_register_assets( 'painted', array(
			'path'      => UPW_PAINTED_DIR,
			'uri'       => $upw_pt_ext->get_declared_URI( '/modules/painted' ),
			'ver'       => $upw_pt_ext->manifest->get_version(),
			'css_dir'   => 'static/css/effects',
			'js_dir'    => 'static/js/effects',
			'base_css'  => 'static/css/painted.css',
			'base_js'   => 'static/js/painted.js',
			// Every style runs on the one runtime (no per-style JS partials).
			'js_styles' => array_keys( upw_painted_styles() ),
			'needs_raf' => true,
			'js_cfg'    => function () use ( $upw_pt_ext ) {
				/** Filters the URL of the paint engine (p5.brush) the Painted effect loads. */
				$engine = apply_filters( 'upw_painted_engine_src', $upw_pt_ext->get_declared_URI( '/shortcodes/studio-scene/static/js/vendor/scene-paint.js' ) );
				$cfg    = array(
					'engine'        => esc_url_raw( $engine ),
					'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
				);
				return 'window.upwPaintedCfg=' . wp_json_encode( $cfg ) . ';';
			},
		) );
	}
	unset( $upw_pt_ext );
}
