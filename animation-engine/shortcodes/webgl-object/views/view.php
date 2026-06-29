<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/** @var array $atts */

if ( ! function_exists( 'sc_get' ) ) {
	function sc_get( $path, $atts, $default = '' ) {
		if ( function_exists( 'fw_akg' ) ) {
			$v = fw_akg( $path, $atts, null );
			if ( $v !== null ) {
				return $v;
			}
		}
		return $default;
	}
}

if ( ! function_exists( 'sc_webgl_object_render' ) ) {
	function sc_webgl_object_render( $atts ) {
		$preset      = sc_get( 'style_preset/preset', $atts, 'glass' );
		$preset_opts = sc_get( 'style_preset/' . $preset, $atts, array() );
		if ( ! is_array( $preset_opts ) ) {
			$preset_opts = array();
		}

		// Image Distortion shader: resolve the uploaded image to a URL the JS can load
		// (the `upload` option stores an array with a 'url'). Mirrors poster handling.
		if ( $preset === 'image_distort' && isset( $preset_opts['image'] ) && is_array( $preset_opts['image'] ) ) {
			$preset_opts['imageUrl'] = ! empty( $preset_opts['image']['url'] ) ? esc_url_raw( $preset_opts['image']['url'] ) : '';
		}

		$color_a    = (string) sc_get( 'color_a', $atts, '#6aa6ff' );
		$color_b    = (string) sc_get( 'color_b', $atts, '#b388ff' );
		$background  = (string) sc_get( 'background', $atts, 'gradient' );
		$bg_color    = (string) sc_get( 'bg_color', $atts, '#0b0f1a' );
		// Placement is a multi-picker: placement/mode + (inline) placement/inline/height.
		$display_mode = sc_get( 'placement/mode', $atts, 'inline' ) === 'background' ? 'background' : 'inline';

		$config = array(
			'preset'          => (string) $preset,
			'presetOpts'      => $preset_opts,
			'scale'           => (float) sc_get( 'scale', $atts, 1 ),
			'colorA'          => $color_a,
			'colorB'          => $color_b,
			'background'      => $background,
			'bgColor'         => $bg_color,
			'autoRotate'      => (float) sc_get( 'auto_rotate', $atts, 0.3 ),
			'noiseAmount'     => (float) sc_get( 'noise_amount', $atts, 0.45 ),
			'noiseSpeed'      => (float) sc_get( 'noise_speed', $atts, 0.5 ),
			'scrollLink'      => sc_get( 'scroll_link', $atts, 'yes' ) === 'yes',
			'pointerFollow'   => sc_get( 'pointer_follow', $atts, 'yes' ) === 'yes',
			'pointerStrength' => (float) sc_get( 'pointer_strength', $atts, 0.5 ),
			'parallax'        => (float) sc_get( 'parallax', $atts, 0.3 ),
			'quality'         => (string) sc_get( 'quality', $atts, 'auto' ),
			'dprCap'          => (float) sc_get( 'dpr_cap', $atts, 2 ),
		);

		$poster     = sc_get( 'poster', $atts, array() );
		$poster_url = ( is_array( $poster ) && ! empty( $poster['url'] ) ) ? $poster['url'] : '';

		// Height lives under the inline placement. In background mode it's unused —
		// the parent Section's Min Height sizes the canvas.
		$height = trim( (string) sc_get( 'placement/inline/height', $atts, '520' ) );

		// Inline CSS vars: height + palette (also a CSS gradient fallback before JS / without WebGL).
		$style = '';
		if ( $display_mode !== 'background' && $height !== '' && is_numeric( $height ) ) {
			$style .= '--webgl-h:' . (int) $height . 'px;';
		}
		$style .= '--webgl-a:' . esc_attr( $color_a ) . ';--webgl-b:' . esc_attr( $color_b ) . ';';
		if ( $background === 'solid' ) {
			$style .= '--webgl-bg:' . esc_attr( $bg_color ) . ';';
		}

		$classes = array(
			'fw-webgl',
			'fw-webgl--' . sanitize_html_class( $preset ),
			'fw-webgl--bg-' . sanitize_html_class( $background ),
		);
		if ( $display_mode === 'background' ) {
			$classes[] = 'fw-webgl--background';
		}

		// Wrapper attributes (Advanced-tab id/class + animations) via the shared helper.
		if ( function_exists( 'sc_build_wrapper_attr' ) ) {
			$atts['base_class']       = 'webgl';
			$atts['unique_id_prefix'] = 'webgl-';
			$atts['css_class']        = trim( implode( ' ', $classes ) . ' ' . ( isset( $atts['css_class'] ) ? $atts['css_class'] : '' ) );
			$attr = sc_build_wrapper_attr( $atts );
		} else {
			$attr = array( 'class' => implode( ' ', $classes ) );
		}

		$attr['style'] = ( isset( $attr['style'] ) && $attr['style'] !== '' ? rtrim( $attr['style'], ';' ) . ';' : '' ) . $style;
		$attr['data-webgl']  = '1';
		if ( $display_mode === 'background' ) {
			$attr['data-webgl-bg'] = '1';
		}
		$attr['data-config'] = wp_json_encode( $config );

		$html  = '<div ' . fw_attr_to_html( $attr ) . '>';
		$html .= '<div class="fw-webgl__canvas" aria-hidden="true"></div>';
		if ( $poster_url !== '' ) {
			$html .= '<img class="fw-webgl__poster" src="' . esc_url( $poster_url ) . '" alt="" loading="lazy" />';
		}
		$html .= '</div>';

		return $html;
	}
}

echo sc_webgl_object_render( $atts );
