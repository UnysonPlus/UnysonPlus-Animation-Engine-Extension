<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Saved Interactions (roadmap 6g): a library of named Interactions, used by any element (Interactions card →
 * "A saved Interaction"), live-linked: saving a preset again under the same name changes it everywhere it is used.
 *
 * Saved from the Live Editor (Interaction timeline → Save as preset), listed in Theme Settings → Site-wide UX →
 * Interactions, with Import / Export: a JSON file (every preset, every setting) and a CSV (one row per preset,
 * its full value as JSON in a column), both of which import back unchanged.
 *
 * Option `upw_ix_presets`: [ id => [ 'name' => …, 'value' => <a saved upw_interactions value>, 'updated' => time ] ].
 */

if ( ! function_exists( 'upw_ix_presets' ) ) :
	function upw_ix_presets() {
		$all = get_option( 'upw_ix_presets', array() );
		return is_array( $all ) ? $all : array();
	}
endif;

if ( ! function_exists( 'upw_ix_preset_get' ) ) :
	/** A preset's saved Interaction value, or null. */
	function upw_ix_preset_get( $id ) {
		$all = upw_ix_presets();
		return ( $id !== '' && isset( $all[ $id ]['value'] ) && is_array( $all[ $id ]['value'] ) ) ? $all[ $id ]['value'] : null;
	}
endif;

if ( ! function_exists( 'upw_ix_preset_choices' ) ) :
	function upw_ix_preset_choices() {
		$out = array( '' => __( '— Choose a saved Interaction —', 'fw' ) );
		foreach ( upw_ix_presets() as $id => $p ) {
			$out[ $id ] = isset( $p['name'] ) ? (string) $p['name'] : $id;
		}
		return $out;
	}
endif;

if ( ! function_exists( 'upw_ix_preset_clean' ) ) :
	/**
	 * A value fit to be a preset: an Interactions value that compiles to something, and not itself a reference to
	 * a saved one. Returns the value, or null.
	 */
	function upw_ix_preset_clean( $value ) {
		if ( ! is_array( $value ) || empty( $value['mode'] ) || $value['mode'] === 'saved' || ! in_array( $value['mode'], upw_interactions_modes(), true ) ) {
			return null;
		}
		$mode = $value['mode'];
		$keep = array( 'mode' => $mode, $mode => isset( $value[ $mode ] ) && is_array( $value[ $mode ] ) ? $value[ $mode ] : array() );
		return upw_interactions_compile( $keep ) ? $keep : null;
	}
endif;

if ( ! function_exists( 'upw_ix_preset_save' ) ) :
	/** Save (or replace) a preset by name. Returns its id, or '' when the value is not a usable Interaction. */
	function upw_ix_preset_save( $name, $value ) {
		$name  = trim( wp_strip_all_tags( (string) $name ) );
		$value = upw_ix_preset_clean( $value );
		$id    = sanitize_title( $name );
		if ( $id === '' || ! $value ) {
			return '';
		}
		$all        = upw_ix_presets();
		$all[ $id ] = array( 'name' => substr( $name, 0, 80 ), 'value' => $value, 'updated' => time() );
		update_option( 'upw_ix_presets', $all, false );
		return $id;
	}
endif;

if ( ! function_exists( 'upw_ix_presets_import' ) ) :
	/**
	 * Merge presets from an export (the JSON file, or the CSV's rows decoded). Same id = replaced.
	 * @return int how many were imported
	 */
	function upw_ix_presets_import( $rows ) {
		$n = 0;
		foreach ( (array) $rows as $id => $p ) {
			if ( ! is_array( $p ) || empty( $p['name'] ) || empty( $p['value'] ) ) {
				continue;
			}
			if ( upw_ix_preset_save( $p['name'], $p['value'] ) !== '' ) {
				$n++;
			}
		}
		return $n;
	}
endif;

/* ---- AJAX: save (Live Editor) and delete (Theme Settings). ---- */
add_action( 'wp_ajax_upw_ix_preset_save', function () {
	check_ajax_referer( 'upw_ix_presets', 'nonce' );
	if ( ! current_user_can( 'edit_pages' ) ) {
		wp_send_json_error( array( 'message' => __( 'You are not allowed to save presets.', 'fw' ) ), 403 );
	}
	$value = json_decode( wp_unslash( (string) ( $_POST['value'] ?? '' ) ), true );
	$id    = upw_ix_preset_save( wp_unslash( (string) ( $_POST['name'] ?? '' ) ), $value );
	if ( $id === '' ) {
		wp_send_json_error( array( 'message' => __( 'Give it a name, and make sure the Interaction has a step that does something.', 'fw' ) ) );
	}
	wp_send_json_success( array( 'id' => $id, 'name' => upw_ix_presets()[ $id ]['name'] ) );
} );

add_action( 'wp_ajax_upw_ix_preset_delete', function () {
	check_ajax_referer( 'upw_ix_presets', 'nonce' );
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_send_json_error( null, 403 );
	}
	$id  = sanitize_title( wp_unslash( (string) ( $_POST['id'] ?? '' ) ) );
	$all = upw_ix_presets();
	unset( $all[ $id ] );
	update_option( 'upw_ix_presets', $all, false );
	wp_send_json_success();
} );

