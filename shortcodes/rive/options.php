<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Rive Animation — edit-modal options.
 *
 * ANIMATION tab — the .riv source, which artboard / state machine / animation to run, and how it plays
 *   (autoplay, on view, on hover, on click, or scroll-scrubbed through a State Machine number input).
 * STYLE tab — how the canvas fits (fit + alignment), its height, and background.
 * ADVANCED tab — CSS id/class + Custom CSS, and a fallback poster (no-WebGL / reduced-motion).
 */

$sel = function ( $label, $default, array $choices, $desc = '' ) {
	return array( 'type' => 'select', 'label' => $label, 'desc' => $desc, 'value' => $default, 'choices' => $choices, 'no-validate' => true );
};
$sld = function ( $label, $value, $min, $max, $step = 1, $desc = '' ) {
	return array( 'type' => 'slider', 'label' => $label, 'desc' => $desc, 'value' => $value, 'properties' => array( 'min' => $min, 'max' => $max, 'step' => $step ) );
};
$txt = function ( $label, $desc = '', $value = '' ) {
	return array( 'type' => 'text', 'label' => $label, 'desc' => $desc, 'value' => $value );
};

$options = array(
	'tab_anim' => array(
		'type'    => 'tab',
		'title'   => __( 'Animation', 'fw' ),
		'options' => array(
			'src'     => array( 'type' => 'upload', 'label' => __( 'Rive file (.riv)', 'fw' ), 'value' => array(),
				'desc' => __( 'Upload or pick a <code>.riv</code> file from the Media Library. Export one from the Rive editor (rive.app).', 'fw' ) ),
			'src_url' => $txt( __( '…or a .riv URL', 'fw' ), __( 'Paste a direct <code>.riv</code> URL instead of uploading (takes priority over the file above).', 'fw' ) ),

			'group_run' => array(
				'type'    => 'group',
				'options' => array(
					'state_machine' => $txt( __( 'State Machine', 'fw' ), __( 'The State Machine to run (recommended — enables hover/click interactivity). Copy the exact name from the Rive editor. Leave empty to play an animation instead.', 'fw' ) ),
					'anim_name'     => $txt( __( 'Animation', 'fw' ), __( 'Used only when no State Machine is set — the timeline animation to play (empty = the file’s default).', 'fw' ) ),
					'artboard'      => $txt( __( 'Artboard', 'fw' ), __( 'Optional — a specific artboard by name (empty = the default artboard).', 'fw' ) ),
					'click_trigger' => $txt( __( 'Click fires trigger', 'fw' ), __( 'Optional — the name of a State Machine <strong>Trigger</strong> input to fire when the element is clicked (e.g. a “bump” or a button press). State Machines that carry their own pointer listeners already react on their own; this is for trigger-only files.', 'fw' ) ),
				),
			),

			'trigger' => $sel( __( 'Plays', 'fw' ), 'autoplay', array(
				'autoplay' => __( 'Autoplay (and pause off-screen)', 'fw' ),
				'view'     => __( 'When it scrolls into view', 'fw' ),
				'hover'    => __( 'On hover (pause when the pointer leaves)', 'fw' ),
				'click'    => __( 'On click', 'fw' ),
				'scroll'   => __( 'Scroll-scrub (drive a State Machine input)', 'fw' ),
			), __( 'How the animation is driven. Interactive State Machines still react to the pointer on top of this.', 'fw' ) ),

			'group_scroll' => array(
				'type'    => 'group',
				'options' => array(
					'scroll_input'  => $txt( __( 'Scroll input (number)', 'fw' ), __( 'Scroll-scrub only — the name of a <strong>Number</strong> input in your State Machine. The scroll position (0–100) is written to it, so design the SM to blend on that input.', 'fw' ) ),
					'scroll_length' => $sld( __( 'Scroll length (screens)', 'fw' ), 2, 1, 6, 0.5, __( 'Scroll-scrub only — how many screen-heights the scrub spans.', 'fw' ) ),
				),
			),

			// Rive Events — State Machines can emit events at runtime (Rive editor → Events). Turn a Rive
			// graphic into interactive UI: follow its "Open URL" events, and/or forward every event to your
			// own JavaScript. Both off by default (a .riv can't navigate or fire hooks unless you allow it).
			'group_events' => array(
				'type'    => 'group',
				'options' => array(
					'event_open_url'   => array( 'type' => 'switch', 'label' => __( 'Follow “Open URL” events', 'fw' ), 'value' => 'no',
						'desc' => __( 'When the animation fires a Rive <strong>Open URL</strong> event, navigate to that URL. Only <code>http</code>/<code>https</code>/<code>mailto</code>/<code>tel</code> links are allowed. Off by default.', 'fw' ),
						'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
						'left-choice'  => array( 'value' => 'no', 'label' => __( 'No', 'fw' ) ) ),
					'event_url_target' => $sel( __( 'Open link in', 'fw' ), 'auto', array(
						'auto'   => __( 'Use the event’s own target', 'fw' ),
						'_self'  => __( 'Same tab', 'fw' ),
						'_blank' => __( 'New tab', 'fw' ),
					), __( 'How to open a followed Open-URL event.', 'fw' ) ),
					'event_js_hook'    => array( 'type' => 'switch', 'label' => __( 'Emit a JS event', 'fw' ), 'value' => 'no',
						'desc' => __( 'Dispatch a DOM <code>fwRiveEvent</code> CustomEvent for every Rive event (its name + properties are in <code>event.detail</code>), so your own JavaScript can react — analytics, open a modal, etc. Off by default.', 'fw' ),
						'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
						'left-choice'  => array( 'value' => 'no', 'label' => __( 'No', 'fw' ) ) ),
				),
			),

			// Data binding — drive a file's View-Model properties (text / number / boolean / colour) with
			// values entered here, so ONE .riv becomes a live label, counter, progress value or brand
			// re-theme without re-exporting. Property names come from the Rive editor's Data panel.
			'bindings' => array(
				'type'          => 'addable-popup',
				'label'         => __( 'Data bindings', 'fw' ),
				'popup-title'   => __( 'Add / Edit binding', 'fw' ),
				'size'          => 'small',
				'desc'          => __( 'Set the file’s <strong>data-bound</strong> properties (its View Model) from here — a text label, a number, a boolean or a colour — without re-exporting the <code>.riv</code>. Property names/paths come from the Rive editor’s <strong>Data</strong> panel; a nested path uses <code>/</code> (e.g. <code>card/title</code>). Needs a file built with data binding.', 'fw' ),
				'template'      => '{{=prop}}',
				'popup-options' => array(
					'prop'  => array( 'type' => 'text', 'label' => __( 'Property name / path', 'fw' ),
						'desc' => __( 'The View-Model property to set, e.g. <code>score</code>, or <code>card/title</code> for a nested one.', 'fw' ) ),
					'type'  => array( 'type' => 'select', 'value' => 'text', 'no-validate' => true, 'label' => __( 'Type', 'fw' ),
						'choices' => array( 'text' => __( 'Text', 'fw' ), 'number' => __( 'Number', 'fw' ), 'boolean' => __( 'Boolean (yes/no)', 'fw' ), 'color' => __( 'Colour (hex)', 'fw' ), 'trigger' => __( 'Trigger (fire on load)', 'fw' ) ) ),
					'value' => array( 'type' => 'text', 'label' => __( 'Value', 'fw' ),
						'desc' => __( 'Text = the string · Number = e.g. <code>42</code> · Boolean = <code>yes</code>/<code>no</code> · Colour = a hex like <code>#00b295</code> · Trigger = no value (fires once on load, after the other bindings — handy to set an initial state).', 'fw' ) ),
				),
			),
		),
	),

	'tab_style' => array(
		'type'    => 'tab',
		'title'   => __( 'Style', 'fw' ),
		'options' => array(
			'fit'        => $sel( __( 'Fit', 'fw' ), 'contain', array(
				'contain' => __( 'Contain (fit inside)', 'fw' ), 'cover' => __( 'Cover (fill, crop)', 'fw' ),
				'fill' => __( 'Fill (stretch)', 'fw' ), 'fit-width' => __( 'Fit width', 'fw' ), 'fit-height' => __( 'Fit height', 'fw' ),
				'none' => __( 'None (actual size)', 'fw' ),
			), __( 'How the artboard scales into the canvas.', 'fw' ) ),
			'alignment'  => $sel( __( 'Alignment', 'fw' ), 'center', array(
				'center' => __( 'Center', 'fw' ), 'top' => __( 'Top', 'fw' ), 'bottom' => __( 'Bottom', 'fw' ),
				'left' => __( 'Left', 'fw' ), 'right' => __( 'Right', 'fw' ),
			), __( 'Where the artboard sits when it doesn’t fill the canvas.', 'fw' ) ),
			'height'     => $sld( __( 'Height (px)', 'fw' ), 360, 80, 900, 10, __( 'The canvas height. Width fills the column.', 'fw' ) ),
			'snug'       => array( 'type' => 'switch', 'label' => __( 'Trim empty space', 'fw' ), 'value' => 'yes',
				'desc' => __( 'Shrink the element to the animation’s own aspect ratio (centered) so there’s no empty canvas around it — the hover/click area then matches the artwork instead of the full column width. Turn off to let the canvas fill the whole column.', 'fw' ),
				'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
				'left-choice'  => array( 'value' => 'no', 'label' => __( 'No', 'fw' ) ) ),
			'background' => array( 'type' => 'color-picker', 'label' => __( 'Background', 'fw' ), 'value' => '',
				'desc' => __( 'Canvas background. Leave empty for transparent (Rive files are usually transparent).', 'fw' ) ),
		),
	),

	'tab_advanced' => array(
		'type'    => 'tab',
		'title'   => __( 'Advanced', 'fw' ),
		'options' => array(
			'unique_id' => array( 'type' => 'unique' ),
			'poster'    => array( 'type' => 'upload', 'label' => __( 'Fallback poster', 'fw' ), 'value' => array(), 'images_only' => true,
				'desc' => __( 'Shown if the runtime can’t load, and as the still image when the visitor has “reduce motion” on. Recommended.', 'fw' ) ),
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
