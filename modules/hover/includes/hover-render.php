<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Hover module: runtime.
 *
 * Emits the chosen effect(s) onto the element wrapper (via `sc_build_wrapper_attr`), forces a
 * wrapper when hover is the only non-default setting (via `sc_needs_wrapper`), and registers the
 * module's per-effect on-demand asset layout with the shared loader. Depends on the helpers.
 *
 * NOTE: uses UPW_HOVER_DIR (defined in hover.php) — NOT __DIR__ — for the asset-loader path,
 * because this file lives in includes/ but the static assets are at the module root.
 */

/* ------------------------------------------------------------------ *
 * 2) Emit the chosen effect(s) onto the element wrapper.
 *    Priority 21 → runs just after the entrance-animation filter (20).
 * ------------------------------------------------------------------ */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	if ( ! upw_hover_enabled() ) {
		return $attr;
	}

	$instances = upw_hover_instances( $atts );
	if ( empty( $instances ) ) {
		return $attr;
	}

	// Append a CSS custom-property string to whatever style is already on the wrapper.
	$add_style = function ( $css ) use ( &$attr ) {
		$existing      = isset( $attr['style'] ) ? rtrim( (string) $attr['style'], '; ' ) . '; ' : '';
		$attr['style'] = esc_attr( $existing . $css );
	};

	$classes = array( 'sc-hover' );
	$effects = array();

	// Apply EACH added hover instance onto the one wrapper, so effects combine (e.g. Lift + Ripple).
	// Per-effect data-attrs / CSS vars mostly don't collide; where two effects share one, the later
	// instance wins — that is the user's experiment to make.
	foreach ( $instances as $inst ) {
		$effect    = $inst['effect'];
		$o         = $inst['settings'];
		$classes[] = 'sc-hover--' . sanitize_html_class( $effect );
		$effects[] = $effect;

		// On-demand assets: record this effect so ONLY its CSS/JS partial is enqueued.
		if ( function_exists( 'upw_anim_use_asset' ) ) {
			upw_anim_use_asset( 'hover', $effect );
		}

		switch ( $effect ) {
		case 'magnetic':
			$attr['data-hover-strength'] = esc_attr( (float) ( $o['strength'] ?? 0.3 ) );
			break;

		case 'tilt':
			$attr['data-hover-max']   = esc_attr( (float) ( $o['max_tilt'] ?? 12 ) );
			$attr['data-hover-scale'] = esc_attr( (float) ( $o['hover_scale'] ?? 1 ) );
			if ( ( $o['glare'] ?? 'no' ) === 'yes' ) {
				$attr['data-hover-glare'] = '1';
			}
			break;

		case 'spotlight':
			$color = upw_hover_color( $o['glow_color'] ?? '' );
			if ( $color === '' ) { $color = '#6aa6ff'; }
			$size  = (int) ( $o['glow_size'] ?? 40 );
			$add_style( '--hover-glow:' . $color . '; --hover-glow-size:' . $size . '%;' );
			break;

		case 'image_reveal':
			$attr['data-hover-style'] = esc_attr( sanitize_html_class( (string) ( $o['reveal_style'] ?? 'zoom_gray' ) ) );
			$add_style( '--hover-zoom:' . (float) ( $o['zoom'] ?? 1.06 ) . ';' );
			break;

		case 'text_scramble':
			$attr['data-hover-duration'] = esc_attr( (float) ( $o['duration'] ?? 0.8 ) );
			break;

		case 'glow_border':
			$gc = upw_hover_color( $o['glow_color'] ?? '' );
			$add_style( '--hover-glow:' . ( $gc !== '' ? $gc : '#6aa6ff' ) . ';' );
			break;

		case 'underline_grow':
			$attr['data-hover-style'] = esc_attr( ( ( $o['origin'] ?? 'left' ) === 'center' ) ? 'center' : 'left' );
			$line = upw_hover_color( $o['line_color'] ?? '' );
			if ( $line !== '' ) {
				$add_style( '--hover-line:' . $line . ';' );
			}
			break;

		case 'ripple':
			$rc = upw_hover_color( $o['ripple_color'] ?? '' );
			$add_style( '--hover-ripple:' . ( $rc !== '' ? $rc : '#6aa6ff' ) . ';' );
			break;

		case 'lift':
			$add_style( '--hover-lift:' . (int) ( $o['distance'] ?? 6 ) . 'px;' );
			if ( ( $o['shadow'] ?? 'yes' ) !== 'yes' ) {
				$attr['data-hover-noshadow'] = '1';
			}
			break;

		case 'color_shift':
			$sc = upw_hover_color( $o['shift_color'] ?? '' );
			$add_style( '--hover-shift:' . ( $sc !== '' ? $sc : '#6aa6ff' ) . ';' );
			break;

		case 'scale':
			$add_style( '--hover-scale-to:' . (float) ( $o['scale_to'] ?? 1.04 ) . ';' );
			break;

		case 'push':
			$add_style( '--hover-push:' . (int) ( $o['depth'] ?? 5 ) . 'px;' );
			break;

		case 'jelly':
			$add_style( '--hover-jelly:' . (float) ( $o['strength'] ?? 1 ) . ';' );
			break;

		case 'skew':
			$add_style( '--hover-skew:' . (float) ( $o['angle'] ?? -6 ) . 'deg;' );
			break;

		case 'shine':
			$shc = upw_hover_color( $o['shine_color'] ?? '' );
			$add_style( '--hover-shine:' . ( $shc !== '' ? $shc : 'rgba(255,255,255,.55)' ) . ';' );
			break;

		case 'gradient_border':
			$ga = upw_hover_color( $o['color_a'] ?? '' );
			$gb = upw_hover_color( $o['color_b'] ?? '' );
			$add_style( '--hover-grad-a:' . ( $ga !== '' ? $ga : '#6aa6ff' ) . '; --hover-grad-b:' . ( $gb !== '' ? $gb : '#a06bff' ) . '; --hover-grad-speed:' . (float) ( $o['speed'] ?? 3 ) . 's;' );
			break;

		case 'corner_brackets':
			$bc = upw_hover_color( $o['bracket_color'] ?? '' );
			$add_style( '--hover-bracket:' . ( $bc !== '' ? $bc : '#6aa6ff' ) . '; --hover-bracket-size:' . (int) ( $o['bracket_size'] ?? 18 ) . 'px;' );
			break;

		case 'fill_sweep':
			$fc  = upw_hover_color( $o['fill_color'] ?? '' );
			$dir = in_array( ( $o['direction'] ?? 'left' ), array( 'left', 'right', 'up', 'center' ), true ) ? $o['direction'] : 'left';
			$attr['data-hover-fill'] = esc_attr( $dir );
			$add_style( '--hover-fill:' . ( $fc !== '' ? $fc : '#2f74e6' ) . ';' );
			break;

		case 'border_draw':
			$lc = upw_hover_color( $o['line_color'] ?? '' );
			$add_style( '--hover-line:' . ( $lc !== '' ? $lc : '#6aa6ff' ) . '; --hover-line-w:' . (int) ( $o['thickness'] ?? 2 ) . 'px;' );
			break;

		case 'glitch':
			$add_style( '--hover-glitch:' . (float) ( $o['strength'] ?? 1 ) . ';' );
			break;

		case 'text_swap':
			$swap = isset( $o['swap_text'] ) ? wp_strip_all_tags( (string) $o['swap_text'] ) : '';
			if ( $swap !== '' ) {
				$attr['data-hover-swap'] = esc_attr( $swap );
			}
			$attr['data-hover-swap-dir'] = esc_attr( ( ( $o['direction'] ?? 'up' ) === 'down' ) ? 'down' : 'up' );
			break;

		case 'rotate':
			$add_style( '--hover-rotate:' . (float) ( $o['angle'] ?? 6 ) . 'deg;' );
			break;

		case 'pulse':
			$add_style( '--hover-pulse:' . (float) ( $o['strength'] ?? 1 ) . ';' );
			break;

		case 'shake':
			$add_style( '--hover-shake:' . (float) ( $o['strength'] ?? 1 ) . ';' );
			break;

		case 'bounce':
			$add_style( '--hover-bounce:' . (int) ( $o['height'] ?? 10 ) . 'px;' );
			break;

		case 'grayscale':
			$add_style( '--hover-gray:' . (int) ( $o['amount'] ?? 100 ) . '%;' );
			break;

		case 'blur':
			$attr['data-hover-blur'] = esc_attr( ( ( $o['direction'] ?? 'rest' ) === 'hover' ) ? 'hover' : 'rest' );
			$add_style( '--hover-blur:' . (int) ( $o['amount'] ?? 4 ) . 'px;' );
			break;

		case 'brightness':
			$mode = ( ( $o['mode'] ?? 'brighten' ) === 'dim' ) ? 'dim' : 'brighten';
			$amt  = (int) ( $o['amount'] ?? 20 );
			$attr['data-hover-bright'] = esc_attr( $mode );
			$add_style( '--hover-bright:' . ( $mode === 'dim' ? ( 1 - $amt / 100 ) : ( 1 + $amt / 100 ) ) . ';' );
			break;

		case 'bg_pan':
			$pa = upw_hover_color( $o['color_a'] ?? '' );
			$pb = upw_hover_color( $o['color_b'] ?? '' );
			$add_style( '--hover-pan-a:' . ( $pa !== '' ? $pa : '#2f74e6' ) . '; --hover-pan-b:' . ( $pb !== '' ? $pb : '#a06bff' ) . '; --hover-pan-speed:' . (float) ( $o['speed'] ?? 3 ) . 's;' );
			break;

		case 'outline':
			$oc = upw_hover_color( $o['line_color'] ?? '' );
			$add_style( '--hover-outline:' . ( $oc !== '' ? $oc : '#6aa6ff' ) . '; --hover-outline-off:' . (int) ( $o['offset'] ?? 6 ) . 'px; --hover-outline-w:' . (int) ( $o['thickness'] ?? 2 ) . 'px;' );
			break;

		case 'letter_spacing':
			$add_style( '--hover-letter:' . (int) ( $o['amount'] ?? 3 ) . 'px;' );
			break;

		case 'webgl_displace':
			$wd_style = $o['style'] ?? 'both';
			if ( ! in_array( $wd_style, array( 'both', 'refract', 'liquid' ), true ) ) { $wd_style = 'both'; }
			$attr['data-wd-style']    = esc_attr( $wd_style );
			$attr['data-wd-strength'] = esc_attr( (float) ( $o['strength'] ?? 0.35 ) );
			$attr['data-wd-chroma']   = esc_attr( (float) ( $o['chroma'] ?? 0.4 ) );
			$attr['data-wd-speed']    = esc_attr( (float) ( $o['speed'] ?? 0.6 ) );
			if ( ( $o['trigger'] ?? 'hover' ) === 'always' ) {
				$attr['data-wd-trigger'] = 'always';
			}
			break;
	}
	}

	$cls                = isset( $attr['class'] ) ? trim( (string) $attr['class'] ) : '';
	$attr['class']      = esc_attr( trim( $cls . ' ' . implode( ' ', array_values( array_unique( $classes ) ) ) ) );
	$attr['data-hover'] = esc_attr( implode( ' ', $effects ) );

	upw_hover_flag( true );
	return $attr;
}, 21, 2 );

