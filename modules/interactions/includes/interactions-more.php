<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Interactions + States — "More properties" (roadmap 6a): size, corner radius, skew, 3D tilt, colours,
 * filters and a clip reveal, on top of Move / Scale / Rotate / Opacity / Blur.
 *
 * An inline multi-picker (None / On) under each step and each state, so the fields stay on the form
 * (and keep their values) while hidden, and switching back to None turns them off without losing them.
 *
 * Saved value: [ 'mode' => 'off'|'on', 'on' => [ width, height, radius, skew_x, skew_y, rotate_x, rotate_y,
 *   background, color, border_color, brightness, contrast, saturate, grayscale, clip, clip_amount ] ]
 * Compiled (merged into the step / state, only what differs from "leave it as it is"):
 *   w, h, rad (px) · skx, sky, rx, ry (deg) · bg, fg, bc (CSS colours) · br, ct, sa (%, 100 = as is),
 *   gs (%) · cs (l|r|t|b|c) + ca (% hidden).
 */

if ( ! function_exists( 'upw_interactions_more_field' ) ) :
	function upw_interactions_more_field() {
		$slider = function ( $label, $val, $min, $max, $step, $desc = '' ) {
			return array( 'type' => 'slider', 'label' => $label, 'desc' => $desc, 'value' => $val, 'properties' => array( 'min' => $min, 'max' => $max, 'step' => $step ) );
		};
		$colour = function ( $label ) {
			return array( 'type' => 'rgba-color-picker', 'label' => $label, 'desc' => __( 'Empty leaves it as it is. The swatches are your Theme Settings colours.', 'fw' ), 'value' => '' );
		};
		$as_is = __( '0 leaves it as it is.', 'fw' );
		return array(
			'type'         => 'multi-picker',
			'label'        => false,
			'desc'         => false,
			'show_borders' => false,
			'value'        => array( 'mode' => 'off' ),
			'picker'       => array(
				'mode' => array(
					'type'    => 'select',
					'label'   => __( 'More properties', 'fw' ),
					'desc'    => __( 'Size, corner radius, skew, 3D tilt, colours, filters and a clip reveal.', 'fw' ),
					'value'   => 'off',
					'choices' => array(
						'off' => __( 'None', 'fw' ),
						'on'  => __( 'Size, colour, tilt, filters, clip…', 'fw' ),
					),
				),
			),
			'choices'      => array(
				'on' => array(
					'width'        => $slider( __( 'Width (px)', 'fw' ), 0, 0, 1600, 1, $as_is . ' ' . __( 'Size changes move the content around it.', 'fw' ) ),
					'height'       => $slider( __( 'Height (px)', 'fw' ), 0, 0, 1200, 1, $as_is ),
					'radius'       => $slider( __( 'Corner radius (px)', 'fw' ), 0, 0, 300, 1, $as_is ),
					'skew_x'       => $slider( __( 'Skew X (°)', 'fw' ), 0, -60, 60, 1 ),
					'skew_y'       => $slider( __( 'Skew Y (°)', 'fw' ), 0, -60, 60, 1 ),
					'rotate_x'     => $slider( __( 'Tilt X (°)', 'fw' ), 0, -180, 180, 1, __( '3D tilt around the horizontal axis: positive tips the top edge away from you.', 'fw' ) ),
					'rotate_y'     => $slider( __( 'Tilt Y (°)', 'fw' ), 0, -180, 180, 1, __( '3D tilt around the vertical axis: positive turns the right edge away from you.', 'fw' ) ),
					'background'   => $colour( __( 'Background colour', 'fw' ) ),
					'color'        => $colour( __( 'Text colour', 'fw' ) ),
					'border_color' => $colour( __( 'Border colour', 'fw' ) ),
					'brightness'   => $slider( __( 'Brightness (%)', 'fw' ), 100, 0, 300, 1, __( '100 leaves it as it is.', 'fw' ) ),
					'contrast'     => $slider( __( 'Contrast (%)', 'fw' ), 100, 0, 300, 1 ),
					'saturate'     => $slider( __( 'Saturation (%)', 'fw' ), 100, 0, 300, 1 ),
					'grayscale'    => $slider( __( 'Greyscale (%)', 'fw' ), 0, 0, 100, 1 ),
					'clip'         => array(
						'type'    => 'select',
						'label'   => __( 'Clip', 'fw' ),
						'desc'    => __( 'Hides part of it behind an edge or a circle. With Direction “From”, it is revealed.', 'fw' ),
						'value'   => 'none',
						'choices' => array(
							'none'   => __( 'No clip', 'fw' ),
							'left'   => __( 'From the left edge', 'fw' ),
							'right'  => __( 'From the right edge', 'fw' ),
							'top'    => __( 'From the top edge', 'fw' ),
							'bottom' => __( 'From the bottom edge', 'fw' ),
							'circle' => __( 'Circle, to the centre', 'fw' ),
						),
					),
					'clip_amount'  => $slider( __( 'Clip amount (%)', 'fw' ), 100, 0, 100, 1, __( 'How much is hidden: 100 = all of it.', 'fw' ) ),
				),
			),
		);
	}
