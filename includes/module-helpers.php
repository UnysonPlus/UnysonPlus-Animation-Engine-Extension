<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — shared module helpers.
 *
 * Small option-field builders used by many modules' settings. Kept in ONE place so the per-module
 * copies (which were byte-identical) can't drift. Loaded early (before the modules) so a module's
 * settings/helpers file can rely on these being defined. Modules keep their own thin, named wrappers
 * (e.g. upw_prlx_slider) that delegate here — the call sites don't change, only the logic is DRY.
 */

if ( ! function_exists( 'upw_anim_slider' ) ) :
	/**
	 * A `slider` option field: value + min/max/step, plus an optional description.
	 *
	 * @return array Unyson option definition.
	 */
	function upw_anim_slider( $label, $val, $min, $max, $step, $desc = '' ) {
		$f = array(
			'type'       => 'slider',
			'label'      => $label,
			'value'      => $val,
			'properties' => array( 'min' => $min, 'max' => $max, 'step' => $step ),
		);
		if ( $desc !== '' ) {
			$f['desc'] = $desc;
		}
		return $f;
	}
endif;
