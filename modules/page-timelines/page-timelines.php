<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Page timelines (Animation Creator 5e: Save and ship).
 *
 * A page timeline is made in Kinetic Scenes' page mode (the Live Editor's Animate) and saved on the page as post meta
 * (UPW_PAGE_TIMELINES_META), never inside the builder JSON, so re-imports and the manual-edit guard leave it alone.
 * On a visitor page that has any, this module:
 *   1. stamps `data-upw-tl="<item key>"` on each element a timeline moves or listens to (only those, so every other
 *      page and element keeps its clean markup);
 *   2. prints the timelines as <script type="application/json" class="upw-timelines">;
 *   3. loads the runtime (static/js/upw-timeline.js, ~3 KB gzipped), which arms each timeline for its trigger.
 * Pages without timelines get nothing.
 *
 * The data shape is documented in upw-timeline.js. upw_page_timelines_clean() is the one gate every write goes through.
 */

if ( ! defined( 'UPW_PAGE_TIMELINES_META' ) ) {
	define( 'UPW_PAGE_TIMELINES_META', '_upw_page_timelines' );
}

/** The longest a stored list may be, in bytes of JSON (a page with many long timelines is still well under). */
if ( ! defined( 'UPW_PAGE_TIMELINES_MAX_BYTES' ) ) {
	define( 'UPW_PAGE_TIMELINES_MAX_BYTES', 400000 );
}

/** A builder item key or timeline id: letters, digits, - and _. */
function upw_page_timelines_key( $v, $max = 64 ) {
	$v = is_scalar( $v ) ? (string) $v : '';
	return ( '' !== $v && strlen( $v ) <= $max && preg_match( '/^[A-Za-z0-9_-]+$/', $v ) ) ? $v : '';
}

/** A class target (".card"): every element with that class (Animation Creator A3). */
function upw_page_timelines_class( $v ) {
	$v = is_scalar( $v ) ? (string) $v : '';
	return preg_match( '/^\.[A-Za-z_-][A-Za-z0-9_-]{0,63}$/', $v ) ? $v : '';
}

/** What a track or trigger may point at: an element's builder key, or a class. */
function upw_page_timelines_target( $v ) {
	$k = upw_page_timelines_key( $v );
	return '' !== $k ? $k : upw_page_timelines_class( $v );
}

/** A number clamped to [min, max], or null when it is not a number. */
function upw_page_timelines_num( $v, $min, $max ) {
	if ( ! is_numeric( $v ) ) {
		return null;
	}
	$v = (float) $v;
	if ( ! is_finite( $v ) ) {
		return null;
	}
	return round( max( $min, min( $max, $v ) ), 4 );
}

/** A CSS timing function the runtime understands (linear(), cubic-bezier(), a keyword), or ''. */
function upw_page_timelines_css_ease( $v ) {
	$v = is_string( $v ) ? trim( $v ) : '';
	if ( '' === $v || strlen( $v ) > 2000 ) {
		return '';
	}
	if ( in_array( $v, array( 'linear', 'ease', 'ease-in', 'ease-out', 'ease-in-out' ), true ) ) {
		return $v;
	}
	if ( preg_match( '/^(linear|cubic-bezier)\([-0-9.,%\s]+\)$/', $v ) ) {
		return $v;
	}
	return '';
}

/**
 * Checks and trims a list of timelines (from Kinetic Scenes, an import or a file): keeps only known fields, in range.
 *
 * @param mixed $list
 * @return array
 */
