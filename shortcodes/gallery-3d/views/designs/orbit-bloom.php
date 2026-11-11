<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Orbit Bloom (Orbit) — a ring of billboarded photos that slowly orbits a tilted
 * disc and BREATHES: it blooms from a tight central cluster out to a wide ring and back, the cards
 * fading and scaling up as they open (Bloom). Front cards (nearer the camera) are bigger and brighter
 * (Focus Brightness); the far arc dims (Depth Fade). gallery-3d.js (initOrbitBloom) does the placement
 * + motion; here we emit one card per image (images cycle). An original UnysonPlus design.
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

$radius = max( 10, min( 60, (float) $dp( 'radius', 32 ) ) );
$tilt   = max( 0, min( 80, (float) $dp( 'tilt', 58 ) ) );
$bloom  = max( 0, min( 100, (float) $dp( 'bloom', 70 ) ) );
$glow   = max( 100, min( 220, (float) $dp( 'focus_glow', 130 ) ) );
$dfade  = max( 0, min( 100, (float) $dp( 'depth_fade', 45 ) ) );
$csize  = max( 4, min( 60, (float) $dp( 'card_size', 16 ) ) );

$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = esc_attr( $allowdrag );
$attr['data-tdg-momentum']  = esc_attr( $momentum );
$attr['data-tdg-speed']     = esc_attr( $speed );
$attr['data-tdg-dir']       = esc_attr( $dir );
$attr['data-tdg-hover']     = esc_attr( $hover );
$attr['data-tdg-radius']    = esc_attr( $radius );
$attr['data-tdg-tilt']      = esc_attr( $tilt );
$attr['data-tdg-bloom']     = esc_attr( $bloom );
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
