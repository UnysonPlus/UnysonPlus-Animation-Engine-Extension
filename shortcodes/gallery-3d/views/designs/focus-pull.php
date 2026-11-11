<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Focus Pull (Spotlight & Focus) — Photos float at several depths (Layers, Depth) in a 3D field;
 * the focus racks from the front layer to the back and back again (Start at the front / back; Rack Time of
 * each stop spent racking), so the layer in focus is sharp and bright while the others blur (Blur) and dim
 * (Dim), and the field drifts gently sideways (Drift). Photos repeat up to the Photos count.
 * gallery-3d.js (FocusPull init) does the placement + motion. An original UnysonPlus design.
 *
 * @var callable $dp, $render_card
 * @var array    $items, $attr
 * @var bool     $as_bg
 * @var string   $motion_mode, $motion_dir, $motion_hover
 * @var float    $motion_speed
 */

$drive = $motion_mode;
if ( ! empty( $as_bg ) ) { $drive = 'continuous'; }
$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = '0';
$attr['data-tdg-hover']     = esc_attr( $motion_hover );
$attr['data-tdg-card']      = esc_attr( max( 3, min( 40, (float) $dp( 'card_size', 15 ) ) ) );
$attr['data-tdg-speed']  = esc_attr( max( 1, min( 12, $motion_speed ) ) );
$attr['data-tdg-dir']    = esc_attr( $motion_dir === 'back' ? -1 : 1 );
$attr['data-tdg-layers'] = esc_attr( (int) max( 2, min( 5, round( (float) $dp( 'layers', 3 ) ) ) ) );
$attr['data-tdg-depth']  = esc_attr( max( 10, min( 120, (float) $dp( 'depth', 45 ) ) ) );
$attr['data-tdg-blur']   = esc_attr( max( 0, min( 20, (float) $dp( 'blur', 7 ) ) ) );
$attr['data-tdg-dim']    = esc_attr( max( 0, min( 80, (float) $dp( 'dim', 30 ) ) ) );
$attr['data-tdg-drift']  = esc_attr( max( 0, min( 100, (float) $dp( 'drift', 30 ) ) ) );
$attr['data-tdg-move']   = esc_attr( max( 10, min( 90, (float) $dp( 'move_time', 40 ) ) ) );
$photos = (int) max( 4, min( 40, round( (float) $dp( 'photos', 15 ) ) ) );

$n = count( $items );
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<div class="tdg__grid">
			<?php
			for ( $i = 0; $n && $i < $photos; $i++ ) {
				echo '<div class="tdg__card">' . $render_card( $items[ $i % $n ] ) . '</div>';
			} ?>
		</div>
	</div>
</div>
