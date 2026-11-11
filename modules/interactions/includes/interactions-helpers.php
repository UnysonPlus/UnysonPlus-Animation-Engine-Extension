<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

if ( ! function_exists( 'upw_interactions_modes' ) ) :
	/** The triggers, in inserter order. */
	function upw_interactions_modes() {
		return array( 'hover', 'click', 'load', 'view', 'past' );
	}
endif;

if ( ! function_exists( 'upw_interactions_eases' ) ) :
	/** Easing choices: key => CSS timing function. */
	function upw_interactions_eases() {
		return array(
			'out'     => 'cubic-bezier(.22,1,.36,1)',
			'inout'   => 'cubic-bezier(.65,0,.35,1)',
			'in'      => 'cubic-bezier(.64,0,.78,0)',
			'back'    => 'cubic-bezier(.34,1.56,.64,1)',
			'linear'  => 'linear',
		);
	}
endif;

if ( ! function_exists( 'upw_interactions_num' ) ) :
	function upw_interactions_num( $arr, $key, $def, $min, $max ) {
		$v = ( is_array( $arr ) && isset( $arr[ $key ] ) && is_numeric( $arr[ $key ] ) ) ? (float) $arr[ $key ] : (float) $def;
		return max( $min, min( $max, $v ) );
	}
endif;

if ( ! function_exists( 'upw_interactions_selector' ) ) :
	/** Keep a user CSS selector to characters a selector can hold (no markup, no braces). */
	function upw_interactions_selector( $s ) {
		$s = trim( wp_strip_all_tags( (string) $s ) );
		$s = str_replace( array( '{', '}', '<', '>', ';', '\\' ), '', $s );
		return substr( $s, 0, 300 );
	}
endif;

if ( ! function_exists( 'upw_interactions_compile' ) ) :
	/**
	 * Turn one saved Interactions value into the compact runtime config, or null when it is off / empty.
	 *
	 * @return array|null [ 't' => trigger, 'again'|'leave'|'offset' => …, 's' => [ step, … ] ]
	 */
	function upw_interactions_compile( $value ) {
		if ( ! is_array( $value ) || empty( $value['mode'] ) || ! in_array( $value['mode'], upw_interactions_modes(), true ) ) {
			return null;
		}
		$mode  = $value['mode'];
		$o     = ( isset( $value[ $mode ] ) && is_array( $value[ $mode ] ) ) ? $value[ $mode ] : array();
		$steps = ( isset( $o['steps'] ) && is_array( $o['steps'] ) ) ? $o['steps'] : array();
		$eases = upw_interactions_eases();
		$out   = array();
		foreach ( array( 's1', 's2', 's3' ) as $k ) {
			$st = isset( $steps[ $k ] ) && is_array( $steps[ $k ] ) ? $steps[ $k ] : array();
			$target = isset( $st['target'] ) ? (string) $st['target'] : ( $k === 's1' ? 'self' : 'none' );
			if ( ! in_array( $target, array( 'self', 'inside', 'page' ), true ) ) {
				continue;
			}
			$sel = upw_interactions_selector( isset( $st['selector'] ) ? $st['selector'] : '' );
			if ( $target === 'page' && $sel === '' ) {
				continue; // nothing to find
			}
			$ease = isset( $st['ease'], $eases[ $st['ease'] ] ) ? $eases[ $st['ease'] ] : $eases['out'];
			$out[] = array(
				'tg'  => $target,
				'q'   => $sel,
				'dir' => ( isset( $st['direction'] ) && $st['direction'] === 'from' ) ? 'from' : 'to',
				'x'   => upw_interactions_num( $st, 'x', 0, -2000, 2000 ),
				'y'   => upw_interactions_num( $st, 'y', 0, -2000, 2000 ),
				'sc'  => upw_interactions_num( $st, 'scale', 1, 0, 10 ),
				'r'   => upw_interactions_num( $st, 'rotate', 0, -1080, 1080 ),
				'op'  => upw_interactions_num( $st, 'opacity', 1, 0, 1 ),
				'bl'  => upw_interactions_num( $st, 'blur', 0, 0, 100 ),
				'd'   => upw_interactions_num( $st, 'duration', 0.5, 0, 20 ),
				'dl'  => upw_interactions_num( $st, 'delay', 0, 0, 20 ),
				'st'  => upw_interactions_num( $st, 'stagger', 0.08, 0, 5 ),
				'e'   => $ease,
				'w'   => ( isset( $st['start'] ) && $st['start'] === 'with' ) ? 1 : 0,
			);
			// A state step (I3): put the targets in a named state, take them out, or toggle it. The state's
			// own values / class / timing are defined on the target (States card); its delay / stagger / start
			// order still come from the step.
			$action = isset( $st['action'] ) ? (string) $st['action'] : 'animate';
			if ( in_array( $action, array( 'state_on', 'state_off', 'state_toggle' ), true ) ) {
				$name = upw_states_name( isset( $st['state'] ) ? $st['state'] : '' );
				if ( $name === '' ) {
					array_pop( $out ); // a state step without a state name does nothing
					continue;
				}
				$i = count( $out ) - 1;
				$out[ $i ]['a']  = $action === 'state_on' ? 'on' : ( $action === 'state_off' ? 'off' : 'toggle' );
				$out[ $i ]['sn'] = $name;
				$out[ $i ]['d']  = 0; // timing of a state change comes from the state
			}
		}
		if ( ! $out ) {
			return null;
		}
		$cfg = array( 't' => $mode, 's' => $out );
		// Devices (I5): only listed when not all three.
		if ( isset( $o['devices'] ) && is_array( $o['devices'] ) ) {
			$dev = array();
			foreach ( array( 'desktop' => 'd', 'tablet' => 't', 'phone' => 'p' ) as $k => $code ) {
				if ( ! empty( $o['devices'][ $k ] ) && $o['devices'][ $k ] !== 'false' ) {
					$dev[] = $code;
				}
			}
			if ( ! $dev ) {
				return null; // runs on no device
			}
			if ( count( $dev ) < 3 ) {
				$cfg['dev'] = $dev;
			}
		}
		// Per-device amount (I5): how far the steps go on tablet / phone, as a share of desktop (100 % = the same).
		$amt = array();
		foreach ( array( 'amount_tablet' => 't', 'amount_phone' => 'p' ) as $k => $code ) {
			$pct = upw_interactions_num( $o, $k, 100, 0, 300 );
			if ( abs( $pct - 100 ) > 0.01 ) {
				$amt[ $code ] = round( $pct / 100, 3 );
			}
		}
		if ( $amt ) {
			$cfg['amt'] = $amt;
		}
		// Reuse (I5): run on every element on the page that matches a selector.
		if ( isset( $o['scope'] ) && $o['scope'] === 'matches' ) {
			$on = upw_interactions_selector( isset( $o['scope_selector'] ) ? $o['scope_selector'] : '' );
			if ( $on !== '' ) {
				$cfg['on'] = $on;
				if ( isset( $o['sitewide'] ) && $o['sitewide'] === 'yes' ) {
					$cfg['site'] = 1; // collected for every page (interactions-sitewide.php)
				}
			}
		}
		if ( $mode === 'click' ) {
			$cfg['again'] = ( isset( $o['again'] ) && in_array( $o['again'], array( 'reverse', 'replay', 'none' ), true ) ) ? $o['again'] : 'reverse';
		} elseif ( $mode === 'view' ) {
			$cfg['leave'] = ( isset( $o['leave'] ) && $o['leave'] === 'keep' ) ? 'keep' : 'reverse';
		} elseif ( $mode === 'past' ) {
			$cfg['offset'] = upw_interactions_num( $o, 'offset', 80, -2000, 4000 );
		}
		return $cfg;
	}
