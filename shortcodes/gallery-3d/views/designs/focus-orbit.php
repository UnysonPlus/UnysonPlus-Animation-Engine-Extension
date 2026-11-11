<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Focus Orbit (Orbit) — a ring of billboarded photos orbiting a tilted disc;
 * the card reaching the front of the orbit pulls INTO the centre, lifts toward you and enlarges into a
 * hero (Hero Pull), then returns to the ring as the next card takes its place — a rolling central
 * feature. Front cards are brighter (Focus Brightness); the far arc dims (Depth Fade). gallery-3d.js
 * (initFocusOrbit) does the placement + motion; here we emit one card per image (images cycle).
 * An original UnysonPlus design.
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

$radius = max( 10, min( 55, (float) $dp( 'radius', 36 ) ) );
$tilt   = max( 0, min( 80, (float) $dp( 'tilt', 30 ) ) );
$hero   = max( 0, min( 100, (float) $dp( 'hero', 70 ) ) );
$glow   = max( 100, min( 240, (float) $dp( 'focus_glow', 150 ) ) );
$dfade  = max( 0, min( 100, (float) $dp( 'depth_fade', 45 ) ) );
$csize  = max( 4, min( 60, (float) $dp( 'card_size', 15 ) ) );

$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = esc_attr( $allowdrag );
$attr['data-tdg-momentum']  = esc_attr( $momentum );
$attr['data-tdg-speed']     = esc_attr( $speed );
$attr['data-tdg-dir']       = esc_attr( $dir );
$attr['data-tdg-hover']     = esc_attr( $hover );
$attr['data-tdg-radius']    = esc_attr( $radius );
$attr['data-tdg-tilt']      = esc_attr( $tilt );
$attr['data-tdg-hero']      = esc_attr( $hero );
$attr['data-tdg-glow']      = esc_attr( $glow );
$attr['data-tdg-dfade']     = esc_attr( $dfade );
$attr['data-tdg-card']      = esc_attr( $csize );

$ni = max( 1, count( $items ) );
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<div class="tdg__orbit">
			<?php foreach ( $items as $item ) {
				echo '<div class="tdg__card">' . $render_card( $item ) . '</div>';
			} ?>
		</div>
	</div>
</div>
