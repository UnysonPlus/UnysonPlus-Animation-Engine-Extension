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
			if ( ( $o['mode'] ?? 'pull' ) === 'push' ) { $attr['data-hover-mode'] = 'push'; }
			break;

		case 'tilt':
			$attr['data-hover-max']   = esc_attr( (float) ( $o['max_tilt'] ?? 12 ) );
			$attr['data-hover-scale'] = esc_attr( (float) ( $o['hover_scale'] ?? 1 ) );
			if ( ( $o['glare'] ?? 'no' ) === 'yes' ) {
				$attr['data-hover-glare'] = '1';
			}
			if ( ( $o['invert'] ?? 'no' ) === 'yes' ) {
				$attr['data-hover-invert'] = '1';
			}
			break;

		case 'spotlight':
			$color = upw_hover_color( $o['glow_color'] ?? '' );
			if ( $color === '' ) { $color = '#6aa6ff'; }
			$size  = (int) ( $o['glow_size'] ?? 40 );
			if ( ( $o['style'] ?? 'glow' ) === 'gradient' ) {
				// "Gradient tint" sub-style — a 2-colour gradient follows the pointer instead of a soft glow.
				$cgb = upw_hover_color( $o['color_b'] ?? '' );
				$attr['data-hover-spot'] = 'gradient';
				$add_style( '--hover-cg-a:' . $color . '; --hover-cg-b:' . ( $cgb !== '' ? $cgb : '#a06bff' ) . '; --hover-cg-size:' . $size . '%;' );
			} else {
				$add_style( '--hover-glow:' . $color . '; --hover-glow-size:' . $size . '%;' );
			}
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
			if ( ( $o['mode'] ?? 'steady' ) === 'pulse' ) { $attr['data-hover-glow-mode'] = 'pulse'; }
			break;

		case 'underline_grow':
			$attr['data-hover-style'] = esc_attr( ( ( $o['origin'] ?? 'left' ) === 'center' ) ? 'center' : 'left' );
			$pos = in_array( ( $o['position'] ?? 'under' ), array( 'under', 'over', 'through' ), true ) ? ( $o['position'] ?? 'under' ) : 'under';
			if ( $pos !== 'under' ) { $attr['data-hover-pos'] = esc_attr( $pos ); }
			$line = upw_hover_color( $o['line_color'] ?? '' );
			if ( $line !== '' ) {
				$add_style( '--hover-line:' . $line . ';' );
			}
			break;

		case 'ripple':
			$rc = upw_hover_color( $o['ripple_color'] ?? '' );
			$add_style( '--hover-ripple:' . ( $rc !== '' ? $rc : '#6aa6ff' ) . ';' );
			if ( ( $o['origin'] ?? 'pointer' ) === 'center' ) { $attr['data-hover-ripple-origin'] = 'center'; }
			break;

		case 'lift':
			$add_style( '--hover-lift:' . (int) ( $o['distance'] ?? 6 ) . 'px;' );
			if ( ( $o['shadow'] ?? 'yes' ) !== 'yes' ) {
				$attr['data-hover-noshadow'] = '1';
			}
			$lst = in_array( ( $o['style'] ?? 'lift' ), array( 'lift', 'tilt', 'sink' ), true ) ? ( $o['style'] ?? 'lift' ) : 'lift';
			if ( $lst !== 'lift' ) { $attr['data-hover-lift-style'] = esc_attr( $lst ); }
			break;

		case 'color_shift':
			$sc  = upw_hover_color( $o['shift_color'] ?? '' );
			$tgt = in_array( ( $o['target'] ?? 'background' ), array( 'background', 'text', 'border' ), true ) ? ( $o['target'] ?? 'background' ) : 'background';
			$attr['data-hover-target'] = esc_attr( $tgt );
			$add_style( '--hover-shift:' . ( $sc !== '' ? $sc : '#6aa6ff' ) . ';' );
			break;

		case 'scale':
			$amt = (float) ( $o['scale_to'] ?? 1.04 );
			if ( ( $o['direction'] ?? 'in' ) === 'out' ) { $amt = max( 0.6, 2 - $amt ); } // zoom out (shrink)
			$add_style( '--hover-scale-to:' . $amt . ';' );
			break;

		case 'push':
			$add_style( '--hover-push:' . (int) ( $o['depth'] ?? 5 ) . 'px;' );
			if ( ( $o['style'] ?? 'press' ) === 'inz' ) { $attr['data-hover-push-style'] = 'inz'; }
			break;

		case 'jelly':
			$add_style( '--hover-jelly:' . (float) ( $o['strength'] ?? 1 ) . ';' );
			break;

		case 'skew':
			$add_style( '--hover-skew:' . (float) ( $o['angle'] ?? -6 ) . 'deg;' );
			$sax = in_array( ( $o['axis'] ?? 'x' ), array( 'x', 'y', 'both' ), true ) ? ( $o['axis'] ?? 'x' ) : 'x';
			if ( $sax !== 'x' ) { $attr['data-hover-skew-axis'] = esc_attr( $sax ); }
			break;

		case 'shine':
			if ( ( $o['style'] ?? 'sheen' ) === 'holographic' ) {
				// "Holographic" sub-style — a rainbow sheen that keeps sweeping while hovered.
				$attr['data-hover-shine'] = 'holographic';
				$add_style( '--hover-holo-speed:' . (float) ( $o['speed'] ?? 3 ) . 's;' );
			} else {
				$shc = upw_hover_color( $o['shine_color'] ?? '' );
				$add_style( '--hover-shine:' . ( $shc !== '' ? $shc : 'rgba(255,255,255,.55)' ) . ';' );
			}
			break;

		case 'gradient_border':
			$ga = upw_hover_color( $o['color_a'] ?? '' );
			$gb = upw_hover_color( $o['color_b'] ?? '' );
			$add_style( '--hover-grad-a:' . ( $ga !== '' ? $ga : '#6aa6ff' ) . '; --hover-grad-b:' . ( $gb !== '' ? $gb : '#a06bff' ) . '; --hover-grad-speed:' . (float) ( $o['speed'] ?? 3 ) . 's;' );
			break;

		case 'corner_brackets':
			$bc = upw_hover_color( $o['bracket_color'] ?? '' );
			$add_style( '--hover-bracket:' . ( $bc !== '' ? $bc : '#6aa6ff' ) . '; --hover-bracket-size:' . (int) ( $o['bracket_size'] ?? 18 ) . 'px;' );
			if ( ( $o['style'] ?? 'pop' ) === 'draw' ) { $attr['data-hover-bracket-style'] = 'draw'; }
			break;

		case 'fill_sweep':
			$fc  = upw_hover_color( $o['fill_color'] ?? '' );
			$dir = in_array( ( $o['direction'] ?? 'left' ), array( 'left', 'right', 'up', 'center', 'diagonal' ), true ) ? $o['direction'] : 'left';
			$attr['data-hover-fill'] = esc_attr( $dir );
			$add_style( '--hover-fill:' . ( $fc !== '' ? $fc : '#2f74e6' ) . ';' );
			break;

		case 'border_draw':
			$lc = upw_hover_color( $o['line_color'] ?? '' );
			$add_style( '--hover-line:' . ( $lc !== '' ? $lc : '#6aa6ff' ) . '; --hover-line-w:' . (int) ( $o['thickness'] ?? 2 ) . 'px;' );
			if ( ( $o['start'] ?? 'corner' ) === 'center' ) { $attr['data-hover-draw-start'] = 'center'; }
			break;

		case 'glitch':
			$add_style( '--hover-glitch:' . (float) ( $o['strength'] ?? 1 ) . ';' );
			$gst = in_array( ( $o['style'] ?? 'rgb' ), array( 'rgb', 'slice', 'jitter' ), true ) ? ( $o['style'] ?? 'rgb' ) : 'rgb';
			if ( $gst !== 'rgb' ) { $attr['data-hover-glitch-style'] = esc_attr( $gst ); }
			break;

		case 'text_swap':
			$swap = isset( $o['swap_text'] ) ? wp_strip_all_tags( (string) $o['swap_text'] ) : '';
			if ( $swap !== '' ) {
				$attr['data-hover-swap'] = esc_attr( $swap );
			}
			$attr['data-hover-swap-dir'] = esc_attr( ( ( $o['direction'] ?? 'up' ) === 'down' ) ? 'down' : 'up' );
			$smode = in_array( ( $o['mode'] ?? 'slide' ), array( 'slide', 'fade', 'flip' ), true ) ? ( $o['mode'] ?? 'slide' ) : 'slide';
			if ( $smode !== 'slide' ) { $attr['data-hover-swap-mode'] = esc_attr( $smode ); }
			break;

		case 'rotate':
			$add_style( '--hover-rotate:' . (float) ( $o['angle'] ?? 6 ) . 'deg;' );
			if ( ( $o['style'] ?? 'flat' ) === 'flip3d' ) { $attr['data-hover-rotate-style'] = 'flip3d'; }
			break;

		case 'pulse':
			$add_style( '--hover-pulse:' . (float) ( $o['strength'] ?? 1 ) . ';' );
			$pst = in_array( ( $o['style'] ?? 'scale' ), array( 'scale', 'glow', 'opacity' ), true ) ? ( $o['style'] ?? 'scale' ) : 'scale';
			if ( $pst !== 'scale' ) { $attr['data-hover-pulse-style'] = esc_attr( $pst ); }
			break;

		case 'shake':
			$add_style( '--hover-shake:' . (float) ( $o['strength'] ?? 1 ) . ';' );
			$sst = in_array( ( $o['style'] ?? 'horizontal' ), array( 'horizontal', 'vertical', 'rotate' ), true ) ? ( $o['style'] ?? 'horizontal' ) : 'horizontal';
			if ( $sst !== 'horizontal' ) { $attr['data-hover-shake-style'] = esc_attr( $sst ); }
			break;

		case 'bounce':
			$add_style( '--hover-bounce:' . (int) ( $o['height'] ?? 10 ) . 'px;' );
			$bst = in_array( ( $o['style'] ?? 'up' ), array( 'up', 'drop', 'squash' ), true ) ? ( $o['style'] ?? 'up' ) : 'up';
			if ( $bst !== 'up' ) { $attr['data-hover-bounce-style'] = esc_attr( $bst ); }
			break;

		case 'grayscale':
			$amt = max( 0, min( 100, (int) ( $o['amount'] ?? 100 ) ) );
			$ft  = in_array( ( $o['filter'] ?? 'grayscale' ), array( 'grayscale', 'sepia', 'invert', 'hue', 'saturate' ), true ) ? ( $o['filter'] ?? 'grayscale' ) : 'grayscale';
			$map = array(
				'grayscale' => 'grayscale(' . $amt . '%)',
				'sepia'     => 'sepia(' . $amt . '%)',
				'invert'    => 'invert(' . $amt . '%)',
				'hue'       => 'hue-rotate(' . round( $amt * 1.8 ) . 'deg)',
				'saturate'  => 'saturate(' . ( 1 + $amt / 33 ) . ')',
			);
			$add_style( '--hover-filter:' . $map[ $ft ] . ';' );
			break;

		case 'blur':
			$attr['data-hover-blur'] = esc_attr( ( ( $o['direction'] ?? 'rest' ) === 'hover' ) ? 'hover' : 'rest' );
			$add_style( '--hover-blur:' . (int) ( $o['amount'] ?? 4 ) . 'px;' );
			break;

		case 'brightness':
			$bfilter = in_array( ( $o['filter'] ?? 'brightness' ), array( 'brightness', 'contrast', 'saturate' ), true ) ? ( $o['filter'] ?? 'brightness' ) : 'brightness';
			$mode    = ( ( $o['mode'] ?? 'brighten' ) === 'dim' ) ? 'dim' : 'brighten';
			$amt     = (int) ( $o['amount'] ?? 20 );
			$val     = $mode === 'dim' ? ( 1 - $amt / 100 ) : ( 1 + $amt / 100 );
			$add_style( '--hover-bright-filter:' . $bfilter . '(' . $val . ');' );
			break;

		case 'bg_pan':
			$pa = upw_hover_color( $o['color_a'] ?? '' );
			$pb = upw_hover_color( $o['color_b'] ?? '' );
			$add_style( '--hover-pan-a:' . ( $pa !== '' ? $pa : '#2f74e6' ) . '; --hover-pan-b:' . ( $pb !== '' ? $pb : '#a06bff' ) . '; --hover-pan-speed:' . (float) ( $o['speed'] ?? 3 ) . 's; --hover-pan-angle:' . (int) ( $o['angle'] ?? 120 ) . 'deg;' );
			break;

		case 'outline':
			$oc = upw_hover_color( $o['line_color'] ?? '' );
			$add_style( '--hover-outline:' . ( $oc !== '' ? $oc : '#6aa6ff' ) . '; --hover-outline-off:' . (int) ( $o['offset'] ?? 6 ) . 'px; --hover-outline-w:' . (int) ( $o['thickness'] ?? 2 ) . 'px;' );
			$ost = in_array( ( $o['style'] ?? 'solid' ), array( 'solid', 'dashed', 'double' ), true ) ? ( $o['style'] ?? 'solid' ) : 'solid';
			if ( $ost !== 'solid' ) { $attr['data-hover-outline-style'] = esc_attr( $ost ); }
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

		case 'goo':
			$add_style( '--hover-goo-speed:' . (float) ( $o['speed'] ?? 4 ) . 's;' );
			break;

		case 'squash':
			$add_style( '--hover-squash:' . (float) ( $o['strength'] ?? 1 ) . ';' );
			break;

		case 'arrow_slide':
			$ac = upw_hover_color( $o['arrow_color'] ?? '' );
			if ( $ac !== '' ) {
				$add_style( '--hover-arrow:' . $ac . ';' );
			}
			break;

		case 'depth_layers':
			$attr['data-hover-depth'] = esc_attr( (float) ( $o['strength'] ?? 1 ) );
			break;

		case 'marching_ants':
			$mc = upw_hover_color( $o['line_color'] ?? '' );
			$add_style( '--hover-ants:' . ( $mc !== '' ? $mc : '#6aa6ff' ) . '; --hover-ants-speed:' . (float) ( $o['speed'] ?? 0.5 ) . 's;' );
			break;

		case 'flashlight':
			$add_style( '--hover-torch-size:' . (int) ( $o['size'] ?? 90 ) . 'px; --hover-torch-dark:' . ( max( 30, min( 95, (int) ( $o['darkness'] ?? 82 ) ) ) / 100 ) . ';' );
			break;

		case 'blob':
			$blc = upw_hover_color( $o['color'] ?? '' );
			$add_style( '--hover-blob:' . ( $blc !== '' ? $blc : '#6aa6ff' ) . '; --hover-blob-size:' . (int) ( $o['size'] ?? 70 ) . 'px;' );
			break;

		case 'cursor_trail':
			$tc = upw_hover_color( $o['color'] ?? '' );
			$add_style( '--hover-trail:' . ( $tc !== '' ? $tc : '#6aa6ff' ) . '; --hover-trail-size:' . (int) ( $o['size'] ?? 10 ) . 'px;' );
			break;

		case 'magnetic_letters':
			$attr['data-hover-ml-strength'] = esc_attr( (float) ( $o['strength'] ?? 1 ) );
			break;

		case 'shockwave':
			$swc = upw_hover_color( $o['color'] ?? '' );
			$add_style( '--hover-shock:' . ( $swc !== '' ? $swc : '#6aa6ff' ) . ';' );
			break;

		case 'peel':
			$pc = upw_hover_color( $o['color'] ?? '' );
			$add_style( '--hover-peel:' . ( $pc !== '' ? $pc : 'rgba(0,0,0,.22)' ) . '; --hover-peel-size:' . (int) ( $o['size'] ?? 22 ) . 'px;' );
			break;
	}
	}

	$cls                = isset( $attr['class'] ) ? trim( (string) $attr['class'] ) : '';
	$attr['class']      = esc_attr( trim( $cls . ' ' . implode( ' ', array_values( array_unique( $classes ) ) ) ) );
	$attr['data-hover'] = esc_attr( implode( ' ', $effects ) );

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
			'js_styles' => array( 'magnetic', 'tilt', 'spotlight', 'ripple', 'text_scramble', 'text_swap', 'webgl_displace', 'depth_layers', 'flashlight', 'blob', 'cursor_trail', 'magnetic_letters' ),
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
