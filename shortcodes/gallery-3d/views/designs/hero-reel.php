<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * 3D Gallery design: Hero Reel — a horizontal reel of thumbnail cards over a full-bleed HERO background.
 * The card stepping through the centre is the "active" one: it scales up + lifts, and its image fills
 * the whole stage as a dimmed, slowly-zooming (Ken Burns) hero behind the reel. As the reel advances the
 * hero cross-fades to the next card. A flat 2D design (Carousel & Flow family). gallery-3d.js
 * (initHeroReel) drives the stepping, the active-card scale/lift, and the hero cross-fade + Ken Burns.
 *
 * @var callable $dp, $render_card
 * @var array    $items, $attr
 * @var bool     $as_bg
 * @var string   $bg      resolved background (parent view)
 */

$drive     = $motion_mode;                                    // 'continuous' | 'scroll' | 'static'
$allowdrag = ( $dp( 'allow_drag', 'yes' ) === 'yes' || $motion_legacy_drag ) ? 1 : 0;
if ( ! empty( $as_bg ) ) { $drive = 'continuous'; $allowdrag = 0; }
$momentum = $dp( 'drag_momentum', 'yes' ) === 'yes' ? 1 : 0;
$speed  = max( 5, min( 90, $motion_speed ) );
$dir    = ( $motion_dir === 'right' ) ? -1 : 1;               // left = +1
$hover  = $motion_hover;
$csize  = max( 8, min( 40, (float) $dp( 'card_size', 18 ) ) );
$gap    = max( 0, min( 20, (float) $dp( 'card_spacing', 4 ) ) );
$ascale = max( 100, min( 160, (float) $dp( 'active_scale', 112 ) ) );
$alift  = max( 0, min( 30, (float) $dp( 'active_lift', 8 ) ) );
$hop    = max( 0, min( 100, (float) $dp( 'hero_opacity', 55 ) ) );
$kb     = max( 0, min( 100, (float) $dp( 'ken_burns', 60 ) ) );
$hold   = max( 0, min( 100, (float) $dp( 'hold', 55 ) ) );

$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = esc_attr( $allowdrag );
$attr['data-tdg-momentum']  = esc_attr( $momentum );
$attr['data-tdg-speed']     = esc_attr( $speed );
$attr['data-tdg-dir']       = esc_attr( $dir );
$attr['data-tdg-hover']     = esc_attr( $hover );
$attr['data-tdg-card']      = esc_attr( $csize );
$attr['data-tdg-gap']       = esc_attr( $gap );
$attr['data-tdg-ascale']    = esc_attr( $ascale );
$attr['data-tdg-alift']     = esc_attr( $alift );
$attr['data-tdg-hop']       = esc_attr( $hop );
$attr['data-tdg-kb']        = esc_attr( $kb );
$attr['data-tdg-hold']      = esc_attr( $hold );
$attr['data-tdg-count']     = esc_attr( count( $items ) );

/* Reference default stage colour is a near-black (#101014) so the dimmed hero reads with depth. */
$hr_bg = ( isset( $bg ) && '' !== $bg ) ? $bg : '#101014';

/* One card per image; pad small sets so the reel reads full. */
$slots = $items;
if ( count( $slots ) > 0 ) {
	while ( count( $slots ) < 7 ) { $slots = array_merge( $slots, $items ); }
}
$slots = array_slice( $slots, 0, 30 );
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage" style="background:<?php echo esc_attr( $hr_bg ); ?>;">
		<div class="tdg__hero" aria-hidden="true"><span class="tdg__hero-layer"></span><span class="tdg__hero-layer"></span></div>
		<div class="tdg__reel">
			<?php foreach ( $slots as $item ) {
				echo '<div class="tdg__card">' . $render_card( $item ) . '</div>';
			} ?>
		</div>
	</div>
</div>
