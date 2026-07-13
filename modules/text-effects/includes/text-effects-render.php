<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Text Effects module: runtime.
 *
 * Emits the chosen effect onto the element wrapper (`sc_build_wrapper_attr`), forces a wrapper
 * when a text effect is the element's only setting (`sc_needs_wrapper`), and registers the
 * module's per-effect on-demand asset layout with the shared loader. Depends on the helpers.
 *
 * NOTE: the asset registration uses UPW_TEXT_EFFECTS_DIR (defined in text-effects.php) — NOT
 * __DIR__ — for the module-root static path, because this file lives in includes/ while the
 * static assets are at the module root.
 */

/**
 * Normalise a multi-select trigger value (array of view/load/click/hover, or a legacy scalar,
 * or empty) into a space-separated list for data-text-trigger. Shared by every one-shot effect
 * whose trigger is the multi image-picker (reveal family, scramble, typewriter, countup,
 * splitflap, matrix). Defaults to 'view'.
 */
if ( ! function_exists( 'upw_text_trigger_list' ) ) :
	function upw_text_trigger_list( $raw ) {
		$t = is_array( $raw ) ? $raw : ( ( $raw === '' || $raw === null ) ? array() : array( (string) $raw ) );
		$t = array_values( array_intersect( array_map( 'strval', $t ), array( 'view', 'load', 'click', 'hover' ) ) );
		return empty( $t ) ? 'view' : implode( ' ', $t );
	}
endif;

