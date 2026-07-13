<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Scroll Color Shift module (Section-level).
 *
 * Give each Section a target background (and optional text) colour; as the visitor scrolls, the
 * PAGE background smoothly morphs from one section's colour to the next — the agency-site
 * "scroll colour shift". Section-only (injected into the Section's Animations tab, like Scroll Loop
 * / Sticky Stack). One passive, rAF-throttled scroll check picks the section crossing the viewport
 * middle and transitions `body` colours. No library. Loads only on pages that use it.
 *
 * Saved value shape (multi-picker, picker id `mode`):
 *   [ 'mode' => 'off'|'shift', 'shift' => [ bg_color, text_color, duration ] ]
 *
 * Best on full-bleed, transparent sections (so the morphing page colour shows through).
 */

/** Resolve a compact-color value (array or legacy string) to a CSS color. */
if ( ! function_exists( 'upw_cs_resolve_color' ) ) :
	function upw_cs_resolve_color( $val ) {
		if ( is_array( $val ) ) {
			if ( ! empty( $val['predefined'] ) ) {
				$slug = preg_replace( '/^(bg|text)-/', '', (string) $val['predefined'] );
				return 'var(--color-' . preg_replace( '/[^a-z0-9\-]/', '', strtolower( $slug ) ) . ')';
			}
			return isset( $val['custom'] ) ? (string) $val['custom'] : '';
		}
		return (string) $val;
	}
endif;

if ( ! function_exists( 'upw_color_shift_enabled' ) ) :
	/** Global master switch — the single choke point (defaults to enabled, like every other module). */
	function upw_color_shift_enabled() {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return true;
		}
		$v = fw_get_db_settings_option( 'animation_color_shift', array() );
		$e = ( is_array( $v ) && isset( $v['enable'] ) ) ? $v['enable'] : 'yes';
		return $e !== 'no' && $e !== false;
	}
endif;

if ( ! function_exists( 'upw_color_shift_flag' ) ) :
	function upw_color_shift_flag( $set = false ) {
		static $used = false;
		if ( $set ) {
			$used = true;
		}
		return $used;
	}
endif;

/** The Scroll Color Shift control (a compact popover multi-picker). */
if ( ! function_exists( 'upw_get_color_shift_fields' ) ) :
	function upw_get_color_shift_fields() {
		$bg = function_exists( 'sc_color_field_compact' )
			? sc_color_field_compact( array( 'label' => __( 'Page colour', 'fw' ), 'kind' => 'bg', 'desc' => __( 'The background the page morphs to while this section is in view.', 'fw' ) ) )
			: array( 'type' => 'color-picker', 'label' => __( 'Page colour', 'fw' ), 'value' => '#0b1220' );
		$tx = function_exists( 'sc_color_field_compact' )
			? sc_color_field_compact( array( 'label' => __( 'Text colour (optional)', 'fw' ), 'kind' => 'text', 'desc' => __( 'Optional — also shift the body text colour to stay readable.', 'fw' ) ) )
			: array( 'type' => 'color-picker', 'label' => __( 'Text colour (optional)', 'fw' ), 'value' => '' );

		$ext  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
		$base = $ext ? $ext->get_declared_URI( '/modules/scroll-color-shift/static/img' ) : '';
		$tile = function ( $file, $label ) use ( $base ) {
			return array(
				'small' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 53 ),
				'large' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 132 ),
				'label' => $label,
			);
		};

		return array(
			'scroll_color_shift' => array(
				'type'         => 'multi-picker',
				'label'        => __( 'Scroll Color Shift', 'fw' ),
				'desc'         => __( 'Morph the page background to this section\'s colour as it scrolls into view. Give several sections their own colour for a smooth scroll-driven palette.', 'fw' ),
				'help'         => __( 'Scroll Color Shift (Animation Engine): as each marked Section crosses the middle of the screen, the page background (and optionally text) transitions to its colour. Works best on full-bleed, transparent sections so the page colour shows through. Pure CSS transition + one passive scroll check; honours "reduce motion" and loads only on pages that use it.', 'fw' ) . ( function_exists( 'upw_perf_note' ) ? ' ' . upw_perf_note() : '' ),
				'popover'      => true,
				'show_borders' => false,
				'value'        => array( 'mode' => 'off' ),
				'placeholder'  => __( 'Off', 'fw' ),
				'anim_meta'    => array( 'category' => __( 'Scroll', 'fw' ) ),
				'picker'       => array(
					'mode' => array(
						'type'    => 'image-picker',
						'label'   => false,
						'desc'    => __( 'Hover a tile to preview it larger.', 'fw' ),
						'value'   => 'off',
						'show_label' => true,
						'choices' => array(
							'shift' => $tile( 'shift', __( 'Color Shift', 'fw' ) ),
						),
					),
				),
				'choices' => array(
					'shift' => array(
						'group_color_shift' => array(
							'type'    => 'group',
							'options' => array(
								'bg_color'   => $bg,
								'text_color' => $tx,
								'duration'   => array( 'type' => 'slider', 'label' => __( 'Transition (s)', 'fw' ), 'value' => 0.6, 'properties' => array( 'min' => 0.2, 'max' => 2, 'step' => 0.1 ) ),
							),
						),
					),
				),
			),
		);
	}
