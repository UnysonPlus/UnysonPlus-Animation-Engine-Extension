<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Gyro Orbit (Orbit) — an atom / gyroscope. The photos are dealt round 2–4 rings
 * (Rings), each ring on its own plane leaning by Ring Lean and fanned evenly round the vertical axis;
 * neighbouring rings run opposite ways around a glowing core (Show Core Glow) while the whole gyro
 * slowly turns (Gyro Spin). Optional ring paths (Show Ring Paths) are real 3D circles on the same planes.
 * gallery-3d.js (initGyroOrbit) does the placement + motion. An original UnysonPlus design.
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

$radius = max( 15, min( 50, (float) $dp( 'radius', 24 ) ) );
$rings  = (int) max( 2, min( 4, round( (float) $dp( 'rings', 3 ) ) ) );
$lean   = max( 0, min( 85, (float) $dp( 'lean', 62 ) ) );
$spin   = max( 0, min( 100, (float) $dp( 'spin', 35 ) ) );
$glow   = max( 100, min( 220, (float) $dp( 'focus_glow', 130 ) ) );
$dfade  = max( 0, min( 100, (float) $dp( 'depth_fade', 50 ) ) );
$csize  = max( 4, min( 60, (float) $dp( 'card_size', 9 ) ) );

$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = esc_attr( $allowdrag );
$attr['data-tdg-momentum']  = esc_attr( $momentum );
$attr['data-tdg-speed']     = esc_attr( $speed );
$attr['data-tdg-dir']       = esc_attr( $dir );
$attr['data-tdg-hover']     = esc_attr( $hover );
$attr['data-tdg-radius']    = esc_attr( $radius );
$attr['data-tdg-rings']     = esc_attr( $rings );
$attr['data-tdg-lean']      = esc_attr( $lean );
$attr['data-tdg-spin']      = esc_attr( $spin );
$attr['data-tdg-glow']      = esc_attr( $glow );
$attr['data-tdg-dfade']     = esc_attr( $dfade );
$attr['data-tdg-card']      = esc_attr( $csize );

// Every ring needs a few cards to read as a ring: repeat the images up to 5 per ring.
$cards = $items;
if ( count( $cards ) ) {
	$k = 0;
	while ( count( $cards ) < $rings * 5 ) { $cards[] = $items[ $k % count( $items ) ]; $k++; }
}
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<div class="tdg__orbit">
			<?php
			if ( $dp( 'show_paths', 'yes' ) === 'yes' ) {
				echo str_repeat( '<div class="tdg__track" aria-hidden="true"></div>', $rings );
			}
			if ( $dp( 'show_core', 'yes' ) === 'yes' ) { echo '<div class="tdg__focus" aria-hidden="true"></div>'; }
			foreach ( $cards as $item ) {
				echo '<div class="tdg__card">' . $render_card( $item ) . '</div>';
			} ?>
		</div>
	</div>
</div>
