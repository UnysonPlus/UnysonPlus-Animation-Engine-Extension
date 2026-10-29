<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Scroll Timeline editor: per-page timing overrides (persistence layer).
 *
 * The Scroll Timeline's drag-to-retime writes here. An override is a new start / end for one scroll
 * animation, keyed by the element's stable `data-upw-g-key` (the `u{hash}` derived from its unique_id),
 * stored in a SINGLE post meta `_upw_timing_overrides` — never in the builder's page JSON. That keeps
 * it additive and safe: it survives the manual-edit guard and a re-import, and a reset simply drops the
 * key. The runtime (upw-gsap.js) reads the printed `window.upwTimingOverrides` and applies each start /
 * end to the matching element for EVERY visitor, so a retime ships for real.
 *
 * Save is admin-only (nonce + manage_options). Printing is public (the retimed timing must apply to
 * real visitors, not only in the editor).
 */

if ( ! defined( 'UPW_TIMING_META' ) ) {
	define( 'UPW_TIMING_META', '_upw_timing_overrides' );
}

if ( ! function_exists( 'upw_timing_sanitize_pos' ) ) :
	/**
	 * Clamp a start/end value to a GSAP-safe ScrollTrigger position string, e.g. `top 73.4%`,
	 * `bottom 40%`, `+=600`, `center center`. Returns '' for anything outside that shape.
	 */
	function upw_timing_sanitize_pos( $v ) {
		$v = trim( (string) $v );
		if ( $v === '' || strlen( $v ) > 40 ) {
			return '';
		}
		return preg_match( '/^[a-zA-Z0-9%.\-+= ]{1,40}$/', $v ) ? $v : '';
	}
endif;

if ( ! function_exists( 'upw_timing_sanitize_kf' ) ) :
	/**
	 * Clamp a Scroll Keyframes beat-positions override to a monotonic list of 2–8 floats in [0,1].
	 * Accepts an array or a comma-separated string ("0,0.35,1"). Returns [] if it isn't a usable list.
	 */
	function upw_timing_sanitize_kf( $raw ) {
		if ( is_string( $raw ) ) {
			$raw = ( trim( $raw ) === '' ) ? array() : explode( ',', $raw );
		}
		if ( ! is_array( $raw ) || count( $raw ) < 2 || count( $raw ) > 8 ) {
			return array();
		}
		$out  = array();
		$prev = 0.0;
		foreach ( $raw as $n ) {
			if ( ! is_numeric( $n ) ) { return array(); }
			$f = (float) $n;
			$f = $f < 0 ? 0 : ( $f > 1 ? 1 : $f );
			$f = max( $f, $prev );                 // keep non-decreasing so beats never cross
			$out[] = round( $f, 4 );
			$prev  = $f;
		}
		return $out;
	}
endif;

if ( ! function_exists( 'upw_timing_sanitize_kfs' ) ) :
	/**
	 * Clamp a Scroll Keyframes per-beat STATE override to a list (≤8) of `{ v:{prop:val}, ease } | null`.
	 * Accepts an array (from stored meta) or a JSON string (from the save POST). Each `v` prop is clamped
	 * to a sane range; `ease` must be one of the known curves. Returns [] if nothing usable remains.
	 */
	function upw_timing_sanitize_kfs( $raw ) {
		if ( is_string( $raw ) ) { $raw = json_decode( $raw, true ); }
		if ( ! is_array( $raw ) || count( $raw ) > 8 ) { return array(); }
		$ranges = array(
			'x' => array( -2000, 2000 ), 'y' => array( -2000, 2000 ), 'scale' => array( 0, 10 ),
			'rotation' => array( -3600, 3600 ), 'rotationX' => array( -360, 360 ), 'rotationY' => array( -360, 360 ),
			'opacity' => array( 0, 1 ), 'blur' => array( 0, 100 ),
		);
		$eases = array( 'linear', 'out', 'in', 'inout', 'back', 'sine' );
		$out   = array();
		$any   = false;
		foreach ( $raw as $beat ) {
			if ( ! is_array( $beat ) ) { $out[] = null; continue; }
			$row = array();
			if ( isset( $beat['v'] ) && is_array( $beat['v'] ) ) {
				$v = array();
				foreach ( $ranges as $k => $rg ) {
					if ( isset( $beat['v'][ $k ] ) && is_numeric( $beat['v'][ $k ] ) ) {
						$v[ $k ] = round( max( $rg[0], min( $rg[1], (float) $beat['v'][ $k ] ) ), 4 );
					}
				}
				if ( $v ) { $row['v'] = $v; }
			}
			if ( isset( $beat['ease'] ) && in_array( $beat['ease'], $eases, true ) ) { $row['ease'] = $beat['ease']; }
			if ( $row ) { $out[] = $row; $any = true; } else { $out[] = null; }
		}
		return $any ? $out : array();
	}
