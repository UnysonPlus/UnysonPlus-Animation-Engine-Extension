<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

$manifest = [];

$manifest['name']        = __( 'WebGL', 'fw' );
$manifest['slug']        = 'unysonplus-webgl';
$manifest['description'] = __(
	'Adds a real-time WebGL "liquid glass" element to the page builder — a refractive glass blob, liquid metal, distorted sphere or particle field, rendered with Three.js. Deactivate to remove it.',
	'fw'
);

$manifest['version']     = '1.0.0';
$manifest['display']     = true;
$manifest['standalone']  = true;

// Needs the shortcodes loader + the page builder to surface [webgl_object].
$manifest['requirements'] = [
	'extensions' => [
		'shortcodes'   => [],
		'page-builder' => [],
	],
];

// Repository Info
$manifest['github_update'] = 'UnysonPlus/UnysonPlus-WebGL-Extension';
$manifest['github_repo']   = 'https://github.com/UnysonPlus/UnysonPlus-WebGL-Extension';
$manifest['github_branch'] = 'master';

// Author Info
$manifest['author']     = 'UnysonPlus';
$manifest['author_uri'] = 'https://www.lastimosa.com.ph/unysonplus';

// Meta
$manifest['license']      = 'GPL-2.0-or-later';
$manifest['text_domain']  = 'fw';
$manifest['requires_php'] = '7.4';
$manifest['requires_wp']  = '5.8';
