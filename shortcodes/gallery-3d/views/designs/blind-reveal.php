<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Blind Reveal (Reveal & Wipe) — the photo is cut into Slats that turn over in 3D one after another
 * (Stagger), like Venetian blinds, each showing the next photo on its other side. Vertical slats turn
 * sideways, horizontal ones head over heels; Gap leaves a thin seam between them.
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
$orient = $dp( 'orientation', 'v' ) === 'h' ? 'h' : 'v';
$attr['data-tdg-slats']   = esc_attr( (int) max( 2, min( 40, round( (float) $dp( 'slats', 10 ) ) ) ) );
$attr['data-tdg-orient']  = esc_attr( $orient );
$attr['data-tdg-stagger'] = esc_attr( max( 0, min( 100, (float) $dp( 'stagger', 50 ) ) ) );
$attr['data-tdg-gap']     = esc_attr( max( 0, min( 20, (float) $dp( 'gap', 2 ) ) ) );
if ( $orient === 'h' ) { $attr['class'] = trim( ( isset( $attr['class'] ) ? $attr['class'] : '' ) . ' tdg--blind-h' ); }
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage"><div class="tdg__frame"></div></div>
	<div class="tdg__pool" hidden>
		<?php foreach ( $items as $item ) { echo '<div class="tdg__poolitem">' . $render_card( $item ) . '</div>'; } ?>
	</div>
</div>
