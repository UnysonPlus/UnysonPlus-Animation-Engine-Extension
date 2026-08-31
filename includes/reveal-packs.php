<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Reveal Packs — a clutter-free asset store for the Interactive Reveal (and, later,
 * Image Sequence) elements. Packs live as loose folders under
 * uploads/unysonplus/reveal/<slug>/ — NEVER as Media-Library attachments — so a
 * product with dozens of layers (or 100+ frames) does not flood the grid. Each pack
 * folder carries a pack.json manifest describing its images (and, for layered packs,
 * each layer's default offset), plus a poster.
 *
 * This file provides the shared plumbing: locate the store, list/read packs, install
 * from a ZIP (path-contained), delete, and seed a bundled demo pack. A polished admin
 * manager UI can layer on top of fw_ir_install_zip()/fw_ir_delete_pack() later.
 */

if ( ! function_exists( 'fw_ir_reveal_dir' ) ) {
	/** The reveal-pack store: array( 'path' => …, 'url' => … ), no trailing slash. */
	function fw_ir_reveal_dir() {
		if ( function_exists( 'fw_upw_uploads_dir' ) ) {
			return fw_upw_uploads_dir( 'reveal' );
		}
		$up = wp_upload_dir();
		return array(
			'path' => rtrim( $up['basedir'], '/\\' ) . '/unysonplus/reveal',
			'url'  => rtrim( $up['baseurl'], '/\\' ) . '/unysonplus/reveal',
		);
	}
}

if ( ! function_exists( 'fw_ir_pack_slug' ) ) {
	/** Sanitize an arbitrary name into a safe folder slug. */
	function fw_ir_pack_slug( $name ) {
		$slug = sanitize_key( str_replace( array( ' ', '.' ), '-', (string) $name ) );
		$slug = preg_replace( '/[^a-z0-9\-_]/', '', $slug );
		return trim( $slug, '-_' );
	}
}

if ( ! function_exists( 'fw_ir_get_pack' ) ) {
	/**
	 * Read one pack by slug. Returns null if missing/invalid, else:
	 * array( slug, name, type, dir, url, poster (url|''), layers[], frames[] ).
	 */
	function fw_ir_get_pack( $slug ) {
		$slug = fw_ir_pack_slug( $slug );
		if ( $slug === '' ) { return null; }
		$store = fw_ir_reveal_dir();
		$dir   = $store['path'] . '/' . $slug;
		$url   = $store['url'] . '/' . $slug;
		$json  = $dir . '/pack.json';
		if ( ! is_dir( $dir ) || ! is_file( $json ) ) { return null; }

		$data = json_decode( (string) @file_get_contents( $json ), true );
		if ( ! is_array( $data ) ) { return null; }

		$type   = isset( $data['type'] ) && $data['type'] === 'sequence' ? 'sequence' : 'layers';
		$poster = ! empty( $data['poster'] ) ? $url . '/' . ltrim( basename( (string) $data['poster'] ), '/' ) : '';

		$layers = array();
		if ( ! empty( $data['layers'] ) && is_array( $data['layers'] ) ) {
			foreach ( $data['layers'] as $l ) {
				if ( empty( $l['src'] ) ) { continue; }
				$layers[] = array(
					'url'   => $url . '/' . ltrim( basename( (string) $l['src'] ), '/' ),
					'label' => isset( $l['label'] ) ? (string) $l['label'] : '',
					'dx'    => (float) ( isset( $l['dx'] ) ? $l['dx'] : 0 ),
					'dy'    => (float) ( isset( $l['dy'] ) ? $l['dy'] : 0 ),
					'rot'   => (float) ( isset( $l['rot'] ) ? $l['rot'] : 0 ),
					'scale' => (float) ( isset( $l['scale'] ) ? $l['scale'] : 0 ),
					'fade'  => ! empty( $l['fade'] ),
				);
			}
		}

		$frames = array();
		if ( ! empty( $data['frames'] ) && is_array( $data['frames'] ) ) {
			foreach ( $data['frames'] as $f ) {
				$frames[] = $url . '/' . ltrim( basename( (string) $f ), '/' );
			}
		}

		return array(
			'slug'   => $slug,
			'name'   => isset( $data['name'] ) ? (string) $data['name'] : $slug,
			'type'   => $type,
			'dir'    => $dir,
			'url'    => $url,
			'poster' => $poster,
			'layers' => $layers,
			'frames' => $frames,
		);
	}
}

if ( ! function_exists( 'fw_ir_list_packs' ) ) {
	/** All installed packs, keyed by slug. Each value is the fw_ir_get_pack() shape. */
	function fw_ir_list_packs() {
		$store = fw_ir_reveal_dir();
		$out   = array();
		if ( ! is_dir( $store['path'] ) ) { return $out; }
		foreach ( (array) glob( $store['path'] . '/*', GLOB_ONLYDIR ) as $dir ) {
			$pack = fw_ir_get_pack( basename( $dir ) );
			if ( $pack ) { $out[ $pack['slug'] ] = $pack; }
		}
		return $out;
	}
}

if ( ! function_exists( 'fw_ir_pack_choices' ) ) {
	/** slug => name choices for a Reveal-Pack picker (empty '' first = "Choose a pack"). */
	function fw_ir_pack_choices( $type = '' ) {
		$out = array( '' => __( 'Choose a pack…', 'fw' ) );
		foreach ( fw_ir_list_packs() as $slug => $pack ) {
			if ( $type !== '' && $pack['type'] !== $type ) { continue; }
			$out[ $slug ] = $pack['name'];
		}
		return $out;
	}
}

if ( ! function_exists( 'fw_ir_install_zip' ) ) {
	/**
	 * Install a Reveal Pack from a ZIP file into uploads/unysonplus/reveal/<slug>/.
	 * The ZIP must contain images and (optionally) a pack.json; if none is present one
	 * is generated (layers = files sorted, radial auto-spread). Path-contained: any
	 * entry escaping the target dir aborts the install. Admin-only by design — callers
	 * must gate on current_user_can('manage_options') + a nonce.
	 *
	 * @return string|WP_Error the installed slug, or an error.
	 */
	function fw_ir_install_zip( $zip_path, $name = '' ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'no_zip', __( 'ZipArchive is not available on this server.', 'fw' ) );
		}
		if ( ! is_readable( $zip_path ) ) {
			return new WP_Error( 'no_file', __( 'Upload could not be read.', 'fw' ) );
		}
		$slug = fw_ir_pack_slug( $name !== '' ? $name : pathinfo( $zip_path, PATHINFO_FILENAME ) );
		if ( $slug === '' ) { $slug = 'pack-' . substr( md5( (string) time() ), 0, 6 ); }

		$store = fw_ir_reveal_dir();
		$dest  = $store['path'] . '/' . $slug;
		if ( is_dir( $dest ) ) { $slug .= '-' . substr( md5( (string) microtime( true ) ), 0, 4 ); $dest = $store['path'] . '/' . $slug; }
		if ( ! wp_mkdir_p( $dest ) ) {
			return new WP_Error( 'mkdir', __( 'Could not create the pack folder.', 'fw' ) );
		}
		$real_dest = realpath( $dest );

		$zip = new ZipArchive();
		if ( $zip->open( $zip_path ) !== true ) {
			return new WP_Error( 'bad_zip', __( 'The ZIP could not be opened.', 'fw' ) );
		}

		$allowed = array( 'png', 'jpg', 'jpeg', 'webp', 'avif', 'gif', 'svg', 'json' );
		$images  = array();
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$entry = $zip->getNameIndex( $i );
			if ( $entry === false || substr( $entry, -1 ) === '/' ) { continue; }
			$base = basename( $entry );
			$ext  = strtolower( pathinfo( $base, PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, $allowed, true ) ) { continue; }
			// Flatten: write every allowed file to the pack root by basename (path-contained).
			$target = $dest . '/' . sanitize_file_name( $base );
			$rt     = realpath( dirname( $target ) );
			if ( $rt === false || strpos( $rt, $real_dest ) !== 0 ) { continue; }
			$stream = $zip->getStream( $entry );
			if ( ! $stream ) { continue; }
			file_put_contents( $target, $stream );
			if ( is_resource( $stream ) ) { fclose( $stream ); }
			if ( $ext !== 'json' ) { $images[] = sanitize_file_name( $base ); }
		}
		$zip->close();

		if ( empty( $images ) && ! is_file( $dest . '/pack.json' ) ) {
			// Nothing usable — clean up.
			array_map( 'unlink', (array) glob( $dest . '/*' ) );
			@rmdir( $dest );
			return new WP_Error( 'empty', __( 'No images were found in the ZIP.', 'fw' ) );
		}

		// If the ZIP shipped no manifest, synthesise one: a radial auto-spread of the layers.
		if ( ! is_file( $dest . '/pack.json' ) ) {
			sort( $images, SORT_NATURAL | SORT_FLAG_CASE );
			$n      = max( 1, count( $images ) );
			$layers = array();
			foreach ( $images as $idx => $src ) {
				$ang = ( 2 * M_PI * $idx ) / $n - M_PI / 2;
				$mag = $idx === 0 ? 0 : 60; // first layer stays as the base
				$layers[] = array(
					'src'   => $src,
					'label' => pathinfo( $src, PATHINFO_FILENAME ),
					'dx'    => (int) round( cos( $ang ) * $mag ),
					'dy'    => (int) round( sin( $ang ) * $mag ),
					'rot'   => 0,
					'scale' => 0,
					'fade'  => false,
				);
			}
			$manifest = array(
				'name'   => $name !== '' ? $name : ucwords( str_replace( array( '-', '_' ), ' ', $slug ) ),
				'type'   => 'layers',
				'poster' => $images[0],
				'layers' => $layers,
			);
			file_put_contents( $dest . '/pack.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT ) );
		}

		return $slug;
	}
}

