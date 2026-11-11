<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Magnet Grid (Grid) — a face-on Columns × Rows grid that follows the pointer:
 * cards within Reach lift toward you (Lift), grow and turn to face it, and the whole grid leans toward it
 * (Grid Lean). With no pointer an invisible one drifts over the grid (Drift when idle; Roam / Stay near
 * the centre), so it is never still; Scroll-scrub moves that drifting pointer with the page. Photos repeat
 * to fill the grid. gallery-3d.js (initMagnetGrid) does the placement + motion. An original UnysonPlus
 * design.
 *
 * @var callable $dp, $render_card
 * @var array    $items, $attr
 * @var bool     $as_bg
 * @var string   $motion_mode, $motion_dir, $motion_hover
 * @var float    $motion_speed
 */

$drive = $motion_mode;
if ( ! empty( $as_bg ) ) { $drive = 'continuous'; }
$speed = max( 3, min( 40, $motion_speed ) );
$roam  = ( $motion_dir === 'center' ) ? 0.4 : 1;

$cols  = (int) max( 2, min( 12, round( (float) $dp( 'columns', 5 ) ) ) );
$rows  = (int) max( 1, min( 8, round( (float) $dp( 'rows', 3 ) ) ) );
$gap   = max( 0, min( 80, (float) $dp( 'gap', 14 ) ) );
$reach = max( 5, min( 60, (float) $dp( 'reach', 22 ) ) );
$lift  = max( 0, min( 150, (float) $dp( 'lift', 90 ) ) );
$lean  = max( 0, min( 30, (float) $dp( 'lean', 10 ) ) );
$glow  = max( 100, min( 220, (float) $dp( 'focus_glow', 130 ) ) );
$csize = max( 4, min( 40, (float) $dp( 'card_size', 12 ) ) );

$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = '0';
$attr['data-tdg-speed']     = esc_attr( $speed );
$attr['data-tdg-roam']      = esc_attr( $roam );
$attr['data-tdg-cols']      = esc_attr( $cols );
$attr['data-tdg-gap']       = esc_attr( $gap );
$attr['data-tdg-reach']     = esc_attr( $reach );
$attr['data-tdg-lift']      = esc_attr( $lift );
$attr['data-tdg-lean']      = esc_attr( $lean );
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
