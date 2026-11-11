<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Loupe (Spotlight & Focus) — a Columns × Rows mosaic of small photos with a round magnifying
 * lens (Lens Size, Lens Ring Colour) gliding over it; inside the lens the photos show enlarged (Zoom). With
 * Follow Pointer, hovering moves the lens; when idle it drifts (Roam the whole mosaic / Stay near the
 * centre). The lens holds plain copies of the cards (built by JS, aria-hidden, not clickable — clicks reach
 * the real cards beneath). Photos repeat to fill the mosaic.
 * gallery-3d.js (Loupe init) does the placement + motion. An original UnysonPlus design.
 *
 * @var callable $dp, $render_card
 * @var array    $items, $attr
 * @var bool     $as_bg
 * @var string   $motion_mode, $motion_dir, $motion_hover
 * @var float    $motion_speed
 */

$drive = $motion_mode;
if ( ! empty( $as_bg ) ) { $drive = 'continuous'; }
$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = '0';
$attr['data-tdg-hover']     = esc_attr( $motion_hover );
$attr['data-tdg-card']      = esc_attr( max( 3, min( 40, (float) $dp( 'card_size', 10 ) ) ) );
$cols = (int) max( 2, min( 14, round( (float) $dp( 'columns', 6 ) ) ) );
$rows = (int) max( 1, min( 10, round( (float) $dp( 'rows', 4 ) ) ) );
$ring = (string) $dp( 'lens_ring', '#ffffff' );
if ( ! preg_match( '/^#[0-9a-fA-F]{3,8}$/', $ring ) ) { $ring = '#ffffff'; }
$attr['data-tdg-speed']  = esc_attr( max( 3, min( 40, $motion_speed ) ) );
$attr['data-tdg-roam']   = $motion_dir === 'center' ? '0.45' : '1';
$attr['data-tdg-cols']   = esc_attr( $cols );
$attr['data-tdg-gap']    = esc_attr( max( 0, min( 60, (float) $dp( 'gap', 8 ) ) ) );
$attr['data-tdg-lens']   = esc_attr( max( 8, min( 60, (float) $dp( 'lens_size', 24 ) ) ) );
$attr['data-tdg-zoom']   = esc_attr( max( 120, min( 500, (float) $dp( 'zoom', 220 ) ) ) );
$attr['data-tdg-follow'] = ( empty( $as_bg ) && $dp( 'follow_pointer', 'yes' ) === 'yes' ) ? '1' : '0';
$attr['style']           = trim( ( isset( $attr['style'] ) ? $attr['style'] : '' ) . ';--tdg-lens-ring:' . $ring, ';' );

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
