<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * `gsap-code-preview` option type — a READ-ONLY panel that shows the GSAP code the current Scroll
 * Motion settings generate. It's the "teaching rung": pick Reveal / up / dramatic / start top 85%,
 * and watch the exact `gsap.from(el, {…})` your choices produce. Learn GSAP by reading what your
 * own options build — then, when you want more, copy it into a Motion Snippet and tweak.
 *
 * SAFETY BY DESIGN (same class as gallery-3d-preview — cannot cause the "blank error: modal"):
 *   - VALUE-LESS. `_render()` emits an empty host; `_get_value_from_input()` returns null. No value
 *     shape to migrate, nothing to corrupt.
 *   - All logic is client-side (preview.js). A JS throw = a dead box, never a broken options AJAX.
 *   - Purely ADDITIVE — changes no existing option's type or shape.
 *
 * The JS reads its sibling effect-group values (in the same modal), translates them to GSAP the way
 * upw-gsap.js does, and prints the code. It re-renders on `fw:options:change`.
 */
class FW_Option_Type_Gsap_Code_Preview extends FW_Option_Type {

	public function _init() {}

	public function get_type() {
		return 'gsap-code-preview';
	}

	private function own_uri( $append = '' ) {
		return fw_get_framework_directory_uri( '/extensions/animation-engine/includes/option-types/gsap-code-preview' . $append );
	}

	/**
	 * @internal
	 */
	protected function _enqueue_static( $id, $option, $data ) {
		$ae  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
		$ver = $ae ? $ae->manifest->get_version() : '1.0.0';
		wp_enqueue_style( 'fw-gsap-code-preview', $this->own_uri( '/static/css/preview.css' ), array(), $ver );
		wp_enqueue_script( 'fw-gsap-code-preview', $this->own_uri( '/static/js/preview.js' ), array( 'jquery' ), $ver, true );
	}

	/**
	 * Value-less host. `data-effect` tells the JS which effect group it lives in, so it reads the
	 * right sibling values (each effect group embeds its own preview).
	 * @internal
	 */
	protected function _render( $id, $option, $data ) {
		$effect = isset( $option['effect'] ) ? (string) $option['effect'] : '';
		$label  = esc_html__( 'The GSAP your settings generate', 'fw' );
		$note   = esc_html__( 'Read-only — a friendly, close approximation of the runtime. Copy it into a Motion Snippet to make it your own.', 'fw' );
		$copy   = esc_attr__( 'Copy', 'fw' );
		return '<div class="fw-gsap-code" data-fw-gsap-code data-effect="' . esc_attr( $effect ) . '">'
			. '<div class="fw-gsap-code__head"><span class="fw-gsap-code__label">' . $label . '</span>'
			. '<button type="button" class="fw-gsap-code__copy" data-copy>' . esc_html( $copy ) . '</button></div>'
			. '<pre class="fw-gsap-code__pre"><code class="language-javascript"></code></pre>'
			. '<p class="fw-gsap-code__note">' . $note . '</p>'
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
		return array( 'value' => null, 'effect' => '' );
	}
}

FW_Option_Type::register( 'FW_Option_Type_Gsap_Code_Preview' );
