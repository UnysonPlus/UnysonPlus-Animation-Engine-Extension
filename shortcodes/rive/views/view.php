<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Rive Animation — frontend render. Emits a <canvas> the Rive runtime draws into, plus a poster (or a
 * plain veil) that stays as the still fallback when the runtime can't load or reduce-motion is on.
 * Group children store FLAT (the group container is presentational).
 *
 * @var array $atts
 */

if ( ! function_exists( 'sc_get' ) ) {
	function sc_get( $path, $atts, $default = '' ) {
		if ( function_exists( 'fw_akg' ) ) { $v = fw_akg( $path, $atts, null ); if ( $v !== null ) { return $v; } }
		return $default;
	}
}

/* ---- source ---- */
$src_url = trim( (string) sc_get( 'src_url', $atts, '' ) );
if ( $src_url === '' ) {
	$up = sc_get( 'src', $atts, array() );
	if ( is_array( $up ) && ! empty( $up['url'] ) ) { $src_url = (string) $up['url']; }
}
$src_url = $src_url !== '' ? esc_url_raw( $src_url ) : '';

if ( $src_url === '' ) {
	if ( function_exists( 'fw_is_editor_context' ) && fw_is_editor_context() && function_exists( 'sc_editor_notice' ) ) {
		echo sc_editor_notice( __( 'Add a .riv file (or URL) for the Rive Animation.', 'fw' ) );
	}
	return;
}

/* ---- config ---- */
$trigger = (string) sc_get( 'trigger', $atts, 'autoplay' );
if ( ! in_array( $trigger, array( 'autoplay', 'view', 'hover', 'click', 'scroll' ), true ) ) { $trigger = 'autoplay'; }
$fit = (string) sc_get( 'fit', $atts, 'contain' );
if ( ! in_array( $fit, array( 'contain', 'cover', 'fill', 'fit-width', 'fit-height', 'none' ), true ) ) { $fit = 'contain'; }
$align = (string) sc_get( 'alignment', $atts, 'center' );
if ( ! in_array( $align, array( 'center', 'top', 'bottom', 'left', 'right' ), true ) ) { $align = 'center'; }
$height = max( 80, min( 900, (int) sc_get( 'height', $atts, 360 ) ) );
$bg     = (string) sc_get( 'background', $atts, '' );
if ( $bg !== '' && ! preg_match( '/^#[0-9a-fA-F]{3,8}$/', $bg ) ) { $bg = ''; }

$config = array(
	'src'          => $src_url,
	'artboard'     => trim( (string) sc_get( 'artboard', $atts, '' ) ),
	'stateMachine' => trim( (string) sc_get( 'state_machine', $atts, '' ) ),
	'clickTrigger' => trim( (string) sc_get( 'click_trigger', $atts, '' ) ),
	'animation'    => trim( (string) sc_get( 'anim_name', $atts, '' ) ),
	'trigger'      => $trigger,
	'scrollInput'  => trim( (string) sc_get( 'scroll_input', $atts, '' ) ),
	'scrollLen'    => max( 1.0, min( 6.0, (float) sc_get( 'scroll_length', $atts, 2 ) ) ),
	'fit'          => $fit,
	'align'        => $align,
	'snug'         => sc_get( 'snug', $atts, 'yes' ) !== 'no',
);

/* ---- Rive Events (Open-URL follow + JS hook) ---- */
$evt_target = (string) sc_get( 'event_url_target', $atts, 'auto' );
if ( ! in_array( $evt_target, array( 'auto', '_self', '_blank' ), true ) ) { $evt_target = 'auto'; }
$config['events'] = array(
	'openUrl'   => sc_get( 'event_open_url', $atts, 'no' ) === 'yes',
	'urlTarget' => $evt_target,
	'jsHook'    => sc_get( 'event_js_hook', $atts, 'no' ) === 'yes',
);

/* ---- Data bindings (View-Model properties set from the builder) ---- */
$rows  = sc_get( 'bindings', $atts, array() );
$binds = array();
if ( is_array( $rows ) ) {
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) { continue; }
		$prop = trim( (string) ( isset( $row['prop'] ) ? $row['prop'] : '' ) );
		if ( $prop === '' ) { continue; }
		$btype = isset( $row['type'] ) ? (string) $row['type'] : 'text';
		if ( ! in_array( $btype, array( 'text', 'number', 'boolean', 'color', 'trigger' ), true ) ) { $btype = 'text'; }
		$binds[] = array(
			'prop'  => $prop,
			'type'  => $btype,
			'value' => (string) ( isset( $row['value'] ) ? $row['value'] : '' ),
		);
	}
}
if ( $binds ) { $config['bindings'] = $binds; }

$poster     = sc_get( 'poster', $atts, array() );
$poster_url = ( is_array( $poster ) && ! empty( $poster['url'] ) ) ? $poster['url'] : '';

/* ---- wrapper ---- */
$classes = array( 'fw-rive', 'fw-rive--' . sanitize_html_class( $trigger ) );
if ( function_exists( 'sc_build_wrapper_attr' ) ) {
	$atts['base_class']       = 'rive';
	$atts['unique_id_prefix'] = 'rive-';
	$atts['css_class']        = trim( implode( ' ', $classes ) . ' ' . ( isset( $atts['css_class'] ) ? $atts['css_class'] : '' ) );
	$attr = sc_build_wrapper_attr( $atts );
} else {
	$attr = array( 'class' => implode( ' ', $classes ) );
}

$style  = '--rive-h:' . $height . 'px;';
if ( $bg !== '' ) { $style .= '--rive-bg:' . esc_attr( $bg ) . ';'; }
$attr['style']       = ( isset( $attr['style'] ) && $attr['style'] !== '' ? rtrim( $attr['style'], ';' ) . ';' : '' ) . $style;
$attr['data-rive']   = '1';
$attr['data-config'] = wp_json_encode( $config );
if ( $trigger === 'click' || $trigger === 'hover' ) { $attr['tabindex'] = '0'; }

echo '<div ' . fw_attr_to_html( $attr ) . '>';
echo '<canvas class="fw-rive__canvas" aria-label="' . esc_attr__( 'Animation', 'fw' ) . '"></canvas>';
if ( $poster_url !== '' ) {
	echo '<img class="fw-rive__poster" src="' . esc_url( $poster_url ) . '" alt="" loading="lazy" decoding="async" />';
}
echo '</div>';
