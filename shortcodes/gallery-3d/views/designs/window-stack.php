<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Window Stack (Devices & Screens) — every photo in a browser window (Title Bar
 * Colour); the windows are stacked in a tilted 3D pile (Tilt, Turn), each one set back (Depth Gap) and up
 * to the side (Offset) from the one in front, the deeper ones dimmed (Dim). The front window leaves —
 * toward the visitor, or dropping away (Direction) — and the others move forward one place while it
 * reappears at the back.
 * gallery-3d.js (WindowStack init) does the placement + motion. An original UnysonPlus design.
 *
 * @var callable $dp, $render_card
 * @var array    $items, $attr
 * @var bool     $as_bg
 * @var string   $motion_mode, $motion_dir, $motion_hover
 * @var float    $motion_speed
 */

$drive = $motion_mode;
if ( ! empty( $as_bg ) ) { $drive = 'continuous'; }
$chrome = (string) $dp( 'chrome_color', '#e9eaee' );
if ( ! preg_match( '/^#[0-9a-fA-F]{3,8}$/', $chrome ) ) { $chrome = '#e9eaee'; }
$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = '0';
$attr['data-tdg-hover']     = esc_attr( $motion_hover );
$attr['data-tdg-card']      = esc_attr( max( 10, min( 80, (float) $dp( 'card_size', 44 ) ) ) );
$attr['data-tdg-speed']  = esc_attr( max( 1, min( 12, $motion_speed ) ) );
$attr['data-tdg-exit']   = $motion_dir === 'down' ? 'down' : 'toward';
$attr['data-tdg-behind'] = esc_attr( max( 1, min( 8, round( (float) $dp( 'behind', 4 ) ) ) ) );
$attr['data-tdg-gap']    = esc_attr( max( 5, min( 100, (float) $dp( 'gap', 30 ) ) ) );
$attr['data-tdg-offset'] = esc_attr( max( 0, min( 30, (float) $dp( 'offset', 8 ) ) ) );
$attr['data-tdg-tilt']   = esc_attr( max( 0, min( 50, (float) $dp( 'tilt', 16 ) ) ) );
$attr['data-tdg-yaw']    = esc_attr( max( -45, min( 45, (float) $dp( 'yaw', 22 ) ) ) );
$attr['data-tdg-dim']    = esc_attr( max( 0, min( 80, (float) $dp( 'dim', 30 ) ) ) );
$attr['data-tdg-move']   = esc_attr( max( 15, min( 95, (float) $dp( 'move_time', 55 ) ) ) );
$attr['style']           = trim( ( isset( $attr['style'] ) ? $attr['style'] : '' ) . ';--tdg-chrome:' . $chrome, ';' );
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<div class="tdg__grid">
			<?php foreach ( $items as $item ) {
				echo '<div class="tdg__card">' . $render_card( $item ) . '</div>';
			} ?>
		</div>
	</div>
</div>
