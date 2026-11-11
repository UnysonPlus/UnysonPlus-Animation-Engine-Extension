<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Shuffle Deck (Stack & Scatter) — a loose deck of photos, the ones behind (Cards
 * Behind) peeking out up and to the side (Deck Offset) at slight angles (Jitter). The top photo slides out
 * to the side (Slide Distance, Slide Tilt), then tucks back in at the bottom while the others move up one
 * place. Direction: alternate sides, always right or always left.
 * gallery-3d.js (ShuffleDeck init) does the placement + motion. An original UnysonPlus design.
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
$attr['data-tdg-card']      = esc_attr( max( 6, min( 70, (float) $dp( 'card_size', 34 ) ) ) );
$attr['data-tdg-speed']  = esc_attr( max( 1, min( 12, $motion_speed ) ) );
$attr['data-tdg-side']   = $motion_dir === 'right' ? '1' : ( $motion_dir === 'left' ? '-1' : '0' );
$attr['data-tdg-behind'] = esc_attr( max( 1, min( 8, round( (float) $dp( 'behind', 4 ) ) ) ) );
$attr['data-tdg-offset'] = esc_attr( max( 0, min( 20, (float) $dp( 'offset', 4 ) ) ) );
$attr['data-tdg-jitter'] = esc_attr( max( 0, min( 20, (float) $dp( 'jitter', 4 ) ) ) );
$attr['data-tdg-slide']  = esc_attr( max( 30, min( 160, (float) $dp( 'slide', 75 ) ) ) );
$attr['data-tdg-tilt']   = esc_attr( max( 0, min( 45, (float) $dp( 'slide_tilt', 10 ) ) ) );
$attr['data-tdg-move']   = esc_attr( max( 15, min( 95, (float) $dp( 'move_time', 55 ) ) ) );
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<div class="tdg__grid">
			<?php foreach ( $items as $item ) {
				echo '<div class="tdg__card"' . $card_style( $item ) . '>' . $render_card( $item ) . '</div>';
			} ?>
		</div>
	</div>
</div>
