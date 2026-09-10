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
	/** Static used-flag for Scroll Keyframes; sets it when passed true and returns whether the module was used on the page. */
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
	// One state = eight animatable props (identity defaults, so an unset prop just does not move).
	// Inner ids are unprefixed — the `tabs` option namespaces them per state ({ start:{x,y,..}, mid, end }).
	$state = function ( $dy, $dop ) use ( $slider ) {
		return array(
			'x'         => $slider( __( 'X (px)', 'fw' ), 0, -300, 300, 5 ),
			'y'         => $slider( __( 'Y (px)', 'fw' ), $dy, -300, 300, 5 ),
			'scale'     => $slider( __( 'Scale', 'fw' ), 1, 0, 3, 0.05 ),
			'rotation'  => $slider( __( 'Rotate (deg)', 'fw' ), 0, -360, 360, 1 ),
			'rotationX' => $slider( __( 'Rotate X (deg)', 'fw' ), 0, -180, 180, 1 ),
			'rotationY' => $slider( __( 'Rotate Y (deg)', 'fw' ), 0, -180, 180, 1 ),
			'opacity'   => $slider( __( 'Opacity', 'fw' ), $dop, 0, 1, 0.05 ),
			'blur'      => $slider( __( 'Blur (px)', 'fw' ), 0, 0, 40, 1 ),
		);
	};

	// Start / Middle / End as tabs (the shared `tabs` option type). Default = a fade-up
	// (Start: y 40, opacity 0; End: natural), so enabling it does something useful.
	$states = array(
		'type'  => 'tabs',
		'label' => false,
		'dots'  => true,
		'value' => array(),
		'tabs'  => array(
			'start' => array( 'title' => __( 'Start', 'fw' ), 'options' => $state( 40, 0 ) ),
			'mid'   => array(
				'title'   => __( 'Middle', 'fw' ),
				'options' => array_merge(
					array(
						'enable' => $switch( __( 'Use a middle keyframe', 'fw' ), __( 'Add a third state the element passes through.', 'fw' ), false ),
						'at'     => $slider( __( 'Middle at (% of range)', 'fw' ), 50, 5, 95, 5 ),
						'ease'   => $ease( __( 'Ease into middle', 'fw' ) ),
					),
					$state( 0, 1 )
				),
			),
			'end' => array(
				'title'   => __( 'End', 'fw' ),
				'options' => array_merge( array( 'ease' => $ease( __( 'Ease into end', 'fw' ) ) ), $state( 0, 1 ) ),
			),
		),
	);

	$grp = array(
		'states'        => $states,
		'run_on_mobile' => $switch( __( 'Run on mobile', 'fw' ), '', true ),
	);

	// ---- Pin → Hold → Exit preset: one-click scroll choreography for ANY element ----
	// The element PINS to the screen for a chosen number of screen-heights (the runtime wraps it in a
	// sticky runway), appears, holds, then exits — no Scroll Story needed. Generates the keyframes below.
	$dir_in  = array(
		'fade' => __( 'Fade in', 'fw' ), 'up' => __( 'Rise up', 'fw' ), 'down' => __( 'Drop down', 'fw' ),
		'left' => __( 'From left', 'fw' ), 'right' => __( 'From right', 'fw' ), 'scale' => __( 'Scale up', 'fw' ), 'none' => __( 'Already there', 'fw' ),
	);
	$dir_out = array(
		'left' => __( 'Slide off left', 'fw' ), 'right' => __( 'Slide off right', 'fw' ), 'up' => __( 'Slide off top', 'fw' ),
		'down' => __( 'Slide off bottom', 'fw' ), 'fade' => __( 'Fade out', 'fw' ), 'scale' => __( 'Scale away', 'fw' ), 'none' => __( 'Stay to the end', 'fw' ),
	);
	$pin_grp = array(
		'pin_length'  => $slider( __( 'Pin length (screens)', 'fw' ), 2, 1, 8, 1 ),
		'pin_from'    => array( 'type' => 'select', 'label' => __( 'Appear by', 'fw' ), 'value' => 'fade', 'choices' => $dir_in,
			'desc' => __( 'How the element enters as it pins.', 'fw' ) ),
		'pin_hold'    => $slider( __( 'Hold until (% of pin)', 'fw' ), 60, 5, 100, 5 ),
		'pin_to'      => array( 'type' => 'select', 'label' => __( 'Exit by', 'fw' ), 'value' => 'left', 'choices' => $dir_out,
			'desc' => __( 'How it leaves after the hold. “Stay to the end” keeps it pinned for the whole length.', 'fw' ) ),
		'run_on_mobile' => $switch( __( 'Run on mobile', 'fw' ), '', true ),
	);

	$ext  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	$base = $ext ? $ext->get_declared_URI( '/modules/scroll-keyframes/static/img' ) : '';
	$tile = array(
		'small' => array( 'src' => $base . '/keyframes.svg', 'height' => 53 ),
		'large' => array( 'src' => $base . '/keyframes.svg', 'height' => 132 ),
		'label' => __( 'Scroll Keyframes', 'fw' ),
	);
	$pin_tile = array(
		'small' => array( 'src' => $base . '/pin-choreo.svg', 'height' => 53 ),
		'large' => array( 'src' => $base . '/pin-choreo.svg', 'height' => 132 ),
		'label' => __( 'Pin · Hold · Exit', 'fw' ),
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
				'desc'    => __( 'A scroll-driven timeline. <strong>Scroll Keyframes</strong> = a free Start → Middle → End (plays as the element passes). <strong>Pin · Hold · Exit</strong> = the element pins to the screen for a few scrolls, then leaves — one-click scroll choreography for any element.', 'fw' ),
				'value'   => 'none',
				'choices' => array( 'keyframes' => $tile, 'pin_choreo' => $pin_tile ),
			),
		),
		'choices' => array(
			'none'       => array(),
			'keyframes'  => array( 'group_scroll_keyframes' => array( 'type' => 'group', 'options' => $grp ) ),
			'pin_choreo' => array( 'group_pin_choreo' => array( 'type' => 'group', 'options' => $pin_grp ) ),
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
	$mode = isset( $c['mode'] ) ? (string) $c['mode'] : 'none';

	// --- Pin · Hold · Exit preset: build appear→hold→exit keyframes + a pin flag for the runtime. ---
	if ( 'pin_choreo' === $mode ) {
		$pg      = ( isset( $c['pin_choreo'] ) && is_array( $c['pin_choreo'] ) ) ? $c['pin_choreo'] : array();
		$pin_len = max( 1, min( 8, (int) ( isset( $pg['pin_length'] ) ? $pg['pin_length'] : 2 ) ) );
		$from    = (string) ( isset( $pg['pin_from'] ) ? $pg['pin_from'] : 'fade' );
		$hold    = max( 5, min( 100, (int) ( isset( $pg['pin_hold'] ) ? $pg['pin_hold'] : 60 ) ) );
		$to      = (string) ( isset( $pg['pin_to'] ) ? $pg['pin_to'] : 'left' );

		// A direction → a partial state (only the props that move; the runtime fills the rest with identity).
		$off = function ( $dir, $enter ) {
			$d = $enter ? 80 : 700; // enter slides a little into place; exit flies far enough to clear the screen
			switch ( $dir ) {
				case 'up':    return array( 'y' => $enter ? $d : -$d, 'opacity' => 0 );
				case 'down':  return array( 'y' => $enter ? -$d : $d, 'opacity' => 0 );
				case 'left':  return array( 'x' => -$d, 'opacity' => 0 );
				case 'right': return array( 'x' => $d, 'opacity' => 0 );
				case 'scale': return array( 'scale' => 0.7, 'opacity' => 0 );
				case 'fade':  return array( 'opacity' => 0 );
				default:      return array(); // 'none' → no move
			}
		};
		$appear  = 0.15;
		$hold_at = min( 0.99, max( $appear + 0.01, $hold / 100 ) );

		$kf   = array();
		$kf[] = array( 'at' => 0,        'v' => ( 'none' === $from ? array() : $off( $from, true ) ), 'ease' => 'out' );
		$kf[] = array( 'at' => $appear,  'v' => array(), 'ease' => 'out' );   // arrived (identity)
		$kf[] = array( 'at' => $hold_at, 'v' => array(), 'ease' => 'inout' ); // end of hold
		if ( 'none' !== $to ) {
			$kf[] = array( 'at' => 1, 'v' => $off( $to, false ), 'ease' => 'in' );
		}

		$cls           = isset( $attr['class'] ) ? trim( (string) $attr['class'] ) : '';
		$attr['class'] = esc_attr( trim( $cls . ' sc-scroll-kf sc-scroll-kf--pin' ) );
		$attr['data-upw-skf']     = esc_attr( base64_encode( wp_json_encode( array( 'kf' => $kf ) ) ) );
		$attr['data-upw-skf-pin'] = esc_attr( (string) $pin_len );
		if ( ( isset( $pg['run_on_mobile'] ) ? $pg['run_on_mobile'] : 'yes' ) !== 'yes' ) {
			$attr['data-upw-skf-mobile'] = '0';
		}
		upw_skf_flag( true );
		return $attr;
	}

	if ( 'keyframes' !== $mode ) {
		return $attr;
	}
	$g = ( isset( $c['keyframes'] ) && is_array( $c['keyframes'] ) ) ? $c['keyframes'] : array();
	$st = ( isset( $g['states'] ) && is_array( $g['states'] ) ) ? $g['states'] : array();
	$state = function ( $p ) use ( $st ) {
		$s = ( isset( $st[ $p ] ) && is_array( $st[ $p ] ) ) ? $st[ $p ] : array();
		return array(
			'x'         => (float) ( isset( $s['x'] ) ? $s['x'] : 0 ),
			'y'         => (float) ( isset( $s['y'] ) ? $s['y'] : 0 ),
			'scale'     => (float) ( isset( $s['scale'] ) ? $s['scale'] : 1 ),
			'rotation'  => (float) ( isset( $s['rotation'] ) ? $s['rotation'] : 0 ),
			'rotationX' => (float) ( isset( $s['rotationX'] ) ? $s['rotationX'] : 0 ),
			'rotationY' => (float) ( isset( $s['rotationY'] ) ? $s['rotationY'] : 0 ),
			'opacity'   => (float) ( isset( $s['opacity'] ) ? $s['opacity'] : 1 ),
			'blur'      => (float) ( isset( $s['blur'] ) ? $s['blur'] : 0 ),
		);
	};
	$mid    = ( isset( $st['mid'] ) && is_array( $st['mid'] ) ) ? $st['mid'] : array();
	$end_s  = ( isset( $st['end'] ) && is_array( $st['end'] ) ) ? $st['end'] : array();
	$start  = $state( 'start' );
	$end    = $state( 'end' );
	$mid_on = ( ( isset( $mid['enable'] ) ? $mid['enable'] : 'no' ) === 'yes' );

	// Nothing to animate (no middle and start == end) → don't stamp anything.
	if ( ! $mid_on && $start === $end ) {
		return $attr;
	}

	$kf   = array();
	$kf[] = array( 'at' => 0, 'v' => $start, 'ease' => 'out' );
	if ( $mid_on ) {
		$at   = max( 5, min( 95, (int) ( isset( $mid['at'] ) ? $mid['at'] : 50 ) ) ) / 100;
		$kf[] = array( 'at' => $at, 'v' => $state( 'mid' ), 'ease' => (string) ( isset( $mid['ease'] ) ? $mid['ease'] : 'out' ) );
	}
	$kf[] = array( 'at' => 1, 'v' => $end, 'ease' => (string) ( isset( $end_s['ease'] ) ? $end_s['ease'] : 'out' ) );

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
	$m = isset( $c['mode'] ) ? (string) $c['mode'] : 'none';
	return ( 'keyframes' === $m || 'pin_choreo' === $m );
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
