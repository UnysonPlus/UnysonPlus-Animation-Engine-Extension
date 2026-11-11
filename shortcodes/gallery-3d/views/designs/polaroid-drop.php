<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Polaroid Drop (Stack & Scatter) — instant-photo prints (Frame Colour) drop one by
 * one onto a growing pile (Pile Size), each landing at its own spot (Scatter) and angle (Max Tilt) with a
 * small bounce. Direction: drop from above, or tossed in from the camera. When Full: the oldest print
 * slides away as the next lands, or the whole pile is swept off and a new one starts.
 * gallery-3d.js (PolaroidDrop init) does the placement + motion. An original UnysonPlus design.
 *
 * Enough cards are rendered (the gallery repeated) that a full pile plus the print landing on it never
 * need the same card twice.
 *
 * @var callable $dp, $render_card
 * @var array    $items, $attr
 * @var bool     $as_bg
 * @var string   $motion_mode, $motion_dir, $motion_hover
 * @var float    $motion_speed
 */

$drive = $motion_mode;
if ( ! empty( $as_bg ) ) { $drive = 'continuous'; }
$pile  = (int) max( 1, min( 20, round( (float) $dp( 'pile', 6 ) ) ) );
$frame = (string) $dp( 'frame_color', '#ffffff' );
if ( ! preg_match( '/^#[0-9a-fA-F]{3,8}$/', $frame ) ) { $frame = '#ffffff'; }
$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = '0';
$attr['data-tdg-hover']     = esc_attr( $motion_hover );
$attr['data-tdg-card']      = esc_attr( max( 6, min( 60, (float) $dp( 'card_size', 24 ) ) ) );
$attr['data-tdg-speed']   = esc_attr( max( 1, min( 10, $motion_speed ) ) );
$attr['data-tdg-from']    = $motion_dir === 'camera' ? 'camera' : 'above';
$attr['data-tdg-pile']    = esc_attr( $pile );
$attr['data-tdg-scatter'] = esc_attr( max( 0, min( 200, (float) $dp( 'scatter', 45 ) ) ) );
$attr['data-tdg-tilt']    = esc_attr( max( 0, min( 45, (float) $dp( 'max_tilt', 14 ) ) ) );
$attr['data-tdg-sweep']   = $dp( 'when_full', 'oldest' ) === 'sweep' ? '1' : '0';
$attr['data-tdg-move']    = esc_attr( max( 15, min( 95, (float) $dp( 'move_time', 45 ) ) ) );
$attr['style']            = trim( ( isset( $attr['style'] ) ? $attr['style'] : '' ) . ';--tdg-frame:' . $frame, ';' );

$n     = count( $items );
$count = $n ? $n * (int) ceil( ( $pile + 1 ) / $n ) : 0;
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<div class="tdg__grid">
			<?php
			for ( $i = 0; $i < $count; $i++ ) {
				echo '<div class="tdg__card"' . $card_style( $items[ $i % $n ] ) . '>' . $render_card( $items[ $i % $n ] ) . '</div>';
			} ?>
		</div>
	</div>
</div>
