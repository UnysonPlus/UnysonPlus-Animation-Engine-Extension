<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Tilt Deck (Isometric) — photos laid flat on an isometric ground plane in a
 * grid (Columns × Depth), marching diagonally across it. The tile passing through the focus point
 * lifts off the plane, rotates flat to face the viewer and brightens, then settles back as the next
 * one rises (Focus Lift / Focus Brightness); cards toward the back dim (Depth Fade). gallery-3d.js
 * (initTiltDeck) does the placement + motion; here we emit Columns × Depth cards (images cycle).
 * An original UnysonPlus design (the isometric look is a generic projection, built from scratch).
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
$speed  = max( 4, min( 40, $motion_speed ) );
$dir    = ( $motion_dir === 'backward' ) ? -1 : 1;            // forward = +1 (marches toward the viewer)
$hover  = $motion_hover;

$cols   = max( 2, min( 7, (int) $dp( 'columns', 4 ) ) );
$rows   = max( 3, min( 8, (int) $dp( 'depth_rows', 5 ) ) );
$gap    = max( 0, min( 60, (float) $dp( 'gap', 12 ) ) );
$tilt   = max( 20, min( 75, (float) $dp( 'iso_tilt', 58 ) ) );
$lift   = max( 0, min( 100, (float) $dp( 'lift', 60 ) ) );
$glow   = max( 100, min( 220, (float) $dp( 'focus_glow', 135 ) ) );
$dfade  = max( 0, min( 100, (float) $dp( 'depth_fade', 30 ) ) );
$csize  = max( 4, min( 60, (float) $dp( 'card_size', 16 ) ) );

$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = esc_attr( $allowdrag );
$attr['data-tdg-momentum']  = esc_attr( $momentum );
$attr['data-tdg-speed']     = esc_attr( $speed );
$attr['data-tdg-dir']       = esc_attr( $dir );
$attr['data-tdg-hover']     = esc_attr( $hover );
$attr['data-tdg-cols']      = esc_attr( $cols );
$attr['data-tdg-gap']       = esc_attr( $gap );
$attr['data-tdg-tilt']      = esc_attr( $tilt );
$attr['data-tdg-lift']      = esc_attr( $lift );
$attr['data-tdg-glow']      = esc_attr( $glow );
$attr['data-tdg-dfade']     = esc_attr( $dfade );
$attr['data-tdg-card']      = esc_attr( $csize );

$count = $cols * $rows;
$ni    = max( 1, count( $items ) );
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<div class="tdg__iso">
			<?php for ( $k = 0; $k < $count; $k++ ) {
				$item = $items[ $k % $ni ];
				echo '<div class="tdg__card">' . $render_card( $item ) . '</div>';
			} ?>
		</div>
	</div>
</div>
