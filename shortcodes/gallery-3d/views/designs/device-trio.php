<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Device Trio (Devices & Screens) — a laptop (Size) with a tablet and a phone
 * floating in front of it (Depth), each screen showing photos. The photos are dealt round the three
 * devices (laptop, tablet, phone, laptop, …); the screens change one device at a time (Loop Duration =
 * seconds between changes), the devices float gently (Float), and the group turns slightly toward the
 * visitor's pointer (Follow Pointer, up to Tilt) — or sways on its own (Direction: Gentle sway / Hold
 * still). Every screen is a real card: captions and click actions work on the photo showing.
 * gallery-3d.js (DeviceTrio init) does the placement + motion. An original UnysonPlus design.
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
$attr['data-tdg-card']      = esc_attr( max( 15, min( 90, (float) $dp( 'card_size', 48 ) ) ) );
$attr['data-tdg-speed']  = esc_attr( max( 1, min( 12, $motion_speed ) ) );
$attr['data-tdg-sway']   = $motion_dir === 'still' ? '0' : '1';
$attr['data-tdg-float']  = esc_attr( max( 0, min( 8, (float) $dp( 'float', 2 ) ) ) );
$attr['data-tdg-depth']  = esc_attr( max( 0, min( 100, (float) $dp( 'depth', 30 ) ) ) );
$attr['data-tdg-tilt']   = esc_attr( max( 0, min( 30, (float) $dp( 'tilt', 10 ) ) ) );
$attr['data-tdg-follow'] = ( empty( $as_bg ) && $dp( 'follow_pointer', 'yes' ) === 'yes' ) ? '1' : '0';

// Deal the photos round the devices; with fewer than three photos, repeat them so every screen has one.
$n     = count( $items );
$decks = array( array(), array(), array() );
for ( $k = 0; $n && $k < max( 3, $n ); $k++ ) { $decks[ $k % 3 ][] = $items[ $k % $n ]; }
$kinds = array( 'laptop', 'tablet', 'phone' );
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<div class="tdg__trio">
			<?php foreach ( $kinds as $d => $kind ) { ?>
			<div class="tdg__device tdg__device--<?php echo esc_attr( $kind ); ?>">
				<div class="tdg__screen">
					<?php foreach ( $decks[ $d ] as $j => $item ) {
						echo '<div class="tdg__card' . ( $j === 0 ? ' is-active' : '' ) . '">' . $render_card( $item ) . '</div>';
					} ?>
				</div>
			</div>
			<?php } ?>
		</div>
	</div>
</div>
