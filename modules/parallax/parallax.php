<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Parallax Depth Layers module.
 *
 * Multi-layer depth parallax: mark a container as a **Scene** (the tracking stage), then set a
 * **Depth** on each child **Layer**. Layers drift at different speeds as the pointer moves over
 * the scene (and/or as the scene scrolls), creating a sense of depth — hero scenes, floating
 * shapes, layered illustrations. A Layer with no explicit Scene falls back to tracking the whole
 * window, so a few depth layers "just work" without marking a stage.
 *
 * Adds a "Parallax Layers" control to EVERY element's Animations tab (via the shortcodes
 * extension's `sc_animation_fields` filter), emits the role + settings onto the element wrapper
 * (via `sc_build_wrapper_attr`), and ships a self-contained vanilla-JS runtime (one shared RAF
 * ticker, no library) enqueued only on pages that use it. Global on/off lives in Theme Settings →
 * Animations → Parallax.
 *
 * Saved value shape (multi-picker, picker id `role`):
 *   [ 'role' => 'layer', 'layer' => [ 'depth' => 30, 'axis' => 'both', … ] ]
 */

if ( ! function_exists( 'upw_parallax_enabled' ) ) :
	function upw_parallax_enabled() {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return true;
		}
		$v = fw_get_db_settings_option( 'animation_parallax', array() );
		$e = ( is_array( $v ) && isset( $v['enable'] ) ) ? $v['enable'] : 'yes';
		return $e !== 'no' && $e !== false;
	}
endif;

if ( ! function_exists( 'upw_parallax_flag' ) ) :
	function upw_parallax_flag( $set = false ) {
		static $used = false;
		if ( $set ) {
			$used = true;
		}
		return $used;
	}
endif;

if ( ! function_exists( 'upw_prlx_slider' ) ) :
	function upw_prlx_slider( $label, $val, $min, $max, $step, $desc = '' ) {
		$f = array( 'type' => 'slider', 'label' => $label, 'value' => $val, 'properties' => array( 'min' => $min, 'max' => $max, 'step' => $step ) );
		if ( $desc !== '' ) { $f['desc'] = $desc; }
		return $f;
	}
endif;
if ( ! function_exists( 'upw_prlx_switch' ) ) :
	function upw_prlx_switch( $label, $desc = '', $default_yes = false ) {
		return array(
			'type'         => 'switch',
			'label'        => $label,
			'desc'         => $desc,
			'value'        => $default_yes ? 'yes' : 'no',
			'left-choice'  => array( 'value' => 'no',  'label' => __( 'Off', 'fw' ) ),
			'right-choice' => array( 'value' => 'yes', 'label' => __( 'On', 'fw' ) ),
		);
	}
endif;

/* ------------------------------------------------------------------ *
 * 1) The per-element "Parallax Layers" control, appended to Animations.
 * ------------------------------------------------------------------ */
