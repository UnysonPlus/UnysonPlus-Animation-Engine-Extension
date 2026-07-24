<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * 3D Gallery design: Photo Scatter — photos scattered flat on a tabletop (the "desk" look).
 *
 * Cards settle at seeded random positions/rotations/sizes, glide in from the edges with a stagger,
 * dwell, then sweep out while the next set slides in (when the pool exceeds Cards per Set). Cycle
 * modes: auto (dwell timer, optional hover-pause) / on click / off. The whole pool renders
 * server-side (each card keeps its own link/caption/lightbox markup); the driver just chooses which
 * cards are on the table.
 *
 * @var callable $dp          per-design option reader
 * @var callable $render_card renders one card's inner markup
 * @var array    $items       normalized image items
 * @var array    $attr        wrapper attributes (with base class + style vars)
 */

$cycle_raw = $dp( 'cycle', null );
if ( is_array( $cycle_raw ) ) {
	$cycle_mode = ( isset( $cycle_raw['mode'] ) && in_array( $cycle_raw['mode'], array( 'auto', 'click', 'off' ), true ) ) ? $cycle_raw['mode'] : 'auto';
	$co         = ( isset( $cycle_raw[ $cycle_mode ] ) && is_array( $cycle_raw[ $cycle_mode ] ) ) ? $cycle_raw[ $cycle_mode ] : array();
} else {
	$cycle_mode = 'auto';
	$co         = array();
}
if ( ! empty( $as_bg ) ) { $cycle_mode = 'auto'; } // a Section background is non-interactive → auto-shuffle

$dwell    = isset( $co['dwell'] ) ? max( 2, min( 20, (float) $co['dwell'] ) ) : 6;
$hpause   = ( ! isset( $co['hover_pause'] ) || $co['hover_pause'] === 'yes' ) ? 1 : 0;
$from     = in_array( $dp( 'from', 'edges' ), array( 'edges', 'top', 'sides', 'random' ), true ) ? $dp( 'from', 'edges' ) : 'edges';
$exit     = in_array( $dp( 'exit', 'sweep' ), array( 'sweep', 'gather', 'fade' ), true ) ? $dp( 'exit', 'sweep' ) : 'sweep';
$visible  = max( 3, min( 16, (int) $dp( 'visible', 9 ) ) );
$rot      = max( 0, min( 35, (float) $dp( 'rotation', 12 ) ) );
$sizevar  = max( 0, min( 60, (float) $dp( 'size_variance', 30 ) ) );
$spread   = max( 50, min( 100, (float) $dp( 'spread', 90 ) ) );
$card     = max( 8, min( 40, (float) $dp( 'card_size', 18 ) ) );

/* Soft render cap — the whole pool is server-rendered (links/captions ride each card), so keep the
 * DOM sane; 60 cards = several sets of even the densest table. */
if ( count( $items ) > 60 ) { $items = array_slice( $items, 0, 60 ); }

$attr['data-tdg-cycle']   = esc_attr( $cycle_mode );
$attr['data-tdg-dwell']   = esc_attr( $dwell );
$attr['data-tdg-hpause']  = esc_attr( $hpause );
$attr['data-tdg-from']    = esc_attr( $from );
$attr['data-tdg-exit']    = esc_attr( $exit );
$attr['data-tdg-visible'] = esc_attr( $visible );
$attr['data-tdg-rot']     = esc_attr( $rot );
$attr['data-tdg-sizevar'] = esc_attr( $sizevar );
$attr['data-tdg-spread']  = esc_attr( $spread );
$attr['data-tdg-card']    = esc_attr( $card );
$attr['data-tdg-count']   = esc_attr( count( $items ) );
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<div class="tdg__plane">
			<?php foreach ( $items as $item ) { echo '<div class="tdg__card">' . $render_card( $item ) . '</div>'; } ?>
		</div>
	</div>
</div>
