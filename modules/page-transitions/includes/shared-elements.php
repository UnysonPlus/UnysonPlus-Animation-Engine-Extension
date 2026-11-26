<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Page Transitions: shared elements (roadmap 6e).
 *
 * An element flows from its place on one page into its place on the next (a product photo into the product page, a
 * card's title into the post's heading), with the browser's cross-document View Transitions. Two ways in:
 *
 *  1. The per-element "Shared Element" card (Animations tab): a name. The same name on two pages = the element that
 *     flows between them. `{post}` in the name becomes the current post's ID.
 *  2. Theme Settings → Animations → Page Transitions → "Featured images and titles flow into their post": on a
 *     list, the clicked card's image and title are named as you leave; on the post, its featured image and title
 *     (and the reverse when you go back). Done by static/js/shared-elements.js, in <head>.
 *
 * A page opts in (`@view-transition { navigation: auto }`, printed in <head>) when the setting is on, or when its
 * builder content has a Shared Element card. Browsers without cross-document View Transitions navigate normally;
 * in those that have them, the cover overlay (if Page Transitions are on) steps aside so the shared elements show.
 */

if ( ! function_exists( 'upw_pt_shared_posts' ) ) :
	/** "Featured images and titles flow into their post" (independent of the overlay's own Enable switch). */
	function upw_pt_shared_posts() {
		return function_exists( 'upw_pt_setting' ) && upw_pt_setting( 'shared_posts', 'no' ) === 'yes';
	}
endif;

if ( ! function_exists( 'upw_pt_shared_name' ) ) :
	/** A Shared Element name as a CSS-safe view-transition-name (`{post}` → the current post's ID), or ''. */
	function upw_pt_shared_name( $raw ) {
		$raw = trim( (string) $raw );
		if ( $raw === '' ) {
			return '';
		}
		$raw = str_replace( '{post}', (string) (int) get_the_ID(), $raw );
		$s   = strtolower( preg_replace( '/[^A-Za-z0-9_-]+/', '-', $raw ) );
		$s   = trim( $s, '-' );
		return $s === '' ? '' : 'upw-' . substr( $s, 0, 60 );
	}
endif;

if ( ! function_exists( 'upw_pt_page_has_shared' ) ) :
	/** Does the page being shown carry a Shared Element card (its builder content), or does a filter say so? */
	function upw_pt_page_has_shared() {
		$has = false;
		$id  = is_singular() ? get_queried_object_id() : 0;
		if ( $id ) {
			$json = get_post_meta( $id, 'fw:opt:ext:pb:page-builder:json', true );
			$has  = is_string( $json ) && strpos( $json, '"upw_shared"' ) !== false && preg_match( '/"upw_shared":\{"mode":"on"/', $json );
		}
		return (bool) apply_filters( 'upw_pt_page_has_shared', $has, $id );
	}
endif;

/* 1) The per-element "Shared Element" card. */
add_filter( 'sc_animation_fields', function ( $fields ) {
	if ( ! is_array( $fields ) ) {
		return $fields;
	}
	$ext  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	$base = $ext ? $ext->get_declared_URI( '/modules/page-transitions/static/img' ) : '';
	$fields['upw_shared'] = array(
		'type'         => 'multi-picker',
		'popover'      => true,
		'label'        => __( 'Shared Element', 'fw' ),
		'desc'         => __( 'Give this element a name; an element with the same name on the next page is where it flows to, as the page changes.', 'fw' ),
		'help'         => __( 'Shared Element (Animation Engine → Page Transitions): uses the browser’s View Transitions between pages. Give the same name to the element on both pages (a logo, a product photo, a heading); when a visitor goes from one page to the other, it moves and resizes from its old place into its new one while the rest of the page cross-fades. {post} in the name becomes the post’s ID, for post templates. Each name must be used once per page. Browsers without View Transitions simply change page.', 'fw' ),
		'show_borders' => false,
		'value'        => array( 'mode' => 'off' ),
		'placeholder'  => __( 'Off', 'fw' ),
		'anim_meta'    => array( 'category' => __( 'Interaction', 'fw' ) ),
		'picker'       => array(
			'mode' => array(
				'type'    => 'image-picker',
				'label'   => false,
				'value'   => 'off',
				'choices' => array(
					'on' => array(
						'small' => array( 'src' => $base . '/shared.svg', 'height' => 66 ),
						'large' => array( 'src' => $base . '/shared.svg', 'height' => 132 ),
						'label' => __( 'Shared Element', 'fw' ),
					),
				),
			),
		),
		'choices'      => array(
			'off' => array(),
			'on'  => array(
				'name' => array(
					'type'  => 'text',
					'label' => __( 'Name', 'fw' ),
					'desc'  => __( 'The same name on the element it flows to, on the other page (letters, numbers and dashes). {post} = the post’s ID.', 'fw' ),
					'value' => '',
					'attr'  => array( 'placeholder' => 'product-photo' ),
				),
			),
		),
	);
	return $fields;
} );