add_filter( 'sc_animation_fields', function ( $fields ) {
	if ( ! is_array( $fields ) ) {
		return $fields;
	}

	$ext  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	$base = $ext ? $ext->get_declared_URI( '/modules/parallax/static/img/roles' ) : '';
	$tile = function ( $file, $label ) use ( $base ) {
		return array(
			'small' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 96 ),
			'large' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 150 ),
			'label' => $label,
		);
	};

	$fields['parallax'] = array(
		'type'         => 'multi-picker',
		'popover'      => true,
		// Popover multi-picker → the user-visible label lives on the TOP level.
		'label'        => __( 'Parallax Layers', 'fw' ),
		'desc'         => __( 'Multi-layer depth parallax. Set a container to Scene, then give each child a Layer depth — they drift at different speeds as the pointer moves (and/or on scroll). A Layer with no Scene tracks the whole window.', 'fw' ),
		'help'         => __( 'Parallax Depth Layers (Animation Engine): pointer- and scroll-driven multi-layer depth. Mark the stage as a Scene; mark each moving element as a Layer and give it a Depth. One shared render loop, no library. Honours "reduce motion" and is skipped on touch for the pointer source (scroll layers still move).', 'fw' ) . ( function_exists( 'upw_perf_note' ) ? ' ' . upw_perf_note() : '' ),
		'show_borders' => false,
		'value'        => array( 'role' => 'none' ),
		'anim_meta'    => array( 'category' => __( 'Scroll', 'fw' ), 'icon' => '&#127748;' ), // 🌄 (Animations-tab inserter)
		'picker'       => array(
			'role' => array(
				'type'    => 'image-picker',
				'label'   => false,
				'desc'    => __( 'Hover a tile to preview it larger.', 'fw' ),
				'value'   => 'none',
				'choices' => array(
					'none'  => $tile( 'none',  __( 'None', 'fw' ) ),
					'scene' => $tile( 'scene', __( 'Scene', 'fw' ) ),
					'layer' => $tile( 'layer', __( 'Layer', 'fw' ) ),
				),
			),
		),
		'choices' => array(
			'none'  => array(),
			'scene' => array(
				'source' => array(
					'type'    => 'select',
					'label'   => __( 'Driven by', 'fw' ),
					'desc'    => __( 'What moves the layers.', 'fw' ),
					'value'   => 'mouse',
					'choices' => array(
						'mouse'  => __( 'Pointer', 'fw' ),
						'scroll' => __( 'Scroll', 'fw' ),
						'both'   => __( 'Pointer + Scroll', 'fw' ),
					),
				),
				'intensity' => upw_prlx_slider( __( 'Intensity (px)', 'fw' ), 40, 8, 140, 2, __( 'How far the deepest layers travel at full pointer / scroll.', 'fw' ) ),
				'smoothing' => upw_prlx_slider( __( 'Smoothing', 'fw' ), 50, 0, 100, 5, __( 'Higher = smoother, more lag as layers ease to the pointer.', 'fw' ) ),
			),
			'layer' => array(
				'depth' => upw_prlx_slider( __( 'Depth', 'fw' ), 30, 0, 100, 1, __( 'How much this layer moves. 0 = fixed; higher = closer / more movement.', 'fw' ) ),
				'axis'  => array(
					'type'    => 'select',
					'label'   => __( 'Axis', 'fw' ),
					'value'   => 'both',
					'choices' => array( 'both' => __( 'Both', 'fw' ), 'x' => __( 'Horizontal only', 'fw' ), 'y' => __( 'Vertical only', 'fw' ) ),
				),
				'direction' => array(
					'type'    => 'select',
					'label'   => __( 'Direction', 'fw' ),
					'desc'    => __( '"Against" moves opposite the pointer — the classic background-recedes feel.', 'fw' ),
					'value'   => 'with',
					'choices' => array( 'with' => __( 'With the pointer', 'fw' ), 'against' => __( 'Against the pointer', 'fw' ) ),
				),
				'scale_far' => upw_prlx_switch( __( 'Scale with depth', 'fw' ), __( 'Deeper layers sit slightly larger, hiding their edges as they move.', 'fw' ) ),
				'blur_far'  => upw_prlx_switch( __( 'Depth blur', 'fw' ), __( 'A subtle blur that grows with depth (depth-of-field).', 'fw' ) ),
			),
		),
	);

	return $fields;
} );

/* ------------------------------------------------------------------ *
 * 2) Emit the role + settings onto the element wrapper.
 * ------------------------------------------------------------------ */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
	if ( ! upw_parallax_enabled() ) {
		return $attr;
	}
	$pl   = ( isset( $atts['parallax'] ) && is_array( $atts['parallax'] ) ) ? $atts['parallax'] : array();
	$role = isset( $pl['role'] ) ? (string) $pl['role'] : 'none';
	if ( $role !== 'scene' && $role !== 'layer' ) {
		return $attr;
	}
	$o = ( isset( $pl[ $role ] ) && is_array( $pl[ $role ] ) ) ? $pl[ $role ] : array();

	$cls = isset( $attr['class'] ) ? trim( (string) $attr['class'] ) : '';

	if ( $role === 'scene' ) {
		$attr['class']            = esc_attr( trim( $cls . ' sc-parallax-scene' ) );
		$attr['data-pl-scene']    = esc_attr( isset( $o['source'] ) && in_array( $o['source'], array( 'mouse', 'scroll', 'both' ), true ) ? $o['source'] : 'mouse' );
		$attr['data-pl-intensity'] = esc_attr( (string) ( isset( $o['intensity'] ) ? (float) $o['intensity'] : 40 ) );
		$attr['data-pl-smooth']    = esc_attr( (string) ( isset( $o['smoothing'] ) ? (float) $o['smoothing'] : 50 ) );
	} else { // layer
		$attr['class']         = esc_attr( trim( $cls . ' sc-parallax-layer' ) );
		$attr['data-pl-depth'] = esc_attr( (string) ( isset( $o['depth'] ) ? (float) $o['depth'] : 30 ) );
		$attr['data-pl-axis']  = esc_attr( isset( $o['axis'] ) && in_array( $o['axis'], array( 'both', 'x', 'y' ), true ) ? $o['axis'] : 'both' );
		$attr['data-pl-dir']   = esc_attr( ( isset( $o['direction'] ) && $o['direction'] === 'against' ) ? 'against' : 'with' );
		if ( isset( $o['scale_far'] ) && $o['scale_far'] === 'yes' ) { $attr['data-pl-scale'] = '1'; }
		if ( isset( $o['blur_far'] ) && $o['blur_far'] === 'yes' ) { $attr['data-pl-blur'] = '1'; }
	}

	upw_parallax_flag( true );
	return $attr;
}, 21, 2 );

