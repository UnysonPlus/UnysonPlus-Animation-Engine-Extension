<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Animation Diagnostics.
 *
 * A Tools → "Animation Diagnostics" admin page (and reusable functions) to INSPECT and REPAIR the
 * per-element animation values a page-builder page has saved — without guessing. For a given post it
 * lists every builder element and which animation modules are active on it (with the raw saved value),
 * flags the "all modules activated" signature, and can RESET a page's animations back to off.
 *
 * Also a WP-CLI-friendly API:
 *   upw_anim_diagnose_post( $post_id )  → structured report
 *   upw_anim_reset_post( $post_id )     → reset every element's animations to their off/none default
 */

if ( ! function_exists( 'upw_anim_field_defs' ) ) :
	/** picker id + off value per animation field, from the live field definitions. Cached per request. */
	function upw_anim_field_defs() {
		static $defs = null;
		if ( $defs !== null ) { return $defs; }
		$defs = array();
		if ( ! function_exists( 'sc_get_animation_fields' ) ) { return $defs; }
		$stack  = sc_get_animation_fields();
		$fields = isset( $stack['animation_stack']['options'] ) && is_array( $stack['animation_stack']['options'] )
			? $stack['animation_stack']['options'] : array();
		foreach ( $fields as $fid => $field ) {
			if ( ! is_array( $field ) || ( $field['type'] ?? '' ) !== 'multi-picker' || empty( $field['picker'] ) ) { continue; }
			$pk = array_key_first( $field['picker'] );
			$defs[ $fid ] = array(
				'picker' => $pk,
				'off'    => isset( $field['value'][ $pk ] ) ? (string) $field['value'][ $pk ] : 'none',
				'label'  => ( isset( $field['label'] ) && $field['label'] ) ? (string) $field['label'] : $fid,
			);
		}
		return $defs;
	}
endif;

if ( ! function_exists( 'upw_anim_pb_key' ) ) :
	/** The page-builder option key (for fw_get_db_post_option / fw_set_db_post_option). */
	function upw_anim_pb_key() {
		$pb = function_exists( 'fw_ext' ) ? fw_ext( 'page-builder' ) : null;
		if ( ! $pb ) { return null; }
		try {
			$r = new ReflectionMethod( $pb, 'get_option_key' );
			$r->setAccessible( true );
			return $r->invoke( $pb );
		} catch ( Exception $e ) { return 'page-builder'; }
	}
endif;

if ( ! function_exists( 'upw_anim_diagnose_post' ) ) :
	/**
	 * @return array { key: string|null, elements: [ { type, active: [ 'fid'=>value, … ], count } ], total_active, all_on_signature: bool }
	 */
	function upw_anim_diagnose_post( $post_id ) {
		$out = array( 'key' => null, 'elements' => array(), 'total_active' => 0, 'all_on_signature' => false );
		$key = upw_anim_pb_key();
		if ( ! $key || ! function_exists( 'fw_get_db_post_option' ) ) { return $out; }
		$out['key'] = $key;
		$val  = fw_get_db_post_option( (int) $post_id, $key );
		$json = ( is_array( $val ) && isset( $val['json'] ) ) ? $val['json'] : '';
		$tree = $json ? json_decode( $json, true ) : null;
		if ( ! is_array( $tree ) ) { return $out; }
		$defs = upw_anim_field_defs();

		$walk = function ( $n ) use ( &$walk, &$out, $defs ) {
			if ( ! is_array( $n ) ) { return; }
			if ( isset( $n['type'], $n['atts'] ) && is_array( $n['atts'] ) ) {
				$active = array();
				foreach ( $defs as $fid => $d ) {
					if ( ! isset( $n['atts'][ $fid ] ) || ! is_array( $n['atts'][ $fid ] ) ) { continue; }
					$pv = isset( $n['atts'][ $fid ][ $d['picker'] ] ) ? $n['atts'][ $fid ][ $d['picker'] ] : $d['off'];
					if ( is_string( $pv ) && $pv !== $d['off'] && $pv !== '' && $pv !== 'none' && $pv !== 'off' ) {
						$active[ $fid ] = $pv;
					}
				}
				if ( $active ) {
					$out['elements'][] = array( 'type' => (string) $n['type'], 'active' => $active, 'count' => count( $active ) );
					$out['total_active'] += count( $active );
					// Signature of the save bug: a single element with (almost) every module turned on.
					if ( count( $active ) >= 8 ) { $out['all_on_signature'] = true; }
				}
			}
			foreach ( $n as $v ) { if ( is_array( $v ) ) { $walk( $v ); } }
		};
		$walk( $tree );
		return $out;
	}
endif;

