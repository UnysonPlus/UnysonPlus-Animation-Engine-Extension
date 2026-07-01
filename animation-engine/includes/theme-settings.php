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

if ( ! function_exists( 'upw_perf_note' ) ) :
	/**
	 * Shared "only loads when used" reassurance, surfaced on the animation pickers so
	 * users see — at the point of choice — that the engine won't bloat their pages.
	 * Kept HONEST: the runtime is enqueued per-PAGE (not per-effect — one file carries a
	 * category's effects, and shared libraries like GSAP load for any effect), so the
	 * accurate promise is "loads only on pages that use it", not "only the selected one".
	 *
	 * @param string $scope 'page' (per-element effects) | 'site' (site-wide, e.g. cursor)
	 */
	function upw_perf_note( $scope = 'page' ) {
		return $scope === 'site'
			? __( '⚡ Loads only when enabled — on the front end, never in admin.', 'fw' )
			: __( '⚡ Loads only on pages that use it — pages without it ship none of this code.', 'fw' );
	}
endif;

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
							'engine_perf_note' => array(
								'type'  => 'html',
								'label' => false,
								'html'  => '<div style="padding:12px 14px;border:1px dashed #c3d9f0;border-radius:6px;background:#f4f9ff;color:#3a4a5c;font-size:13px;line-height:1.55;">'
									. '<strong style="color:#2f74e6;">' . esc_html__( 'Built for performance', 'fw' ) . '</strong><br>'
									. esc_html__( 'Every effect — Scroll Motion, Hover, WebGL, Cursor — is loaded only on the pages that actually use it; pages without it ship none of its code. Whatever a page does use is combined into one minified file by the Asset Optimizer. Activating the engine adds capabilities, not weight.', 'fw' )
									. '</div>',
							),
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

		// Wrap the sub-tabs in a titled box so this section matches the theme's other
		// functional settings tabs (General / Header / Blog / Social / Footer), which
		// all use tab → box("… Settings") → sub-tabs. (Demo Options is the intentional
		// heading-less exception; the Animations tab is real settings, so it follows
		// the rest.)
		return array(
			'animation_engine_container' => array(
				'title'   => __( 'Animations', 'fw' ),
				'type'    => 'tab',
				'options' => array(
					'animation_settings_box' => array(
						'title'   => __( 'Animation Settings', 'fw' ),
						'type'    => 'box',
						'options' => $sub_tabs,
					),
				),
			),
		);
	}
endif;

// Priority 20 so it runs AFTER the shortcodes extension has merged its built-ins into
// the Miscellaneous section (priority 10) — the Misc section is then present to anchor
// against. We insert the Animations section just BEFORE Miscellaneous, so Miscellaneous
// (and Demo Options, which the theme adds after Misc) remain the last tabs.
add_filter( 'fw_settings_options', function ( $options ) {
	if ( ! is_array( $options ) ) {
		return $options;
	}

	$section    = upw_anim_engine_settings_section();
	$misc_index = null;
	foreach ( $options as $i => $sec ) {
		if ( is_array( $sec ) && isset( $sec['misc_container'] ) ) {
			$misc_index = $i;
			break;
		}
	}

	if ( $misc_index !== null ) {
		array_splice( $options, $misc_index, 0, array( $section ) );
	} else {
		$options[] = $section; // no Miscellaneous section — just append
	}

	return $options;
}, 20 );

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
