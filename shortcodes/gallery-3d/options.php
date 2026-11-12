<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery options. A popover Design picker (like the engine's other pickers) selects the 3D
 * layout; each design reveals ITS OWN controls, so designs stay self-contained and more slot in
 * trivially. Shared card controls (Box Style, shadow, captions, lightbox) live on the Style tab.
 *
 * Designs: carousel_ring (rotating ring) · panorama_wall (curved, scrolling multi-row wall).
 * The view reads the chosen design via design_settings/design and its options via
 * design_settings/<design>/<sub>.
 */

$ratio_choices = array(
	'1-1'      => __( 'Square (1:1)', 'fw' ),
	'4-3'      => __( 'Landscape (4:3)', 'fw' ),
	'3-4'      => __( 'Portrait (3:4)', 'fw' ),
	'4-5'      => __( 'Portrait (4:5)', 'fw' ),
	'16-9'     => __( 'Wide (16:9)', 'fw' ),
	'9-16'     => __( 'Tall (9:16)', 'fw' ),
	// Each card keeps its OWN image's shape, so a gallery of mixed portrait / landscape / square
	// photos shows at their natural sizes instead of all being cropped to one ratio. Supported on the
	// flat-card families (Carousel & Flow, Stack & Scatter, and the rings); designs that build their
	// geometry from a single ratio (sphere, grids, device frames) fall back to Square.
	'original' => __( 'Original — mixed (keep each image’s shape)', 'fw' ),
);
$slider = function ( $label, $val, $min, $max, $step, $desc = '' ) {
	$f = array( 'type' => 'slider', 'label' => $label, 'value' => $val, 'properties' => array( 'min' => $min, 'max' => $max, 'step' => $step ) );
	if ( $desc !== '' ) { $f['desc'] = $desc; }
	return $f;
};
$switch = function ( $label, $val, $desc = '' ) {
	$f = array( 'type' => 'switch', 'label' => $label, 'value' => $val, 'left-choice' => array( 'value' => 'no', 'label' => __( 'No', 'fw' ) ), 'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ) );
	if ( $desc !== '' ) { $f['desc'] = $desc; }
	return $f;
};
$hover = function ( $desc = '' ) {
	$f = array(
		'label'   => __( 'On Hover', 'fw' ),
		'type'    => 'select',
		'value'   => 'slow',
		'choices' => array(
			'none'  => __( 'Keep rotating', 'fw' ),
			'pause' => __( 'Pause', 'fw' ),
			'slow'  => __( 'Slow down', 'fw' ),
		),
	);
	if ( $desc !== '' ) { $f['desc'] = $desc; }
	return $f;
};
/* Motion as an INLINE multi-picker (label on the picker, per the multi-picker conventions): each mode
 * reveals ONLY its own settings — Auto shows loop/direction/hover, Scroll-scrub shows pin/length/
 * direction, Static shows nothing. Drag to spin + Drag Momentum stay OUTSIDE (they layer over any
 * mode). NOTE the key is `motion` (new) — never reuse the old scalar `drive` key: existing saves have
 * nothing at `motion`, so the picker just renders its default (no value-shape migration needed; the
 * views fall back to the legacy flat keys). */
$motion_picker = function ( $auto_key, $auto_label, $speed_def, $speed_min, $speed_max, $dir_choices ) use ( $slider, $switch, $hover ) {
	$dir = array( 'label' => __( 'Direction', 'fw' ), 'type' => 'select', 'value' => 'left', 'choices' => $dir_choices );
	return array(
		'type'         => 'multi-picker',
		'label'        => false,
		'desc'         => false,
		'show_borders' => false,
		'value'        => array( 'mode' => $auto_key ),
		'picker'       => array(
			'mode' => array(
				'type'    => 'select',
				'label'   => __( 'Motion', 'fw' ),
				'desc'    => __( 'How it moves on its own — each mode shows only its own settings.', 'fw' ),
				'choices' => array( $auto_key => $auto_label, 'scroll' => __( 'Scroll-scrub', 'fw' ), 'static' => __( 'Static', 'fw' ) ),
			),
		),
		'choices' => array(
			$auto_key => array(
				'speed'          => $slider( __( 'Loop Duration (s)', 'fw' ), $speed_def, $speed_min, $speed_max, 1, __( 'Seconds for one full loop — lower is faster.', 'fw' ) ),
				'direction'      => $dir,
				'hover_behavior' => $hover( __( 'What happens when the visitor hovers the gallery. No effect when used as a Section Background.', 'fw' ) ),
			),
			'scroll' => array(
				'pin'           => $switch( __( 'Pin while scrubbing', 'fw' ), 'yes', __( 'Hold the gallery on screen while the visitor scrolls — their scrolling drives the motion across the whole pinned stretch, then the page continues. Off = it scrolls past like normal content. No effect as a Section Background.', 'fw' ) ),
				'scroll_length' => $slider( __( 'Scroll Length (viewports)', 'fw' ), 2.5, 1, 5, 0.5, __( 'How much scrolling the pin holds, in screen-heights. Longer = a slower, more cinematic scrub.', 'fw' ) ),
				'rounds'        => $slider( __( 'Rounds per scroll', 'fw' ), 1, 0.25, 6, 0.25, __( 'How far the scrub travels across the pin. Rotating designs (Ring, Wheel, Orbit…) read it as full turns; flowing designs (walls, tunnels, reels, strips…) read it as passes — the deck travels its length that many times. Pair with Entrance (Style tab) for a scroll choreography — the images fade in one by one at the start and out one by one at the end.', 'fw' ) ),
				'direction'     => $dir,
			),
		),
	);
};

$height_field = array(
	'type'  => 'unit-input',
	'label' => __( 'Stage Height', 'fw' ),
	'units' => array( 'px', 'vh' ),
	'value' => array( 'value' => 730, 'unit' => 'px' ),
	'min'   => 200,
	'desc'  => __( 'Height of the 3D stage.', 'fw' ),
);
$bg_field = function_exists( 'sc_color_field_compact' )
	? sc_color_field_compact( array( 'label' => __( 'Stage Background', 'fw' ), 'kind' => 'bg', 'desc' => __( 'Behind the scene. Leave empty for transparent.', 'fw' ) ) )
	: array( 'type' => 'color-picker', 'label' => __( 'Stage Background', 'fw' ) );
$overflow_field = array(
	'type'    => 'select',
	'label'   => __( 'Stage Overflow', 'fw' ),
	'value'   => 'clip',
	'choices' => array(
		'clip'  => __( 'Clip to stage (default)', 'fw' ),
		'spill' => __( 'Spill (show cards in full)', 'fw' ),
	),
	'desc'    => __( 'Clip contains everything to the Stage Height box. Spill lets fanned / oversized cards show in full beyond it — use this for the fan / wheel designs where the frame would otherwise cut cards off, so you don\'t have to over-size the Stage Height.', 'fw' ),
	'help'    => __( 'Spill only changes what\'s visible, not the reserved layout height — so spilled cards can overlap whatever sits directly above or below. Leave some vertical space around the gallery. Ignored while the gallery is a Section Background or pinned for scroll-scrub (those own their own clipping).', 'fw' ),
);

/* ---- Design tiles for the popover picker ---- */
$ae       = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
$img_base = $ae ? $ae->get_declared_URI( '/shortcodes/gallery-3d/static/img/designs' ) : '';
// Cache-bust the tile SVGs by the extension version so an updated design preview refreshes in the
// builder instead of serving the browser-cached old image at the same URL (this bit the Photo
// Scatter tile — the redrawn SVG kept showing the stale carousel-lookalike until a hard refresh).
$img_ver  = ( $ae && $ae->manifest ) ? '?v=' . rawurlencode( $ae->manifest->get_version() ) : '';
// Category => designs (mirrors the reference grouping; the picker shows these as tabs).
$design_groups = array(
	// NOTE: the abstract "Scroll Choreography" (scroll_choreography) design is RETIRED from the picker —
	// native design-agnostic chaining (the top-level "Chain with adjacent galleries" switch) replaces it,
	// so you chain real designs instead of configuring abstract formation steps. Its runtime, options and
	// view are kept below only so galleries saved on it before this change keep rendering (back-compat);
	// it is simply no longer offered for new galleries.
	'grp_3dp' => array(
		'label'   => __( '3D & Perspective', 'fw' ),
		'designs' => array(
			'carousel_ring' => __( 'Carousel Ring', 'fw' ),
			'panorama_wall'     => __( 'Panorama Wall', 'fw' ),
			'card_sphere'      => __( 'Card Sphere', 'fw' ),
			'orbit_globe'      => __( 'Orbit Globe', 'fw' ),
			'sphere_cascade'   => __( 'Sphere Cascade', 'fw' ),
			'totem_wall'       => __( 'Totem Wall', 'fw' ),
			'parallax_totem'   => __( 'Parallax Totem', 'fw' ),
			'card_tunnel'      => __( 'Card Tunnel', 'fw' ),
			'spiral_stream'    => __( 'Spiral Stream', 'fw' ),
			'depth_stack'      => __( 'Depth Stack', 'fw' ),
		),
	),
	'grp_stack' => array(
		'label'   => __( 'Stack & Scatter', 'fw' ),
		'designs' => array(
			'photo_scatter' => __( 'Photo Scatter', 'fw' ),
			'card_stack'    => __( 'Card Stack', 'fw' ),
			'card_fan'      => __( 'Card Fan', 'fw' ),
			'shuffle_deck'  => __( 'Shuffle Deck', 'fw' ),
			'polaroid_drop' => __( 'Polaroid Drop', 'fw' ),
		),
	),
	'grp_device' => array(
		'label'   => __( 'Devices & Screens', 'fw' ),
		'designs' => array(
			'device_cycler' => __( 'Device Cycler', 'fw' ),
			'phone_lineup'  => __( 'Phone Lineup', 'fw' ),
			'window_stack'  => __( 'Window Stack', 'fw' ),
			'device_trio'   => __( 'Device Trio', 'fw' ),
		),
	),
	/* Coming-soon FAMILIES — each is one badged "SOON" tile in its own category tab. These `soon_*`
	 * keys are deliberately NOT in the render registry (DEFAULTS / view whitelist), so selecting one
	 * can never render an empty gallery — the preview and the view both fall back to carousel_ring.
	 * Their reveal (below) is a "coming soon" note instead of controls. Tile art: static/img/designs/
	 * soon-*.svg (a muted family glyph + a SOON ribbon). */
	'grp_carousel'  => array( 'label' => __( 'Carousel & Flow', 'fw' ),   'designs' => array( 'card_reel' => __( 'Card Reel', 'fw' ), 'film_strip' => __( 'Film Strip', 'fw' ), 'wheel_carousel' => __( 'Wheel Carousel', 'fw' ), 'cover_flow' => __( 'Cover Flow', 'fw' ), 'carousel_flow' => __( 'Carousel Flow', 'fw' ), 'hero_reel' => __( 'Hero Reel', 'fw' ), 'focus_slider' => __( 'Focus Slider', 'fw' ), 'mosaic_marquee' => __( 'Mosaic Marquee', 'fw' ) ) ),
	'grp_grid'      => array( 'label' => __( 'Grid', 'fw' ),              'designs' => array( 'wave_grid' => __( 'Wave Grid', 'fw' ), 'flip_grid' => __( 'Flip Grid', 'fw' ), 'magnet_grid' => __( 'Magnet Grid', 'fw' ) ) ),
	'grp_iso'       => array( 'label' => __( 'Isometric', 'fw' ),         'designs' => array( 'tilt_deck' => __( 'Tilt Deck', 'fw' ), 'iso_orbit' => __( 'Iso Orbit', 'fw' ), 'iso_stack' => __( 'Iso Stack', 'fw' ), 'iso_cascade' => __( 'Iso Cascade', 'fw' ), 'iso_fan' => __( 'Iso Fan', 'fw' ) ) ),
	'grp_orbit'     => array( 'label' => __( 'Orbit', 'fw' ),             'designs' => array( 'orbit_bloom' => __( 'Orbit Bloom', 'fw' ), 'focus_orbit' => __( 'Focus Orbit', 'fw' ), 'dual_orbit' => __( 'Dual Orbit', 'fw' ), 'comet_orbit' => __( 'Comet Orbit', 'fw' ), 'orbit_swarm' => __( 'Orbit Swarm', 'fw' ), 'ring_tilt' => __( 'Ring Tilt', 'fw' ), 'gyro_orbit' => __( 'Gyro Orbit', 'fw' ), 'infinity_orbit' => __( 'Infinity Orbit', 'fw' ), 'vortex' => __( 'Vortex', 'fw' ), 'pendulum_orbit' => __( 'Pendulum Orbit', 'fw' ), 'moon_orbit' => __( 'Moon Orbit', 'fw' ), 'tiered_orbit' => __( 'Tiered Orbit', 'fw' ) ) ),
	'grp_reveal'    => array( 'label' => __( 'Reveal & Wipe', 'fw' ),     'designs' => array( 'blind_reveal' => __( 'Blind Reveal', 'fw' ), 'iris_wipe' => __( 'Iris Wipe', 'fw' ), 'tile_dissolve' => __( 'Tile Dissolve', 'fw' ) ) ),
	'grp_spotlight' => array( 'label' => __( 'Spotlight & Focus', 'fw' ), 'designs' => array( 'spotlight_wall' => __( 'Spotlight Wall', 'fw' ), 'focus_pull' => __( 'Focus Pull', 'fw' ), 'loupe' => __( 'Loupe', 'fw' ) ) ),
	'grp_webgl'     => array( 'label' => __( 'WebGL (real 3D)', 'fw' ), 'designs' => array( 'webgl_mirror_ring' => __( 'Mirror Ring', 'fw' ), 'webgl_depth_field' => __( 'Depth Field', 'fw' ), 'webgl_wave_wall' => __( 'Wave Wall', 'fw' ) ) ),
);

/**
 * Register a custom Motion Gallery design in the picker from a child theme (the JS half is
 * window.upwGallery3d.registerDesign). Add a group, or a design to an existing group's 'designs':
 * a design value is a label string (tile art derived from the key), OR an array
 * array( 'label' => '...', 'tile' => '<image url>' ) to supply the picker tile. A key added here
 * must also be allowed to render — see the 'upw_gallery_3d_render_designs' filter in view.php — and
 * have its JS driver registered. Part of the gap → fallback loop (a child-theme design for a motion
 * the built-in library doesn't cover yet).
 */
$design_groups = apply_filters( 'upw_gallery_3d_design_groups', $design_groups );

$mk_tile = function ( $dk, $dl ) use ( $img_base, $img_ver ) {
	// A design value may be array( 'label' => ..., 'tile' => <url> ) (custom) or a plain label string.
	if ( is_array( $dl ) ) {
		$label = isset( $dl['label'] ) ? $dl['label'] : $dk;
		$tile  = isset( $dl['tile'] ) && $dl['tile'] ? $dl['tile'] : ( $img_base . '/' . str_replace( '_', '-', $dk ) . '.svg' . $img_ver );
		return array(
			'small' => array( 'src' => $tile, 'height' => 62, 'title' => $label ),
			'large' => array( 'src' => $tile, 'height' => 130 ),
			'label' => $label,
		);
	}
	$file = str_replace( '_', '-', $dk );
	return array(
		'small' => array( 'src' => $img_base . '/' . $file . '.svg' . $img_ver, 'height' => 62, 'title' => $dl ),
		'large' => array( 'src' => $img_base . '/' . $file . '.svg' . $img_ver, 'height' => 130 ),
		'label' => $dl,
	);
};
$design_tiles = array();
foreach ( $design_groups as $gk => $g ) {
	$grp = array();
	foreach ( $g['designs'] as $dk => $dl ) { $grp[ $dk ] = $mk_tile( $dk, $dl ); }
	$design_tiles[ $gk ] = array( 'label' => $g['label'], 'choices' => $grp );
}

/* ---- "Coming soon" family reveal --------------------------------------------------------------
 * The coming-soon FAMILIES render as badged SOON tiles in the picker (grp_* with 'soon'=>true above;
 * tile art = static/img/designs/soon-*.svg). Clicking one is harmless — soon_* is not a registered
 * design, so the live preview and the front-end view both fall back to carousel_ring — so instead of
 * design controls we reveal this "coming soon" note (with the roadmap link). One shared note, mapped
 * to every soon_* key. */
$roadmap_url = 'https://docs.unysonplus.com/animation-engine/3d-motion-roadmap';
$soon_reveal = array(
	'group_soon' => array( 'type' => 'group', 'options' => array(
		'soon_note' => array(
			'type'  => 'html',
			'label' => false,
			'desc'  => false,
			'html'  => '<div class="upw-soon-note"><span class="upw-soon-note__b">' . esc_html__( 'Coming soon.', 'fw' ) . '</span> '
				. sprintf(
					/* translators: %s = roadmap link */
					esc_html__( 'This design isn\'t available yet — the Motion Gallery library is growing, with 50+ designs planned across these families. Pick an available design above, or %s.', 'fw' ),
					'<a href="' . esc_url( $roadmap_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'see the roadmap →', 'fw' ) . '</a>'
				) . '</div>',
		),
	) ),
);
// Map every soon_* family key (collected from the groups flagged 'soon') to the shared reveal.
$soon_choices = array();
foreach ( $design_groups as $g ) {
	if ( empty( $g['soon'] ) ) { continue; }
	foreach ( array_keys( $g['designs'] ) as $dk ) { $soon_choices[ $dk ] = $soon_reveal; }
}

/* ---- Per-design reveal option sets ---- */
$carousel_ring_opts = array(
	'group_ss_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'auto', __( 'Auto-rotate', 'fw' ), 16, 3, 60, array( 'left' => __( 'Left', 'fw' ), 'right' => __( 'Right', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to spin', 'fw' ), 'yes', __( 'Let visitors grab and spin the ring by hand — layers on top of any Motion (grabbing pauses it, releasing resumes). No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep spinning after releasing a drag (Drag to spin).', 'fw' ) ),
	) ),
	'group_ss_ring' => array( 'type' => 'group', 'options' => array(
		'tilt'        => $slider( __( 'Ring Tilt (°)', 'fw' ), -28, -60, 60, 1, __( 'Tips the ring toward or away from the viewer.', 'fw' ) ),
		'sway'        => $slider( __( 'Vertical Sway (°)', 'fw' ), 0, 0, 45, 1, __( 'Rocks the ring up and down as it spins (twice per turn), so you orbit it from shifting heights — a “product orbit” look. 0 = a flat carousel.', 'fw' ) ),
		'ring_opening' => $slider( __( 'Ring Opening (%)', 'fw' ), 55, 0, 100, 1, __( 'How far the ring opens; low flattens it toward edge-on, high opens the loop.', 'fw' ) ),
		'roll'        => $slider( __( 'Diagonal Tilt (°)', 'fw' ), 0, -45, 45, 1, __( 'Rolls the whole ring to a diagonal — negative rolls the other way.', 'fw' ) ),
		'ring_size'   => $slider( __( 'Ring Size (%)', 'fw' ), 80, 40, 140, 1, __( 'Radius of the ring.', 'fw' ) ),
		'spacing'     => $slider( __( 'Card Spacing (%)', 'fw' ), 100, 60, 180, 1, __( 'Gap between cards around the ring.', 'fw' ) ),
		'perspective' => $slider( __( 'Perspective', 'fw' ), 18, 8, 100, 1, __( 'Higher = stronger depth (closer camera).', 'fw' ) ),
		'back_fade'   => $slider( __( 'Back Fade (%)', 'fw' ), 70, 0, 100, 1, __( 'How much cards on the far side dim.', 'fw' ) ),
	) ),
	'group_ss_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Card Size (%)', 'fw' ), 21, 6, 60, 1, __( 'Card width as a % of the stage width.', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '1-1', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 6, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 30, 0.5, __( 'Inner frame around each image, as a % of the card width.', 'fw' ) ),
	) ),
	'group_ss_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

$panorama_wall_opts = array(
	'group_sw_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 20, 5, 90, array( 'left' => __( 'Left', 'fw' ), 'right' => __( 'Right', 'fw' ), 'alternate' => __( 'Alternate rows', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to spin', 'fw' ), 'yes', __( 'Let visitors grab and scroll the wall by hand — layers on top of any Motion (grabbing pauses it, releasing resumes). No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep scrolling after releasing a drag (Drag to spin).', 'fw' ) ),
	) ),
	'group_sw_wall' => array( 'type' => 'group', 'options' => array(
		'rows'        => $slider( __( 'Rows', 'fw' ), 5, 1, 9, 1, __( 'Number of stacked rows of cards.', 'fw' ) ),
		'columns'     => $slider( __( 'Columns', 'fw' ), 11, 3, 24, 1, __( 'Cards across each row (images repeat to fill). More = denser wall.', 'fw' ) ),
		'curvature'   => $slider( __( 'Curvature (%)', 'fw' ), -100, -150, 150, 1, __( 'Negative = concave (the wall wraps toward you); positive = convex (it bulges away). Bigger magnitude = tighter curve.', 'fw' ) ),
		'tilt'        => $slider( __( 'Tilt (°)', 'fw' ), 0, -45, 45, 1, __( 'Tips the whole wall up or down.', 'fw' ) ),
		'gap'         => $slider( __( 'Gap (%)', 'fw' ), 5, 0, 20, 0.5, __( 'Space between cards, as a % of the card width.', 'fw' ) ),
		'edge_fade'   => $slider( __( 'Edge Fade (%)', 'fw' ), 0, 0, 100, 1, __( 'Fades the cards toward the left/right edges.', 'fw' ) ),
		'perspective' => $slider( __( 'Perspective', 'fw' ), 68, 8, 100, 1, __( 'Higher = stronger depth (closer camera).', 'fw' ) ),
	) ),
	'group_sw_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Card Size (%)', 'fw' ), 20, 6, 40, 1, __( 'Card width as a % of the stage width.', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '16-9', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 2, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 30, 0.5, __( 'Inner frame around each image, as a % of the card width.', 'fw' ) ),
	) ),
	'group_sw_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