function upw_page_timelines_clean( $list ) {
	if ( ! is_array( $list ) ) {
		return array();
	}
	$props   = array(
		'x'       => array( -1000, 1000 ),
		'y'       => array( -1000, 1000 ),
		'scale'   => array( 0, 20 ),
		'rotate'  => array( -3600, 3600 ),
		'opacity' => array( 0, 1 ),
		'blur'    => array( 0, 50 ),
	);
	$out     = array();
	$ids     = array();
	foreach ( array_slice( array_values( $list ), 0, 50 ) as $tl ) {
		if ( ! is_array( $tl ) ) {
			continue;
		}
		$id = upw_page_timelines_key( isset( $tl['id'] ) ? $tl['id'] : '' );
		if ( '' === $id || isset( $ids[ $id ] ) ) {
			continue;
		}
		$ids[ $id ] = true;
		$trig       = ( isset( $tl['trigger'] ) && is_array( $tl['trigger'] ) ) ? $tl['trigger'] : array();
		$type       = ( isset( $trig['type'] ) && in_array( $trig['type'], array( 'load', 'in-view', 'hover', 'click', 'scroll' ), true ) ) ? $trig['type'] : 'load';
		$trigger    = array( 'type' => $type );
		$target     = upw_page_timelines_target( isset( $trig['target'] ) ? $trig['target'] : '' );
		if ( '' !== $target ) {
			$trigger['target'] = $target;
		}
		if ( isset( $trig['once'] ) && false === $trig['once'] ) {
			$trigger['once'] = false;
		}
		if ( isset( $trig['leave'] ) && 'stay' === $trig['leave'] ) {
			$trigger['leave'] = 'stay';
		}
		if ( ! empty( $trig['toggle'] ) ) {
			$trigger['toggle'] = true;
		}
		// Scroll (A5): pinned or not, for how many screen heights.
		if ( 'scroll' === $type && ! empty( $trig['pin'] ) ) {
			$trigger['pin'] = true;
		}
		foreach ( array( 'threshold' => array( 0, 1 ), 'delay' => array( 0, 30 ), 'length' => array( 0.5, 10 ) ) as $k => $r ) {
			$n = isset( $trig[ $k ] ) ? upw_page_timelines_num( $trig[ $k ], $r[0], $r[1] ) : null;
			if ( null !== $n ) {
				$trigger[ $k ] = $n;
			}
		}

		$tracks = array();
		$seen   = array();
		foreach ( array_slice( ( isset( $tl['tracks'] ) && is_array( $tl['tracks'] ) ) ? array_values( $tl['tracks'] ) : array(), 0, 100 ) as $tr ) {
			if ( ! is_array( $tr ) ) {
				continue;
			}
			$t_key = upw_page_timelines_target( isset( $tr['target'] ) ? $tr['target'] : '' );
			if ( '' === $t_key || isset( $seen[ $t_key ] ) ) {
				continue;
			}
			$seen[ $t_key ] = true;
			$frames         = array();
			foreach ( array_slice( ( isset( $tr['frames'] ) && is_array( $tr['frames'] ) ) ? array_values( $tr['frames'] ) : array(), 0, 200 ) as $f ) {
				$t = is_array( $f ) && isset( $f['t'] ) ? upw_page_timelines_num( $f['t'], 0, 120 ) : null;
				if ( null === $t ) {
					continue;
				}
				$frame = array( 't' => $t );
				foreach ( $props as $p => $r ) {
					$n = isset( $f[ $p ] ) ? upw_page_timelines_num( $f[ $p ], $r[0], $r[1] ) : null;
					if ( null !== $n ) {
						$frame[ $p ] = $n;
					}
				}
				if ( isset( $f['ease'] ) && is_string( $f['ease'] ) && preg_match( '/^[A-Za-z0-9_.-]{1,40}$/', $f['ease'] ) ) {
					$frame['ease'] = $f['ease'];
				}
				$e = isset( $f['e'] ) ? upw_page_timelines_css_ease( $f['e'] ) : '';
				if ( '' !== $e ) {
					$frame['e'] = $e;
				}
				$frames[] = $frame;
			}
			if ( ! $frames ) {
				continue;
			}
			usort( $frames, function ( $a, $b ) {
				return $a['t'] < $b['t'] ? -1 : ( $a['t'] > $b['t'] ? 1 : 0 );
			} );
			$track = array( 'target' => $t_key );
			// A class track: each element starts `stagger` seconds after the one before, in `order`.
			if ( '.' === $t_key[0] ) {
				$stagger = isset( $tr['stagger'] ) ? upw_page_timelines_num( $tr['stagger'], 0, 5 ) : null;
				if ( $stagger ) {
					$track['stagger'] = $stagger;
				}
				if ( isset( $tr['order'] ) && in_array( $tr['order'], array( 'last', 'center', 'random' ), true ) ) {
					$track['order'] = $tr['order'];
				}
			}
			if ( isset( $tr['label'] ) && is_string( $tr['label'] ) && '' !== trim( $tr['label'] ) ) {
				$track['label'] = mb_substr( sanitize_text_field( $tr['label'] ), 0, 60 );
			}
			if ( isset( $tr['ease'] ) && is_string( $tr['ease'] ) && preg_match( '/^[A-Za-z0-9_.-]{1,40}$/', $tr['ease'] ) ) {
				$track['ease'] = $tr['ease'];
			}
			$track['frames'] = $frames;
			$tracks[]        = $track;
		}

		$clean = array(
			'id'       => $id,
			'name'     => mb_substr( sanitize_text_field( isset( $tl['name'] ) && is_string( $tl['name'] ) ? $tl['name'] : '' ), 0, 80 ),
			'duration' => (float) ( upw_page_timelines_num( isset( $tl['duration'] ) ? $tl['duration'] : 2, 0.05, 120 ) ?: 2 ),
			'trigger'  => $trigger,
		);
		if ( '' === $clean['name'] ) {
			$clean['name'] = __( 'Page timeline', 'fw' );
		}
		$repeat = isset( $tl['repeat'] ) ? (int) upw_page_timelines_num( $tl['repeat'], -1, 100 ) : 0;
		if ( 0 !== $repeat ) {
			$clean['repeat'] = $repeat;
		}
		if ( ! empty( $tl['yoyo'] ) ) {
			$clean['yoyo'] = true;
		}
		// Runs on: only stored when not all three.
		if ( isset( $tl['runsOn'] ) && is_array( $tl['runsOn'] ) ) {
			$devices = array_values( array_intersect( array( 'desktop', 'tablet', 'phone' ), array_map( 'strval', $tl['runsOn'] ) ) );
			if ( count( $devices ) < 3 ) {
				$clean['runsOn'] = $devices;
			}
		}
		$clean['tracks'] = $tracks;
		$out[]           = $clean;
	}
	return $out;
}

