<?php
/**
 * PHP Version: 7.4 or higher
 *
 * GSAP + ScrollTrigger "Scroll Motion" engine for every Unysonplus shortcode.
 *
 * This is a SECOND, independent animation engine that sits alongside the
 * Animate.css "Entrance Animation" block (shortcode-animation-helper.php). The
 * two do different jobs and never share saved values:
 *
 *   - Animate.css  -> one-shot CSS *entrance* effects ("appear with X").
 *   - GSAP (here)  -> scroll-driven motion: reveal, stagger, parallax, pin,
 *                     and scroll-scrub. The "award-site" vocabulary that CSS
 *                     keyframes cannot express.
 *
 * Provides:
 *   1. sc_get_gsap_fields() — the inner fields appended to the Animations tab
 *      (merged in by sc_get_animation_fields()). A self-contained multi-picker
 *      block keyed `gsap_motion`, so it cannot collide with the existing
 *      `animation` block and needs no migration of old saves.
 *   2. A filter on `sc_build_wrapper_attr` (priority 25, AFTER the Animate.css
 *      filter at 20) that stamps clean `data-upw-g*` attributes — and, for the
 *      effects that start hidden, an `upw-g-pending` guard class — onto the
 *      shortcode wrapper. No view.php changes: everything routes through the
 *      shared wrapper-attribute builder.
 *   3. Conditional enqueue: the bundled GSAP + ScrollTrigger + the initializer
 *      + the failsafe CSS load only when at least one shortcode on the rendered
 *      page actually uses a GSAP effect (mirrors sc_animation_flag()). Pages
 *      with no scroll motion ship ZERO of these bytes.
 */
if ( ! defined( 'FW' ) ) die( 'Forbidden' );


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
            'desc'         => __( 'Pick a scroll-driven effect. Leave on None for no GSAP motion (nothing loads).', 'fw' ) . ( function_exists( 'upw_perf_note' ) ? ' ' . upw_perf_note() : '' ),
            'help'         => __( 'Scroll Motion (GSAP): scroll-driven motion powered by GSAP + ScrollTrigger. Independent of the entrance animation above — GSAP loads only on pages that use it.', 'fw' ),
            'popover'      => true,
            'show_borders' => false,
            'value'        => [ 'effect' => 'none' ],
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


/**
 * Per-request flag: "at least one GSAP-animated shortcode has rendered".
 * Gates the wp_footer enqueue so zero GSAP bytes ship on un-animated pages.
 */
if ( ! function_exists( 'sc_gsap_flag' ) ) :
function sc_gsap_flag( $set = false ) {
    static $used = false;
    if ( $set ) $used = true;
    return $used;
}
endif;


/**
 * Records which GSAP effects rendered on this request, so wp_footer can load
 * the heavier per-effect plugins (e.g. SplitText) ONLY when they're used.
 */
if ( ! function_exists( 'sc_gsap_used' ) ) :
function sc_gsap_used( $effect = null ) {
    static $set = [];
    if ( $effect !== null ) { $set[ (string) $effect ] = true; }
    return $set;
}
endif;


/**
 * Stamp the GSAP data-attributes (and, for hidden-start effects, the
 * `upw-g-pending` guard class) onto the shortcode wrapper.
 *
 * Runs at priority 25 — after the Animate.css filter (20) — so the two engines
 * can coexist on one element (CSS entrance + GSAP scroll) without clobbering
 * each other's class list.
 */