/* ------------------------------------------------------------------ *
 * 2) Emit the chosen effect onto the element wrapper.
 * ------------------------------------------------------------------ */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	if ( ! upw_text_enabled() ) {
		return $attr;
	}

	$tx     = ( isset( $atts['text_effect'] ) && is_array( $atts['text_effect'] ) ) ? $atts['text_effect'] : array();
	$effect = isset( $tx['effect'] ) ? (string) $tx['effect'] : 'none';

	if ( ! in_array( $effect, upw_text_effects(), true ) ) {
		return $attr;
	}

	$o = ( isset( $tx[ $effect ] ) && is_array( $tx[ $effect ] ) ) ? $tx[ $effect ] : array();

	$cls           = isset( $attr['class'] ) ? trim( (string) $attr['class'] ) : '';
	$attr['class'] = esc_attr( trim( $cls . ' sc-text sc-text--' . sanitize_html_class( $effect ) ) );
	$attr['data-text'] = esc_attr( $effect );

	// On-demand assets: record this effect so ONLY its JS partial (+ CSS partial if it has
	// one) is enqueued, not the whole 37-effect bundle.
	if ( function_exists( 'upw_anim_use_asset' ) ) {
		upw_anim_use_asset( 'text-effects', $effect );
	}

	$add_style = function ( $css ) use ( &$attr ) {
		$existing      = isset( $attr['style'] ) ? rtrim( (string) $attr['style'], '; ' ) . '; ' : '';
		$attr['style'] = esc_attr( $existing . $css );
	};

	// The reveal family (split_reveal + Wave-A variants) all emit the same attrs;
	// the JS routes by the effect id to the right initial state.
	$reveal_ids = array( 'split_reveal', 'blur', 'mask', 'flip3d', 'scale', 'slide', 'bounce', 'random', 'skew' );
	if ( in_array( $effect, $reveal_ids, true ) ) {
		$attr['data-text-split']    = esc_attr( in_array( ( $o['split_by'] ?? 'chars' ), array( 'chars', 'words', 'lines' ), true ) ? $o['split_by'] : 'chars' );
		$attr['data-text-stagger']  = esc_attr( (float) ( $o['stagger'] ?? 0.03 ) );
		$attr['data-text-duration'] = esc_attr( (float) ( $o['duration'] ?? 0.6 ) );
		// Trigger is MULTI-SELECT (array of view/load/click/hover); emit a space-separated list.
		$attr['data-text-trigger']  = esc_attr( upw_text_trigger_list( $o['trigger'] ?? null ) );
		$attr['data-text-seq']      = esc_attr( ( ( $o['sequence'] ?? 'together' ) === 'cascade' ) ? 'cascade' : 'together' );
		if ( isset( $o['direction'] ) ) {
			$attr['data-text-dir'] = esc_attr( in_array( $o['direction'], array( 'up', 'down', 'left', 'right' ), true ) ? $o['direction'] : 'left' );
		}
		return $attr;
	}

	switch ( $effect ) {
		case 'scramble':
			$attr['data-text-duration'] = esc_attr( (float) ( $o['duration'] ?? 1.2 ) );
			$attr['data-text-trigger']  = esc_attr( upw_text_trigger_list( $o['trigger'] ?? null ) );
			break;

		case 'typewriter':
			$attr['data-text-speed']   = esc_attr( (int) ( $o['speed'] ?? 55 ) );
			$attr['data-text-caret']   = ( ( $o['caret'] ?? 'yes' ) !== 'no' ) ? '1' : '0';
			$attr['data-text-loop']    = ( ( $o['loop'] ?? 'no' ) === 'yes' ) ? '1' : '0';
			$attr['data-text-trigger'] = esc_attr( upw_text_trigger_list( $o['trigger'] ?? null ) );
			break;

		case 'shimmer':
			$ca = upw_text_color( $o['color_a'] ?? '', '#8a8f98' );
			$cb = upw_text_color( $o['color_b'] ?? '', '#ffffff' );
			$add_style( '--text-c1:' . $ca . '; --text-c2:' . $cb . '; --text-speed:' . (float) ( $o['speed'] ?? 3 ) . 's;' );
			break;

		case 'wave':
			$attr['data-text-split'] = 'chars';
			$add_style( '--text-wave-amp:' . (int) ( $o['amplitude'] ?? 6 ) . 'px; --text-wave-speed:' . (float) ( $o['speed'] ?? 1.4 ) . 's;' );
			break;

		case 'glitch':
			$attr['data-text-trigger'] = esc_attr( ( ( $o['trigger'] ?? 'hover' ) === 'always' ) ? 'always' : 'hover' );
			$add_style( '--text-glitch:' . (int) ( $o['intensity'] ?? 3 ) . 'px;' );
			break;

		case 'vf_weight':
			$attr['data-text-trigger'] = esc_attr( ( ( $o['trigger'] ?? 'hover' ) === 'view' ) ? 'view' : 'hover' );
			$add_style( '--text-wght-from:' . (int) ( $o['from'] ?? 300 ) . '; --text-wght-to:' . (int) ( $o['to'] ?? 800 ) . ';' );
			break;

		case 'gradient_flow':
			$add_style( '--text-c1:' . upw_text_color( $o['color_a'] ?? '', '#ff6b6b' )
				. '; --text-c2:' . upw_text_color( $o['color_b'] ?? '', '#6a8dff' )
				. '; --text-c3:' . upw_text_color( $o['color_c'] ?? '', '#17c964' )
				. '; --text-speed:' . (float) ( $o['speed'] ?? 4 ) . 's;' );
			break;

		case 'rainbow':
			$add_style( '--text-speed:' . (float) ( $o['speed'] ?? 4 ) . 's;' );
			break;

		case 'neon':
			$add_style( '--text-neon:' . upw_text_color( $o['glow_color'] ?? '', '#6aa6ff' ) . '; --text-speed:' . (float) ( $o['speed'] ?? 2.5 ) . 's;' );
			break;

		case 'breathing':
			$add_style( '--text-speed:' . (float) ( $o['speed'] ?? 3 ) . 's;' );
			break;

		case 'jitter':
			$add_style( '--text-jitter:' . (int) ( $o['intensity'] ?? 2 ) . 'px;' );
			break;

		case 'float':
			$add_style( '--text-float:' . (int) ( $o['distance'] ?? 8 ) . 'px; --text-speed:' . (float) ( $o['speed'] ?? 3 ) . 's;' );
			break;

		case 'marker':
			$attr['data-text-trigger'] = esc_attr( ( ( $o['trigger'] ?? 'view' ) === 'hover' ) ? 'hover' : 'view' );
			$add_style( '--text-marker:' . upw_text_color( $o['color'] ?? '', '#ffe066' ) . ';' );
			break;

		case 'strikebox':
			$attr['data-text-shape']   = esc_attr( in_array( ( $o['shape'] ?? 'strike' ), array( 'strike', 'underline', 'box' ), true ) ? $o['shape'] : 'strike' );
			$attr['data-text-trigger'] = esc_attr( ( ( $o['trigger'] ?? 'view' ) === 'hover' ) ? 'hover' : 'view' );
			$lc = upw_text_color( $o['color'] ?? '', '' );
			if ( $lc !== '' ) { $add_style( '--text-line:' . $lc . ';' ); }
			break;

		case 'outline_fill':
			$attr['data-text-trigger'] = esc_attr( ( ( $o['trigger'] ?? 'view' ) === 'hover' ) ? 'hover' : 'view' );
			$fc = upw_text_color( $o['color'] ?? '', '' );
			if ( $fc !== '' ) { $add_style( '--text-fill:' . $fc . ';' ); }
			break;

		case 'chromatic':
			$add_style( '--text-chroma:' . (int) ( $o['intensity'] ?? 2 ) . 'px;' );
			break;

		case 'width_sweep':
			$attr['data-text-trigger'] = esc_attr( ( ( $o['trigger'] ?? 'hover' ) === 'view' ) ? 'view' : 'hover' );
			$add_style( '--text-wdth-from:' . (int) ( $o['from'] ?? 75 ) . '; --text-wdth-to:' . (int) ( $o['to'] ?? 125 ) . ';' );
			break;

		case 'rotating_words':
			$attr['data-text-words']    = esc_attr( (string) ( $o['words'] ?? '' ) );
			$attr['data-text-interval'] = esc_attr( (float) ( $o['interval'] ?? 1.8 ) );
			break;

		case 'countup':
		case 'splitflap':
		case 'matrix':
			$attr['data-text-duration'] = esc_attr( (float) ( $o['duration'] ?? 1.4 ) );
			$attr['data-text-trigger']  = esc_attr( upw_text_trigger_list( $o['trigger'] ?? null ) );
			break;

		case 'fill_sweep':
			$attr['data-text-trigger'] = esc_attr( ( ( $o['trigger'] ?? 'hover' ) === 'view' ) ? 'view' : 'hover' );
			$add_style( '--text-fill:' . upw_text_color( $o['color'] ?? '', '#2f74e6' ) . ';' );
			break;

		case 'letter_jump':
			$add_style( '--text-jump:' . (int) ( $o['height'] ?? 6 ) . 'px;' );
			break;

		case 'expand_spacing':
			$add_style( '--text-spacing:' . (int) ( $o['amount'] ?? 6 ) . 'px;' );
			break;

		case 'color_wave':
			$attr['data-text-trigger'] = esc_attr( ( ( $o['trigger'] ?? 'hover' ) === 'view' ) ? 'view' : 'hover' );
			$add_style( '--text-wavecolor:' . upw_text_color( $o['color'] ?? '', '#2f74e6' ) . ';' );
			break;

		case 'magnetic':
			$attr['data-text-strength'] = esc_attr( (float) ( $o['strength'] ?? 0.4 ) );
			break;

		case 'image_mask':
			$mi = ( isset( $o['image'] ) && is_array( $o['image'] ) && ! empty( $o['image']['url'] ) ) ? esc_url_raw( $o['image']['url'] ) : '';
			if ( $mi !== '' ) { $attr['data-text-img'] = esc_url( $mi ); }
			break;

		case 'kinetic':
			$add_style( '--text-kinetic:' . (int) ( $o['intensity'] ?? 4 ) . ';' );
			break;
	}

	return $attr;
}, 22, 2 );

