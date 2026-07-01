<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Page Transitions module (site-wide).
 *
 * A full-screen overlay injected at wp_body_open covers the viewport on first paint and
 * reveals it (entrance, pure CSS so it runs even without JS). On an internal link click the
 * runtime plays the reverse (cover) then navigates, so pages feel connected. An optional
 * first-visit loader shows a spinner/bar/dots until the page finishes loading. Config lives
 * in Theme Settings → Animations → Page Transitions; nothing loads in admin or when disabled.
 */

if ( ! function_exists( 'upw_pt_setting' ) ) :
	function upw_pt_setting( $key, $default = '' ) {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return $default;
		}
		$v = fw_get_db_settings_option( 'animation_pt', array() );
		if ( is_array( $v ) && isset( $v[ $key ] ) && $v[ $key ] !== '' && $v[ $key ] !== null ) {
			return is_bool( $v[ $key ] ) ? ( $v[ $key ] ? 'yes' : 'no' ) : $v[ $key ];
		}
		return $default;
	}
endif;

if ( ! function_exists( 'upw_pt_enabled' ) ) :
	function upw_pt_enabled() {
		return upw_pt_setting( 'enable', 'no' ) === 'yes' && ! is_admin();
	}
endif;

if ( ! function_exists( 'upw_pt_types' ) ) :
	function upw_pt_types() {
		return array(
			'fade', 'slide', 'zoom', 'rotate', 'curtain', 'doors', 'split', 'wipe', 'diagonal',
			'bars', 'stripes', 'blinds', 'reveal', 'shape', 'iris', 'glitch', 'flip',
			'checkerboard', 'pixels', 'ripple', 'conic', 'morph', 'contentfade',
		);
	}
endif;

if ( ! function_exists( 'upw_pt_resolve' ) ) :
	/** Read the transition multi-picker into a normalized [ type, dir, count, total ]. */
	function upw_pt_resolve() {
		$tr   = upw_pt_setting( 'transition', array() );
		$type = ( is_array( $tr ) && ! empty( $tr['transition'] ) ) ? (string) $tr['transition'] : 'fade';
		if ( ! in_array( $type, upw_pt_types(), true ) ) { $type = 'fade'; }
		$sub  = ( is_array( $tr ) && isset( $tr[ $type ] ) && is_array( $tr[ $type ] ) ) ? $tr[ $type ] : array();
		$dur  = (float) upw_pt_setting( 'duration', 0.6 );
		$dir   = '';
		$count = 0;
		$total = $dur;
		switch ( $type ) {
			case 'slide':
				$dir = in_array( ( $sub['direction'] ?? 'up' ), array( 'up', 'down', 'left', 'right' ), true ) ? $sub['direction'] : 'up';
				break;
			case 'wipe':
				$dir = in_array( ( $sub['direction'] ?? 'left' ), array( 'left', 'right', 'up', 'down' ), true ) ? $sub['direction'] : 'left';
				break;
			case 'curtain':
				$dir = ( ( $sub['split'] ?? 'vertical' ) === 'horizontal' ) ? 'horizontal' : 'vertical';
				break;
			case 'reveal':
				$dir = in_array( ( $sub['origin'] ?? 'center' ), array( 'center', 'tl', 'tr', 'bl', 'br' ), true ) ? $sub['origin'] : 'center';
				break;
			case 'diagonal':
				$dir = ( ( $sub['direction'] ?? 'tlbr' ) === 'trbl' ) ? 'trbl' : 'tlbr';
				break;
			case 'split':
				$dir = ( ( $sub['direction'] ?? 'vertical' ) === 'horizontal' ) ? 'horizontal' : 'vertical';
				break;
			case 'shape':
				$dir = in_array( ( $sub['shape'] ?? 'circle' ), array( 'circle', 'square', 'diamond' ), true ) ? $sub['shape'] : 'circle';
				break;
			case 'flip':
				$dir = ( ( $sub['axis'] ?? 'y' ) === 'x' ) ? 'x' : 'y';
				break;
			case 'blinds':
				$dir   = ( ( $sub['direction'] ?? 'vertical' ) === 'horizontal' ) ? 'horizontal' : 'vertical';
				$count = max( 3, min( 10, (int) ( $sub['count'] ?? 6 ) ) );
				$total = $dur + ( $count - 1 ) * 0.07; // staggered strips
				break;
			case 'checkerboard':
			case 'pixels':
				$count = max( 8, min( 20, (int) ( $sub['density'] ?? 12 ) ) );
				$total = $dur + 0.5; // grid stagger
				break;
		}
		return array( 'type' => $type, 'dir' => $dir, 'count' => $count, 'dur' => $dur, 'total' => $total );
	}
