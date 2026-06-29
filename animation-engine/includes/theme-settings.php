<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine → Appearance → Theme Settings → "Animations".
 *
 * Adds an "Animations" section to the Theme Settings page and forces that page to
 * exist under ANY active theme (the same mechanism the plugin's Component Presets /
 * Miscellaneous built-ins use). This is the home for the engine's GLOBAL options;
 * each module (WebGL today; hover, scroll-motion, … later) contributes its own
 * sub-tab here. Values are stored theme-scoped in fw_theme_settings_options:{theme-id}.
 */

// Make Appearance → Theme Settings available even on a theme that ships no
// settings.php of its own, so the Animations section always has a host.
add_filter( 'fw_theme_settings_menu_register', '__return_true' );

if ( ! function_exists( 'upw_anim_engine_settings_section' ) ) :
	/**
	 * The "Animations" nav section: a box → group of global engine options, plus a
	 * per-module area. Returns the section keyed `animation_engine_container`.
	 */
	function upw_anim_engine_settings_section() {
		$module_tabs = apply_filters( 'upw_anim_engine_module_tabs', array() );

		$engine_tab = array(
			'engine_general' => array(
				'title'   => __( 'Engine', 'fw' ),
				'type'    => 'tab',
				'options' => array(
					'engine_box' => array(
						'title'   => __( 'Animation Engine', 'fw' ),
						'type'    => 'box',
						'options' => array(
							'animation_engine' => array(
								'type'          => 'multi',
								'label'         => false,
								'inner-options' => array(
									'respect_reduced_motion' => array(
										'label'        => __( 'Respect "reduce motion"', 'fw' ),
										'desc'         => __( 'Disable engine animations for visitors who set the OS "reduce motion" preference. Recommended.', 'fw' ),
										'type'         => 'switch',
										'value'        => 'yes',
										'left-choice'  => array( 'value' => 'no',  'label' => __( 'No', 'fw' ) ),
										'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
									),
									'disable_on_mobile' => array(
										'label'        => __( 'Disable heavy effects on mobile', 'fw' ),
										'desc'         => __( 'Skip GPU-heavy effects (WebGL, physics) on phones (< 768px).', 'fw' ),
										'type'         => 'switch',
										'value'        => 'no',
										'left-choice'  => array( 'value' => 'no',  'label' => __( 'No', 'fw' ) ),
										'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
									),
								),
							),
						),
					),
				),
			),
		);

		// Modules append their own sub-tabs after the Engine tab.
		$sub_tabs = array_merge( $engine_tab, is_array( $module_tabs ) ? $module_tabs : array() );

		return array(
			'animation_engine_container' => array(
				'title'   => __( 'Animations', 'fw' ),
				'type'    => 'tab',
				'options' => $sub_tabs,
			),
		);
	}
endif;

add_filter( 'fw_settings_options', function ( $options ) {
	if ( ! is_array( $options ) ) {
		return $options;
	}
	$options[] = upw_anim_engine_settings_section();
	return $options;
} );

if ( ! function_exists( 'upw_anim_engine_setting' ) ) :
	/**
	 * Read a global Animation Engine setting (theme-scoped). Modules use this to honour
	 * the engine's global policy (e.g. reduced motion, disable-on-mobile).
	 *
	 * @param string $key     Leaf id inside the `animation_engine` multi (e.g. 'respect_reduced_motion').
	 * @param mixed  $default Returned when unset.
	 * @return mixed
	 */
	function upw_anim_engine_setting( $key, $default = '' ) {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return $default;
		}
		$data = fw_get_db_settings_option( 'animation_engine', array() );
		if ( is_array( $data ) && array_key_exists( $key, $data ) ) {
			$val = $data[ $key ];
			if ( $val !== null && $val !== '' ) {
				return is_bool( $val ) ? ( $val ? 'yes' : 'no' ) : $val;
			}
		}
		return $default;
	}
endif;
