<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Focus Slider — a horizontal (or vertical) auto-advancing slider where the card
 * passing through the CENTRE is "focused": it scales up (Center Scale) and sits clean while its
 * neighbours stay small. Slide Portion sets the card spacing (low = cinematic overlap, high = spread);
 * Zigzag pushes off-centre cards alternately up/down (left/right in vertical mode). Padding / Corner
 * Radius / Background frame each card. A flat 2D design (Carousel & Flow family). gallery-3d.js
 * (initFocusSlider) drives the stepping, the centre-focus scale and the zigzag.
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
$dir    = ( $motion_dir === 'right' ) ? -1 : 1;               // left/up = +1

$axis   = ( $dp( 'axis', 'horizontal' ) === 'vertical' ) ? 'vertical' : 'horizontal';
$csize  = max( 20, min( 100, (float) $dp( 'card_size', 78 ) ) );
$cscale = max( 100, min( 300, (float) $dp( 'center_scale', 200 ) ) );
$slide  = max( 20, min( 100, (float) $dp( 'slide_portion', 60 ) ) );
$gap    = max( 0, min( 40, (float) $dp( 'card_spacing', 6 ) ) );
$zig    = max( 0, min( 100, (float) $dp( 'zigzag', 0 ) ) );
$zigm   = ( $dp( 'zigzag_mode', 'alternate' ) === 'fixed' ) ? 'fixed' : 'alternate';
$hold   = max( 0, min( 100, (float) $dp( 'hold', 50 ) ) );

$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = esc_attr( $allowdrag );
$attr['data-tdg-momentum']  = esc_attr( $momentum );
$attr['data-tdg-speed']     = esc_attr( $speed );
$attr['data-tdg-dir']       = esc_attr( $dir );
$attr['data-tdg-hover']     = esc_attr( $motion_hover );
$attr['data-tdg-axis']      = esc_attr( $axis );
$attr['data-tdg-card']      = esc_attr( $csize );
$attr['data-tdg-cscale']    = esc_attr( $cscale );
$attr['data-tdg-slide']     = esc_attr( $slide );
$attr['data-tdg-gap']       = esc_attr( $gap );
$attr['data-tdg-zig']       = esc_attr( $zig );
$attr['data-tdg-zigmode']   = esc_attr( $zigm );
$attr['data-tdg-hold']      = esc_attr( $hold );
$attr['data-tdg-count']     = esc_attr( count( $items ) );

/* Reference default stage colour is a near-black (#101014) so the focused card reads with depth. */
$fs_bg = ( isset( $bg ) && '' !== $bg ) ? $bg : '#101014';

/* One card per image; pad small sets so the slider always has neighbours around the focus. */
$slots = $items;
if ( count( $slots ) > 0 ) {
	while ( count( $slots ) < 7 ) { $slots = array_merge( $slots, $items ); }
}
$slots = array_slice( $slots, 0, 30 );
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage" style="background:<?php echo esc_attr( $fs_bg ); ?>;">
		<div class="tdg__reel">
			<?php foreach ( $slots as $item ) {
				echo '<div class="tdg__card">' . $render_card( $item ) . '</div>';
			} ?>
		</div>
	</div>
</div>
