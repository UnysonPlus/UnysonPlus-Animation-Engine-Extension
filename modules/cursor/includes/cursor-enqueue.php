<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Cursor module: front-end enqueue.
 *
 * Runs on the front end only when the cursor is enabled: reads the chosen style + its options,
 * ships ONLY that style's shape CSS + the one JS group that implements it (on-demand), and
 * passes the resolved config to the runtime as window.upwCursorCfg. Depends on the helpers.
 *
 * NOTE: uses UPW_CURSOR_DIR (defined in cursor.php) — NOT __DIR__ — for filemtime cache-busting,
 * because this file lives in includes/ but the static assets are at the module root.
 */

add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() || upw_cursor_setting( 'enable', 'no' ) !== 'yes' ) {
		return;
	}
	// Style is a multi-picker: { shape: 'dot_ring', dot_ring: {trail}, glyph: {glyph_char}, … }.
	$style_mp = upw_cursor_setting( 'style', array() );
	$style    = ( is_array( $style_mp ) && ! empty( $style_mp['shape'] ) ) ? (string) $style_mp['shape'] : 'dot_ring';
	if ( ! array_key_exists( $style, upw_cursor_styles() ) || $style === 'none' ) {
		return;
	}
	$sub = ( is_array( $style_mp ) && isset( $style_mp[ $style ] ) && is_array( $style_mp[ $style ] ) ) ? $style_mp[ $style ] : array();
	$ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( ! $ext ) {
		return;
	}
	$base = $ext->get_declared_URI( '/modules/cursor' );
	$ver  = $ext->manifest->get_version();
	$dir  = defined( 'UPW_CURSOR_DIR' ) ? UPW_CURSOR_DIR : dirname( __DIR__ ); // module root (assets live here)
	// On-demand assets (site-wide single choice): ship the shared base CSS + ONLY the chosen
	// style's shape CSS + the one JS group file that implements it (was 44 KB → ~5-11 KB).
	$fver = function ( $rel ) use ( $dir, $ver ) { $abs = $dir . $rel; return file_exists( $abs ) ? $ver . '.' . filemtime( $abs ) : $ver; };
	// Map each style to its JS group file (many simple styles share the "shapes" builder).
	$js_group_map = array(
		'echo' => 'swarm', 'firefly' => 'swarm', 'confetti' => 'swarm', 'bubble' => 'swarm',
		'ink' => 'canvasfx', 'fluid' => 'canvasfx', 'distort' => 'canvasfx',
		'word_trail' => 'wordtrail',
	);
	$singles = array( 'comet', 'particles', 'elastic', 'arrow', 'spring', 'streak', 'rope', 'metaball', 'label', 'sticky', 'reveal', 'magnify', 'spotlight' );
	$group   = isset( $js_group_map[ $style ] ) ? $js_group_map[ $style ] : ( in_array( $style, $singles, true ) ? $style : 'shapes' );

	wp_enqueue_style( 'upw-cursor-base', $base . '/static/css/base.css', array(), $fver( '/static/css/base.css' ) );
	$style_css = "/static/css/styles/$style.css";
	if ( file_exists( $dir . $style_css ) ) {
		wp_enqueue_style( 'upw-cursor-' . sanitize_html_class( $style ), $base . $style_css, array( 'upw-cursor-base' ), $fver( $style_css ) );
	}
	// The cursor loop runs on the shared frame scheduler (window.upwAnimRaf) so it pauses while
	// the tab is hidden instead of burning CPU in a background tab.
	$cur_deps = function_exists( 'upw_anim_raf_handle' ) ? array( upw_anim_raf_handle() ) : array();
	wp_enqueue_script( 'upw-cursor', $base . '/static/js/styles/' . $group . '.js', $cur_deps, $fver( '/static/js/styles/' . $group . '.js' ), true );

	$color     = function_exists( 'sc_color_to_css' ) ? sc_color_to_css( upw_cursor_setting( 'color', '' ), '#2f74e6' ) : '#2f74e6';
	// Canvas (2D context) can't use a CSS var() — resolve a real hex for ink/fluid/ripple.
	$color_hex = function_exists( 'sc_color_to_css' ) ? sc_color_to_css( upw_cursor_setting( 'color', '' ), '#2f74e6', true ) : '#2f74e6';
	$img   = isset( $sub['custom_image'] ) ? $sub['custom_image'] : array();
	$img   = ( is_array( $img ) && ! empty( $img['url'] ) ) ? esc_url_raw( $img['url'] ) : '';
	$rimg  = isset( $sub['reveal_image'] ) ? $sub['reveal_image'] : array();
	$rimg  = ( is_array( $rimg ) && ! empty( $rimg['url'] ) ) ? esc_url_raw( $rimg['url'] ) : '';

	// Word Trail + Label typography (typography-v2 → JS style props + Google-font enqueue).
	$word_font  = upw_cursor_font_props( isset( $sub['word_font'] ) ? $sub['word_font'] : array() );
	$label_font = upw_cursor_font_props( isset( $sub['label_font'] ) ? $sub['label_font'] : array() );

	$cfg = array(
		'style'         => $style,
		'color'         => $color !== '' ? $color : '#2f74e6',
		'colorHex'      => $color_hex !== '' ? $color_hex : '#2f74e6',
		'canvasFollowScroll' => ( isset( $sub['follow_scroll'] ) ? $sub['follow_scroll'] : 'yes' ) === 'yes',
		'size'          => (int) upw_cursor_setting( 'size', 8 ),
		'trail'         => (float) ( isset( $sub['trail'] ) ? $sub['trail'] : 0.18 ),
		'glyph'         => (string) ( isset( $sub['glyph_char'] ) ? $sub['glyph_char'] : '→' ),
		'image'         => $img,
		'spotRadius'    => (int) ( isset( $sub['spot_radius'] ) ? $sub['spot_radius'] : 160 ),
		'spotDim'       => (float) ( isset( $sub['spot_dim'] ) ? $sub['spot_dim'] : 0.6 ),
		'count'         => (int) ( isset( $sub['count'] ) ? $sub['count'] : 8 ),
		'confettiMulti' => ( isset( $sub['multicolor'] ) ? $sub['multicolor'] : 'yes' ) === 'yes',
		'elastic'       => (float) ( isset( $sub['elastic'] ) ? $sub['elastic'] : 0.5 ),
		'lensRadius'    => (int) ( isset( $sub['lens_radius'] ) ? $sub['lens_radius'] : 70 ),
		'lensBlur'      => (float) ( isset( $sub['lens_blur'] ) ? $sub['lens_blur'] : 4 ),
		'radarSpeed'    => (float) ( isset( $sub['radar_speed'] ) ? $sub['radar_speed'] : 1.6 ),
		'label'         => (string) ( isset( $sub['default_label'] ) ? $sub['default_label'] : 'View' ),
		'word'          => (string) ( isset( $sub['word'] ) ? $sub['word'] : 'scroll' ),
		'wordFont'      => $word_font,
		'labelFont'     => $label_font,
		'revealImage'   => $rimg,
		'revealRadius'  => (int) ( isset( $sub['reveal_radius'] ) ? $sub['reveal_radius'] : 80 ),
		'zoom'          => (float) ( isset( $sub['zoom'] ) ? $sub['zoom'] : 2 ),
		'magnifyScope'  => (string) ( isset( $sub['magnify_scope'] ) ? $sub['magnify_scope'] : 'images' ),
		'inkWidth'      => (int) ( isset( $sub['ink_width'] ) ? $sub['ink_width'] : 6 ),
		'hoverGrow'     => upw_cursor_setting( 'hover_grow', 'yes' ) === 'yes',
		'magnetic'      => upw_cursor_setting( 'magnetic', 'no' ) === 'yes',
		'blend'         => upw_cursor_setting( 'blend', 'no' ) === 'yes',
		'clickRipple'   => upw_cursor_setting( 'click_ripple', 'no' ) === 'yes',
		'clickBurst'    => upw_cursor_setting( 'click_burst', 'no' ) === 'yes',
		'hideDefault'   => upw_cursor_setting( 'hide_default', 'yes' ) === 'yes',
		'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
	);
	wp_add_inline_script( 'upw-cursor', 'window.upwCursorCfg=' . wp_json_encode( $cfg ) . ';', 'before' );
} );