$card_sphere_opts = array(
	'group_cg_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 20, 5, 90, array( 'left' => __( 'Left', 'fw' ), 'right' => __( 'Right', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to spin', 'fw' ), 'yes', __( 'Let visitors grab and spin the globe by hand — layers on top of any Motion (grabbing pauses it, releasing resumes). No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep spinning after releasing a drag (Drag to spin).', 'fw' ) ),
	) ),
	'group_cg_globe' => array( 'type' => 'group', 'options' => array(
		'globe_size'  => $slider( __( 'Globe Size (%)', 'fw' ), 70, 40, 95, 1, __( 'Diameter of the sphere, as a % of the shorter side of the stage. A pure zoom: the sphere and its cards scale together, so the tiling does not change.', 'fw' ) ),
		'gap'         => $slider( __( 'Gap (%)', 'fw' ), 2.5, 0, 8, 0.5, __( 'Space between cards, as a % of the card width.', 'fw' ) ),
		'back_fade'   => $slider( __( 'Back Fade (%)', 'fw' ), 55, 0, 90, 1, __( 'How much cards near the rim dim.', 'fw' ) ),
		'tilt'        => $slider( __( 'Tilt (°)', 'fw' ), 0, -45, 45, 1, __( 'Tips the globe up or down.', 'fw' ) ),
		'perspective' => $slider( __( 'Perspective', 'fw' ), 55, 8, 100, 1, __( 'Higher = stronger depth (closer camera).', 'fw' ) ),
	) ),
	'group_cg_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Card Size (%)', 'fw' ), 20, 8, 30, 1, __( 'Size of each card as a % of the globe. This is the density control — smaller cards tile the sphere more finely, so it reads round instead of faceted.', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '16-9', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 2, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 30, 0.5, __( 'Inner frame around each image, as a % of the card width.', 'fw' ) ),
	) ),
	'group_cg_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

$orbit_globe_opts = array(
	'group_og_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 20, 5, 90, array( 'left' => __( 'Left', 'fw' ), 'right' => __( 'Right', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to spin', 'fw' ), 'yes', __( 'Let visitors grab and spin the globe by hand — layers on top of any Motion (grabbing pauses it, releasing resumes). No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep spinning after releasing a drag (Drag to spin).', 'fw' ) ),
	) ),
	'group_og_globe' => array( 'type' => 'group', 'options' => array(
		'globe_size' => $slider( __( 'Globe Size (%)', 'fw' ), 50, 40, 95, 1, __( 'Diameter of the orbit sphere, as a % of the shorter side of the stage. A pure zoom: the cloud and its cards scale together.', 'fw' ) ),
		'gap'        => $slider( __( 'Gap (%)', 'fw' ), 2.5, 0.5, 8, 0.5, __( 'Spacing between cards — larger values thin out the cloud (fewer cards).', 'fw' ) ),
		'back_fade'  => $slider( __( 'Back Fade (%)', 'fw' ), 55, 0, 90, 1, __( 'How much cards on the far side of the orbit dim.', 'fw' ) ),
		'tilt'       => $slider( __( 'Tilt (°)', 'fw' ), 27, -45, 45, 1, __( 'Tips the orbit axis up or down.', 'fw' ) ),
	) ),
	'group_og_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Card Size (%)', 'fw' ), 28, 8, 30, 1, __( 'Size of each card as a % of the globe. Also the density control — smaller cards pack the cloud more densely (more cards).', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '1-1', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 2, 0, 60, 1 ),
	) ),
	'group_og_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Photo Scatter — photos scattered flat on a tabletop (the "desk" look): seeded random positions +
 * rotations, cards glide in from the edges, settle, then sweep out and the next set slides in.
 * Shuffle rides its own inline multi-picker (NOT the shared Motion picker — dwell/enter semantics,
 * not loop/scrub). */
$photo_scatter_opts = array(
	'group_ps_motion' => array( 'type' => 'group', 'options' => array(
		'cycle' => array(
			'type'         => 'multi-picker',
			'label'        => false,
			'desc'         => false,
			'show_borders' => false,
			'value'        => array( 'mode' => 'auto' ),
			'picker'       => array(
				'mode' => array(
					'type'    => 'select',
					'label'   => __( 'Motion', 'fw' ),
					'desc'    => __( 'How the photos move. "Scatter in & out on scroll" flies each set of photos in, holds, then flies it back out as the visitor scrolls — one set after another (the cinematic pile that keeps refreshing). "Organize on scroll" morphs a single pile from scattered into a tidy grid. "Cycle automatically / on click" swap sets on a timer or a click. "Hold still" is one static scatter. As a Section Background it always cycles automatically.', 'fw' ),
					'choices' => array(
						'scroll_cycle' => __( 'Scatter in & out on scroll', 'fw' ),
						'scroll'       => __( 'Organize on scroll', 'fw' ),
						'auto'         => __( 'Cycle automatically', 'fw' ),
						'click'        => __( 'Cycle on click', 'fw' ),
						'off'          => __( 'Hold still (static scatter)', 'fw' ),
					),
				),
			),
			'choices' => array(
				'auto' => array(
					'dwell'       => $slider( __( 'Dwell (s)', 'fw' ), 6, 2, 20, 0.5, __( 'How long each set rests on the table before the next sweeps in.', 'fw' ) ),
					'hover_pause' => $switch( __( 'Pause on Hover', 'fw' ), 'yes', __( 'Hold the current set while the visitor hovers the scatter.', 'fw' ) ),
				),
				'scroll_cycle' => array(
					'final_organized' => $switch( __( 'End on an organized grid', 'fw' ), 'yes', __( 'The last set arrives in a tidy, upright grid (entering from the bottom) instead of scattered — like a page of search results.', 'fw' ) ),
					'corners'         => $switch( __( 'Leave a few in the corners', 'fw' ), 'no', __( 'A couple of photos from the set just before the final one linger in the corners as the grid flies in — a looser, layered hand-off.', 'fw' ) ),
				),
			),
		),
		'from' => array(
			'label'   => __( 'Photos enter from', 'fw' ),
			'type'    => 'select',
			'desc'    => __( 'Which edge each set flies in from (used by every cycling mode).', 'fw' ),
			'value'   => 'edges',
			'choices' => array(
				'edges'  => __( 'All edges', 'fw' ),
				'top'    => __( 'Top', 'fw' ),
				'bottom' => __( 'Bottom', 'fw' ),
				'sides'  => __( 'Left & right', 'fw' ),
				'random' => __( 'Random', 'fw' ),
			),
		),
		'exit' => array(
			'label'   => __( 'Photos exit toward', 'fw' ),
			'type'    => 'select',
			'desc'    => __( 'How the current set clears before the next flies in. Nearest edge / Up / Down / Sides send them off that way; Gather collects them into a centre pile; Fade dissolves them in place.', 'fw' ),
			'value'   => 'sweep',
			'choices' => array(
				'sweep'  => __( 'Nearest edge', 'fw' ),
				'up'     => __( 'Up (off the top)', 'fw' ),
				'down'   => __( 'Down (off the bottom)', 'fw' ),
				'sides'  => __( 'Left & right', 'fw' ),
				'gather' => __( 'Gather into a pile', 'fw' ),
				'fade'   => __( 'Fade in place', 'fw' ),
			),
		),
	) ),
	'group_ps_scatter' => array( 'type' => 'group', 'options' => array(
		'visible'       => $slider( __( 'Cards per Set', 'fw' ), 9, 3, 50, 1, __( 'How many photos rest on the table at once. Extra images form the next sets. Push it high (30–50) for a dense, edge-to-edge scatter; a single per-batch scene can then hold its whole set at once.', 'fw' ) ),
		'rotation'      => $slider( __( 'Rotation Range (°)', 'fw' ), 12, 0, 35, 1, __( 'Each photo settles at a random tilt within this range — 0 lays them all straight.', 'fw' ) ),
		'size_variance' => $slider( __( 'Size Variance (%)', 'fw' ), 30, 0, 60, 1, __( 'How much the photo sizes differ — 0 makes every photo the same size.', 'fw' ) ),
		'spread'        => $slider( __( 'Spread (%)', 'fw' ), 90, 50, 100, 1, __( 'How much of the stage the scatter uses — lower gathers the pile toward the middle; 95–100 fills edge-to-edge (outer photos bleed off the frame and are clipped).', 'fw' ) ),
		'center_clear'  => $switch( __( 'Keep center clear', 'fw' ), 'no', __( 'Ring the photos around the middle so a centered heading placed over the scatter stays readable — best when the gallery shares a column with a title.', 'fw' ) ),
	) ),
	'group_ps_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Card Size (%)', 'fw' ), 18, 8, 40, 1, __( 'Base photo width as a % of the stage width (Size Variance plays around it).', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '3-4', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 4, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 30, 0.5, __( 'Inner frame around each image — pair with a white Box Preset for the polaroid look.', 'fw' ) ),
	) ),
	'group_ps_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* ---- Card Stack: a deck of image cards — the top one is featured; the rest peek behind it and the
 * deck advances (top card peels away, next comes forward) on scroll or a timer. A "use cases"
 * deck of app windows. ---- */
$card_stack_opts = array(
	'group_cs_cycle' => array( 'type' => 'group', 'options' => array(
		'cycle' => array(
			'type' => 'multi-picker', 'label' => false, 'desc' => false, 'show_borders' => false,
			'value' => array( 'mode' => 'scroll' ),
			'picker' => array( 'mode' => array( 'type' => 'select', 'label' => __( 'Advance', 'fw' ),
				'desc' => __( 'How the deck moves through your cards. On scroll = the top card peels away and the next comes forward as the visitor scrolls (best inside a pinned Scroll Story scene); Automatically = a dwell timer; Off = the top card only.', 'fw' ),
				'choices' => array( 'scroll' => __( 'On scroll', 'fw' ), 'auto' => __( 'Automatically', 'fw' ), 'off' => __( 'Off (top only)', 'fw' ) ) ) ),
			'choices' => array( 'auto' => array( 'dwell' => $slider( __( 'Dwell (s)', 'fw' ), 2.5, 1, 12, 0.5, __( 'Seconds each card stays on top before the next.', 'fw' ) ) ) ),
		),
		'exit' => array( 'label' => __( 'Top card leaves', 'fw' ), 'type' => 'select', 'value' => 'down',
			'desc' => __( 'Which way the top card peels off as the next comes forward.', 'fw' ),
			'choices' => array( 'down' => __( 'Down', 'fw' ), 'up' => __( 'Up', 'fw' ), 'left' => __( 'Left', 'fw' ), 'right' => __( 'Right', 'fw' ) ) ),
	) ),
	'group_cs_stack' => array( 'type' => 'group', 'options' => array(
		'behind'       => $slider( __( 'Cards Behind', 'fw' ), 3, 1, 6, 1, __( 'How many cards peek behind the top one, forming the stack.', 'fw' ) ),
		'stack_offset' => $slider( __( 'Stack Offset (%)', 'fw' ), 5, 0, 14, 0.5, __( 'How far each card behind is nudged up (peeking out) from the one in front.', 'fw' ) ),
		'stack_scale'  => $slider( __( 'Stack Shrink (%)', 'fw' ), 6, 0, 18, 1, __( 'How much smaller each card behind is than the one in front — adds depth.', 'fw' ) ),
	) ),
	'group_cs_card' => array( 'type' => 'group', 'options' => array(
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '4-3', 'choices' => $ratio_choices ),
		'card_size'     => $slider( __( 'Card Size (%)', 'fw' ), 40, 20, 90, 1, __( 'Front card width as a % of the stage width.', 'fw' ) ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 14, 0, 40, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 20, 0.5 ),
	) ),
	'group_cs_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Post types offered by the Post Type source: public + featured-image support, built dynamically so
 * e.g. Portfolio simply appears when that extension is active (no hard dependency). */
$pt_choices = array();
foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt_obj ) {
	if ( 'attachment' === $pt_obj->name || ! post_type_supports( $pt_obj->name, 'thumbnail' ) ) { continue; }
	$pt_choices[ $pt_obj->name ] = $pt_obj->labels->name;
}
if ( empty( $pt_choices ) ) { $pt_choices = array( 'post' => __( 'Posts', 'fw' ) ); }

/* ---- Device Cycler: a device frame whose screen cycles through your images (view modes). ---- */
$device_cycler_opts = array(
	'group_dc_device' => array( 'type' => 'group', 'options' => array(
		'device' => array( 'label' => __( 'Device Frame', 'fw' ), 'type' => 'select', 'value' => 'laptop',
			'choices' => array( 'laptop' => __( 'Laptop', 'fw' ), 'tablet' => __( 'Tablet', 'fw' ), 'phone' => __( 'Phone', 'fw' ), 'browser' => __( 'Browser Window', 'fw' ), 'none' => __( 'No Frame', 'fw' ) ),
			'desc' => __( 'The chrome drawn around the screens. Each of your images becomes one screen state (e.g. a Grid / List / Gallery view).', 'fw' ) ),
	) ),
	'group_dc_cycle' => array( 'type' => 'group', 'options' => array(
		'cycle' => array(
			'type' => 'multi-picker', 'label' => false, 'desc' => false, 'show_borders' => false,
			'value' => array( 'mode' => 'scroll' ),
			'picker' => array( 'mode' => array( 'type' => 'select', 'label' => __( 'Cycle Screens', 'fw' ),
				'desc' => __( 'How the screen changes between your images. On scroll = each screen shows in turn as the visitor scrolls (best inside a pinned Scroll Story scene); Automatically = a dwell timer; Off = the first screen only.', 'fw' ),
				'choices' => array( 'scroll' => __( 'On scroll', 'fw' ), 'auto' => __( 'Automatically', 'fw' ), 'off' => __( 'Off (first only)', 'fw' ) ) ) ),
			'choices' => array( 'auto' => array( 'dwell' => $slider( __( 'Dwell (s)', 'fw' ), 3, 1, 12, 0.5, __( 'Seconds each screen stays before the next.', 'fw' ) ) ) ),
		),
		'transition' => array( 'label' => __( 'Transition', 'fw' ), 'type' => 'select', 'value' => 'fade',
			'choices' => array( 'fade' => __( 'Cross-fade', 'fw' ), 'slide_up' => __( 'Slide up', 'fw' ), 'none' => __( 'Cut', 'fw' ) ) ),
	) ),
	'group_dc_screen' => array( 'type' => 'group', 'options' => array(
		'card_ratio'    => array( 'label' => __( 'Screen Ratio', 'fw' ), 'type' => 'select', 'value' => '16-9', 'choices' => $ratio_choices ),
		'card_size'     => $slider( __( 'Device Size (%)', 'fw' ), 62, 8, 100, 1, __( 'Device width as a % of the stage width. Small values make a compact device (e.g. a phone tucked in a corner).', 'fw' ) ),
		'corner_radius' => $slider( __( 'Screen Corner (px)', 'fw' ), 4, 0, 40, 1 ),
	) ),
	'group_dc_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Sphere Cascade — the vertical, spherical sibling of Panorama Wall. Columns of cards cascade
 * vertically over a signed-curvature surface: concave bowl → flat grid → full convex sphere. */
$sphere_cascade_opts = array(
	'group_sc_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 20, 5, 90, array( 'up' => __( 'Up', 'fw' ), 'down' => __( 'Down', 'fw' ), 'alternate' => __( 'Alternate columns', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to spin', 'fw' ), 'yes', __( 'Let visitors grab and cascade the columns by hand — layers on top of any Motion (grabbing pauses it, releasing resumes). No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep cascading after releasing a drag (Drag to spin).', 'fw' ) ),
	) ),
	'group_sc_shape' => array( 'type' => 'group', 'options' => array(
		'columns'   => $slider( __( 'Columns', 'fw' ), 5, 2, 5, 1, __( 'Number of card columns across the surface.', 'fw' ) ),
		'curvature' => $slider( __( 'Curvature (concave ↔ convex) (%)', 'fw' ), -100, -150, 150, 1, __( 'Signed: negative curves the grid into a concave bowl (you look inside it); positive bulges it convex — push toward +150 and it wraps into a full sphere. 0 is a flat grid.', 'fw' ) ),
		'tilt'      => $slider( __( 'Tilt (°)', 'fw' ), 0, -45, 45, 1, __( 'Tips the whole surface up or down.', 'fw' ) ),
		'gap'       => $slider( __( 'Gap (%)', 'fw' ), 5, 0.5, 20, 0.5, __( 'Space between cards, as a % of the card width.', 'fw' ) ),
		'edge_fade' => $slider( __( 'Edge Fade (%)', 'fw' ), 0, 0, 100, 1, __( 'Fades cards toward the rim of the surface.', 'fw' ) ),
	) ),
	'group_sc_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Card Size (%)', 'fw' ), 18, 6, 40, 1, __( 'Card width as a % of the stage width.', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '16-9', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 4, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 13, 0, 30, 0.5, __( 'Inner frame around each image, as a % of the card width.', 'fw' ) ),
	) ),
	'group_sc_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Totem Wall — a flat row of INDEPENDENT vertical cylinders. Each column wraps its own cards around a
 * horizontal axis into a rounded "totem", and the row of totems cascades vertically. Density is set by
 * Zoom (not a card count) — higher Zoom shows fewer, larger totems; the rim ones fall off and fade. */
$totem_wall_opts = array(
	'group_tw_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 20, 5, 90, array( 'up' => __( 'Up', 'fw' ), 'down' => __( 'Down', 'fw' ), 'alternate' => __( 'Alternate columns', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to spin', 'fw' ), 'yes', __( 'Let visitors grab and cascade the totems by hand — layers on top of any Motion (grabbing pauses it, releasing resumes). No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep cascading after releasing a drag (Drag to spin).', 'fw' ) ),
	) ),
	'group_tw_shape' => array( 'type' => 'group', 'options' => array(
		'zoom'      => $slider( __( 'Zoom (%)', 'fw' ), 10, 0, 100, 1, __( 'Totem size. Higher zooms in — fewer, larger totems fill the stage (the rim totems fall off the sides and fade).', 'fw' ) ),
		'curvature' => $slider( __( 'Curvature (concave ↔ convex) (%)', 'fw' ), -150, -150, 150, 1, __( 'Signed: negative curls each column away from you (concave); positive bulges it toward you into a rounded totem — push toward +150 for full, pill-shaped pillars. Near 0 is a flat wall.', 'fw' ) ),
		'tilt'      => $slider( __( 'Tilt (°)', 'fw' ), -45, -45, 45, 1, __( 'Tips the whole wall up or down — steep tilts give the diagonal, diamond-lattice look.', 'fw' ) ),
		'gap'       => $slider( __( 'Gap (%)', 'fw' ), 0.5, 0, 20, 0.5, __( 'Space between cards, as a % of the card width — also spaces the totems apart.', 'fw' ) ),
		'edge_fade' => $slider( __( 'Edge Fade (%)', 'fw' ), 0, 0, 100, 1, __( 'Fades the totems toward the left/right edges of the stage.', 'fw' ) ),
	) ),
	'group_tw_card' => array( 'type' => 'group', 'options' => array(
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '16-9', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 0, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the card width.', 'fw' ) ),
	) ),
	'group_tw_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Parallax Totem — the depth-scatter sibling of Totem Wall. The same vertical totems, but each card
 * gets a seeded depth, size and position jitter and drifts at a depth-varied speed (near = faster =
 * the parallax). Scatter / Size variance / Parallax depth all 0 → a clean grid; raised → a floating
 * depth field. No Tilt / Alternate (the scatter carries the interest). */
$parallax_totem_opts = array(
	'group_pt_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 20, 5, 90, array( 'up' => __( 'Up', 'fw' ), 'down' => __( 'Down', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to spin', 'fw' ), 'yes', __( 'Let visitors grab and drift the field by hand — layers on top of any Motion (grabbing pauses it, releasing resumes). No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep drifting after releasing a drag (Drag to spin).', 'fw' ) ),
	) ),
	'group_pt_shape' => array( 'type' => 'group', 'options' => array(
		'zoom'           => $slider( __( 'Zoom (%)', 'fw' ), 10, 0, 100, 1, __( 'Totem size. Higher zooms in — fewer, larger cards fill the stage (the rim ones fall off and fade).', 'fw' ) ),
		'scatter'        => $slider( __( 'Scatter (%)', 'fw' ), 0, 0, 100, 1, __( 'Jitters each card off its grid slot by a fixed (seeded) amount — 0 is a tidy grid, 100 a loose scatter. Stable across reloads.', 'fw' ) ),
		'size_variance'  => $slider( __( 'Size Variance (%)', 'fw' ), 0, 0, 100, 1, __( 'How much the card sizes differ — 0 makes every card the same size, higher mixes big and small.', 'fw' ) ),
		'parallax_depth' => $slider( __( 'Parallax Depth (%)', 'fw' ), 0, 0, 100, 1, __( 'Pushes cards to different depths and drifts the near ones FASTER than the far ones — the parallax. 0 keeps them all on one plane, moving together.', 'fw' ) ),
		'curvature'      => $slider( __( 'Curvature (concave ↔ convex) (%)', 'fw' ), -150, -150, 150, 1, __( 'Signed: negative curls each column away (concave); positive bulges it toward you into a rounded totem. Near 0 is a flat wall.', 'fw' ) ),
		'gap'            => $slider( __( 'Gap (%)', 'fw' ), 0.5, 0, 20, 0.5, __( 'Space between cards, as a % of the card width — also spaces the totems apart.', 'fw' ) ),
		'edge_fade'      => $slider( __( 'Edge Fade (%)', 'fw' ), 0, 0, 100, 1, __( 'Fades the cards toward the left/right edges of the stage.', 'fw' ) ),
	) ),
	'group_pt_card' => array( 'type' => 'group', 'options' => array(
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '16-9', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 0, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the card width.', 'fw' ) ),
	) ),
	'group_pt_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Card Tunnel — an infinite perspective corridor. Four walls of cards recede to a vanishing point and
 * fly forward / backward. No card ratio (cards are sized to their wall) and no tilt — the corridor IS
 * the composition. */
