<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Marquee module.
 *
 * Turns any element's content into a seamless, never-ending ticker (running text, a logo band,
 * scrolling images…). Attach it from the element's Animations tab; at runtime the content is
 * cloned into a doubled track and translated by exactly one set, so the loop has no visible jump.
 * Horizontal (left / right) or vertical (up / down). One shared CSS animation per element — no
 * library — enqueued only on pages that use it. Global on/off: Theme Settings → Animations → Effects.
 *
 * Saved value shape (multi-picker, picker id `mode`):
 *   [ 'mode' => 'left', 'left' => [ 'speed' => 'normal', 'gap' => 40, … ] ]
 */

if ( ! function_exists( 'upw_marquee_enabled' ) ) :
	function upw_marquee_enabled() {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return true;
		}
		$v = fw_get_db_settings_option( 'animation_marquee', array() );
		$e = ( is_array( $v ) && isset( $v['enable'] ) ) ? $v['enable'] : 'yes';
		return $e !== 'no' && $e !== false;
	}
endif;

if ( ! function_exists( 'upw_marquee_flag' ) ) :
	function upw_marquee_flag( $set = false ) {
		static $used = false;
		if ( $set ) {
			$used = true;
		}
		return $used;
	}
endif;

if ( ! function_exists( 'upw_mq_slider' ) ) :
	function upw_mq_slider( $label, $val, $min, $max, $step, $desc = '' ) {
		$f = array( 'type' => 'slider', 'label' => $label, 'value' => $val, 'properties' => array( 'min' => $min, 'max' => $max, 'step' => $step ) );
		if ( $desc !== '' ) { $f['desc'] = $desc; }
		return $f;
	}
endif;

/* ------------------------------------------------------------------ *
 * 1) The per-element "Marquee" control, appended to the Animations tab.
 * ------------------------------------------------------------------ */