/* ---- Import / Export. ---- */
add_action( 'wp_ajax_upw_ix_presets_export', function () {
	check_ajax_referer( 'upw_ix_presets', 'nonce' );
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( '', 403 );
	}
	$all    = upw_ix_presets();
	$format = ( isset( $_GET['format'] ) && $_GET['format'] === 'csv' ) ? 'csv' : 'json';
	nocache_headers();
	if ( $format === 'json' ) {
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="saved-interactions.json"' );
		$out = array( 'type' => 'unysonplus-saved-interactions', 'version' => 1, 'presets' => array() );
		foreach ( $all as $id => $p ) {
			$out['presets'][ $id ] = array( 'name' => $p['name'], 'value' => $p['value'] );
		}
		echo wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="saved-interactions.csv"' );
	$fh = fopen( 'php://output', 'w' );
	fputcsv( $fh, array( 'id', 'name', 'trigger', 'steps', 'value' ) );
	foreach ( $all as $id => $p ) {
		$mode  = isset( $p['value']['mode'] ) ? $p['value']['mode'] : '';
		$steps = 0;
		foreach ( (array) ( $p['value'][ $mode ]['steps'] ?? array() ) as $st ) {
			if ( is_array( $st ) && ( $st['target'] ?? 'none' ) !== 'none' ) {
				$steps++;
			}
		}
		fputcsv( $fh, array( $id, $p['name'], $mode, $steps, wp_json_encode( $p['value'] ) ) );
	}
	fclose( $fh );
	exit;
} );

add_action( 'wp_ajax_upw_ix_presets_import', function () {
	check_ajax_referer( 'upw_ix_presets', 'nonce' );
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_send_json_error( null, 403 );
	}
	$file = isset( $_FILES['file']['tmp_name'] ) ? (string) $_FILES['file']['tmp_name'] : '';
	$raw  = ( $file && is_uploaded_file( $file ) ) ? (string) file_get_contents( $file ) : '';
	$raw  = preg_replace( '/^\xEF\xBB\xBF/', '', $raw ); // a spreadsheet's byte-order mark
	$rows = array();
	$json = json_decode( $raw, true );
	if ( is_array( $json ) && isset( $json['presets'] ) && is_array( $json['presets'] ) ) {
		$rows = $json['presets'];
	} elseif ( $raw !== '' ) { // CSV: id, name, trigger, steps, value (the value column is the whole Interaction as JSON)
		$fh = fopen( 'php://memory', 'r+' );
		fwrite( $fh, $raw );
		rewind( $fh );
		$head = fgetcsv( $fh );
		$col  = is_array( $head ) ? array_flip( array_map( 'trim', $head ) ) : array();
		while ( isset( $col['name'], $col['value'] ) && ( $r = fgetcsv( $fh ) ) !== false ) {
			$rows[] = array( 'name' => $r[ $col['name'] ] ?? '', 'value' => json_decode( (string) ( $r[ $col['value'] ] ?? '' ), true ) );
		}
		fclose( $fh );
	}
	$n = upw_ix_presets_import( $rows );
	if ( ! $n ) {
		wp_send_json_error( array( 'message' => __( 'No saved Interactions found in that file. Use a file made by Export.', 'fw' ) ) );
	}
	/* translators: %d: number of presets */
	wp_send_json_success( array( 'message' => sprintf( _n( '%d saved Interaction imported.', '%d saved Interactions imported.', $n, 'fw' ), $n ) ) );
} );