$card_tunnel_opts = array(
	'group_ct_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 20, 5, 90, array( 'forward' => __( 'Forward', 'fw' ), 'backward' => __( 'Backward', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to spin', 'fw' ), 'yes', __( 'Let visitors grab and fly through the tunnel by hand — layers on top of any Motion (grabbing pauses it, releasing resumes). No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep flying after releasing a drag (Drag to spin).', 'fw' ) ),
	) ),
	'group_ct_tunnel' => array( 'type' => 'group', 'options' => array(
		'tunnel_size' => $slider( __( 'Tunnel Size (%)', 'fw' ), 60, 20, 120, 1, __( 'Width of the corridor. Small = a tight tube seen from outside; 100%+ opens the walls out past the frame for an immersive fly-through.', 'fw' ) ),
		'card_length' => $slider( __( 'Card Length (%)', 'fw' ), 30, 5, 120, 1, __( 'How long each card is along the tunnel — higher makes fewer, longer panels streaking past.', 'fw' ) ),
		'gap'         => $slider( __( 'Gap (%)', 'fw' ), 2, 0, 20, 0.5, __( 'Space between cards along the tunnel, as a % of the card length.', 'fw' ) ),
		'depth_fade'  => $slider( __( 'Depth Fade (%)', 'fw' ), 0, 0, 100, 1, __( 'Fades the cards toward the far end of the tunnel into the background — 100% dissolves the depths into the dark.', 'fw' ) ),
		'spiral'      => $slider( __( 'Spiral (°)', 'fw' ), 0, -360, 360, 5, __( 'Rolls the whole corridor as it flies, so the walls spiral past. 0 = a straight tunnel; higher twists it into a helix. Great on Motion → Scroll-scrub.', 'fw' ) ),
		'square'      => $switch( __( 'Square cross-section', 'fw' ), 'no', __( 'Force an equal-sided (square) corridor instead of matching the stage’s shape — a clean, symmetrical image tunnel.', 'fw' ) ),
	) ),
	'group_ct_card' => array( 'type' => 'group', 'options' => array(
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 0, 0, 60, 1 ),
	) ),
	'group_ct_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Spiral Stream — cards threaded along a 3D helix that streams past the camera, rotating around their
 * own vertical axis (fronts / edges / mirrored backs) on a tapering coil. Card Count sets how many
 * cards ride the spiral; Taper narrows it into a vortex or flares it out. */
$spiral_stream_opts = array(
	'group_sp_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 20, 5, 90, array( 'forward' => __( 'Forward', 'fw' ), 'backward' => __( 'Backward', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to spin', 'fw' ), 'yes', __( 'Let visitors grab and stream the spiral by hand — layers on top of any Motion (grabbing pauses it, releasing resumes). No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep streaming after releasing a drag (Drag to spin).', 'fw' ) ),
	) ),
	'group_sp_spiral' => array( 'type' => 'group', 'options' => array(
		'card_count'   => $slider( __( 'Card Count', 'fw' ), 8, 3, 48, 1, __( 'How many cards ride the spiral (images repeat to fill).', 'fw' ) ),
		'spiral_turns' => $slider( __( 'Spiral Turns', 'fw' ), 1, 0.25, 6, 0.25, __( 'How many full revolutions the coil makes from front to back.', 'fw' ) ),
		'spiral_size'  => $slider( __( 'Spiral Size (%)', 'fw' ), 35, 0, 100, 1, __( 'Radius of the coil — how far the cards swing out from the axis.', 'fw' ) ),
		'taper'        => $slider( __( 'Taper (%)', 'fw' ), -90, -100, 100, 1, __( 'Signed: negative narrows the coil toward the back into a vortex/funnel; positive flares it out; 0 is a straight cylinder.', 'fw' ) ),
		'card_gap'     => $slider( __( 'Card Gap (%)', 'fw' ), 0, 0, 100, 1, __( 'Stretches the coil along its axis — more gap spreads the cards further apart in depth.', 'fw' ) ),
		'ring_tilt'    => $slider( __( 'Ring Tilt (°)', 'fw' ), -45, -45, 45, 1, __( 'Tips the whole spiral toward or away from the viewer.', 'fw' ) ),
		'perspective'  => $slider( __( 'Perspective (%)', 'fw' ), 0, 0, 100, 1, __( 'Depth strength — higher exaggerates near/far (bigger front cards, tinier tail).', 'fw' ) ),
		'back_fade'    => $slider( __( 'Back Fade (%)', 'fw' ), 10, 0, 100, 1, __( 'Fades the cards toward the far tail of the stream.', 'fw' ) ),
		'scale_pulse'  => $slider( __( 'Scale Pulse (%)', 'fw' ), 0, 0, 100, 1, __( 'Breathes the card sizes along the coil for a living, organic stream.', 'fw' ) ),
	) ),
	'group_sp_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Card Size (%)', 'fw' ), 12, 4, 60, 1, __( 'Card width as a % of the stage width.', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '3-4', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 0, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the card width.', 'fw' ) ),
	) ),
	'group_sp_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Depth Stack — a deck of cards receding into depth, streaming toward the camera. Spread (Fan) or
 * Scatter fans the deck out; Depth Fade + Depth Blur give a depth-of-field falloff toward the back.
 * Best on Scroll-scrub (Motion) — the visitor's scroll advances the stack. */
$depth_stack_opts = array(
	'group_dz_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 20, 5, 90, array( 'forward' => __( 'Forward', 'fw' ), 'backward' => __( 'Backward', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to spin', 'fw' ), 'yes', __( 'Let visitors grab and advance the stack by hand — layers on top of any Motion (grabbing pauses it, releasing resumes). No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep advancing after releasing a drag (Drag to spin).', 'fw' ) ),
	) ),
	'group_dz_stack' => array( 'type' => 'group', 'options' => array(
		'card_count'   => $slider( __( 'Card Count', 'fw' ), 4, 3, 50, 1, __( 'How many cards ride the stack (images repeat to fill). More = a longer, denser trail behind the hero.', 'fw' ) ),
		'depth_gap'    => $slider( __( 'Depth Gap (%)', 'fw' ), 18, 0, 80, 1, __( 'Spacing between cards in depth — larger pushes the back cards further away (smaller).', 'fw' ) ),
		'spread'       => $slider( __( 'Spread (%)', 'fw' ), 0, 0, 120, 1, __( 'Fans the deck out sideways. 0 stacks the cards directly behind the hero (only the front shows); higher trails them off across the frame.', 'fw' ) ),
		'spread_angle' => $slider( __( 'Spread Angle (°)', 'fw' ), -180, -180, 180, 1, __( 'Which direction the deck fans out (Fan layout).', 'fw' ) ),
		'wobble'       => $slider( __( 'Wobble (%)', 'fw' ), 0, 0, 100, 1, __( 'Random tilt on each card for a loose, hand-tossed feel (seeded, stable across reloads).', 'fw' ) ),
		'depth_fade'   => $slider( __( 'Depth Fade (%)', 'fw' ), 0, 0, 100, 1, __( 'Dims the cards toward the back of the stack.', 'fw' ) ),
		'depth_blur'   => $slider( __( 'Depth Blur (%)', 'fw' ), 0, 0, 100, 0.5, __( 'Softens the cards toward the back — a depth-of-field blur that keeps the hero sharp.', 'fw' ) ),
	) ),
	'group_dz_layout' => array( 'type' => 'group', 'options' => array(
		'layout' => array(
			'type'    => 'select',
			'label'   => __( 'Layout', 'fw' ),
			'value'   => 'fan',
			'choices' => array(
				'fan'     => __( 'Fan (spread along an angle)', 'fw' ),
				'scatter' => __( 'Scatter (random)', 'fw' ),
				'aisle'   => __( 'Aisle (cards to both sides — dolly down the middle)', 'fw' ),
				'field'   => __( 'Field (a scattered cloud you fly through)', 'fw' ),
			),
			'desc'    => __( 'Fan trails the deck out along Spread Angle; Scatter throws the cards to seeded random offsets.', 'fw' ),
		),
	) ),
	'group_dz_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Card Size (%)', 'fw' ), 20, 4, 80, 1, __( 'Hero card width as a % of the stage width.', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '4-5', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 0, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the card width.', 'fw' ) ),
	) ),
	'group_dz_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Tilt Deck (Isometric) — photos lie flat on an isometric ground plane in a Columns × Depth grid and
 * march diagonally across it; the tile crossing the focus point lifts off the plane, rotates flat to
 * face the viewer and brightens (Focus Lift / Focus Brightness), then settles back. Works on Continuous
 * or Scroll-scrub. An original design — the isometric look is a plain projection, not any template. */
$tilt_deck_opts = array(
	'group_iso_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 12, 4, 40, array( 'forward' => __( 'Forward', 'fw' ), 'backward' => __( 'Backward', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to march', 'fw' ), 'yes', __( 'Let visitors grab and march the grid by hand — layers on top of any Motion (grabbing pauses it, releasing resumes). No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep marching after releasing a drag (Drag to march).', 'fw' ) ),
	) ),
	'group_iso_grid' => array( 'type' => 'group', 'options' => array(
		'columns'    => $slider( __( 'Columns', 'fw' ), 4, 2, 7, 1, __( 'How many tiles sit side by side across the isometric plane.', 'fw' ) ),
		'depth_rows' => $slider( __( 'Depth (rows)', 'fw' ), 5, 3, 8, 1, __( 'How many rows march into depth. More rows = a longer run before a tile recycles.', 'fw' ) ),
		'gap'        => $slider( __( 'Tile Gap (%)', 'fw' ), 12, 0, 60, 1, __( 'Spacing between tiles on the plane, as a % of tile size.', 'fw' ) ),
		'iso_tilt'   => $slider( __( 'Iso Tilt (°)', 'fw' ), 58, 20, 75, 1, __( 'How steeply the ground plane tilts away from the camera. Lower reads flatter; higher is a steeper, more top-down isometric.', 'fw' ) ),
	) ),
	'group_iso_focus' => array( 'type' => 'group', 'options' => array(
		'lift'       => $slider( __( 'Focus Lift (%)', 'fw' ), 60, 0, 100, 1, __( 'How far the tile at the focus point lifts off the plane and rotates to face you. 0 keeps every tile flat on the grid; higher gives a strong pop toward the viewer.', 'fw' ) ),
		'focus_glow' => $slider( __( 'Focus Brightness (%)', 'fw' ), 135, 100, 220, 1, __( 'How much brighter the focused tile is than the rest. 100 = no highlight.', 'fw' ) ),
		'depth_fade' => $slider( __( 'Depth Fade (%)', 'fw' ), 30, 0, 100, 1, __( 'Dims the tiles toward the back of the plane for a sense of distance.', 'fw' ) ),
	) ),
	'group_iso_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Tile Size (%)', 'fw' ), 16, 4, 60, 1, __( 'Tile width as a % of the stage width.', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Tile Ratio', 'fw' ), 'type' => 'select', 'value' => '1-1', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 6, 0, 60, 1 ),
		'padding'       => $slider( __( 'Tile Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the tile width.', 'fw' ) ),
	) ),
	'group_iso_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Iso Orbit (Isometric) — tiles ride a ring lying flat on an isometric ground plane and revolve around
 * its centre; the tile at the near point lifts off the plane, rotates flat to face the viewer and
 * brightens, then settles back. Works on Continuous spin, Scroll-scrub or Static. An original design. */
$iso_orbit_opts = array(
	'group_iso2_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 18, 6, 60, array( 'cw' => __( 'Clockwise', 'fw' ), 'ccw' => __( 'Counter-clockwise', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to spin', 'fw' ), 'yes', __( 'Let visitors grab and spin the ring by hand — layers on top of any Motion (grabbing pauses it, releasing resumes). No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep spinning after releasing a drag (Drag to spin).', 'fw' ) ),
	) ),
	'group_iso2_ring' => array( 'type' => 'group', 'options' => array(
		'radius'   => $slider( __( 'Orbit Size (%)', 'fw' ), 30, 8, 60, 1, __( 'Radius of the ring as a % of the stage width — how far the tiles sit from the centre.', 'fw' ) ),
		'iso_tilt' => $slider( __( 'Iso Tilt (°)', 'fw' ), 58, 20, 75, 1, __( 'How steeply the ground plane tilts away from the camera. Lower reads flatter; higher is a steeper, more top-down isometric.', 'fw' ) ),
	) ),
	'group_iso2_focus' => array( 'type' => 'group', 'options' => array(
		'lift'       => $slider( __( 'Focus Lift (%)', 'fw' ), 70, 0, 100, 1, __( 'How far the tile at the near point of the orbit lifts off the plane and rotates to face you. 0 keeps every tile flat on the ring.', 'fw' ) ),
		'focus_glow' => $slider( __( 'Focus Brightness (%)', 'fw' ), 140, 100, 220, 1, __( 'How much brighter the tile at the front is than the rest. 100 = no highlight.', 'fw' ) ),
		'depth_fade' => $slider( __( 'Depth Fade (%)', 'fw' ), 35, 0, 100, 1, __( 'Dims the tiles on the far side of the orbit for a sense of distance.', 'fw' ) ),
	) ),
	'group_iso2_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Tile Size (%)', 'fw' ), 16, 4, 60, 1, __( 'Tile width as a % of the stage width.', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Tile Ratio', 'fw' ), 'type' => 'select', 'value' => '1-1', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 6, 0, 60, 1 ),
		'padding'       => $slider( __( 'Tile Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the tile width.', 'fw' ) ),
	) ),
	'group_iso2_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Iso Stack (Isometric) — photo slabs stacked into a tower rising off the isometric plane, streaming
 * upward on a gentle spiral; the slab at the focus height lifts to face the viewer and brightens, then
 * flattens again as it carries on up. Works on Continuous, Scroll-scrub or Static. An original design. */
$iso_stack_opts = array(
	'group_iso3_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 14, 5, 50, array( 'up' => __( 'Up', 'fw' ), 'down' => __( 'Down', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to stream', 'fw' ), 'yes', __( 'Let visitors grab and stream the tower by hand — layers on top of any Motion (grabbing pauses it, releasing resumes). No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep streaming after releasing a drag (Drag to stream).', 'fw' ) ),
	) ),
	'group_iso3_stack' => array( 'type' => 'group', 'options' => array(
		'gap'      => $slider( __( 'Stack Gap (%)', 'fw' ), 40, 10, 80, 1, __( 'Vertical spacing between slabs — larger makes a taller, airier tower.', 'fw' ) ),
		'spiral'   => $slider( __( 'Spiral (%)', 'fw' ), 40, 0, 100, 1, __( 'How much the tower twists as it rises. 0 is a straight vertical stack; higher spirals the slabs around a gentle helix.', 'fw' ) ),
		'iso_tilt' => $slider( __( 'Iso Tilt (°)', 'fw' ), 58, 20, 75, 1, __( 'How steeply the ground plane tilts away from the camera. Lower reads flatter; higher is a steeper, more top-down isometric.', 'fw' ) ),
	) ),
	'group_iso3_focus' => array( 'type' => 'group', 'options' => array(
		'lift'       => $slider( __( 'Focus Lift (%)', 'fw' ), 70, 0, 100, 1, __( 'How far the slab at the focus height lifts to face you. 0 keeps every slab flat in the tower.', 'fw' ) ),
		'focus_glow' => $slider( __( 'Focus Brightness (%)', 'fw' ), 140, 100, 220, 1, __( 'How much brighter the focused slab is than the rest. 100 = no highlight.', 'fw' ) ),
		'depth_fade' => $slider( __( 'Depth Fade (%)', 'fw' ), 40, 0, 100, 1, __( 'Dims the slabs toward the bottom of the tower for a sense of depth.', 'fw' ) ),
	) ),
	'group_iso3_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Slab Size (%)', 'fw' ), 17, 4, 60, 1, __( 'Slab width as a % of the stage width.', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Slab Ratio', 'fw' ), 'type' => 'select', 'value' => '1-1', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 6, 0, 60, 1 ),
		'padding'       => $slider( __( 'Slab Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the slab width.', 'fw' ) ),
	) ),
	'group_iso3_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Iso Cascade (Isometric) — photos descend a diagonal staircase across the isometric plane: high and
 * far at the back, stepping down and toward the viewer; the tile reaching the front lifts flat to face
 * you and brightens, then recycles to the back. Continuous, Scroll-scrub or Static. An original design. */
$iso_cascade_opts = array(
	'group_iso4_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 12, 5, 45, array( 'down' => __( 'Down', 'fw' ), 'up' => __( 'Up', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to cascade', 'fw' ), 'yes', __( 'Let visitors grab and run the cascade by hand — layers on top of any Motion (grabbing pauses it, releasing resumes). No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep cascading after releasing a drag (Drag to cascade).', 'fw' ) ),
	) ),
	'group_iso4_cascade' => array( 'type' => 'group', 'options' => array(
		'spread'   => $slider( __( 'Spread (%)', 'fw' ), 60, 20, 100, 1, __( 'How far the staircase reaches diagonally across the floor — wider spreads the steps apart.', 'fw' ) ),
		'rise'     => $slider( __( 'Rise (%)', 'fw' ), 50, 10, 100, 1, __( 'How tall the staircase is — how high the back of the cascade sits above the front.', 'fw' ) ),
		'iso_tilt' => $slider( __( 'Iso Tilt (°)', 'fw' ), 58, 20, 75, 1, __( 'How steeply the ground plane tilts away from the camera. Lower reads flatter; higher is a steeper, more top-down isometric.', 'fw' ) ),
	) ),
	'group_iso4_focus' => array( 'type' => 'group', 'options' => array(
		'lift'       => $slider( __( 'Focus Lift (%)', 'fw' ), 70, 0, 100, 1, __( 'How far the tile at the front of the cascade lifts to face you. 0 keeps every tile flat on the steps.', 'fw' ) ),
		'focus_glow' => $slider( __( 'Focus Brightness (%)', 'fw' ), 140, 100, 220, 1, __( 'How much brighter the tile at the front is than the rest. 100 = no highlight.', 'fw' ) ),
		'depth_fade' => $slider( __( 'Depth Fade (%)', 'fw' ), 45, 0, 100, 1, __( 'Dims the tiles toward the back (top) of the cascade for a sense of distance.', 'fw' ) ),
	) ),
	'group_iso4_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Tile Size (%)', 'fw' ), 17, 4, 60, 1, __( 'Tile width as a % of the stage width.', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Tile Ratio', 'fw' ), 'type' => 'select', 'value' => '1-1', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 6, 0, 60, 1 ),
		'padding'       => $slider( __( 'Tile Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the tile width.', 'fw' ) ),
	) ),
	'group_iso4_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Iso Fan (Isometric) — tiles spread along a flat arc (a fan) on the isometric plane and sweep across
 * it; the tile passing the centre-front lifts flat to face the viewer and brightens, then settles back
 * as it sweeps on. Continuous sweep, Scroll-scrub or Static. An original design. */
$iso_fan_opts = array(
	'group_iso5_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 16, 6, 50, array( 'left' => __( 'Left', 'fw' ), 'right' => __( 'Right', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to sweep', 'fw' ), 'yes', __( 'Let visitors grab and sweep the fan by hand — layers on top of any Motion (grabbing pauses it, releasing resumes). No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep sweeping after releasing a drag (Drag to sweep).', 'fw' ) ),
	) ),
	'group_iso5_fan' => array( 'type' => 'group', 'options' => array(
		'reach'    => $slider( __( 'Fan Reach (%)', 'fw' ), 34, 10, 60, 1, __( 'Radius of the fan as a % of the stage width — how far the tiles sweep out from the centre.', 'fw' ) ),
		'arc'      => $slider( __( 'Fan Spread (°)', 'fw' ), 130, 40, 175, 1, __( 'How wide the fan opens — the arc the tiles spread across. Narrow is a tight sweep; wide fans the tiles right across the frame.', 'fw' ) ),
		'iso_tilt' => $slider( __( 'Iso Tilt (°)', 'fw' ), 58, 20, 75, 1, __( 'How steeply the ground plane tilts away from the camera. Lower reads flatter; higher is a steeper, more top-down isometric.', 'fw' ) ),
	) ),
	'group_iso5_focus' => array( 'type' => 'group', 'options' => array(
		'lift'       => $slider( __( 'Focus Lift (%)', 'fw' ), 70, 0, 100, 1, __( 'How far the tile at the centre-front of the fan lifts to face you. 0 keeps every tile flat on the arc.', 'fw' ) ),
		'focus_glow' => $slider( __( 'Focus Brightness (%)', 'fw' ), 140, 100, 220, 1, __( 'How much brighter the tile at the centre is than the rest. 100 = no highlight.', 'fw' ) ),
		'depth_fade' => $slider( __( 'Depth Fade (%)', 'fw' ), 40, 0, 100, 1, __( 'Dims the tiles toward the edges of the fan for a sense of distance.', 'fw' ) ),
	) ),
	'group_iso5_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Tile Size (%)', 'fw' ), 16, 4, 60, 1, __( 'Tile width as a % of the stage width.', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Tile Ratio', 'fw' ), 'type' => 'select', 'value' => '1-1', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 6, 0, 60, 1 ),
		'padding'       => $slider( __( 'Tile Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the tile width.', 'fw' ) ),
	) ),
	'group_iso5_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Orbit Bloom (Orbit) — a ring of billboarded cards that orbits a tilted disc and BREATHES: it blooms
 * from a tight central cluster out to a wide ring and back, cards fading + scaling up as they open.
 * Continuous, Scroll-scrub or Static. An original design. */
