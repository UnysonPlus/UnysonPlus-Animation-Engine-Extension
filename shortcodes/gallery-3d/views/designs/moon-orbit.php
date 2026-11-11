<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Moon Orbit (Orbit) — a solar system of photos. The first few photos (Planets)
 * are large cards orbiting the main ring of a tilted disc; every other photo is dealt to them as a
 * smaller "moon" (Moon Size) circling its planet faster (Moon Speed) at Moon Orbit distance — orbits
 * within orbits. gallery-3d.js (initMoonOrbit) does the placement + motion. An original UnysonPlus design.
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

$radius  = max( 15, min( 50, (float) $dp( 'radius', 33 ) ) );
$planets = (int) max( 2, min( 6, round( (float) $dp( 'planets', 4 ) ) ) );
$moonr   = max( 3, min( 20, (float) $dp( 'moon_radius', 8 ) ) );
$mspeed  = max( 100, min( 600, (float) $dp( 'moon_speed', 300 ) ) );
$msize   = max( 25, min( 90, (float) $dp( 'moon_size', 50 ) ) );
$tilt    = max( 0, min( 80, (float) $dp( 'tilt', 55 ) ) );
$glow    = max( 100, min( 220, (float) $dp( 'focus_glow', 130 ) ) );
$dfade   = max( 0, min( 100, (float) $dp( 'depth_fade', 45 ) ) );
$csize   = max( 4, min( 60, (float) $dp( 'card_size', 11 ) ) );

$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = esc_attr( $allowdrag );
$attr['data-tdg-momentum']  = esc_attr( $momentum );
$attr['data-tdg-speed']     = esc_attr( $speed );
$attr['data-tdg-dir']       = esc_attr( $dir );
$attr['data-tdg-hover']     = esc_attr( $hover );
$attr['data-tdg-radius']    = esc_attr( $radius );
$attr['data-tdg-planets']   = esc_attr( $planets );
$attr['data-tdg-moonr']     = esc_attr( $moonr );
$attr['data-tdg-mspeed']    = esc_attr( $mspeed );
$attr['data-tdg-msize']     = esc_attr( $msize );
$attr['data-tdg-tilt']      = esc_attr( $tilt );
$attr['data-tdg-glow']      = esc_attr( $glow );
$attr['data-tdg-dfade']     = esc_attr( $dfade );
$attr['data-tdg-card']      = esc_attr( $csize );

// Each planet needs a few moons: repeat the images up to 4 cards per planet (1 planet + 3 moons).
$cards = $items;
if ( count( $cards ) ) {
	$k = 0;
	while ( count( $cards ) < $planets * 4 ) { $cards[] = $items[ $k % count( $items ) ]; $k++; }
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