endif;

if ( ! function_exists( 'upw_timing_get_overrides' ) ) :
	/** Read the sanitized overrides map for a post: [ key => [ 'start'=>..., 'end'=>... ] ]. */
	function upw_timing_get_overrides( $post_id ) {
		$raw = get_post_meta( (int) $post_id, UPW_TIMING_META, true );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $key => $o ) {
			$k = sanitize_html_class( $key );
			if ( $k === '' || ! is_array( $o ) ) {
				continue;
			}
			$row = array();
			$s   = isset( $o['start'] ) ? upw_timing_sanitize_pos( $o['start'] ) : '';
			$e   = isset( $o['end'] ) ? upw_timing_sanitize_pos( $o['end'] ) : '';
			if ( $s !== '' ) { $row['start'] = $s; }
			if ( $e !== '' ) { $row['end'] = $e; }
			if ( isset( $o['kf'] ) ) {
				$kf = upw_timing_sanitize_kf( $o['kf'] );
				if ( $kf ) { $row['kf'] = $kf; }
			}
			if ( isset( $o['kfs'] ) ) {
				$kfs = upw_timing_sanitize_kfs( $o['kfs'] );
				if ( $kfs ) { $row['kfs'] = $kfs; }
			}
			if ( $row ) { $out[ $k ] = $row; }
		}
		return $out;
	}
endif;

/* Print window.upwTimingOverrides early, for everyone, when the current singular page has any. */
add_action( 'wp_head', function () {
	if ( is_admin() || ! is_singular() ) {
		return;
	}
	$id = get_queried_object_id();
	if ( ! $id ) {
		return;
	}
	$ov = upw_timing_get_overrides( $id );
	if ( ! $ov ) {
		return;
	}
	echo '<script id="upw-timing-overrides">window.upwTimingOverrides=' . wp_json_encode( $ov ) . ';</script>' . "\n";
}, 1 );

/* Admin-only AJAX: save or reset one element's timing override. */
add_action( 'wp_ajax_upw_save_timing', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'msg' => 'forbidden' ), 403 );
	}
	check_ajax_referer( 'upw_timing', 'nonce' );

	$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
	$key     = isset( $_POST['key'] ) ? sanitize_html_class( wp_unslash( $_POST['key'] ) ) : '';
	if ( ! $post_id || $key === '' || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( array( 'msg' => 'bad-request' ), 400 );
	}

	$overrides = upw_timing_get_overrides( $post_id );
	$reset     = ! empty( $_POST['reset'] );

	if ( $reset ) {
		unset( $overrides[ $key ] );
	} else {
		$start = isset( $_POST['start'] ) ? upw_timing_sanitize_pos( wp_unslash( $_POST['start'] ) ) : '';
		$end   = isset( $_POST['end'] ) ? upw_timing_sanitize_pos( wp_unslash( $_POST['end'] ) ) : '';
		$kf    = isset( $_POST['kf'] ) ? upw_timing_sanitize_kf( wp_unslash( $_POST['kf'] ) ) : array();
		$kfs   = isset( $_POST['kfs'] ) ? upw_timing_sanitize_kfs( wp_unslash( $_POST['kfs'] ) ) : array();
		$row   = array();
		if ( $start !== '' ) { $row['start'] = $start; }
		if ( $end !== '' ) { $row['end'] = $end; }
		if ( $kf ) { $row['kf'] = $kf; }
		if ( $kfs ) { $row['kfs'] = $kfs; }
		if ( $row ) {
			$overrides[ $key ] = $row;
		} else {
			unset( $overrides[ $key ] );
		}
	}

	if ( $overrides ) {
		update_post_meta( $post_id, UPW_TIMING_META, $overrides );
	} else {
		delete_post_meta( $post_id, UPW_TIMING_META );
	}

	wp_send_json_success( array( 'overrides' => $overrides ) );
} );