$orbit_bloom_opts = array(
	'group_orb_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 22, 6, 60, array( 'cw' => __( 'Clockwise', 'fw' ), 'ccw' => __( 'Counter-clockwise', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to spin', 'fw' ), 'yes', __( 'Let visitors grab and spin the ring by hand — layers on top of any Motion. No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep spinning after releasing a drag (Drag to spin).', 'fw' ) ),
	) ),
	'group_orb_ring' => array( 'type' => 'group', 'options' => array(
		'radius'  => $slider( __( 'Ring Size (%)', 'fw' ), 32, 10, 60, 1, __( 'Radius of the fully-bloomed ring as a % of the stage width.', 'fw' ) ),
		'tilt'    => $slider( __( 'Ring Tilt (°)', 'fw' ), 58, 0, 80, 1, __( 'How far the orbit disc tilts back from the camera. 0 is a flat face-on ring; higher leans it into a shallow ellipse.', 'fw' ) ),
		'bloom'   => $slider( __( 'Bloom (%)', 'fw' ), 70, 0, 100, 1, __( 'How much the ring breathes — how far it contracts to a tight central cluster between blooms. 0 = a steady ring with no breathing.', 'fw' ) ),
	) ),
	'group_orb_focus' => array( 'type' => 'group', 'options' => array(
		'focus_glow' => $slider( __( 'Focus Brightness (%)', 'fw' ), 130, 100, 220, 1, __( 'How much brighter the cards nearest the camera are than the far arc. 100 = even.', 'fw' ) ),
		'depth_fade' => $slider( __( 'Depth Fade (%)', 'fw' ), 45, 0, 100, 1, __( 'Dims the cards on the far side of the ring for a sense of depth.', 'fw' ) ),
	) ),
	'group_orb_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Card Size (%)', 'fw' ), 16, 4, 60, 1, __( 'Card width as a % of the stage width.', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '1-1', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 6, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the card width.', 'fw' ) ),
	) ),
	'group_orb_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Focus Orbit (Orbit) — a ring of billboarded cards orbiting a tilted disc; the card reaching the front
 * pulls into the centre, lifts toward you and enlarges into a hero, then returns to the ring as the next
 * takes its place. Continuous, Scroll-scrub or Static. An original design. */
$focus_orbit_opts = array(
	'group_orb2_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 20, 6, 60, array( 'cw' => __( 'Clockwise', 'fw' ), 'ccw' => __( 'Counter-clockwise', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to spin', 'fw' ), 'yes', __( 'Let visitors grab and spin the ring by hand — layers on top of any Motion. No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep spinning after releasing a drag (Drag to spin).', 'fw' ) ),
	) ),
	'group_orb2_ring' => array( 'type' => 'group', 'options' => array(
		'radius' => $slider( __( 'Ring Size (%)', 'fw' ), 36, 10, 55, 1, __( 'Radius of the orbit as a % of the stage width — how far the satellites sit from the centre.', 'fw' ) ),
		'tilt'   => $slider( __( 'Ring Tilt (°)', 'fw' ), 30, 0, 80, 1, __( 'How far the orbit disc tilts back from the camera. 0 is a flat face-on ring; higher leans it into a shallow ellipse.', 'fw' ) ),
	) ),
	'group_orb2_focus' => array( 'type' => 'group', 'options' => array(
		'hero'       => $slider( __( 'Hero Pull (%)', 'fw' ), 70, 0, 100, 1, __( 'How strongly the card at the front pulls into the centre and enlarges into a hero. 0 keeps every card on the ring (an even revolve).', 'fw' ) ),
		'focus_glow' => $slider( __( 'Focus Brightness (%)', 'fw' ), 150, 100, 240, 1, __( 'How much brighter the hero is than the ring. 100 = no highlight.', 'fw' ) ),
		'depth_fade' => $slider( __( 'Depth Fade (%)', 'fw' ), 45, 0, 100, 1, __( 'Dims the cards on the far side of the ring for a sense of depth.', 'fw' ) ),
	) ),
	'group_orb2_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Card Size (%)', 'fw' ), 15, 4, 60, 1, __( 'Base card width (on the ring) as a % of the stage width — the hero scales up from this.', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '1-1', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 6, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the card width.', 'fw' ) ),
	) ),
	'group_orb2_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Dual Orbit (Orbit) — two concentric rings of billboarded cards on one tilted disc, the inner ring
 * turning the other way (or the same way, faster) — a geared, layered orbit. An original design. */
$dual_orbit_opts = array(
	'group_dor_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 20, 6, 60, array( 'cw' => __( 'Clockwise', 'fw' ), 'ccw' => __( 'Counter-clockwise', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to spin', 'fw' ), 'yes', __( 'Let visitors grab and spin the rings by hand — layers on top of any Motion. No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep spinning after releasing a drag (Drag to spin).', 'fw' ) ),
	) ),
	'group_dor_ring' => array( 'type' => 'group', 'options' => array(
		'radius'      => $slider( __( 'Outer Ring Size (%)', 'fw' ), 33, 15, 55, 1, __( 'Radius of the outer ring as a % of the stage width.', 'fw' ) ),
		'inner'       => $slider( __( 'Inner Ring Size (%)', 'fw' ), 50, 25, 85, 1, __( 'Radius of the inner ring as a % of the outer ring.', 'fw' ) ),
		'tilt'        => $slider( __( 'Ring Tilt (°)', 'fw' ), 62, 0, 80, 1, __( 'How far the orbit disc tilts back from the camera. 0 is face-on; higher leans both rings into shallow ellipses.', 'fw' ) ),
		'counter'     => $switch( __( 'Counter-rotate', 'fw' ), 'yes', __( 'Turn the inner ring the opposite way to the outer ring. Off = both rings turn the same way at their own speeds.', 'fw' ) ),
		'inner_speed' => $slider( __( 'Inner Ring Speed (%)', 'fw' ), 150, 50, 300, 5, __( 'The inner ring\'s speed relative to the outer ring. 100 = the same speed.', 'fw' ) ),
		'show_paths'  => $switch( __( 'Show Orbit Paths', 'fw' ), 'yes', __( 'A thin line along each ring, so the two orbits read clearly.', 'fw' ) ),
	) ),
	'group_dor_focus' => array( 'type' => 'group', 'options' => array(
		'focus_glow' => $slider( __( 'Focus Brightness (%)', 'fw' ), 130, 100, 220, 1, __( 'How much brighter the cards nearest the camera are than the far arc. 100 = even.', 'fw' ) ),
		'depth_fade' => $slider( __( 'Depth Fade (%)', 'fw' ), 45, 0, 100, 1, __( 'Dims the cards on the far side of each ring for a sense of depth.', 'fw' ) ),
	) ),
	'group_dor_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Card Size (%)', 'fw' ), 11, 4, 60, 1, __( 'Outer-ring card width as a % of the stage width (inner-ring cards are a little smaller).', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '1-1', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 6, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the card width.', 'fw' ) ),
	) ),
	'group_dor_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Comet Orbit (Orbit) — cards travel an elliptical orbit around a glowing focus with orbital (Kepler)
 * timing: they whip fast and flare large as they swing past the focus, then drift slow and small along
 * the far end. An original design. */
$comet_orbit_opts = array(
	'group_com_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 24, 6, 60, array( 'cw' => __( 'Clockwise', 'fw' ), 'ccw' => __( 'Counter-clockwise', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to spin', 'fw' ), 'yes', __( 'Let visitors grab and push the orbit by hand — layers on top of any Motion. No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep moving after releasing a drag (Drag to spin).', 'fw' ) ),
	) ),
	'group_com_path' => array( 'type' => 'group', 'options' => array(
		'radius'       => $slider( __( 'Orbit Size (%)', 'fw' ), 33, 15, 55, 1, __( 'Half the long axis of the ellipse, as a % of the stage width.', 'fw' ) ),
		'eccentricity' => $slider( __( 'Eccentricity (%)', 'fw' ), 62, 0, 85, 1, __( 'How stretched the orbit is. 0 = a circle at an even pace; higher = a long ellipse where cards bunch up at the far end and race past the focus.', 'fw' ) ),
		'tilt'         => $slider( __( 'Orbit Tilt (°)', 'fw' ), 45, 0, 80, 1, __( 'How far the orbit plane tilts back from the camera.', 'fw' ) ),
		'show_focus'   => $switch( __( 'Show Focus Glow', 'fw' ), 'yes', __( 'A soft glowing "sun" at the focus the cards swing around.', 'fw' ) ),
		'show_paths'   => $switch( __( 'Show Orbit Path', 'fw' ), 'yes', __( 'A thin line along the ellipse the cards travel.', 'fw' ) ),
	) ),
	'group_com_focus' => array( 'type' => 'group', 'options' => array(
		'flare'      => $slider( __( 'Flare (%)', 'fw' ), 70, 0, 100, 1, __( 'How much the cards grow and brighten as they swing past the focus. 0 = no flare.', 'fw' ) ),
		'depth_fade' => $slider( __( 'Depth Fade (%)', 'fw' ), 40, 0, 100, 1, __( 'Dims the cards on the far side of the orbit for a sense of depth.', 'fw' ) ),
	) ),
	'group_com_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Card Size (%)', 'fw' ), 12, 4, 60, 1, __( 'Base card width as a % of the stage width — the flare scales up from this.', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '1-1', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 6, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the card width.', 'fw' ) ),
	) ),
	'group_com_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Orbit Swarm (Orbit) — every card keeps its own orbit (its own radius, speed and height), so inner
 * cards overtake outer ones and the cloud drifts up and down; now and then the swarm gathers in toward
 * the centre and scatters back out. An original design. */
$orbit_swarm_opts = array(
	'group_swm_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 26, 6, 60, array( 'cw' => __( 'Clockwise', 'fw' ), 'ccw' => __( 'Counter-clockwise', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to spin', 'fw' ), 'yes', __( 'Let visitors grab and spin the swarm by hand — layers on top of any Motion. No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep spinning after releasing a drag (Drag to spin).', 'fw' ) ),
	) ),
	'group_swm_swarm' => array( 'type' => 'group', 'options' => array(
		'radius'   => $slider( __( 'Swarm Size (%)', 'fw' ), 31, 15, 55, 1, __( 'Radius of the outermost orbit as a % of the stage width.', 'fw' ) ),
		'spread'   => $slider( __( 'Spread (%)', 'fw' ), 55, 0, 90, 1, __( 'How far apart the orbits are. 0 = every card on one ring; higher = orbits from near the centre out to the edge.', 'fw' ) ),
		'swirl'    => $slider( __( 'Swirl (%)', 'fw' ), 60, 0, 100, 1, __( 'How much faster the inner orbits run than the outer ones, so cards overtake each other. 0 = all at one pace.', 'fw' ) ),
		'height'   => $slider( __( 'Height Drift (%)', 'fw' ), 30, 0, 100, 1, __( 'How far the cards rise and sink above and below the orbit plane.', 'fw' ) ),
		'cohesion' => $slider( __( 'Gather (%)', 'fw' ), 45, 0, 100, 1, __( 'How strongly the swarm periodically gathers toward the centre before scattering back out. 0 = never gathers.', 'fw' ) ),
		'tilt'     => $slider( __( 'Orbit Tilt (°)', 'fw' ), 55, 0, 80, 1, __( 'How far the orbit plane tilts back from the camera.', 'fw' ) ),
	) ),
	'group_swm_focus' => array( 'type' => 'group', 'options' => array(
		'focus_glow' => $slider( __( 'Focus Brightness (%)', 'fw' ), 130, 100, 220, 1, __( 'How much brighter the cards nearest the camera are. 100 = even.', 'fw' ) ),
		'depth_fade' => $slider( __( 'Depth Fade (%)', 'fw' ), 45, 0, 100, 1, __( 'Dims the cards on the far side of the swarm for a sense of depth.', 'fw' ) ),
	) ),
	'group_swm_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Card Size (%)', 'fw' ), 9, 4, 60, 1, __( 'Card width as a % of the stage width.', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '1-1', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 6, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the card width.', 'fw' ) ),
	) ),
	'group_swm_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Ring Tilt (Orbit) — a steeply tilted flat ring, like a planet's rings: the cards lie IN the ring
 * plane (not turned to face you) and stand up by Card Lift, around an optional planet; the whole ring
 * slowly wobbles (precession). An original design. */
$ring_tilt_opts = array(
	'group_rt_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 30, 6, 60, array( 'cw' => __( 'Clockwise', 'fw' ), 'ccw' => __( 'Counter-clockwise', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to spin', 'fw' ), 'yes', __( 'Let visitors grab and spin the ring by hand — layers on top of any Motion. No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep spinning after releasing a drag (Drag to spin).', 'fw' ) ),
	) ),
	'group_rt_ring' => array( 'type' => 'group', 'options' => array(
		'radius' => $slider( __( 'Ring Size (%)', 'fw' ), 40, 15, 55, 1, __( 'Radius of the ring as a % of the stage width.', 'fw' ) ),
		'tilt'   => $slider( __( 'Ring Tilt (°)', 'fw' ), 70, 30, 85, 1, __( 'How far the ring leans back. Higher = closer to edge-on.', 'fw' ) ),
		'lift'   => $slider( __( 'Card Lift (°)', 'fw' ), 20, 0, 90, 1, __( 'How far each card stands up out of the ring plane. 0 = lying flat in the ring; 90 = standing upright, facing outward.', 'fw' ) ),
		'wobble' => $slider( __( 'Wobble (°)', 'fw' ), 8, 0, 25, 1, __( 'How far the ring slowly rocks and precesses as it turns. 0 = a steady ring.', 'fw' ) ),
	) ),
	'group_rt_core' => array( 'type' => 'group', 'options' => array(
		'show_core'  => $switch( __( 'Show Planet', 'fw' ), 'yes', __( 'A shaded sphere at the centre of the ring.', 'fw' ) ),
		'core_size'  => $slider( __( 'Planet Size (%)', 'fw' ), 34, 10, 70, 1, __( 'Planet diameter as a % of the ring diameter.', 'fw' ) ),
		'core_color' => array( 'type' => 'color-picker', 'label' => __( 'Planet Colour', 'fw' ), 'value' => '#2f74e6' ),
	) ),
	'group_rt_focus' => array( 'type' => 'group', 'options' => array(
		'focus_glow' => $slider( __( 'Focus Brightness (%)', 'fw' ), 125, 100, 220, 1, __( 'How much brighter the front of the ring is than the back. 100 = even.', 'fw' ) ),
		'depth_fade' => $slider( __( 'Depth Fade (%)', 'fw' ), 50, 0, 100, 1, __( 'Dims the cards on the far side of the ring for a sense of depth.', 'fw' ) ),
	) ),
	'group_rt_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Card Size (%)', 'fw' ), 12, 4, 60, 1, __( 'Card width as a % of the stage width.', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '4-3', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 6, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the card width.', 'fw' ) ),
	) ),
	'group_rt_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Gyro Orbit (Orbit) — an atom / gyroscope: 2–4 rings of billboarded cards, each on its own leaning
 * plane, fanned round the vertical axis, neighbouring rings running opposite ways around a glowing core;
 * the whole gyro slowly turns. An original design. */
$gyro_orbit_opts = array(
	'group_gyr_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 22, 6, 60, array( 'cw' => __( 'Clockwise', 'fw' ), 'ccw' => __( 'Counter-clockwise', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to spin', 'fw' ), 'yes', __( 'Let visitors grab and spin the gyro by hand — layers on top of any Motion. No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep spinning after releasing a drag (Drag to spin).', 'fw' ) ),
	) ),
	'group_gyr_rings' => array( 'type' => 'group', 'options' => array(
		'radius'     => $slider( __( 'Gyro Size (%)', 'fw' ), 24, 15, 50, 1, __( 'Radius of the rings as a % of the stage width.', 'fw' ) ),
		'rings'      => $slider( __( 'Rings', 'fw' ), 3, 2, 4, 1, __( 'How many orbit rings. The photos are dealt round them in turn.', 'fw' ) ),
		'lean'       => $slider( __( 'Ring Lean (°)', 'fw' ), 62, 0, 85, 1, __( 'How far each ring leans from flat. 0 = all rings lie flat on top of each other; higher = the crossed rings of an atom.', 'fw' ) ),
		'spin'       => $slider( __( 'Gyro Spin (%)', 'fw' ), 35, 0, 100, 1, __( 'How fast the whole gyro turns round its vertical axis while the cards travel their rings. 0 = the rings stay put.', 'fw' ) ),
		'show_paths' => $switch( __( 'Show Ring Paths', 'fw' ), 'yes', __( 'A thin line along each ring.', 'fw' ) ),
		'show_core'  => $switch( __( 'Show Core Glow', 'fw' ), 'yes', __( 'A soft glow at the centre of the rings.', 'fw' ) ),
	) ),
	'group_gyr_focus' => array( 'type' => 'group', 'options' => array(
		'focus_glow' => $slider( __( 'Focus Brightness (%)', 'fw' ), 130, 100, 220, 1, __( 'How much brighter the cards nearest the camera are. 100 = even.', 'fw' ) ),
		'depth_fade' => $slider( __( 'Depth Fade (%)', 'fw' ), 50, 0, 100, 1, __( 'Dims the cards on the far side of the gyro for a sense of depth.', 'fw' ) ),
	) ),
	'group_gyr_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Card Size (%)', 'fw' ), 9, 4, 60, 1, __( 'Card width as a % of the stage width.', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '1-1', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 6, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the card width.', 'fw' ) ),
	) ),
	'group_gyr_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Infinity Orbit (Orbit) — cards loop a figure-eight on a tilted plane; where the loops cross, one pass
 * rises over the other. An original design. */
$infinity_orbit_opts = array(
	'group_inf_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 26, 6, 60, array( 'cw' => __( 'Forward', 'fw' ), 'ccw' => __( 'Reverse', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to spin', 'fw' ), 'yes', __( 'Let visitors grab and push the loop by hand — layers on top of any Motion. No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep moving after releasing a drag (Drag to spin).', 'fw' ) ),
	) ),
	'group_inf_path' => array( 'type' => 'group', 'options' => array(
		'radius'     => $slider( __( 'Loop Width (%)', 'fw' ), 38, 15, 50, 1, __( 'Half the width of the figure-eight as a % of the stage width.', 'fw' ) ),
		'loop'       => $slider( __( 'Loop Height (%)', 'fw' ), 100, 40, 160, 1, __( 'How tall each loop of the eight is. Lower = a slim, flat ∞; higher = round lobes.', 'fw' ) ),
		'cross'      => $slider( __( 'Cross Height (%)', 'fw' ), 40, 0, 100, 1, __( 'How far one pass rises over the other where the loops cross. 0 = both passes meet flat in the middle.', 'fw' ) ),
		'tilt'       => $slider( __( 'Plane Tilt (°)', 'fw' ), 50, 0, 80, 1, __( 'How far the plane of the eight leans back from the camera.', 'fw' ) ),
		'show_paths' => $switch( __( 'Show Path', 'fw' ), 'yes', __( 'A thin line along the figure-eight.', 'fw' ) ),
	) ),
	'group_inf_focus' => array( 'type' => 'group', 'options' => array(
		'focus_glow' => $slider( __( 'Focus Brightness (%)', 'fw' ), 130, 100, 220, 1, __( 'How much brighter the cards nearest the camera are. 100 = even.', 'fw' ) ),
		'depth_fade' => $slider( __( 'Depth Fade (%)', 'fw' ), 45, 0, 100, 1, __( 'Dims the cards on the far side of the loop for a sense of depth.', 'fw' ) ),
	) ),
	'group_inf_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Card Size (%)', 'fw' ), 11, 4, 60, 1, __( 'Card width as a % of the stage width.', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '1-1', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 6, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the card width.', 'fw' ) ),
	) ),
	'group_inf_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Vortex (Orbit) — a top-down whirlpool: cards spiral in along a few arms, sinking, shrinking and dimming
 * toward a glowing eye, then re-enter at the rim. Flows inward or outward. An original design. */