if ( ! function_exists( 'fw_ir_delete_pack' ) ) {
	/** Permanently remove an installed pack (path-contained). */
	function fw_ir_delete_pack( $slug ) {
		$slug  = fw_ir_pack_slug( $slug );
		if ( $slug === '' ) { return false; }
		$store = fw_ir_reveal_dir();
		$dir   = $store['path'] . '/' . $slug;
		$real  = realpath( $dir );
		if ( $real === false || strpos( $real, realpath( $store['path'] ) ) !== 0 ) { return false; }
		foreach ( (array) glob( $dir . '/*' ) as $f ) { if ( is_file( $f ) ) { unlink( $f ); } }
		return @rmdir( $dir );
	}
}

if ( ! function_exists( 'fw_ir_seed_demo_pack' ) ) {
	/**
	 * One-time: copy the bundled demo layer SVGs into the store as a ready-to-use pack
	 * so the picker is never empty and the feature is demonstrable out of the box.
	 * Hooked on admin_init, guarded by an option.
	 */
	function fw_ir_seed_demo_pack() {
		if ( get_option( 'fw_ir_demo_pack_seeded_v1' ) ) { return; }
		update_option( 'fw_ir_demo_pack_seeded_v1', 1, false );

		$ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
		if ( ! $ext ) { return; }
		$src = $ext->get_declared_path( '/shortcodes/interactive-reveal/static/img/demo' );
		if ( ! is_dir( $src ) ) { return; }

		$store = fw_ir_reveal_dir();
		$dest  = $store['path'] . '/demo-gadget';
		if ( is_dir( $dest ) ) { return; }
		if ( ! wp_mkdir_p( $dest ) ) { return; }

		foreach ( array( '1-base.svg', '2-board.svg', '3-coil.svg', '4-grille.svg' ) as $f ) {
			if ( is_file( "$src/$f" ) ) { copy( "$src/$f", "$dest/$f" ); }
		}
		$manifest = array(
			'name'   => __( 'Demo Gadget', 'fw' ),
			'type'   => 'layers',
			'poster' => '1-base.svg',
			'layers' => array(
				array( 'src' => '1-base.svg',   'label' => 'Base',   'dx' => 0,   'dy' => 0,   'rot' => 0,   'scale' => 0,  'fade' => false ),
				array( 'src' => '2-board.svg',  'label' => 'Board',  'dx' => 0,   'dy' => -60, 'rot' => 0,   'scale' => 6,  'fade' => true ),
				array( 'src' => '3-coil.svg',   'label' => 'Coil',   'dx' => 65,  'dy' => 35,  'rot' => 12,  'scale' => 0,  'fade' => false ),
				array( 'src' => '4-grille.svg', 'label' => 'Grille', 'dx' => -60, 'dy' => 55,  'rot' => -8,  'scale' => 0,  'fade' => false ),
			),
		);
		file_put_contents( "$dest/pack.json", wp_json_encode( $manifest, JSON_PRETTY_PRINT ) );
	}
}
add_action( 'admin_init', 'fw_ir_seed_demo_pack' );

