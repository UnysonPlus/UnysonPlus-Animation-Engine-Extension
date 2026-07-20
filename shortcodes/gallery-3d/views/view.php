<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * 3D Gallery — common render + design dispatch.
 *
 * Normalizes the image slots (self-contained — the Gallery's helpers only exist when a Gallery is
 * also on the page), builds the shared card renderer + wrapper, then includes the chosen design
 * template under designs/<design>.php. Each design adds its own data-* attrs and lays out the cards
 * ($render_card()). Pure CSS 3D + one rAF driver (gallery-3d.js); reduced-motion aware.
 *
 * @var array $atts
 */

if ( ! function_exists( 'sc_get' ) ) {
	function sc_get( $path, $atts, $default = '' ) {
		if ( function_exists( 'fw_akg' ) ) {
			$v = fw_akg( $path, $atts, null );
			if ( $v !== null ) { return $v; }
		}
		return $default;
	}
}

$sc_get = function ( $k, $d = '' ) use ( $atts ) { return sc_get( $k, $atts, $d ); };

/* ---- images (self-contained normalization) ---- */
$images = $sc_get( 'images', array() );
$items  = array();
foreach ( (array) $images as $img ) {
	if ( ! is_array( $img ) ) { continue; }
	$id   = isset( $img['attachment_id'] ) ? (int) $img['attachment_id'] : 0;
	$url  = ''; $full = ''; $alt = ''; $cap = ''; $title = ''; $desc = '';
	if ( $id ) {
		$s    = wp_get_attachment_image_src( $id, 'large' );
		$f    = wp_get_attachment_image_src( $id, 'full' );
		$url  = $s ? $s[0] : ( ! empty( $img['url'] ) ? $img['url'] : '' );
		$full = $f ? $f[0] : $url;
		$alt  = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
		$p    = get_post( $id );
		if ( $p ) { $cap = (string) $p->post_excerpt; $title = (string) $p->post_title; $desc = (string) $p->post_content; }
	} elseif ( ! empty( $img['url'] ) ) {
		$url = $full = (string) $img['url'];
	}
	if ( $url === '' ) { continue; }
	$items[] = array( 'id' => $id, 'url' => $url, 'full' => $full, 'alt' => $alt, 'caption' => $cap, 'title' => $title, 'description' => $desc );
}

/* ---- chosen design + per-design option reader ---- */
$designs = array( 'carousel_ring', 'panorama_wall', 'card_sphere', 'orbit_globe' );
$design  = (string) $sc_get( 'design_settings/design', 'carousel_ring' );
if ( ! in_array( $design, $designs, true ) ) { $design = 'carousel_ring'; }
$dp = function ( $sub, $default = '' ) use ( $sc_get, $design ) { return $sc_get( 'design_settings/' . $design . '/' . $sub, $default ); };

/* ---- shared style / caption / lightbox ---- */
$boxp   = function_exists( 'sc_card_box_style_class' ) ? sc_card_box_style_class( $atts ) : '';
$shadow = '';
$sh_val = $sc_get( 'shadow', array() );
if ( is_array( $sh_val ) && class_exists( 'FW_Option_Type_Box_Shadow' ) ) { $shadow = FW_Option_Type_Box_Shadow::to_css( $sh_val ); }

$captions   = in_array( $sc_get( 'captions', 'none' ), array( 'none', 'hover', 'below' ), true ) ? $sc_get( 'captions', 'none' ) : 'none';
$cap_source = (string) $sc_get( 'caption_source', 'caption' );
/* Lightbox is opt-in: only an explicit 'lightbox' turns it on (default + fallback = off). */
$click      = $sc_get( 'click_action', 'none' ) === 'lightbox' ? 'lightbox' : 'none';
$lb_group   = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'tdg-lb-' ) : uniqid( 'tdg-lb-' );
$cap_text   = function ( $item ) use ( $cap_source ) {
	$key = in_array( $cap_source, array( 'title', 'alt', 'description', 'caption' ), true ) ? $cap_source : 'caption';
	return isset( $item[ $key ] ) ? trim( (string) $item[ $key ] ) : '';
};

/* ---- common frame (each design owns height + background) ---- */
$h_val  = $dp( 'height', array( 'value' => 730, 'unit' => 'px' ) );
$height = ( is_array( $h_val ) && class_exists( 'FW_Option_Type_Unit_Input' ) ) ? FW_Option_Type_Unit_Input::to_string( $h_val ) : '730px';
if ( $height === '' ) { $height = '730px'; }
$bg = '';
$bg_val = $dp( 'background', '' );
if ( is_array( $bg_val ) ) {
	$cus = isset( $bg_val['custom'] ) ? trim( (string) $bg_val['custom'] ) : '';
	$pre = isset( $bg_val['predefined'] ) ? trim( (string) $bg_val['predefined'] ) : '';
	if ( $pre !== '' ) { $bg = 'var(--color-' . preg_replace( '/[^a-z0-9\-]/', '', preg_replace( '/^(text|bg)-/', '', $pre ) ) . ')'; }
	elseif ( $cus !== '' ) { $bg = preg_replace( '/[^A-Za-z0-9#(),.%\s]/', '', $cus ); }
} elseif ( is_string( $bg_val ) && $bg_val !== '' ) { $bg = $bg_val; }