$vortex_opts = array(
	'group_vtx_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 30, 6, 60, array( 'in' => __( 'Inward', 'fw' ), 'out' => __( 'Outward', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to spin', 'fw' ), 'yes', __( 'Let visitors grab and push the whirlpool by hand — layers on top of any Motion. No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep moving after releasing a drag (Drag to spin).', 'fw' ) ),
	) ),
	'group_vtx_swirl' => array( 'type' => 'group', 'options' => array(
		'radius'    => $slider( __( 'Vortex Size (%)', 'fw' ), 42, 15, 55, 1, __( 'Radius of the rim as a % of the stage width.', 'fw' ) ),
		'arms'      => $slider( __( 'Arms', 'fw' ), 3, 1, 6, 1, __( 'How many spiral arms the photos flow along.', 'fw' ) ),
		'twist'     => $slider( __( 'Twist (turns)', 'fw' ), 0.8, -3, 3, 0.1, __( 'How many turns an arm makes from the rim to the eye. Negative = the whirlpool turns the other way.', 'fw' ) ),
		'depth'     => $slider( __( 'Depth (%)', 'fw' ), 60, 0, 100, 1, __( 'How far the cards sink as they reach the eye. 0 = a flat swirl.', 'fw' ) ),
		'tilt'      => $slider( __( 'Tilt (°)', 'fw' ), 52, 0, 80, 1, __( 'How far the whirlpool leans back. 0 = looking straight down into it.', 'fw' ) ),
		'show_core' => $switch( __( 'Show Eye Glow', 'fw' ), 'yes', __( 'A cool glow at the bottom of the whirlpool.', 'fw' ) ),
	) ),
	'group_vtx_focus' => array( 'type' => 'group', 'options' => array(
		'depth_fade' => $slider( __( 'Fade to Eye (%)', 'fw' ), 55, 0, 100, 1, __( 'How much the cards darken as they sink toward the eye.', 'fw' ) ),
	) ),
	'group_vtx_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Card Size (%)', 'fw' ), 8, 4, 60, 1, __( 'Card width at the rim as a % of the stage width — cards shrink toward the eye.', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '1-1', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 6, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the card width.', 'fw' ) ),
	) ),
	'group_vtx_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Pendulum Orbit (Orbit) — cards along an arc of a tilted orbit that swings back and forth like a
 * pendulum, easing at each end, instead of revolving. An original design. */
$pendulum_orbit_opts = array(
	'group_pen_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 8, 3, 40, array( 'cw' => __( 'Right first', 'fw' ), 'ccw' => __( 'Left first', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to swing', 'fw' ), 'yes', __( 'Let visitors grab and push the swing by hand — layers on top of any Motion. No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep moving after releasing a drag.', 'fw' ) ),
	) ),
	'group_pen_arc' => array( 'type' => 'group', 'options' => array(
		'radius' => $slider( __( 'Arc Size (%)', 'fw' ), 36, 15, 55, 1, __( 'Radius of the orbit the arc sits on, as a % of the stage width.', 'fw' ) ),
		'arc'    => $slider( __( 'Arc Span (°)', 'fw' ), 130, 40, 300, 1, __( 'How much of the orbit the photos spread across.', 'fw' ) ),
		'swing'  => $slider( __( 'Swing (°)', 'fw' ), 45, 0, 180, 1, __( 'How far the arc swings to each side. 0 = it stays still (use Drag to swing).', 'fw' ) ),
		'tilt'   => $slider( __( 'Orbit Tilt (°)', 'fw' ), 58, 0, 80, 1, __( 'How far the orbit leans back from the camera.', 'fw' ) ),
	) ),
	'group_pen_focus' => array( 'type' => 'group', 'options' => array(
		'focus_glow' => $slider( __( 'Focus Brightness (%)', 'fw' ), 140, 100, 220, 1, __( 'How much brighter (and larger) the card passing the front is. 100 = even.', 'fw' ) ),
		'depth_fade' => $slider( __( 'Depth Fade (%)', 'fw' ), 45, 0, 100, 1, __( 'Dims the cards as they swing away to the sides.', 'fw' ) ),
	) ),
	'group_pen_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Card Size (%)', 'fw' ), 8, 4, 60, 1, __( 'Card width as a % of the stage width.', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '1-1', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 6, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the card width.', 'fw' ) ),
	) ),
	'group_pen_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Moon Orbit (Orbit) — large "planet" cards orbit the main ring; the rest circle them as smaller,
 * faster "moons". An original design. */
$moon_orbit_opts = array(
	'group_mon_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 30, 6, 60, array( 'cw' => __( 'Clockwise', 'fw' ), 'ccw' => __( 'Counter-clockwise', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to spin', 'fw' ), 'yes', __( 'Let visitors grab and spin the system by hand — layers on top of any Motion. No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep spinning after releasing a drag (Drag to spin).', 'fw' ) ),
	) ),
	'group_mon_system' => array( 'type' => 'group', 'options' => array(
		'radius'      => $slider( __( 'Orbit Size (%)', 'fw' ), 33, 15, 50, 1, __( 'Radius of the planets\' orbit as a % of the stage width.', 'fw' ) ),
		'planets'     => $slider( __( 'Planets', 'fw' ), 4, 2, 6, 1, __( 'How many large planet cards; every other photo becomes one of their moons.', 'fw' ) ),
		'moon_radius' => $slider( __( 'Moon Orbit (%)', 'fw' ), 8, 3, 20, 1, __( 'How far the moons circle from their planet, as a % of the stage width.', 'fw' ) ),
		'moon_speed'  => $slider( __( 'Moon Speed (%)', 'fw' ), 300, 100, 600, 10, __( 'How fast the moons circle their planet, relative to the planets\' orbit.', 'fw' ) ),
		'moon_size'   => $slider( __( 'Moon Size (%)', 'fw' ), 50, 25, 90, 1, __( 'Moon card size as a % of a planet card.', 'fw' ) ),
		'tilt'        => $slider( __( 'Orbit Tilt (°)', 'fw' ), 55, 0, 80, 1, __( 'How far the orbit plane leans back from the camera.', 'fw' ) ),
	) ),
	'group_mon_focus' => array( 'type' => 'group', 'options' => array(
		'focus_glow' => $slider( __( 'Focus Brightness (%)', 'fw' ), 130, 100, 220, 1, __( 'How much brighter the cards nearest the camera are. 100 = even.', 'fw' ) ),
		'depth_fade' => $slider( __( 'Depth Fade (%)', 'fw' ), 45, 0, 100, 1, __( 'Dims the cards on the far side for a sense of depth.', 'fw' ) ),
	) ),
	'group_mon_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Planet Size (%)', 'fw' ), 11, 4, 60, 1, __( 'Planet card width as a % of the stage width.', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '1-1', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 6, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the card width.', 'fw' ) ),
	) ),
	'group_mon_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Tiered Orbit (Orbit) — stacked horizontal rings at different heights, neighbouring tiers turning
 * opposite ways, tapering into a tree or a tornado. Seen from outside. An original design. */
$tiered_orbit_opts = array(
	'group_tie_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 26, 6, 60, array( 'cw' => __( 'Clockwise', 'fw' ), 'ccw' => __( 'Counter-clockwise', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to spin', 'fw' ), 'yes', __( 'Let visitors grab and spin the tiers by hand — layers on top of any Motion. No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep spinning after releasing a drag (Drag to spin).', 'fw' ) ),
	) ),
	'group_tie_tiers' => array( 'type' => 'group', 'options' => array(
		'radius'     => $slider( __( 'Ring Size (%)', 'fw' ), 28, 15, 50, 1, __( 'Radius of the middle tier as a % of the stage width.', 'fw' ) ),
		'tiers'      => $slider( __( 'Tiers', 'fw' ), 3, 2, 5, 1, __( 'How many stacked rings. The photos are shared out between them.', 'fw' ) ),
		'gap'        => $slider( __( 'Tier Spacing (%)', 'fw' ), 16, 5, 40, 1, __( 'Height between tiers as a % of the stage height.', 'fw' ) ),
		'taper'      => $slider( __( 'Taper (%)', 'fw' ), 35, -70, 70, 1, __( 'Positive = narrow at the top, like a tree; negative = wide at the top, like a tornado; 0 = a straight column.', 'fw' ) ),
		'elevation'  => $slider( __( 'View Height (°)', 'fw' ), 22, 0, 45, 1, __( 'How far above the tiers you look from. 0 = level, the rings seen edge-on.', 'fw' ) ),
		'alternate'  => $switch( __( 'Alternate Directions', 'fw' ), 'yes', __( 'Turn neighbouring tiers opposite ways. Off = every tier turns the same way.', 'fw' ) ),
		'show_paths' => $switch( __( 'Show Ring Paths', 'fw' ), 'yes', __( 'A thin line along each tier.', 'fw' ) ),
	) ),
	'group_tie_focus' => array( 'type' => 'group', 'options' => array(
		'focus_glow' => $slider( __( 'Focus Brightness (%)', 'fw' ), 130, 100, 220, 1, __( 'How much brighter the cards nearest the camera are. 100 = even.', 'fw' ) ),
		'depth_fade' => $slider( __( 'Depth Fade (%)', 'fw' ), 50, 0, 100, 1, __( 'Dims the cards on the far side of each tier.', 'fw' ) ),
	) ),
	'group_tie_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Card Size (%)', 'fw' ), 8, 4, 60, 1, __( 'Card width as a % of the stage width.', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '1-1', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 6, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the card width.', 'fw' ) ),
	) ),
	'group_tie_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* ---- Grid family ---- */

/* Wave Grid (Grid) — a grid of photos lying on a tilted floor with a ripple running across it, each
 * photo rising and tipping with the wave. An original design. */
$wave_grid_opts = array(
	'group_wvg_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 5, 2, 20, array( 'out' => __( 'Outward', 'fw' ), 'in' => __( 'Inward', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag the wave', 'fw' ), 'yes', __( 'Let visitors grab and push the wave by hand — layers on top of any Motion. No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep the wave moving after releasing a drag.', 'fw' ) ),
	) ),
	'group_wvg_grid' => array( 'type' => 'group', 'options' => array(
		'columns'    => $slider( __( 'Columns', 'fw' ), 6, 2, 12, 1 ),
		'rows'       => $slider( __( 'Rows', 'fw' ), 4, 1, 8, 1, __( 'Photos repeat when the grid has more cells than the gallery has photos.', 'fw' ) ),
		'gap'        => $slider( __( 'Gap (%)', 'fw' ), 14, 0, 80, 1, __( 'Space between photos as a % of a photo\'s width.', 'fw' ) ),
		'tilt'       => $slider( __( 'Floor Tilt (°)', 'fw' ), 55, 0, 80, 1, __( 'How far the grid lies back. 0 = face-on, so the wave pushes photos straight toward you.', 'fw' ) ),
		'amplitude'  => $slider( __( 'Wave Height (%)', 'fw' ), 35, 0, 120, 1, __( 'How high the crests lift the photos, as a % of a photo\'s width.', 'fw' ) ),
		'wavelength' => $slider( __( 'Wavelength (photos)', 'fw' ), 3.5, 1.5, 10, 0.5, __( 'How many photos from one crest to the next. Small = choppy; large = long, gentle swells.', 'fw' ) ),
		'origin'     => array( 'label' => __( 'Wave Starts From', 'fw' ), 'type' => 'select', 'value' => 'center', 'choices' => array( 'center' => __( 'The centre (rings)', 'fw' ), 'left' => __( 'The left edge (straight rows)', 'fw' ), 'corner' => __( 'The top-left corner (diagonal)', 'fw' ) ) ),
		'focus_glow' => $slider( __( 'Crest Brightness (%)', 'fw' ), 130, 100, 220, 1, __( 'How much the photos on a crest light up. 100 = even.', 'fw' ) ),
	) ),
	'group_wvg_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Card Size (%)', 'fw' ), 11, 4, 40, 1, __( 'Photo width as a % of the stage width.', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '1-1', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 6, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the card width.', 'fw' ) ),
	) ),
	'group_wvg_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Flip Grid (Grid) — a face-on grid of two-sided cards; every Loop Duration a wave of 3D flips turns
 * each card over to the next photo, so a small grid cycles through the whole gallery. An original design. */
$flip_grid_opts = array(
	'group_flg_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 4, 2, 15, array( 'fwd' => __( 'Forward', 'fw' ), 'back' => __( 'Backward', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to flip', 'fw' ), 'no', __( 'Let visitors drag sideways to flip through the photos by hand. No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep flipping after releasing a drag.', 'fw' ) ),
	) ),
	'group_flg_grid' => array( 'type' => 'group', 'options' => array(
		'columns'   => $slider( __( 'Columns', 'fw' ), 4, 1, 10, 1 ),
		'rows'      => $slider( __( 'Rows', 'fw' ), 3, 1, 8, 1, __( 'With fewer cells than photos, each flip shows the next photos; with more cells, photos repeat.', 'fw' ) ),
		'gap'       => $slider( __( 'Gap (%)', 'fw' ), 8, 0, 60, 1, __( 'Space between cards as a % of a card\'s width.', 'fw' ) ),
		'flip_axis' => array( 'label' => __( 'Flip', 'fw' ), 'type' => 'select', 'value' => 'y', 'choices' => array( 'y' => __( 'Sideways (around the vertical)', 'fw' ), 'x' => __( 'Head over heels (around the horizontal)', 'fw' ) ) ),
		'order'     => array( 'label' => __( 'Flip Order', 'fw' ), 'type' => 'select', 'value' => 'wave', 'choices' => array( 'wave' => __( 'A diagonal wave', 'fw' ), 'random' => __( 'Random', 'fw' ), 'rows' => __( 'Row by row', 'fw' ) ) ),
		'tilt'      => $slider( __( 'Tilt (°)', 'fw' ), 8, 0, 40, 1, __( 'A slight backward lean, so the flips read in 3D. 0 = flat on.', 'fw' ) ),
	) ),
	'group_flg_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Card Size (%)', 'fw' ), 15, 4, 40, 1, __( 'Card width as a % of the stage width.', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '1-1', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 6, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the card width.', 'fw' ) ),
	) ),
	'group_flg_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Magnet Grid (Grid) — a face-on grid that follows the pointer: nearby cards lift, grow and turn toward
 * it, the grid leans toward it; with no pointer, an invisible one drifts around. An original design. */
$magnet_grid_opts = array(
	'group_mgg_motion' => array( 'type' => 'group', 'options' => array(
		'motion' => $motion_picker( 'continuous', __( 'Drift when idle', 'fw' ), 10, 3, 40, array( 'roam' => __( 'Roam the whole grid', 'fw' ), 'center' => __( 'Stay near the centre', 'fw' ) ) ),
	) ),
	'group_mgg_grid' => array( 'type' => 'group', 'options' => array(
		'columns'    => $slider( __( 'Columns', 'fw' ), 5, 2, 12, 1 ),
		'rows'       => $slider( __( 'Rows', 'fw' ), 3, 1, 8, 1, __( 'Photos repeat when the grid has more cells than the gallery has photos.', 'fw' ) ),
		'gap'        => $slider( __( 'Gap (%)', 'fw' ), 14, 0, 80, 1, __( 'Space between cards as a % of a card\'s width.', 'fw' ) ),
		'reach'      => $slider( __( 'Reach (%)', 'fw' ), 22, 5, 60, 1, __( 'How far the pull spreads around the pointer, as a % of the stage width.', 'fw' ) ),
		'lift'       => $slider( __( 'Lift (%)', 'fw' ), 90, 0, 150, 1, __( 'How far the nearest cards rise toward you, as a % of a card\'s width.', 'fw' ) ),
		'lean'       => $slider( __( 'Grid Lean (°)', 'fw' ), 10, 0, 30, 1, __( 'How far the whole grid tips toward the pointer. 0 = it stays flat.', 'fw' ) ),
		'focus_glow' => $slider( __( 'Focus Brightness (%)', 'fw' ), 130, 100, 220, 1, __( 'How much the cards near the pointer light up. 100 = even.', 'fw' ) ),
	) ),
	'group_mgg_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Card Size (%)', 'fw' ), 12, 4, 40, 1, __( 'Card width as a % of the stage width.', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '1-1', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 6, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the card width.', 'fw' ) ),
	) ),
	'group_mgg_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* ---- Reveal & Wipe family — one large photo at a time, revealing the next ---- */

/* Blind Reveal — the photo cut into slats that turn over in 3D one after another, like Venetian blinds,
 * showing the next photo on their other side. An original design. */
