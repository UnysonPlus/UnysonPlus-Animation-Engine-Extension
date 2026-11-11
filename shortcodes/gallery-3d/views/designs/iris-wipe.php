<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Iris Wipe (Reveal & Wipe) — the next photo opens up over the current one through a growing Shape
 * (a circle, a diamond, a diagonal sweep or barn doors) taking Wipe Length of each photo's time, while
 * the outgoing photo pushes gently in and the incoming one settles from a slight Zoom.
 * One large photo shows at a time in a centred .tdg__frame (Photo Size × Photo Ratio). The photos come
 * from the hidden .tdg__pool below, which gallery-3d.js reads; the pool's links also make a click (or
 * Enter) on the photo open the lightbox / link of the photo on show, and keep every photo in the lightbox.
 * An original UnysonPlus design.
 *
 * @var callable $dp, $render_card
 * @var array    $items, $attr
 * @var bool     $as_bg
 * @var string   $motion_mode, $motion_dir, $motion_hover
 * @var float    $motion_speed
 * @var bool     $motion_legacy_drag
 */

$drive     = $motion_mode;
$allowdrag = ( $dp( 'allow_drag', 'no' ) === 'yes' || $motion_legacy_drag ) ? 1 : 0;
if ( ! empty( $as_bg ) ) { $drive = 'continuous'; $allowdrag = 0; }
$momentum = $dp( 'drag_momentum', 'yes' ) === 'yes' ? 1 : 0;

$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = esc_attr( $allowdrag );
$attr['data-tdg-momentum']  = esc_attr( $momentum );
$attr['data-tdg-speed']     = esc_attr( max( 2, min( 15, $motion_speed ) ) );
$attr['data-tdg-dir']       = esc_attr( $motion_dir === 'back' ? -1 : 1 );
$attr['data-tdg-hover']     = esc_attr( $motion_hover );
$attr['data-tdg-frame']     = esc_attr( max( 20, min( 100, (float) $dp( 'frame_size', 70 ) ) ) );
$shape = (string) $dp( 'shape', 'circle' );
if ( ! in_array( $shape, array( 'circle', 'diamond', 'diagonal', 'doors' ), true ) ) { $shape = 'circle'; }
$attr['data-tdg-shape'] = esc_attr( $shape );
$attr['data-tdg-zoom']  = esc_attr( max( 0, min( 40, (float) $dp( 'zoom', 12 ) ) ) );
$attr['data-tdg-dur']   = esc_attr( max( 15, min( 90, (float) $dp( 'transition', 45 ) ) ) );
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage"><div class="tdg__frame"></div></div>
	<div class="tdg__pool" hidden>
		<?php foreach ( $items as $item ) { echo '<div class="tdg__poolitem">' . $render_card( $item ) . '</div>'; } ?>
	</div>
</div>
