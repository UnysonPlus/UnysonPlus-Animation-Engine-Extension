<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Studio Scene — frontend render. Emits the scene file inside a `[data-upw-scene]` block; the bundled
 * scene player (enqueued by static.php) mounts it. Layout-tab choices override the scene's own stage
 * and playback settings for this placement only.
 *
 * @var array $atts
 */

$scene = function_exists( 'upw_studio_scene_parse' ) ? upw_studio_scene_parse( isset( $atts['scene'] ) ? $atts['scene'] : '' ) : null;

if ( ! $scene ) {
	if ( function_exists( 'fw_is_editor_context' ) && fw_is_editor_context() && function_exists( 'sc_editor_notice' ) ) {
		$has_text = trim( (string) ( isset( $atts['scene'] ) ? $atts['scene'] : '' ) ) !== '';
		echo sc_editor_notice( $has_text
			? __( 'The Studio Scene text isn’t a scene the player understands. Paste a scene file or the Studio’s embed code.', 'fw' )
			: __( 'Add a scene to the Studio Scene: paste one, or open the Animation Studio from its settings.', 'fw' ) );
	}
	return;
}

/* ---- this placement's overrides ---- */
$height_mode = isset( $atts['height_mode'] ) ? (string) $atts['height_mode'] : 'scene';
if ( in_array( $height_mode, array( 'ratio', 'ratio-capped', 'fill' ), true ) ) {
	$scene['stage']['heightMode'] = $height_mode;
}
$offset = isset( $atts['height_offset'] ) ? (int) $atts['height_offset'] : 0;
if ( $offset > 0 ) {
	$scene['stage']['heightOffset'] = min( 300, $offset );
}
$trigger = isset( $atts['trigger'] ) ? (string) $atts['trigger'] : 'scene';
if ( in_array( $trigger, array( 'view', 'load', 'scroll' ), true ) ) {
	$scene['playback']['trigger'] = $trigger;
}
$scroll_length = isset( $atts['scroll_length'] ) ? (float) $atts['scroll_length'] : 0;
if ( $scroll_length >= 0.2 && isset( $scene['playback']['trigger'] ) && 'scroll' === $scene['playback']['trigger'] ) {
	$scroll                     = isset( $scene['playback']['scroll'] ) && is_array( $scene['playback']['scroll'] ) ? $scene['playback']['scroll'] : array();
	$scroll['length']           = min( 10, $scroll_length );
	$scene['playback']['scroll'] = $scroll;
}
$loop = isset( $atts['loop'] ) ? (string) $atts['loop'] : 'scene';
if ( $loop === 'yes' || $loop === 'no' ) {
	$scene['playback']['loop'] = ( $loop === 'yes' );
}

/* ---- wrapper ---- */
if ( function_exists( 'sc_build_wrapper_attr' ) ) {
	$atts['base_class']       = 'studio-scene';
	$atts['unique_id_prefix'] = 'studio-scene-';
	$atts['css_class']        = trim( 'fw-studio-scene ' . ( isset( $atts['css_class'] ) ? $atts['css_class'] : '' ) );
	$attr = sc_build_wrapper_attr( $atts );
} else {
	$attr = array( 'class' => 'fw-studio-scene' );
}
$attr['data-upw-scene'] = '';

// Custom code clips are JavaScript. Like Motion Snippet, they run only when the post's author may
// publish unfiltered HTML; for anyone else the player skips them and the rest of the scene plays.
$upw_post = get_post();
if ( $upw_post && user_can( (int) $upw_post->post_author, 'unfiltered_html' ) ) {
	$attr['data-upw-allow-code'] = '';
}

// JSON_HEX_TAG turns < and > into < / >, so nothing in the scene can close the script element.
$json = wp_json_encode( $scene, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE );
if ( ! $json ) {
	return;
}

echo '<div ' . fw_attr_to_html( $attr ) . '>';
echo '<script type="application/json">' . $json . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput -- JSON, tag-escaped above.
echo '</div>';