$blind_reveal_opts = array(
	'group_blr_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Autoplay', 'fw' ), 4, 2, 15, array( 'fwd' => __( 'Forward', 'fw' ), 'back' => __( 'Backward', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to change', 'fw' ), 'no', __( 'Let visitors drag sideways to turn the blinds by hand. No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep going after releasing a drag.', 'fw' ) ),
	) ),
	'group_blr_blinds' => array( 'type' => 'group', 'options' => array(
		'slats'       => $slider( __( 'Slats', 'fw' ), 10, 2, 40, 1, __( 'How many slats the photo is cut into.', 'fw' ) ),
		'orientation' => array( 'label' => __( 'Slats', 'fw' ), 'type' => 'select', 'value' => 'v', 'choices' => array( 'v' => __( 'Vertical (turn sideways)', 'fw' ), 'h' => __( 'Horizontal (turn head over heels)', 'fw' ) ) ),
		'stagger'     => $slider( __( 'Stagger (%)', 'fw' ), 50, 0, 100, 1, __( 'How far apart the slats start turning. 0 = all at once; 100 = a slow ripple from one side to the other.', 'fw' ) ),
		'gap'         => $slider( __( 'Gap (px)', 'fw' ), 2, 0, 20, 1, __( 'A thin seam between slats. 0 = the photo looks whole at rest.', 'fw' ) ),
	) ),
	'group_blr_photo' => array( 'type' => 'group', 'options' => array(
		'frame_size'    => $slider( __( 'Photo Size (%)', 'fw' ), 70, 20, 100, 1, __( 'Width of the photo as a % of the stage width (it shrinks to fit the Stage Height).', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Photo Ratio', 'fw' ), 'type' => 'select', 'value' => '16-9', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 10, 0, 60, 1 ),
	) ),
	'group_blr_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Iris Wipe — the next photo opens up over the current one through a growing shape, with a gentle
 * push-in zoom. An original design. */
$iris_wipe_opts = array(
	'group_irw_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Autoplay', 'fw' ), 4, 2, 15, array( 'fwd' => __( 'Forward', 'fw' ), 'back' => __( 'Backward', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to change', 'fw' ), 'no', __( 'Let visitors drag sideways to wipe to the next photo by hand. No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep going after releasing a drag.', 'fw' ) ),
	) ),
	'group_irw_wipe' => array( 'type' => 'group', 'options' => array(
		'shape'      => array( 'label' => __( 'Shape', 'fw' ), 'type' => 'select', 'value' => 'circle', 'choices' => array( 'circle' => __( 'Circle (an iris)', 'fw' ), 'diamond' => __( 'Diamond', 'fw' ), 'diagonal' => __( 'Diagonal sweep', 'fw' ), 'doors' => __( 'Barn doors', 'fw' ) ) ),
		'zoom'       => $slider( __( 'Zoom (%)', 'fw' ), 12, 0, 40, 1, __( 'How much the incoming photo settles from a zoom while the outgoing one pushes in. 0 = a flat wipe.', 'fw' ) ),
		'transition' => $slider( __( 'Wipe Length (%)', 'fw' ), 45, 15, 90, 1, __( 'How much of each photo\'s time the wipe takes. The rest of the time the photo holds still.', 'fw' ) ),
	) ),
	'group_irw_photo' => array( 'type' => 'group', 'options' => array(
		'frame_size'    => $slider( __( 'Photo Size (%)', 'fw' ), 70, 20, 100, 1, __( 'Width of the photo as a % of the stage width (it shrinks to fit the Stage Height).', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Photo Ratio', 'fw' ), 'type' => 'select', 'value' => '16-9', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 10, 0, 60, 1 ),
	) ),
	'group_irw_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Tile Dissolve — the photo breaks into tiles that fly toward you, spin and fade in a wave, uncovering
 * the next photo underneath. An original design. */
$tile_dissolve_opts = array(
	'group_tld_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Autoplay', 'fw' ), 4, 2, 15, array( 'fwd' => __( 'Forward', 'fw' ), 'back' => __( 'Backward', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to change', 'fw' ), 'no', __( 'Let visitors drag sideways to break the photo apart by hand. No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep going after releasing a drag.', 'fw' ) ),
	) ),
	'group_tld_tiles' => array( 'type' => 'group', 'options' => array(
		'columns' => $slider( __( 'Columns', 'fw' ), 8, 2, 20, 1 ),
		'rows'    => $slider( __( 'Rows', 'fw' ), 5, 2, 14, 1 ),
		'pattern' => array( 'label' => __( 'Order', 'fw' ), 'type' => 'select', 'value' => 'corner', 'choices' => array( 'corner' => __( 'From the top-left corner', 'fw' ), 'random' => __( 'Random', 'fw' ), 'center' => __( 'From the centre out', 'fw' ) ) ),
		'fly'     => $slider( __( 'Fly Distance (%)', 'fw' ), 60, 0, 100, 1, __( 'How far the tiles fly toward you and spin. 0 = they simply shrink and fade in place.', 'fw' ) ),
	) ),
	'group_tld_photo' => array( 'type' => 'group', 'options' => array(
		'frame_size'    => $slider( __( 'Photo Size (%)', 'fw' ), 70, 20, 100, 1, __( 'Width of the photo as a % of the stage width (it shrinks to fit the Stage Height).', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Photo Ratio', 'fw' ), 'type' => 'select', 'value' => '16-9', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 10, 0, 60, 1 ),
	) ),
	'group_tld_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* ---- Spotlight & Focus family ---- */

/* Spotlight Wall — a grid of photos in shadow; a spotlight glides from photo to photo, holding on each,
 * bringing the photos in its pool up to full colour. Hovering can take the spotlight. An original design. */
$spotlight_wall_opts = array(
	'group_spw_motion' => array( 'type' => 'group', 'options' => array(
		'motion'         => $motion_picker( 'continuous', __( 'Autoplay', 'fw' ), 3, 1, 12, array( 'fwd' => __( 'Forward', 'fw' ), 'back' => __( 'Backward', 'fw' ) ) ),
		'follow_pointer' => $switch( __( 'Follow Pointer', 'fw' ), 'yes', __( 'While the visitor hovers the gallery, the spotlight follows their pointer.', 'fw' ) ),
	) ),
	'group_spw_light' => array( 'type' => 'group', 'options' => array(
		'columns'   => $slider( __( 'Columns', 'fw' ), 5, 1, 12, 1 ),
		'rows'      => $slider( __( 'Rows', 'fw' ), 3, 1, 8, 1, __( 'Photos repeat when the grid has more cells than the gallery has photos.', 'fw' ) ),
		'gap'       => $slider( __( 'Gap (%)', 'fw' ), 10, 0, 80, 1, __( 'Space between photos as a % of a photo\'s width.', 'fw' ) ),
		'spot_size' => $slider( __( 'Spotlight Size (%)', 'fw' ), 80, 30, 300, 5, __( 'The radius of the pool of light, as a % of a photo\'s width. Bigger lights the neighbours too.', 'fw' ) ),
		'shadow'    => $slider( __( 'Shadow Brightness (%)', 'fw' ), 35, 0, 100, 1, __( 'How bright the photos outside the spotlight are. 0 = black; 100 = no shadow.', 'fw' ) ),
		'greyscale' => $slider( __( 'Shadow Greyscale (%)', 'fw' ), 100, 0, 100, 1, __( 'How much colour the photos in shadow lose.', 'fw' ) ),
		'lift'      => $slider( __( 'Lift (%)', 'fw' ), 8, 0, 30, 1, __( 'How much the lit photo grows.', 'fw' ) ),
		'move_time' => $slider( __( 'Move Time (%)', 'fw' ), 35, 10, 90, 1, __( 'How much of each stop the spotlight spends gliding to the next photo; it holds still for the rest.', 'fw' ) ),
		'order'     => array( 'label' => __( 'Order', 'fw' ), 'type' => 'select', 'value' => 'random', 'choices' => array( 'random' => __( 'Random (never the same photo twice in a row)', 'fw' ), 'reading' => __( 'Reading order', 'fw' ) ) ),
	) ),
	'group_spw_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Card Size (%)', 'fw' ), 13, 3, 40, 1, __( 'Photo width as a % of the stage width.', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '1-1', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 6, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the card width.', 'fw' ) ),
	) ),
	'group_spw_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Focus Pull — photos float at several depths and the focus racks between them: the layer in focus is
 * sharp and bright, the others blur and dim, and the field drifts gently. An original design. */
$focus_pull_opts = array(
	'group_fcp_motion' => array( 'type' => 'group', 'options' => array(
		'motion' => $motion_picker( 'continuous', __( 'Autoplay', 'fw' ), 3, 1, 12, array( 'front' => __( 'Start at the front', 'fw' ), 'back' => __( 'Start at the back', 'fw' ) ) ),
	) ),
	'group_fcp_depth' => array( 'type' => 'group', 'options' => array(
		'layers'    => $slider( __( 'Layers', 'fw' ), 3, 2, 5, 1, __( 'How many depths the photos float at. The focus racks from the front layer to the back and back again.', 'fw' ) ),
		'photos'    => $slider( __( 'Photos', 'fw' ), 15, 4, 40, 1, __( 'How many photos float in the scene (the gallery repeats to fill it).', 'fw' ) ),
		'depth'     => $slider( __( 'Depth (%)', 'fw' ), 45, 10, 120, 1, __( 'The distance between layers, as a % of the stage width.', 'fw' ) ),
		'blur'      => $slider( __( 'Blur (px)', 'fw' ), 7, 0, 20, 1, __( 'How blurred the layer next to the focus is; layers further away blur more.', 'fw' ) ),
		'dim'       => $slider( __( 'Dim (%)', 'fw' ), 30, 0, 80, 1, __( 'How much the out-of-focus photos darken.', 'fw' ) ),
		'drift'     => $slider( __( 'Drift (%)', 'fw' ), 30, 0, 100, 1, __( 'A gentle sideways drift, deeper layers moving more (parallax). 0 = still.', 'fw' ) ),
		'move_time' => $slider( __( 'Rack Time (%)', 'fw' ), 40, 10, 90, 1, __( 'How much of each stop the focus spends racking to the next layer; it holds still for the rest.', 'fw' ) ),
	) ),
	'group_fcp_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Card Size (%)', 'fw' ), 15, 3, 40, 1, __( 'Photo width as a % of the stage width (deeper layers look smaller).', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '1-1', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 6, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the card width.', 'fw' ) ),
	) ),
	'group_fcp_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Loupe — a mosaic of small photos with a magnifying lens gliding over it, showing them enlarged inside
 * the lens. Hovering can move the lens. An original design. */
$loupe_opts = array(
	'group_lpe_motion' => array( 'type' => 'group', 'options' => array(
		'motion'         => $motion_picker( 'continuous', __( 'Drift when idle', 'fw' ), 12, 3, 40, array( 'roam' => __( 'Roam the whole mosaic', 'fw' ), 'center' => __( 'Stay near the centre', 'fw' ) ) ),
		'follow_pointer' => $switch( __( 'Follow Pointer', 'fw' ), 'yes', __( 'While the visitor hovers the gallery, the lens follows their pointer.', 'fw' ) ),
	) ),
	'group_lpe_lens' => array( 'type' => 'group', 'options' => array(
		'columns'   => $slider( __( 'Columns', 'fw' ), 6, 2, 14, 1 ),
		'rows'      => $slider( __( 'Rows', 'fw' ), 4, 1, 10, 1, __( 'Photos repeat when the mosaic has more cells than the gallery has photos.', 'fw' ) ),
		'gap'       => $slider( __( 'Gap (%)', 'fw' ), 8, 0, 60, 1, __( 'Space between photos as a % of a photo\'s width.', 'fw' ) ),
		'lens_size' => $slider( __( 'Lens Size (%)', 'fw' ), 24, 8, 60, 1, __( 'The lens diameter as a % of the stage width.', 'fw' ) ),
		'zoom'      => $slider( __( 'Zoom (%)', 'fw' ), 220, 120, 500, 10, __( 'How much the lens enlarges. 200 = twice the size.', 'fw' ) ),
		'lens_ring' => array( 'type' => 'color-picker', 'label' => __( 'Lens Ring Colour', 'fw' ), 'value' => '#ffffff' ),
	) ),
	'group_lpe_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Card Size (%)', 'fw' ), 10, 3, 40, 1, __( 'Photo width as a % of the stage width.', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '1-1', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 6, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the card width.', 'fw' ) ),
	) ),
	'group_lpe_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Card Fan — the photos held like a hand of playing cards; a highlight sweeps along the hand and the card
 * it reaches rises out of the fan. An original design. */
$card_fan_opts = array(
	'group_cfn_motion' => array( 'type' => 'group', 'options' => array(
		'motion'         => $motion_picker( 'continuous', __( 'Autoplay', 'fw' ), 2, 1, 10, array( 'ltr' => __( 'Start on the left', 'fw' ), 'rtl' => __( 'Start on the right', 'fw' ) ) ),
		'follow_pointer' => $switch( __( 'Follow Pointer', 'fw' ), 'yes', __( 'While the visitor hovers the gallery, the card they point at rises.', 'fw' ) ),
	) ),
	'group_cfn_fan' => array( 'type' => 'group', 'options' => array(
		'cards'      => $slider( __( 'Cards in Hand', 'fw' ), 7, 3, 15, 1, __( 'How many cards the hand holds (the gallery repeats to fill it).', 'fw' ) ),
		'spread'     => $slider( __( 'Fan Spread (°)', 'fw' ), 70, 10, 200, 1, __( 'The angle between the first and the last card.', 'fw' ) ),
		'pivot'      => $slider( __( 'Pivot Depth (%)', 'fw' ), 120, 30, 400, 5, __( 'How far below the cards the fan turns, as a % of a card\'s height. Deeper makes a flatter, wider arc.', 'fw' ) ),
		'lift'       => $slider( __( 'Lift (%)', 'fw' ), 35, 0, 90, 1, __( 'How far the highlighted card rises out of the hand, as a % of its height.', 'fw' ) ),
		'straighten' => $slider( __( 'Straighten (%)', 'fw' ), 60, 0, 100, 1, __( 'How upright the highlighted card turns as it rises. 100 = fully upright.', 'fw' ) ),
		'dim'        => $slider( __( 'Dim (%)', 'fw' ), 25, 0, 90, 1, __( 'How much the other cards darken.', 'fw' ) ),
		'sway'       => $slider( __( 'Sway (°)', 'fw' ), 3, 0, 20, 1, __( 'A gentle rocking of the whole hand. 0 = still.', 'fw' ) ),
		'move_time'  => $slider( __( 'Move Time (%)', 'fw' ), 45, 10, 90, 1, __( 'How much of each step the highlight spends moving to the next card; it holds still for the rest.', 'fw' ) ),
	) ),
	'group_cfn_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Card Size (%)', 'fw' ), 20, 4, 50, 1, __( 'Card width as a % of the stage width.', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '3-4', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 12, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the card width.', 'fw' ) ),
	) ),
	'group_cfn_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Shuffle Deck — a loose deck; the top photo slides out to the side and tucks back in at the bottom while
 * the others move up. An original design. */
$shuffle_deck_opts = array(
	'group_shd_motion' => array( 'type' => 'group', 'options' => array(
		'motion' => $motion_picker( 'continuous', __( 'Autoplay', 'fw' ), 3, 1, 12, array( 'alternate' => __( 'Alternate sides', 'fw' ), 'right' => __( 'Always to the right', 'fw' ), 'left' => __( 'Always to the left', 'fw' ) ) ),
	) ),
	'group_shd_deck' => array( 'type' => 'group', 'options' => array(
		'behind'     => $slider( __( 'Cards Behind', 'fw' ), 4, 1, 8, 1, __( 'How many photos peek out behind the top one.', 'fw' ) ),
		'offset'     => $slider( __( 'Deck Offset (%)', 'fw' ), 4, 0, 20, 0.5, __( 'How far each photo behind is nudged up and to the side, as a % of a card\'s width.', 'fw' ) ),
		'jitter'     => $slider( __( 'Jitter (°)', 'fw' ), 4, 0, 20, 1, __( 'How much the photos behind lean, each its own way, for a hand-held look. 0 = a neat deck.', 'fw' ) ),
		'slide'      => $slider( __( 'Slide Distance (%)', 'fw' ), 75, 30, 160, 1, __( 'How far the top photo slides out before it tucks back in, as a % of its width.', 'fw' ) ),
		'slide_tilt' => $slider( __( 'Slide Tilt (°)', 'fw' ), 10, 0, 45, 1, __( 'How much the photo turns as it slides out.', 'fw' ) ),
		'move_time'  => $slider( __( 'Shuffle Time (%)', 'fw' ), 55, 15, 95, 1, __( 'How much of each step the shuffle takes; the deck rests for the rest.', 'fw' ) ),
	) ),
	'group_shd_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Card Size (%)', 'fw' ), 34, 6, 70, 1, __( 'Card width as a % of the stage width.', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '4-3', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 12, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the card width.', 'fw' ) ),
	) ),
	'group_shd_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Polaroid Drop — instant-photo prints drop one by one onto a growing pile; when it is full the oldest
 * slides away, or the pile is swept off. An original design. */
$polaroid_drop_opts = array(
	'group_pld_motion' => array( 'type' => 'group', 'options' => array(
		'motion' => $motion_picker( 'continuous', __( 'Autoplay', 'fw' ), 2, 1, 10, array( 'above' => __( 'Drop from above', 'fw' ), 'camera' => __( 'Toss in from the camera', 'fw' ) ) ),
	) ),
	'group_pld_pile' => array( 'type' => 'group', 'options' => array(
		'pile'        => $slider( __( 'Pile Size', 'fw' ), 6, 1, 20, 1, __( 'How many prints lie on the pile at once.', 'fw' ) ),
		'when_full'   => array( 'label' => __( 'When Full', 'fw' ), 'type' => 'select', 'value' => 'oldest', 'choices' => array( 'oldest' => __( 'The oldest print slides away', 'fw' ), 'sweep' => __( 'Sweep the pile away and start again', 'fw' ) ) ),
		'scatter'     => $slider( __( 'Scatter (%)', 'fw' ), 45, 0, 200, 1, __( 'How far from the centre the prints land, as a % of a print\'s width. 0 = a neat stack.', 'fw' ) ),
		'max_tilt'    => $slider( __( 'Max Tilt (°)', 'fw' ), 14, 0, 45, 1, __( 'The most a print can lean as it lands.', 'fw' ) ),
		'move_time'   => $slider( __( 'Drop Time (%)', 'fw' ), 45, 15, 95, 1, __( 'How much of each step the drop takes; the pile rests for the rest.', 'fw' ) ),
		'frame_color' => array( 'type' => 'color-picker', 'label' => __( 'Frame Colour', 'fw' ), 'value' => '#ffffff' ),
	) ),
	'group_pld_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Card Size (%)', 'fw' ), 24, 6, 60, 1, __( 'Print width as a % of the stage width.', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '4-5', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 3, 0, 30, 1 ),
	) ),
	'group_pld_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Phone Lineup — every photo on a phone; the phones stand in a shallow V and the lineup turns like a
 * carousel. An original design. */
$phone_lineup_opts = array(
	'group_phl_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 3, 1, 12, array( 'left' => __( 'Left', 'fw' ), 'right' => __( 'Right', 'fw' ) ) ),
		'snap'          => $switch( __( 'Stop at Centre', 'fw' ), 'yes', __( 'Hold each phone in the middle for a moment (Loop Duration = seconds per phone). Off: the lineup turns without stopping.', 'fw' ) ),
		'allow_drag'    => $switch( __( 'Drag to spin', 'fw' ), 'yes', __( 'Let visitors grab the lineup and turn it by hand. No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep turning after a drag is released.', 'fw' ) ),
	) ),
	'group_phl_line' => array( 'type' => 'group', 'options' => array(
		'phones'      => $slider( __( 'Phones', 'fw' ), 7, 3, 15, 1, __( 'How many phones stand in the lineup (the gallery repeats to fill them). 5 or more lets the ends fade smoothly.', 'fw' ) ),
		'spacing'     => $slider( __( 'Spacing (%)', 'fw' ), 70, 20, 200, 1, __( 'The distance between phones, as a % of a phone\'s width. Under 100 they overlap.', 'fw' ) ),
		'depth'       => $slider( __( 'Depth (%)', 'fw' ), 50, 0, 150, 1, __( 'How far each phone steps back from the centre one. 0 = a straight line.', 'fw' ) ),
		'turn'        => $slider( __( 'Turn (°)', 'fw' ), 25, 0, 80, 1, __( 'How much the side phones turn toward the centre.', 'fw' ) ),
		'float'       => $slider( __( 'Float (%)', 'fw' ), 3, 0, 12, 0.5, __( 'A gentle up-and-down float. 0 = still.', 'fw' ) ),
		'move_time'   => $slider( __( 'Move Time (%)', 'fw' ), 45, 10, 95, 1, __( 'With Stop at Centre: how much of each step the lineup spends moving.', 'fw' ) ),
		'thickness'   => $slider( __( 'Thickness (%)', 'fw' ), 8, 0, 20, 0.5, __( 'How thick each phone is, as a % of its width — the metal side shows as the phones turn. 0 = a flat screen.', 'fw' ) ),
		'frame_color' => array( 'type' => 'color-picker', 'label' => __( 'Frame Colour', 'fw' ), 'value' => '#3a3c41', 'desc' => __( 'The colour of each phone\'s metal frame, sides and back. The bezel round the screen stays black.', 'fw' ) ),
	) ),
	'group_phl_card' => array( 'type' => 'group', 'options' => array(
		'card_size'  => $slider( __( 'Phone Size (%)', 'fw' ), 16, 4, 40, 1, __( 'Phone width as a % of the stage width.', 'fw' ) ),
		'card_ratio' => array( 'label' => __( 'Screen Ratio', 'fw' ), 'type' => 'select', 'value' => '9-16', 'choices' => $ratio_choices ),
	) ),
	'group_phl_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Window Stack — every photo in a browser window, stacked in a tilted 3D pile; the front window leaves
 * and the rest move forward. An original design. */
$window_stack_opts = array(
	'group_wst_motion' => array( 'type' => 'group', 'options' => array(
		'motion' => $motion_picker( 'continuous', __( 'Autoplay', 'fw' ), 3, 1, 12, array( 'toward' => __( 'Front window comes toward you', 'fw' ), 'down' => __( 'Front window drops away', 'fw' ) ) ),
	) ),
	'group_wst_stack' => array( 'type' => 'group', 'options' => array(
		'behind'       => $slider( __( 'Windows Behind', 'fw' ), 4, 1, 8, 1, __( 'How many windows show behind the front one.', 'fw' ) ),
		'gap'          => $slider( __( 'Depth Gap (%)', 'fw' ), 30, 5, 100, 1, __( 'How far back each window sits from the one in front, as a % of a window\'s width.', 'fw' ) ),
		'offset'       => $slider( __( 'Offset (%)', 'fw' ), 8, 0, 30, 0.5, __( 'How far each window behind is moved up and to the side.', 'fw' ) ),
		'tilt'         => $slider( __( 'Tilt (°)', 'fw' ), 16, 0, 50, 1, __( 'How much the pile leans back.', 'fw' ) ),
		'yaw'          => $slider( __( 'Turn (°)', 'fw' ), 22, -45, 45, 1, __( 'How much the pile turns to the side (negative turns it the other way).', 'fw' ) ),
		'dim'          => $slider( __( 'Dim (%)', 'fw' ), 30, 0, 80, 1, __( 'How much the deepest window darkens.', 'fw' ) ),
		'move_time'    => $slider( __( 'Move Time (%)', 'fw' ), 55, 15, 95, 1, __( 'How much of each step the change takes; the pile rests for the rest.', 'fw' ) ),
		'chrome_color' => array( 'type' => 'color-picker', 'label' => __( 'Title Bar Colour', 'fw' ), 'value' => '#e9eaee' ),
	) ),
	'group_wst_card' => array( 'type' => 'group', 'options' => array(
		'card_size'  => $slider( __( 'Window Size (%)', 'fw' ), 44, 10, 80, 1, __( 'Window width as a % of the stage width.', 'fw' ) ),
		'card_ratio' => array( 'label' => __( 'Window Ratio', 'fw' ), 'type' => 'select', 'value' => '16-9', 'choices' => $ratio_choices ),
	) ),
	'group_wst_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Device Trio — a laptop with a tablet and a phone floating in front of it; the screens change one device
 * at a time. An original design. */
/* ---- WebGL (real 3D) — drawn with Three.js by static/js/gallery-3d-webgl.js, loaded only when used.
 * Real perspective, lights and (Mirror Ring) floor reflections; the photos also stay in the page as the
 * accessible card list. No WebGL → a plain grid. */
$webgl_light = array(
	'label'   => __( 'Lighting', 'fw' ),
	'type'    => 'select',
	'value'   => 'studio',
	'choices' => array(
		'soft'     => __( 'Soft (even, low contrast)', 'fw' ),
		'studio'   => __( 'Studio (key light + rim)', 'fw' ),
		'dramatic' => __( 'Dramatic (dark, strong key light)', 'fw' ),
	),
);
$webgl_card_group = function ( $size_def, $ratio_def ) use ( $slider, $ratio_choices ) {
	return array(
		'card_size'     => $slider( __( 'Card Size (%)', 'fw' ), $size_def, 6, 50, 1, __( 'How wide a photo at the front looks, as a % of the stage width.', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => $ratio_def, 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 12, 0, 60, 1 ),
	);
};
$webgl_mirror_ring_opts = array(
	'group_wmr_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Auto-rotate', 'fw' ), 24, 4, 90, array( 'left' => __( 'Left', 'fw' ), 'right' => __( 'Right', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to spin', 'fw' ), 'yes', __( 'Let visitors grab and spin the ring — grabbing pauses the motion, releasing resumes it.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep spinning after releasing a drag.', 'fw' ) ),
	) ),
	'group_wmr_scene' => array( 'type' => 'group', 'options' => array(
		'ring_size'   => $slider( __( 'Ring Size (%)', 'fw' ), 90, 40, 160, 1, __( 'How wide the ring is. Bigger leaves more room between the photos.', 'fw' ) ),
		'camera_height' => $slider( __( 'Camera Height (°)', 'fw' ), 14, 0, 45, 1, __( 'How far above the ring you look from. 0 = level with the photos.', 'fw' ) ),
		'reflection'  => $slider( __( 'Floor Reflection (%)', 'fw' ), 45, 0, 100, 1, __( 'How strongly the glossy floor reflects the photos. 0 = no floor reflection.', 'fw' ) ),
		'floor_color' => array( 'type' => 'color-picker', 'label' => __( 'Floor Glow', 'fw' ), 'value' => '', 'desc' => __( 'A soft disc of colour on the floor under the ring. Empty = a neutral grey.', 'fw' ) ),
		'lighting'    => $webgl_light,
	) ),
	'group_wmr_card'  => array( 'type' => 'group', 'options' => $webgl_card_group( 20, '3-4' ) ),
	'group_wmr_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'background' => $bg_field ) ),
);
$webgl_depth_field_opts = array(
	'group_wdf_motion' => array( 'type' => 'group', 'options' => array(
		'motion' => $motion_picker( 'continuous', __( 'Drift', 'fw' ), 20, 4, 90, array( 'toward' => __( 'Toward the viewer', 'fw' ), 'away' => __( 'Away from the viewer', 'fw' ) ) ),
	) ),
	'group_wdf_scene' => array( 'type' => 'group', 'options' => array(
		'cards'     => $slider( __( 'Photos in the Field', 'fw' ), 18, 4, 60, 1, __( 'How many photos float in the scene (the gallery repeats to fill it).', 'fw' ) ),
		'depth'     => $slider( __( 'Depth (%)', 'fw' ), 60, 0, 100, 1, __( 'How deep the field goes. Deeper spreads the photos further back.', 'fw' ) ),
		'spread'    => $slider( __( 'Spread (%)', 'fw' ), 100, 40, 160, 1, __( 'How wide and tall the field is.', 'fw' ) ),
		'fog'       => $slider( __( 'Fog (%)', 'fw' ), 55, 0, 100, 1, __( 'How quickly distant photos fade into the background colour.', 'fw' ) ),
		'fog_color' => array( 'type' => 'color-picker', 'label' => __( 'Fog Colour', 'fw' ), 'value' => '', 'desc' => __( 'What distant photos fade into. Empty = the Stage Background, or a deep blue-black. Match it to the background.', 'fw' ) ),
		'parallax'  => $slider( __( 'Pointer Parallax (%)', 'fw' ), 40, 0, 100, 1, __( 'How far the camera follows the pointer, so near photos move more than far ones.', 'fw' ) ),
		'lighting'  => $webgl_light,
	) ),
	'group_wdf_card'  => array( 'type' => 'group', 'options' => $webgl_card_group( 18, '4-3' ) ),
	'group_wdf_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'background' => $bg_field ) ),
);
$webgl_wave_wall_opts = array(
	'group_wwv_motion' => array( 'type' => 'group', 'options' => array(
		'motion' => $motion_picker( 'continuous', __( 'Ripple', 'fw' ), 8, 2, 40, array( 'left' => __( 'Left', 'fw' ), 'right' => __( 'Right', 'fw' ) ) ),
	) ),
	'group_wwv_scene' => array( 'type' => 'group', 'options' => array(
		'columns'     => $slider( __( 'Columns', 'fw' ), 6, 2, 12, 1 ),
		'rows'        => $slider( __( 'Rows', 'fw' ), 3, 1, 8, 1 ),
		'wave_height' => $slider( __( 'Wave Height (%)', 'fw' ), 40, 0, 100, 1, __( 'How far the surface rises and falls.', 'fw' ) ),
		'wave_length' => $slider( __( 'Wave Length (%)', 'fw' ), 80, 20, 200, 1, __( 'How long each wave is. Shorter makes busier ripples.', 'fw' ) ),
		'view_angle'  => $slider( __( 'View Angle (°)', 'fw' ), 25, 0, 60, 1, __( 'How far below the wall you look up from. 0 = straight on.', 'fw' ) ),
		'light_angle' => $slider( __( 'Light Angle (°)', 'fw' ), -30, -90, 90, 1, __( 'Where the key light comes from, left (−) to right (+). The photos catch it as they tilt.', 'fw' ) ),
		'gloss'       => $slider( __( 'Gloss (%)', 'fw' ), 40, 0, 100, 1, __( 'How shiny the photos are: higher catches brighter highlights.', 'fw' ) ),
		'gap'         => $slider( __( 'Gap (%)', 'fw' ), 8, 0, 30, 1, __( 'Space between the photos, as a % of a photo\'s width.', 'fw' ) ),
		'lighting'    => $webgl_light,
	) ),
	'group_wwv_card'  => array( 'type' => 'group', 'options' => array(
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '4-3', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 8, 0, 60, 1 ),
	) ),
	'group_wwv_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'background' => $bg_field ) ),
);

