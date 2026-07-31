<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Scroll Keyframes module (node-level).
 *
 * The builder's "motion timeline": an element interpolates between a START, an optional MIDDLE and an
 * END state (transform / opacity / blur), driven by scroll. The progress source is automatic —
 *   • inside a Scroll Story stage → the element scrubs across ITS scene's beat slice (reusing the
 *     story's published __storyProgress / __beatIndex / __storyBeats), so it plays across the pinned
 *     range; otherwise
 *   • it scrubs as the element travels the viewport (the shared window.upwScrollProgress helper).
 *
 * Per-element (attaches from the Animations tab via `sc_animation_fields`). One tiny runtime that
 * rides the shared frame scheduler (window.upwAnimRaf); honours reduce motion + mobile; loads only on
 * pages that use it. No GSAP.
 *
 * Saved value shape (multi-picker, picker id `mode`):
 *   [ 'mode' => 'none'|'keyframes', 'keyframes' => [ start_x, start_y, …, mid_enable, mid_at,
 *     mid_ease, mid_x, …, end_ease, end_x, …, run_on_mobile ] ]
 * Baked as base64 JSON on `data-upw-skf`: { kf: [ { at, v:{x,y,scale,rotation,rotationX,rotationY,
 * opacity,blur}, ease }, … ] }.
 */

if ( ! function_exists( 'upw_skf_enabled' ) ) :
	/** Global master switch (defaults to enabled, like every other module). */
	function upw_skf_enabled() {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return true;
		}
		$v = fw_get_db_settings_option( 'animation_scroll_keyframes', array() );
		$e = ( is_array( $v ) && isset( $v['enable'] ) ) ? $v['enable'] : 'yes';
		return $e !== 'no' && $e !== false;
	}
endif;

if ( ! function_exists( 'upw_skf_flag' ) ) :
	function upw_skf_flag( $set = false ) {
		static $used = false;
		if ( $set ) {
			$used = true;
		}
		return $used;
	}
endif;

/* ------------------------------------------------------------------ *
 * 1) Per-element "Scroll Keyframes" control (Animations tab).
 * ------------------------------------------------------------------ */
