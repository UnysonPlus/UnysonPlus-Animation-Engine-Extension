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
        'up'    => __( 'Up (rise in)', 'fw' ),
        'down'  => __( 'Down', 'fw' ),
        'left'  => __( 'From the left', 'fw' ),
        'right' => __( 'From the right', 'fw' ),
        'none'  => __( 'No movement (fade only)', 'fw' ),
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
                        'reveal'    => $fx( 'reveal',    __( 'Reveal', 'fw' ) ),
                        'stagger'   => $fx( 'stagger',   __( 'Stagger', 'fw' ) ),
                        'splittext' => $fx( 'splittext', __( 'Split Text', 'fw' ) ),
                        'parallax'  => $fx( 'parallax',  __( 'Parallax', 'fw' ) ),
                        'pin'       => $fx( 'pin',       __( 'Pin', 'fw' ) ),
                        'scrub'     => $fx( 'scrub',     __( 'Scrub', 'fw' ) ),
                        'zoom'      => $fx( 'zoom',      __( 'Zoom In', 'fw' ) ),
                        'rotate'    => $fx( 'rotate',    __( 'Rotate In', 'fw' ) ),
                        'blur'      => $fx( 'blur',      __( 'Blur In', 'fw' ) ),
                        'clip'      => $fx( 'clip',      __( 'Clip Wipe', 'fw' ) ),
                        'skew'      => $fx( 'skew',      __( 'Skew Settle', 'fw' ) ),
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
                            'scale' => [
                                'type'       => 'slider',
                                'label'      => __( 'Start scale', 'fw' ),
                                'desc'       => __( 'Smaller = zooms in from further out.', 'fw' ),
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
                                    'up'    => __( 'Bottom → up', 'fw' ),
                                    'down'  => __( 'Top → down', 'fw' ),
                                    'left'  => __( 'Right → left', 'fw' ),
                                    'right' => __( 'Left → right', 'fw' ),
                                ],
                            ],
                        ], $entrance_tail ),
                    ],
                ],
                'skew' => [
                    'group_gsap_skew' => [
                        'type'    => 'group',
                        'options' => array_merge( [
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