/** The timelines saved on a page. */
function upw_page_timelines_get( $post_id ) {
	$raw = get_post_meta( (int) $post_id, UPW_PAGE_TIMELINES_META, true );
	if ( is_string( $raw ) && '' !== $raw ) {
		$raw = json_decode( $raw, true );
	}
	return is_array( $raw ) ? upw_page_timelines_clean( $raw ) : array();
}

/**
 * Saves a page's timelines (replacing what was there); an empty list removes them.
 * Stored as a JSON string so WordPress's slashing and serialising never touch the curves.
 *
 * @return array|WP_Error the list as saved
 */
function upw_page_timelines_save( $post_id, $list ) {
	$clean = upw_page_timelines_clean( $list );
	if ( ! $clean ) {
		delete_post_meta( (int) $post_id, UPW_PAGE_TIMELINES_META );
		return array();
	}
	$json = wp_json_encode( $clean );
	if ( ! is_string( $json ) || strlen( $json ) > UPW_PAGE_TIMELINES_MAX_BYTES ) {
		return new WP_Error( 'too_big', __( 'These timelines are too large to save. Remove some keyframes or tracks and try again.', 'fw' ) );
	}
	update_post_meta( (int) $post_id, UPW_PAGE_TIMELINES_META, wp_slash( $json ) );
	return $clean;
}

/**
 * The REST API (and so an agent or a script with an application password): `meta._upw_page_timelines` on
 * /wp/v2/pages/<id> (and posts), as the JSON string. Only people who can edit the post read or write it, and every
 * write goes through upw_page_timelines_clean(), like Save to page. An empty string or "[]" removes the timelines.
 */
add_action( 'init', function () {
	foreach ( array( 'page', 'post' ) as $type ) {
		register_post_meta( $type, UPW_PAGE_TIMELINES_META, array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'auth_callback'     => function ( $allowed, $key, $post_id ) {
				return current_user_can( 'edit_post', (int) $post_id );
			},
			'sanitize_callback' => function ( $value ) {
				$list  = is_string( $value ) ? json_decode( $value, true ) : $value; // update_metadata() has unslashed it
				$clean = upw_page_timelines_clean( is_array( $list ) ? $list : array() );
				return $clean ? wp_json_encode( $clean ) : '';
			},
		) );
	}
} );

