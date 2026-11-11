<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Comet Orbit (Orbit) — billboarded photos travel one elliptical orbit around a
 * glowing focus with orbital (Kepler) timing: they bunch up and drift slowly along the far end, then
 * whip past the focus, flaring larger and brighter (Flare). Eccentricity stretches the ellipse (0 = an
 * even circle). gallery-3d.js (initCometOrbit) does the placement + motion; here we emit one card per
 * image (images cycle) plus the optional focus glow. An original UnysonPlus design.
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

$radius = max( 15, min( 55, (float) $dp( 'radius', 33 ) ) );
$ecc    = max( 0, min( 85, (float) $dp( 'eccentricity', 62 ) ) );
$tilt   = max( 0, min( 80, (float) $dp( 'tilt', 45 ) ) );
$focus  = $dp( 'show_focus', 'yes' ) === 'yes';
$flare  = max( 0, min( 100, (float) $dp( 'flare', 70 ) ) );
$dfade  = max( 0, min( 100, (float) $dp( 'depth_fade', 40 ) ) );
$csize  = max( 4, min( 60, (float) $dp( 'card_size', 12 ) ) );

$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = esc_attr( $allowdrag );
$attr['data-tdg-momentum']  = esc_attr( $momentum );
$attr['data-tdg-speed']     = esc_attr( $speed );
$attr['data-tdg-dir']       = esc_attr( $dir );
$attr['data-tdg-hover']     = esc_attr( $hover );
$attr['data-tdg-radius']    = esc_attr( $radius );
$attr['data-tdg-ecc']       = esc_attr( $ecc );
$attr['data-tdg-tilt']      = esc_attr( $tilt );
$attr['data-tdg-flare']     = esc_attr( $flare );
$attr['data-tdg-dfade']     = esc_attr( $dfade );
$attr['data-tdg-card']      = esc_attr( $csize );
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<div class="tdg__orbit">
			<?php if ( $dp( 'show_paths', 'yes' ) === 'yes' ) { echo '<div class="tdg__track" aria-hidden="true"></div>'; } ?>
			<?php if ( $focus ) { echo '<div class="tdg__focus" aria-hidden="true"></div>'; } ?>
			<?php foreach ( $items as $item ) {
				echo '<div class="tdg__card">' . $render_card( $item ) . '</div>';
			} ?>
		</div>
	</div>
</div>
