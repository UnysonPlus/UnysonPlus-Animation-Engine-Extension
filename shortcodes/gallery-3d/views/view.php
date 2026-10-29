<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery — common render + design dispatch.
 *
 * Normalizes the image slots (self-contained — the Gallery's helpers only exist when a Gallery is
 * also on the page), builds the shared card renderer + wrapper, then includes the chosen design
 * template under designs/<design>.php. Each design adds its own data-* attrs and lays out the cards
 * ($render_card()). Pure CSS 3D + one rAF driver (gallery-3d.js); reduced-motion aware.
 *
 * @var array $atts
 */

if ( ! function_exists( 'sc_get' ) ) {
	/** Returns a value from the shortcode atts array by path via fw_akg, falling back to a default. */
	function sc_get( $path, $atts, $default = '' ) {
		if ( function_exists( 'fw_akg' ) ) {
			$v = fw_akg( $path, $atts, null );
			if ( $v !== null ) { return $v; }
		}
		return $default;
	}
}

$sc_get = function ( $k, $d = '' ) use ( $atts ) { return sc_get( $k, $atts, $d ); };

/* ---- cards: Media Library images OR a post type's featured images (self-contained normalization).
 * `source` is a NEW key — pre-existing saves have nothing at it and fall through to 'media', whose
 * `images` option still lives at its original path, so old galleries are untouched. Each item may
 * carry a 'link' (post permalink, or the image's Media-Library "Link URL" meta) consumed when
 * On Card Click = Open Link. ---- */
$src_kind = ( $sc_get( 'source/kind', 'media' ) === 'posts' ) ? 'posts' : 'media';
$items    = array();

if ( $src_kind === 'posts' ) {
	$q_type  = (string) $sc_get( 'source/posts/post_type', 'post' );
	$q_count = max( 1, min( 200, (int) $sc_get( 'source/posts/count', 12 ) ) );
	$q_order = (string) $sc_get( 'source/posts/orderby', 'date_desc' );
	$q_args  = array(
		'post_type'           => $q_type,
		'post_status'         => 'publish',
		'posts_per_page'      => $q_count,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
		'meta_key'            => '_thumbnail_id', // only posts WITH a featured image become cards
	);
	switch ( $q_order ) {
		case 'date_asc':   $q_args['orderby'] = 'date';       $q_args['order'] = 'ASC';  break;
		case 'title':      $q_args['orderby'] = 'title';      $q_args['order'] = 'ASC';  break;
		case 'menu_order': $q_args['orderby'] = 'menu_order'; $q_args['order'] = 'ASC';  break;
		case 'rand':       $q_args['orderby'] = 'rand';                                   break;
		default:           $q_args['orderby'] = 'date';       $q_args['order'] = 'DESC';
	}
	foreach ( get_posts( $q_args ) as $qp ) {
		$tid = (int) get_post_thumbnail_id( $qp );
		if ( ! $tid ) { continue; }
		$s = wp_get_attachment_image_src( $tid, 'large' );
		$f = wp_get_attachment_image_src( $tid, 'full' );
		if ( ! $s ) { continue; }
		$alt = trim( (string) get_post_meta( $tid, '_wp_attachment_image_alt', true ) );
		$items[] = array(
			'id'          => $tid,
			'url'         => $s[0],
			'full'        => $f ? $f[0] : $s[0],
			'alt'         => ( $alt !== '' ? $alt : (string) $qp->post_title ),
			'caption'     => (string) $qp->post_title,
			'title'       => (string) $qp->post_title,
			'description' => (string) get_the_excerpt( $qp ),
			'link'        => (string) get_permalink( $qp ),
		);
	}
} else {
	$images = $sc_get( 'source/media/images', null );
	if ( ! is_array( $images ) ) { $images = $sc_get( 'images', array() ); } // pre-source saves (flat key)
	foreach ( (array) $images as $img ) {
		if ( ! is_array( $img ) ) { continue; }
		$id   = isset( $img['attachment_id'] ) ? (int) $img['attachment_id'] : 0;
		$url  = ''; $full = ''; $alt = ''; $cap = ''; $title = ''; $desc = ''; $link = ''; $lnt = false;
		if ( $id ) {
			$s    = wp_get_attachment_image_src( $id, 'large' );
			$f    = wp_get_attachment_image_src( $id, 'full' );
			$url  = $s ? $s[0] : ( ! empty( $img['url'] ) ? $img['url'] : '' );
			$full = $f ? $f[0] : $url;
			$alt  = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
			$link = trim( (string) get_post_meta( $id, '_upw_link_url', true ) ); // the Media Library "Link URL" field
			$lnt  = ( $link !== '' && get_post_meta( $id, '_upw_link_new_tab', true ) === '1' ); // its per-image new-tab checkbox
			$p    = get_post( $id );
			if ( $p ) { $cap = (string) $p->post_excerpt; $title = (string) $p->post_title; $desc = (string) $p->post_content; }
		} elseif ( ! empty( $img['url'] ) ) {
			$url = $full = (string) $img['url'];
		}
		if ( $url === '' ) { continue; }
		$items[] = array( 'id' => $id, 'url' => $url, 'full' => $full, 'alt' => $alt, 'caption' => $cap, 'title' => $title, 'description' => $desc, 'link' => $link, 'link_new_tab' => $lnt );
	}
}