endif;

/* ------------------------------------------------------------------ *
 * 1) Inject the overlay (+ optional loader) at the very top of <body>.
 * ------------------------------------------------------------------ */
add_action( 'wp_body_open', function () {
	if ( ! upw_pt_enabled() ) {
		return;
	}
	$r     = upw_pt_resolve();
	$type  = $r['type'];
	$color = function_exists( 'sc_color_to_css' ) ? sc_color_to_css( upw_pt_setting( 'color', '' ), '#0e1524' ) : '#0e1524';
	$style = '--pt-color:' . esc_attr( $color ) . '; --pt-dur:' . esc_attr( $r['dur'] ) . 's;';
	if ( $r['count'] ) { $style .= ' --pt-cells:' . (int) $r['count'] . ';'; }

	$attrs = 'data-pt-type="' . esc_attr( $type ) . '" data-pt-total="' . esc_attr( $r['total'] ) . '"';
	if ( $r['dir'] !== '' ) { $attrs .= ' data-pt-dir="' . esc_attr( $r['dir'] ) . '"'; }
	if ( $r['count'] ) { $attrs .= ' data-pt-count="' . (int) $r['count'] . '"'; }

	// Inner markup: strips for blinds, a cell grid for checkerboard/pixels, else two panels.
	$inner = '';
	if ( $type === 'blinds' ) {
		for ( $i = 0; $i < $r['count']; $i++ ) {
			$inner .= '<span class="upw-pt__strip" style="--i:' . $i . ';"></span>';
		}
	} elseif ( $type === 'checkerboard' || $type === 'pixels' ) {
		$cols = (int) $r['count'];
		$rows = max( 4, (int) ceil( $cols * 9 / 16 ) );
		$style .= ' --pt-cols:' . $cols . '; --pt-rows:' . $rows . ';';
		$n = $cols * $rows;
		for ( $i = 0; $i < $n; $i++ ) {
			$d = ( $type === 'pixels' ) ? ( mt_rand( 0, 100 ) / 100 ) : ( ( ( $i % $cols ) + intval( $i / $cols ) ) % 2 ? 0.14 : 0 );
			$inner .= '<span class="upw-pt__cell" style="--d:' . $d . 's;"></span>';
		}
	} else {
		$inner = '<span class="upw-pt__p upw-pt__p1"></span><span class="upw-pt__p upw-pt__p2"></span>';
	}

	echo '<div class="upw-pt" ' . $attrs . ' style="' . $style . '" aria-hidden="true">' . $inner . '</div>'; // phpcs:ignore -- all values escaped above

	if ( upw_pt_setting( 'loader', 'no' ) === 'yes' ) {
		$lstyle = in_array( upw_pt_setting( 'loader_style', 'spinner' ), array( 'spinner', 'bar', 'dots' ), true ) ? upw_pt_setting( 'loader_style', 'spinner' ) : 'spinner';
		echo '<div class="upw-pt-loader" data-pt-loader="' . esc_attr( $lstyle ) . '" style="' . $style . '" aria-hidden="true"><span class="upw-pt-loader__box"><i></i><i></i><i></i></span></div>';
	}
}, 1 );