$corner = max( 0, min( 60, (int) $dp( 'corner_radius', 12 ) ) );
/* Card Padding is a % of the card width (CSS padding % always resolves against the inline size, so it
 * stays uniform on all four sides) — matches the animos control and scales with Card Size. */
$pad    = max( 0, min( 40, (float) $dp( 'padding', 0 ) ) );
$ratio_key = (string) $dp( 'card_ratio', '1-1' );
$ratio_css = str_replace( '-', ' / ', preg_match( '/^\d+-\d+$/', $ratio_key ) ? $ratio_key : '1-1' );

$inner_class = trim( 'tdg__inner' . ( $boxp !== '' ? ' ' . $boxp : '' ) );

/* ---- one card renderer, shared by every design ---- */
$render_card = function ( $item ) use ( $inner_class, $cap_text, $captions, $click, $lb_group ) {
	$caption = $cap_text( $item );
	$full    = ( ! empty( $item['full'] ) ) ? $item['full'] : ( ! empty( $item['url'] ) ? $item['url'] : '' );
	$src     = ( ! empty( $item['url'] ) ) ? $item['url'] : $full;
	$alt     = ( ! empty( $item['alt'] ) ) ? $item['alt'] : $caption;
	$has_ov  = ( $captions === 'hover' && $caption !== '' );
	$has_bl  = ( $captions === 'below' && $caption !== '' );
	$o  = '<div class="' . esc_attr( $inner_class ) . '">';
	$o .= ( $click === 'lightbox' && $full !== '' )
		? '<a class="tdg__link" href="' . esc_url( $full ) . '" data-fw-lightbox="' . esc_attr( $lb_group ) . '"' . ( $caption !== '' ? ' data-fw-caption="' . esc_attr( $caption ) . '"' : '' ) . '>'
		: '<span class="tdg__link">';
	$o .= '<img class="tdg__img" src="' . esc_url( $src ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" decoding="async" />';
	if ( $has_ov ) { $o .= '<span class="tdg__overlay"><span class="tdg__overlay-text">' . esc_html( $caption ) . '</span></span>'; }
	$o .= ( $click === 'lightbox' && $full !== '' ) ? '</a>' : '</span>';
	$o .= '</div>';
	if ( $has_bl ) { $o .= '<figcaption class="tdg__caption">' . esc_html( $caption ) . '</figcaption>'; }
	return $o;
};

/* ---- wrapper (animations / css_class / advanced) + shared vars ---- */
$atts['base_class']       = 'tdg';
$atts['unique_id_prefix'] = 'tdg-';
$attr = function_exists( 'sc_build_wrapper_attr' ) ? sc_build_wrapper_attr( $atts ) : array( 'class' => 'tdg' );
$attr['class'] = trim( ( isset( $attr['class'] ) ? $attr['class'] : '' ) . ' tdg--' . str_replace( '_', '-', $design ) );
/* "Use as Section Background" — fill the parent Section behind its content (shared runtime). In
 * that mode sc-bg-fill governs the size, so the fixed Stage Height is dropped. */
$as_bg = function_exists( 'sc_section_background_is_on' ) && sc_section_background_is_on( $sc_get( 'as_background', 'no' ) );
if ( $as_bg ) {
	$attr['class'] = trim( $attr['class'] . ' sc-bg-fill tdg--bg' );
	if ( function_exists( 'sc_section_background_use' ) ) { sc_section_background_use(); }
}

$attr['style'] = ( isset( $attr['style'] ) ? rtrim( $attr['style'], '; ' ) . ';' : '' )
	. ( $as_bg ? '' : 'height:' . esc_attr( $height ) . ';' )
	. '--tdg-radius:' . $corner . 'px;'
	. '--tdg-pad:' . $pad . '%;'
	. '--tdg-ratio:' . esc_attr( $ratio_css ) . ';'
	. ( $shadow !== '' ? '--tdg-shadow:' . esc_attr( $shadow ) . ';' : '' )
	. ( $bg !== '' ? '--tdg-bg:' . esc_attr( $bg ) . ';' : '' );

if ( empty( $items ) ) {
	echo '<div class="tdg tdg--empty"><p class="tdg__empty">' . esc_html__( 'Add images to the 3D Gallery.', 'fw' ) . '</p></div>';
	return;
}

$design_file = dirname( __FILE__ ) . '/designs/' . str_replace( '_', '-', $design ) . '.php';
if ( ! file_exists( $design_file ) ) { $design_file = dirname( __FILE__ ) . '/designs/carousel-ring.php'; }
include $design_file;
