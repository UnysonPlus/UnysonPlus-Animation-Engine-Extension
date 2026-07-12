<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Scroll Motion module: option definitions.
 *
 * sc_get_gsap_fields() builds the GSAP "Scroll Motion" multi-picker (keyed `gsap_motion`) and the
 * `sc_animation_fields` filter injects it into every element's Animations tab. Depends on nothing
 * beyond the Animation Engine being active.
 */


/**
 * Returns the GSAP "Scroll Motion" fields appended to the Animations tab.
 *
 * Saved value shape (multi-picker, picker id = `effect`):
 *
 *     [ 'effect' => 'reveal', 'reveal' => [ <sub-option values> ] ]
 *
 * Only the selected effect's sub-array carries data; switching effects never
 * loses the others' values (standard multi-picker behaviour).
 */
if ( ! function_exists( 'sc_get_gsap_fields' ) ) :
function sc_get_gsap_fields() {

    // Reveal/Stagger "Style" presets. Each bundles a tasteful package of
    // scale + blur + easing + duration (mapped JS-side in upw-gsap.js), so a
    // single dropdown turns a flat fade into crafted, compound motion.
    $style_choices = [
        'subtle'   => __( 'Subtle', 'fw' ),
        'standard' => __( 'Standard', 'fw' ),
        'dramatic' => __( 'Dramatic', 'fw' ),
        'bounce'   => __( 'Bounce (overshoot)', 'fw' ),
        'elastic'  => __( 'Elastic (springy)', 'fw' ),
    ];

    // ScrollTrigger `start` positions (element edge vs viewport edge).
    $start_choices = [
        'top 85%'    => __( 'Default — near bottom of screen', 'fw' ),
        'top 100%'   => __( 'As soon as it enters', 'fw' ),
        'top 70%'    => __( 'A little later (70%)', 'fw' ),
        'top center' => __( 'When it reaches the middle', 'fw' ),
        'top 40%'    => __( 'Well into view (40%)', 'fw' ),
    ];

    $direction_choices = [
        'up'         => __( 'Up (rise in)', 'fw' ),
        'down'       => __( 'Down', 'fw' ),
        'left'       => __( 'From the left', 'fw' ),
        'right'      => __( 'From the right', 'fw' ),
        'up_left'    => __( 'Up + from the left', 'fw' ),
        'up_right'   => __( 'Up + from the right', 'fw' ),
        'down_left'  => __( 'Down + from the left', 'fw' ),
        'down_right' => __( 'Down + from the right', 'fw' ),
        'none'       => __( 'No movement (fade only)', 'fw' ),
    ];

    $run_on_mobile = function ( $default_yes = true ) {
        return [
            'type'         => 'switch',
            'label'        => __( 'Run on mobile', 'fw' ),
            'desc'         => __( 'Disable on phones (< 768px) if the effect feels heavy on small screens.', 'fw' ),
            'value'        => $default_yes ? 'yes' : 'no',
            'left-choice'  => [ 'value' => 'no',  'label' => __( 'No',  'fw' ) ],
            'right-choice' => [ 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ],
        ];
    };

    // Reusable "where / when" fields shared by reveal / stagger. The "how"
    // (scale, blur, ease, duration) now lives in the Style preset above.
    $timing = function () use ( $start_choices ) {
        return [
            'delay' => [
                'type'         => 'number',
                'label'        => __( 'Delay (seconds)', 'fw' ),
                'desc'         => __( 'Wait before the motion starts once the trigger is reached.', 'fw' ),
                'value'        => 0,
                'min'          => 0,
                'step'         => 0.1,
                'numeric_type' => 'float',
            ],
            'start' => [
                'type'    => 'select',
                'label'   => __( 'Start animating', 'fw' ),
                'desc'    => __( 'How far into view the element should be before it animates.', 'fw' ),
                'value'   => 'top 85%',
                'choices' => $start_choices,
            ],
        ];
    };

    // The Style select, inserted into both reveal and stagger groups.
    $style_field = [
        'type'    => 'select',
        'label'   => __( 'Style', 'fw' ),
        'desc'    => __( 'Overall character — layers a scale + blur + refined easing so the motion feels crafted, not flat. Dramatic is the boldest.', 'fw' ),
        'value'   => 'standard',
        'choices' => $style_choices,
    ];

    // Scroll Effect previews: hand-drawn (and self-animating) SVG diagrams under
    // static/img/scroll-effects/. SVG suits these abstract motion patterns — crisp,
    // tiny, and able to demonstrate the motion in the picker. Each tile shows the same
    // SVG at two sizes (large = the hover preview). Falls back gracefully if the URI
    // can't be resolved (the option type still works as a radio of empty tiles).
    $fx_ext  = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
    $fx_base = $fx_ext ? $fx_ext->get_declared_URI( '/modules/scroll-motion/static/img/scroll-effects' ) : '';
    $fx      = function ( $file, $label ) use ( $fx_base ) {
        return [
            'small' => [ 'src' => $fx_base . '/' . $file . '.svg', 'height' => 66 ],
            'large' => [ 'src' => $fx_base . '/' . $file . '.svg', 'height' => 132 ],
            'label' => $label,
        ];
    };

    // Shared trailing fields for the one-shot entrance effects (zoom/rotate/blur/
    // clip/skew): the timing block + a "play once" switch + run-on-mobile.
    $once_field = [
        'type'         => 'switch',
        'label'        => __( 'Play once', 'fw' ),
        'desc'         => __( 'Off = replay every time it scrolls back into view.', 'fw' ),
        'value'        => 'yes',
        'left-choice'  => [ 'value' => 'no',  'label' => __( 'No',  'fw' ) ],
        'right-choice' => [ 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ],
    ];
    $entrance_tail = array_merge( $timing(), [ 'once' => $once_field, 'run_on_mobile' => $run_on_mobile( true ) ] );

    return [
        'gsap_motion' => [
            'type'         => 'multi-picker',
            'label'        => __( 'Scroll Effect', 'fw' ),
            'desc'         => __( 'Pick a scroll-driven effect. Leave on None for no GSAP motion (nothing loads).', 'fw' ),
            'help'         => __( 'Scroll Motion (GSAP): scroll-driven motion powered by GSAP + ScrollTrigger. Independent of the entrance animation above.', 'fw' ) . ( function_exists( 'upw_perf_note' ) ? ' ' . upw_perf_note() : '' ),
            'popover'      => true,
            'show_borders' => false,
            'value'        => [ 'effect' => 'none' ],
            'anim_meta'    => [ 'category' => __( 'Scroll', 'fw' ), 'icon' => '&#128220;' ], // 📜 (Animations-tab inserter)
            'picker' => [
                'effect' => [
                    'type'    => 'image-picker',
                    'label'   => false,
                    'desc'    => __( 'Hover a tile to preview it larger.', 'fw' ),
                    'value'   => 'none',
                    'choices' => [
                        'none'      => $fx( 'none',      __( 'None', 'fw' ) ),
                        'blur'      => $fx( 'blur',      __( 'Blur In', 'fw' ) ),
                        'clip'      => $fx( 'clip',      __( 'Clip Wipe', 'fw' ) ),
                        'color_scrub' => $fx( 'color-scrub', __( 'Color Scrub', 'fw' ) ),
                        'counter'   => $fx( 'counter',   __( 'Count Up', 'fw' ) ),
                        'expand'    => $fx( 'expand',    __( 'Expand / Grow', 'fw' ) ),
                        'flip'      => $fx( 'flip',      __( 'Flip In (3D)', 'fw' ) ),
                        'mask_wipe' => $fx( 'mask-wipe', __( 'Mask Wipe', 'fw' ) ),
                        'parallax'  => $fx( 'parallax',  __( 'Parallax', 'fw' ) ),
                        'pin'       => $fx( 'pin',       __( 'Pin', 'fw' ) ),
                        'reveal'    => $fx( 'reveal',    __( 'Reveal', 'fw' ) ),
                        'rotate'    => $fx( 'rotate',    __( 'Rotate In', 'fw' ) ),
                        'scroll_spin' => $fx( 'scroll-spin', __( 'Scroll Spin', 'fw' ) ),
                        'scrub'     => $fx( 'scrub',     __( 'Scrub', 'fw' ) ),
                        'skew'      => $fx( 'skew',      __( 'Skew Settle', 'fw' ) ),
                        'splittext' => $fx( 'splittext', __( 'Split Text', 'fw' ) ),
                        'stagger'   => $fx( 'stagger',   __( 'Stagger', 'fw' ) ),
                        'tilt_scrub' => $fx( 'tilt-scrub', __( 'Tilt Scrub (3D)', 'fw' ) ),
                        'velocity_skew' => $fx( 'velocity-skew', __( 'Velocity Skew', 'fw' ) ),
                        'zoom'      => $fx( 'zoom',      __( 'Zoom In', 'fw' ) ),
                    ],
                ],
            ],
            'choices' => [
                'reveal' => [
                    'group_gsap_reveal' => [
                        'type'    => 'group',
                        'options' => array_merge(
                            [
                                'direction' => [
                                    'type'    => 'select',
                                    'label'   => __( 'Direction', 'fw' ),
                                    'value'   => 'up',
                                    'choices' => $direction_choices,
                                ],
                                'style' => $style_field,
                                'distance' => [
                                    'type'         => 'number',
                                    'label'        => __( 'Distance (px)', 'fw' ),
                                    'desc'         => __( 'How far it travels as it fades in.', 'fw' ),
                                    'value'        => 50,
                                    'min'          => 0,
                                    'step'         => 1,
                                    'numeric_type' => 'integer',
                                ],
                            ],
                            $timing(),
                            [
                                'once' => [
                                    'type'         => 'switch',
                                    'label'        => __( 'Play once', 'fw' ),
                                    'desc'         => __( 'Off = replay every time it scrolls back into view.', 'fw' ),
                                    'value'        => 'yes',
                                    'left-choice'  => [ 'value' => 'no',  'label' => __( 'No',  'fw' ) ],
                                    'right-choice' => [ 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ],
                                ],
                                'run_on_mobile' => $run_on_mobile( true ),
                            ]
                        ),
                    ],
                ],
                'stagger' => [
                    'group_gsap_stagger' => [
                        'type'    => 'group',
                        'options' => array_merge(
                            [
                                'scope' => [
                                    'type'    => 'select',
                                    'label'   => __( 'Apply to', 'fw' ),
                                    'desc'    => __( 'Which pieces cascade in. "Grid items" auto-detects the repeated items inside a grid/gallery/masonry wrapper (drills through the layout container for you); "Direct children only" staggers this element\'s immediate children as-is.', 'fw' ),
                                    'value'   => 'auto',
                                    'choices' => [
                                        'auto'   => __( 'Grid items (auto-detect)', 'fw' ),
                                        'direct' => __( 'Direct children only', 'fw' ),
                                    ],
                                ],
                                'direction' => [
                                    'type'    => 'select',
                                    'label'   => __( 'Direction', 'fw' ),
                                    'value'   => 'up',
                                    'choices' => $direction_choices,
                                ],
                                'style' => $style_field,
                                'distance' => [
                                    'type'         => 'number',
                                    'label'        => __( 'Distance (px)', 'fw' ),
                                    'value'        => 50,
                                    'min'          => 0,
                                    'step'         => 1,
                                    'numeric_type' => 'integer',
                                ],
                                'stagger_each' => [
                                    'type'         => 'number',
                                    'label'        => __( 'Time between items (seconds)', 'fw' ),
                                    'desc'         => __( 'Gap between each child as they cascade in. Applies to the direct children of this element.', 'fw' ),
                                    'value'        => 0.12,
                                    'min'          => 0,
                                    'step'         => 0.01,
                                    'numeric_type' => 'float',
                                ],
                                'stagger_from' => [
                                    'type'    => 'select',
                                    'label'   => __( 'Cascade from', 'fw' ),
                                    'value'   => 'start',
                                    'choices' => [
                                        'start'  => __( 'First to last', 'fw' ),
                                        'end'    => __( 'Last to first', 'fw' ),
                                        'center' => __( 'Center outward', 'fw' ),
                                        'edges'  => __( 'Edges inward', 'fw' ),
                                    ],
                                ],
                            ],
                            $timing(),
                            [ 'run_on_mobile' => $run_on_mobile( true ) ]
                        ),
                    ],
                ],
                'splittext' => [
                    'group_gsap_splittext' => [
                        'type'    => 'group',
                        'options' => [
                            'split_by' => [
                                'type'    => 'select',
                                'label'   => __( 'Split by', 'fw' ),
                                'desc'    => __( 'What reveals in sequence — letters, words or lines.', 'fw' ),
                                'value'   => 'chars',
                                'choices' => [
                                    'chars' => __( 'Characters', 'fw' ),
                                    'words' => __( 'Words', 'fw' ),
                                    'lines' => __( 'Lines', 'fw' ),
                                ],
                            ],
                            'target' => [
                                'type'    => 'select',
                                'label'   => __( 'Apply to', 'fw' ),
                                'desc'    => __( 'Which text inside this element gets split and revealed.', 'fw' ),
                                'value'   => 'headings',
                                'choices' => [
                                    'headings'   => __( 'Headings (H1–H6)', 'fw' ),
                                    'paragraphs' => __( 'Paragraphs', 'fw' ),
                                    'all'        => __( 'Headings + paragraphs', 'fw' ),
                                ],
                            ],
                            'style'        => $style_field,
                            'split_anim' => [
                                'type'    => 'select',
                                'label'   => __( 'Piece animation', 'fw' ),
                                'desc'    => __( 'How each character / word / line arrives.', 'fw' ),
                                'value'   => 'slide',
                                'choices' => [
                                    'slide'  => __( 'Slide up', 'fw' ),
                                    'flip3d' => __( 'Flip 3D', 'fw' ),
                                    'scale'  => __( 'Scale pop', 'fw' ),
                                    'blur'   => __( 'Blur in', 'fw' ),
                                    'rotate' => __( 'Rotate in', 'fw' ),
                                    'random' => __( 'Random per piece', 'fw' ),
                                ],
                            ],
                            'stagger_each' => [
                                'type'         => 'number',
                                'label'        => __( 'Time between pieces (seconds)', 'fw' ),
                                'desc'         => __( 'Smaller = faster cascade. Characters look good around 0.02–0.04.', 'fw' ),
                                'value'        => 0.03,
                                'min'          => 0,
                                'step'         => 0.01,
                                'numeric_type' => 'float',
                            ],
                            'direction' => [
                                'type'    => 'select',
                                'label'   => __( 'Direction', 'fw' ),
                                'value'   => 'up',
                                'choices' => [
                                    'up'   => __( 'Rise up', 'fw' ),
                                    'down' => __( 'Drop down', 'fw' ),
                                ],
                            ],
                            'start' => [
                                'type'    => 'select',
                                'label'   => __( 'Start animating', 'fw' ),
                                'value'   => 'top 85%',
                                'choices' => $start_choices,
                            ],
                            'run_on_mobile' => $run_on_mobile( true ),
                        ],
                    ],
                ],
                'parallax' => [
                    'group_gsap_parallax' => [
                        'type'    => 'group',
                        'options' => [
                            'axis' => [
                                'type'    => 'select',
                                'label'   => __( 'Axis', 'fw' ),
                                'value'   => 'vertical',
                                'choices' => [
                                    'vertical'   => __( 'Vertical', 'fw' ),
                                    'horizontal' => __( 'Horizontal', 'fw' ),
                                ],
                            ],
                            'speed' => [
                                'type'         => 'number',
                                'label'        => __( 'Strength (%)', 'fw' ),
                                'desc'         => __( 'How much the element drifts relative to the scroll. Higher = more movement. Try 10–30.', 'fw' ),
                                'value'        => 20,
                                'min'          => 1,
                                'max'          => 100,
                                'step'         => 1,
                                'numeric_type' => 'integer',
                            ],
                            'pmotion' => [
                                'type'    => 'select',
                                'label'   => __( 'Add motion', 'fw' ),
                                'desc'    => __( 'Layer a subtle rotate or scale on top of the drift.', 'fw' ),
                                'value'   => 'none',
                                'choices' => [
                                    'none'   => __( 'Drift only', 'fw' ),
                                    'rotate' => __( '+ Rotate', 'fw' ),
                                    'scale'  => __( '+ Scale', 'fw' ),
                                ],
                            ],
                            'pfade' => [
                                'type'         => 'switch',
                                'label'        => __( 'Fade with drift', 'fw' ),
                                'value'        => 'no',
                                'left-choice'  => [ 'value' => 'no',  'label' => __( 'Off', 'fw' ) ],
                                'right-choice' => [ 'value' => 'yes', 'label' => __( 'On', 'fw' ) ],
                            ],
                            'run_on_mobile' => $run_on_mobile( false ),
                        ],
                    ],
                ],
                'pin' => [
                    'group_gsap_pin' => [
                        'type'    => 'group',
                        'options' => [
                            'pin_length' => [
                                'type'         => 'number',
                                'label'        => __( 'Pin length (% of screen height)', 'fw' ),
                                'desc'         => __( 'How long the element stays pinned as you scroll. 100 = one full screen of scrolling.', 'fw' ),
                                'value'        => 100,
                                'min'          => 10,
                                'step'         => 10,
                                'numeric_type' => 'integer',
                            ],
                            'pin_fade' => [
                                'type'         => 'switch',
                                'label'        => __( 'Fade at edges', 'fw' ),
                                'desc'         => __( 'Fade the element in as it pins and out as it releases (smooth hand-off).', 'fw' ),
                                'value'        => 'no',
                                'left-choice'  => [ 'value' => 'no',  'label' => __( 'Off', 'fw' ) ],
                                'right-choice' => [ 'value' => 'yes', 'label' => __( 'On', 'fw' ) ],
                            ],
                            'run_on_mobile' => $run_on_mobile( false ),
                        ],
                    ],
                ],
                'scrub' => [
                    'group_gsap_scrub' => [
                        'type'    => 'group',
                        'options' => [
                            'scrub_kind' => [
                                'type'    => 'select',
                                'label'   => __( 'What to animate', 'fw' ),
                                'value'   => 'fade',
                                'choices' => [
                                    'fade'   => __( 'Fade in', 'fw' ),
                                    'scale'  => __( 'Scale up', 'fw' ),
                                    'rotate' => __( 'Rotate', 'fw' ),
                                    'slide'  => __( 'Slide up', 'fw' ),
                                    'blur'   => __( 'Blur → sharp', 'fw' ),
                                    'skew'   => __( 'Skew → straight', 'fw' ),
                                ],
                            ],
                            'intensity' => [
                                'type'         => 'number',
                                'label'        => __( 'Intensity', 'fw' ),
                                'desc'         => __( 'Strength of the effect (px / degrees / %, depending on the type above).', 'fw' ),
                                'value'        => 20,
                                'min'          => 1,
                                'step'         => 1,
                                'numeric_type' => 'integer',
                            ],
                            'start' => [
                                'type'    => 'select',
                                'label'   => __( 'Start animating', 'fw' ),
                                'value'   => 'top 85%',
                                'choices' => $start_choices,
                            ],
                            'run_on_mobile' => $run_on_mobile( true ),
                        ],
                    ],
                ],
                'zoom' => [
                    'group_gsap_zoom' => [
                        'type'    => 'group',
                        'options' => array_merge( [
                            'zdir' => [
                                'type'    => 'select',
                                'label'   => __( 'Zoom', 'fw' ),
                                'desc'    => __( 'In grows from small; Out starts larger and settles down to size.', 'fw' ),
                                'value'   => 'in',
                                'choices' => [
                                    'in'  => __( 'Zoom in (from small)', 'fw' ),
                                    'out' => __( 'Zoom out (from large)', 'fw' ),
                                ],
                            ],
                            'scale' => [
                                'type'       => 'slider',
                                'label'      => __( 'Start scale', 'fw' ),
                                'desc'       => __( 'Smaller = zooms in from further out (for Zoom out it mirrors to start larger).', 'fw' ),
                                'value'      => 0.6,
                                'properties' => [ 'min' => 0.2, 'max' => 0.95, 'step' => 0.05 ],
                            ],
                        ], $entrance_tail ),
                    ],
                ],
                'rotate' => [
                    'group_gsap_rotate' => [
                        'type'    => 'group',
                        'options' => array_merge( [
                            'rotate' => [
                                'type'       => 'slider',
                                'label'      => __( 'Rotation (°)', 'fw' ),
                                'value'      => 8,
                                'properties' => [ 'min' => 2, 'max' => 30, 'step' => 1 ],
                            ],
                            'direction' => [
                                'type'    => 'select',
                                'label'   => __( 'Spin from', 'fw' ),
                                'value'   => 'left',
                                'choices' => [
                                    'left'  => __( 'Counter-clockwise', 'fw' ),
                                    'right' => __( 'Clockwise', 'fw' ),
                                ],
                            ],
                        ], $entrance_tail ),
                    ],
                ],
                'blur' => [
                    'group_gsap_blur' => [
                        'type'    => 'group',
                        'options' => array_merge( [
                            'blur' => [
                                'type'       => 'slider',
                                'label'      => __( 'Blur (px)', 'fw' ),
                                'value'      => 12,
                                'properties' => [ 'min' => 2, 'max' => 40, 'step' => 1 ],
                            ],
                        ], $entrance_tail ),
                    ],
                ],
                'clip' => [
                    'group_gsap_clip' => [
                        'type'    => 'group',
                        'options' => array_merge( [
                            'direction' => [
                                'type'    => 'select',
                                'label'   => __( 'Wipe from', 'fw' ),
                                'value'   => 'up',
                                'choices' => [
                                    'up'       => __( 'Bottom → up', 'fw' ),
                                    'down'     => __( 'Top → down', 'fw' ),
                                    'left'     => __( 'Right → left', 'fw' ),
                                    'right'    => __( 'Left → right', 'fw' ),
                                    'iris'     => __( 'Iris (circle open)', 'fw' ),
                                    'diagonal' => __( 'Diagonal', 'fw' ),
                                    'rounded'  => __( 'Rounded box', 'fw' ),
                                ],
                            ],
                        ], $entrance_tail ),
                    ],
                ],
                'skew' => [
                    'group_gsap_skew' => [
                        'type'    => 'group',
                        'options' => array_merge( [
                            'axis' => [
                                'type'    => 'select',
                                'label'   => __( 'Skew axis', 'fw' ),
                                'value'   => 'vertical',
                                'choices' => [
                                    'vertical'   => __( 'Vertical (skewY)', 'fw' ),
                                    'horizontal' => __( 'Horizontal (skewX)', 'fw' ),
                                ],
                            ],
                            'skew' => [
                                'type'       => 'slider',
                                'label'      => __( 'Skew (°)', 'fw' ),
                                'value'      => 8,
                                'properties' => [ 'min' => 2, 'max' => 30, 'step' => 1 ],
                            ],
                            'distance' => [
                                'type'         => 'number',
                                'label'        => __( 'Distance (px)', 'fw' ),
                                'value'        => 40,
                                'min'          => 0,
                                'step'         => 1,
                                'numeric_type' => 'integer',
                            ],
                        ], $entrance_tail ),
                    ],
                ],
                'flip' => [
                    'group_gsap_flip' => [
                        'type'    => 'group',
                        'options' => array_merge( [
                            'axis' => [
                                'type'    => 'select',
                                'label'   => __( 'Flip axis', 'fw' ),
                                'desc'    => __( 'Which way the card turns. Y = swings left/right like a door; X = tips forward like a lid.', 'fw' ),
                                'value'   => 'y',
                                'choices' => [
                                    'y' => __( 'Vertical hinge (Y)', 'fw' ),
                                    'x' => __( 'Horizontal hinge (X)', 'fw' ),
                                ],
                            ],
                            'direction' => [
                                'type'    => 'select',
                                'label'   => __( 'Flip from', 'fw' ),
                                'value'   => 'left',
                                'choices' => [
                                    'left'  => __( 'One side', 'fw' ),
                                    'right' => __( 'The other side', 'fw' ),
                                ],
                            ],
                            'deg' => [
                                'type'       => 'slider',
                                'label'      => __( 'Start angle (°)', 'fw' ),
                                'desc'       => __( 'How far it is turned away before flipping in. 90° = edge-on.', 'fw' ),
                                'value'      => 90,
                                'properties' => [ 'min' => 30, 'max' => 120, 'step' => 5 ],
                            ],
                        ], $entrance_tail ),
                    ],
                ],
                'expand' => [
                    'group_gsap_expand' => [
                        'type'    => 'group',
                        'options' => array_merge( [
                            'axis' => [
                                'type'    => 'select',
                                'label'   => __( 'Grow direction', 'fw' ),
                                'desc'    => __( 'Horizontal grows a bar/underline sideways; Vertical grows it up or down.', 'fw' ),
                                'value'   => 'x',
                                'choices' => [
                                    'x' => __( 'Horizontal (scaleX)', 'fw' ),
                                    'y' => __( 'Vertical (scaleY)', 'fw' ),
                                ],
                            ],
                            'origin' => [
                                'type'    => 'select',
                                'label'   => __( 'Grow from', 'fw' ),
                                'desc'    => __( 'The anchored edge the element expands away from.', 'fw' ),
                                'value'   => 'left',
                                'choices' => [
                                    'left'   => __( 'Left', 'fw' ),
                                    'center' => __( 'Center', 'fw' ),
                                    'right'  => __( 'Right', 'fw' ),
                                    'top'    => __( 'Top', 'fw' ),
                                    'bottom' => __( 'Bottom', 'fw' ),
                                ],
                            ],
                        ], $entrance_tail ),
                    ],
                ],
                'counter' => [
                    'group_gsap_counter' => [
                        'type'    => 'group',
                        'options' => array_merge( [
                            'cstyle' => [
                                'type'    => 'select',
                                'label'   => __( 'Count style', 'fw' ),
                                'desc'    => __( 'Plain ticks the value up; Odometer rolls each digit like a mechanical counter.', 'fw' ),
                                'value'   => 'count',
                                'choices' => [
                                    'count'    => __( 'Plain count', 'fw' ),
                                    'odometer' => __( 'Odometer (digit roll)', 'fw' ),
                                ],
                            ],
                            'duration' => [
                                'type'       => 'slider',
                                'label'      => __( 'Count duration (s)', 'fw' ),
                                'desc'       => __( 'How long the number takes to tick from the start up to its value.', 'fw' ),
                                'value'      => 2,
                                'properties' => [ 'min' => 0.5, 'max' => 6, 'step' => 0.5 ],
                            ],
                            'from' => [
                                'type'         => 'number',
                                'label'        => __( 'Start from', 'fw' ),
                                'desc'         => __( 'The value the count begins at (usually 0).', 'fw' ),
                                'value'        => 0,
                                'step'         => 1,
                                'numeric_type' => 'float',
                            ],
                            'prefix' => [
                                'type'  => 'text',
                                'label' => __( 'Prefix', 'fw' ),
                                'desc'  => __( 'Optional text shown before the number — e.g. $ or +. Leave blank if the number already includes it.', 'fw' ),
                                'value' => '',
                            ],
                            'suffix' => [
                                'type'  => 'text',
                                'label' => __( 'Suffix', 'fw' ),
                                'desc'  => __( 'Optional text shown after the number — e.g. % or K or +.', 'fw' ),
                                'value' => '',
                            ],
                            'sep' => [
                                'type'         => 'switch',
                                'label'        => __( 'Thousands separator', 'fw' ),
                                'desc'         => __( 'Show grouped digits (1,250) while counting. Decimals in the source number are preserved either way.', 'fw' ),
                                'value'        => 'no',
                                'left-choice'  => [ 'value' => 'no',  'label' => __( 'Off', 'fw' ) ],
                                'right-choice' => [ 'value' => 'yes', 'label' => __( 'On', 'fw' ) ],
                            ],
                            'start' => [
                                'type'    => 'select',
                                'label'   => __( 'Start counting', 'fw' ),
                                'value'   => 'top 85%',
                                'choices' => $start_choices,
                            ],
                            'once' => [
                                'type'         => 'switch',
                                'label'        => __( 'Count once', 'fw' ),
                                'desc'         => __( 'Off = re-count every time it scrolls back into view.', 'fw' ),
                                'value'        => 'yes',
                                'left-choice'  => [ 'value' => 'no',  'label' => __( 'No',  'fw' ) ],
                                'right-choice' => [ 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ],
                            ],
                            'run_on_mobile' => $run_on_mobile( true ),
                        ] ),
                    ],
                ],
                'velocity_skew' => [
                    'group_gsap_velocity_skew' => [
                        'type'    => 'group',
                        'options' => [
                            'max' => [
                                'type'       => 'slider',
                                'label'      => __( 'Max skew (°)', 'fw' ),
                                'desc'       => __( 'The strongest lean at high scroll speed; springs back to straight when you stop.', 'fw' ),
                                'value'      => 20,
                                'properties' => [ 'min' => 4, 'max' => 45, 'step' => 1 ],
                            ],
                            'axis' => [
                                'type'    => 'select',
                                'label'   => __( 'Skew axis', 'fw' ),
                                'value'   => 'y',
                                'choices' => [
                                    'y' => __( 'Vertical (skewY)', 'fw' ),
                                    'x' => __( 'Horizontal (skewX)', 'fw' ),
                                ],
                            ],
                            'run_on_mobile' => $run_on_mobile( false ),
                        ],
                    ],
                ],
                'tilt_scrub' => [
                    'group_gsap_tilt_scrub' => [
                        'type'    => 'group',
                        'options' => [
                            'axis' => [
                                'type'    => 'select',
                                'label'   => __( 'Tilt axis', 'fw' ),
                                'desc'    => __( 'Y tips left/right; X tips forward/back.', 'fw' ),
                                'value'   => 'y',
                                'choices' => [
                                    'y' => __( 'Vertical hinge (Y)', 'fw' ),
                                    'x' => __( 'Horizontal hinge (X)', 'fw' ),
                                ],
                            ],
                            'deg' => [
                                'type'       => 'slider',
                                'label'      => __( 'Tilt (°)', 'fw' ),
                                'desc'       => __( 'How far it leans; it tips from +° to −° as it passes through the viewport.', 'fw' ),
                                'value'      => 12,
                                'properties' => [ 'min' => 3, 'max' => 45, 'step' => 1 ],
                            ],
                            'run_on_mobile' => $run_on_mobile( false ),
                        ],
                    ],
                ],
                'scroll_spin' => [
                    'group_gsap_scroll_spin' => [
                        'type'    => 'group',
                        'options' => [
                            'turns' => [
                                'type'       => 'slider',
                                'label'      => __( 'Turns', 'fw' ),
                                'desc'       => __( 'Full rotations as it travels through the viewport.', 'fw' ),
                                'value'      => 1,
                                'properties' => [ 'min' => 0.25, 'max' => 4, 'step' => 0.25 ],
                            ],
                            'dir' => [
                                'type'    => 'select',
                                'label'   => __( 'Direction', 'fw' ),
                                'value'   => 'cw',
                                'choices' => [
                                    'cw'  => __( 'Clockwise', 'fw' ),
                                    'ccw' => __( 'Counter-clockwise', 'fw' ),
                                ],
                            ],
                            'run_on_mobile' => $run_on_mobile( true ),
                        ],
                    ],
                ],
                'mask_wipe' => [
                    'group_gsap_mask_wipe' => [
                        'type'    => 'group',
                        'options' => array_merge( [
                            'direction' => [
                                'type'    => 'select',
                                'label'   => __( 'Wipe from', 'fw' ),
                                'value'   => 'left',
                                'choices' => [
                                    'left'  => __( 'Left → right', 'fw' ),
                                    'right' => __( 'Right → left', 'fw' ),
                                    'up'    => __( 'Top → down', 'fw' ),
                                    'down'  => __( 'Bottom → up', 'fw' ),
                                ],
                            ],
                            'soft' => [
                                'type'       => 'slider',
                                'label'      => __( 'Edge softness (%)', 'fw' ),
                                'desc'       => __( 'Width of the feathered leading edge. 0 = a hard wipe.', 'fw' ),
                                'value'      => 25,
                                'properties' => [ 'min' => 0, 'max' => 60, 'step' => 5 ],
                            ],
                        ], $entrance_tail ),
                    ],
                ],
                'color_scrub' => [
                    'group_gsap_color_scrub' => [
                        'type'    => 'group',
                        'options' => [
                            'ctarget' => [
                                'type'    => 'select',
                                'label'   => __( 'Colour', 'fw' ),
                                'desc'    => __( 'Which property tweens as you scroll through the element.', 'fw' ),
                                'value'   => 'text',
                                'choices' => [
                                    'text' => __( 'Text colour', 'fw' ),
                                    'bg'   => __( 'Background colour', 'fw' ),
                                ],
                            ],
                            'c1' => upw_color_field( __( 'From colour', 'fw' ), 'text', '#888888' ),
                            'c2' => upw_color_field( __( 'To colour', 'fw' ), 'text', '#2f74e6' ),
                            'run_on_mobile' => $run_on_mobile( true ),
                        ],
                    ],
                ],
            ],
        ],
    ];
}
endif;

/**
 * Inject the Scroll Motion fields into every element's Animations tab. The core
 * Animations tab (sc_get_animation_fields) builds only the Animate.css Entrance block;
 * this module — loaded only when the Animation Engine is active — appends the GSAP
 * group via the same `sc_animation_fields` filter the Hover module uses. Priority 9
 * keeps Scroll Motion just before Hover (10) and after the core Entrance block, so the
 * tab order (Entrance → Scroll Motion → Hover) matches how it was when GSAP lived in core.
 */
add_filter( 'sc_animation_fields', function ( $fields ) {
    if ( is_array( $fields ) && function_exists( 'sc_get_gsap_fields' ) ) {
        $fields = array_merge( $fields, sc_get_gsap_fields() );
    }
    return $fields;
}, 9 );
