<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Scroll Text Highlight module.
 *
 * Reveals an element's words one-by-one as it scrolls through the viewport (the "scrollytelling"
 * read): each word goes from muted to full as the reader scrolls past it. Four styles — fill
 * (colour), fade (opacity), blur (blur→sharp) and marker (highlighter sweep). Per-element (attaches
 * from the Animations tab). The runtime splits the text into word/char spans and scrubs an .is-on
 * class from a passive, rAF-throttled scroll check — no library. Assets load only on pages that use
 * it. Global on/off: Theme Settings → Animations → Effects.
 *
 * Saved value shape (multi-picker, picker id `mode`):
 *   [ 'mode' => 'off'|'<style>', '<style>' => [ split, active_color, duration, once ] ]
 */

if ( ! function_exists( 'upw_scroll_text_highlight_enabled' ) ) :
	function upw_scroll_text_highlight_enabled() {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return true;
		}
		$v = fw_get_db_settings_option( 'animation_scroll_text_highlight', array() );
		$e = ( is_array( $v ) && isset( $v['enable'] ) ) ? $v['enable'] : 'yes';
		return $e !== 'no' && $e !== false;
	}
endif;

if ( ! function_exists( 'upw_sth_styles' ) ) :
	function upw_sth_styles() {
		return array(
			'fill'   => __( 'Fill (colour)', 'fw' ),
			'fade'   => __( 'Fade (opacity)', 'fw' ),
			'blur'   => __( 'Blur to sharp', 'fw' ),
			'marker' => __( 'Marker sweep', 'fw' ),
		);
	}
endif;

/** Resolve a compact-color value (array or legacy string) to a CSS color. */
if ( ! function_exists( 'upw_sth_resolve_color' ) ) :
	function upw_sth_resolve_color( $val ) {
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

/* 1) The per-element "Scroll Text Highlight" control, appended to the Animations tab. */
add_filter( 'sc_animation_fields', function ( $fields ) {
	if ( ! is_array( $fields ) ) {
		return $fields;
	}

	$color_field = function_exists( 'sc_color_field_compact' )
		? sc_color_field_compact( array( 'label' => __( 'Highlight colour', 'fw' ), 'kind' => 'text' ) )
		: array( 'type' => 'color-picker', 'label' => __( 'Highlight colour', 'fw' ), 'value' => '' );

	$opts = array(
		'split' => array(
			'type'    => 'select',
			'label'   => __( 'Reveal by', 'fw' ),
			'value'   => 'word',
			'choices' => array( 'word' => __( 'Word', 'fw' ), 'char' => __( 'Character', 'fw' ) ),
			'desc'    => __( 'Light up one word (or one letter) at a time as you scroll.', 'fw' ),
		),
		'active_color' => $color_field,
		'duration' => array( 'type' => 'slider', 'label' => __( 'Per-word ease (s)', 'fw' ), 'desc' => __( 'How softly each word transitions on.', 'fw' ), 'value' => 0.5, 'properties' => array( 'min' => 0, 'max' => 1.5, 'step' => 0.05 ) ),
		'once' => array(
			'type'         => 'switch',
			'label'        => __( 'Keep highlighted', 'fw' ),
			'desc'         => __( 'Stay lit once revealed (off = re-dims when scrolled back up).', 'fw' ),
			'value'        => 'yes',
			'left-choice'  => array( 'value' => 'no',  'label' => __( 'Off', 'fw' ) ),
			'right-choice' => array( 'value' => 'yes', 'label' => __( 'On', 'fw' ) ),
		),
	);

	$ext  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	$base = $ext ? $ext->get_declared_URI( '/modules/scroll-text-highlight/static/img' ) : '';
	$tile = function ( $file, $label ) use ( $base ) {
		return array(
			'small' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 66 ),
			'large' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 132 ),
			'label' => $label,
		);
	};

	$choices = array( 'off' => $tile( 'off', __( 'Off', 'fw' ) ) );
	$reveal  = array( 'off' => array() );
	foreach ( upw_sth_styles() as $k => $lbl ) {
		$choices[ $k ] = $tile( $k, $lbl );
		$reveal[ $k ]  = array( 'group_sth_' . $k => array( 'type' => 'group', 'options' => $opts ) );
	}

	// Alphabetize picker tiles by label (None/Off first) for easier scanning.
	uksort( $choices, function ( $a, $b ) use ( $choices ) {
		$rank = function ( $k ) { if ( $k === 'none' || $k === 'off' ) { return 0; } return 1; };
		$ra = $rank( $a ); $rb = $rank( $b );
		if ( $ra !== $rb ) { return $ra - $rb; }
		$la = isset( $choices[ $a ]['label'] ) ? $choices[ $a ]['label'] : $a;
		$lb = isset( $choices[ $b ]['label'] ) ? $choices[ $b ]['label'] : $b;
		return strcasecmp( (string) $la, (string) $lb );
	} );

	$fields['scroll_text_highlight'] = array(
		'type'         => 'multi-picker',
		'popover'      => true,
		'label'        => __( 'Scroll Text Highlight', 'fw' ),
		'desc'         => __( 'Light up this text word-by-word as it scrolls through the viewport — fill, fade, blur or marker sweep.', 'fw' ),
		'help'         => __( 'Scroll Text Highlight (Animation Engine): splits the text into word (or character) spans and scrubs each from muted to full as the reader scrolls past — the "scrollytelling" read. Pure CSS transitions + one passive, rAF-throttled scroll check, no library. Honours "reduce motion" (shows everything lit) and loads only on pages that use it.', 'fw' ) . ( function_exists( 'upw_perf_note' ) ? ' ' . upw_perf_note() : '' ),
		'show_borders' => false,
		'value'        => array( 'mode' => 'off' ),
		'anim_meta'    => array( 'category' => __( 'Scroll', 'fw' ) ),
		'picker'       => array(
			'mode' => array(
				'type'    => 'image-picker',
				'label'   => false,
				'desc'    => __( 'Hover a tile to preview it larger.', 'fw' ),
				'value'   => 'off',
				'choices' => $choices,
			),
		),
		'choices' => $reveal,
	);

	return $fields;
} );