endif;

if ( ! function_exists( 'upw_states_name' ) ) :
	/** A state name as a CSS-class-safe slug ("Open menu" → "open-menu"). */
	function upw_states_name( $s ) {
		$s = strtolower( trim( (string) $s ) );
		$s = preg_replace( '/[^a-z0-9]+/', '-', $s );
		return substr( trim( $s, '-' ), 0, 40 );
	}
endif;

if ( ! function_exists( 'upw_states_compile' ) ) :
	/**
	 * The element's named states (States card, I3) as runtime config, or [] when off.
	 *
	 * @return array [ [ 'n' => name, 'x','y','sc','r','op','bl', 'cls', 'd', 'e', 'start' => 0|1, 'g' => group, 'inert' => 0|1 ], … ]
	 */
	function upw_states_compile( $value ) {
		if ( ! is_array( $value ) || empty( $value['mode'] ) || $value['mode'] !== 'on' ) {
			return array();
		}
		$o     = ( isset( $value['on'] ) && is_array( $value['on'] ) ) ? $value['on'] : array();
		$tabs  = ( isset( $o['states'] ) && is_array( $o['states'] ) ) ? $o['states'] : array();
		$eases = upw_interactions_eases();
		$out   = array();
		$seen  = array();
		foreach ( array( 's1', 's2', 's3' ) as $k ) {
			$st   = isset( $tabs[ $k ] ) && is_array( $tabs[ $k ] ) ? $tabs[ $k ] : array();
			$name = upw_states_name( isset( $st['name'] ) ? $st['name'] : ( $k === 's1' ? 'open' : '' ) );
			if ( $name === '' || isset( $seen[ $name ] ) ) {
				continue;
			}
			$seen[ $name ] = true;
			$cls = trim( preg_replace( '/[^A-Za-z0-9_\- ]/', '', isset( $st['class'] ) ? (string) $st['class'] : '' ) );
			$out[] = array(
				'n'     => $name,
				'x'     => upw_interactions_num( $st, 'x', 0, -2000, 2000 ),
				'y'     => upw_interactions_num( $st, 'y', 0, -2000, 2000 ),
				'sc'    => upw_interactions_num( $st, 'scale', 1, 0, 10 ),
				'r'     => upw_interactions_num( $st, 'rotate', 0, -1080, 1080 ),
				'op'    => upw_interactions_num( $st, 'opacity', 1, 0, 1 ),
				'bl'    => upw_interactions_num( $st, 'blur', 0, 0, 100 ),
				'cls'   => substr( $cls, 0, 120 ),
				'd'     => upw_interactions_num( $st, 'duration', 0.4, 0, 20 ),
				'e'     => isset( $st['ease'], $eases[ $st['ease'] ] ) ? $eases[ $st['ease'] ] : $eases['out'],
				'start' => ( isset( $st['start_in'] ) && $st['start_in'] === 'yes' ) ? 1 : 0,
				'g'     => upw_states_name( isset( $st['group'] ) ? $st['group'] : '' ),
				'inert' => ( isset( $st['inert'] ) && $st['inert'] === 'yes' ) ? 1 : 0,
			);
		}
		return $out;
	}
endif;

if ( ! function_exists( 'upw_interactions_of' ) ) :
	/** Every Interactions instance on an element (the card is multi: `upw_interactions`, `__2`). */
	function upw_interactions_of( $atts ) {
		$list = array();
		if ( ! is_array( $atts ) ) {
			return $list;
		}
		foreach ( array( 'upw_interactions', 'upw_interactions__2' ) as $key ) {
			if ( isset( $atts[ $key ] ) ) {
				$c = upw_interactions_compile( $atts[ $key ] );
				if ( $c ) {
					$list[] = $c;
				}
			}
		}
		return $list;
	}
endif;
