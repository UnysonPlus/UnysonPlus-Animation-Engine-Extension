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
	'1-1'  => __( 'Square (1:1)', 'fw' ),
	'4-3'  => __( 'Landscape (4:3)', 'fw' ),
	'3-4'  => __( 'Portrait (3:4)', 'fw' ),
	'4-5'  => __( 'Portrait (4:5)', 'fw' ),
	'16-9' => __( 'Wide (16:9)', 'fw' ),
	'9-16' => __( 'Tall (9:16)', 'fw' ),
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
		),
	),
	'grp_device' => array(
		'label'   => __( 'Devices & Screens', 'fw' ),
		'designs' => array(
			'device_cycler' => __( 'Device Cycler', 'fw' ),
		),
	),
	/* Coming-soon FAMILIES — each is one badged "SOON" tile in its own category tab. These `soon_*`
	 * keys are deliberately NOT in the render registry (DEFAULTS / view whitelist), so selecting one
	 * can never render an empty gallery — the preview and the view both fall back to carousel_ring.
	 * Their reveal (below) is a "coming soon" note instead of controls. Tile art: static/img/designs/
	 * soon-*.svg (a muted family glyph + a SOON ribbon). */
	'grp_carousel'  => array( 'label' => __( 'Carousel & Flow', 'fw' ),   'designs' => array( 'card_reel' => __( 'Card Reel', 'fw' ), 'film_strip' => __( 'Film Strip', 'fw' ), 'wheel_carousel' => __( 'Wheel Carousel', 'fw' ), 'cover_flow' => __( 'Cover Flow', 'fw' ), 'carousel_flow' => __( 'Carousel Flow', 'fw' ), 'hero_reel' => __( 'Hero Reel', 'fw' ), 'focus_slider' => __( 'Focus Slider', 'fw' ), 'mosaic_marquee' => __( 'Mosaic Marquee', 'fw' ) ) ),
	'grp_grid'      => array( 'label' => __( 'Grid', 'fw' ),              'designs' => array( 'soon_grid' => __( 'Grid', 'fw' ) ),                     'soon' => true ),
	'grp_iso'       => array( 'label' => __( 'Isometric', 'fw' ),         'designs' => array( 'soon_isometric' => __( 'Isometric', 'fw' ) ),           'soon' => true ),
	'grp_orbit'     => array( 'label' => __( 'Orbit', 'fw' ),             'designs' => array( 'soon_orbit' => __( 'Orbit', 'fw' ) ),                   'soon' => true ),
	'grp_reveal'    => array( 'label' => __( 'Reveal & Wipe', 'fw' ),     'designs' => array( 'soon_reveal_wipe' => __( 'Reveal & Wipe', 'fw' ) ),     'soon' => true ),
	'grp_spotlight' => array( 'label' => __( 'Spotlight & Focus', 'fw' ), 'designs' => array( 'soon_spotlight' => __( 'Spotlight & Focus', 'fw' ) ), 'soon' => true ),
);
$mk_tile = function ( $dk, $dl ) use ( $img_base, $img_ver ) {
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
					'final_organized' => $switch( __( 'End on an organized grid', 'fw' ), 'yes', __( 'The last set arrives in a tidy, upright grid (entering from the bottom) instead of scattered — the "search results" resolution, exactly like Poly.', 'fw' ) ),
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
		'spread'        => $slider( __( 'Spread (%)', 'fw' ), 90, 50, 100, 1, __( 'How much of the stage the scatter uses — lower gathers the pile toward the middle; 95–100 fills edge-to-edge (outer photos bleed off the frame and are clipped, like Poly).', 'fw' ) ),
		'center_clear'  => $switch( __( 'Keep center clear', 'fw' ), 'no', __( 'Ring the photos around the middle so a centered heading placed over the scatter stays readable — best when the gallery shares a column with a title (the Poly per-batch look).', 'fw' ) ),
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
 * deck advances (top card peels away, next comes forward) on scroll or a timer. Poly's "use cases"
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
					'device_cycler'    => $device_cycler_opts,
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
