<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Studio Scene — plays a scene made in the Unyson+ Animation Studio.
 *
 * The scene file is stored as text in the `scene` att. The bundled scene player (static/js/vendor/
 * scene-player.js, a copy of the Studio's published player) mounts every `[data-upw-scene]` block on
 * the page. In the builder, "Open in Animation Studio" opens the Studio in a new window and the two
 * talk over postMessage: the Studio says hello, we send it this element's scene, and its "Save to
 * page" sends the edited scene back into the field. We only accept messages from the Studio's own
 * address; the Studio only sends back to the window that opened it.
 *
 * Helpers here are prefixed upw_studio_scene_ and loaded once, at registration.
 */

/** The Animation Studio's address (filterable, e.g. to a local copy while developing the Studio). */
function upw_studio_scene_studio_url() {
	/**
	 * Filters the Animation Studio address used by "Open in Animation Studio".
	 *
	 * @param string $url The Studio URL, ending in a slash.
	 */
	return trailingslashit( apply_filters( 'fw_studio_scene_studio_url', 'https://animation-studio.unysonplus.com/' ) );
}

/**
 * Reads the `scene` att: a scene file, or the Studio's embed code (the scene sits in its
 * <script type="application/json">). Returns the decoded scene array, or null if it isn't one.
 *
 * @param string $raw
 * @return array|null
 */
function upw_studio_scene_parse( $raw ) {
	$raw = trim( (string) $raw );
	if ( $raw === '' ) {
		return null;
	}
	if ( strpos( $raw, '<script' ) !== false && preg_match( '#<script[^>]*type=["\']application/json["\'][^>]*>(.*?)</script>#is', $raw, $m ) ) {
		$raw = trim( $m[1] );
	}
	$scene = json_decode( $raw, true );
	if ( ! is_array( $scene ) || ( $scene['format'] ?? '' ) !== 'unysonplus-scene' || ! isset( $scene['layers'] ) || ! is_array( $scene['layers'] ) ) {
		return null;
	}
	return $scene;
}

/** The builder buttons + live summary under the Scene field (rendered by an `html` option). */
function upw_studio_scene_tools_html() {
	return '<div class="upw-studio-tools" data-studio="' . esc_attr( upw_studio_scene_studio_url() ) . '">'
		. '<div class="upw-studio-tools__row">'
		. '<button type="button" class="button button-primary upw-studio-open">' . esc_html__( 'Open in Animation Studio', 'fw' ) . '</button>'
		. '<a class="upw-studio-new" href="' . esc_url( upw_studio_scene_studio_url() ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open the Studio in a new tab', 'fw' ) . '</a>'
		. '</div>'
		. '<p class="upw-studio-status" role="status" aria-live="polite"></p>'
		. '<p class="upw-studio-hint">' . esc_html__( 'The Studio opens in a new window with this scene loaded. Click “Save to page” there to bring your changes back, then update the page.', 'fw' ) . '</p>'
		. '</div>';
}

class FW_Shortcode_Studio_Scene extends FW_Shortcode {

	public function _init() {
		// The builder's edit window: the round-trip script, loaded wherever shortcode admin scripts load.
		add_action( 'fw:ext:shortcodes:enqueue-shortcodes-admin-scripts', array( $this, '_action_admin_scripts' ) );
	}

	public function _action_admin_scripts() {
		$ext = fw_ext( 'animation-engine' );
		if ( ! $ext ) {
			return;
		}
		$base = $ext->get_declared_URI( '/shortcodes/studio-scene/static' );
		$dir  = __DIR__ . '/static';
		$ver  = $ext->manifest->get_version();
		$jsv  = file_exists( $dir . '/js/studio-scene-admin.js' ) ? $ver . '.' . filemtime( $dir . '/js/studio-scene-admin.js' ) : $ver;
		$cssv = file_exists( $dir . '/css/studio-scene-admin.css' ) ? $ver . '.' . filemtime( $dir . '/css/studio-scene-admin.css' ) : $ver;

		wp_enqueue_style( 'fw-studio-scene-admin', $base . '/css/studio-scene-admin.css', array(), $cssv );
		wp_enqueue_script( 'fw-studio-scene-admin', $base . '/js/studio-scene-admin.js', array( 'jquery' ), $jsv, true );
		wp_localize_script( 'fw-studio-scene-admin', 'upwStudioScene', array(
			'studioUrl' => upw_studio_scene_studio_url(),
			'i18n'      => array(
				'empty'       => __( 'No scene yet. Paste one above, or open the Studio to make one.', 'fw' ),
				'invalid'     => __( 'This isn’t a scene the player understands. Paste a scene file or the Studio’s embed code.', 'fw' ),
				/* translators: 1: scene title, 2: number of layers, 3: length in seconds */
				'summary'     => __( '“%1$s”: %2$s layers, %3$s s', 'fw' ),
				'untitled'    => __( 'Untitled scene', 'fw' ),
				'waiting'     => __( 'Opening the Studio…', 'fw' ),
				'connected'   => __( 'The Studio is open with this scene. Click “Save to page” there to bring changes back.', 'fw' ),
				'saved'       => __( 'Scene updated from the Studio. Update the page to keep it.', 'fw' ),
				'blocked'     => __( 'The browser blocked the Studio window. Allow pop-ups for this site, then try again.', 'fw' ),
			),
		) );
	}
}
