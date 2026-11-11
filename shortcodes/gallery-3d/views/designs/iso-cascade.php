<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Iso Cascade (Isometric) — photos descend a diagonal staircase across an
 * isometric ground plane: high and far at the back, stepping down and toward the viewer. The tile
 * reaching the front of the cascade lifts flat to face you and brightens (Focus Lift / Focus
 * Brightness), then recycles to the back; tiles toward the back dim (Depth Fade). gallery-3d.js
 * (initIsoCascade) does the placement + motion; here we emit one tile per image (images cycle).
 * An original UnysonPlus design (a plain isometric staircase built from scratch).
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
$speed  = max( 5, min( 45, $motion_speed ) );
$dir    = ( $motion_dir === 'up' ) ? -1 : 1;                 // down = +1 (cascade descends toward the viewer)
$hover  = $motion_hover;

$spread = max( 20, min( 100, (float) $dp( 'spread', 60 ) ) );
$rise   = max( 10, min( 100, (float) $dp( 'rise', 50 ) ) );
$tilt   = max( 20, min( 75, (float) $dp( 'iso_tilt', 58 ) ) );
$lift   = max( 0, min( 100, (float) $dp( 'lift', 70 ) ) );
$glow   = max( 100, min( 220, (float) $dp( 'focus_glow', 140 ) ) );
$dfade  = max( 0, min( 100, (float) $dp( 'depth_fade', 45 ) ) );
$csize  = max( 4, min( 60, (float) $dp( 'card_size', 17 ) ) );

$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = esc_attr( $allowdrag );
$attr['data-tdg-momentum']  = esc_attr( $momentum );
$attr['data-tdg-speed']     = esc_attr( $speed );
$attr['data-tdg-dir']       = esc_attr( $dir );
$attr['data-tdg-hover']     = esc_attr( $hover );
$attr['data-tdg-spread']    = esc_attr( $spread );
$attr['data-tdg-rise']      = esc_attr( $rise );
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
