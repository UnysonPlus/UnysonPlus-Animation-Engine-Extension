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

/* Motion comes pre-parsed from view.php ($motion_* — the nested Motion picker w/ legacy fallback). */
$drive     = $motion_mode;                                    // 'auto' | 'scroll' | 'static'
$allowdrag = ( $dp( 'allow_drag', 'no' ) === 'yes' || $motion_legacy_drag ) ? 1 : 0;
if ( ! empty( $as_bg ) ) { $drive = 'auto'; $allowdrag = 0; } // a Section background is non-interactive → auto-rotate, no drag
$speed    = max( 3, min( 60, $motion_speed ) );
$dir      = ( $motion_dir === 'right' ) ? -1 : 1;
$hover    = $motion_hover;
$momentum = $dp( 'drag_momentum', 'yes' ) === 'yes' ? 1 : 0;
$tilt     = max( -60, min( 60, (float) $dp( 'tilt', -28 ) ) );
$roll     = max( -45, min( 45, (float) $dp( 'roll', 0 ) ) );          // our extra "Diagonal Tilt"; off by default (animos has none)
$opening  = max( 0, min( 100, (float) $dp( 'ring_opening', 55 ) ) );
$ring     = max( 40, min( 140, (float) $dp( 'ring_size', 80 ) ) );
$spacing  = max( 60, min( 180, (float) $dp( 'spacing', 100 ) ) );      // our extra "Card Spacing"
$persp    = max( 8, min( 100, (float) $dp( 'perspective', 18 ) ) );
$backfade = max( 0, min( 100, (float) $dp( 'back_fade', 70 ) ) );
$card     = max( 6, min( 60, (float) $dp( 'card_size', 21 ) ) );

/* Soft render cap: the Ring is the one 1:1 design (one card per pool image), so a 200-post source
 * would build a comically huge ring — the radius grows with the count. 40 is well beyond any
 * readable ring; the pool is simply truncated (the other designs cycle their pools instead). */
if ( count( $items ) > 40 ) { $items = array_slice( $items, 0, 40 ); }

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