endif;

if ( ! function_exists( 'upw_interactions_colour' ) ) :
	/** A CSS colour from a colour picker, or '' (hex, rgb[a], hsl[a], transparent, or a var(--…) preset). */
	function upw_interactions_colour( $c ) {
		$c = trim( (string) $c );
		if ( $c === '' ) {
			return '';
		}
		if ( preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\([0-9.,\s%]+\)|hsla?\([0-9.,\s%deg]+\)|transparent|var\(--[A-Za-z0-9_-]+\))$/', $c ) ) {
			return $c;
		}
		return '';
	}
endif;

if ( ! function_exists( 'upw_interactions_more_compile' ) ) :
	/** The compact keys of a "More properties" value (only those that change something), or []. */
	function upw_interactions_more_compile( $more ) {
		if ( ! is_array( $more ) || empty( $more['mode'] ) || $more['mode'] !== 'on' || empty( $more['on'] ) || ! is_array( $more['on'] ) ) {
			return array();
		}
		$m   = $more['on'];
		$n   = function ( $k, $def, $min, $max ) use ( $m ) {
			return upw_interactions_num( $m, $k, $def, $min, $max );
		};
		$out = array();
		foreach ( array( 'w' => array( 'width', 1600 ), 'h' => array( 'height', 1200 ), 'rad' => array( 'radius', 300 ) ) as $key => $def ) {
			$v = $n( $def[0], 0, 0, $def[1] );
			if ( $v > 0 ) {
				$out[ $key ] = $v;
			}
		}
		foreach ( array( 'skx' => array( 'skew_x', 60 ), 'sky' => array( 'skew_y', 60 ), 'rx' => array( 'rotate_x', 180 ), 'ry' => array( 'rotate_y', 180 ) ) as $key => $def ) {
			$v = $n( $def[0], 0, -$def[1], $def[1] );
			if ( $v ) {
				$out[ $key ] = $v;
			}
		}
		foreach ( array( 'bg' => 'background', 'fg' => 'color', 'bc' => 'border_color' ) as $key => $id ) {
			$c = upw_interactions_colour( isset( $m[ $id ] ) ? $m[ $id ] : '' );
			if ( $c !== '' ) {
				$out[ $key ] = $c;
			}
		}
		foreach ( array( 'br' => 'brightness', 'ct' => 'contrast', 'sa' => 'saturate' ) as $key => $id ) {
			$v = $n( $id, 100, 0, 300 );
			if ( abs( $v - 100 ) > 0.01 ) {
				$out[ $key ] = $v;
			}
		}
		$gs = $n( 'grayscale', 0, 0, 100 );
		if ( $gs > 0 ) {
			$out['gs'] = $gs;
		}
		$sides = array( 'left' => 'l', 'right' => 'r', 'top' => 't', 'bottom' => 'b', 'circle' => 'c' );
		$clip  = isset( $m['clip'] ) ? (string) $m['clip'] : 'none';
		if ( isset( $sides[ $clip ] ) ) {
			$ca = $n( 'clip_amount', 100, 0, 100 );
			if ( $ca > 0 ) {
				$out['cs'] = $sides[ $clip ];
				$out['ca'] = $ca;
			}
		}
		return $out;
	}
endif;

if ( ! function_exists( 'upw_interactions_ease_css' ) ) :
	/**
	 * A saved easing value as a CSS timing function. The older short keys keep their exact curves; every
	 * other value goes through the shared easing set (springs, the Motion design curves, a custom
	 * cubic-bezier). Unknown or "Default" = Smooth stop.
	 */
	function upw_interactions_ease_css( $key ) {
		$legacy = upw_interactions_eases();
		$key    = (string) $key;
		if ( isset( $legacy[ $key ] ) ) {
			return $legacy[ $key ];
		}
		$css = function_exists( 'sc_easing_css' ) ? sc_easing_css( $key ) : '';
		return $css !== '' ? $css : $legacy['out'];
	}
endif;

if ( ! function_exists( 'upw_interactions_ease_field' ) ) :
	/** The shared easing picker (springs, Motion design curves, a custom cubic-bezier), or the old select without it. */
	function upw_interactions_ease_field( $value, $label = null ) {
		$label = $label === null ? __( 'Easing', 'fw' ) : $label;
		$value = function_exists( 'sc_easing_normalize' ) ? sc_easing_normalize( $value ) : $value;
		if ( class_exists( 'FW_Option_Type_Easing_Picker' ) || function_exists( 'sc_easing_defs' ) ) {
			return array(
				'type'  => 'easing-picker',
				'label' => $label,
				'desc'  => __( 'The speed curve. Motion design and Spring curves overshoot and settle like real objects; Custom curve takes a CSS cubic-bezier.', 'fw' ),
				'value' => $value,
			);
		}
		return array(
			'type'    => 'select',
			'label'   => $label,
			'value'   => 'out',
			'choices' => array(
				'out'    => __( 'Smooth stop', 'fw' ),
				'inout'  => __( 'Smooth start and stop', 'fw' ),
				'in'     => __( 'Speed up', 'fw' ),
				'back'   => __( 'Overshoot', 'fw' ),
				'linear' => __( 'Linear', 'fw' ),
			),
		);
	}