add_filter( 'sc_animation_fields', function ( $fields ) {
	if ( ! is_array( $fields ) ) {
		return $fields;
	}

	// Options shared by every direction (built once, mapped onto each below).
	$opts = array(
		'speed' => array(
			'type'    => 'select',
			'label'   => __( 'Speed', 'fw' ),
			'value'   => 'normal',
			'choices' => array( 'slow' => __( 'Slow', 'fw' ), 'normal' => __( 'Normal', 'fw' ), 'fast' => __( 'Fast', 'fw' ) ),
		),
		'gap' => upw_mq_slider( __( 'Gap (px)', 'fw' ), 40, 0, 200, 4, __( 'Space between each repeat.', 'fw' ) ),
		'separator' => array(
			'type'  => 'text',
			'label' => __( 'Separator', 'fw' ),
			'value' => '',
			'desc'  => __( 'Optional text shown between repeats — e.g. • or —. Leave blank for none.', 'fw' ),
		),
		'pause_on_hover' => array(
			'type'         => 'switch',
			'label'        => __( 'Pause on hover', 'fw' ),
			'value'        => 'yes',
			'left-choice'  => array( 'value' => 'no',  'label' => __( 'No', 'fw' ) ),
			'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
		),
		'edge_fade' => array(
			'type'         => 'switch',
			'label'        => __( 'Fade edges', 'fw' ),
			'desc'         => __( 'Softly fade the content in / out at the container edges.', 'fw' ),
			'value'        => 'no',
			'left-choice'  => array( 'value' => 'no',  'label' => __( 'Off', 'fw' ) ),
			'right-choice' => array( 'value' => 'yes', 'label' => __( 'On', 'fw' ) ),
		),
		'scroll_reactive' => array(
			'type'         => 'switch',
			'label'        => __( 'React to scroll', 'fw' ),
			'desc'         => __( 'Speed up as the visitor scrolls faster (settles back when they stop).', 'fw' ),
			'value'        => 'no',
			'left-choice'  => array( 'value' => 'no',  'label' => __( 'Off', 'fw' ) ),
			'right-choice' => array( 'value' => 'yes', 'label' => __( 'On', 'fw' ) ),
		),
		'draggable' => array(
			'type'         => 'switch',
			'label'        => __( 'Draggable', 'fw' ),
			'desc'         => __( 'Let visitors grab and flick the ticker (with momentum).', 'fw' ),
			'value'        => 'no',
			'left-choice'  => array( 'value' => 'no',  'label' => __( 'Off', 'fw' ) ),
			'right-choice' => array( 'value' => 'yes', 'label' => __( 'On', 'fw' ) ),
		),
		'custom_speed' => array(
			'type'         => 'number',
			'label'        => __( 'Custom speed (px/s)', 'fw' ),
			'desc'         => __( 'Override the preset above. 0 = use the preset.', 'fw' ),
			'value'        => 0,
			'min'          => 0,
			'step'         => 5,
			'numeric_type' => 'integer',
		),
		'text_style' => array(
			'type'    => 'select',
			'label'   => __( 'Text style', 'fw' ),
			'desc'    => __( 'For text content — hollow outline letters.', 'fw' ),
			'value'   => 'normal',
			'choices' => array( 'normal' => __( 'Normal', 'fw' ), 'outline' => __( 'Outline (hollow)', 'fw' ) ),
		),
		'warp_heading' => array(
			'type'  => 'html',
			'label' => false,
			'desc'  => false,
			'html'  => '<h4 style="margin:24px 0 4px;font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#666;">' . esc_html__( 'Warp & Distortion', 'fw' ) . '</h4>',
		),
		'skew_h' => upw_mq_slider( __( 'Skew horizontal', 'fw' ), 0, -100, 100, 1, __( 'Slant the ticker left / right.', 'fw' ) ),
		'skew_v' => upw_mq_slider( __( 'Skew vertical', 'fw' ), 0, -100, 100, 1, __( 'Slant the ticker up / down.', 'fw' ) ),
		'tilt'   => upw_mq_slider( __( 'Tilt (angle)', 'fw' ), 0, -100, 100, 1, __( 'Rotate the whole ticker — an angled banner.', 'fw' ) ),
		'bend'   => upw_mq_slider( __( 'Bend (3D tilt)', 'fw' ), 0, -100, 100, 1, __( 'Tilt the ticker in 3D perspective. Works on any content.', 'fw' ) ),
		'curve'  => upw_mq_slider( __( 'Curve (arc text)', 'fw' ), 0, -100, 100, 1, __( 'Bend the TEXT along a real arc — a true curve (like on a circle). Text content only; overrides Bend for text.', 'fw' ) ),
		'wave'   => upw_mq_slider( __( 'Wave', 'fw' ), 0, 0, 100, 1, __( 'Make the content undulate up / down as it scrolls.', 'fw' ) ),
	);

	// Popover image-picker tiles — animated direction swatches (consistent with the other
	// engine effects). Popover multi-picker → the user-visible label lives on the TOP level.
	$ext  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	$base = $ext ? $ext->get_declared_URI( '/modules/marquee/static/img/directions' ) : '';
	$tile = function ( $file, $label ) use ( $base ) {
		return array(
			'small' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 96 ),
			'large' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 150 ),
			'label' => $label,
		);
	};

	$fields['marquee'] = array(
		'type'         => 'multi-picker',
		'popover'      => true,
		'label'        => __( 'Marquee', 'fw' ),
		'desc'         => __( 'Scroll this element\'s content in a seamless, never-ending loop — a ticker or running-text banner. The content is cloned so the loop has no visible jump. Works best on a heading or text with large type.', 'fw' ),
		'help'         => __( 'Marquee (Animation Engine): a self-running seamless ticker for any element. The content is doubled and translated by exactly one set (no jump), pauses on hover, and honours "reduce motion". Pure CSS animation, no library; loads only on pages that use it.', 'fw' ) . ( function_exists( 'upw_perf_note' ) ? ' ' . upw_perf_note() : '' ),
		'show_borders' => false,
		'value'        => array( 'mode' => 'none' ),
		'anim_meta'    => array( 'category' => __( 'Motion', 'fw' ), 'icon' => '&#127916;' ), // 🎞 (Animations-tab inserter)
		'picker'       => array(
			'mode' => array(
				'type'    => 'image-picker',
				'label'   => false,
				'desc'    => __( 'Hover a tile to preview it larger.', 'fw' ),
				'value'   => 'none',
				'choices' => array(
					'none'  => $tile( 'none',  __( 'None', 'fw' ) ),
					'left'  => $tile( 'left',  __( 'Left', 'fw' ) ),
					'right' => $tile( 'right', __( 'Right', 'fw' ) ),
					'up'    => $tile( 'up',    __( 'Up', 'fw' ) ),
					'down'  => $tile( 'down',  __( 'Down', 'fw' ) ),
				),
			),
		),
		'choices' => array(
			'none'  => array(),
			'left'  => $opts,
			'right' => $opts,
			'up'    => $opts,
			'down'  => $opts,
		),
	);

	return $fields;
} );

