<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * WebGL extension.
 *
 * Intentionally minimal: the only thing this extension ships is the
 * `[webgl_object]` leaf shortcode under shortcodes/webgl-object/, which the
 * shortcodes loader auto-discovers for any active extension. Activating the
 * extension makes the shortcode available; deactivating it unregisters the tag
 * (saved instances then render empty — by design).
 */
class FW_Extension_WebGL extends FW_Extension {

	/**
	 * @internal
	 */
	public function _init() {
		// No bootstrapping required — the shortcode is discovered automatically.
	}
}
