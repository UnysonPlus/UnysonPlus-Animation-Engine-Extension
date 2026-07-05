<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Scroll Motion module: runtime render + enqueue.
 *
 * Stamps the GSAP `data-upw-g*` attributes (+ `upw-g-pending` guard class) onto the shortcode
 * wrapper (sc_build_wrapper_attr), forces a wrapper when a GSAP effect is the only setting
 * (sc_needs_wrapper), and conditionally enqueues the bundled GSAP + ScrollTrigger + initializer
 * on wp_footer. Depends on sc_gsap_flag() / sc_gsap_used() from scroll-motion-helpers.php.
 */


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
            if ( $pick( 'scope', [ 'auto', 'direct' ], 'auto' ) === 'direct' ) $data['data-upw-g-scope'] = 'direct';
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