/* ------------------------------------------------------------------ *
 * 2b) Force a wrapper when an element's ONLY non-default setting is a parallax role.
 * ------------------------------------------------------------------ */
add_filter( 'sc_needs_wrapper', function ( $needs, $atts ) {
	if ( $needs || ! upw_parallax_enabled() ) {
		return $needs;
	}
	$pl   = ( isset( $atts['parallax'] ) && is_array( $atts['parallax'] ) ) ? $atts['parallax'] : array();
	$role = isset( $pl['role'] ) ? (string) $pl['role'] : 'none';
	return ( $role === 'scene' || $role === 'layer' );
}, 10, 2 );

/* ------------------------------------------------------------------ *
 * 3) Enqueue the runtime — only on pages that actually used parallax.
 * ------------------------------------------------------------------ */
add_action( 'wp_footer', function () {
	if ( ! upw_parallax_flag() ) {
		return;
	}
	$ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( ! $ext ) {
		return;
	}
	$base = $ext->get_declared_URI( '/modules/parallax' );
	$ver  = $ext->manifest->get_version();
	$dir  = __DIR__;
	$jsv  = file_exists( "$dir/static/js/parallax.js" )  ? $ver . '.' . filemtime( "$dir/static/js/parallax.js" )  : $ver;
	$cssv = file_exists( "$dir/static/css/parallax.css" ) ? $ver . '.' . filemtime( "$dir/static/css/parallax.css" ) : $ver;

	wp_enqueue_style( 'upw-parallax', $base . '/static/css/parallax.css', array(), $cssv );
	wp_enqueue_script( 'upw-parallax', $base . '/static/js/parallax.js', array(), $jsv, true );

	$cfg = array(
		'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
		'disableMobile' => ( function_exists( 'upw_anim_engine_setting' ) && upw_anim_engine_setting( 'disable_on_mobile', 'no' ) === 'yes' ),
	);
	wp_add_inline_script( 'upw-parallax', 'window.upwParallaxCfg=' . wp_json_encode( $cfg ) . ';', 'before' );
}, 5 );

/* ------------------------------------------------------------------ *
 * 4) Global on/off → Theme Settings → Animations → Parallax sub-tab.
 * ------------------------------------------------------------------ */
add_filter( 'upw_anim_engine_module_tabs', function ( $tabs ) {
	$tabs['parallax_layers'] = array(
		'title'   => __( 'Parallax', 'fw' ),
		'type'    => 'tab',
		'options' => array(
			'parallax_box' => array(
				'title'   => __( 'Parallax Depth Layers', 'fw' ),
				'type'    => 'box',
				'options' => array(
					'animation_parallax' => array(
						'type'          => 'multi',
						'label'         => false,
						'inner-options' => array(
							'enable' => array(
								'label'        => __( 'Enable parallax layers', 'fw' ),
								'desc'         => __( 'Master switch for the per-element Parallax Layers. Off = none load anywhere.', 'fw' ),
								'type'         => 'switch',
								'value'        => 'yes',
								'left-choice'  => array( 'value' => 'no',  'label' => __( 'No', 'fw' ) ),
								'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
							),
						),
					),
				),
			),
		),
	);
	return $tabs;
} );