/* ------------------------------------------------------------------ *
 * Site-wide timelines (Animation Creator 5h) and motion presets (5g).
 * ------------------------------------------------------------------ */

if ( ! defined( 'UPW_SITE_TIMELINES_OPTION' ) ) {
	define( 'UPW_SITE_TIMELINES_OPTION', 'upw_site_timelines' );
}
if ( ! defined( 'UPW_TIMELINE_PRESETS_OPTION' ) ) {
	define( 'UPW_TIMELINE_PRESETS_OPTION', 'upw_page_timeline_presets' );
}

/**
 * Site-wide timelines: played on every page. Only class targets mean the same thing on every page, so a site timeline
 * keeps only its class tracks (and a class, or nothing, as the element that starts it).
 */
function upw_site_timelines_clean( $list ) {
	$out = array();
	foreach ( upw_page_timelines_clean( $list ) as $tl ) {
		$tl['tracks'] = array_values( array_filter( $tl['tracks'], function ( $tr ) { return '.' === $tr['target'][0]; } ) );
		if ( ! empty( $tl['trigger']['target'] ) && '.' !== $tl['trigger']['target'][0] ) {
			unset( $tl['trigger']['target'] );
		}
		if ( $tl['tracks'] ) {
			$tl['site'] = true;
			$out[]      = $tl;
		}
	}
	return $out;
}

function upw_site_timelines_get() {
	$raw = get_option( UPW_SITE_TIMELINES_OPTION, '' );
	$raw = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : $raw;
	return is_array( $raw ) ? upw_site_timelines_clean( $raw ) : array();
}

/** @return array|WP_Error the list as saved */
function upw_site_timelines_save( $list ) {
	$clean = upw_site_timelines_clean( $list );
	if ( ! $clean ) {
		delete_option( UPW_SITE_TIMELINES_OPTION );
		return array();
	}
	$json = wp_json_encode( $clean );
	if ( ! is_string( $json ) || strlen( $json ) > UPW_PAGE_TIMELINES_MAX_BYTES ) {
		return new WP_Error( 'too_big', __( 'These site-wide timelines are too large to save.', 'fw' ) );
	}
	update_option( UPW_SITE_TIMELINES_OPTION, $json, true );
	return $clean;
}

/**
 * Motion presets: a named set of keyframes (and, for a class, a stagger and order) to start a track from.
 * { id, name, frames: [ … ], stagger?, order? }, at most 100.
 */
function upw_timeline_presets_clean( $list ) {
	$out = array();
	$ids = array();
	foreach ( array_slice( is_array( $list ) ? array_values( $list ) : array(), 0, 100 ) as $p ) {
		if ( ! is_array( $p ) ) {
			continue;
		}
		$id = upw_page_timelines_key( isset( $p['id'] ) ? $p['id'] : '' );
		if ( '' === $id || isset( $ids[ $id ] ) ) {
			continue;
		}
		// The frames go through the same check as a track's (a one-track timeline, cleaned and read back).
		$probe = upw_page_timelines_clean( array( array(
			'id'     => 'p',
			'tracks' => array( array(
				'target'  => '.preset',
				'frames'  => isset( $p['frames'] ) ? $p['frames'] : array(),
				'stagger' => isset( $p['stagger'] ) ? $p['stagger'] : 0,
				'order'   => isset( $p['order'] ) ? $p['order'] : '',
			) ),
		) ) );
		if ( empty( $probe[0]['tracks'][0] ) ) {
			continue;
		}
		$tr       = $probe[0]['tracks'][0];
		$ids[ $id ] = true;
		$preset   = array(
			'id'     => $id,
			'name'   => mb_substr( sanitize_text_field( isset( $p['name'] ) && is_string( $p['name'] ) ? $p['name'] : '' ), 0, 60 ) ?: __( 'Preset', 'fw' ),
			'frames' => $tr['frames'],
		);
		if ( ! empty( $tr['stagger'] ) ) {
			$preset['stagger'] = $tr['stagger'];
		}
		if ( ! empty( $tr['order'] ) ) {
			$preset['order'] = $tr['order'];
		}
		$out[] = $preset;
	}
	return $out;
}

function upw_timeline_presets_get() {
	$raw = get_option( UPW_TIMELINE_PRESETS_OPTION, '' );
	$raw = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : $raw;
	return is_array( $raw ) ? upw_timeline_presets_clean( $raw ) : array();
}

