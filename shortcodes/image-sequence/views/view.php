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

/** Resolve the ordered list of frame URLs from either source. */
if ( ! function_exists( 'sc_seq_frames' ) ) {
	function sc_seq_frames( $atts ) {
		$source = sc_get( 'frames_source/source', $atts, 'upload' );
		$urls   = array();

		if ( $source === 'pattern' ) {
			$pattern = trim( (string) sc_get( 'frames_source/pattern/url_pattern', $atts, '' ) );
			$count   = (int) sc_get( 'frames_source/pattern/count', $atts, 0 );
			$start   = (int) sc_get( 'frames_source/pattern/start', $atts, 1 );
			$pad     = (int) sc_get( 'frames_source/pattern/pad', $atts, 0 );
			if ( $pattern !== '' && strpos( $pattern, '%d' ) !== false && $count > 0 && $count <= 2000 ) {
				for ( $i = 0; $i < $count; $i++ ) {
					$n   = $start + $i;
					$num = $pad > 0 ? str_pad( (string) $n, $pad, '0', STR_PAD_LEFT ) : (string) $n;
					$urls[] = esc_url_raw( str_replace( '%d', $num, $pattern ) );
				}
			}
		} else {
			$frames = sc_get( 'frames_source/upload/frames', $atts, array() );
			if ( is_array( $frames ) ) {
				foreach ( $frames as $img ) {
					if ( ! is_array( $img ) ) { continue; }
					$url = '';
					$id  = isset( $img['attachment_id'] ) ? (int) $img['attachment_id'] : 0;
					if ( $id ) {
						$src = wp_get_attachment_image_src( $id, 'full' );
						if ( $src && ! empty( $src[0] ) ) { $url = $src[0]; }
					}
					if ( $url === '' && ! empty( $img['url'] ) ) { $url = $img['url']; }
					if ( $url !== '' ) { $urls[] = esc_url_raw( $url ); }
				}
			}
		}
		return array_values( array_filter( $urls ) );
	}
}

$frames = sc_seq_frames( $atts );

$mode   = sc_get( 'mode', $atts, 'pin' ) === 'inview' ? 'inview' : 'pin';
$fit    = sc_get( 'fit', $atts, 'cover' ) === 'contain' ? 'contain' : 'cover';
$dir    = sc_get( 'direction', $atts, 'forward' ) === 'reverse' ? 'reverse' : 'forward';
$pinlen = max( 1, min( 6, (int) sc_get( 'pin_length', $atts, 2 ) ) );
$height = max( 160, (int) sc_get( 'height', $atts, 520 ) );
$bg     = function_exists( 'sc_color_to_css' ) ? sc_color_to_css( sc_get( 'bg', $atts, '' ), '#0b0f1a' ) : '#0b0f1a';

if ( empty( $frames ) ) {
	if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
		echo '<div class="sc-seq__empty" style="padding:2rem;text-align:center;color:#888;border:1px dashed #ccd;border-radius:8px;">'
			. esc_html__( 'Image Sequence — add frames (upload or a URL pattern) to preview.', 'fw' ) . '</div>';
	}
	return;
}

$data  = ' data-seq-frames="' . esc_attr( wp_json_encode( $frames ) ) . '"';
$data .= ' data-seq-mode="' . esc_attr( $mode ) . '"';
$data .= ' data-seq-fit="' . esc_attr( $fit ) . '"';
$data .= ' data-seq-dir="' . esc_attr( $dir ) . '"';

if ( $mode === 'pin' ) {
	$outer_style = '--seq-screens:' . ( $pinlen + 1 ) . ';--seq-bg:' . esc_attr( $bg ) . ';';
	echo '<div class="sc-seq sc-seq--pin" style="' . esc_attr( $outer_style ) . '"' . $data . '>'
		. '<div class="sc-seq__pin"><canvas class="sc-seq__canvas"></canvas></div>'
		. '</div>';
} else {
	$outer_style = 'height:' . $height . 'px;--seq-bg:' . esc_attr( $bg ) . ';';
	echo '<div class="sc-seq sc-seq--inview" style="' . esc_attr( $outer_style ) . '"' . $data . '>'
		. '<canvas class="sc-seq__canvas"></canvas>'
		. '</div>';
}
