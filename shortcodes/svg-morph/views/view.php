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
 * Built-in morph shapes — single closed paths in a 0–100 box. The runtime samples each into N
 * points, so any of these can morph into any other. Keep each a SINGLE subpath (one M … Z): the
 * sampler walks a continuous outline, so a multi-subpath shape would tear.
 */
if ( ! function_exists( 'sc_svg_morph_shapes' ) ) {
	function sc_svg_morph_shapes() {
		return array(
			'circle'   => 'M50,3 C76,3 97,24 97,50 C97,76 76,97 50,97 C24,97 3,76 3,50 C3,24 24,3 50,3 Z',
			'square'   => 'M6,6 L94,6 L94,94 L6,94 Z',
			'triangle' => 'M50,4 L96,90 L4,90 Z',
			'diamond'  => 'M50,4 L96,50 L50,96 L4,50 Z',
			'pentagon' => 'M50,4 L95,37 L78,92 L22,92 L5,37 Z',
			'hexagon'  => 'M50,3 L91,26 L91,74 L50,97 L9,74 L9,26 Z',
			'star'     => 'M50,3 L62,37 L98,37 L69,59 L80,95 L50,73 L20,95 L31,59 L2,37 L38,37 Z',
			'heart'    => 'M50,88 C10,60 4,30 26,18 C41,10 50,24 50,32 C50,24 59,10 74,18 C96,30 90,60 50,88 Z',
			'droplet'  => 'M50,4 C50,4 86,44 86,64 A36,36 0 1 1 14,64 C14,44 50,4 50,4 Z',
			'blob1'    => 'M50,6 C72,6 90,20 92,44 C94,68 80,90 56,92 C32,94 10,82 7,58 C4,34 20,10 50,6 Z',
			'blob2'    => 'M54,5 C78,9 95,28 90,50 C86,72 95,86 69,93 C43,100 18,86 11,62 C4,38 14,16 54,5 Z',
			'blob3'    => 'M46,8 C71,4 86,28 94,50 C99,71 74,90 52,93 C30,96 12,80 8,55 C4,30 22,12 46,8 Z',
		);
	}
}

/**
 * Curated morph SETS — an ordered sequence of shape keys the element cycles through. Chosen because
 * each pair reads well as a morph. `loopback` closes the cycle (…→ last → first) for a seamless loop.
 */
/** Turn an SVG `points` list into a path `d`. */
if ( ! function_exists( 'sc_svg_morph_points_to_path' ) ) {
	function sc_svg_morph_points_to_path( $pts, $close ) {
		$pts  = trim( preg_replace( '/\s+/', ' ', str_replace( ',', ' ', (string) $pts ) ) );
		$nums = $pts === '' ? array() : preg_split( '/\s+/', $pts );
		if ( count( $nums ) < 4 ) { return ''; }
		$d = 'M' . $nums[0] . ',' . $nums[1];
		for ( $i = 2; $i + 1 < count( $nums ); $i += 2 ) { $d .= 'L' . $nums[ $i ] . ',' . $nums[ $i + 1 ]; }
		return $d . ( $close ? ' Z' : '' );
	}
}

/**
 * Extract the PRIMARY morphable path `d` from SVG markup: the longest `<path>` (a heuristic for the
 * main outline), or a basic shape (circle / rect / polygon / polyline) converted to a path. Only a
 * validated numeric `d` is ever returned — never the raw markup — so this is safe to echo. Complex
 * multi-part art collapses to its largest single outline (morphing needs one shape).
 */
