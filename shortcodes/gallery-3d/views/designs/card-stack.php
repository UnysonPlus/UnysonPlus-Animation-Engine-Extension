<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * 3D Gallery design: Card Stack — a deck of image cards. The top card is featured; the rest peek
 * behind it (offset up + shrunk) forming a stack. The deck ADVANCES — the top card peels away and
 * the next comes forward — driven by scroll (best inside a pinned Scroll Story scene, where it
 * scrubs with the scene) or an automatic dwell timer. Poly's "use cases" deck of app windows.
 * Every card is a live, user-replaceable Media-Library image.
 *
 * @var callable $dp          per-design option reader
 * @var callable $render_card renders one card's inner markup
 * @var array    $items       normalized image items
 * @var array    $attr        wrapper attributes (with base class + style vars)
 * @var bool     $as_bg       used as a Section background
 */

$cycle_raw = $dp( 'cycle', null );
if ( is_array( $cycle_raw ) ) {
	$cycle_mode = ( isset( $cycle_raw['mode'] ) && in_array( $cycle_raw['mode'], array( 'scroll', 'auto', 'off' ), true ) ) ? $cycle_raw['mode'] : 'scroll';
	$co         = ( isset( $cycle_raw[ $cycle_mode ] ) && is_array( $cycle_raw[ $cycle_mode ] ) ) ? $cycle_raw[ $cycle_mode ] : array();
} else {
	$cycle_mode = 'scroll';
	$co         = array();
}
if ( ! empty( $as_bg ) ) { $cycle_mode = 'auto'; } // a Section background is non-interactive

$dwell   = isset( $co['dwell'] ) ? max( 1, min( 12, (float) $co['dwell'] ) ) : 2.5;
$exit    = in_array( $dp( 'exit', 'down' ), array( 'down', 'up', 'left', 'right' ), true ) ? $dp( 'exit', 'down' ) : 'down';
$behind  = max( 1, min( 6, (int) $dp( 'behind', 3 ) ) );
$offset  = max( 0, min( 14, (float) $dp( 'stack_offset', 5 ) ) );
$scale   = max( 0, min( 18, (float) $dp( 'stack_scale', 6 ) ) );
$card_w  = max( 20, min( 90, (float) $dp( 'card_size', 52 ) ) );

/* Soft cap — the whole deck is server-rendered (links/captions ride each card), so keep the DOM sane. */
if ( count( $items ) > 40 ) { $items = array_slice( $items, 0, 40 ); }

$attr['data-tdg-cycle']  = esc_attr( $cycle_mode );
$attr['data-tdg-dwell']  = esc_attr( $dwell );
$attr['data-tdg-exit']   = esc_attr( $exit );
$attr['data-tdg-behind'] = esc_attr( $behind );
$attr['data-tdg-offset'] = esc_attr( $offset );
$attr['data-tdg-scale']  = esc_attr( $scale );
$attr['data-tdg-count']  = esc_attr( count( $items ) );
$attr['style']           = ( isset( $attr['style'] ) ? rtrim( $attr['style'], '; ' ) . ';' : '' ) . '--tdg-card-w:' . $card_w . '%;';
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<div class="tdg__deck">
			<?php foreach ( $items as $i => $item ) { echo '<div class="tdg__card' . ( 0 === $i ? ' is-active' : '' ) . '">' . $render_card( $item ) . '</div>'; } ?>
		</div>
	</div>
</div>