endif;

if ( ! function_exists( 'upw_interactions_action_compile' ) ) :
	/**
	 * The compact keys of a Set / Lottie / Rive step (roadmap 6b, 6c), or [] when it has nothing usable.
	 *   set    → sk (show|hide|class_add|class_remove|class_toggle|z_index|pointer_off|pointer_on), sv
	 *   lottie → lo (play|reverse|pause|stop|frame|segment), lf, lt (frames)
	 *   rive   → ro (play|pause|trigger|input), rn (name), rv (number or boolean)
 *   burst  → bk (mix|ring|sparks|lines|confetti), bn (count), br (radius px), bc (colour)
	 */
	function upw_interactions_action_compile( $action, $st ) {
		$g = function ( $k, $d = '' ) use ( $st ) {
			return isset( $st[ $k ] ) && is_scalar( $st[ $k ] ) ? trim( (string) $st[ $k ] ) : $d;
		};
		if ( $action === 'set' ) {
			$kinds = array( 'show', 'hide', 'class_add', 'class_remove', 'class_toggle', 'z_index', 'pointer_off', 'pointer_on' );
			$k     = in_array( $g( 'set', 'hide' ), $kinds, true ) ? $g( 'set', 'hide' ) : 'hide';
			$out   = array( 'sk' => $k );
			if ( strpos( $k, 'class_' ) === 0 ) {
				$cls = trim( preg_replace( '/\s+/', ' ', preg_replace( '/[^A-Za-z0-9_\- ]/', '', wp_strip_all_tags( $g( 'set_value' ) ) ) ) );
				if ( $cls === '' ) {
					return array();
				}
				$out['sv'] = substr( $cls, 0, 120 );
			} elseif ( $k === 'z_index' ) {
				$out['sv'] = max( -9999, min( 99999, (int) $g( 'set_value', '10' ) ) );
			}
			return $out;
		}
		if ( $action === 'lottie' ) {
			$ops = array( 'play', 'reverse', 'pause', 'stop', 'frame', 'segment' );
			$op  = in_array( $g( 'lottie', 'play' ), $ops, true ) ? $g( 'lottie', 'play' ) : 'play';
			$out = array( 'lo' => $op );
			if ( $op === 'frame' || $op === 'segment' ) {
				$out['lf'] = max( 0, min( 100000, (float) $g( 'lottie_from', '0' ) ) );
			}
			if ( $op === 'segment' ) {
				if ( $g( 'lottie_to' ) === '' || ! is_numeric( $g( 'lottie_to' ) ) ) {
					return array();
				}
				$out['lt'] = max( 0, min( 100000, (float) $g( 'lottie_to' ) ) );
			}
			return $out;
		}
		if ( $action === 'burst' ) { // roadmap 5b; drawn by the Confetti module (off in Theme Settings = no burst)
			if ( function_exists( 'upw_confetti_enabled' ) && ! upw_confetti_enabled() ) {
				return array();
			}
			$kinds = array( 'mix', 'ring', 'sparks', 'lines', 'confetti' );
			$out   = array(
				'bk' => in_array( $g( 'burst', 'mix' ), $kinds, true ) ? $g( 'burst', 'mix' ) : 'mix',
				'bn' => max( 3, min( 60, (int) $g( 'burst_count', '10' ) ) ),
				'br' => max( 10, min( 400, (int) $g( 'burst_size', '70' ) ) ),
			);
			$c = upw_interactions_colour( $g( 'burst_colour' ) );
			if ( $c !== '' ) {
				$out['bc'] = $c;
			}
			return $out;
		}
		if ( $action === 'rive' ) {
			$ops = array( 'play', 'pause', 'trigger', 'input' );
			$op  = in_array( $g( 'rive', 'trigger' ), $ops, true ) ? $g( 'rive', 'trigger' ) : 'trigger';
			$out = array( 'ro' => $op );
			if ( $op === 'trigger' || $op === 'input' ) {
				$name = substr( wp_strip_all_tags( $g( 'rive_name' ) ), 0, 80 );
				if ( $name === '' ) {
					return array();
				}
				$out['rn'] = $name;
			}
			if ( $op === 'input' ) {
				$v = strtolower( $g( 'rive_value' ) );
				if ( $v === 'true' || $v === 'false' ) {
					$out['rv'] = $v === 'true';
				} elseif ( is_numeric( $v ) ) {
					$out['rv'] = (float) $v;
				} else {
					return array();
				}
			}
			return $out;
		}
		return array();
	}
endif;
