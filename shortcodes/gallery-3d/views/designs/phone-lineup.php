<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Phone Lineup (Devices & Screens) — every photo on the screen of a 3D phone (a
 * metal frame in Frame Colour, sides as deep as Thickness, buttons, a back with a camera); the phones (Phones) stand in a shallow V — the centre one nearest, the others stepping back
 * (Depth) and turning toward it (Turn), spaced by Spacing — and the lineup turns like a carousel, fading
 * phones out at one end and in at the other. Stop at Centre holds each phone in the middle; the phones
 * float gently (Float); visitors can drag the lineup (Drag to spin). Photos repeat to fill the lineup.
 * gallery-3d.js (PhoneLineup init) does the placement + motion. An original UnysonPlus design.
 *
 * @var callable $dp, $render_card
 * @var array    $items, $attr
 * @var bool     $as_bg
 * @var string   $motion_mode, $motion_dir, $motion_hover
 * @var float    $motion_speed
 */

$drive = $motion_mode;
if ( ! empty( $as_bg ) ) { $drive = 'continuous'; }
$frame = (string) $dp( 'frame_color', '#3a3c41' );
if ( ! preg_match( '/^#[0-9a-fA-F]{3,8}$/', $frame ) ) { $frame = '#3a3c41'; }
$count = (int) max( 3, min( 15, round( (float) $dp( 'phones', 7 ) ) ) );
$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = ( empty( $as_bg ) && $dp( 'allow_drag', 'yes' ) === 'yes' ) ? '1' : '0';
$attr['data-tdg-momentum']  = $dp( 'drag_momentum', 'yes' ) === 'yes' ? '1' : '0';
$attr['data-tdg-hover']     = esc_attr( $motion_hover );
$attr['data-tdg-card']      = esc_attr( max( 4, min( 40, (float) $dp( 'card_size', 16 ) ) ) );
$attr['data-tdg-speed']   = esc_attr( max( 1, min( 12, $motion_speed ) ) );
$attr['data-tdg-dir']     = $motion_dir === 'right' ? '-1' : '1';
$attr['data-tdg-spacing'] = esc_attr( max( 20, min( 200, (float) $dp( 'spacing', 70 ) ) ) );
$attr['data-tdg-depth']   = esc_attr( max( 0, min( 150, (float) $dp( 'depth', 50 ) ) ) );
$attr['data-tdg-turn']    = esc_attr( max( 0, min( 80, (float) $dp( 'turn', 25 ) ) ) );
$attr['data-tdg-bob']     = esc_attr( max( 0, min( 12, (float) $dp( 'float', 3 ) ) ) );
$attr['data-tdg-thick']   = esc_attr( max( 0, min( 20, (float) $dp( 'thickness', 8 ) ) ) );
$attr['data-tdg-snap']    = $dp( 'snap', 'yes' ) === 'yes' ? '1' : '0';
$attr['data-tdg-move']    = esc_attr( max( 10, min( 95, (float) $dp( 'move_time', 45 ) ) ) );
$attr['style']            = trim( ( isset( $attr['style'] ) ? $attr['style'] : '' ) . ';--tdg-frame:' . $frame, ';' );

$n = count( $items );
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<div class="tdg__grid">
			<?php
			for ( $i = 0; $n && $i < $count; $i++ ) {
				echo '<div class="tdg__card">' . $render_card( $items[ $i % $n ] ) . '</div>';
			} ?>
		</div>
	</div>
</div>