// Content Fade-Up rises the page content in on load — flag it on <body> so the CSS runs
// from the first paint (body_class() is emitted just before wp_body_open).
add_filter( 'body_class', function ( $classes ) {
	if ( upw_pt_enabled() ) {
		$r = upw_pt_resolve();
		if ( $r['type'] === 'contentfade' ) { $classes[] = 'upw-pt-cin'; }
	}
	return $classes;
} );

/* ------------------------------------------------------------------ *
 * 2) Enqueue CSS (head) + JS (footer) — front end only, when enabled.
 * ------------------------------------------------------------------ */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! upw_pt_enabled() ) {
		return;
	}
	$ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	if ( ! $ext ) {
		return;
	}
	$base = $ext->get_declared_URI( '/modules/page-transitions' );
	$ver  = $ext->manifest->get_version();
	$dir  = __DIR__;
	$jsv  = file_exists( "$dir/static/js/page-transitions.js" )  ? $ver . '.' . filemtime( "$dir/static/js/page-transitions.js" )  : $ver;
	$cssv = file_exists( "$dir/static/css/page-transitions.css" ) ? $ver . '.' . filemtime( "$dir/static/css/page-transitions.css" ) : $ver;

	wp_enqueue_style( 'upw-pt', $base . '/static/css/page-transitions.css', array(), $cssv );
	wp_enqueue_script( 'upw-pt', $base . '/static/js/page-transitions.js', array(), $jsv, true );

	$cfg = array(
		'duration'      => (float) upw_pt_setting( 'duration', 0.6 ),
		'loader'        => upw_pt_setting( 'loader', 'no' ) === 'yes',
		'reducedMotion' => ( ! function_exists( 'upw_anim_engine_setting' ) || upw_anim_engine_setting( 'respect_reduced_motion', 'yes' ) !== 'no' ),
	);
	wp_add_inline_script( 'upw-pt', 'window.upwPtCfg=' . wp_json_encode( $cfg ) . ';', 'before' );
}, 5 );

/* ------------------------------------------------------------------ *
 * 3) Theme Settings → Animations → Page Transitions sub-tab.
 * ------------------------------------------------------------------ */
