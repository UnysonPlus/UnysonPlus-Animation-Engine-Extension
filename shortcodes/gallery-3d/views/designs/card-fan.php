<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Card Fan (Stack & Scatter) — the photos are held like a hand of playing cards,
 * fanned (Fan Spread) around a pivot below them (Pivot Depth). A highlight sweeps along the hand and back:
 * the card it reaches rises (Lift), straightens (Straighten) and comes forward while the rest dim (Dim).
 * With Follow Pointer, the visitor picks the card by pointing at it. Photos repeat to fill the hand.
 * gallery-3d.js (CardFan init) does the placement + motion. An original UnysonPlus design.
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
$attr['data-tdg-card']      = esc_attr( max( 4, min( 50, (float) $dp( 'card_size', 20 ) ) ) );
$count = (int) max( 3, min( 15, round( (float) $dp( 'cards', 7 ) ) ) );
$attr['data-tdg-speed']    = esc_attr( max( 1, min( 10, $motion_speed ) ) );
$attr['data-tdg-dir']      = esc_attr( $motion_dir === 'rtl' ? -1 : 1 );
$attr['data-tdg-spread']   = esc_attr( max( 10, min( 200, (float) $dp( 'spread', 70 ) ) ) );
$attr['data-tdg-pivot']    = esc_attr( max( 30, min( 400, (float) $dp( 'pivot', 120 ) ) ) );
$attr['data-tdg-lift']     = esc_attr( max( 0, min( 90, (float) $dp( 'lift', 35 ) ) ) );
$attr['data-tdg-straight'] = esc_attr( max( 0, min( 100, (float) $dp( 'straighten', 60 ) ) ) );
$attr['data-tdg-dim']      = esc_attr( max( 0, min( 90, (float) $dp( 'dim', 25 ) ) ) );
$attr['data-tdg-sway']     = esc_attr( max( 0, min( 20, (float) $dp( 'sway', 3 ) ) ) );
$attr['data-tdg-move']     = esc_attr( max( 10, min( 90, (float) $dp( 'move_time', 45 ) ) ) );
$attr['data-tdg-follow']   = ( empty( $as_bg ) && $dp( 'follow_pointer', 'yes' ) === 'yes' ) ? '1' : '0';

$n = count( $items );
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<div class="tdg__grid">
			<?php
			for ( $i = 0; $n && $i < $count; $i++ ) {
				echo '<div class="tdg__card"' . $card_style( $items[ $i % $n ] ) . '>' . $render_card( $items[ $i % $n ] ) . '</div>';
			} ?>
		</div>
	</div>
</div>
