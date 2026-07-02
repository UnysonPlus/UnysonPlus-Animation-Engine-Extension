<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Allow 3D model uploads (.glb / .gltf) in the Media Library.
 *
 * Two things are needed, and only the first is obvious:
 *  1. `upload_mimes` — add the extensions to the allow-list.
 *  2. `wp_check_filetype_and_ext` — WordPress ALSO sniffs the file's real MIME with finfo
 *     and rejects the upload when it doesn't match the declared type. A `.glb` sniffs as
 *     `application/octet-stream` and a `.gltf` (JSON) as `text/plain`, neither of which
 *     matches `model/gltf-*` — that mismatch is what produces "This file cannot be
 *     processed by the web server." We re-assert ext + type by extension so the sniff
 *     can't veto these two known-safe formats.
 *
 * Scoped to the two model extensions only; every other file keeps WordPress's normal
 * validation. Filterable off via `fw_model_allow_uploads`.
 */

if ( ! function_exists( 'fw_model_allowed_mimes' ) ) {
	function fw_model_allowed_mimes() {
		return array(
			'glb'  => 'model/gltf-binary',
			'gltf' => 'model/gltf+json',
		);
	}
}

add_filter(
	'upload_mimes',
	function ( $mimes ) {
		if ( ! apply_filters( 'fw_model_allow_uploads', true ) ) {
			return $mimes;
		}
		foreach ( fw_model_allowed_mimes() as $ext => $type ) {
			$mimes[ $ext ] = $type;
		}
		return $mimes;
	}
);

// `upload_mimes` only allows the UPLOAD; the media-library type filter and
// fw_get_mime_type_by_ext() read the GLOBAL map from wp_get_mime_types() (the `mime_types`
// filter). Without registering them there, the [model_viewer] "pick from Media" frame is
// handed an empty `library.type` and renders a blank modal. Register them in both.
add_filter(
	'mime_types',
	function ( $mimes ) {
		if ( ! apply_filters( 'fw_model_allow_uploads', true ) ) {
			return $mimes;
		}
		foreach ( fw_model_allowed_mimes() as $ext => $type ) {
			$mimes[ $ext ] = $type;
		}
		return $mimes;
	}
);

add_filter(
	'wp_check_filetype_and_ext',
	function ( $data, $file, $filename, $mimes ) {
		if ( ! apply_filters( 'fw_model_allow_uploads', true ) ) {
			return $data;
		}
		// Only step in when WP couldn't resolve the type (the finfo-mismatch case).
		if ( ! empty( $data['ext'] ) && ! empty( $data['type'] ) ) {
			return $data;
		}
		$ext   = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		$allow = fw_model_allowed_mimes();
		if ( isset( $allow[ $ext ] ) ) {
			$data['ext']  = $ext;
			$data['type'] = $allow[ $ext ];
		}
		return $data;
	},
	10,
	4
);