endif;

/* Inject into the SECTION's Animations tab only (inside the animation-stack organizer). */
add_filter( 'fw_shortcode_get_options', function ( $options, $tag = '' ) {
	if ( $tag !== 'section' || ! is_array( $options ) || ! function_exists( 'upw_get_color_shift_fields' ) ) {
		return $options;
	}
	if ( ! isset( $options['tab_animation']['options'] ) || ! is_array( $options['tab_animation']['options'] ) ) {
		return $options;
	}
	$tab =& $options['tab_animation']['options'];
	if ( isset( $tab['animation_stack']['options'] ) && is_array( $tab['animation_stack']['options'] ) ) {
		$tab['animation_stack']['options'] = array_merge( $tab['animation_stack']['options'], upw_get_color_shift_fields() );
	} else {
		$tab = array_merge( $tab, upw_get_color_shift_fields() );
	}
	unset( $tab );
	return $options;
}, 10, 2 );

/* Stamp the color-shift data attributes onto the section wrapper. */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	if ( ! upw_color_shift_enabled() ) {
		return $attr;
	}
	$c    = ( isset( $atts['scroll_color_shift'] ) && is_array( $atts['scroll_color_shift'] ) ) ? $atts['scroll_color_shift'] : array();
	$mode = isset( $c['mode'] ) ? (string) $c['mode'] : 'off';
	if ( $mode !== 'shift' ) {
		return $attr;
	}
	$o  = ( isset( $c['shift'] ) && is_array( $c['shift'] ) ) ? $c['shift'] : array();
	$bg = upw_cs_resolve_color( isset( $o['bg_color'] ) ? $o['bg_color'] : '' );
	if ( $bg === '' ) {
		return $attr; // a target colour is required for the effect to mean anything
	}
	$tx  = upw_cs_resolve_color( isset( $o['text_color'] ) ? $o['text_color'] : '' );
	$dur = isset( $o['duration'] ) ? (float) $o['duration'] : 0.6;

	$cls           = isset( $attr['class'] ) ? trim( (string) $attr['class'] ) : '';
	$attr['class'] = esc_attr( trim( $cls . ' sc-colorshift' ) );
	$attr['data-cs-bg']  = esc_attr( $bg );
	if ( $tx !== '' ) {
		$attr['data-cs-text'] = esc_attr( $tx );
	}
	$attr['data-cs-dur'] = esc_attr( rtrim( rtrim( number_format( max( 0.1, $dur ), 2, '.', '' ), '0' ), '.' ) );

	upw_color_shift_flag( true );
	return $attr;
}, 23, 2 );

/* Force a wrapper when a section's ONLY non-default setting is the color shift. */
add_filter( 'sc_needs_wrapper', function ( $needs, $atts ) {
	if ( $needs ) {
		return $needs;
	}
	$c = ( isset( $atts['scroll_color_shift'] ) && is_array( $atts['scroll_color_shift'] ) ) ? $atts['scroll_color_shift'] : array();
	return isset( $c['mode'] ) && (string) $c['mode'] === 'shift';
}, 10, 2 );

/* Enqueue the tiny runtime + CSS only on pages that used a color shift. */
add_action( 'wp_footer', function () {
	if ( ! upw_color_shift_flag() ) {
		return;
	}
	$ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( ! $ext ) {
		return;
	}
	$base = $ext->get_declared_URI( '/modules/scroll-color-shift' );
	$ver  = $ext->manifest->get_version();
	$dir  = __DIR__;
	$jsv  = file_exists( "$dir/static/js/scroll-color-shift.js" )  ? $ver . '.' . filemtime( "$dir/static/js/scroll-color-shift.js" )  : $ver;
	$cssv = file_exists( "$dir/static/css/scroll-color-shift.css" ) ? $ver . '.' . filemtime( "$dir/static/css/scroll-color-shift.css" ) : $ver;

	wp_enqueue_style( 'upw-color-shift', $base . '/static/css/scroll-color-shift.css', array(), $cssv );
	wp_enqueue_script( 'upw-color-shift', $base . '/static/js/scroll-color-shift.js', array(), $jsv, true );

	$cfg = array(
		'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
	);
	wp_add_inline_script( 'upw-color-shift', 'window.upwColorShiftCfg=' . wp_json_encode( $cfg ) . ';', 'before' );
}, 5 );