/* ---- chosen design + per-design option reader ---- */
$designs = array( 'scroll_choreography', 'carousel_ring', 'panorama_wall', 'card_sphere', 'orbit_globe', 'sphere_cascade', 'totem_wall', 'parallax_totem', 'card_tunnel', 'spiral_stream', 'depth_stack', 'card_reel', 'film_strip', 'wheel_carousel', 'cover_flow', 'carousel_flow', 'hero_reel', 'focus_slider', 'mosaic_marquee', 'photo_scatter', 'card_stack', 'device_cycler' );
/** Allow a child-theme-registered design key to render instead of falling back to carousel_ring.
 *  Pair with 'upw_gallery_3d_design_groups' (picker) + window.upwGallery3d.registerDesign (driver). */
$designs = apply_filters( 'upw_gallery_3d_render_designs', $designs );
$design  = (string) $sc_get( 'design_settings/design', 'carousel_ring' );
if ( ! in_array( $design, $designs, true ) ) { $design = 'carousel_ring'; }
$dp = function ( $sub, $default = '' ) use ( $sc_get, $design ) { return $sc_get( 'design_settings/' . $design . '/' . $sub, $default ); };

/* ---- motion (shared shape, parsed once for every design) ----
 * The Motion multi-picker (design_settings/<design>/motion) nests the mode + that mode's settings:
 *   [ 'mode' => 'auto|continuous|scroll|static', '<mode>' => [ speed|direction|hover_behavior |
 *     pin|scroll_length|direction ] ]
 * Saves from BEFORE the picker existed used flat keys (drive/speed/direction/hover_behavior) — they
 * are honoured as a fallback so no migration is needed (the modal simply shows the default Motion
 * until re-saved). The design files consume $motion_* and add their own clamps. */
$auto_key           = ( $design === 'carousel_ring' ) ? 'auto' : 'continuous';
$motion_legacy_drag = false;
$m = $dp( 'motion', null );
if ( is_array( $m ) ) {
	$motion_mode = ( isset( $m['mode'] ) && is_string( $m['mode'] ) && $m['mode'] !== '' ) ? $m['mode'] : $auto_key;
	$mo          = ( isset( $m[ $motion_mode ] ) && is_array( $m[ $motion_mode ] ) ) ? $m[ $motion_mode ] : array();
} else { // legacy flat keys (pre-Motion-picker saves)
	$motion_mode = (string) $dp( 'drive', $auto_key );
	$mo          = array(
		'speed'          => $dp( 'speed', $design === 'carousel_ring' ? 16 : 20 ),
		'direction'      => $dp( 'direction', 'left' ),
		'hover_behavior' => $dp( 'hover_behavior', 'slow' ),
	);
}
if ( $motion_mode === 'drag' ) { $motion_mode = 'static'; $motion_legacy_drag = true; } // legacy "Drag" mode = Static + Drag-to-spin
if ( ! in_array( $motion_mode, array( $auto_key, 'scroll', 'static' ), true ) ) { $motion_mode = $auto_key; }
$motion_speed = isset( $mo['speed'] ) ? (float) $mo['speed'] : ( $design === 'carousel_ring' ? 16 : 20 );
$motion_dir   = isset( $mo['direction'] ) ? (string) $mo['direction'] : 'left';
$motion_hover = ( isset( $mo['hover_behavior'] ) && in_array( $mo['hover_behavior'], array( 'none', 'pause', 'slow' ), true ) ) ? $mo['hover_behavior'] : 'slow';
$motion_pin   = ( ! isset( $mo['pin'] ) || $mo['pin'] === 'yes' ); // pin defaults ON for scroll-scrub
$motion_len   = isset( $mo['scroll_length'] ) ? max( 1, min( 5, (float) $mo['scroll_length'] ) ) : 2.5;
$motion_rounds = isset( $mo['rounds'] ) ? max( 0.25, min( 6, (float) $mo['rounds'] ) ) : 1; // scroll-scrub: full turns across the pin

