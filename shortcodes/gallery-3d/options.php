<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * 3D Gallery options. A popover Design picker (like the engine's other pickers) selects the 3D
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

/* ---- Design tiles for the popover picker ---- */
$ae       = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
$img_base = $ae ? $ae->get_declared_URI( '/shortcodes/gallery-3d/static/img/designs' ) : '';
// Category => designs (mirrors the animos grouping; the picker shows these as tabs).
$design_groups = array(
	'grp_3dp' => array(
		'label'   => __( '3D & Perspective', 'fw' ),
		'designs' => array(
			'carousel_ring' => __( 'Carousel Ring', 'fw' ),
			'panorama_wall'     => __( 'Panorama Wall', 'fw' ),
			'card_sphere'      => __( 'Card Sphere', 'fw' ),
			'orbit_globe'      => __( 'Orbit Globe', 'fw' ),
		),
	),
	'grp_stack' => array(
		'label'   => __( 'Stack & Scatter', 'fw' ),
		'designs' => array(
			'photo_scatter' => __( 'Photo Scatter', 'fw' ),
		),
	),
);
$mk_tile = function ( $dk, $dl ) use ( $img_base ) {
	$file = str_replace( '_', '-', $dk );
	return array(
		'small' => array( 'src' => $img_base . '/' . $file . '.svg', 'height' => 62, 'title' => $dl ),
		'large' => array( 'src' => $img_base . '/' . $file . '.svg', 'height' => 130 ),
		'label' => $dl,
	);
};
$design_tiles = array();
foreach ( $design_groups as $gk => $g ) {
	$grp = array();
	foreach ( $g['designs'] as $dk => $dl ) { $grp[ $dk ] = $mk_tile( $dk, $dl ); }
	$design_tiles[ $gk ] = array( 'label' => $g['label'], 'choices' => $grp );
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
	'group_ss_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'background' => $bg_field ) ),
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
	'group_sw_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'background' => $bg_field ) ),
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
	'group_cg_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'background' => $bg_field ) ),
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
	'group_og_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'background' => $bg_field ) ),
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
					'label'   => __( 'Shuffle', 'fw' ),
					'desc'    => __( 'How the scatter cycles to the next set of photos (when there are more images than Cards per Set). As a Section Background it always shuffles automatically.', 'fw' ),
					'choices' => array(
						'auto'  => __( 'Automatically', 'fw' ),
						'click' => __( 'On click', 'fw' ),
						'off'   => __( 'Never (static scatter)', 'fw' ),
					),
				),
			),
			'choices' => array(
				'auto' => array(
					'dwell'       => $slider( __( 'Dwell (s)', 'fw' ), 6, 2, 20, 0.5, __( 'How long each set rests on the table before the next sweeps in.', 'fw' ) ),
					'hover_pause' => $switch( __( 'Pause on Hover', 'fw' ), 'yes', __( 'Hold the current set while the visitor hovers the scatter.', 'fw' ) ),
				),
			),
		),
		'from' => array(
			'label'   => __( 'Glide in from', 'fw' ),
			'type'    => 'select',
			'value'   => 'edges',
			'choices' => array(
				'edges'  => __( 'All edges', 'fw' ),
				'top'    => __( 'Top', 'fw' ),
				'sides'  => __( 'Left & right', 'fw' ),
				'random' => __( 'Random', 'fw' ),
			),
		),
		'exit' => array(
			'label'   => __( 'How a set leaves', 'fw' ),
			'type'    => 'select',
			'desc'    => __( 'How the current photos clear before the next set glides in. Sweep flies them off the edges; Gather collects them into a pile at the centre; Fade dissolves them in place.', 'fw' ),
			'value'   => 'sweep',
			'choices' => array(
				'sweep'  => __( 'Sweep off the edges', 'fw' ),
				'gather' => __( 'Gather into a pile', 'fw' ),
				'fade'   => __( 'Fade in place', 'fw' ),
			),
		),
	) ),
	'group_ps_scatter' => array( 'type' => 'group', 'options' => array(
		'visible'       => $slider( __( 'Cards per Set', 'fw' ), 9, 3, 16, 1, __( 'How many photos rest on the table at once. Extra images form the next sets.', 'fw' ) ),
		'rotation'      => $slider( __( 'Rotation Range (°)', 'fw' ), 12, 0, 35, 1, __( 'Each photo settles at a random tilt within this range — 0 lays them all straight.', 'fw' ) ),
		'size_variance' => $slider( __( 'Size Variance (%)', 'fw' ), 30, 0, 60, 1, __( 'How much the photo sizes differ — 0 makes every photo the same size.', 'fw' ) ),
		'spread'        => $slider( __( 'Spread (%)', 'fw' ), 90, 50, 100, 1, __( 'How much of the stage the scatter uses — lower gathers the pile toward the middle.', 'fw' ) ),
	) ),
	'group_ps_card' => array( 'type' => 'group', 'options' => array(
		'card_size'     => $slider( __( 'Card Size (%)', 'fw' ), 18, 8, 40, 1, __( 'Base photo width as a % of the stage width (Size Variance plays around it).', 'fw' ) ),
		'card_ratio'    => array( 'label' => __( 'Card Ratio', 'fw' ), 'type' => 'select', 'value' => '3-4', 'choices' => $ratio_choices ),
		'corner_radius' => $slider( __( 'Corner Radius (px)', 'fw' ), 4, 0, 60, 1 ),
		'padding'       => $slider( __( 'Card Padding (%)', 'fw' ), 0, 0, 30, 0.5, __( 'Inner frame around each image — pair with a white Box Preset for the polaroid look.', 'fw' ) ),
	) ),
	'group_ps_frame' => array( 'type' => 'group', 'options' => array( 'height' => $height_field, 'background' => $bg_field ) ),
);

/* Post types offered by the Post Type source: public + featured-image support, built dynamically so
 * e.g. Portfolio simply appears when that extension is active (no hard dependency). */
$pt_choices = array();
foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt_obj ) {
	if ( 'attachment' === $pt_obj->name || ! post_type_supports( $pt_obj->name, 'thumbnail' ) ) { continue; }
	$pt_choices[ $pt_obj->name ] = $pt_obj->labels->name;
}
if ( empty( $pt_choices ) ) { $pt_choices = array( 'post' => __( 'Posts', 'fw' ) ); }

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
				'choices' => array(
					'carousel_ring' => $carousel_ring_opts,
					'panorama_wall'     => $panorama_wall_opts,
					'card_sphere'      => $card_sphere_opts,
					'orbit_globe'      => $orbit_globe_opts,
					'photo_scatter'    => $photo_scatter_opts,
				),
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
