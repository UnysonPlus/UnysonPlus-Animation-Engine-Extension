<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Page Transitions module: Theme Settings → Animations → Page Transitions sub-tab.
 *
 * Registers the Page Transitions sub-tab (an enable switch + a popover transition multi-picker with
 * per-type option reveals, overlay color, duration, and first-visit loader) via the engine's
 * `upw_anim_engine_module_tabs` filter. Depends on upw_pt_types() from page-transitions-helpers.php.
 */

/* ------------------------------------------------------------------ *
 * 3) Theme Settings → Animations → Page Transitions sub-tab.
 * ------------------------------------------------------------------ */
add_filter( 'upw_anim_engine_module_tabs', function ( $tabs ) {
	$sw = function ( $label, $desc, $default_yes, $help = '' ) {
		return array(
			'type'         => 'switch',
			'label'        => $label,
			'desc'         => $desc,
			'help'         => $help,
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
			'small' => array( 'src' => $pt_base . '/' . $file . '.svg', 'height' => 66 ),
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
					'bars'         => $pt_tile( 'bars',         __( 'Bars', 'fw' ) ),
					'blinds'       => $pt_tile( 'blinds',       __( 'Blinds', 'fw' ) ),
					'checkerboard' => $pt_tile( 'checkerboard', __( 'Checkerboard', 'fw' ) ),
					'reveal'       => $pt_tile( 'reveal',       __( 'Circle Reveal', 'fw' ) ),
					'conic'        => $pt_tile( 'conic',        __( 'Conic Wipe', 'fw' ) ),
					'contentfade'  => $pt_tile( 'contentfade',  __( 'Content Fade-Up', 'fw' ) ),
					'curtain'      => $pt_tile( 'curtain',      __( 'Curtain', 'fw' ) ),
					'diagonal'     => $pt_tile( 'diagonal',     __( 'Diagonal', 'fw' ) ),
					'doors'        => $pt_tile( 'doors',        __( 'Doors', 'fw' ) ),
					'fade'         => $pt_tile( 'fade',         __( 'Fade', 'fw' ) ),
					'flip'         => $pt_tile( 'flip',         __( 'Flip 3D', 'fw' ) ),
					'glitch'       => $pt_tile( 'glitch',       __( 'Glitch', 'fw' ) ),
					'iris'         => $pt_tile( 'iris',         __( 'Iris', 'fw' ) ),
					'morph'        => $pt_tile( 'morph',        __( 'Morph Blob', 'fw' ) ),
					'pixels'       => $pt_tile( 'pixels',       __( 'Pixel Dissolve', 'fw' ) ),
					'ripple'       => $pt_tile( 'ripple',       __( 'Ripple (click)', 'fw' ) ),
					'rotate'       => $pt_tile( 'rotate',       __( 'Rotate', 'fw' ) ),
					'shape'        => $pt_tile( 'shape',        __( 'Shape Reveal', 'fw' ) ),
					'slide'        => $pt_tile( 'slide',        __( 'Slide', 'fw' ) ),
					'split'        => $pt_tile( 'split',        __( 'Split', 'fw' ) ),
					'stripes'      => $pt_tile( 'stripes',      __( 'Stripes', 'fw' ) ),
					'wipe'         => $pt_tile( 'wipe',         __( 'Wipe', 'fw' ) ),
					'zoom'         => $pt_tile( 'zoom',         __( 'Zoom', 'fw' ) ),
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
								__( 'A full-screen overlay reveals each page on load and covers it when you navigate — so pages feel connected. Front end only.', 'fw' ),
								false,
								function_exists( 'upw_perf_note' ) ? upw_perf_note( 'site' ) : ''
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