/* ---- shared style / caption / lightbox ---- */
$boxp   = function_exists( 'sc_card_box_style_class' ) ? sc_card_box_style_class( $atts ) : '';
$shadow = '';
$sh_val = $sc_get( 'shadow', array() );
if ( is_array( $sh_val ) && class_exists( 'FW_Option_Type_Box_Shadow' ) ) { $shadow = FW_Option_Type_Box_Shadow::to_css( $sh_val ); }

$captions   = in_array( $sc_get( 'captions', 'none' ), array( 'none', 'hover', 'below' ), true ) ? $sc_get( 'captions', 'none' ) : 'none';
$cap_source = (string) $sc_get( 'caption_source', 'caption' );
/* Card click is opt-in: 'lightbox' (full image in the shared lightbox) or 'link' (each card's own
 * URL — the post's page with the Post Type source, or the image's Media-Library "Link URL" field).
 * The NEW `click` multi-picker (action + per-action settings) is read first; the legacy flat
 * `click_action` scalar stays as the fallback. */
$click_raw = (string) $sc_get( 'click/action', (string) $sc_get( 'click_action', 'none' ) );
$click     = in_array( $click_raw, array( 'lightbox', 'link' ), true ) ? $click_raw : 'none';
$lb_group   = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'tdg-lb-' ) : uniqid( 'tdg-lb-' );

/* Lightbox click needs the SHARED Gallery lightbox runtime (a delegated click handler) + its overlay
 * styles. The 3D gallery lives in the Animation Engine, so it isn't guaranteed a Gallery shortcode is
 * also on the page — enqueue the same handles (deduped if the Gallery shortcode already did) only when
 * a card actually opens the lightbox. Without this the data-fw-lightbox links do nothing. */
if ( $click === 'lightbox' && function_exists( 'fw_ext' ) ) {
	$sc_ext_lb = fw_ext( 'shortcodes' );
	if ( $sc_ext_lb ) {
		$lb_ver = $sc_ext_lb->manifest->get_version();
		wp_enqueue_style( 'fw-shortcode-gallery', $sc_ext_lb->get_declared_URI( '/shortcodes/gallery/static/css/styles.css' ), array(), $lb_ver );
		wp_enqueue_script( 'fw-shortcode-gallery', $sc_ext_lb->get_declared_URI( '/shortcodes/gallery/static/js/lightbox.js' ), array(), $lb_ver, true );
	}
}
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

$corner = max( 0, min( 60, (int) $dp( 'corner_radius', 6 ) ) );
/* Card Padding is a % of the card width (CSS padding % always resolves against the inline size, so it
 * stays uniform on all four sides) — matches the reference control and scales with Card Size. */
$pad    = max( 0, min( 40, (float) $dp( 'padding', 0 ) ) );
$ratio_key = (string) $dp( 'card_ratio', '1-1' );
$ratio_css = str_replace( '-', ' / ', preg_match( '/^\d+-\d+$/', $ratio_key ) ? $ratio_key : '1-1' );

$inner_class = trim( 'tdg__inner' . ( $boxp !== '' ? ' ' . $boxp : '' ) );

