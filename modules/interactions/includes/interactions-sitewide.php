<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Site-wide Interactions (roadmap I5, reuse across pages).
 *
 * An Interaction set to "Runs for: every element that matches …" can also be switched to "Use on every page".
 * It stays where it was made (its source element, edited there like any other), and when the source page is
 * saved it is collected into the option `upw_ix_sitewide` ( post_id => [ compiled interaction, … ] ). Every
 * front-end page then wires those interactions onto its own matching elements (window.upwIxSite), except the
 * source page itself, which already carries them in its markup. Only published sources count. Theme Settings →
 * Site-wide UX → Interactions lists them, with links back to their source pages.
 */

if ( ! defined( 'UPW_IX_PB_META' ) ) {
	define( 'UPW_IX_PB_META', 'fw:opt:ext:pb:page-builder:json' );
}

if ( ! function_exists( 'upw_ix_sitewide_collect' ) ) :
	/** Re-read one post's builder content and store (or drop) its site-wide interactions. */
	function upw_ix_sitewide_collect( $post_id ) {
		$post_id = (int) $post_id;
		if ( ! $post_id || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$found = array();
		$json  = get_post_meta( $post_id, UPW_IX_PB_META, true );
		$tree  = is_string( $json ) ? json_decode( $json, true ) : ( is_array( $json ) ? $json : null );
		if ( is_array( $tree ) ) {
			$walk = function ( $items ) use ( &$walk, &$found ) {
				foreach ( (array) $items as $it ) {
					if ( ! is_array( $it ) ) {
						continue;
					}
					if ( ! empty( $it['atts'] ) && is_array( $it['atts'] ) ) {
						foreach ( upw_interactions_of( $it['atts'] ) as $cfg ) {
							if ( ! empty( $cfg['site'] ) && ! empty( $cfg['on'] ) ) {
								$cfg['src'] = isset( $it['shortcode'] ) ? (string) $it['shortcode'] : (string) ( isset( $it['type'] ) ? $it['type'] : '' );
								$found[]    = $cfg;
							}
						}
					}
					if ( ! empty( $it['_items'] ) ) {
						$walk( $it['_items'] );
					}
				}
			};
			$walk( $tree );
		}
		$all = get_option( 'upw_ix_sitewide', array() );
		$all = is_array( $all ) ? $all : array();
		if ( $found ) {
			$all[ $post_id ] = $found;
		} else {
			unset( $all[ $post_id ] );
		}
		update_option( 'upw_ix_sitewide', $all, true );
	}
endif;

/* The builder content changed (backend builder, Live Editor, importers all write this meta). */
$upw_ix_meta_hook = function ( $meta_id, $post_id, $meta_key ) {
	if ( $meta_key === UPW_IX_PB_META ) {
		upw_ix_sitewide_collect( $post_id );
	}
};
add_action( 'added_post_meta', $upw_ix_meta_hook, 10, 3 );
add_action( 'updated_post_meta', $upw_ix_meta_hook, 10, 3 );
unset( $upw_ix_meta_hook );
add_action( 'deleted_post', function ( $post_id ) {
	$all = get_option( 'upw_ix_sitewide', array() );
	if ( is_array( $all ) && isset( $all[ $post_id ] ) ) {
		unset( $all[ $post_id ] );
		update_option( 'upw_ix_sitewide', $all, true );
	}
} );

if ( ! function_exists( 'upw_ix_sitewide_for_request' ) ) :
	/** The site-wide interactions this front-end page should wire (published sources, not this page's own). */
	function upw_ix_sitewide_for_request() {
		static $list = null;
		if ( null !== $list ) {
			return $list;
		}
		$list = array();
		$all  = get_option( 'upw_ix_sitewide', array() );
		if ( ! is_array( $all ) || ! $all ) {
			return $list;
		}
		$here = is_singular() ? (int) get_queried_object_id() : 0;
		foreach ( $all as $post_id => $cfgs ) {
			if ( (int) $post_id === $here || get_post_status( (int) $post_id ) !== 'publish' ) {
				continue;
			}
			foreach ( (array) $cfgs as $cfg ) {
				unset( $cfg['src'] );
				$list[] = $cfg;
			}
		}
		return $list;
	}
endif;

/* Front end: record the runtime for the footer loader, and hand it the list before the script loads. */
add_action( 'wp', function () {
	if ( is_admin() || ! upw_ix_sitewide_for_request() || ! function_exists( 'upw_anim_use_asset' ) ) {
		return;
	}
	upw_anim_use_asset( 'interactions', 'site' );
} );
add_action( 'wp_footer', function () {
	if ( is_admin() ) {
		return;
	}
	$list = upw_ix_sitewide_for_request();
	if ( $list ) {
		echo '<script id="upw-ix-site">window.upwIxSite=' . wp_json_encode( $list ) . ';</script>' . "\n";
	}
}, 1 );

/* Theme Settings → Site-wide UX → Interactions: what runs on every page, and where each one is made. */
add_filter( 'upw_anim_engine_module_tabs', function ( $tabs ) {
	$all  = get_option( 'upw_ix_sitewide', array() );
	$rows = '';
	$labels = array( 'hover' => __( 'On hover', 'fw' ), 'click' => __( 'On click', 'fw' ), 'load' => __( 'On page load', 'fw' ), 'view' => __( 'In view', 'fw' ), 'past' => __( 'Scrolled past', 'fw' ) );
	foreach ( is_array( $all ) ? $all : array() as $post_id => $cfgs ) {
		$title  = get_the_title( (int) $post_id );
		$status = get_post_status( (int) $post_id );
		$edit   = get_edit_post_link( (int) $post_id, 'raw' );
		$live   = add_query_arg( 'fw-live-editor', 1, get_permalink( (int) $post_id ) );
		foreach ( (array) $cfgs as $cfg ) {
			$rows .= '<tr><td><code>' . esc_html( isset( $cfg['on'] ) ? $cfg['on'] : '' ) . '</code></td><td>'
				. esc_html( isset( $labels[ $cfg['t'] ] ) ? $labels[ $cfg['t'] ] : $cfg['t'] ) . '</td><td>'
				. esc_html( $title !== '' ? $title : '#' . (int) $post_id )
				. ( $status !== 'publish' ? ' <em>(' . esc_html__( 'not published, so not running', 'fw' ) . ')</em>' : '' )
				. '</td><td><a href="' . esc_url( $live ) . '" target="_blank" rel="noopener">' . esc_html__( 'Live Editor', 'fw' ) . '</a> · <a href="' . esc_url( (string) $edit ) . '">' . esc_html__( 'Builder', 'fw' ) . '</a></td></tr>';
		}
	}
	$html = '<p style="margin:0 0 12px;max-width:760px;line-height:1.55">'
		. esc_html__( 'Interactions that run on every page. Each one is made and edited on its source element: open its Interactions card, set “Runs for” to “Every element on the page that matches…”, and switch on “Use on every page”. Switch it off there to stop it.', 'fw' ) . '</p>';
	$html .= $rows
		? '<table class="widefat striped" style="max-width:900px"><thead><tr><th>' . esc_html__( 'Runs for', 'fw' ) . '</th><th>' . esc_html__( 'Trigger', 'fw' ) . '</th><th>' . esc_html__( 'Made on', 'fw' ) . '</th><th>' . esc_html__( 'Edit', 'fw' ) . '</th></tr></thead><tbody>' . $rows . '</tbody></table>'
		: '<p><em>' . esc_html__( 'None yet.', 'fw' ) . '</em></p>';

	$tabs['interactions'] = array(
		'title'   => __( 'Interactions', 'fw' ),
		'type'    => 'tab',
		'options' => array(
			'ix_sitewide_box' => array(
				'title'   => __( 'Site-wide Interactions', 'fw' ),
				'type'    => 'box',
				'options' => array(
					'ix_sitewide_list' => array( 'type' => 'html', 'label' => false, 'html' => $html ),
				),
			),
		),
	);
	return $tabs;
} );
