<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Layout Animation module (roadmap 6d).
 *
 * When the layout inside an element changes (an accordion opens, a filter hides cards, a Set step shows a panel,
 * a state adds a class that changes size), its items GLIDE from where they were to where they now are, instead
 * of jumping. Optionally the element's own height glides too (an accordion's panel).
 *
 * Runtime (static/js/upw-layout.js): it remembers each item's layout position; when a MutationObserver or a
 * ResizeObserver reports a change (both run after layout and before paint), it animates each moved item from its
 * old place to its new one with an ADDITIVE transform (so the item's own transform and other effects stay), and
 * new items fade in. Positions are read from the offset chain, which transforms do not affect.
 *
 * Saved value (multi-picker, picker id `mode`):
 *   [ 'mode' => 'off'|'on', 'on' => [ what items|height|both, items (selector), duration, ease, enter yes|no ] ]
 */

/* 1) The per-element "Layout Animation" card. */
add_filter( 'sc_animation_fields', function ( $fields ) {
	if ( ! is_array( $fields ) ) {
		return $fields;
	}
	$ext  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	$base = $ext ? $ext->get_declared_URI( '/modules/layout-animation/static/img' ) : '';
	$ease = function_exists( 'upw_interactions_ease_field' )
		? upw_interactions_ease_field( 'soft_land' )
		: array( 'type' => 'text', 'label' => __( 'Easing', 'fw' ), 'value' => 'soft_land' );

	$fields['upw_layout'] = array(
		'type'         => 'multi-picker',
		'popover'      => true,
		'label'        => __( 'Layout Animation', 'fw' ),
		'desc'         => __( 'When the layout inside this element changes (an accordion opens, a filter hides cards, a panel is shown), its items glide to their new places instead of jumping.', 'fw' ),
		'help'         => __( 'Layout Animation (Animation Engine): put it on the section, column or element whose contents change. It watches for changes (items added, removed, shown, hidden, resized or given a new class) and animates every item from its old place to its new one with a transform that adds to the item’s own. Optionally the element’s own height glides as it grows or shrinks. Honours “reduce motion” (no animation) and loads only on pages that use it.', 'fw' ),
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
						'small' => array( 'src' => $base . '/layout.svg', 'height' => 66 ),
						'large' => array( 'src' => $base . '/layout.svg', 'height' => 132 ),
						'label' => __( 'Layout Animation', 'fw' ),
					),
				),
			),
		),
		'choices'      => array(
			'off' => array(),
			'on'  => array(
				'what'     => array(
					'type'    => 'select',
					'label'   => __( 'What glides', 'fw' ),
					'value'   => 'items',
					'choices' => array(
						'items'  => __( 'Its items, to their new places', 'fw' ),
						'height' => __( 'Its own height, as it grows or shrinks', 'fw' ),
						'both'   => __( 'Both', 'fw' ),
					),
				),
				'items'    => array(
					'type'  => 'text',
					'label' => __( 'Items', 'fw' ),
					'desc'  => __( 'Which elements count as items: a CSS selector inside this element (.card, li). Empty = found automatically: the children of the first level that holds several.', 'fw' ),
					'value' => '',
					'attr'  => array( 'placeholder' => '.card' ),
				),
				'duration' => array( 'type' => 'slider', 'label' => __( 'Duration (seconds)', 'fw' ), 'value' => 0.45, 'properties' => array( 'min' => 0.1, 'max' => 1.5, 'step' => 0.05 ) ),
				'ease'     => $ease,
				'enter'    => array(
					'type'         => 'switch',
					'label'        => __( 'New items fade in', 'fw' ),
					'desc'         => __( 'Items that appear (shown, or added to the page) fade and grow in.', 'fw' ),
					'value'        => 'yes',
					'left-choice'  => array( 'value' => 'no', 'label' => __( 'No', 'fw' ) ),
					'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
				),
			),
		),
	);
	return $fields;
} );

if ( ! function_exists( 'upw_layout_compile' ) ) :
	/** The runtime config of a Layout Animation value, or null when it is off. */
	function upw_layout_compile( $value ) {
		if ( ! is_array( $value ) || empty( $value['mode'] ) || $value['mode'] !== 'on' ) {
			return null;
		}
		$o    = ( isset( $value['on'] ) && is_array( $value['on'] ) ) ? $value['on'] : array();
		$what = ( isset( $o['what'] ) && in_array( $o['what'], array( 'items', 'height', 'both' ), true ) ) ? $o['what'] : 'items';
		$sel  = isset( $o['items'] ) ? (string) $o['items'] : '';
		$sel  = function_exists( 'upw_interactions_selector' ) ? upw_interactions_selector( $sel ) : trim( wp_strip_all_tags( $sel ) );
		$dur  = isset( $o['duration'] ) && is_numeric( $o['duration'] ) ? max( 0.1, min( 1.5, (float) $o['duration'] ) ) : 0.45;
		$ease = function_exists( 'upw_interactions_ease_css' ) ? upw_interactions_ease_css( isset( $o['ease'] ) ? $o['ease'] : 'soft_land' ) : 'cubic-bezier(.22,1,.36,1)';
		return array(
			'w' => $what,
			'q' => $sel,
			'd' => $dur,
			'e' => $ease,
			'n' => ( ! isset( $o['enter'] ) || $o['enter'] === 'yes' ) ? 1 : 0,
		);
	}
endif;

/* 2) Emit the config on the element's wrapper and record the runtime for the footer loader. */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	$c = isset( $atts['upw_layout'] ) ? upw_layout_compile( $atts['upw_layout'] ) : null;
	if ( ! $c ) {
		return $attr;
	}
	$attr['data-upw-layout'] = wp_json_encode( $c );
	if ( function_exists( 'upw_anim_use_asset' ) ) {
		upw_anim_use_asset( 'layout-animation', 'on' );
	}
	return $attr;
}, 22, 2 );

/* An element whose only setting is a layout animation still needs its wrapper. */
add_filter( 'sc_needs_wrapper', function ( $needs, $atts ) {
	if ( $needs ) {
		return $needs;
	}
	return isset( $atts['upw_layout'] ) && (bool) upw_layout_compile( $atts['upw_layout'] );
}, 10, 2 );

/* 3) On-demand runtime: one small script, only on pages that use it. */
if ( function_exists( 'upw_anim_register_assets' ) ) {
	$upw_la_ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( $upw_la_ext ) {
		upw_anim_register_assets( 'layout-animation', array(
			'path'      => __DIR__,
			'uri'       => $upw_la_ext->get_declared_URI( '/modules/layout-animation' ),
			'ver'       => $upw_la_ext->manifest->get_version(),
			'css_dir'   => 'static/css',
			'js_dir'    => 'static/js',
			'base_js'   => 'static/js/upw-layout.js',
			'js_styles' => array( 'on' ),
			'js_cfg'    => function () {
				$cfg = array(
					'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
				);
				return 'window.upwLayoutCfg=' . wp_json_encode( $cfg ) . ';';
			},
		) );
	}
	unset( $upw_la_ext );
}
