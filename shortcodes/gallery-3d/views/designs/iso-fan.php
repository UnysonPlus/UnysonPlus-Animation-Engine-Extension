<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Iso Fan (Isometric) — photos spread along a flat arc (a fan) on an isometric
 * ground plane and sweep across it; the tile passing the centre-front of the fan lifts flat to face the
 * viewer and brightens (Focus Lift / Focus Brightness), then settles back as it sweeps on and recycles
 * at the far edge; tiles toward the edges dim (Depth Fade). gallery-3d.js (initIsoFan) does the
 * placement + motion; here we emit one tile per image (images cycle). An original UnysonPlus design
 * (a plain isometric arc built from scratch).
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
$speed  = max( 6, min( 50, $motion_speed ) );
$dir    = ( $motion_dir === 'right' ) ? -1 : 1;              // left = +1 (fan sweeps left)
$hover  = $motion_hover;

$reach  = max( 10, min( 60, (float) $dp( 'reach', 34 ) ) );
$arc    = max( 40, min( 175, (float) $dp( 'arc', 130 ) ) );
$tilt   = max( 20, min( 75, (float) $dp( 'iso_tilt', 58 ) ) );
$lift   = max( 0, min( 100, (float) $dp( 'lift', 70 ) ) );
$glow   = max( 100, min( 220, (float) $dp( 'focus_glow', 140 ) ) );
$dfade  = max( 0, min( 100, (float) $dp( 'depth_fade', 40 ) ) );
$csize  = max( 4, min( 60, (float) $dp( 'card_size', 16 ) ) );

$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = esc_attr( $allowdrag );
$attr['data-tdg-momentum']  = esc_attr( $momentum );
$attr['data-tdg-speed']     = esc_attr( $speed );
$attr['data-tdg-dir']       = esc_attr( $dir );
$attr['data-tdg-hover']     = esc_attr( $hover );
$attr['data-tdg-reach']     = esc_attr( $reach );
$attr['data-tdg-arc']       = esc_attr( $arc );
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