add_filter( 'sc_build_wrapper_attr', function ( $attr, $atts ) {
    $g = ( isset( $atts['gsap_motion'] ) && is_array( $atts['gsap_motion'] ) ) ? $atts['gsap_motion'] : [];

    $effect  = isset( $g['effect'] ) ? (string) $g['effect'] : 'none';
    $allowed = [ 'reveal', 'stagger', 'splittext', 'parallax', 'pin', 'scrub', 'zoom', 'rotate', 'blur', 'clip', 'skew' ];
    if ( ! in_array( $effect, $allowed, true ) ) {
        return $attr;
    }

    $s = ( isset( $g[ $effect ] ) && is_array( $g[ $effect ] ) ) ? $g[ $effect ] : [];

    // Local helpers ---------------------------------------------------------
    $data = [ 'data-upw-g' => $effect ];

    $num = function ( $key, $default ) use ( $s ) {
        return isset( $s[ $key ] ) && is_numeric( $s[ $key ] )
            ? rtrim( rtrim( number_format( (float) $s[ $key ], 2, '.', '' ), '0' ), '.' )
            : (string) $default;
    };
    $pick = function ( $key, array $allow, $default ) use ( $s ) {
        $v = isset( $s[ $key ] ) ? (string) $s[ $key ] : $default;
        return in_array( $v, $allow, true ) ? $v : $default;
    };
    $on = function ( $key, $default_yes = true ) use ( $s ) {
        $v = isset( $s[ $key ] ) ? (string) $s[ $key ] : ( $default_yes ? 'yes' : 'no' );
        return $v === 'yes';
    };

    $dir_allow   = [ 'up', 'down', 'left', 'right', 'none' ];
    $style_allow = [ 'subtle', 'standard', 'dramatic' ];
    $start_allow = [ 'top 85%', 'top 100%', 'top 70%', 'top center', 'top 40%' ];

    $pending = false; // effects that start hidden need the FOUC guard class

    switch ( $effect ) {
        case 'reveal':
            $dir = $pick( 'direction', $dir_allow, 'up' );
            if ( $dir !== 'up' )    $data['data-upw-g-dir']      = $dir;
            $data['data-upw-g-style']    = $pick( 'style', $style_allow, 'standard' );
            $data['data-upw-g-distance'] = $num( 'distance', 50 );
            if ( $num( 'delay', 0 ) !== '0' ) $data['data-upw-g-delay'] = $num( 'delay', 0 );
            $data['data-upw-g-start'] = $pick( 'start', $start_allow, 'top 85%' );
            if ( ! $on( 'once', true ) ) $data['data-upw-g-once'] = '0';
            if ( ! $on( 'run_on_mobile', true ) ) $data['data-upw-g-mobile'] = '0';
            $pending = true;
            break;

        case 'stagger':
            $dir = $pick( 'direction', $dir_allow, 'up' );
            if ( $dir !== 'up' )    $data['data-upw-g-dir']      = $dir;
            $data['data-upw-g-style']    = $pick( 'style', $style_allow, 'standard' );
            $data['data-upw-g-distance'] = $num( 'distance', 50 );
            $data['data-upw-g-each']     = $num( 'stagger_each', 0.12 );
            $data['data-upw-g-from']     = $pick( 'stagger_from', [ 'start', 'end', 'center', 'edges' ], 'start' );
            if ( $num( 'delay', 0 ) !== '0' ) $data['data-upw-g-delay'] = $num( 'delay', 0 );
            $data['data-upw-g-start']    = $pick( 'start', $start_allow, 'top 85%' );
            if ( ! $on( 'run_on_mobile', true ) ) $data['data-upw-g-mobile'] = '0';
            $pending = true;
            break;

        case 'splittext':
            $data['data-upw-g-split']  = $pick( 'split_by', [ 'chars', 'words', 'lines' ], 'chars' );
            $data['data-upw-g-target'] = $pick( 'target', [ 'headings', 'paragraphs', 'all' ], 'headings' );
            $data['data-upw-g-style']  = $pick( 'style', $style_allow, 'standard' );
            $data['data-upw-g-each']   = $num( 'stagger_each', 0.03 );
            $dir = $pick( 'direction', [ 'up', 'down' ], 'up' );
            if ( $dir !== 'up' ) $data['data-upw-g-dir'] = $dir;
            $data['data-upw-g-start']  = $pick( 'start', $start_allow, 'top 85%' );
            if ( ! $on( 'run_on_mobile', true ) ) $data['data-upw-g-mobile'] = '0';
            $pending = true;
            break;

        case 'parallax':
            $data['data-upw-g-axis']  = $pick( 'axis', [ 'vertical', 'horizontal' ], 'vertical' ) === 'horizontal' ? 'x' : 'y';
            $data['data-upw-g-speed'] = $num( 'speed', 20 );
            if ( ! $on( 'run_on_mobile', false ) ) $data['data-upw-g-mobile'] = '0';
            break;

        case 'pin':
            $data['data-upw-g-pin-length'] = $num( 'pin_length', 100 );
            if ( ! $on( 'run_on_mobile', false ) ) $data['data-upw-g-mobile'] = '0';
            break;

        case 'scrub':
            $kind = $pick( 'scrub_kind', [ 'fade', 'scale', 'rotate', 'slide' ], 'fade' );
            $data['data-upw-g-scrub-kind'] = $kind;
            $data['data-upw-g-intensity']  = $num( 'intensity', 20 );
            $data['data-upw-g-start']      = $pick( 'start', $start_allow, 'top 85%' );
            if ( ! $on( 'run_on_mobile', true ) ) $data['data-upw-g-mobile'] = '0';
            $pending = ( $kind === 'fade' ); // only fade starts invisible
            break;

        case 'zoom':
            $data['data-upw-g-scale'] = $num( 'scale', 0.6 );
            $data['data-upw-g-start'] = $pick( 'start', $start_allow, 'top 85%' );
            if ( $num( 'delay', 0 ) !== '0' ) $data['data-upw-g-delay'] = $num( 'delay', 0 );
            if ( ! $on( 'once', true ) )          $data['data-upw-g-once']   = '0';
            if ( ! $on( 'run_on_mobile', true ) ) $data['data-upw-g-mobile'] = '0';
            $pending = true;
            break;

        case 'rotate':
            $data['data-upw-g-rotate'] = $num( 'rotate', 8 );
            $data['data-upw-g-dir']    = $pick( 'direction', [ 'left', 'right' ], 'left' );
            $data['data-upw-g-start']  = $pick( 'start', $start_allow, 'top 85%' );
            if ( $num( 'delay', 0 ) !== '0' ) $data['data-upw-g-delay'] = $num( 'delay', 0 );
            if ( ! $on( 'once', true ) )          $data['data-upw-g-once']   = '0';
            if ( ! $on( 'run_on_mobile', true ) ) $data['data-upw-g-mobile'] = '0';
            $pending = true;
            break;

        case 'blur':
            $data['data-upw-g-blur']  = $num( 'blur', 12 );
            $data['data-upw-g-start'] = $pick( 'start', $start_allow, 'top 85%' );
            if ( $num( 'delay', 0 ) !== '0' ) $data['data-upw-g-delay'] = $num( 'delay', 0 );
            if ( ! $on( 'once', true ) )          $data['data-upw-g-once']   = '0';
            if ( ! $on( 'run_on_mobile', true ) ) $data['data-upw-g-mobile'] = '0';
            $pending = true;
            break;

        case 'clip':
            $data['data-upw-g-dir']   = $pick( 'direction', [ 'up', 'down', 'left', 'right' ], 'up' );
            $data['data-upw-g-start'] = $pick( 'start', $start_allow, 'top 85%' );
            if ( $num( 'delay', 0 ) !== '0' ) $data['data-upw-g-delay'] = $num( 'delay', 0 );
            if ( ! $on( 'once', true ) )          $data['data-upw-g-once']   = '0';
            if ( ! $on( 'run_on_mobile', true ) ) $data['data-upw-g-mobile'] = '0';
            $pending = true;
            break;

        case 'skew':
            $data['data-upw-g-skew']     = $num( 'skew', 8 );
            $data['data-upw-g-distance'] = $num( 'distance', 40 );
            $data['data-upw-g-start']    = $pick( 'start', $start_allow, 'top 85%' );
            if ( $num( 'delay', 0 ) !== '0' ) $data['data-upw-g-delay'] = $num( 'delay', 0 );
            if ( ! $on( 'once', true ) )          $data['data-upw-g-once']   = '0';
            if ( ! $on( 'run_on_mobile', true ) ) $data['data-upw-g-mobile'] = '0';
            $pending = true;
            break;
    }

    // Merge data-* attributes (escaped).
    foreach ( $data as $k => $v ) {
        $attr[ $k ] = esc_attr( $v );
    }

    // Add the pending guard class to existing classes for hidden-start effects.
    if ( $pending ) {
        $existing_class = isset( $attr['class'] ) ? trim( (string) $attr['class'] ) : '';
        $attr['class']  = esc_attr( $existing_class === '' ? 'upw-g-pending' : $existing_class . ' upw-g-pending' );
    }

    sc_gsap_flag( true );
    sc_gsap_used( $effect );

    return $attr;
}, 25, 2 );