/* 2) The name on the element's wrapper. */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	$v = isset( $atts['upw_shared'] ) && is_array( $atts['upw_shared'] ) ? $atts['upw_shared'] : array();
	if ( empty( $v['mode'] ) || $v['mode'] !== 'on' ) {
		return $attr;
	}
	$name = upw_pt_shared_name( isset( $v['on']['name'] ) ? $v['on']['name'] : '' );
	if ( $name === '' ) {
		return $attr;
	}
	$style                   = isset( $attr['style'] ) ? rtrim( (string) $attr['style'], '; ' ) : '';
	$attr['style']           = ltrim( $style . '; view-transition-name: ' . $name . ';', '; ' );
	$attr['data-upw-vt']     = $name;
	return $attr;
}, 22, 2 );

add_filter( 'sc_needs_wrapper', function ( $needs, $atts ) {
	if ( $needs ) {
		return $needs;
	}
	$v = isset( $atts['upw_shared'] ) && is_array( $atts['upw_shared'] ) ? $atts['upw_shared'] : array();
	return ! empty( $v['mode'] ) && $v['mode'] === 'on' && ! empty( $v['on']['name'] );
}, 10, 2 );

/* 3) The page's opt-in and the runtime, in <head> (the browser needs both before the new page first renders). */
add_action( 'wp_head', function () {
	if ( is_admin() || ! ( upw_pt_shared_posts() || upw_pt_page_has_shared() ) ) {
		return;
	}
	$dur    = max( 0.2, min( 1.5, (float) upw_pt_setting( 'shared_duration', 0.45 ) ) );
	$ease   = function_exists( 'upw_interactions_ease_css' ) ? upw_interactions_ease_css( upw_pt_setting( 'shared_ease', 'soft_land' ) ) : 'ease';
	$reduce = ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' );
	$css    = '@view-transition{navigation:auto}'
		. '::view-transition-group(*),::view-transition-old(*),::view-transition-new(*){animation-duration:' . $dur . 's}'
		. '::view-transition-group(*){animation-timing-function:' . esc_html( $ease ) . '}'
		. '.upw-vt .upw-pt{display:none!important}'
		. ( $reduce ? '@media (prefers-reduced-motion:reduce){@view-transition{navigation:none}}' : '' );
	echo '<style id="upw-shared-elements">' . $css . '</style>' . "\n"; // phpcs:ignore -- built from clamped / escaped values

	$ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( ! $ext ) {
		return;
	}
	$file = UPW_PAGE_TRANSITIONS_DIR . '/static/js/shared-elements.js';
	$cfg  = array(
		'on'     => true,
		'posts'  => upw_pt_shared_posts(),
		'single' => is_singular() && ! is_front_page(),
	);
	echo '<script>window.upwShareCfg=' . wp_json_encode( $cfg ) . ';</script>' . "\n"; // phpcs:ignore
	$src = fw_min_uri( $ext->get_declared_URI( '/modules/page-transitions/static/js/shared-elements.js' ) );
	$ver = $ext->manifest->get_version() . ( file_exists( $file ) ? '.' . filemtime( $file ) : '' );
	echo '<script src="' . esc_url( add_query_arg( 'ver', $ver, $src ) ) . '"></script>' . "\n"; // phpcs:ignore -- tiny, must run before first render
}, 3 );