/* ------------------------------------------------------------------ *
 * 2) Emit the marquee settings onto the element wrapper.
 * ------------------------------------------------------------------ */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	if ( ! upw_marquee_enabled() ) {
		return $attr;
	}
	$mq   = ( isset( $atts['marquee'] ) && is_array( $atts['marquee'] ) ) ? $atts['marquee'] : array();
	$mode = isset( $mq['mode'] ) ? (string) $mq['mode'] : 'none';
	if ( ! in_array( $mode, array( 'left', 'right', 'up', 'down' ), true ) ) {
		return $attr;
	}
	$o = ( isset( $mq[ $mode ] ) && is_array( $mq[ $mode ] ) ) ? $mq[ $mode ] : array();

	$cls           = isset( $attr['class'] ) ? trim( (string) $attr['class'] ) : '';
	$attr['class'] = esc_attr( trim( $cls . ' sc-marquee sc-marquee--' . $mode ) );

	$speed = isset( $o['speed'] ) && in_array( $o['speed'], array( 'slow', 'normal', 'fast' ), true ) ? $o['speed'] : 'normal';
	$attr['data-mq-speed'] = esc_attr( $speed );
	$attr['data-mq-gap']   = esc_attr( (string) ( isset( $o['gap'] ) ? (float) $o['gap'] : 40 ) );

	$sep = isset( $o['separator'] ) ? trim( (string) $o['separator'] ) : '';
	if ( $sep !== '' ) {
		$attr['data-mq-sep'] = esc_attr( $sep );
	}
	if ( isset( $o['pause_on_hover'] ) && $o['pause_on_hover'] === 'no' ) {
		$attr['data-mq-pause'] = '0';
	}
	if ( isset( $o['edge_fade'] ) && $o['edge_fade'] === 'yes' ) {
		$attr['data-mq-fade'] = '1';
	}

	// Custom speed (px/s) overrides the preset when > 0.
	$cs = isset( $o['custom_speed'] ) ? (int) $o['custom_speed'] : 0;
	if ( $cs > 0 ) {
		$attr['data-mq-cspeed'] = esc_attr( (string) $cs );
	}

	if ( isset( $o['scroll_reactive'] ) && $o['scroll_reactive'] === 'yes' ) {
		$attr['data-mq-scrollreact'] = '1';
	}
	if ( isset( $o['draggable'] ) && $o['draggable'] === 'yes' ) {
		$attr['data-mq-drag'] = '1';
	}

	// Warp / distortion — stamp only the non-zero values.
	foreach ( array( 'skew_h' => 'skewh', 'skew_v' => 'skewv', 'tilt' => 'tilt', 'bend' => 'bend', 'curve' => 'curve', 'wave' => 'wave' ) as $key => $suffix ) {
		$v = isset( $o[ $key ] ) ? (float) $o[ $key ] : 0;
		if ( $v != 0.0 ) {
			$attr[ 'data-mq-' . $suffix ] = esc_attr( rtrim( rtrim( number_format( $v, 2, '.', '' ), '0' ), '.' ) );
		}
	}

	// Text fill style.
	$ts = isset( $o['text_style'] ) ? (string) $o['text_style'] : 'normal';
	if ( $ts === 'outline' || $ts === 'gradient' ) {
		$attr['class'] = esc_attr( trim( (string) $attr['class'] . ' sc-mq--' . $ts ) );
	}

	upw_marquee_flag( true );
	return $attr;
}, 23, 2 );