/**
 * Force a wrapper when an element's ONLY non-default setting is a GSAP scroll
 * effect. Leaf shortcodes that gate their wrapper on sc_needs_wrapper() (e.g.
 * text-block, media-image) otherwise emit no wrapper, so the data-upw-g*
 * attributes the filter above stamps onto $attr have nowhere to land and the
 * animation silently never fires. Keeping this in the GSAP module (rather than
 * hard-coding gsap_motion into sc_needs_wrapper) keeps the engine self-contained.
 */
add_filter( 'sc_needs_wrapper', function ( $needs, $atts ) {
    if ( $needs ) { return $needs; }
    $g      = ( isset( $atts['gsap_motion'] ) && is_array( $atts['gsap_motion'] ) ) ? $atts['gsap_motion'] : [];
    $effect = isset( $g['effect'] ) ? (string) $g['effect'] : 'none';
    return in_array( $effect, [ 'reveal', 'stagger', 'splittext', 'parallax', 'pin', 'scrub' ], true );
}, 10, 2 );


/**
 * Conditionally enqueue the bundled GSAP + ScrollTrigger + initializer + the
 * failsafe CSS at the start of wp_footer. Only fires when at least one shortcode
 * rendered with a GSAP effect, so un-animated pages ship none of it.
 */
add_action( 'wp_footer', function () {
    if ( ! sc_gsap_flag() ) return;

    $ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
    if ( ! $ext ) return;

    $ver     = $ext->manifest->get_version();
    $gsap_ver = '3.13.0';

    // Vendor files are already minified — reference them directly (do NOT pass
    // through fw_min_uri, which would look for *.min.min.js).
    wp_enqueue_script(
        'upw-gsap-core',
        $ext->get_declared_URI( '/modules/scroll-motion/static/js/vendor/gsap/gsap.min.js' ),
        [],
        $gsap_ver,
        true
    );
    wp_enqueue_script(
        'upw-gsap-scrolltrigger',
        $ext->get_declared_URI( '/modules/scroll-motion/static/js/vendor/gsap/ScrollTrigger.min.js' ),
        [ 'upw-gsap-core' ],
        $gsap_ver,
        true
    );
    $init_deps = [ 'upw-gsap-scrolltrigger' ];

    // SplitText is only needed when a "Split Text" effect is on the page.
    $used = function_exists( 'sc_gsap_used' ) ? sc_gsap_used() : [];
    if ( isset( $used['splittext'] ) ) {
        wp_enqueue_script(
            'upw-gsap-splittext',
            $ext->get_declared_URI( '/modules/scroll-motion/static/js/vendor/gsap/SplitText.min.js' ),
            [ 'upw-gsap-core' ],
            $gsap_ver,
            true
        );
        $init_deps[] = 'upw-gsap-splittext';
    }

    wp_enqueue_script(
        'upw-gsap-init',
        $ext->get_declared_URI( '/modules/scroll-motion/static/js/upw-gsap.js' ),
        $init_deps,
        $ver,
        true
    );

    wp_enqueue_style(
        'upw-gsap',
        $ext->get_declared_URI( '/modules/scroll-motion/static/css/upw-gsap.css' ),
        [],
        $ver
    );
}, 5 );
