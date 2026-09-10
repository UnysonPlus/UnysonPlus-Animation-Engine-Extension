<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Cover Flow — the classic album cover flow (Carousel & Flow family). The centre
 * card faces you; the immediate neighbours tilt to Side Tilt and recede, and further cards stack
 * compressed behind them. Flows either horizontally OR vertically (Orientation) — this one design
 * covers both of the reference's "Cover Flow" and "Cover Flow Vertical". Continuous, or "Stop at centre"
 * snaps each card to the middle. gallery-3d.js (initFlow, mode 'cover') places the cards.
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
$dir    = ( $motion_dir === 'backward' ) ? -1 : 1;
$hover  = $motion_hover;
$axis   = ( $dp( 'orientation', 'horizontal' ) === 'vertical' ) ? 'v' : 'h';
$csize  = max( 6, min( 90, (float) $dp( 'card_size', 46 ) ) );
$tilt   = max( 0, min( 90, (float) $dp( 'side_tilt', 40 ) ) );
$gap    = max( 0, min( 40, (float) $dp( 'gap', 4 ) ) );
$snap   = ( $dp( 'snap', 'yes' ) === 'no' ) ? 'no' : 'yes';   // Cover Flow feels best snapping

$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = esc_attr( $allowdrag );
$attr['data-tdg-momentum']  = esc_attr( $momentum );
$attr['data-tdg-speed']     = esc_attr( $speed );
$attr['data-tdg-dir']       = esc_attr( $dir );
$attr['data-tdg-hover']     = esc_attr( $hover );
$attr['data-tdg-flow']      = 'cover';
$attr['data-tdg-axis']      = esc_attr( $axis );
$attr['data-tdg-card']      = esc_attr( $csize );
$attr['data-tdg-tilt']      = esc_attr( $tilt );
$attr['data-tdg-gap']       = esc_attr( $gap );
$attr['data-tdg-snap']      = esc_attr( $snap );
$attr['data-tdg-count']     = esc_attr( count( $items ) );

/* One card per image; pad small sets so both sides of the flow are populated. */
$slots = $items;
if ( count( $slots ) > 0 ) {
	while ( count( $slots ) < 7 ) { $slots = array_merge( $slots, $items ); }
}
$slots = array_slice( $slots, 0, 30 );
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<div class="tdg__flow">
			<?php foreach ( $slots as $item ) {
				echo '<div class="tdg__card">' . $render_card( $item ) . '</div>';
			} ?>
		</div>
	</div>
</div>
