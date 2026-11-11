<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Wave Grid (Grid) — a Columns × Rows grid of photos lying on a tilted floor
 * (Floor Tilt), with a ripple running across it from the centre, the left edge or a corner (Wave Starts
 * From). Each photo rises on the crest (Wave Height) and tips with the slope of the wave; crests light up
 * (Crest Brightness). Wavelength sets the distance between crests. Photos repeat to fill the grid.
 * gallery-3d.js (initWaveGrid) does the placement + motion. An original UnysonPlus design.
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
$speed  = max( 2, min( 20, $motion_speed ) );
$dir    = ( $motion_dir === 'in' ) ? -1 : 1;
$hover  = $motion_hover;

$cols   = (int) max( 2, min( 12, round( (float) $dp( 'columns', 6 ) ) ) );
$rows   = (int) max( 1, min( 8, round( (float) $dp( 'rows', 4 ) ) ) );
$gap    = max( 0, min( 80, (float) $dp( 'gap', 14 ) ) );
$tilt   = max( 0, min( 80, (float) $dp( 'tilt', 55 ) ) );
$amp    = max( 0, min( 120, (float) $dp( 'amplitude', 35 ) ) );
$wl     = max( 1.5, min( 10, (float) $dp( 'wavelength', 3.5 ) ) );
$origin = array_search( (string) $dp( 'origin', 'center' ), array( 'center', 'left', 'corner' ), true );
$origin = ( $origin === false ) ? 0 : $origin;
$glow   = max( 100, min( 220, (float) $dp( 'focus_glow', 130 ) ) );
$csize  = max( 4, min( 40, (float) $dp( 'card_size', 11 ) ) );

$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = esc_attr( $allowdrag );
$attr['data-tdg-momentum']  = esc_attr( $momentum );
$attr['data-tdg-speed']     = esc_attr( $speed );
$attr['data-tdg-dir']       = esc_attr( $dir );
$attr['data-tdg-hover']     = esc_attr( $hover );
$attr['data-tdg-cols']      = esc_attr( $cols );
$attr['data-tdg-gap']       = esc_attr( $gap );
$attr['data-tdg-tilt']      = esc_attr( $tilt );
$attr['data-tdg-amp']       = esc_attr( $amp );
$attr['data-tdg-wl']        = esc_attr( $wl );
$attr['data-tdg-origin']    = esc_attr( $origin );
$attr['data-tdg-glow']      = esc_attr( $glow );
$attr['data-tdg-card']      = esc_attr( $csize );

$n = count( $items );
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<div class="tdg__grid">
			<?php
			for ( $i = 0; $n && $i < $cols * $rows; $i++ ) {
				echo '<div class="tdg__card">' . $render_card( $items[ $i % $n ] ) . '</div>';
			} ?>
		</div>
	</div>
</div>
