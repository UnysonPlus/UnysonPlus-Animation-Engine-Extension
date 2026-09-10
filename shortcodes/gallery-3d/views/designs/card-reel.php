<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Card Reel — a vertical cover-flow carousel (Carousel & Flow family). A single
 * column of cards wraps around a horizontal cylinder: the centre card sits flat and forward (the
 * focus); the ones above and below tilt back along the curve. The reel flows up/down continuously, or
 * "Stop at centre" snaps each card to the middle. The 3D Curve bends the cylinder. gallery-3d.js
 * (initReel) places the cards; here we emit one card per image (a loop of unique frames).
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
$dir    = ( $motion_dir === 'down' ) ? -1 : 1;                // up = +1
$hover  = $motion_hover;
$curve  = max( -100, min( 100, (float) $dp( 'curve_3d', -100 ) ) );
$csize  = max( 6, min( 60, (float) $dp( 'card_size', 22 ) ) );
$gap    = max( 0, min( 20, (float) $dp( 'gap', 1 ) ) );
$snap   = ( $dp( 'snap', 'no' ) === 'yes' ) ? 'yes' : 'no';

$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = esc_attr( $allowdrag );
$attr['data-tdg-momentum']  = esc_attr( $momentum );
$attr['data-tdg-speed']     = esc_attr( $speed );
$attr['data-tdg-dir']       = esc_attr( $dir );
$attr['data-tdg-hover']     = esc_attr( $hover );
$attr['data-tdg-curve']     = esc_attr( $curve );
$attr['data-tdg-card']      = esc_attr( $csize );
$attr['data-tdg-gap']       = esc_attr( $gap );
$attr['data-tdg-snap']      = esc_attr( $snap );
$attr['data-tdg-count']     = esc_attr( count( $items ) );

/* One card per image (a loop of unique frames); pad small sets so the column reads full. */
$slots = $items;
if ( count( $slots ) > 0 ) {
	while ( count( $slots ) < 7 ) { $slots = array_merge( $slots, $items ); }
}
$slots = array_slice( $slots, 0, 30 );
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<div class="tdg__reel">
			<?php foreach ( $slots as $item ) {
				echo '<div class="tdg__card">' . $render_card( $item ) . '</div>';
			} ?>
		</div>
	</div>
</div>