function upw_timeline_presets_save( $list ) {
	$clean = upw_timeline_presets_clean( $list );
	if ( $clean ) {
		update_option( UPW_TIMELINE_PRESETS_OPTION, wp_json_encode( $clean ), false );
	} else {
		delete_option( UPW_TIMELINE_PRESETS_OPTION );
	}
	return $clean;
}

/* ------------------------------------------------------------------ *
 * Visitors: only on a page that has timelines.
 * ------------------------------------------------------------------ */

/** The timelines of the page being viewed (null until known). */
function upw_page_timelines_here( $set = null ) {
	static $here = null;
	if ( null !== $set ) {
		$here = $set;
	}
	return $here;
}

add_action( 'wp', function () {
	if ( is_admin() ) {
		upw_page_timelines_here( array() );
		return;
	}
	// The page's own timelines (on a single page or post), then the site-wide ones (every page, 5h).
	$list    = array_merge( is_singular() ? upw_page_timelines_get( get_queried_object_id() ) : array(), upw_site_timelines_get() );
	$targets = array();
	foreach ( $list as $tl ) {
		if ( ! empty( $tl['trigger']['target'] ) && '.' !== $tl['trigger']['target'][0] ) {
			$targets[ $tl['trigger']['target'] ] = true;
		}
		foreach ( $tl['tracks'] as $tr ) {
			if ( '.' !== $tr['target'][0] ) {
				$targets[ $tr['target'] ] = true;
			}
		}
	}
	upw_page_timelines_here( array( 'list' => $list, 'targets' => $targets ) );
} );

/** Is this item key one the page's timelines use? */
function upw_page_timelines_targets( $uid ) {
	$here = upw_page_timelines_here();
	return '' !== (string) $uid && ! empty( $here['targets'][ (string) $uid ] );
}

// 1. Stamp the elements the timelines use: on the wrapper (plugin views), and on the output for views a theme
//    overrides without calling sc_build_wrapper_attr().
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	$uid = is_array( $atts ) && ! empty( $atts['unique_id'] ) ? (string) $atts['unique_id'] : '';
	if ( upw_page_timelines_targets( $uid ) && empty( $attr['data-upw-tl'] ) ) {
		$attr['data-upw-tl'] = $uid;
	}
	return $attr;
}, 997, 2 );

add_filter( 'sc_needs_wrapper', function ( $needs, $atts = array() ) {
	if ( $needs ) {
		return $needs;
	}
	$uid = is_array( $atts ) && ! empty( $atts['unique_id'] ) ? (string) $atts['unique_id'] : '';
	return upw_page_timelines_targets( $uid );
}, 10, 2 );

add_filter( 'do_shortcode_tag', function ( $output, $tag, $attr ) {
	if ( ! is_string( $output ) || '' === $output ) {
		return $output;
	}
	$uid = is_array( $attr ) && ! empty( $attr['unique_id'] ) ? (string) $attr['unique_id'] : '';
	if ( ! upw_page_timelines_targets( $uid ) || ! upw_page_timelines_key( $uid ) || preg_match( '/^\s*<[a-zA-Z][^>]*\bdata-upw-tl=/', $output ) ) {
		return $output;
	}
	$stamped = preg_replace( '/<([a-zA-Z][a-zA-Z0-9-]*)/', '<$1 data-upw-tl="' . esc_attr( $uid ) . '"', $output, 1 );
	return is_string( $stamped ) ? $stamped : $output;
}, 12, 3 );

/**
 * Each track's first moment as CSS, in <head>, so an element that rises or fades in is never seen at rest first
 * (the runtime loads in the footer). It applies only once a head script has marked the page (not on the Live
 * Editor's Edit canvas, which holds motion still), only on the devices the timeline runs on, not for visitors who ask
 * for reduced motion (unless the engine's setting says otherwise), and the mark is taken off again if the runtime has
 * not started after 4 seconds, so a blocked script can never leave an element hidden.
 */