add_filter( 'sc_animation_fields', function ( $fields ) {
	if ( ! is_array( $fields ) ) {
		return $fields;
	}
	$slider = function ( $label, $val, $min, $max, $step = 1 ) {
		return array( 'type' => 'slider', 'label' => $label, 'value' => $val, 'properties' => array( 'min' => $min, 'max' => $max, 'step' => $step ) );
	};
	$switch = function ( $label, $desc, $default_yes ) {
		return array(
			'type' => 'switch', 'label' => $label, 'desc' => $desc, 'value' => $default_yes ? 'yes' : 'no',
			'left-choice'  => array( 'value' => 'no',  'label' => __( 'No', 'fw' ) ),
			'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
		);
	};
	$ease = function ( $label ) {
		return array(
			'type' => 'select', 'label' => $label, 'value' => 'out', 'choices' => array(
				'linear' => __( 'Linear', 'fw' ), 'out' => __( 'Ease Out', 'fw' ), 'in' => __( 'Ease In', 'fw' ),
				'inout'  => __( 'Ease In-Out', 'fw' ), 'back' => __( 'Overshoot (back)', 'fw' ), 'sine' => __( 'Sine', 'fw' ),
			),
		);
	};
	$heading = function ( $text ) {
		return array( 'type' => 'html', 'label' => false, 'desc' => false, 'html' => '<h4 style="margin:22px 0 4px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:#666;">' . esc_html( $text ) . '</h4>' );
	};
	// One state = eight animatable props (identity defaults, so an unset prop just doesn't move).
	$state = function ( $p, $dy, $dop ) use ( $slider ) {
		return array(
			$p . '_x'         => $slider( __( 'X (px)', 'fw' ), 0, -300, 300, 5 ),
			$p . '_y'         => $slider( __( 'Y (px)', 'fw' ), $dy, -300, 300, 5 ),
			$p . '_scale'     => $slider( __( 'Scale', 'fw' ), 1, 0, 3, 0.05 ),
			$p . '_rotation'  => $slider( __( 'Rotate (deg)', 'fw' ), 0, -360, 360, 1 ),
			$p . '_rotationX' => $slider( __( 'Rotate X (deg)', 'fw' ), 0, -180, 180, 1 ),
			$p . '_rotationY' => $slider( __( 'Rotate Y (deg)', 'fw' ), 0, -180, 180, 1 ),
			$p . '_opacity'   => $slider( __( 'Opacity', 'fw' ), $dop, 0, 1, 0.05 ),
			$p . '_blur'      => $slider( __( 'Blur (px)', 'fw' ), 0, 0, 40, 1 ),
		);
	};

	// Default = a fade-up (Start: y 40, opacity 0 → End: natural), so enabling it does something useful.
	$grp = array_merge(
		array( '_h_start' => $heading( __( 'Start state', 'fw' ) ) ),
		$state( 'start', 40, 0 ),
		array(
			'_h_mid'     => $heading( __( 'Middle state (optional)', 'fw' ) ),
			'mid_enable' => $switch( __( 'Use a middle keyframe', 'fw' ), __( 'Add a third state the element passes through.', 'fw' ), false ),
			'mid_at'     => $slider( __( 'Middle at (% of range)', 'fw' ), 50, 5, 95, 5 ),
			'mid_ease'   => $ease( __( 'Ease into middle', 'fw' ) ),
		),
		$state( 'mid', 0, 1 ),
		array(
			'_h_end'   => $heading( __( 'End state', 'fw' ) ),
			'end_ease' => $ease( __( 'Ease into end', 'fw' ) ),
		),
		$state( 'end', 0, 1 ),
		array( 'run_on_mobile' => $switch( __( 'Run on mobile', 'fw' ), '', true ) )
	);

	$ext  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	$base = $ext ? $ext->get_declared_URI( '/modules/scroll-keyframes/static/img' ) : '';
	$tile = array(
		'small' => array( 'src' => $base . '/keyframes.svg', 'height' => 53 ),
		'large' => array( 'src' => $base . '/keyframes.svg', 'height' => 132 ),
		'label' => __( 'Scroll Keyframes', 'fw' ),
	);

	$fields['scroll_keyframes'] = array(
		'type'         => 'multi-picker',
		'popover'      => true,
		'label'        => __( 'Scroll Keyframes', 'fw' ),
		'desc'         => __( 'Move this element through keyframed states as you scroll — a mini motion timeline.', 'fw' ),
		'help'         => __( 'Scroll Keyframes (Animation Engine): the element interpolates between a Start, an optional Middle and an End state (transform / opacity / blur) as you scroll. Inside a Scroll Story it plays across that scene; anywhere else it plays as the element travels the viewport. Rides the shared frame loop; honours reduce motion; loads only on pages that use it.', 'fw' ) . ( function_exists( 'upw_perf_note' ) ? ' ' . upw_perf_note() : '' ),
		'show_borders' => false,
		'value'        => array( 'mode' => 'none' ),
		'placeholder'  => __( 'None', 'fw' ),
		'anim_meta'    => array( 'category' => __( 'Scroll', 'fw' ) ),
		'picker'       => array(
			'mode' => array(
				'type'    => 'image-picker',
				'label'   => false,
				'desc'    => __( 'Enable a scroll-driven keyframe timeline on this element.', 'fw' ),
				'value'   => 'none',
				'choices' => array( 'keyframes' => $tile ),
			),
		),
		'choices' => array(
			'none'      => array(),
			'keyframes' => array( 'group_scroll_keyframes' => array( 'type' => 'group', 'options' => $grp ) ),
		),
	);
	return $fields;
} );

