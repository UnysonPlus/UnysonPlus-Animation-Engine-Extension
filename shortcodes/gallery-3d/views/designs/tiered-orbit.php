<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Tiered Orbit (Orbit) — 2–5 horizontal rings of billboarded photos (Tiers) stacked
 * at different heights (Tier Spacing), seen from slightly above (View Height); neighbouring tiers turn
 * opposite ways (Alternate Directions). Taper narrows the top (a tree) or the bottom (a tornado). Optional
 * ring paths (Show Ring Paths). Seen from OUTSIDE — unlike Panorama Wall, which wraps around the viewer.
 * gallery-3d.js (initTieredOrbit) does the placement + motion. An original UnysonPlus design.
 *
 * @var callable $dp, $render_card
 * @var array    $items, $attr
 * @var bool     $as_bg
 * @var string   $motion_mode, $motion_dir, $motion_hover
 * @var float    $motion_speed
 * @var bool     $motion_legacy_drag
 */

$drive     = $motion_mode;
$allowdrag = ( $dp( 'allow_drag', 'yes' ) === 'yes' || $motion_legacy_drag ) ? 1 : 0;
if ( ! empty( $as_bg ) ) { $drive = 'continuous'; $allowdrag = 0; }
$momentum = $dp( 'drag_momentum', 'yes' ) === 'yes' ? 1 : 0;
$speed  = max( 6, min( 60, $motion_speed ) );
$dir    = ( $motion_dir === 'ccw' ) ? -1 : 1;
$hover  = $motion_hover;

$radius = max( 15, min( 50, (float) $dp( 'radius', 28 ) ) );
$tiers  = (int) max( 2, min( 5, round( (float) $dp( 'tiers', 3 ) ) ) );
$gap    = max( 5, min( 40, (float) $dp( 'gap', 16 ) ) );
$taper  = max( -70, min( 70, (float) $dp( 'taper', 35 ) ) );
$elev   = max( 0, min( 45, (float) $dp( 'elevation', 22 ) ) );
$alt    = $dp( 'alternate', 'yes' ) === 'yes' ? 1 : 0;
$glow   = max( 100, min( 220, (float) $dp( 'focus_glow', 130 ) ) );
$dfade  = max( 0, min( 100, (float) $dp( 'depth_fade', 50 ) ) );
$csize  = max( 4, min( 60, (float) $dp( 'card_size', 8 ) ) );

$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = esc_attr( $allowdrag );
$attr['data-tdg-momentum']  = esc_attr( $momentum );
$attr['data-tdg-speed']     = esc_attr( $speed );
$attr['data-tdg-dir']       = esc_attr( $dir );
$attr['data-tdg-hover']     = esc_attr( $hover );
$attr['data-tdg-radius']    = esc_attr( $radius );
$attr['data-tdg-tiers']     = esc_attr( $tiers );
$attr['data-tdg-gap']       = esc_attr( $gap );
$attr['data-tdg-taper']     = esc_attr( $taper );
$attr['data-tdg-elev']      = esc_attr( $elev );
$attr['data-tdg-alt']       = esc_attr( $alt );
$attr['data-tdg-glow']      = esc_attr( $glow );
$attr['data-tdg-dfade']     = esc_attr( $dfade );
$attr['data-tdg-card']      = esc_attr( $csize );

// Each tier needs enough cards to read as a ring: repeat the images up to 7 per tier.
$cards = $items;
if ( count( $cards ) ) {
	$k = 0;
	while ( count( $cards ) < $tiers * 7 ) { $cards[] = $items[ $k % count( $items ) ]; $k++; }
}
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<div class="tdg__orbit">
			<?php
			if ( $dp( 'show_paths', 'yes' ) === 'yes' ) {
				echo str_repeat( '<div class="tdg__track" aria-hidden="true"></div>', $tiers );
			}
			foreach ( $cards as $item ) {
				echo '<div class="tdg__card">' . $render_card( $item ) . '</div>';
			} ?>
		</div>
	</div>
</div>
