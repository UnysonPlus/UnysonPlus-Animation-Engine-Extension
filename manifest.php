<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

$manifest = [];

$manifest['name']        = __( 'Animation Engine', 'fw' );
$manifest['slug']        = 'unysonplus-animation-engine';
$manifest['description'] = __(
	'The home for UnysonPlus\'s animation capabilities. Its first module is WebGL — a real-time refractive glass blob, liquid metal, distorted sphere or particle field rendered with Three.js (the [webgl_object] page-builder element). Adds a "Site-wide UX" section to Theme Settings. Other modules plug in over time: Scroll Motion, Hover, Cursor, Text Effects, Backgrounds, Page Transitions, and a Seamless / Infinite Scroll Loop (Lenis) that turns a run of full-height sections into a never-ending, snapping scroll experience.',
	'fw'
);

$manifest['version']     = '1.1.36';
$manifest['display']     = true;
$manifest['standalone']  = true;
$manifest['thumbnail']   = 'thumbnail.svg';

// Needs the shortcodes loader + the page builder to surface [webgl_object].
$manifest['requirements'] = [
	'extensions' => [
		'shortcodes'   => [],
		'page-builder' => [],
	],
];

// Repository Info
$manifest['github_update'] = 'UnysonPlus/UnysonPlus-Animation-Engine-Extension';
$manifest['github_repo']   = 'https://github.com/UnysonPlus/UnysonPlus-Animation-Engine-Extension';
$manifest['github_branch'] = 'master';

// Author Info
$manifest['author']     = 'UnysonPlus';
$manifest['author_uri'] = 'https://www.lastimosa.com.ph/unysonplus';

// Meta
$manifest['license']      = 'GPL-2.0-or-later';
$manifest['text_domain']  = 'fw';
$manifest['requires_php'] = '7.4';
$manifest['requires_wp']  = '5.8';
