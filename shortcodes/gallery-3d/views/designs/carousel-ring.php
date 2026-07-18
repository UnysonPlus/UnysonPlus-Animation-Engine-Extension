<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * 3D Gallery design: Carousel Ring — a rotating ring / cylinder of cards.
 *
 * @var callable $dp          per-design option reader
 * @var callable $render_card renders one card's inner markup
 * @var array    $items       normalized image items
 * @var array    $attr        wrapper attributes (with base class + style vars)
 */

$drive_raw = (string) $dp( 'drive', 'auto' );
$allowdrag = ( $dp( 'allow_drag', 'no' ) === 'yes' ) ? 1 : 0;
if ( $drive_raw === 'drag' ) { $drive_raw = 'static'; $allowdrag = 1; } // legacy "Drag" motion = Static base + Drag-to-spin
$drive    = in_array( $drive_raw, array( 'auto', 'scroll', 'static' ), true ) ? $drive_raw : 'auto';
if ( ! empty( $as_bg ) ) { $drive = 'auto'; $allowdrag = 0; } // a Section background is non-interactive → auto-rotate, no drag
$speed    = max( 3, min( 60, (float) $dp( 'speed', 16 ) ) );
$dir      = ( $dp( 'direction', 'left' ) === 'right' ) ? -1 : 1;
$hover_raw = (string) $dp( 'hover_behavior', '' );
if ( $hover_raw === '' ) { $hover_raw = ( $dp( 'pause_hover', 'yes' ) === 'yes' ) ? 'pause' : 'none'; } // legacy pause_hover
$hover    = in_array( $hover_raw, array( 'none', 'pause', 'slow' ), true ) ? $hover_raw : 'pause';
$momentum = $dp( 'drag_momentum', 'yes' ) === 'yes' ? 1 : 0;
$tilt     = max( -60, min( 60, (float) $dp( 'tilt', -28 ) ) );
$roll     = max( -45, min( 45, (float) $dp( 'roll', 0 ) ) );          // our extra "Diagonal Tilt"; off by default (animos has none)
$opening  = max( 0, min( 100, (float) $dp( 'ring_opening', 55 ) ) );
$ring     = max( 40, min( 140, (float) $dp( 'ring_size', 80 ) ) );
$spacing  = max( 60, min( 180, (float) $dp( 'spacing', 100 ) ) );      // our extra "Card Spacing"
$persp    = max( 8, min( 100, (float) $dp( 'perspective', 18 ) ) );
$backfade = max( 0, min( 100, (float) $dp( 'back_fade', 70 ) ) );
$card     = max( 6, min( 60, (float) $dp( 'card_size', 21 ) ) );

$attr['data-tdg-drive']    = esc_attr( $drive );
$attr['data-tdg-speed']    = esc_attr( $speed );
$attr['data-tdg-dir']      = esc_attr( $dir );
$attr['data-tdg-hover']    = esc_attr( $hover );
$attr['data-tdg-momentum'] = esc_attr( $momentum );
$attr['data-tdg-allowdrag'] = esc_attr( $allowdrag );
$attr['data-tdg-tilt']     = esc_attr( $tilt );
$attr['data-tdg-roll']     = esc_attr( $roll );
$attr['data-tdg-opening']  = esc_attr( $opening );
$attr['data-tdg-ring']     = esc_attr( $ring );
$attr['data-tdg-spacing']  = esc_attr( $spacing );
$attr['data-tdg-persp']    = esc_attr( $persp );
$attr['data-tdg-backfade'] = esc_attr( $backfade );
$attr['data-tdg-card']     = esc_attr( $card );
$attr['data-tdg-count']    = esc_attr( count( $items ) );
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<div class="tdg__ring">
			<?php foreach ( $items as $item ) { echo '<div class="tdg__card">' . $render_card( $item ) . '</div>'; } ?>
		</div>
	</div>
</div>
