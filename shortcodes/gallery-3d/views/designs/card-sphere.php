<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * 3D Gallery design: Card Sphere — cards wrapped onto a spinning sphere (a "disco ball" of images).
 * Each card sits on the sphere surface facing outward (rotateY(lon) rotateX(lat) translateZ(R)).
 *
 * The TILING IS DERIVED, not configured: Globe Size sets the sphere and the bands / per-band card
 * counts are computed to cover it. (The inverse — deriving the sphere from an explicit card count —
 * meant turning "density" up silently grew the globe, which is backwards.)
 *
 * Both Globe Size and Card Size are % of the stage's SHORTER SIDE (a sphere is bounded by it), so
 * R / cardW = globe / (2·card) is a pure ratio — that's what lets us derive the counts here in PHP
 * without knowing the pixel size. gallery-3d.js (initGlobe) converts to pixels and spins it.
 *
 * @var callable $dp, $render_card
 * @var array    $items, $attr
 * @var bool     $as_bg
 */

/* Motion comes pre-parsed from view.php ($motion_* — the nested Motion picker w/ legacy fallback). */
$drive     = $motion_mode;                                    // 'continuous' | 'scroll' | 'static'
$allowdrag = ( $dp( 'allow_drag', 'no' ) === 'yes' || $motion_legacy_drag ) ? 1 : 0;
if ( ! empty( $as_bg ) ) { $drive = 'continuous'; $allowdrag = 0; }
$speed    = max( 5, min( 90, $motion_speed ) );
$dir      = ( $motion_dir === 'right' ) ? -1 : 1;
$hover    = $motion_hover;
$momentum = $dp( 'drag_momentum', 'yes' ) === 'yes' ? 1 : 0;
$globe    = max( 40, min( 95, (float) $dp( 'globe_size', 70 ) ) );  // sphere diameter, % of the stage's shorter side
$card     = max( 8, min( 30, (float) $dp( 'card_size', 20 ) ) );
$gap      = max( 0, min( 8, (float) $dp( 'gap', 2.5 ) ) );
$backfade = max( 0, min( 90, (float) $dp( 'back_fade', 55 ) ) );
$tilt     = max( -45, min( 45, (float) $dp( 'tilt', 0 ) ) );
$persp    = max( 8, min( 100, (float) $dp( 'perspective', 55 ) ) ); // our extra (animos has none)

$maxlat = 80; // top/bottom band latitude — kept off the exact poles so cards don't pile up

/* ---- derive the tiling from the ratios ---- */
$rw = 16; $rh = 9;
if ( preg_match( '/^(\d+)-(\d+)$/', (string) $dp( 'card_ratio', '16-9' ), $m ) ) { $rw = max( 1, (int) $m[1] ); $rh = max( 1, (int) $m[2] ); }
$card_aspect = $rh / $rw;                 // cardH / cardW
// Card Size is a % of the GLOBE (not the stage), so R / cardW = 1 / (2·card%) — independent of Globe
// Size. That makes Globe Size a pure zoom (sphere + cards scale together, tiling unchanged) and Card
// Size the density control: smaller cards tile the sphere more finely, so it reads round instead of
// faceted. Still pixel-free, so the counts can be derived here.
$r_over_card = 50 / $card;
$gap_frac    = $gap / 100;
// Bands: the -maxlat..+maxlat arc divided by the card height (+ gap).
$rows = max( 3, min( 24, (int) round( $r_over_card * 2 * deg2rad( $maxlat ) / ( $card_aspect + $gap_frac ) ) ) );

$attr['data-tdg-drive']    = esc_attr( $drive );
$attr['data-tdg-speed']    = esc_attr( $speed );
$attr['data-tdg-dir']      = esc_attr( $dir );
$attr['data-tdg-hover']    = esc_attr( $hover );
$attr['data-tdg-momentum'] = esc_attr( $momentum );
$attr['data-tdg-allowdrag'] = esc_attr( $allowdrag );
$attr['data-tdg-globe']    = esc_attr( $globe );
$attr['data-tdg-maxlat']   = esc_attr( $maxlat );
$attr['data-tdg-rows']     = esc_attr( $rows );
$attr['data-tdg-backfade'] = esc_attr( $backfade );
$attr['data-tdg-tilt']     = esc_attr( $tilt );
$attr['data-tdg-persp']    = esc_attr( $persp );
$attr['data-tdg-card']     = esc_attr( $card );
$attr['data-tdg-count']    = esc_attr( count( $items ) );

$ni = max( 1, count( $items ) );
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<div class="tdg__globe">
			<?php for ( $b = 0; $b < $rows; $b++ ) :
				$lat = ( ( $b + 0.5 ) / $rows * 2 - 1 ) * $maxlat;   // -maxlat .. maxlat (must match JS)
				// Cards around this band's ring: its circumference / (card + gap). Thins toward the poles.
				$cnt = max( 3, (int) round( 2 * M_PI * $r_over_card * cos( deg2rad( $lat ) ) / ( 1 + $gap_frac ) ) );
				?>
				<div class="tdg__band" data-band="<?php echo (int) $b; ?>">
					<?php for ( $k = 0; $k < $cnt; $k++ ) {
						$item = $items[ ( $k + $b ) % $ni ]; // offset per band so bands don't line up identically
						echo '<div class="tdg__card">' . $render_card( $item ) . '</div>';
					} ?>
				</div>
			<?php endfor; ?>
		</div>
	</div>
</div>
