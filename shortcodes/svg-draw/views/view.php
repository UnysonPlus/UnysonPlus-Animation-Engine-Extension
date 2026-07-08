<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/** @var array $atts */

if ( ! function_exists( 'sc_get' ) ) {
	function sc_get( $path, $atts, $default = '' ) {
		if ( function_exists( 'fw_akg' ) ) {
			$v = fw_akg( $path, $atts, null );
			if ( $v !== null ) { return $v; }
		}
		return $default;
	}
}

/**
 * Sanitise user-supplied SVG markup before it is echoed inline.
 *
 * Two-tier: authors who can post raw HTML (`unfiltered_html`) keep full control — they can already
 * inject arbitrary markup site-wide, so gating them adds nothing. Everyone else (Author /
 * Contributor) is run through a hardened allow-strip that removes every script-capable / remote-ref
 * element AND all `on*` event handlers — crucially catching the `<svg/onload=…>` slash-separator
 * form that a naïve `\son…` strip misses, plus scripted/data: URLs on any remaining ref attribute.
 */
if ( ! function_exists( 'sc_svg_draw_sanitize' ) ) {
	function sc_svg_draw_sanitize( $svg ) {
		$svg = (string) $svg;
		if ( $svg === '' ) { return ''; }
		// Keep only from the first <svg to the last </svg>.
		if ( preg_match( '#<svg[\s\S]*</svg>#i', $svg, $m ) ) { $svg = $m[0]; }

		// Trusted authors keep full control.
		if ( current_user_can( 'unfiltered_html' ) ) {
			return $svg;
		}

		// Remove elements that can run script or pull remote/SMIL refs — paired (with their content)
		// or self-closing — plus any orphaned closing tags left behind.
		$danger = 'script|foreignObject|iframe|embed|object|animate|animateTransform|animateMotion|set|image|use|a|handler|listener';
		$svg = preg_replace( '#<\s*(' . $danger . ')\b[^>]*(?:>[\s\S]*?<\s*/\s*\1\s*>|/?>)#i', '', $svg );
		$svg = preg_replace( '#<\s*/\s*(?:' . $danger . ')\s*>#i', '', $svg );
		// Strip EVERY on* event handler — the separator before "on" may be whitespace OR a slash
		// (`<svg/onload=…>`), which the old `\son…` pattern let through.
		$svg = preg_replace( '#[\s/]on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)#i', ' ', $svg );
		// Neutralise scripted / data URLs on any remaining reference attribute.
		$svg = preg_replace(
			'#\b(href|xlink:href|src)\s*=\s*(?:"[^"]*(?:javascript|data|vbscript)\s*:[^"]*"|\'[^\']*(?:javascript|data|vbscript)\s*:[^\']*\')#i',
			'$1="#"',
			$svg
		);
		return $svg;
	}
}

/** Built-in stroke presets — single-colour outline art that draws cleanly. */
if ( ! function_exists( 'sc_svg_draw_preset' ) ) {
	function sc_svg_draw_preset( $id ) {
		$p = array(
			'signature' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 90" fill="none"><path d="M12 62 C34 20 44 20 40 54 C38 74 60 40 66 34 C58 62 78 60 92 44 C86 58 100 58 108 46 M120 30 C110 60 128 66 132 46 C134 34 122 34 124 52 C126 70 150 56 158 44 C176 22 176 60 168 62 C186 54 196 40 210 46 C200 66 224 62 236 46 C252 26 250 60 244 60 C262 58 276 46 288 40"/></svg>',
			'underline' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 40" fill="none"><path d="M8 24 C60 8 120 8 180 20 C220 28 260 30 292 18 C270 26 240 30 210 28"/></svg>',
			'arrow'     => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 80" fill="none"><path d="M10 40 H180 M150 14 L184 40 L150 66"/></svg>',
			'check'     => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" fill="none"><circle cx="50" cy="50" r="42"/><path d="M30 52 L45 68 L72 34"/></svg>',
			'wave'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 40" fill="none"><path d="M4 20 Q24 2 44 20 T84 20 T124 20 T164 20 T204 20 T244 20 T284 20 T316 20"/></svg>',
			'star'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" fill="none"><path d="M50 6 L61 38 L96 38 L68 59 L79 92 L50 71 L21 92 L32 59 L4 38 L39 38 Z"/></svg>',
			'heart'     => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 90" fill="none"><path d="M50 82 C10 54 6 26 26 16 C40 9 50 20 50 30 C50 20 60 9 74 16 C94 26 90 54 50 82 Z"/></svg>',
			'circle'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" fill="none"><path d="M50 8 C74 8 92 28 92 50 C92 74 72 92 50 92 C26 92 8 72 8 50 C8 27 27 9 50 9"/></svg>',
		);
		return isset( $p[ $id ] ) ? $p[ $id ] : $p['signature'];
	}
}