/* ------------------------------------------------------------------ *
 * 2b) Force a wrapper when a text effect is the element's only setting
 *     (leaf shortcodes gate their wrapper on sc_needs_wrapper()).
 * ------------------------------------------------------------------ */
add_filter( 'sc_needs_wrapper', function ( $needs, $atts ) {
	if ( $needs || ! upw_text_enabled() ) {
		return $needs;
	}
	$tx     = ( isset( $atts['text_effect'] ) && is_array( $atts['text_effect'] ) ) ? $atts['text_effect'] : array();
	$effect = isset( $tx['effect'] ) ? (string) $tx['effect'] : 'none';
	return in_array( $effect, upw_text_effects(), true );
}, 10, 2 );

/* ------------------------------------------------------------------ *
 * 3) On-demand assets. Register the module's per-effect partial layout with the shared
 *    loader; a page ships ONLY the shared core (text engine) + the used effects' partials
 *    — recorded per element in the wrapper filter via upw_anim_use_asset(). js_core_first:
 *    the core defines the shared engine that each effect partial aliases.
 * ------------------------------------------------------------------ */
if ( function_exists( 'upw_anim_register_assets' ) ) {
	$upw_text_ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( $upw_text_ext ) {
		upw_anim_register_assets( 'text-effects', array(
			'path'          => UPW_TEXT_EFFECTS_DIR,
			'uri'           => $upw_text_ext->get_declared_URI( '/modules/text-effects' ),
			'ver'           => $upw_text_ext->manifest->get_version(),
			'css_dir'       => 'static/css/effects',   // effects with a CSS class have a partial here; JS-only ones have none
			'js_dir'        => 'static/js/effects',
			'base_css'      => 'static/css/base.css',   // the split-piece / line spans, always needed
			'base_js'       => 'static/js/text-effects-core.js',
			'js_core_first' => true,                    // core (engine) loads before the effect partials
			'js_styles'     => upw_text_effects(),      // every effect ships a JS partial (registers into window.upwText)
			// The split/mask reveal engine (piece staggering, presets, cascade) is kept OUT of the
			// core — loaded only when one of these entrance effects is on the page.
			'js_shared'     => array(
				'reveal' => array( 'split_reveal', 'blur', 'flip3d', 'scale', 'slide', 'bounce', 'random', 'skew', 'mask' ),
			),
			'js_cfg'        => function () {
				$cfg = array(
					'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
					'disableMobile' => ( function_exists( 'upw_anim_engine_setting' ) && upw_anim_engine_setting( 'disable_on_mobile', 'no' ) === 'yes' ),
				);
				return 'window.upwTextCfg=' . wp_json_encode( $cfg ) . ';';
			},
		) );
	}
	unset( $upw_text_ext );
}
