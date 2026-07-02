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

if ( ! function_exists( 'sc_model_viewer_render' ) ) {
	function sc_model_viewer_render( $atts ) {
		// --- Source: media pick wins over a pasted URL; nothing renders without one. ---
		$file = sc_get( 'model_file', $atts, array() );
		$src  = ( is_array( $file ) && ! empty( $file['url'] ) ) ? $file['url'] : (string) sc_get( 'model_url', $atts, '' );
		$src  = trim( (string) $src );
		if ( $src === '' ) {
			return '';
		}

		$poster     = sc_get( 'poster', $atts, array() );
		$poster_url = ( is_array( $poster ) && ! empty( $poster['url'] ) ) ? $poster['url'] : '';
		$alt        = trim( (string) sc_get( 'alt', $atts, '' ) );

		// --- Build the <model-viewer> attributes. Booleans are `true` (presence only). ---
		$mv = array(
			'src'     => esc_url_raw( $src ),
			'alt'     => $alt !== '' ? $alt : __( '3D model', 'fw' ),
			'loading' => 'lazy',
			'reveal'  => 'auto',
		);
		if ( $poster_url !== '' ) {
			$mv['poster'] = esc_url_raw( $poster_url );
		}

		// Camera / interaction.
		if ( sc_get( 'camera_controls', $atts, 'yes' ) === 'yes' ) {
			$mv['camera-controls'] = true;
			$mv['touch-action']    = 'pan-y';
		}
		if ( sc_get( 'disable_zoom', $atts, 'no' ) === 'yes' ) {
			$mv['disable-zoom'] = true;
		}
		if ( sc_get( 'disable_pan', $atts, 'no' ) === 'yes' ) {
			$mv['disable-pan'] = true;
		}
		// Camera limits (blank = no limit). FOV limits accept a bare number or "Ndeg".
		$min_fov = trim( (string) sc_get( 'min_fov', $atts, '' ) );
		$max_fov = trim( (string) sc_get( 'max_fov', $atts, '' ) );
		if ( $min_fov !== '' ) {
			$mv['min-field-of-view'] = is_numeric( $min_fov ) ? $min_fov . 'deg' : $min_fov;
		}
		if ( $max_fov !== '' ) {
			$mv['max-field-of-view'] = is_numeric( $max_fov ) ? $max_fov . 'deg' : $max_fov;
		}
		$min_orbit = trim( (string) sc_get( 'min_orbit', $atts, '' ) );
		$max_orbit = trim( (string) sc_get( 'max_orbit', $atts, '' ) );
		if ( $min_orbit !== '' ) {
			$mv['min-camera-orbit'] = $min_orbit;
		}
		if ( $max_orbit !== '' ) {
			$mv['max-camera-orbit'] = $max_orbit;
		}
		if ( sc_get( 'auto_rotate', $atts, 'yes' ) === 'yes' ) {
			$mv['auto-rotate']         = true;
			$mv['rotation-per-second'] = ( (int) sc_get( 'rotation_speed', $atts, 30 ) ) . 'deg';
			$mv['auto-rotate-delay']   = (int) sc_get( 'auto_rotate_delay', $atts, 3000 );
		}
		$orbit_map = array(
			'three_quarter' => '-30deg 78deg auto',
			'front'         => '0deg 90deg auto',
			'side'          => '-90deg 85deg auto',
			'top'           => '0deg 20deg auto',
		);
		$orbit_key = (string) sc_get( 'camera_orbit', $atts, 'three_quarter' );
		if ( isset( $orbit_map[ $orbit_key ] ) ) {
			$mv['camera-orbit'] = $orbit_map[ $orbit_key ];
		}
		$fov_map = array( 'narrow' => '20deg', 'normal' => '30deg', 'wide' => '45deg' );
		$fov_key = (string) sc_get( 'field_of_view', $atts, 'auto' );
		if ( isset( $fov_map[ $fov_key ] ) ) {
			$mv['field-of-view'] = $fov_map[ $fov_key ];
		}
		$mv['interaction-prompt'] = sc_get( 'interaction_prompt', $atts, 'auto' ) === 'none' ? 'none' : 'auto';

		// Lighting.
		$env      = (string) sc_get( 'environment', $atts, 'neutral' );
		$env_url  = '';
		if ( $env === 'neutral' ) {
			$mv['environment-image'] = 'neutral';
		} elseif ( $env === 'legacy' ) {
			$mv['environment-image'] = 'legacy';
		} elseif ( $env === 'custom' ) {
			$env_url = trim( (string) sc_get( 'env_image', $atts, '' ) );
			if ( $env_url !== '' ) {
				$mv['environment-image'] = esc_url_raw( $env_url );
			}
		} // 'none' → omit (default lighting rig).

		// Skybox: use the custom HDR as an immersive backdrop (needs a real image URL —
		// the built-in "neutral"/"legacy" maps can't be used as a skybox).
		if ( $env_url !== '' && sc_get( 'skybox', $atts, 'no' ) === 'yes' ) {
			$mv['skybox-image'] = esc_url_raw( $env_url );
		}

		$tone = (string) sc_get( 'tone_mapping', $atts, 'auto' );
		if ( in_array( $tone, array( 'neutral', 'commerce', 'aces', 'agx' ), true ) ) {
			$mv['tone-mapping'] = $tone;
		}
		$mv['exposure']         = (string) (float) sc_get( 'exposure', $atts, 1 );
		$mv['shadow-intensity'] = (string) (float) sc_get( 'shadow_intensity', $atts, 0.6 );
		$mv['shadow-softness']  = (string) (float) sc_get( 'shadow_softness', $atts, 1 );

		// Embedded animation clips.
		if ( sc_get( 'animation_autoplay', $atts, 'no' ) === 'yes' ) {
			$mv['autoplay'] = true;
			$clip = trim( (string) sc_get( 'animation_name', $atts, '' ) );
			if ( $clip !== '' ) {
				$mv['animation-name'] = $clip;
			}
		}

		// AR.
		if ( sc_get( 'ar', $atts, 'no' ) === 'yes' ) {
			$mv['ar']           = true;
			$mv['ar-modes']     = 'webxr scene-viewer quick-look';
			$mv['ar-placement'] = sc_get( 'ar_placement', $atts, 'floor' ) === 'wall' ? 'wall' : 'floor';
			if ( sc_get( 'ar_scale', $atts, 'auto' ) === 'fixed' ) {
				$mv['ar-scale'] = 'fixed';
			}
		}

		// --- Material variants: flag the wrapper (JS builds the swatch row) + preselect. ---
		$variants_show = sc_get( 'variants_show', $atts, 'no' ) === 'yes';
		if ( $variants_show ) {
			$variant_default = trim( (string) sc_get( 'variant_default', $atts, '' ) );
			if ( $variant_default !== '' ) {
				$mv['variant-name'] = $variant_default;
			}
		}

		// --- Hotspots: slotted <button> children pinned to 3D points on the model. ---
		$hotspots     = sc_get( 'hotspots', $atts, array() );
		$hotspot_html = '';
		if ( is_array( $hotspots ) ) {
			$home_host = function_exists( 'wp_parse_url' ) ? wp_parse_url( home_url(), PHP_URL_HOST ) : '';
			$i = 0;
			foreach ( $hotspots as $h ) {
				$pos = isset( $h['position'] ) ? trim( (string) $h['position'] ) : '';
				if ( $pos === '' ) {
					continue; // a hotspot without a position can't be placed
				}
				$i++;
				$h_attr = ' slot="hotspot-' . $i . '" data-position="' . esc_attr( $pos ) . '"';
				$normal = isset( $h['normal'] ) ? trim( (string) $h['normal'] ) : '';
				if ( $normal !== '' ) {
					$h_attr .= ' data-normal="' . esc_attr( $normal ) . '"';
				}
				$label  = isset( $h['label'] ) ? (string) $h['label'] : '';
				$detail = isset( $h['detail'] ) ? (string) $h['detail'] : '';
				$link   = isset( $h['link'] ) ? trim( (string) $h['link'] ) : '';

				$inner = '';
				if ( $label !== '' ) {
					$inner .= '<span class="fw-model__hotspot-title">' . esc_html( $label ) . '</span>';
				}
				if ( $detail !== '' ) {
					$inner .= '<span class="fw-model__hotspot-detail">' . nl2br( esc_html( $detail ) ) . '</span>';
				}
				if ( $link !== '' ) {
					$is_ext = $home_host && wp_parse_url( $link, PHP_URL_HOST ) && wp_parse_url( $link, PHP_URL_HOST ) !== $home_host;
					$rel    = $is_ext ? ' target="_blank" rel="noopener noreferrer"' : '';
					$ann    = '<a class="fw-model__hotspot-annotation is-link" href="' . esc_url( $link ) . '"' . $rel . '>' . $inner . '</a>';
				} else {
					$ann = '<div class="fw-model__hotspot-annotation">' . $inner . '</div>';
				}
				$hotspot_html .= '<button class="fw-model__hotspot" type="button"' . $h_attr . '>' . $ann . '</button>';
			}
			if ( $hotspot_html !== '' && sc_get( 'hotspot_hide_backside', $atts, 'yes' ) === 'yes' ) {
				$mv['min-hotspot-opacity'] = '0';
			}
		}

		// --- Wrapper: height + solid background via CSS vars. ---
		$height = trim( (string) sc_get( 'height', $atts, '520' ) );
		$style  = '';
		if ( $height !== '' && is_numeric( $height ) ) {
			$style .= '--model-h:' . (int) $height . 'px;';
		}
		$background = (string) sc_get( 'background', $atts, 'transparent' );
		if ( $background === 'solid' ) {
			$bg_color = function_exists( 'sc_color_to_css' )
				? sc_color_to_css( sc_get( 'bg_color', $atts, '' ), '#f4f5f7', true )
				: (string) sc_get( 'bg_color', $atts, '#f4f5f7' );
			$style .= '--model-bg:' . esc_attr( $bg_color ) . ';';
		}

		$classes = array( 'fw-model', 'fw-model--bg-' . sanitize_html_class( $background ) );
		if ( $variants_show ) {
			$classes[] = 'fw-model--has-variants';
		}

		if ( function_exists( 'sc_build_wrapper_attr' ) ) {
			$atts['base_class']       = 'model';
			$atts['unique_id_prefix'] = 'model-';
			$atts['css_class']        = trim( implode( ' ', $classes ) . ' ' . ( isset( $atts['css_class'] ) ? $atts['css_class'] : '' ) );
			$attr = sc_build_wrapper_attr( $atts );
		} else {
			$attr = array( 'class' => implode( ' ', $classes ) );
		}
		$attr['style'] = ( isset( $attr['style'] ) && $attr['style'] !== '' ? rtrim( $attr['style'], ';' ) . ';' : '' ) . $style;
		if ( $variants_show ) {
			$attr['data-variants'] = '1';
		}

		// --- Render the <model-viewer> tag (boolean attrs = bare presence); hotspots are
		// slotted children so <model-viewer> positions them on the model. ---
		$mv_html = '<model-viewer';
		foreach ( $mv as $k => $v ) {
			if ( $v === true ) {
				$mv_html .= ' ' . $k;
			} elseif ( $v !== false && $v !== '' ) {
				$mv_html .= ' ' . $k . '="' . esc_attr( $v ) . '"';
			}
		}
		$mv_html .= '>' . $hotspot_html . '</model-viewer>';

		// A slim load-progress bar (driven by the JS `progress` event), an (optional)
		// variant swatch row the JS fills from the model, and the poster fallback shown
		// when 3D isn't supported (.is-unsupported) — all inside the .fw-model wrapper.
		$bar       = '<div class="fw-model__bar" aria-hidden="true"><i></i></div>';
		$variants_ui = $variants_show ? '<div class="fw-model__variants" role="group" aria-label="' . esc_attr__( 'Model variants', 'fw' ) . '"></div>' : '';
		$fallback  = '';
		if ( $poster_url !== '' ) {
			$fallback = '<img class="fw-model__fallback" src="' . esc_url( $poster_url ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" />';
		}

		return '<div ' . fw_attr_to_html( $attr ) . '>' . $mv_html . $bar . $variants_ui . $fallback . '</div>';
	}
}

echo sc_model_viewer_render( $atts );