if ( ! function_exists( 'sc_svg_draw_render' ) ) {
	function sc_svg_draw_render( $atts ) {
		$source = sc_get( 'svg/source', $atts, 'preset' );
		$svg    = '';

		if ( $source === 'code' ) {
			$svg = sc_svg_draw_sanitize( sc_get( 'svg/code/code', $atts, '' ) );
		} elseif ( $source === 'upload' ) {
			$file = sc_get( 'svg/upload/file', $atts, array() );
			$url  = ( is_array( $file ) && ! empty( $file['url'] ) ) ? $file['url'] : '';
			if ( $url !== '' ) {
				$dir  = wp_get_upload_dir();
				$path = ( ! empty( $dir['baseurl'] ) && ! empty( $dir['basedir'] ) ) ? str_replace( $dir['baseurl'], $dir['basedir'], $url ) : '';
				// Confirm the resolved path stays INSIDE the uploads dir (no ../ traversal) and is an .svg.
					$real = $path ? realpath( $path ) : false;
					$root = ! empty( $dir['basedir'] ) ? realpath( $dir['basedir'] ) : false;
					if ( $real && $root && strpos( $real, $root . DIRECTORY_SEPARATOR ) === 0
						&& strtolower( pathinfo( $real, PATHINFO_EXTENSION ) ) === 'svg' ) {
						$svg = sc_svg_draw_sanitize( file_get_contents( $real ) );
					}
			}
		} else {
			$svg = sc_svg_draw_preset( sc_get( 'svg/preset/preset', $atts, 'signature' ) );
		}
		if ( $svg === '' ) { $svg = sc_svg_draw_preset( 'signature' ); }

		$trigger    = in_array( sc_get( 'trigger', $atts, 'view' ), array( 'view', 'load', 'hover' ), true ) ? sc_get( 'trigger', $atts, 'view' ) : 'view';
		$duration   = (float) sc_get( 'duration', $atts, 1.6 );
		$stagger    = (float) sc_get( 'stagger', $atts, 0.15 );
		$direction  = sc_get( 'direction', $atts, 'normal' ) === 'reverse' ? 'reverse' : 'normal';
		$loop       = sc_get( 'loop', $atts, 'no' ) === 'yes';
		$width      = (float) sc_get( 'stroke_width', $atts, 2 );
		$fill_after = sc_get( 'fill_after', $atts, 'no' ) === 'yes';
		$max_width  = (int) sc_get( 'max_width', $atts, 320 );
		$align      = in_array( sc_get( 'align', $atts, 'center' ), array( 'left', 'center', 'right' ), true ) ? sc_get( 'align', $atts, 'center' ) : 'center';

		$stroke = function_exists( 'sc_color_to_css' ) ? sc_color_to_css( sc_get( 'stroke_color', $atts, '' ), '#2f74e6' ) : '#2f74e6';
		$fill   = function_exists( 'sc_color_to_css' ) ? sc_color_to_css( sc_get( 'fill_color', $atts, '' ), '#2f74e6' ) : '#2f74e6';

		$style = '--draw-stroke:' . esc_attr( $stroke ) . '; --draw-width:' . esc_attr( $width ) . 'px;';
		if ( $fill_after ) { $style .= ' --draw-fill:' . esc_attr( $fill ) . ';'; }
		if ( $max_width > 0 ) { $style .= ' max-width:' . $max_width . 'px;'; }

		$classes = 'sc-svg-draw sc-svg-draw--' . esc_attr( $align );

		$out  = '<div class="' . $classes . '" style="' . $style . '"';
		$out .= ' data-draw-trigger="' . esc_attr( $trigger ) . '"';
		$out .= ' data-draw-duration="' . esc_attr( $duration ) . '"';
		$out .= ' data-draw-stagger="' . esc_attr( $stagger ) . '"';
		$out .= ' data-draw-direction="' . esc_attr( $direction ) . '"';
		if ( $loop ) { $out .= ' data-draw-loop="1"'; }
		if ( $fill_after ) { $out .= ' data-draw-fill="1"'; }
		$out .= '>' . $svg . '</div>';
		return $out;
	}
}

echo sc_svg_draw_render( $atts ); // phpcs:ignore -- markup sanitized in sc_svg_draw_sanitize()