/* -----------------------------------------------------------------------------
 * Minimal admin manager — Media → Reveal Packs. Upload a ZIP of layer images
 * (optionally with a pack.json), see the installed packs, delete them. Admin-only.
 * -------------------------------------------------------------------------- */
if ( ! function_exists( 'fw_ir_admin_menu' ) ) {
	function fw_ir_admin_menu() {
		add_media_page(
			__( 'Reveal Packs', 'fw' ),
			__( 'Reveal Packs', 'fw' ),
			'manage_options',
			'fw-reveal-packs',
			'fw_ir_admin_page'
		);
	}
}
add_action( 'admin_menu', 'fw_ir_admin_menu' );

if ( ! function_exists( 'fw_ir_admin_page' ) ) {
	function fw_ir_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$notice = '';

		// Install from an uploaded ZIP.
		if ( isset( $_POST['fw_ir_install'] ) && check_admin_referer( 'fw_ir_install' ) ) {
			if ( ! empty( $_FILES['pack_zip']['tmp_name'] ) && is_uploaded_file( $_FILES['pack_zip']['tmp_name'] ) ) {
				$name = isset( $_POST['pack_name'] ) ? sanitize_text_field( wp_unslash( $_POST['pack_name'] ) ) : '';
				if ( $name === '' ) { $name = pathinfo( (string) ( $_FILES['pack_zip']['name'] ?? '' ), PATHINFO_FILENAME ); }
				$res = fw_ir_install_zip( $_FILES['pack_zip']['tmp_name'], $name );
				$notice = is_wp_error( $res )
					? '<div class="notice notice-error"><p>' . esc_html( $res->get_error_message() ) . '</p></div>'
					: '<div class="notice notice-success"><p>' . esc_html( sprintf( __( 'Installed pack "%s".', 'fw' ), $res ) ) . '</p></div>';
			} else {
				$notice = '<div class="notice notice-error"><p>' . esc_html__( 'Please choose a .zip file.', 'fw' ) . '</p></div>';
			}
		}

		// Delete a pack.
		if ( isset( $_POST['fw_ir_delete'], $_POST['slug'] ) && check_admin_referer( 'fw_ir_delete' ) ) {
			fw_ir_delete_pack( sanitize_text_field( wp_unslash( $_POST['slug'] ) ) );
			$notice = '<div class="notice notice-success"><p>' . esc_html__( 'Pack deleted.', 'fw' ) . '</p></div>';
		}

		$packs = fw_ir_list_packs();
		echo '<div class="wrap"><h1>' . esc_html__( 'Reveal Packs', 'fw' ) . '</h1>';
		echo '<p style="max-width:70ch;color:#555;">' . esc_html__( 'Asset packs for the Interactive Reveal element. Frames / layers live under uploads/unysonplus/reveal/ — never in the Media Library. Upload a .zip of images (a pack.json manifest with per-layer offsets is optional; without one a radial auto-spread is generated).', 'fw' ) . '</p>';
		echo wp_kses_post( $notice );

		echo '<h2>' . esc_html__( 'Add a pack', 'fw' ) . '</h2>';
		echo '<form method="post" enctype="multipart/form-data" style="margin-bottom:2rem;">';
		wp_nonce_field( 'fw_ir_install' );
		echo '<input type="text" name="pack_name" placeholder="' . esc_attr__( 'Pack name (optional)', 'fw' ) . '" class="regular-text" /> ';
		echo '<input type="file" name="pack_zip" accept=".zip" required /> ';
		echo '<button type="submit" name="fw_ir_install" class="button button-primary">' . esc_html__( 'Upload pack', 'fw' ) . '</button>';
		echo '</form>';

		echo '<h2>' . esc_html__( 'Installed', 'fw' ) . '</h2>';
		if ( empty( $packs ) ) {
			echo '<p>' . esc_html__( 'No packs yet.', 'fw' ) . '</p>';
		} else {
			echo '<div style="display:flex;flex-wrap:wrap;gap:1rem;">';
			foreach ( $packs as $slug => $p ) {
				echo '<div style="border:1px solid #dcdcde;border-radius:8px;padding:.8rem;width:180px;background:#fff;">';
				if ( $p['poster'] ) {
					echo '<div style="height:110px;background:#f6f7f7 center/contain no-repeat url(' . esc_url( $p['poster'] ) . ');border-radius:5px;"></div>';
				}
				echo '<p style="margin:.5rem 0 .2rem;font-weight:600;">' . esc_html( $p['name'] ) . '</p>';
				echo '<p style="margin:0 0 .5rem;color:#787c82;font-size:12px;">' . esc_html( $p['type'] ) . ' · ' . esc_html( count( $p['layers'] ) ? count( $p['layers'] ) . ' layers' : count( $p['frames'] ) . ' frames' ) . '<br><code>' . esc_html( $slug ) . '</code></p>';
				echo '<form method="post" onsubmit="return confirm(\'' . esc_js( __( 'Delete this pack?', 'fw' ) ) . '\');">';
				wp_nonce_field( 'fw_ir_delete' );
				echo '<input type="hidden" name="slug" value="' . esc_attr( $slug ) . '" />';
				echo '<button type="submit" name="fw_ir_delete" class="button-link-delete button-link">' . esc_html__( 'Delete', 'fw' ) . '</button>';
				echo '</form></div>';
			}
			echo '</div>';
		}
		echo '</div>';
	}
}
