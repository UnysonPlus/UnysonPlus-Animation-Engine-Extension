<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * `gallery-3d-preview` option type — a LIVE preview of the 3D Gallery designs, shown inside the
 * element's Design tab so a geometry value can be judged without saving + viewing the page.
 *
 * SAFETY BY DESIGN — this is why it cannot cause the "blank error: modal" failure class:
 *   - It is VALUE-LESS. `_render()` emits an empty host div; it reads NO saved value, stores NONE
 *     (`_get_value_from_input` returns null). No value shape to migrate, nothing to corrupt.
 *   - All logic is client-side (preview.js). If that JS throws, you get a dead box, never a broken
 *     options-render AJAX.
 *   - It is purely ADDITIVE — it changes no existing option's type or shape.
 *
 * The JS reads its sibling `design_settings` values (from the DOM, in the same modal), builds the
 * scene the way the PHP views do — with PLACEHOLDER cards, not the real photos (the front end is the
 * final render) — and drives it with the element's REAL runtime. It re-renders on `fw:options:change`
 * (which the framework already broadcasts for sliders/selects/the design picker).
 */
class FW_Option_Type_Gallery_3D_Preview extends FW_Option_Type {

	public function _init() {}

	public function get_type() {
		return 'gallery-3d-preview';
	}

	/** Where the shortcode's real runtime lives (CSS + driver), reused so the preview matches 1:1. */
	private function gallery_uri( $append = '' ) {
		$ae = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
		return $ae ? $ae->get_declared_URI( '/shortcodes/gallery-3d/static' . $append ) : '';
	}

	private function own_uri( $append = '' ) {
		return fw_get_framework_directory_uri( '/extensions/animation-engine/includes/option-types/gallery-3d-preview' . $append );
	}

	/**
	 * @internal
	 */
	protected function _enqueue_static( $id, $option, $data ) {
		$ae  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
		$ver = $ae ? $ae->manifest->get_version() : '1.0.0';

		// The element's real card CSS + 3D driver (so the preview is the genuine runtime), plus this
		// option type's own admin CSS/JS. The driver is dependency-free and, in admin, only acts on
		// hosts the preview builds — it never auto-scans real content here.
		wp_enqueue_style( 'fw-gallery-3d-runtime', $this->gallery_uri( '/css/gallery-3d.css' ), array(), $ver );
		wp_enqueue_style( 'fw-gallery-3d-preview', $this->own_uri( '/static/css/preview.css' ), array( 'fw-gallery-3d-runtime' ), $ver );
		wp_enqueue_script( 'fw-gallery-3d-runtime', $this->gallery_uri( '/js/gallery-3d.js' ), array(), $ver, true );
		wp_enqueue_script( 'fw-gallery-3d-preview', $this->own_uri( '/static/js/preview.js' ), array( 'jquery', 'fw-gallery-3d-runtime' ), $ver, true );
	}

	/**
	 * Value-less: just a host the JS renders into.
	 * @internal
	 */
	protected function _render( $id, $option, $data ) {
		return '<div class="fw-gallery-3d-preview" data-fw-gallery-3d-preview>'
			. '<div class="fw-gallery-3d-preview__stage"></div>'
			. '<div class="fw-gallery-3d-preview__bar">'
			. '<span class="fw-gallery-3d-preview__note">' . esc_html__( 'Live preview — placeholder cards; your images render on the front end.', 'fw' ) . '</span>'
			. '<span class="fw-gallery-3d-preview__bg" aria-label="' . esc_attr__( 'Preview background', 'fw' ) . '">'
			. '<button type="button" class="is-active" data-bg="dark">' . esc_html__( 'Dark', 'fw' ) . '</button>'
			. '<button type="button" data-bg="light">' . esc_html__( 'Light', 'fw' ) . '</button>'
			. '</span>'
			. '</div>'
			. '</div>';
	}

	/**
	 * Stores nothing.
	 * @internal
	 */
	protected function _get_value_from_input( $option, $input_value ) {
		return null;
	}

	protected function _get_defaults() {
		// Value-less, but the framework requires a 'value' key in the defaults (else it warns
		// "has no default value"). Mirrors the `html` option type.
		return array( 'value' => null );
	}
}

FW_Option_Type::register( 'FW_Option_Type_Gallery_3D_Preview' );