if ( ! function_exists( 'upw_anim_reset_post' ) ) :
	/** Reset EVERY element's animation values to their off/none default. Returns count of atts reset. */
	function upw_anim_reset_post( $post_id ) {
		$key = upw_anim_pb_key();
		if ( ! $key || ! function_exists( 'fw_get_db_post_option' ) || ! function_exists( 'fw_set_db_post_option' ) ) { return 0; }
		$val  = fw_get_db_post_option( (int) $post_id, $key );
		$json = ( is_array( $val ) && isset( $val['json'] ) ) ? $val['json'] : '';
		$tree = $json ? json_decode( $json, true ) : null;
		if ( ! is_array( $tree ) ) { return 0; }
		$defs = upw_anim_field_defs();
		$reset = 0;

		$walk = function ( &$n ) use ( &$walk, $defs, &$reset ) {
			if ( ! is_array( $n ) ) { return; }
			if ( isset( $n['atts'] ) && is_array( $n['atts'] ) ) {
				foreach ( $defs as $fid => $d ) {
					if ( isset( $n['atts'][ $fid ] ) ) {
						$n['atts'][ $fid ] = array( $d['picker'] => $d['off'] );
						$reset++;
					}
				}
			}
			foreach ( $n as &$v ) { if ( is_array( $v ) ) { $walk( $v ); } }
			unset( $v );
		};
		$walk( $tree );
		if ( $reset > 0 ) {
			$val['json'] = json_encode( $tree );
			fw_set_db_post_option( (int) $post_id, $key, $val );
		}
		return $reset;
	}
endif;

/* ----------------------------------------------------------------------------------------------- *
 * Admin page: Tools → Animation Diagnostics
 * ----------------------------------------------------------------------------------------------- */
add_action( 'admin_menu', function () {
	add_management_page(
		__( 'Animation Diagnostics', 'fw' ),
		__( 'Animation Diagnostics', 'fw' ),
		'manage_options',
		'upw-anim-diagnostics',
		'upw_anim_diagnostics_render_page'
	);
} );

if ( ! function_exists( 'upw_anim_diagnostics_render_page' ) ) :
	function upw_anim_diagnostics_render_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$post_id = isset( $_REQUEST['pid'] ) ? (int) $_REQUEST['pid'] : 0;
		$notice  = '';

		// Reset action.
		if ( $post_id && isset( $_POST['upw_anim_reset'] ) && check_admin_referer( 'upw_anim_reset_' . $post_id ) ) {
			$n = upw_anim_reset_post( $post_id );
			$notice = sprintf( __( 'Reset %d animation value(s) on post #%d to off.', 'fw' ), $n, $post_id );
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'Animation Diagnostics', 'fw' ) . '</h1>';
		echo '<p>' . esc_html__( 'Inspect which animation modules a page-builder page has saved on each element — and reset them if a save went wrong (e.g. every module turned on at once).', 'fw' ) . '</p>';
		if ( $notice ) { echo '<div class="notice notice-success"><p>' . esc_html( $notice ) . '</p></div>'; }

		echo '<form method="get" style="margin:14px 0;">';
		echo '<input type="hidden" name="page" value="upw-anim-diagnostics">';
		echo '<label>' . esc_html__( 'Post / Page ID:', 'fw' ) . ' <input type="number" name="pid" value="' . esc_attr( $post_id ?: '' ) . '" min="1" style="width:110px;"></label> ';
		echo '<button class="button button-primary">' . esc_html__( 'Inspect', 'fw' ) . '</button>';
		echo '</form>';

		if ( ! $post_id ) { echo '</div>'; return; }

		$rep = upw_anim_diagnose_post( $post_id );
		$title = get_the_title( $post_id );
		echo '<h2>' . esc_html( sprintf( __( 'Post #%d — %s', 'fw' ), $post_id, $title ?: '(no title)' ) ) . '</h2>';

		if ( $rep['all_on_signature'] ) {
			echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Signature detected:', 'fw' )
				. '</strong> ' . esc_html__( 'an element has 8+ modules active — that is the "every module turned on after one save" bug. Reset below, then re-add just the animation you want.', 'fw' ) . '</p></div>';
		}

		if ( empty( $rep['elements'] ) ) {
			echo '<p>' . esc_html__( 'No active animations on this page. ✓', 'fw' ) . '</p>';
		} else {
			echo '<table class="widefat striped" style="max-width:760px;"><thead><tr><th>' . esc_html__( 'Element', 'fw' )
				. '</th><th>' . esc_html__( 'Active modules', 'fw' ) . '</th></tr></thead><tbody>';
			foreach ( $rep['elements'] as $el ) {
				$parts = array();
				foreach ( $el['active'] as $fid => $v ) { $parts[] = esc_html( $fid . ' = ' . $v ); }
				echo '<tr><td><code>' . esc_html( $el['type'] ) . '</code></td><td>'
					. ( $el['count'] >= 8 ? '<span style="color:#b32d2e;font-weight:600;">(' . (int) $el['count'] . ') </span>' : '' )
					. implode( ', ', $parts ) . '</td></tr>';
			}
			echo '</tbody></table>';
			echo '<p style="margin-top:14px;"><strong>' . esc_html( sprintf( __( 'Total active: %d', 'fw' ), $rep['total_active'] ) ) . '</strong></p>';
			echo '<form method="post" onsubmit="return confirm(\'' . esc_js( __( 'Reset ALL animations on this page to off?', 'fw' ) ) . '\');">';
			echo '<input type="hidden" name="pid" value="' . esc_attr( $post_id ) . '">';
			wp_nonce_field( 'upw_anim_reset_' . $post_id );
			echo '<button name="upw_anim_reset" value="1" class="button button-secondary">' . esc_html__( 'Reset all animations on this page', 'fw' ) . '</button>';
			echo '</form>';
		}
		echo '</div>';
	}
endif;
