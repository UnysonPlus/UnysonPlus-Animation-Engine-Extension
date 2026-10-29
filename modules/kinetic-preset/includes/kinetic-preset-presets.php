<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Kinetic Scenes preset module: the presets a page element can use, by group.
 *
 * GENERATED from the Kinetic Scenes player's preset catalogue (packages/player/src/presets/catalog.ts): each entry is
 * id => array( label, default length in seconds, has an in/out direction ). Presets that need a scene (another
 * layer, sound, a picture's pixels, keyframes) are left out. Regenerate when the catalogue changes.
 */

if ( ! function_exists( 'upw_kp_presets' ) ) :
	function upw_kp_presets() {
		return array(
			'text' => array(
				'text-rise' => array( 'Rise', 0.9, true ),
				'text-slam' => array( 'Slam', 0.8, true ),
				'text-stretch' => array( 'Stretch', 0.9, true ),
				'text-scramble' => array( 'Scramble', 1.1, false ),
				'text-typewriter' => array( 'Typewriter', 1.2, true ),
				'text-blur' => array( 'Blur in', 1, true ),
				'text-flip' => array( 'Flip', 0.9, true ),
				'text-tracking' => array( 'Tracking', 1.2, true ),
				'text-count' => array( 'Count up', 1.6, false ),
				'text-carry' => array( 'Carry in', 0.9, false ),
				'gravity-drop' => array( 'Gravity drop', 0.9, true ),
				'knockout' => array( 'Knockout', 0.5, true ),
				'odometer' => array( 'Odometer', 1.6, false ),
				'timecode' => array( 'Running timecode', 3, false ),
			),
			'reveal' => array(
				'fade' => array( 'Fade', 0.5, true ),
				'wipe' => array( 'Wipe', 0.8, true ),
				'iris' => array( 'Iris', 0.9, true ),
				'slide' => array( 'Slide', 0.6, true ),
				'grow' => array( 'Grow', 0.9, true ),
				'stack-in' => array( 'Stack', 0.55, true ),
				'strip-in' => array( 'Fly-in strip', 0.45, true ),
				'soft-wipe' => array( 'Soft wipe', 0.7, true ),
				'blinds' => array( 'Blinds', 0.8, true ),
			),
			'motion' => array(
				'pop' => array( 'Pop', 0.5, true ),
				'zoom' => array( 'Zoom', 0.6, true ),
				'spin' => array( 'Spin', 0.9, true ),
				'progress' => array( 'Progress bar', 2, false ),
				'turn' => array( 'Turn', 0.7, true ),
			),
			'emphasis' => array(
				'text-wave' => array( 'Wave', 1, false ),
				'pulse' => array( 'Pulse', 1.2, false ),
				'shake' => array( 'Shake', 0.6, false ),
				'float' => array( 'Float', 3, false ),
				'recolor' => array( 'Recolour', 0.6, false ),
				'glow' => array( 'Glow', 0.6, true ),
				'burst' => array( 'Burst', 0.7, false ),
				'strike' => array( 'Strike', 0.9, true ),
				'jiggle' => array( 'Jiggle', 1.2, false ),
				'tick' => array( 'Tick', 0.5, true ),
				'sheen' => array( 'Sheen', 0.9, false ),
			),
		);
	}
endif;
