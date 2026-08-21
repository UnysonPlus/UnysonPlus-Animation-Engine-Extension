<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * 3D Gallery design: Wheel Carousel — cards fanned on a big Ferris-style wheel whose hub sits below
 * the frame, so only the top arc shows. Each card is rotated tangent to the wheel (upright at the top
 * = the focus, tilting away toward the sides) and the wheel steps around card by card. Overshoot lets
 * a step sail past and settle, Anticipation winds it back first, Hold pauses on each card. A flat (2D)
 * wheel. gallery-3d.js (initWheel) places the cards; here we emit one card per image (a loop).
 *
 * @var callable $dp, $render_card
 * @var array    $items, $attr
 * @var bool     $as_bg
 */

$drive     = $motion_mode;                                    // 'continuous' | 'scroll' | 'static'
$allowdrag = ( $dp( 'allow_drag', 'yes' ) === 'yes' || $motion_legacy_drag ) ? 1 : 0;
if ( ! empty( $as_bg ) ) { $drive = 'continuous'; $allowdrag = 0; }
$momentum = $dp( 'drag_momentum', 'yes' ) === 'yes' ? 1 : 0;
$speed  = max( 5, min( 90, $motion_speed ) );
$dir    = ( $motion_dir === 'ccw' ) ? -1 : 1;                 // clockwise = +1
$hover  = $motion_hover;
$csize  = max( 6, min( 90, (float) $dp( 'card_size', 40 ) ) );
$wsize  = max( 20, min( 160, (float) $dp( 'wheel_size', 70 ) ) );
$antic  = max( 0, min( 40, (float) $dp( 'anticipation', 0 ) ) );
$over   = max( 0, min( 30, (float) $dp( 'overshoot', 0 ) ) );
$hold   = max( 0, min( 60, (float) $dp( 'hold', 0 ) ) );

$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = esc_attr( $allowdrag );
$attr['data-tdg-momentum']  = esc_attr( $momentum );
$attr['data-tdg-speed']     = esc_attr( $speed );
$attr['data-tdg-dir']       = esc_attr( $dir );
$attr['data-tdg-hover']     = esc_attr( $hover );
$attr['data-tdg-card']      = esc_attr( $csize );
$attr['data-tdg-wheel']     = esc_attr( $wsize );
$attr['data-tdg-antic']     = esc_attr( $antic );
$attr['data-tdg-over']      = esc_attr( $over );
$attr['data-tdg-hold']      = esc_attr( $hold );
$attr['data-tdg-count']     = esc_attr( count( $items ) );

/* One card per image (a loop of unique frames); pad small sets so the wheel reads full. */
$slots = $items;
if ( count( $slots ) > 0 ) {
	while ( count( $slots ) < 9 ) { $slots = array_merge( $slots, $items ); }
}
$slots = array_slice( $slots, 0, 30 );
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<div class="tdg__wheel">
			<?php foreach ( $slots as $item ) {
				echo '<div class="tdg__card">' . $render_card( $item ) . '</div>';
			} ?>
		</div>
	</div>
</div>