$device_trio_opts = array(
	'group_dtr_motion' => array( 'type' => 'group', 'options' => array(
		'motion'         => $motion_picker( 'continuous', __( 'Autoplay', 'fw' ), 3, 1, 12, array( 'sway' => __( 'Gentle sway', 'fw' ), 'still' => __( 'Hold still', 'fw' ) ) ),
		'follow_pointer' => $switch( __( 'Follow Pointer', 'fw' ), 'yes', __( 'While the visitor hovers the gallery, the devices turn slightly toward their pointer.', 'fw' ) ),
	) ),
	'group_dtr_trio' => array( 'type' => 'group', 'options' => array(
		'card_size' => $slider( __( 'Size (%)', 'fw' ), 48, 15, 90, 1, __( 'The laptop\'s width as a % of the stage width; the tablet and phone scale with it.', 'fw' ) ),
		'depth'     => $slider( __( 'Depth (%)', 'fw' ), 30, 0, 100, 1, __( 'How far in front of the laptop the tablet and phone float.', 'fw' ) ),
		'float'     => $slider( __( 'Float (%)', 'fw' ), 2, 0, 8, 0.5, __( 'A gentle up-and-down float. 0 = still.', 'fw' ) ),
		'tilt'      => $slider( __( 'Tilt (°)', 'fw' ), 10, 0, 30, 1, __( 'The most the group turns (toward the pointer, or as it sways).', 'fw' ) ),
	) ),
	'group_dtr_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Card Reel — a vertical cover-flow carousel (Carousel & Flow). A single column of cards wraps around
 * a horizontal cylinder; the centre card is the flat, forward focus. Flows up/down, or "Stop at centre"
 * snaps each card to the middle. 3D Curve bends the cylinder (flat ↔ tight barrel). */
$card_reel_opts = array(
	'group_cr_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 20, 5, 90, array( 'up' => __( 'Up', 'fw' ), 'down' => __( 'Down', 'fw' ) ) ),
		'snap'          => $switch( __( 'Stop at Centre', 'fw' ), 'no', __( 'Snap each card to the centre and hold it briefly (a stepping carousel) instead of flowing continuously. Works with Continuous and Scroll-scrub.', 'fw' ) ),
		'allow_drag'    => $switch( __( 'Drag to spin', 'fw' ), 'yes', __( 'Let visitors grab and flick the reel by hand — layers on top of any Motion (grabbing pauses it, releasing resumes). No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep flowing after releasing a drag (Drag to spin).', 'fw' ) ),
	) ),
	'group_cr_reel' => array( 'type' => 'group', 'options' => array(
		'curve_3d'  => $slider( __( '3D Curve (%)', 'fw' ), -100, -100, 100, 1, __( 'Bends the reel into a cylinder — the centre card faces you while the neighbours tilt back. 0 is a flat vertical strip; ±100 is a tight barrel (the sign flips which way it bows).', 'fw' ) ),
		'card_size' => $slider( __( 'Card Size (%)', 'fw' ), 22, 6, 60, 1, __( 'Card width as a % of the stage width.', 'fw' ) ),
		'gap'       => $slider( __( 'Gap (%)', 'fw' ), 1, 0, 20, 0.5, __( 'Space between cards along the reel, as a % of the card height.', 'fw' ) ),
	) ),
	'group_cr_card' => array( 'type' => 'group', 'options' => array(
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '1-1', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 0, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the card width.', 'fw' ) ),
	) ),
	'group_cr_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Film Strip — the horizontal sibling of Card Reel (Carousel & Flow). A single row of cards wraps
 * around a vertical cylinder; the centre card is the flat, forward focus. Flows left/right, or "Stop
 * at centre" snaps. Same controls as Card Reel, horizontal. */
$film_strip_opts = array(
	'group_fs_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 20, 5, 90, array( 'left' => __( 'Left', 'fw' ), 'right' => __( 'Right', 'fw' ) ) ),
		'snap'          => $switch( __( 'Stop at Centre', 'fw' ), 'no', __( 'Snap each card to the centre and hold it briefly (a stepping carousel) instead of flowing continuously. Works with Continuous and Scroll-scrub.', 'fw' ) ),
		'allow_drag'    => $switch( __( 'Drag to spin', 'fw' ), 'yes', __( 'Let visitors grab and flick the strip by hand — layers on top of any Motion (grabbing pauses it, releasing resumes). No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep flowing after releasing a drag (Drag to spin).', 'fw' ) ),
	) ),
	'group_fs_reel' => array( 'type' => 'group', 'options' => array(
		'curve_3d'  => $slider( __( '3D Curve (%)', 'fw' ), -100, -100, 100, 1, __( 'Bends the strip into a cylinder — the centre card faces you while the sides tilt back. 0 is a flat horizontal row; ±100 is a tight barrel (the sign flips which way it bows).', 'fw' ) ),
		'card_size' => $slider( __( 'Card Size (%)', 'fw' ), 22, 6, 60, 1, __( 'Card width as a % of the stage width.', 'fw' ) ),
		'gap'       => $slider( __( 'Gap (%)', 'fw' ), 1, 0, 20, 0.5, __( 'Space between cards along the strip, as a % of the card width.', 'fw' ) ),
	) ),
	'group_fs_card' => array( 'type' => 'group', 'options' => array(
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '1-1', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 0, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the card width.', 'fw' ) ),
	) ),
	'group_fs_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Wheel Carousel — cards fanned on a big Ferris-style wheel (hub below the frame, only the top arc
 * shows), stepping around card by card with a springy step: Overshoot sails past + settles,
 * Anticipation winds back first, Hold pauses on each card. A flat 2D wheel. */
$wheel_carousel_opts = array(
	'group_wc_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 20, 5, 90, array( 'cw' => __( 'Clockwise', 'fw' ), 'ccw' => __( 'Counter-clockwise', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to spin', 'fw' ), 'yes', __( 'Let visitors grab and spin the wheel by hand — layers on top of any Motion (grabbing pauses it, releasing snaps to the nearest card). No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep spinning after releasing a drag (Drag to spin).', 'fw' ) ),
	) ),
	'group_wc_wheel' => array( 'type' => 'group', 'options' => array(
		'wheel_size'   => $slider( __( 'Wheel Size (%)', 'fw' ), 70, 20, 160, 1, __( 'Radius of the wheel — bigger flattens the arc (a gentler fan); smaller tightens it into a steeper curve.', 'fw' ) ),
		'card_size'    => $slider( __( 'Card Size (%)', 'fw' ), 40, 6, 90, 1, __( 'Card width as a % of the stage width.', 'fw' ) ),
		'anticipation' => $slider( __( 'Anticipation (%)', 'fw' ), 0, 0, 40, 1, __( 'The wheel winds back a touch before each step — a mechanical wind-up.', 'fw' ) ),
		'overshoot'    => $slider( __( 'Overshoot (%)', 'fw' ), 0, 0, 30, 1, __( 'Each step sails past the card then springs back to settle — a bouncy stop.', 'fw' ) ),
		'hold'         => $slider( __( 'Hold (%)', 'fw' ), 0, 0, 60, 1, __( 'How long the wheel pauses on each card before stepping to the next.', 'fw' ) ),
	) ),
	'group_wc_card' => array( 'type' => 'group', 'options' => array(
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '1-1', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 0, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the card width.', 'fw' ) ),
	) ),
	'group_wc_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

$orient_field = array( 'label' => __( 'Orientation', 'fw' ), 'type' => 'select', 'value' => 'horizontal', 'choices' => array( 'horizontal' => __( 'Horizontal', 'fw' ), 'vertical' => __( 'Vertical', 'fw' ) ), 'desc' => __( 'Which way the flow runs. Horizontal slides left/right; Vertical slides up/down.', 'fw' ) );

/* Cover Flow — the classic album cover flow: centre card facing you, immediate neighbours tilted to
 * Side Tilt and receding, further cards stacked compressed behind. Horizontal OR Vertical (Orientation)
 * — this one design covers the reference's Cover Flow and Cover Flow Vertical. */
$cover_flow_opts = array(
	'group_cvf_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 20, 5, 90, array( 'forward' => __( 'Forward', 'fw' ), 'backward' => __( 'Backward', 'fw' ) ) ),
		'snap'          => $switch( __( 'Stop at Centre', 'fw' ), 'yes', __( 'Snap each card to the centre and hold it briefly (the classic cover-flow step) instead of drifting continuously.', 'fw' ) ),
		'allow_drag'    => $switch( __( 'Drag to spin', 'fw' ), 'yes', __( 'Let visitors grab and flick through the covers by hand. No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep flowing after releasing a drag (Drag to spin).', 'fw' ) ),
	) ),
	'group_cvf_flow' => array( 'type' => 'group', 'options' => array(
		'orientation' => $orient_field,
		'side_tilt'   => $slider( __( 'Side Tilt (°)', 'fw' ), 40, 0, 90, 1, __( 'How steeply the cards beside the centre one flip away from you — the signature cover-flow angle. 0 lays them flat.', 'fw' ) ),
		'card_size'   => $slider( __( 'Card Size (%)', 'fw' ), 46, 6, 90, 1, __( 'Centre card width as a % of the stage width.', 'fw' ) ),
		'gap'         => $slider( __( 'Gap (%)', 'fw' ), 4, 0, 40, 0.5, __( 'Space between the centre card and its neighbours.', 'fw' ) ),
	) ),
	'group_cvf_card' => array( 'type' => 'group', 'options' => array(
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '1-1', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 0, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the card width.', 'fw' ) ),
	) ),
	'group_cvf_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Carousel Flow — a flat focus carousel: centre card full size, side cards scaled down (Side Card
 * Scale) and faded, sliding past. No tilt. Horizontal OR Vertical (Orientation). */
$carousel_flow_opts = array(
	'group_caf_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 20, 5, 90, array( 'forward' => __( 'Forward', 'fw' ), 'backward' => __( 'Backward', 'fw' ) ) ),
		'snap'          => $switch( __( 'Stop at Centre', 'fw' ), 'yes', __( 'Snap each card to the centre and hold it briefly instead of drifting continuously.', 'fw' ) ),
		'allow_drag'    => $switch( __( 'Drag to spin', 'fw' ), 'yes', __( 'Let visitors grab and flick through the carousel by hand. No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep flowing after releasing a drag (Drag to spin).', 'fw' ) ),
	) ),
	'group_caf_flow' => array( 'type' => 'group', 'options' => array(
		'orientation' => $orient_field,
		'side_scale'  => $slider( __( 'Side Card Scale (%)', 'fw' ), 82, 20, 100, 1, __( 'How big the flanking cards are next to the full-size centre one. 100 = same size; lower shrinks them.', 'fw' ) ),
		'card_size'   => $slider( __( 'Card Size (%)', 'fw' ), 50, 6, 90, 1, __( 'Centre card width as a % of the stage width.', 'fw' ) ),
		'gap'         => $slider( __( 'Gap (%)', 'fw' ), 5, 0, 40, 0.5, __( 'Space between cards.', 'fw' ) ),
	) ),
	'group_caf_card' => array( 'type' => 'group', 'options' => array(
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '1-1', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 0, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the card width.', 'fw' ) ),
	) ),
	'group_caf_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Hero Reel — a horizontal reel of thumbnail cards over a full-bleed HERO background. The card stepping
 * through the centre is the active one: it scales up + lifts, and its image fills the stage as a dimmed,
 * slowly-zooming (Ken Burns) hero behind the reel, cross-fading as the reel advances. A flat 2D design. */
$hero_reel_opts = array(
	'group_hr_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 24, 5, 90, array( 'left' => __( 'Left', 'fw' ), 'right' => __( 'Right', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to spin', 'fw' ), 'yes', __( 'Let visitors grab and step the reel by hand — layers on top of any Motion (grabbing pauses it, releasing snaps to the nearest card). No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep flowing after releasing a drag (Drag to spin).', 'fw' ) ),
	) ),
	'group_hr_reel' => array( 'type' => 'group', 'options' => array(
		'card_size'    => $slider( __( 'Card Size (%)', 'fw' ), 18, 8, 40, 1, __( 'Thumbnail width as a % of the stage width.', 'fw' ) ),
		'card_spacing' => $slider( __( 'Card Spacing (%)', 'fw' ), 4, 0, 20, 0.5, __( 'Gap between the reel thumbnails, as a % of the card width.', 'fw' ) ),
		'active_scale' => $slider( __( 'Active Scale (%)', 'fw' ), 112, 100, 160, 1, __( 'How much the centre (active) card grows above the others.', 'fw' ) ),
		'active_lift'  => $slider( __( 'Active Lift (%)', 'fw' ), 8, 0, 30, 1, __( 'How far the active card lifts up, as a % of its height.', 'fw' ) ),
	) ),
	'group_hr_hero' => array( 'type' => 'group', 'options' => array(
		'hero_opacity' => $slider( __( 'Hero Opacity (%)', 'fw' ), 55, 0, 100, 1, __( 'How strongly the active card fills the stage as a background behind the reel. 0 hides it (just the stage colour shows).', 'fw' ) ),
		'ken_burns'    => $slider( __( 'Ken Burns (%)', 'fw' ), 60, 0, 100, 1, __( 'Slow zoom on the hero background — higher pushes in further over each card.', 'fw' ) ),
		'hold'         => $slider( __( 'Hold (%)', 'fw' ), 55, 0, 100, 1, __( 'How long the reel holds on each card before stepping to the next.', 'fw' ) ),
	) ),
	'group_hr_card' => array( 'type' => 'group', 'options' => array(
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '4-3', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 8, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the card width.', 'fw' ) ),
	) ),
	'group_hr_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Focus Slider — a horizontal (or vertical) auto-advancing slider where the card passing through the
 * centre is "focused" (scales up via Center Scale). Slide Portion sets the card spacing (low = cinematic
 * overlap, high = spread); Zigzag pushes off-centre cards alternately up/down. A flat 2D design. */
$focus_slider_opts = array(
	'group_fs_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 24, 5, 90, array( 'left' => __( 'Left / Up', 'fw' ), 'right' => __( 'Right / Down', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to slide', 'fw' ), 'yes', __( 'Let visitors grab and step the slider by hand — layers on top of any Motion (grabbing pauses it, releasing snaps to the nearest card). No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep flowing after releasing a drag (Drag to slide).', 'fw' ) ),
	) ),
	'group_fs_slider' => array( 'type' => 'group', 'options' => array(
		'axis'          => array( 'label' => __( 'Direction', 'fw' ), 'type' => 'select', 'value' => 'horizontal', 'choices' => array( 'horizontal' => __( 'Horizontal', 'fw' ), 'vertical' => __( 'Vertical', 'fw' ) ) ),
		'card_size'     => $slider( __( 'Card Size (%)', 'fw' ), 78, 20, 100, 1, __( 'Size of the FOCUSED (centre) card as a % of the stage (its height for Horizontal, its width for Vertical). The other cards shrink from this by Center Scale.', 'fw' ) ),
		'center_scale'  => $slider( __( 'Center Scale (%)', 'fw' ), 200, 100, 300, 1, __( 'How much bigger the centre (focused) card is than its neighbours. 200% = the neighbours are half its size.', 'fw' ) ),
		'slide_portion' => $slider( __( 'Slide Portion (%)', 'fw' ), 60, 20, 100, 1, __( 'Centre-to-centre spacing along the slide. Low = cards overlap (cinematic); high = spread apart.', 'fw' ) ),
		'card_spacing'  => $slider( __( 'Gap (%)', 'fw' ), 6, 0, 40, 0.5, __( 'Extra gap between cards, as a % of the card size.', 'fw' ) ),
		'hold'          => $slider( __( 'Hold (%)', 'fw' ), 50, 0, 100, 1, __( 'How long the slider holds on each card before advancing to the next.', 'fw' ) ),
	) ),
	'group_fs_zigzag' => array( 'type' => 'group', 'options' => array(
		'zigzag'      => $slider( __( 'Zigzag Offset (%)', 'fw' ), 0, 0, 100, 1, __( 'Shift off-centre cards perpendicular to the slide (up/down for Horizontal, left/right for Vertical). 0 = a straight line.', 'fw' ) ),
		'zigzag_mode' => array( 'label' => __( 'Zigzag Mode', 'fw' ), 'type' => 'select', 'value' => 'alternate', 'choices' => array( 'alternate' => __( 'Alternate (flip each step)', 'fw' ), 'fixed' => __( 'Fixed (by card)', 'fw' ) ) ),
	) ),
	'group_fs_card' => array( 'type' => 'group', 'options' => array(
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '1-1', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 8, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 20, 0.5, __( 'Inner frame around each image, as a % of the card size.', 'fw' ) ),
	) ),
	'group_fs_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Mosaic Marquee — a horizontal marquee of cards packed masonry-style (varied sizes), scrolling
 * sideways forever. Card Height sets the tile scale (fewer, bigger rows as it rises); Layout 1/2/3 are
 * three mosaic patterns. A flat 2D design. */
