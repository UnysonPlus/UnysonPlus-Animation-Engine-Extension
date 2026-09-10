<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Device Cycler — a device frame (laptop / tablet / phone / browser) whose SCREEN
 * cycles through your images, one at a time, like a product showing off its view modes. Each image is
 * one "screen" state; the driver cross-fades (or slides / cuts) between them, driven by scroll (best
 * inside a pinned Scroll Story scene, where it scrubs with the scene) or an automatic dwell timer.
 * Every screen is a live, user-replaceable Media-Library image.
 *
 * @var callable $dp          per-design option reader
 * @var callable $render_card renders one card's inner markup
 * @var array    $items       normalized image items
 * @var array    $attr        wrapper attributes (with base class + style vars)
 * @var bool     $as_bg       used as a Section background
 */

$device = in_array( $dp( 'device', 'laptop' ), array( 'laptop', 'tablet', 'phone', 'browser', 'none' ), true ) ? $dp( 'device', 'laptop' ) : 'laptop';

$cycle_raw = $dp( 'cycle', null );
if ( is_array( $cycle_raw ) ) {
	$cycle_mode = ( isset( $cycle_raw['mode'] ) && in_array( $cycle_raw['mode'], array( 'scroll', 'auto', 'off' ), true ) ) ? $cycle_raw['mode'] : 'scroll';
	$co         = ( isset( $cycle_raw[ $cycle_mode ] ) && is_array( $cycle_raw[ $cycle_mode ] ) ) ? $cycle_raw[ $cycle_mode ] : array();
} else {
	$cycle_mode = 'scroll';
	$co         = array();
}
if ( ! empty( $as_bg ) ) { $cycle_mode = 'auto'; } // a Section background is non-interactive

$dwell      = isset( $co['dwell'] ) ? max( 1, min( 12, (float) $co['dwell'] ) ) : 3;
$transition = in_array( $dp( 'transition', 'fade' ), array( 'fade', 'slide_up', 'none' ), true ) ? $dp( 'transition', 'fade' ) : 'fade';
$dev_w      = max( 8, min( 100, (float) $dp( 'card_size', 62 ) ) );

/* Soft cap — screens are cross-faded, so a handful is the norm; keep the DOM sane. */
if ( count( $items ) > 24 ) { $items = array_slice( $items, 0, 24 ); }

$attr['data-tdg-device']     = esc_attr( $device );
$attr['data-tdg-cycle']      = esc_attr( $cycle_mode );
$attr['data-tdg-dwell']      = esc_attr( $dwell );
$attr['data-tdg-transition'] = esc_attr( $transition );
$attr['data-tdg-count']      = esc_attr( count( $items ) );

/* Orientation follows the device frame: a laptop / browser is always LANDSCAPE and a phone always
 * PORTRAIT — swap the chosen Screen Ratio if it's the wrong way round so the frame never looks absurd
 * (a portrait laptop). Tablet / No Frame keep the ratio as-is. */
$ratio_key = (string) $dp( 'card_ratio', '16-9' );
$rp = preg_match( '/^(\d+)-(\d+)$/', $ratio_key, $rm ) ? array( (int) $rm[1], (int) $rm[2] ) : array( 16, 9 );
$rw = $rp[0]; $rh = $rp[1];
if ( in_array( $device, array( 'laptop', 'browser' ), true ) && $rw < $rh ) { $rt = $rw; $rw = $rh; $rh = $rt; }
if ( 'phone' === $device && $rw > $rh ) { $rt = $rw; $rw = $rh; $rh = $rt; }

$attr['style'] = ( isset( $attr['style'] ) ? rtrim( $attr['style'], '; ' ) . ';' : '' ) . '--tdg-dev-w:' . $dev_w . '%;--tdg-ratio:' . $rw . ' / ' . $rh . ';';
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<div class="tdg__device tdg__device--<?php echo esc_attr( $device ); ?>">
			<div class="tdg__screen">
				<div class="tdg__plane">
					<?php foreach ( $items as $i => $item ) { echo '<div class="tdg__card' . ( $i === 0 ? ' is-active' : '' ) . '">' . $render_card( $item ) . '</div>'; } ?>
				</div>
			</div>
		</div>
	</div>
</div>
