<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Studio Scene — edit-modal options.
 *
 * SCENE tab — the scene file (or the Studio's embed code), the "Open in Animation Studio" round trip and a
 *   live summary of what is pasted (see class-fw-shortcode-studio-scene.php + static/js/studio-scene-admin.js).
 * LAYOUT tab — page-level overrides of the scene's own stage settings: how tall it sits, the header offset,
 *   when it starts and whether it loops. "Scene default" leaves the scene's choice alone.
 * ANIMATIONS / ADVANCED — the shared tabs every element gets.
 */

$sel = function ( $label, $default, array $choices, $desc = '' ) {
	return array( 'type' => 'select', 'label' => $label, 'desc' => $desc, 'value' => $default, 'choices' => $choices, 'no-validate' => true );
};

$options = array(
	'tab_scene' => array(
		'type'    => 'tab',
		'title'   => __( 'Scene', 'fw' ),
		'options' => array(
			'scene' => array(
				'type'  => 'textarea',
				'label' => __( 'Scene', 'fw' ),
				'value' => '',
				'desc'  => __( 'Paste a scene file (<code>.scene.json</code>) or the <strong>Embed code</strong> from the Animation Studio’s Export window. Either works. Or use <strong>Open in Animation Studio</strong> below to design it there and save it straight back here.', 'fw' ),
				'attr'  => array( 'rows' => 6, 'spellcheck' => 'false', 'class' => 'upw-studio-scene-field' ),
			),
			'studio_tools' => array(
				'type'  => 'html',
				'label' => false,
				'desc'  => false,
				'html'  => function_exists( 'upw_studio_scene_tools_html' ) ? upw_studio_scene_tools_html() : '',
			),
		),
	),

	'tab_layout' => array(
		'type'    => 'tab',
		'title'   => __( 'Layout', 'fw' ),
		'options' => array(
			'height_mode' => $sel( __( 'Height', 'fw' ), 'scene', array(
				'scene'        => __( 'Scene default', 'fw' ),
				'ratio'        => __( 'Keep the shape (the page scrolls if it is taller than the window)', 'fw' ),
				'ratio-capped' => __( 'Keep the shape, never taller than the window', 'fw' ),
				'fill'         => __( 'Fill the window', 'fw' ),
			), __( 'The scene has a shape per screen size (set in the Studio). This decides how tall it may grow on the page.', 'fw' ) ),
			'height_offset' => array(
				'type'       => 'slider',
				'label'      => __( 'Header height', 'fw' ),
				'desc'       => __( 'Pixels to leave for a sticky site header when the scene is capped to, or fills, the window. 0 uses the scene’s own setting.', 'fw' ),
				'value'      => 0,
				'properties' => array( 'min' => 0, 'max' => 300, 'step' => 1 ),
			),
			'trigger' => $sel( __( 'Starts', 'fw' ), 'scene', array(
				'scene' => __( 'Scene default', 'fw' ),
				'view'  => __( 'When scrolled into view', 'fw' ),
				'load'  => __( 'When the page loads', 'fw' ),
				'scroll' => __( 'As the page scrolls', 'fw' ),
			), __( 'As the page scrolls: scrolling plays the scene forwards and back, with the stage held in place while it plays (unless the scene turns pinning off). Visitors who turn off animation always see the scene’s still frame instead.', 'fw' ) ),
			'scroll_length' => array(
				'type'       => 'slider',
				'label'      => __( 'Scroll length', 'fw' ),
				'desc'       => __( 'For a scene that plays as the page scrolls: how many screens of scrolling it takes while held in place. 0 uses the scene’s own setting.', 'fw' ),
				'value'      => 0,
				'properties' => array( 'min' => 0, 'max' => 6, 'step' => 0.1 ),
			),
			'loop' => $sel( __( 'Loop', 'fw' ), 'scene', array(
				'scene' => __( 'Scene default', 'fw' ),
				'yes'   => __( 'Loop', 'fw' ),
				'no'    => __( 'Play once', 'fw' ),
			) ),
		),
	),

	'tab_animations' => array(
		'type'    => 'tab',
		'title'   => __( 'Animations', 'fw' ),
		'options' => function_exists( 'sc_get_animation_fields' ) ? sc_get_animation_fields() : array(),
	),

	'tab_advanced' => array(
		'type'    => 'tab',
		'title'   => __( 'Advanced', 'fw' ),
		'options' => function_exists( 'sc_get_advanced_tab' ) ? sc_get_advanced_tab() : array(
			'unique_id' => array( 'type' => 'unique' ),
			'group_css' => array(
				'type'    => 'group',
				'options' => array(
					'css_id'     => array( 'type' => 'text', 'label' => __( 'CSS ID', 'fw' ), 'desc' => __( 'Useful for anchor links.', 'fw' ) ),
					'css_class'  => array( 'type' => 'text', 'label' => __( 'CSS Class', 'fw' ), 'desc' => __( 'Lands on the element wrapper.', 'fw' ) ),
					'custom_css' => array( 'type' => 'code-editor', 'label' => __( 'Custom CSS', 'fw' ), 'value' => '', 'mode' => 'css',
						'desc' => __( 'Scoped to THIS element — use the keyword <code>selector</code> for the wrapper.', 'fw' ) ),
				),
			),
		),
	),
);