if ( ! function_exists( 'sc_svg_morph_extract_path' ) ) {
	function sc_svg_morph_extract_path( $svg ) {
		$svg = (string) $svg;
		if ( $svg === '' ) { return ''; }
		$safe = '/^[MmLlHhVvCcSsQqTtAaZz0-9eE,\.\+\-\s]+$/';
		$best = '';
		if ( preg_match_all( '/<path\b[^>]*\bd\s*=\s*"([^"]+)"/i', $svg, $m ) ) {
			foreach ( $m[1] as $d ) {
				$d = trim( html_entity_decode( $d, ENT_QUOTES ) );
				if ( preg_match( $safe, $d ) && strlen( $d ) > strlen( $best ) ) { $best = $d; }
			}
		}
		if ( $best !== '' ) { return $best; }
		// No <path> — convert a basic shape.
		if ( preg_match( '/<circle\b[^>]*\bcx\s*=\s*"([\d.\-]+)"[^>]*\bcy\s*=\s*"([\d.\-]+)"[^>]*\br\s*=\s*"([\d.\-]+)"/i', $svg, $c ) ) {
			$cx = (float) $c[1]; $cy = (float) $c[2]; $r = (float) $c[3];
			return $r > 0 ? sprintf( 'M%s,%s a%s,%s 0 1,0 %s,0 a%s,%s 0 1,0 -%s,0 Z', $cx - $r, $cy, $r, $r, 2 * $r, $r, $r, 2 * $r ) : '';
		}
		if ( preg_match( '/<ellipse\b[^>]*\bcx\s*=\s*"([\d.\-]+)"[^>]*\bcy\s*=\s*"([\d.\-]+)"[^>]*\brx\s*=\s*"([\d.\-]+)"[^>]*\bry\s*=\s*"([\d.\-]+)"/i', $svg, $e ) ) {
			$cx = (float) $e[1]; $cy = (float) $e[2]; $rx = (float) $e[3]; $ry = (float) $e[4];
			return ( $rx > 0 && $ry > 0 ) ? sprintf( 'M%s,%s a%s,%s 0 1,0 %s,0 a%s,%s 0 1,0 -%s,0 Z', $cx - $rx, $cy, $rx, $ry, 2 * $rx, $rx, $ry, 2 * $rx ) : '';
		}
		if ( preg_match( '/<rect\b[^>]*\bx\s*=\s*"([\d.\-]+)"[^>]*\by\s*=\s*"([\d.\-]+)"[^>]*\bwidth\s*=\s*"([\d.\-]+)"[^>]*\bheight\s*=\s*"([\d.\-]+)"/i', $svg, $r2 ) ) {
			$x = (float) $r2[1]; $y = (float) $r2[2]; $w = (float) $r2[3]; $h = (float) $r2[4];
			return ( $w > 0 && $h > 0 ) ? sprintf( 'M%s,%s H%s V%s H%s Z', $x, $y, $x + $w, $y + $h, $x ) : '';
		}
		if ( preg_match( '/<polygon\b[^>]*\bpoints\s*=\s*"([^"]+)"/i', $svg, $pg ) ) {
			return sc_svg_morph_points_to_path( $pg[1], true );
		}
		if ( preg_match( '/<polyline\b[^>]*\bpoints\s*=\s*"([^"]+)"/i', $svg, $pl ) ) {
			return sc_svg_morph_points_to_path( $pl[1], false );
		}
		return '';
	}
}

