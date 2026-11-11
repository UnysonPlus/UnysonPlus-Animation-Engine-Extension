<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Infinity Orbit (Orbit) — billboarded photos loop a figure-eight (a lemniscate)
 * on a tilted plane (Plane Tilt); where the loops cross, one pass rises over the other (Cross Height) so
 * the ∞ reads in 3D. Loop Width / Loop Height shape the eight; Show Path draws it (gallery-3d.js adds the
 * SVG). gallery-3d.js (initInfinityOrbit) does the placement + motion. An original UnysonPlus design.
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

$radius = max( 15, min( 50, (float) $dp( 'radius', 38 ) ) );
$loop   = max( 40, min( 160, (float) $dp( 'loop', 100 ) ) );
$cross  = max( 0, min( 100, (float) $dp( 'cross', 40 ) ) );
$tilt   = max( 0, min( 80, (float) $dp( 'tilt', 50 ) ) );
$paths  = $dp( 'show_paths', 'yes' ) === 'yes' ? 1 : 0;
$glow   = max( 100, min( 220, (float) $dp( 'focus_glow', 130 ) ) );
$dfade  = max( 0, min( 100, (float) $dp( 'depth_fade', 45 ) ) );
$csize  = max( 4, min( 60, (float) $dp( 'card_size', 11 ) ) );

$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = esc_attr( $allowdrag );
$attr['data-tdg-momentum']  = esc_attr( $momentum );
$attr['data-tdg-speed']     = esc_attr( $speed );
$attr['data-tdg-dir']       = esc_attr( $dir );
$attr['data-tdg-hover']     = esc_attr( $hover );
$attr['data-tdg-radius']    = esc_attr( $radius );
$attr['data-tdg-loop']      = esc_attr( $loop );
$attr['data-tdg-cross']     = esc_attr( $cross );
$attr['data-tdg-tilt']      = esc_attr( $tilt );
$attr['data-tdg-paths']     = esc_attr( $paths );
$attr['data-tdg-glow']      = esc_attr( $glow );
$attr['data-tdg-dfade']     = esc_attr( $dfade );
$attr['data-tdg-card']      = esc_attr( $csize );

// An ∞ needs enough cards to trace both loops: repeat the images up to 14.
$cards = $items;
if ( count( $cards ) ) {
	$k = 0;
	while ( count( $cards ) < 14 ) { $cards[] = $items[ $k % count( $items ) ]; $k++; }
}
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<div class="tdg__orbit">
			<?php foreach ( $cards as $item ) {
				echo '<div class="tdg__card">' . $render_card( $item ) . '</div>';
			} ?>
		</div>
	</div>
</div>