$mosaic_marquee_opts = array(
	'group_mm_motion' => array( 'type' => 'group', 'options' => array(
		'motion'        => $motion_picker( 'continuous', __( 'Continuous', 'fw' ), 24, 5, 90, array( 'left' => __( 'Left', 'fw' ), 'right' => __( 'Right', 'fw' ) ) ),
		'allow_drag'    => $switch( __( 'Drag to spin', 'fw' ), 'yes', __( 'Let visitors grab and scrub the marquee by hand — layers on top of any Motion (grabbing pauses it, releasing flings). No effect as a Section Background.', 'fw' ) ),
		'drag_momentum' => $switch( __( 'Drag Momentum', 'fw' ), 'yes', __( 'Keep scrolling after releasing a drag (Drag to spin).', 'fw' ) ),
	) ),
	'group_mm_mosaic' => array( 'type' => 'group', 'options' => array(
		'layout'      => array( 'label' => __( 'Layout', 'fw' ), 'type' => 'select', 'value' => '1', 'choices' => array( '1' => __( 'Layout 1', 'fw' ), '2' => __( 'Layout 2', 'fw' ), '3' => __( 'Layout 3', 'fw' ) ), 'desc' => __( 'Three different mosaic tile patterns.', 'fw' ) ),
		'card_height' => $slider( __( 'Card Height (%)', 'fw' ), 55, 10, 90, 1, __( 'Tile scale — higher makes fewer, bigger rows of cards; lower packs more, smaller tiles.', 'fw' ) ),
		'gap'         => $slider( __( 'Gap (%)', 'fw' ), 3, 0, 8, 0.5, __( 'Space between the mosaic tiles.', 'fw' ) ),
	) ),
	'group_mm_card' => array( 'type' => 'group', 'options' => array(
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '1-1', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 18, 0, 60, 1 ),
		'padding'       => $slider( __( 'Padding (%)', 'fw' ), 6, 0, 20, 0.5, __( 'Inset around the whole mosaic — the background shows in this margin and in the empty cells.', 'fw' ) ),
	) ),
	'group_mm_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'overflow' => $overflow_field, 'background' => $bg_field ) ),
);

/* Scroll Choreography — a single gallery that morphs through an ordered list of formations as the
 * visitor scrolls a pinned stage (stack in → form → reform → exit). It is intrinsically scroll-pinned
 * (view.php forces Scroll-scrub + Pin for this design), so it needs no Motion picker of its own. */
$choreo_formations = array(
	'stack'   => __( 'Stack', 'fw' ),
	'grid'    => __( 'Grid', 'fw' ),
	'ring'    => __( 'Ring (3D carousel)', 'fw' ),
	'fan'     => __( 'Fan', 'fw' ),
	'wall'    => __( 'Wall (tilted)', 'fw' ),
	'spiral'   => __( 'Spiral (helix)', 'fw' ),
	'wave'     => __( 'Wave', 'fw' ),
	'tunnel'   => __( 'Tunnel (depth)', 'fw' ),
	'carousel' => __( 'Carousel (focus row)', 'fw' ),
	'column'   => __( 'Column (vertical)', 'fw' ),
	'sphere'   => __( 'Sphere (ball)', 'fw' ),
	'coverflow' => __( 'Cover Flow', 'fw' ),
	'filmstrip' => __( 'Film Strip', 'fw' ),
	'orbit'    => __( 'Orbit', 'fw' ),
	'hero'     => __( 'Hero Reel', 'fw' ),
	'focus'    => __( 'Focus Slider', 'fw' ),
	'depthstack' => __( 'Depth Stack', 'fw' ),
	'mosaic'   => __( 'Mosaic', 'fw' ),
	'scatter'  => __( 'Scatter / Exit', 'fw' ),
);
/* Formations a design or child theme contributes (paired with window.upwGallery3d.registerChoreoForm in
 * JS) appear in the Steps picker through this filter — keep "scatter" last as the exit. */
$choreo_formations = apply_filters( 'fw_gallery_3d_choreo_formations', $choreo_formations );
$scroll_choreography_opts = array(
	'group_choreo' => array( 'type' => 'group', 'options' => array(
		'steps' => array(
			'type'          => 'addable-popup',
			'label'         => __( 'Steps', 'fw' ),
			'desc'          => __( 'The ordered looks the cards morph through — add a step per formation, drag to reorder. Each step HOLDS before morphing to the next, so the scene settles on every look. The first step is where the cards begin; add Scatter as the last step to fly them out.', 'fw' ),
			'template'      => '{{ print(formation) }}{{ if (hold && hold > 0) { print(" · hold " + hold + "%") } }}',
			'popup-title'   => __( 'Choreography step', 'fw' ),
			'popup-options' => array(
				'formation' => array(
					'type'    => 'select',
					'label'   => __( 'Formation', 'fw' ),
					'value'   => 'grid',
					'choices' => $choreo_formations,
					'desc'    => __( 'The arrangement the cards settle into for this step.', 'fw' ),
				),
				'hold'      => $slider( __( 'Hold (%)', 'fw' ), 15, 0, 80, 5, __( 'How long this look holds before morphing to the next step.', 'fw' ) ),
			),
			'value'         => array(
				array( 'formation' => 'stack',   'hold' => 8 ),
				array( 'formation' => 'grid',    'hold' => 22 ),
				array( 'formation' => 'ring',    'hold' => 22 ),
				array( 'formation' => 'sphere',  'hold' => 15 ),
				array( 'formation' => 'scatter', 'hold' => 0 ),
			),
		),
		'stagger'       => $slider( __( 'Cascade (%)', 'fw' ), 25, 0, 60, 5, __( 'Spread the morph across the cards so they move one after another instead of in lockstep. 0 = all together.', 'fw' ) ),
		'easing'        => array(
			'label'   => __( 'Easing', 'fw' ),
			'type'    => 'select',
			'value'   => 'smooth',
			'choices' => array(
				'smooth' => __( 'Smooth', 'fw' ),
				'back'   => __( 'Overshoot', 'fw' ),
				'out'    => __( 'Ease out', 'fw' ),
				'in'     => __( 'Ease in', 'fw' ),
				'linear' => __( 'Linear', 'fw' ),
			),
			'desc'    => __( 'The curve each formation morphs with. Overshoot gives a springy settle.', 'fw' ),
		),
		'scroll_length' => $slider( __( 'Scroll Length (screens)', 'fw' ), 3, 1, 6, 1, __( 'How many screen-heights the whole choreography spans. More formations want more length.', 'fw' ) ),
		'card_size'     => $slider( __( 'Card Size (%)', 'fw' ), 16, 8, 40, 1, __( 'Card width as a % of the stage width.', 'fw' ) ),
		'chain'         => array(
			'type'  => 'switch',
			'label' => __( 'Chain with adjacent galleries', 'fw' ),
			'value' => false,
			'desc'  => __( 'Connect this gallery to the Scroll-Choreography galleries next to it — stacked in the same column OR each in its own section, one after another — into ONE scroll sequence: each flies out as the next assembles in. Turn it on for every gallery you want in the chain.', 'fw' ),
		),
	) ),
	'group_choreo_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'background' => $bg_field ) ),
);

$options = array(

	/* ---------------------------------------------------------------- CONTENT */
	'tab_content' => array(
		'title'   => __( 'Content', 'fw' ),
		'type'    => 'tab',
		'options' => array(
			'group_content' => array(
				'type'    => 'group',
				'options' => array(
					/* Where the cards come from. The picker natively reveals only the chosen source's
					 * options: Media Library → the Images upload; Post Type → the query settings.
					 * (Pre-source saves kept images at the old flat `images` key — the view still falls
					 * back to it, per the user's call that no formal migration is needed pre-release.) */
					'source' => array(
						'type'         => 'multi-picker',
						'label'        => false,
						'desc'         => false,
						'show_borders' => false,
						'value'        => array( 'kind' => 'media' ),
						'picker'       => array(
							'kind' => array(
								'type'    => 'select',
								'label'   => __( 'Source', 'fw' ),
								'desc'    => __( 'Media Library = the images you pick below. Post Type = a post type\'s featured images build the cards automatically (and stay fresh as you publish) — set On Card Click to "Open Link" on the Style tab to link each card to its post.', 'fw' ),
								'choices' => array( 'media' => __( 'Media Library', 'fw' ), 'posts' => __( 'Post Type', 'fw' ) ),
							),
						),
						'choices' => array(
							'media' => array(
								'images' => array(
									'label'       => __( 'Images', 'fw' ),
									'desc'        => __( 'The cards of the 3D showcase. Add or reorder images.', 'fw' ),
									'help'        => __( 'Each image becomes a card in the 3D scene. Captions/alt read from the Media Library (see Caption Source on the Style tab), and the image\'s "Link URL" field is used when On Card Click is "Open Link".', 'fw' ),
									'type'        => 'multi-upload',
									'images_only' => true,
								),
							),
							'posts' => array(
								'post_type' => array(
									'label'   => __( 'Post Type', 'fw' ),
									'type'    => 'select',
									'value'   => isset( $pt_choices['post'] ) ? 'post' : key( $pt_choices ),
									'choices' => $pt_choices,
									'desc'    => __( 'Public post types with featured-image support — e.g. Portfolio appears here when that extension is active.', 'fw' ),
								),
								'count'   => array(
								'label' => __( 'Number of Cards', 'fw' ),
								'type'  => 'text',
								'value' => '12',
								'attr'  => array( 'inputmode' => 'numeric', 'pattern' => '[0-9]*', 'style' => 'width:90px' ),
								'desc'  => __( 'How many posts to pull (1–200). Only posts WITH a featured image become cards. Each design lays the pool out its own way — the Panorama Wall happily cycles hundreds.', 'fw' ) ),
								'orderby' => array(
									'label'   => __( 'Order', 'fw' ),
									'type'    => 'select',
									'value'   => 'date_desc',
									'choices' => array(
										'date_desc'  => __( 'Newest first', 'fw' ),
										'date_asc'   => __( 'Oldest first', 'fw' ),
										'title'      => __( 'Title (A–Z)', 'fw' ),
										'menu_order' => __( 'Menu order', 'fw' ),
										'rand'       => __( 'Random', 'fw' ),
									),
								),
							),
						),
					),
				),
			),
		),
	),

	/* ---------------------------------------------------------------- DESIGN (inline picker) */
	'tab_design' => array(
		'title'   => __( 'Design', 'fw' ),
		'type'    => 'tab',
		'options' => array(
			/* Live in-modal preview of the picked design (placeholder cards; front end is final). */
			'design_preview' => array(
				'type'  => 'gallery-3d-preview',
				'label' => false,
				'desc'  => false,
			),
			'design_settings' => array(
				'type'         => 'multi-picker',
				'label'        => false,
				'desc'         => false,
				'show_borders' => false,
				'value'        => array( 'design' => 'carousel_ring' ),
				'picker'       => array(
					'design' => array(
						'type'    => 'image-picker',
						'label'   => __( 'Design', 'fw' ),
						'desc'    => __( 'Pick a 3D layout — its controls appear below. Hover a tile to preview it larger.', 'fw' ),
						'help'    => __( 'Each design is a self-contained 3D scene with its own controls.', 'fw' ),
						'value'   => 'carousel_ring',
						'layout'  => 'tabs',
						'choices' => $design_tiles,
					),
				),
				'choices' => array_merge( array(
					'scroll_choreography' => $scroll_choreography_opts,
					'carousel_ring' => $carousel_ring_opts,
					'panorama_wall'     => $panorama_wall_opts,
					'card_sphere'      => $card_sphere_opts,
					'orbit_globe'      => $orbit_globe_opts,
					'sphere_cascade'   => $sphere_cascade_opts,
					'totem_wall'       => $totem_wall_opts,
					'parallax_totem'   => $parallax_totem_opts,
					'card_tunnel'      => $card_tunnel_opts,
					'spiral_stream'    => $spiral_stream_opts,
					'depth_stack'      => $depth_stack_opts,
					'tilt_deck'        => $tilt_deck_opts,
					'iso_orbit'        => $iso_orbit_opts,
					'iso_stack'        => $iso_stack_opts,
					'iso_cascade'      => $iso_cascade_opts,
					'iso_fan'          => $iso_fan_opts,
					'orbit_bloom'      => $orbit_bloom_opts,
					'focus_orbit'      => $focus_orbit_opts,
					'dual_orbit'       => $dual_orbit_opts,
					'comet_orbit'      => $comet_orbit_opts,
					'orbit_swarm'      => $orbit_swarm_opts,
					'ring_tilt'        => $ring_tilt_opts,
					'gyro_orbit'       => $gyro_orbit_opts,
					'infinity_orbit'   => $infinity_orbit_opts,
					'vortex'           => $vortex_opts,
					'pendulum_orbit'   => $pendulum_orbit_opts,
					'moon_orbit'       => $moon_orbit_opts,
					'tiered_orbit'     => $tiered_orbit_opts,
					'wave_grid'        => $wave_grid_opts,
					'flip_grid'        => $flip_grid_opts,
					'magnet_grid'      => $magnet_grid_opts,
					'blind_reveal'     => $blind_reveal_opts,
					'iris_wipe'        => $iris_wipe_opts,
					'tile_dissolve'    => $tile_dissolve_opts,
					'spotlight_wall'   => $spotlight_wall_opts,
					'focus_pull'       => $focus_pull_opts,
					'loupe'            => $loupe_opts,
					'card_reel'        => $card_reel_opts,
					'film_strip'       => $film_strip_opts,
					'wheel_carousel'   => $wheel_carousel_opts,
					'cover_flow'       => $cover_flow_opts,
					'carousel_flow'    => $carousel_flow_opts,
					'hero_reel'        => $hero_reel_opts,
					'focus_slider'     => $focus_slider_opts,
					'mosaic_marquee'   => $mosaic_marquee_opts,
					'photo_scatter'    => $photo_scatter_opts,
					'card_stack'       => $card_stack_opts,
					'card_fan'         => $card_fan_opts,
					'shuffle_deck'     => $shuffle_deck_opts,
					'polaroid_drop'    => $polaroid_drop_opts,
					'device_cycler'    => $device_cycler_opts,
					'phone_lineup'     => $phone_lineup_opts,
					'window_stack'     => $window_stack_opts,
					'device_trio'      => $device_trio_opts,
					'webgl_mirror_ring' => $webgl_mirror_ring_opts,
					'webgl_depth_field' => $webgl_depth_field_opts,
					'webgl_wave_wall'   => $webgl_wave_wall_opts,
				), $soon_choices ),
			),
		),
	),

	/* ---------------------------------------------------------------- STYLE (shared) */
	'tab_style' => array(
		'title'   => __( 'Style', 'fw' ),
		'type'    => 'tab',
		'options' => array(
			'group_background' => array(
				'type'    => 'group',
				'options' => array(
					'as_background' => function_exists( 'sc_section_background_field' )
						? sc_section_background_field( array(
							'desc' => __( 'Fill the parent Section with the 3D scene and sit behind its content — the Section\'s own elements are lifted on top. Stage Height no longer applies; give the Section a min-height. In background mode the scene always auto-animates (it\'s non-interactive, so the Motion setting is ignored).', 'fw' ),
						) )
						: array(
							'type'         => 'switch',
							'label'        => __( 'Use as Section Background', 'fw' ),
							'value'        => 'no',
							'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
							'left-choice'  => array( 'value' => 'no', 'label' => __( 'No', 'fw' ) ),
						),
				),
			),
			'group_entrance' => array(
				'type'    => 'group',
				'options' => array(
					/* Built-in reveal that works on EVERY design. Unlike a generic Scroll Reveal (which can't
					 * touch 3D cards — the design rewrites each card's transform every frame), this rides on
					 * the inner content layer the layout never touches, so it composes with the 3D motion.
					 * One effect to start; the select is the seam for more. */
					'entrance' => array(
						'label'   => __( 'Entrance', 'fw' ),
						'type'    => 'select',
						'value'   => 'none',
						'desc'    => __( 'Reveal the images one by one as the gallery scrolls into view — works on every design, on top of the 3D motion. Pick how each image arrives: rise, scale, blur, slide, or flip.', 'fw' ),
						'choices' => array(
							'none'  => __( 'None', 'fw' ),
							'rise'  => __( 'Fade + Rise', 'fw' ),
							'scale' => __( 'Fade + Scale', 'fw' ),
							'blur'  => __( 'Fade + Blur', 'fw' ),
							'slide' => __( 'Slide In', 'fw' ),
							'flip'  => __( 'Flip In', 'fw' ),
						),
					),
					'entrance_stagger' => array(
						'type'       => 'slider',
						'label'      => __( 'Sequence spread', 'fw' ),
						'desc'       => __( 'How spread out the one-by-one cascade is. 0 = all together; higher = a longer, more staggered reveal.', 'fw' ),
						'value'      => 60,
						'properties' => array( 'min' => 0, 'max' => 90, 'step' => 5 ),
					),
					'entrance_distance' => array(
						'type'       => 'slider',
						'label'      => __( 'Rise / slide distance (px)', 'fw' ),
						'value'      => 40,
						'properties' => array( 'min' => 0, 'max' => 120, 'step' => 4 ),
					),
					'entrance_exit' => array(
						'type'         => 'switch',
						'label'        => __( 'Animate out on leave', 'fw' ),
						'desc'         => __( 'Fade and sink the images back out as the gallery scrolls off the top.', 'fw' ),
						'value'        => 'no',
						'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
						'left-choice'  => array( 'value' => 'no', 'label' => __( 'No', 'fw' ) ),
					),
					'chain' => array(
						'type'         => 'switch',
						'label'        => __( 'Chain with adjacent galleries', 'fw' ),
						'desc'         => __( 'On Scroll-scrub + Pin: connect this gallery to the adjacent chained galleries — of any design — into ONE pinned scroll sequence, each flying out as the next assembles in. Tick it on every gallery you want linked; they can sit in one column or in separate sections.', 'fw' ),
						'value'        => 'no',
						'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
						'left-choice'  => array( 'value' => 'no', 'label' => __( 'No', 'fw' ) ),
					),
				),
			),
			'group_card_style' => array(
				'type'    => 'group',
				'options' => array(
					'box_style' => function_exists( 'sc_card_box_style_field' )
						? sc_card_box_style_field( array( 'desc' => __( 'Apply a reusable Box Preset (border / fill / hover) to each card. Manage presets in Theme Settings → Components → Box Presets.', 'fw' ) ) )
						: array( 'type' => 'text', 'label' => __( 'Box Style', 'fw' ) ),
					'shadow' => array(
						'type'  => 'box-shadow',
						'label' => __( 'Card Shadow', 'fw' ),
						'value' => array( 'x' => 0, 'y' => 6, 'blur' => 16, 'spread' => -4, 'color' => 'rgba(0,0,0,0.35)', 'inset' => false ),
					),
				),
			),
			'group_captions' => array(
				'type'    => 'group',
				'options' => array(
					'captions' => array(
						'label'   => __( 'Captions', 'fw' ),
						'type'    => 'select',
						'value'   => 'none',
						'choices' => array( 'none' => __( 'None', 'fw' ), 'hover' => __( 'Overlay on Hover', 'fw' ), 'below' => __( 'Below the Image', 'fw' ) ),
					),
					'caption_source' => array(
						'label'   => __( 'Caption Source', 'fw' ),
						'type'    => 'select',
						'value'   => 'caption',
						'choices' => array( 'caption' => __( 'Image Caption', 'fw' ), 'title' => __( 'Image Title', 'fw' ), 'alt' => __( 'Alt Text', 'fw' ), 'description' => __( 'Description', 'fw' ) ),
					),
					/* On Card Click as a multi-picker: each action reveals only its own settings. NEW key
					 * (`click`) — the legacy flat `click_action` scalar stays honoured as a view fallback,
					 * so no value-shape migration is needed. */
					'click' => array(
						'type'         => 'multi-picker',
						'label'        => false,
						'desc'         => false,
						'show_borders' => false,
						'value'        => array( 'action' => 'none' ),
						'picker'       => array(
							'action' => array(
								'type'    => 'select',
								'label'   => __( 'On Card Click', 'fw' ),
								'desc'    => __( 'Open Lightbox shows the full image in the shared gallery lightbox. Open Link follows each card\'s link — its post\'s page with the Post Type source, or the image\'s "Link URL" field in the Media Library. Off by default — a 3D gallery is often decorative.', 'fw' ),
								'choices' => array(
									'lightbox' => __( 'Open Lightbox', 'fw' ),
									'link'     => __( 'Open Link', 'fw' ),
									'none'     => __( 'Do Nothing', 'fw' ),
								),
							),
						),
						/* No per-action reveals yet: Open Link's URL AND its "open in a new tab" checkbox both
						 * live on the image itself (the Media Library fields), so one gallery can freely mix
						 * internal and external links. External hosts always open a new tab automatically. */
						'choices' => array(),
					),
				),
			),
		),
	),

	/* ---------------------------------------------------------------- ANIMATIONS + ADVANCED */
	'tab_animation' => array(
		'title'   => __( 'Animations', 'fw' ),
		'type'    => 'tab',
		'options' => function_exists( 'sc_get_animation_fields' ) ? sc_get_animation_fields() : array(),
	),
	'tab_advanced' => array(
		'title'   => __( 'Advanced', 'fw' ),
		'type'    => 'tab',
		'options' => array(
			'advanced_settings' => array(
				'type'    => 'group',
				'options' => function_exists( 'sc_get_advanced_tab' ) ? sc_get_advanced_tab() : array(),
			),
		),
	),
);
