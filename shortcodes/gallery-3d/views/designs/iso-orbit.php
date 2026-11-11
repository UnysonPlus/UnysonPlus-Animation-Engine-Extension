<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Iso Orbit (Isometric) — photos ride a ring lying flat on an isometric ground
 * plane and revolve around its centre; the tile at the near point of the orbit lifts off the plane,
 * rotates flat to face the viewer and brightens (Focus Lift / Focus Brightness), then settles back as
 * it carries on round; the far side dims (Depth Fade). gallery-3d.js (initIsoOrbit) does the placement
 * + motion; here we emit one tile per image. An original UnysonPlus design (a plain isometric ring).
 *
 * @var callable $dp, $render_card
 * @var array    $items, $attr
 * @var bool     $as_bg
 * @var string   $motion_mode, $motion_dir, $motion_hover
 * @var float    $motion_speed
 * @var bool     $motion_legacy_drag
 */

$drive     = $motion_mode;                                    // 'continuous' | 'scroll' | 'static'
$allowdrag = ( $dp( 'allow_drag', 'yes' ) === 'yes' || $motion_legacy_drag ) ? 1 : 0;
if ( ! empty( $as_bg ) ) { $drive = 'continuous'; $allowdrag = 0; }
$momentum = $dp( 'drag_momentum', 'yes' ) === 'yes' ? 1 : 0;
$speed  = max( 6, min( 60, $motion_speed ) );
$dir    = ( $motion_dir === 'ccw' ) ? -1 : 1;                 // cw = +1 (revolve clockwise on the floor)
$hover  = $motion_hover;

$radius = max( 8, min( 60, (float) $dp( 'radius', 30 ) ) );
$tilt   = max( 20, min( 75, (float) $dp( 'iso_tilt', 58 ) ) );
$lift   = max( 0, min( 100, (float) $dp( 'lift', 70 ) ) );
$glow   = max( 100, min( 220, (float) $dp( 'focus_glow', 140 ) ) );
$dfade  = max( 0, min( 100, (float) $dp( 'depth_fade', 35 ) ) );
$csize  = max( 4, min( 60, (float) $dp( 'card_size', 16 ) ) );

$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = esc_attr( $allowdrag );
$attr['data-tdg-momentum']  = esc_attr( $momentum );
$attr['data-tdg-speed']     = esc_attr( $speed );
$attr['data-tdg-dir']       = esc_attr( $dir );
$attr['data-tdg-hover']     = esc_attr( $hover );
$attr['data-tdg-radius']    = esc_attr( $radius );
$attr['data-tdg-tilt']      = esc_attr( $tilt );
$attr['data-tdg-lift']      = esc_attr( $lift );
$attr['data-tdg-glow']      = esc_attr( $glow );
$attr['data-tdg-dfade']     = esc_attr( $dfade );
$attr['data-tdg-card']      = esc_attr( $csize );

$ni = max( 1, count( $items ) );
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<div class="tdg__iso">
			<?php foreach ( $items as $item ) {
				echo '<div class="tdg__card">' . $render_card( $item ) . '</div>';
			} ?>
		</div>
	</div>
</div>