if ( ! function_exists( 'sc_svg_morph_render' ) ) {
	function sc_svg_morph_render( $atts ) {
		$shapes_lib = sc_svg_morph_shapes();
		$paths      = array();
		$timing     = array(); // per shape: [ morph_dur, hold ] (seconds)
		$safe       = '/^[MmLlHhVvCcSsQqTtAaZz0-9eE,\.\+\-\s]+$/';

		$items = sc_get( 'shapes_list', $atts, array() );
		foreach ( (array) $items as $item ) {
			$pick = ( isset( $item['pick'] ) && is_array( $item['pick'] ) ) ? $item['pick'] : array();
			$src  = isset( $pick['source'] ) ? $pick['source'] : 'library';
			$d    = '';
			if ( 'library' === $src ) {
				$sk = isset( $pick['library']['shape'] ) ? $pick['library']['shape'] : '';
				if ( isset( $shapes_lib[ $sk ] ) ) { $d = $shapes_lib[ $sk ]; }
			} elseif ( 'upload' === $src ) {
				$markup = isset( $pick['upload']['markup'] ) ? (string) $pick['upload']['markup'] : '';
				if ( $markup !== '' ) { $d = sc_svg_morph_extract_path( $markup ); }
			} elseif ( 'custom' === $src ) {
				$raw = isset( $pick['custom']['d'] ) ? trim( (string) $pick['custom']['d'] ) : '';
				if ( $raw !== '' && preg_match( $safe, $raw ) ) { $d = $raw; }
			}
			if ( $d !== '' && preg_match( $safe, $d ) ) {
				$paths[]  = $d;
				$timing[] = array(
					max( 0.2, min( 8, isset( $item['morph_dur'] ) ? (float) $item['morph_dur'] : 1.2 ) ),
					max( 0, min( 6, isset( $item['hold'] ) ? (float) $item['hold'] : 0.6 ) ),
				);
			}
		}
		$loopback = sc_get( 'loopback', $atts, 'yes' ) !== 'no';

		// Fallback: a liquid-blob loop if nothing valid was supplied.
		if ( count( $paths ) < 2 ) {
			$paths    = array( $shapes_lib['blob1'], $shapes_lib['blob2'], $shapes_lib['blob3'] );
			$timing   = array( array( 1.2, 0.6 ), array( 1.2, 0.6 ), array( 1.2, 0.6 ) );
			$loopback = true;
		}

		$render   = sc_get( 'render_mode', $atts, 'fill' ) === 'stroke' ? 'stroke' : 'fill';
		$trigger  = in_array( sc_get( 'trigger', $atts, 'loop' ), array( 'loop', 'hover', 'view', 'click' ), true ) ? sc_get( 'trigger', $atts, 'loop' ) : 'loop';
		$ease     = in_array( sc_get( 'easing', $atts, 'ease-in-out' ), array( 'linear', 'ease-in', 'ease-out', 'ease-in-out' ), true ) ? sc_get( 'easing', $atts, 'ease-in-out' ) : 'ease-in-out';
		$width    = max( 1, min( 16, (float) sc_get( 'stroke_width', $atts, 3 ) ) );
		$max_w    = (int) sc_get( 'max_width', $atts, 200 );
		$align    = in_array( sc_get( 'align', $atts, 'center' ), array( 'left', 'center', 'right' ), true ) ? sc_get( 'align', $atts, 'center' ) : 'center';

		$fill   = function_exists( 'sc_color_to_css' ) ? sc_color_to_css( sc_get( 'fill_color', $atts, '' ), '#2f74e6' ) : '#2f74e6';
		$stroke = function_exists( 'sc_color_to_css' ) ? sc_color_to_css( sc_get( 'stroke_color', $atts, '' ), '#2f74e6' ) : '#2f74e6';

		$style  = '--morph-fill:' . esc_attr( $fill ) . '; --morph-stroke:' . esc_attr( $stroke ) . '; --morph-width:' . esc_attr( $width ) . 'px;';
		if ( $max_w > 0 ) { $style .= ' max-width:' . $max_w . 'px;'; }

		$json   = wp_json_encode( array_values( $paths ) );
		$timing = wp_json_encode( array_values( $timing ) );

		$out  = '<div class="sc-svg-morph sc-svg-morph--' . esc_attr( $render ) . ' sc-svg-morph--' . esc_attr( $align ) . '" style="' . $style . '"';
		$out .= ' data-shapes="' . esc_attr( $json ) . '"';
		$out .= ' data-timing="' . esc_attr( $timing ) . '"';
		$out .= ' data-trigger="' . esc_attr( $trigger ) . '"';
		$out .= ' data-ease="' . esc_attr( $ease ) . '"';
		if ( $loopback ) { $out .= ' data-loopback="1"'; }
		$out .= '>';
		$out .= '<svg viewBox="-6 -6 112 112" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">';
		$out .= '<path d="' . esc_attr( $paths[0] ) . '" /></svg>';
		$out .= '</div>';
		return $out;
	}
}

echo sc_svg_morph_render( $atts ); // phpcs:ignore -- all output escaped above