/* ------------------------------------------------------------------ *
 * 2) Bake the keyframes onto the element wrapper.
 * ------------------------------------------------------------------ */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	if ( ! upw_skf_enabled() ) {
		return $attr;
	}
	$c = ( isset( $atts['scroll_keyframes'] ) && is_array( $atts['scroll_keyframes'] ) ) ? $atts['scroll_keyframes'] : array();
	if ( ( isset( $c['mode'] ) ? (string) $c['mode'] : 'none' ) !== 'keyframes' ) {
		return $attr;
	}
	$g = ( isset( $c['keyframes'] ) && is_array( $c['keyframes'] ) ) ? $c['keyframes'] : array();
	$state = function ( $p ) use ( $g ) {
		return array(
			'x'         => (float) ( isset( $g[ $p . '_x' ] ) ? $g[ $p . '_x' ] : 0 ),
			'y'         => (float) ( isset( $g[ $p . '_y' ] ) ? $g[ $p . '_y' ] : 0 ),
			'scale'     => (float) ( isset( $g[ $p . '_scale' ] ) ? $g[ $p . '_scale' ] : 1 ),
			'rotation'  => (float) ( isset( $g[ $p . '_rotation' ] ) ? $g[ $p . '_rotation' ] : 0 ),
			'rotationX' => (float) ( isset( $g[ $p . '_rotationX' ] ) ? $g[ $p . '_rotationX' ] : 0 ),
			'rotationY' => (float) ( isset( $g[ $p . '_rotationY' ] ) ? $g[ $p . '_rotationY' ] : 0 ),
			'opacity'   => (float) ( isset( $g[ $p . '_opacity' ] ) ? $g[ $p . '_opacity' ] : 1 ),
			'blur'      => (float) ( isset( $g[ $p . '_blur' ] ) ? $g[ $p . '_blur' ] : 0 ),
		);
	};
	$start  = $state( 'start' );
	$end    = $state( 'end' );
	$mid_on = ( ( isset( $g['mid_enable'] ) ? $g['mid_enable'] : 'no' ) === 'yes' );

	// Nothing to animate (no middle and start == end) → don't stamp anything.
	if ( ! $mid_on && $start === $end ) {
		return $attr;
	}

	$kf   = array();
	$kf[] = array( 'at' => 0, 'v' => $start, 'ease' => 'out' );
	if ( $mid_on ) {
		$at   = max( 5, min( 95, (int) ( isset( $g['mid_at'] ) ? $g['mid_at'] : 50 ) ) ) / 100;
		$kf[] = array( 'at' => $at, 'v' => $state( 'mid' ), 'ease' => (string) ( isset( $g['mid_ease'] ) ? $g['mid_ease'] : 'out' ) );
	}
	$kf[] = array( 'at' => 1, 'v' => $end, 'ease' => (string) ( isset( $g['end_ease'] ) ? $g['end_ease'] : 'out' ) );

	$cls           = isset( $attr['class'] ) ? trim( (string) $attr['class'] ) : '';
	$attr['class'] = esc_attr( trim( $cls . ' sc-scroll-kf' ) );
	$attr['data-upw-skf'] = esc_attr( base64_encode( wp_json_encode( array( 'kf' => $kf ) ) ) );
	if ( ( isset( $g['run_on_mobile'] ) ? $g['run_on_mobile'] : 'yes' ) !== 'yes' ) {
		$attr['data-upw-skf-mobile'] = '0';
	}
	upw_skf_flag( true );
	return $attr;
}, 24, 2 );

/* ------------------------------------------------------------------ *
 * 3) Force a wrapper when Scroll Keyframes is the only non-default setting.
 * ------------------------------------------------------------------ */
add_filter( 'sc_needs_wrapper', function ( $needs, $atts ) {
	if ( $needs || ! upw_skf_enabled() ) {
		return $needs;
	}
	$c = ( isset( $atts['scroll_keyframes'] ) && is_array( $atts['scroll_keyframes'] ) ) ? $atts['scroll_keyframes'] : array();
	return ( isset( $c['mode'] ) && (string) $c['mode'] === 'keyframes' );
}, 10, 2 );

/* ------------------------------------------------------------------ *
 * 4) Enqueue the tiny runtime only on pages that use it.
 * ------------------------------------------------------------------ */
add_action( 'wp_footer', function () {
	if ( ! upw_skf_flag() ) {
		return;
	}
	$ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( ! $ext ) {
		return;
	}
	$base = $ext->get_declared_URI( '/modules/scroll-keyframes' );
	$ver  = $ext->manifest->get_version();
	$dir  = __DIR__;
	$jsv  = file_exists( "$dir/static/js/scroll-keyframes.js" ) ? $ver . '.' . filemtime( "$dir/static/js/scroll-keyframes.js" ) : $ver;
	// Depend on the shared frame scheduler (window.upwAnimRaf + upwScrollProgress + upwReduceMotion + upwIsMobile).
	$dep = function_exists( 'upw_anim_raf_handle' ) ? array( upw_anim_raf_handle() ) : array();
	wp_enqueue_script( 'upw-scroll-keyframes', $base . '/static/js/scroll-keyframes.js', $dep, $jsv, true );

	$cfg = array(
		'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
	);
	wp_add_inline_script( 'upw-scroll-keyframes', 'window.upwSkfCfg=' . wp_json_encode( $cfg ) . ';', 'before' );
}, 5 );