/* ------------------------------------------------------------------ *
 * 2b) Force a wrapper when an element's ONLY non-default setting is a marquee.
 * ------------------------------------------------------------------ */
add_filter( 'sc_needs_wrapper', function ( $needs, $atts ) {
	if ( $needs || ! upw_marquee_enabled() ) {
		return $needs;
	}
	$mq   = ( isset( $atts['marquee'] ) && is_array( $atts['marquee'] ) ) ? $atts['marquee'] : array();
	$mode = isset( $mq['mode'] ) ? (string) $mq['mode'] : 'none';
	return in_array( $mode, array( 'left', 'right', 'up', 'down' ), true );
}, 10, 2 );

/* ------------------------------------------------------------------ *
 * 3) Enqueue the runtime — only on pages that actually used a marquee.
 * ------------------------------------------------------------------ */
add_action( 'wp_footer', function () {
	if ( ! upw_marquee_flag() ) {
		return;
	}
	$ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( ! $ext ) {
		return;
	}
	$base = $ext->get_declared_URI( '/modules/marquee' );
	$ver  = $ext->manifest->get_version();
	$dir  = __DIR__;
	$jsv  = file_exists( "$dir/static/js/marquee.js" )  ? $ver . '.' . filemtime( "$dir/static/js/marquee.js" )  : $ver;
	$cssv = file_exists( "$dir/static/css/marquee.css" ) ? $ver . '.' . filemtime( "$dir/static/css/marquee.css" ) : $ver;

	wp_enqueue_style( 'upw-marquee', $base . '/static/css/marquee.css', array(), $cssv );
	wp_enqueue_script( 'upw-marquee', $base . '/static/js/marquee.js', array(), $jsv, true );

	$cfg = array(
		'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
		'disableMobile' => ( function_exists( 'upw_anim_engine_setting' ) && upw_anim_engine_setting( 'disable_on_mobile', 'no' ) === 'yes' ),
	);
	wp_add_inline_script( 'upw-marquee', 'window.upwMarqueeCfg=' . wp_json_encode( $cfg ) . ';', 'before' );
}, 5 );

/* ------------------------------------------------------------------ *
 * 4) Global on/off → Theme Settings → Animations → Marquee sub-tab.
 *    (The central Effects control folds this switch into the Effects tab.)
 * ------------------------------------------------------------------ */
add_filter( 'upw_anim_engine_module_tabs', function ( $tabs ) {
	$tabs['marquee_effect'] = array(
		'title'   => __( 'Marquee', 'fw' ),
		'type'    => 'tab',
		'options' => array(
			'marquee_box' => array(
				'title'   => __( 'Marquee', 'fw' ),
				'type'    => 'box',
				'options' => array(
					'animation_marquee' => array(
						'type'          => 'multi',
						'label'         => false,
						'inner-options' => array(
							'enable' => array(
								'label'        => __( 'Enable marquee', 'fw' ),
								'desc'         => __( 'Master switch for the per-element Marquee. Off = none load anywhere.', 'fw' ),
								'type'         => 'switch',
								'value'        => 'yes',
								'left-choice'  => array( 'value' => 'no',  'label' => __( 'No', 'fw' ) ),
								'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
							),
						),
					),
				),
			),
		),
	);
	return $tabs;
} );

/* Marquee picker tile size (admin only, marquee-scoped) — the swatches bake a direction label,
 * so bump them past the core 72px popover cap. Mirrors the physics module's approach. */
add_action( 'admin_head', function () {
	$sel = 'ul.thumbnails.image_picker_selector li .thumbnail img[src*="/marquee/static/img/directions/"]';
	echo '<style id="upw-marquee-picker-size">'
		. '.fw-mp-pop ' . $sel . ','
		. '.fw-modal-large .fw-mp-pop ' . $sel . ','
		. '.appearance_page_fw-settings .fw-mp-pop ' . $sel
		. '{height:96px !important;width:auto !important;}'
		. "</style>\n";
} );