function upw_page_timelines_first_css( $list, $reduce ) {
	$mq    = array(
		'desktop' => '(min-width: 992px)',
		'tablet'  => '(min-width: 768px) and (max-width: 991.98px)',
		'phone'   => '(max-width: 767.98px)',
	);
	$done  = array();
	$rules = '';
	foreach ( $list as $tl ) {
		$css = '';
		foreach ( $tl['tracks'] as $tr ) {
			if ( isset( $done[ $tr['target'] ] ) ) {
				continue; // the first timeline that moves an element sets how it starts
			}
			$v = array();
			foreach ( array( 'x', 'y', 'scale', 'rotate', 'opacity', 'blur' ) as $p ) {
				foreach ( $tr['frames'] as $f ) {
					if ( isset( $f[ $p ] ) ) {
						$v[ $p ] = (float) $f[ $p ];
						break;
					}
				}
			}
			$d = array();
			if ( isset( $v['x'] ) || isset( $v['y'] ) ) {
				$d[] = 'translate:' . ( isset( $v['x'] ) ? $v['x'] : 0 ) . '% ' . ( isset( $v['y'] ) ? $v['y'] : 0 ) . '%';
			}
			if ( isset( $v['rotate'] ) ) {
				$d[] = 'rotate:' . $v['rotate'] . 'deg';
			}
			if ( isset( $v['scale'] ) ) {
				$d[] = 'scale:' . max( 0, $v['scale'] );
			}
			if ( isset( $v['opacity'] ) ) {
				$d[] = 'opacity:' . max( 0, min( 1, $v['opacity'] ) );
			}
			if ( ! empty( $v['blur'] ) && $v['blur'] > 0.001 ) {
				$d[] = 'filter:blur(' . round( $v['blur'] * 10, 3 ) . 'px)';
			}
			if ( $d ) {
				$done[ $tr['target'] ] = true;
				$sel                   = '.' === $tr['target'][0] ? $tr['target'] : '[data-upw-tl="' . $tr['target'] . '"]';
				$css                  .= '.upw-tl-on ' . $sel . '{' . implode( ';', $d ) . '}';
			}
		}
		if ( '' === $css ) {
			continue;
		}
		if ( ! empty( $tl['runsOn'] ) ) {
			$q   = array_values( array_intersect_key( $mq, array_flip( $tl['runsOn'] ) ) );
			$css = $q ? '@media ' . implode( ',', $q ) . '{' . $css . '}' : '';
		}
		$rules .= $css;
	}
	if ( '' === $rules ) {
		return '';
	}
	return $reduce ? '@media (prefers-reduced-motion: no-preference){' . $rules . '}' : $rules;
}

add_action( 'wp_head', function () {
	$here = upw_page_timelines_here();
	if ( empty( $here['list'] ) ) {
		return;
	}
	$reduce = ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' );
	$css    = upw_page_timelines_first_css( $here['list'], $reduce );
	if ( '' === $css ) {
		return;
	}
	echo '<style id="upw-timelines-first">' . $css . "</style>\n";
	echo "<script>(function(){if(window.upwEditCanvas){return;}var h=document.documentElement;h.classList.add('upw-tl-on');setTimeout(function(){if(!window.upwTimeline){h.classList.remove('upw-tl-on');}},4000);})();</script>\n";
}, 20 );

// 2 + 3. The data and the runtime, in the footer.
add_action( 'wp_footer', function () {
	$here = upw_page_timelines_here();
	if ( empty( $here['list'] ) ) {
		return;
	}
	$ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( ! $ext ) {
		return;
	}
	$rel = '/modules/page-timelines/static/js/upw-timeline.js';
	wp_enqueue_script( 'upw-timeline', fw_min_uri( $ext->get_declared_URI( $rel ) ), function_exists( 'upw_anim_raf_handle' ) ? array( upw_anim_raf_handle() ) : array(), $ext->manifest->get_version() . '.' . (int) @filemtime( $ext->get_path( $rel ) ), true );
	$cfg = array(
		'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
	);
	wp_add_inline_script( 'upw-timeline', 'window.upwTimelineCfg=' . wp_json_encode( $cfg ) . ';', 'before' );
	// JSON in a script element: </script> can't close it early (wp_json_encode escapes "/" as "\/").
	echo '<script type="application/json" class="upw-timelines">' . wp_json_encode( $here['list'] ) . "</script>\n";
}, 5 );
