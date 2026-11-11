<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Pendulum Orbit (Orbit) — billboarded photos spread along an arc (Arc Span) of a
 * tilted orbit, and the arc swings back and forth like a pendulum (Swing), easing to a stop at each end,
 * instead of revolving. One full swing takes one Loop Duration. The card passing the front is the
 * brightest and largest. gallery-3d.js (initPendulumOrbit) does the placement + motion. An original
 * UnysonPlus design.
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
$speed  = max( 3, min( 40, $motion_speed ) );
$dir    = ( $motion_dir === 'ccw' ) ? -1 : 1;
$hover  = $motion_hover;

$radius = max( 15, min( 55, (float) $dp( 'radius', 36 ) ) );
$arc    = max( 40, min( 300, (float) $dp( 'arc', 130 ) ) );
$swing  = max( 0, min( 180, (float) $dp( 'swing', 45 ) ) );
$tilt   = max( 0, min( 80, (float) $dp( 'tilt', 58 ) ) );
$glow   = max( 100, min( 220, (float) $dp( 'focus_glow', 140 ) ) );
$dfade  = max( 0, min( 100, (float) $dp( 'depth_fade', 45 ) ) );
$csize  = max( 4, min( 60, (float) $dp( 'card_size', 8 ) ) );

$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = esc_attr( $allowdrag );
$attr['data-tdg-momentum']  = esc_attr( $momentum );
$attr['data-tdg-speed']     = esc_attr( $speed );
$attr['data-tdg-dir']       = esc_attr( $dir );
$attr['data-tdg-hover']     = esc_attr( $hover );
$attr['data-tdg-radius']    = esc_attr( $radius );
$attr['data-tdg-arc']       = esc_attr( $arc );
$attr['data-tdg-swing']     = esc_attr( $swing );
$attr['data-tdg-tilt']      = esc_attr( $tilt );
$attr['data-tdg-glow']      = esc_attr( $glow );
$attr['data-tdg-dfade']     = esc_attr( $dfade );
$attr['data-tdg-card']      = esc_attr( $csize );
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