/* ---- Theme Settings → Site-wide UX → Interactions: the library, with Import / Export. ---- */
add_filter( 'upw_anim_engine_module_tabs', function ( $tabs ) {
	if ( ! isset( $tabs['interactions']['options'] ) ) {
		return $tabs;
	}
	$nonce  = wp_create_nonce( 'upw_ix_presets' );
	$ajax   = admin_url( 'admin-ajax.php' );
	$labels = array( 'hover' => __( 'On hover', 'fw' ), 'click' => __( 'On click', 'fw' ), 'load' => __( 'On page load', 'fw' ), 'view' => __( 'In view', 'fw' ), 'past' => __( 'Scrolled past', 'fw' ), 'mouse' => __( 'Mouse move', 'fw' ) );
	$rows   = '';
	foreach ( upw_ix_presets() as $id => $p ) {
		$mode  = isset( $p['value']['mode'] ) ? $p['value']['mode'] : '';
		$rows .= '<tr data-id="' . esc_attr( $id ) . '"><td><strong>' . esc_html( $p['name'] ) . '</strong></td><td>' . esc_html( $labels[ $mode ] ?? $mode ) . '</td><td>'
			. esc_html( ! empty( $p['updated'] ) ? date_i18n( get_option( 'date_format' ), (int) $p['updated'] ) : '' ) . '</td><td><button type="button" class="button-link upw-ixp-del" style="color:#b32d2e">' . esc_html__( 'Delete', 'fw' ) . '</button></td></tr>';
	}
	$html  = '<div class="upw-ixp" data-ajax="' . esc_attr( $ajax ) . '" data-nonce="' . esc_attr( $nonce ) . '">';
	$html .= '<p style="margin:0 0 12px;max-width:760px;line-height:1.55">' . esc_html__( 'Interactions saved to reuse on any element: in the Live Editor, select an element with an Interaction, open its Interaction timeline and click “Save as preset”. Then on any element, Interactions → “A saved Interaction”. Saving again under the same name changes it everywhere it is used.', 'fw' ) . '</p>';
	$html .= $rows
		? '<table class="widefat striped" style="max-width:900px"><thead><tr><th>' . esc_html__( 'Name', 'fw' ) . '</th><th>' . esc_html__( 'Trigger', 'fw' ) . '</th><th>' . esc_html__( 'Saved', 'fw' ) . '</th><th></th></tr></thead><tbody>' . $rows . '</tbody></table>'
		: '<p><em>' . esc_html__( 'None saved yet.', 'fw' ) . '</em></p>';
	$html .= '<h4 style="margin:18px 0 8px">' . esc_html__( 'Import / Export', 'fw' ) . '</h4>';
	$html .= '<p style="margin:0 0 10px;color:#646970">' . esc_html__( 'Move saved Interactions to another site: Export a file here, Import it there. Both files import back unchanged; an Interaction with the same name is replaced.', 'fw' ) . '</p>';
	$html .= '<p><a class="button" href="' . esc_url( add_query_arg( array( 'action' => 'upw_ix_presets_export', 'format' => 'json', 'nonce' => $nonce ), $ajax ) ) . '">' . esc_html__( 'Export (JSON, every setting)', 'fw' ) . '</a> '
		. '<a class="button" href="' . esc_url( add_query_arg( array( 'action' => 'upw_ix_presets_export', 'format' => 'csv', 'nonce' => $nonce ), $ajax ) ) . '">' . esc_html__( 'Export (CSV spreadsheet)', 'fw' ) . '</a></p>';
	$html .= '<p><input type="file" class="upw-ixp-file" accept=".json,.csv,application/json,text/csv"> <button type="button" class="button upw-ixp-import">' . esc_html__( 'Import', 'fw' ) . '</button> <span class="upw-ixp-msg" role="status"></span></p>';
	$html .= '</div>';
	// Delete and Import are wired by one listener in the admin footer (the tab's HTML is injected later, so an
	// inline script here would not run reliably).

	$tabs['interactions']['options']['ix_presets_box'] = array(
		'title'   => __( 'Saved Interactions', 'fw' ),
		'type'    => 'box',
		'options' => array(
			'ix_presets_list' => array( 'type' => 'html', 'label' => false, 'html' => $html ),
		),
	);
	return $tabs;
}, 20 );

/* The Saved Interactions box's buttons: one delegated listener on the Theme Settings screen. */
add_action( 'admin_footer', function () {
	if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'fw-settings' ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	?>
	<script>
	( function () {
		document.addEventListener( 'click', function ( e ) {
			var r = e.target.closest && e.target.closest( '.upw-ixp' );
			if ( ! r ) { return; }
			var A = r.getAttribute( 'data-ajax' ), N = r.getAttribute( 'data-nonce' ), M = r.querySelector( '.upw-ixp-msg' );
			var del = e.target.closest( '.upw-ixp-del' );
			if ( del ) {
				e.preventDefault();
				var tr = del.closest( 'tr' );
				if ( ! window.confirm( <?php echo wp_json_encode( __( 'Delete this saved Interaction? Elements that use it stop playing it.', 'fw' ) ); ?> ) ) { return; }
				var f = new FormData();
				f.append( 'action', 'upw_ix_preset_delete' ); f.append( 'nonce', N ); f.append( 'id', tr.getAttribute( 'data-id' ) );
				fetch( A, { method: 'POST', body: f, credentials: 'same-origin' } ).then( function ( x ) { return x.json(); } ).then( function ( j ) { if ( j && j.success ) { tr.remove(); } } );
				return;
			}
			if ( e.target.closest( '.upw-ixp-import' ) ) {
				e.preventDefault();
				var inp = r.querySelector( '.upw-ixp-file' );
				if ( ! inp.files.length ) { M.textContent = <?php echo wp_json_encode( __( 'Choose a file first.', 'fw' ) ); ?>; return; }
				var g = new FormData();
				g.append( 'action', 'upw_ix_presets_import' ); g.append( 'nonce', N ); g.append( 'file', inp.files[ 0 ] );
				M.textContent = '…';
				fetch( A, { method: 'POST', body: g, credentials: 'same-origin' } ).then( function ( x ) { return x.json(); } ).then( function ( j ) {
					M.textContent = ( j && j.data && j.data.message ) || '';
					if ( j && j.success ) { window.setTimeout( function () { window.location.reload(); }, 900 ); }
				} );
			}
		} );
	} )();
	</script>
	<?php
} );
