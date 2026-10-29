<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

$ext = fw_ext( 'animation-engine' );
if ( ! $ext ) {
	return;
}

$base = $ext->get_declared_URI( '/shortcodes/studio-scene' );

// The scene player: a copy of Kinetic Scenes' published player, served from this site (no
// outside requests). It carries its own animation engine internally and only claims the global
// `gsap` when no other copy is on the page, so it sits safely next to Scroll Motion.
/** Filters the URL of the bundled scene player script. */
$player_src = apply_filters( 'fw_studio_scene_player_src', $base . '/static/js/vendor/scene-player.js' );
wp_enqueue_script( 'upw-scene-player', $player_src, array(), '0.0.44', true );

// The 3D runtime (three.js), loaded by the player only for scenes with a 3D layer. Said explicitly so it
// is still found when an optimiser combines or moves the player script.
/** Filters the folder the player loads scene-3d.js from (with a trailing slash). */
$asset_base = apply_filters( 'fw_studio_scene_asset_base', $base . '/static/js/vendor/' );
wp_add_inline_script( 'upw-scene-player', 'window.UPW_SCENE_ASSET_BASE=' . wp_json_encode( esc_url_raw( $asset_base ) ) . ';', 'before' );

// Re-mount scenes in markup inserted after load (builder previews, live editors).
wp_add_inline_script(
	'upw-scene-player',
	'window.fwShortcodeInit=window.fwShortcodeInit||[];window.fwShortcodeInit.push(function(s){if(window.UnysonPlusScene){window.UnysonPlusScene.autoMount(s&&s.querySelectorAll?s:document);}});'
);