add_filter( 'upw_anim_engine_module_tabs', function ( $tabs ) {
	$sw = function ( $label, $desc, $default_yes ) {
		return array(
			'type'         => 'switch',
			'label'        => $label,
			'desc'         => $desc,
			'value'        => $default_yes ? 'yes' : 'no',
			'left-choice'  => array( 'value' => 'no',  'label' => __( 'No', 'fw' ) ),
			'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
		);
	};
	$color = function_exists( 'sc_color_field_compact' )
		? sc_color_field_compact( array( 'label' => __( 'Overlay color', 'fw' ), 'kind' => 'bg', 'value' => array( 'predefined' => '', 'custom' => '#0e1524' ) ) )
		: array( 'type' => 'color-picker', 'label' => __( 'Overlay color', 'fw' ), 'value' => '#0e1524' );

	// Transition image-picker tiles.
	$pt_ext  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
	$pt_base = $pt_ext ? $pt_ext->get_declared_URI( '/modules/page-transitions/static/img/transitions' ) : '';
	$pt_tile = function ( $file, $label ) use ( $pt_base ) {
		return array(
			'small' => array( 'src' => $pt_base . '/' . $file . '.svg', 'height' => 60 ),
			'large' => array( 'src' => $pt_base . '/' . $file . '.svg', 'height' => 120 ),
			'label' => $label,
		);
	};
	$pt_sel = function ( $label, $default, $choices ) {
		return array( 'type' => 'select', 'label' => $label, 'value' => $default, 'choices' => $choices );
	};
	$dir4     = array( 'up' => __( 'Up', 'fw' ), 'down' => __( 'Down', 'fw' ), 'left' => __( 'Left', 'fw' ), 'right' => __( 'Right', 'fw' ) );
	$wipe4    = array( 'left' => __( 'Left', 'fw' ), 'right' => __( 'Right', 'fw' ), 'up' => __( 'Up', 'fw' ), 'down' => __( 'Down', 'fw' ) );
	$orient   = array( 'vertical' => __( 'Vertical', 'fw' ), 'horizontal' => __( 'Horizontal', 'fw' ) );

	$pt_transition = array(
		'type'         => 'multi-picker',
		'label'        => __( 'Transition', 'fw' ),
		'desc'         => __( 'How pages reveal on load and cover when you navigate.', 'fw' ),
		'popover'      => true,
		'show_borders' => false,
		'value'        => array( 'transition' => 'fade' ),
		'picker'       => array(
			'transition' => array(
				'type'    => 'image-picker',
				'label'   => false,
				'desc'    => __( 'Hover a tile to preview it larger.', 'fw' ),
				'value'   => 'fade',
				'choices' => array(
					'fade'         => $pt_tile( 'fade',         __( 'Fade', 'fw' ) ),
					'slide'        => $pt_tile( 'slide',        __( 'Slide', 'fw' ) ),
					'zoom'         => $pt_tile( 'zoom',         __( 'Zoom', 'fw' ) ),
					'rotate'       => $pt_tile( 'rotate',       __( 'Rotate', 'fw' ) ),
					'curtain'      => $pt_tile( 'curtain',      __( 'Curtain', 'fw' ) ),
					'doors'        => $pt_tile( 'doors',        __( 'Doors', 'fw' ) ),
					'split'        => $pt_tile( 'split',        __( 'Split', 'fw' ) ),
					'wipe'         => $pt_tile( 'wipe',         __( 'Wipe', 'fw' ) ),
					'diagonal'     => $pt_tile( 'diagonal',     __( 'Diagonal', 'fw' ) ),
					'bars'         => $pt_tile( 'bars',         __( 'Bars', 'fw' ) ),
					'stripes'      => $pt_tile( 'stripes',      __( 'Stripes', 'fw' ) ),
					'blinds'       => $pt_tile( 'blinds',       __( 'Blinds', 'fw' ) ),
					'reveal'       => $pt_tile( 'reveal',       __( 'Circle Reveal', 'fw' ) ),
					'shape'        => $pt_tile( 'shape',        __( 'Shape Reveal', 'fw' ) ),
					'iris'         => $pt_tile( 'iris',         __( 'Iris', 'fw' ) ),
					'glitch'       => $pt_tile( 'glitch',       __( 'Glitch', 'fw' ) ),
					'flip'         => $pt_tile( 'flip',         __( 'Flip 3D', 'fw' ) ),
					'checkerboard' => $pt_tile( 'checkerboard', __( 'Checkerboard', 'fw' ) ),
					'pixels'       => $pt_tile( 'pixels',       __( 'Pixel Dissolve', 'fw' ) ),
					'ripple'       => $pt_tile( 'ripple',       __( 'Ripple (click)', 'fw' ) ),
					'conic'        => $pt_tile( 'conic',        __( 'Conic Wipe', 'fw' ) ),
					'morph'        => $pt_tile( 'morph',        __( 'Morph Blob', 'fw' ) ),
					'contentfade'  => $pt_tile( 'contentfade',  __( 'Content Fade-Up', 'fw' ) ),
				),
			),
		),
		'choices' => array(
			'slide'        => array( 'direction' => $pt_sel( __( 'Direction', 'fw' ), 'up', $dir4 ) ),
			'wipe'         => array( 'direction' => $pt_sel( __( 'Direction', 'fw' ), 'left', $wipe4 ) ),
			'curtain'      => array( 'split' => $pt_sel( __( 'Split', 'fw' ), 'vertical', $orient ) ),
			'split'        => array( 'direction' => $pt_sel( __( 'Split', 'fw' ), 'vertical', $orient ) ),
			'reveal'       => array( 'origin' => $pt_sel( __( 'Origin', 'fw' ), 'center', array( 'center' => __( 'Center', 'fw' ), 'tl' => __( 'Top-left', 'fw' ), 'tr' => __( 'Top-right', 'fw' ), 'bl' => __( 'Bottom-left', 'fw' ), 'br' => __( 'Bottom-right', 'fw' ) ) ) ),
			'diagonal'     => array( 'direction' => $pt_sel( __( 'Direction', 'fw' ), 'tlbr', array( 'tlbr' => __( 'Top-left → Bottom-right', 'fw' ), 'trbl' => __( 'Top-right → Bottom-left', 'fw' ) ) ) ),
			'shape'        => array( 'shape' => $pt_sel( __( 'Shape', 'fw' ), 'circle', array( 'circle' => __( 'Circle', 'fw' ), 'square' => __( 'Square', 'fw' ), 'diamond' => __( 'Diamond', 'fw' ) ) ) ),
			'flip'         => array( 'axis' => $pt_sel( __( 'Axis', 'fw' ), 'y', array( 'y' => __( 'Vertical (Y)', 'fw' ), 'x' => __( 'Horizontal (X)', 'fw' ) ) ) ),
			'blinds'       => array(
				'direction' => $pt_sel( __( 'Orientation', 'fw' ), 'vertical', $orient ),
				'count'     => array( 'type' => 'slider', 'label' => __( 'Strips', 'fw' ), 'value' => 6, 'properties' => array( 'min' => 3, 'max' => 10, 'step' => 1 ) ),
			),
			'checkerboard' => array( 'density' => array( 'type' => 'slider', 'label' => __( 'Columns', 'fw' ), 'value' => 12, 'properties' => array( 'min' => 8, 'max' => 20, 'step' => 1 ) ) ),
			'pixels'       => array( 'density' => array( 'type' => 'slider', 'label' => __( 'Columns', 'fw' ), 'value' => 14, 'properties' => array( 'min' => 8, 'max' => 20, 'step' => 1 ) ) ),
		),
	);

	$tabs['page_transitions'] = array(
		'title'   => __( 'Page Transitions', 'fw' ),
		'type'    => 'tab',
		'options' => array(
			'pt_box' => array(
				'title'   => __( 'Page Transitions', 'fw' ),
				'type'    => 'box',
				'options' => array(
					'animation_pt' => array(
						'type'          => 'multi',
						'label'         => false,
						'inner-options' => array(
							'enable' => $sw(
								__( 'Enable page transitions', 'fw' ),
								__( 'A full-screen overlay reveals each page on load and covers it when you navigate — so pages feel connected. Front end only.', 'fw' ) . ( function_exists( 'upw_perf_note' ) ? ' ' . upw_perf_note( 'site' ) : '' ),
								false
							),
							'transition' => $pt_transition,
							'color'    => $color,
							'duration' => array(
								'type'       => 'slider',
								'label'      => __( 'Duration (s)', 'fw' ),
								'value'      => 0.6,
								'properties' => array( 'min' => 0.2, 'max' => 1.5, 'step' => 0.1 ),
							),
							'loader'       => $sw( __( 'First-visit loader', 'fw' ), __( 'Show a loading indicator on the visitor’s first page of the session, until it finishes loading.', 'fw' ), false ),
							'loader_style' => array(
								'type'    => 'select',
								'label'   => __( 'Loader style', 'fw' ),
								'value'   => 'spinner',
								'choices' => array( 'spinner' => __( 'Spinner', 'fw' ), 'bar' => __( 'Bar', 'fw' ), 'dots' => __( 'Dots', 'fw' ) ),
							),
						),
					),
				),
			),
		),
	);
	return $tabs;
} );
