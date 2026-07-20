<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * 3D Gallery design: Orbit Globe — cards distributed through a sphere VOLUME (Fibonacci points),
 * each BILLBOARDED (always facing the camera) rather than tangent to the surface like Card Sphere.
 * The whole cloud orbits; the stage perspective makes near cards big + far cards small, and Back
 * Fade dims the far side — a depth-of-field orbit. gallery-3d.js (initOrbit) does the point
 * distribution and per-frame positioning; here we only emit the right number of plain cards.
 *
 * Card COUNT scales inversely with Card Size (smaller cards = denser cloud) and down with Gap — the
 * formula MUST match preview.js. Images repeat via modulo to fill the count.
 *
 * @var callable $dp, $render_card
 * @var array    $items, $attr
 * @var bool     $as_bg
 */

$drive_raw = (string) $dp( 'drive', 'continuous' );
$allowdrag = ( $dp( 'allow_drag', 'no' ) === 'yes' ) ? 1 : 0;
if ( $drive_raw === 'drag' ) { $drive_raw = 'static'; $allowdrag = 1; } // legacy "Drag" motion = Static base + Drag-to-spin
$drive    = in_array( $drive_raw, array( 'continuous', 'scroll', 'static' ), true ) ? $drive_raw : 'continuous';
if ( ! empty( $as_bg ) ) { $drive = 'continuous'; $allowdrag = 0; }
$speed    = max( 5, min( 90, (float) $dp( 'speed', 20 ) ) );
$dir      = ( $dp( 'direction', 'left' ) === 'right' ) ? -1 : 1;
$hover_raw = (string) $dp( 'hover_behavior', '' );
$hover    = in_array( $hover_raw, array( 'none', 'pause', 'slow' ), true ) ? $hover_raw : 'slow';
$momentum = $dp( 'drag_momentum', 'yes' ) === 'yes' ? 1 : 0;
$globe    = max( 40, min( 95, (float) $dp( 'globe_size', 50 ) ) );
$card     = max( 8, min( 30, (float) $dp( 'card_size', 28 ) ) );
$gap      = max( 0.5, min( 8, (float) $dp( 'gap', 2.5 ) ) );
$backfade = max( 0, min( 90, (float) $dp( 'back_fade', 55 ) ) );
$tilt     = max( -45, min( 45, (float) $dp( 'tilt', 27 ) ) );

$n_cards = max( 14, min( 90, (int) round( 5 / ( $card / 100 ) / ( 1 + $gap / 100 ) ) ) );

$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-speed']     = esc_attr( $speed );
$attr['data-tdg-dir']       = esc_attr( $dir );
$attr['data-tdg-hover']     = esc_attr( $hover );
$attr['data-tdg-momentum']  = esc_attr( $momentum );
$attr['data-tdg-allowdrag'] = esc_attr( $allowdrag );
$attr['data-tdg-globe']     = esc_attr( $globe );
$attr['data-tdg-backfade']  = esc_attr( $backfade );
$attr['data-tdg-tilt']      = esc_attr( $tilt );
$attr['data-tdg-card']      = esc_attr( $card );
$attr['data-tdg-count']     = esc_attr( count( $items ) );

$ni = max( 1, count( $items ) );
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<div class="tdg__orbit">
			<?php for ( $k = 0; $k < $n_cards; $k++ ) {
				$item = $items[ $k % $ni ];
				echo '<div class="tdg__card">' . $render_card( $item ) . '</div>';
			} ?>
		</div>
	</div>
</div>
