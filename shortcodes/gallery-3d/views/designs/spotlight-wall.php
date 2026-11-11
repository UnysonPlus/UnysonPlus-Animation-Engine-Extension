<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Spotlight Wall (Spotlight & Focus) — a Columns × Rows grid of photos in shadow (Shadow
 * Brightness, Shadow Greyscale); a spotlight glides from photo to photo (Order: random or reading order;
 * Move Time of each stop spent gliding, the rest held) and the photos inside its pool (Spotlight Size) come
 * up to full colour and grow (Lift). With Follow Pointer, hovering takes the spotlight. Photos repeat to
 * fill the grid.
 * gallery-3d.js (SpotlightWall init) does the placement + motion. An original UnysonPlus design.
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
$attr['data-tdg-card']      = esc_attr( max( 3, min( 40, (float) $dp( 'card_size', 13 ) ) ) );
$cols = (int) max( 1, min( 12, round( (float) $dp( 'columns', 5 ) ) ) );
$rows = (int) max( 1, min( 8, round( (float) $dp( 'rows', 3 ) ) ) );
$attr['data-tdg-speed']  = esc_attr( max( 1, min( 12, $motion_speed ) ) );
$attr['data-tdg-dir']    = esc_attr( $motion_dir === 'back' ? -1 : 1 );
$attr['data-tdg-cols']   = esc_attr( $cols );
$attr['data-tdg-gap']    = esc_attr( max( 0, min( 80, (float) $dp( 'gap', 10 ) ) ) );
$attr['data-tdg-spot']   = esc_attr( max( 30, min( 300, (float) $dp( 'spot_size', 80 ) ) ) );
$attr['data-tdg-dim']    = esc_attr( max( 0, min( 100, (float) $dp( 'shadow', 35 ) ) ) );
$attr['data-tdg-grey']   = esc_attr( max( 0, min( 100, (float) $dp( 'greyscale', 100 ) ) ) );
$attr['data-tdg-lift']   = esc_attr( max( 0, min( 30, (float) $dp( 'lift', 8 ) ) ) );
$attr['data-tdg-move']   = esc_attr( max( 10, min( 90, (float) $dp( 'move_time', 35 ) ) ) );
$attr['data-tdg-order']  = $dp( 'order', 'random' ) === 'reading' ? '0' : '1';
$attr['data-tdg-follow'] = ( empty( $as_bg ) && $dp( 'follow_pointer', 'yes' ) === 'yes' ) ? '1' : '0';

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