/* 2) Emit the highlight settings onto the element wrapper. */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	if ( ! upw_scroll_text_highlight_enabled() ) {
		return $attr;
	}
	$f    = ( isset( $atts['scroll_text_highlight'] ) && is_array( $atts['scroll_text_highlight'] ) ) ? $atts['scroll_text_highlight'] : array();
	$mode = isset( $f['mode'] ) ? (string) $f['mode'] : 'off';
	$styles = upw_sth_styles();
	if ( ! isset( $styles[ $mode ] ) ) {
		return $attr;
	}
	$o = ( isset( $f[ $mode ] ) && is_array( $f[ $mode ] ) ) ? $f[ $mode ] : array();

	$cls           = isset( $attr['class'] ) ? trim( (string) $attr['class'] ) : '';
	$attr['class'] = esc_attr( trim( $cls . ' sc-sth sc-sth--' . $mode ) );

	$dur = isset( $o['duration'] ) ? (float) $o['duration'] : 0.5;
	$styles_css = array( '--sth-dur: ' . rtrim( rtrim( number_format( $dur, 2, '.', '' ), '0' ), '.' ) . 's' );
	$color = upw_sth_resolve_color( isset( $o['active_color'] ) ? $o['active_color'] : '' );
	if ( $color !== '' ) {
		$styles_css[] = '--sth-active: ' . $color;
	}
	$existing_style = isset( $attr['style'] ) ? trim( (string) $attr['style'] ) : '';
	$css            = implode( '; ', $styles_css ) . ';';
	$attr['style']  = esc_attr( $existing_style === '' ? $css : rtrim( $existing_style, '; ' ) . '; ' . $css );

	$split = ( isset( $o['split'] ) && $o['split'] === 'char' ) ? 'char' : 'word';
	$attr['data-sth-split'] = esc_attr( $split );
	$attr['data-sth-once']  = ( isset( $o['once'] ) && $o['once'] === 'no' ) ? '0' : '1';

	if ( function_exists( 'upw_anim_use_asset' ) ) {
		upw_anim_use_asset( 'scroll-text-highlight', $mode );
	}
	return $attr;
}, 22, 2 );

/* 2b) Force a wrapper when an element's ONLY non-default setting is a scroll text highlight. */
add_filter( 'sc_needs_wrapper', function ( $needs, $atts ) {
	if ( $needs || ! upw_scroll_text_highlight_enabled() ) {
		return $needs;
	}
	$f    = ( isset( $atts['scroll_text_highlight'] ) && is_array( $atts['scroll_text_highlight'] ) ) ? $atts['scroll_text_highlight'] : array();
	$mode = isset( $f['mode'] ) ? (string) $f['mode'] : 'off';
	return array_key_exists( $mode, upw_sth_styles() );
}, 10, 2 );

/* 3) On-demand assets — shared base + one runtime + ONLY the used styles' CSS partials. */
if ( function_exists( 'upw_anim_register_assets' ) ) {
	$upw_sth_ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( $upw_sth_ext ) {
		upw_anim_register_assets( 'scroll-text-highlight', array(
			'path'      => __DIR__,
			'uri'       => $upw_sth_ext->get_declared_URI( '/modules/scroll-text-highlight' ),
			'ver'       => $upw_sth_ext->manifest->get_version(),
			'css_dir'   => 'static/css/effects',
			'base_css'  => 'static/css/base.css',
			'base_js'   => 'static/js/scroll-text-highlight.js',
			'js_styles' => array( 'fill', 'fade', 'blur', 'marker' ),
			'js_cfg'    => function () {
				$cfg = array(
					'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
				);
				return 'window.upwSthCfg=' . wp_json_encode( $cfg ) . ';';
			},
		) );
	}
	unset( $upw_sth_ext );
}
