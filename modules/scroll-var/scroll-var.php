<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Scroll Var module.
 *
 * Publishes an element's in-view scroll progress (0→1 as it crosses the viewport) to a CSS custom
 * property the builder names, so ANY custom CSS can be driven by scroll — StringTune's
 * "attribute-driven progress" idea, generalized. Per-element (attaches from the Animations tab).
 * The runtime uses an IntersectionObserver to activate + a passive, rAF-throttled scroll check that
 * reads getBoundingClientRect() (so it rides Lenis smooth-scroll automatically) and sets the named
 * property to a clamped 0..1 value. No GSAP. Assets load only on pages that use it.
 *
 * Saved value shape (group `scroll_var`):
 *   [ 'enable' => 'yes'|'no', 'var_name' => '--progress', 'axis' => 'y'|'x', 'range' => 'cover'|'enter'|'center' ]
 */

if ( ! function_exists( 'upw_scroll_var_enabled' ) ) :
	function upw_scroll_var_enabled() {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return true;
		}
		$v = fw_get_db_settings_option( 'animation_scroll_var', array() );
		$e = ( is_array( $v ) && isset( $v['enable'] ) ) ? $v['enable'] : 'yes';
		return $e !== 'no' && $e !== false;
	}
endif;

/** Sanitize a user string into a valid CSS custom-property name, forcing a leading `--`. */
if ( ! function_exists( 'upw_scroll_var_sanitize_name' ) ) :
	function upw_scroll_var_sanitize_name( $name ) {
		$name = strtolower( trim( (string) $name ) );
		$name = preg_replace( '/^-+/', '', $name );           // drop leading dashes; we re-add exactly two
		$name = preg_replace( '/[^a-z0-9_-]/', '', $name );   // CSS ident-safe chars only
		if ( $name === '' ) {
			$name = 'progress';
		}
		return '--' . $name;
	}
endif;

/* 1) The per-element "Scroll → CSS Variable" control, appended to the Animations tab. */
add_filter( 'sc_animation_fields', function ( $fields ) {
	if ( ! is_array( $fields ) ) {
		return $fields;
	}

	$fields['scroll_var'] = array(
		'type'      => 'group',
		'label'     => false,
		'desc'      => false,
		'anim_meta' => array(
			'category' => __( 'Scroll', 'fw' ),
			'label'    => __( 'Scroll → CSS Variable', 'fw' ),
		),
		'options'   => array(
			'enable' => array(
				'type'         => 'switch',
				'label'        => __( 'Scroll → CSS Variable', 'fw' ),
				'desc'         => __( 'Publish this element\'s in-view scroll progress (0→1 as it crosses the viewport) to a CSS custom property you can drive any custom CSS with.', 'fw' ),
				'help'         => __( 'Scroll Var (Animation Engine): sets a CSS custom property (e.g. --progress) on this element to a clamped 0..1 value as it scrolls through the viewport. Use it in the element\'s Custom CSS — e.g. transform: scale(calc(1 + var(--progress))). Uses one passive, rAF-throttled scroll check + getBoundingClientRect() (rides smooth-scroll automatically), no library. Loads only on pages that use it.', 'fw' ),
				'value'        => 'no',
				'left-choice'  => array( 'value' => 'no',  'label' => __( 'Off', 'fw' ) ),
				'right-choice' => array( 'value' => 'yes', 'label' => __( 'On', 'fw' ) ),
			),
			'var_name' => array(
				'type'  => 'text',
				'label' => __( 'Variable name', 'fw' ),
				'desc'  => __( 'The CSS custom property to write, e.g. --progress. A leading -- is added automatically.', 'fw' ),
				'value' => '--progress',
			),
			'axis' => array(
				'type'    => 'select',
				'label'   => __( 'Axis', 'fw' ),
				'value'   => 'y',
				'choices' => array(
					'y' => __( 'Vertical (Y)', 'fw' ),
					'x' => __( 'Horizontal (X)', 'fw' ),
				),
			),
			'range' => array(
				'type'    => 'select',
				'label'   => __( 'Progress range', 'fw' ),
				'value'   => 'cover',
				'desc'    => __( 'When 0 and 1 hit as the element crosses the viewport.', 'fw' ),
				'choices' => array(
					'cover'  => __( 'Cover — enters (bottom) → exits (top)', 'fw' ),
					'enter'  => __( 'Enter — until fully in view', 'fw' ),
					'center' => __( 'Center — element centre crosses viewport centre', 'fw' ),
				),
			),
		),
	);

	return $fields;
} );

/* 2) Emit the scroll-var settings onto the element wrapper (+ flag the runtime for enqueue). */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	if ( ! upw_scroll_var_enabled() ) {
		return $attr;
	}
	$f = ( isset( $atts['scroll_var'] ) && is_array( $atts['scroll_var'] ) ) ? $atts['scroll_var'] : array();
	if ( ! isset( $f['enable'] ) || $f['enable'] !== 'yes' ) {
		return $attr;
	}

	$name  = upw_scroll_var_sanitize_name( isset( $f['var_name'] ) ? $f['var_name'] : '--progress' );
	$axis  = ( isset( $f['axis'] ) && $f['axis'] === 'x' ) ? 'x' : 'y';
	$range = ( isset( $f['range'] ) && in_array( $f['range'], array( 'cover', 'enter', 'center' ), true ) ) ? $f['range'] : 'cover';

	$attr['data-upw-svar']       = '1';
	$attr['data-upw-svar-name']  = esc_attr( $name );
	$attr['data-upw-svar-axis']  = esc_attr( $axis );
	$attr['data-upw-svar-range'] = esc_attr( $range );

	$GLOBALS['upw_scroll_var_used'] = true;
	return $attr;
}, 22, 2 );

/* 2b) Force a wrapper when an element's ONLY non-default setting is a scroll var. */
add_filter( 'sc_needs_wrapper', function ( $needs, $atts ) {
	if ( $needs || ! upw_scroll_var_enabled() ) {
		return $needs;
	}
	$f = ( isset( $atts['scroll_var'] ) && is_array( $atts['scroll_var'] ) ) ? $atts['scroll_var'] : array();
	return ( isset( $f['enable'] ) && $f['enable'] === 'yes' );
}, 10, 2 );

/* 3) On-demand asset — the runtime ships only on pages that actually use a scroll var. */
add_action( 'wp_footer', function () {
	if ( empty( $GLOBALS['upw_scroll_var_used'] ) ) {
		return;
	}
	$ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( ! $ext ) {
		return;
	}
	wp_enqueue_script(
		'upw-scroll-var',
		$ext->get_declared_URI( '/modules/scroll-var/static/js/upw-scroll-var.js' ),
		array(),
		$ext->manifest->get_version(),
		true
	);
}, 20 );