/* ---- one card renderer, shared by every design ---- */
$home_host   = (string) wp_parse_url( home_url(), PHP_URL_HOST );
$render_card = function ( $item ) use ( $inner_class, $cap_text, $captions, $click, $lb_group, $home_host ) {
	$caption = $cap_text( $item );
	$full    = ( ! empty( $item['full'] ) ) ? $item['full'] : ( ! empty( $item['url'] ) ? $item['url'] : '' );
	$src     = ( ! empty( $item['url'] ) ) ? $item['url'] : $full;
	/* alt falls back caption → title, so a LINKED card always carries an accessible name (an <a>
	 * whose only content is an image is named by that image's alt). */
	$alt     = ( ! empty( $item['alt'] ) ) ? $item['alt'] : ( $caption !== '' ? $caption : ( ! empty( $item['title'] ) ? $item['title'] : '' ) );
	$has_ov  = ( $captions === 'hover' && $caption !== '' );
	$has_bl  = ( $captions === 'below' && $caption !== '' );
	$link    = ( $click === 'link' && ! empty( $item['link'] ) ) ? (string) $item['link'] : '';
	$is_a    = false;
	$o  = '<div class="' . esc_attr( $inner_class ) . '">';
	if ( $click === 'lightbox' && $full !== '' ) {
		$is_a = true;
		$o   .= '<a class="tdg__link" href="' . esc_url( $full ) . '" data-fw-lightbox="' . esc_attr( $lb_group ) . '"' . ( $caption !== '' ? ' data-fw-caption="' . esc_attr( $caption ) . '"' : '' ) . '>';
	} elseif ( $link !== '' ) {
		/* Open Link — external hosts open a new tab automatically (the tag_list convention);
		 * the image's own "Open link in a new tab" checkbox forces it for internal links too. */
		$host = (string) wp_parse_url( $link, PHP_URL_HOST );
		$ext  = ( $host !== '' && strcasecmp( $host, $home_host ) !== 0 );
		$is_a = true;
		$o   .= '<a class="tdg__link" href="' . esc_url( $link ) . '"' . ( ( $ext || ! empty( $item['link_new_tab'] ) ) ? ' target="_blank" rel="noopener noreferrer"' : '' ) . '>';
	} else {
		$o .= '<span class="tdg__link">';
	}
	$o .= '<img class="tdg__img" src="' . esc_url( $src ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" decoding="async" />';
	if ( $has_ov ) { $o .= '<span class="tdg__overlay"><span class="tdg__overlay-text">' . esc_html( $caption ) . '</span></span>'; }
	$o .= $is_a ? '</a>' : '</span>';
	$o .= '</div>';
	if ( $has_bl ) { $o .= '<figcaption class="tdg__caption">' . esc_html( $caption ) . '</figcaption>'; }
	return $o;
};

/* ---- wrapper (animations / css_class / advanced) + shared vars ---- */
$atts['base_class']       = 'tdg';
$atts['unique_id_prefix'] = 'tdg-';
$attr = function_exists( 'sc_build_wrapper_attr' ) ? sc_build_wrapper_attr( $atts ) : array( 'class' => 'tdg' );
$attr['class'] = trim( ( isset( $attr['class'] ) ? $attr['class'] : '' ) . ' tdg--' . str_replace( '_', '-', $design ) );

/* Per-item entrance + hover for the FLAT bento design (Mosaic Marquee): its cards are independent
 * grid tiles, so the entrance can cascade across them and hover can land on each card. The true-3D
 * designs re-write every card's transform each frame, so per-item motion there would fight the
 * design — they stay whole-element. */
$tdg_flat       = ( 'mosaic_marquee' === $design );
$tdg_hover_item = array();
if ( $tdg_flat && ! empty( $attr['data-sc-anim'] ) && ( ! isset( $attr['data-sc-anim-seq'] ) || $attr['data-sc-anim-seq'] !== 'no' ) ) {
	// Cascade the entrance across .tdg__card. The cards are built by JS after load, so DEFER the
	// wrapper bind — gallery-3d.js calls upwScAnimBind() once the grid exists (no children yet at scan).
	$attr['data-sc-anim-children'] = '.tdg__card';
	$attr['data-sc-anim-defer']    = '1';
}
if ( $tdg_flat && function_exists( 'upw_hover_scope' ) && upw_hover_scope( $atts ) === 'each'
	&& function_exists( 'upw_hover_collection_item_attr' ) ) {
	$tdg_hover_item = upw_hover_collection_item_attr( $atts );
	if ( ! empty( $tdg_hover_item ) ) {
		// Move the hover OFF the wrapper and ONTO each card (each tile reacts on its own): strip the
		// wrapper's hover attrs + sc-hover classes; mosaic-marquee.php stamps the item attrs per card.
		foreach ( array_keys( $attr ) as $k ) {
			if ( is_string( $k ) && strpos( $k, 'data-hover' ) === 0 ) { unset( $attr[ $k ] ); }
		}
		if ( isset( $attr['class'] ) ) {
			$attr['class'] = trim( preg_replace( '/\s+/', ' ', preg_replace( '/\bsc-hover\S*/', '', (string) $attr['class'] ) ) );
		}
	}
}
/* "Use as Section Background" — fill the parent Section behind its content (shared runtime). In
 * that mode sc-bg-fill governs the size, so the fixed Stage Height is dropped. */
$as_bg = function_exists( 'sc_section_background_is_on' ) && sc_section_background_is_on( $sc_get( 'as_background', 'no' ) );
if ( $as_bg ) {
	$attr['class'] = trim( $attr['class'] . ' sc-bg-fill tdg--bg' );
	if ( function_exists( 'sc_section_background_use' ) ) { sc_section_background_use(); }
}

/* Pin while scrubbing (Motion: Scroll-scrub): stretch the wrapper by Scroll Length and let the CSS
 * stick the stage inside it — the visitor's scroll drives the scrub across the whole pinned stretch.
 * Never as a Section Background (the Section owns layout there). */
/* Scroll Choreography is intrinsically a scroll-pinned, multi-formation scene — force Scroll-scrub +
 * Pin and take its Scroll Length from the design's own option (it has no Motion picker of its own). */
if ( 'scroll_choreography' === $design && ! $as_bg ) {
	$motion_mode   = 'scroll';
	$motion_pin    = true;
	$motion_len    = max( 1, min( 6, (float) $dp( 'scroll_length', 3 ) ) );
	$motion_rounds = 1;
}
$is_pinned = ( $motion_mode === 'scroll' && $motion_pin && ! $as_bg );
if ( $is_pinned ) { $attr['class'] = trim( $attr['class'] . ' tdg--pinned' ); }

/* Rounds per scroll (scroll-scrub only): rotating designs read it as full turns across the pin;
 * non-rotating scroll-scrub designs (walls, tunnels, reels, strips…) read it as PASSES — the scrub
 * range they travel is multiplied by it, so a viewer scrolls the deck by twice, thrice, … over the
 * pin. Emitted centrally for every design so each layout's onScroll can pick it up. Default 1. */
if ( $motion_mode === 'scroll' ) { $attr['data-tdg-rounds'] = esc_attr( (string) $motion_rounds ); }

/* Stage Overflow = Spill: let fanned / oversized cards show in full beyond the Stage Height box
 * instead of being clipped by it (opt-in per design; default clips). Only in the normal flow — the
 * pinned sticky stage and the Section-Background fill own their own clipping. */
if ( ! $as_bg && ! $is_pinned && 'spill' === (string) $dp( 'overflow', 'clip' ) ) {
	$attr['class'] = trim( $attr['class'] . ' tdg--spill' );
}

/* Built-in sequential Entrance — reveal cards one by one as the gallery scrolls in (all designs). It
 * poses .tdg__inner (never touched by the layout), so it composes with the 3D motion instead of fighting
 * it. Skipped in Section-Background mode (that scene auto-animates and is decorative). */
$entrance = (string) $sc_get( 'entrance', 'none' );
if ( 'none' !== $entrance && ! $as_bg ) {
	$attr['class'] = trim( $attr['class'] . ' tdg--entrance' );
	$attr['data-tdg-entrance']          = esc_attr( $entrance );
	$attr['data-tdg-entrance-stagger']  = esc_attr( (string) max( 0, min( 90, (int) $sc_get( 'entrance_stagger', 60 ) ) ) );
	$attr['data-tdg-entrance-distance'] = esc_attr( (string) max( 0, min( 120, (int) $sc_get( 'entrance_distance', 40 ) ) ) );
	if ( 'yes' === $sc_get( 'entrance_exit', 'no' ) ) { $attr['data-tdg-exit'] = 'yes'; }
}

$attr['style'] = ( isset( $attr['style'] ) ? rtrim( $attr['style'], '; ' ) . ';' : '' )
	. ( $as_bg ? '' : ( $is_pinned
		? '--tdg-stage-h:' . esc_attr( $height ) . ';height:calc(' . esc_attr( $height ) . ' + ' . esc_attr( $motion_len * 100 ) . 'vh);'
		: 'height:' . esc_attr( $height ) . ';' ) )
	. '--tdg-radius:' . $corner . 'px;'
	. '--tdg-pad:' . $pad . '%;'
	. '--tdg-ratio:' . esc_attr( $ratio_css ) . ';'
	. ( $shadow !== '' ? '--tdg-shadow:' . esc_attr( $shadow ) . ';' : '' )
	. ( $bg !== '' ? '--tdg-bg:' . esc_attr( $bg ) . ';' : '' );

if ( empty( $items ) ) {
	echo '<div class="tdg tdg--empty"><p class="tdg__empty">' . esc_html__( 'Add images to the Motion Gallery.', 'fw' ) . '</p></div>';
	return;
}

$design_file = dirname( __FILE__ ) . '/designs/' . str_replace( '_', '-', $design ) . '.php';
if ( ! file_exists( $design_file ) ) { $design_file = dirname( __FILE__ ) . '/designs/carousel-ring.php'; }
include $design_file;