/* ------------------------------------------------------------------ *
 * 2b) Force a wrapper when an element's ONLY non-default setting is a hover
 *     interaction. Leaf shortcodes (text-block, special-heading, …) that gate
 *     their wrapper on sc_needs_wrapper() otherwise emit NO wrapper, so the
 *     data-hover attrs stamped above have nowhere to land and the effect
 *     silently never fires. Mirrors the GSAP module's sc_needs_wrapper hook.
 * ------------------------------------------------------------------ */
add_filter( 'sc_needs_wrapper', function ( $needs, $atts ) {
	if ( $needs || ! upw_hover_enabled() ) {
		return $needs;
	}
	return ! empty( upw_hover_instances( $atts ) );
}, 10, 2 );

/* ------------------------------------------------------------------ *
 * 3) On-demand assets. Register the module's per-effect partial layout with the
 *    shared loader (includes/asset-loader.php); a page then ships ONLY the CSS/JS
 *    for the effects it actually uses — recorded per element in the wrapper filter
 *    above via upw_anim_use_asset( 'hover', <effect> ). CSS-only pages load no JS.
 * ------------------------------------------------------------------ */
if ( function_exists( 'upw_anim_register_assets' ) ) {
	$upw_hover_ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( $upw_hover_ext ) {
		upw_anim_register_assets( 'hover', array(
			'path'      => UPW_HOVER_DIR,
			'uri'       => $upw_hover_ext->get_declared_URI( '/modules/hover' ),
			'ver'       => $upw_hover_ext->manifest->get_version(),
			'css_dir'   => 'static/css/effects',
			'js_dir'    => 'static/js/effects',
			'base_js'   => 'static/js/hover-core.js',
			// ONLY these effects ship a JS partial; every other effect is CSS-only,
			// so a page using only CSS effects loads zero hover JavaScript.
			'js_styles' => array( 'magnetic', 'tilt', 'spotlight', 'ripple', 'text_scramble', 'text_swap', 'webgl_displace' ),
			'js_cfg'    => function () {
				$cfg = array(
					'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
					'disableMobile' => ( function_exists( 'upw_anim_engine_setting' ) && upw_anim_engine_setting( 'disable_on_mobile', 'no' ) === 'yes' ),
				);
				return 'window.upwHoverCfg=' . wp_json_encode( $cfg ) . ';';
			},
		) );
	}
	unset( $upw_hover_ext );
}
