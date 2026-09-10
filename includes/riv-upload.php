<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Allow Rive animation uploads (.riv) in the Media Library.
 *
 * Same two steps as the glTF model upload (see glb-upload.php):
 *  1. `upload_mimes` — add `riv` to the allow-list (as application/octet-stream).
 *  2. `wp_check_filetype_and_ext` — WordPress sniffs the real MIME with finfo; a `.riv`
 *     sniffs as `application/octet-stream`, which we re-assert by extension so the sniff
 *     can't veto a known-safe Rive file.
 *
 * Scoped to `.riv` only; every other file keeps WordPress's normal validation.
 * Filterable off via `fw_rive_allow_uploads`.
 */

if ( ! function_exists( 'upw_riv_content_ok' ) ) {
	/** A Rive .riv binary begins with the ASCII fingerprint "RIVE"; older builds may differ, so we
	 * accept the fingerprint OR any non-text binary head (not starting with '<' — i.e. not HTML/SVG). */
	function upw_riv_content_ok( $file ) {
		if ( ! is_string( $file ) || ! is_readable( $file ) ) { return false; }
		$head = (string) file_get_contents( $file, false, null, 0, 16 );
		if ( $head === '' ) { return false; }
		if ( substr( $head, 0, 4 ) === 'RIVE' ) { return true; }
		return $head[0] !== '<'; // reject HTML/SVG/script masquerading as .riv
	}
}

if ( ! apply_filters( 'fw_rive_allow_uploads', true ) ) {
	return;
}

add_filter( 'upload_mimes', function ( $mimes ) {
	$mimes['riv'] = 'application/octet-stream';
	return $mimes;
} );

add_filter( 'wp_check_filetype_and_ext', function ( $data, $file, $filename ) {
	if ( ! empty( $data['ext'] ) && ! empty( $data['type'] ) ) { return $data; }
	if ( ! preg_match( '/\.riv$/i', (string) $filename ) ) { return $data; }
	if ( ! upw_riv_content_ok( $file ) ) { return $data; }
	$data['ext']  = 'riv';
	$data['type'] = 'application/octet-stream';
	return $data;
}, 10, 3 );
