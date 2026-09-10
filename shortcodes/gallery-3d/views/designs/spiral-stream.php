<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Spiral Stream — cards threaded along a 3D helix that streams past the camera.
 * Each card sits on the spiral (angle = its position × Spiral Turns) at a tapering radius (Taper
 * narrows the coil into a vortex or flares it out) and rotates around its own vertical axis, so you
 * see fronts, edge-on slivers and mirrored backs, upright throughout. The whole helix tips by Ring
 * Tilt. gallery-3d.js (initSpiral) does the placement; here we emit Card Count cards (images cycle).
 *
 * @var callable $dp, $render_card
 * @var array    $items, $attr
 * @var bool     $as_bg
 */

$drive     = $motion_mode;                                    // 'continuous' | 'scroll' | 'static'
$allowdrag = ( $dp( 'allow_drag', 'yes' ) === 'yes' || $motion_legacy_drag ) ? 1 : 0;
if ( ! empty( $as_bg ) ) { $drive = 'continuous'; $allowdrag = 0; }
$momentum = $dp( 'drag_momentum', 'yes' ) === 'yes' ? 1 : 0;
$speed  = max( 5, min( 90, $motion_speed ) );
$dir    = ( $motion_dir === 'backward' ) ? -1 : 1;            // forward = +1 (stream toward you)
$hover  = $motion_hover;
$turns   = max( 0.25, min( 6, (float) $dp( 'spiral_turns', 1 ) ) );
$count   = max( 3, min( 48, (int) $dp( 'card_count', 8 ) ) );
$size    = max( 0, min( 100, (float) $dp( 'spiral_size', 35 ) ) );
$taper   = max( -100, min( 100, (float) $dp( 'taper', -90 ) ) );
$csize   = max( 4, min( 60, (float) $dp( 'card_size', 12 ) ) );
$bfade   = max( 0, min( 100, (float) $dp( 'back_fade', 10 ) ) );
$persp   = max( 0, min( 100, (float) $dp( 'perspective', 0 ) ) );
$tilt    = max( -45, min( 45, (float) $dp( 'ring_tilt', -45 ) ) );
$cgap    = max( 0, min( 100, (float) $dp( 'card_gap', 0 ) ) );
$pulse   = max( 0, min( 100, (float) $dp( 'scale_pulse', 0 ) ) );

$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = esc_attr( $allowdrag );
$attr['data-tdg-momentum']  = esc_attr( $momentum );
$attr['data-tdg-speed']     = esc_attr( $speed );
$attr['data-tdg-dir']       = esc_attr( $dir );
$attr['data-tdg-hover']     = esc_attr( $hover );
$attr['data-tdg-turns']     = esc_attr( $turns );
$attr['data-tdg-size']      = esc_attr( $size );
$attr['data-tdg-taper']     = esc_attr( $taper );
$attr['data-tdg-card']      = esc_attr( $csize );
$attr['data-tdg-backfade']  = esc_attr( $bfade );
$attr['data-tdg-persp']     = esc_attr( $persp );
$attr['data-tdg-tilt']      = esc_attr( $tilt );
$attr['data-tdg-gap']       = esc_attr( $cgap );
$attr['data-tdg-pulse']     = esc_attr( $pulse );
$attr['data-tdg-count']     = esc_attr( count( $items ) );

$ni = max( 1, count( $items ) );
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<div class="tdg__spiral">
			<?php for ( $k = 0; $k < $count; $k++ ) {
				$item = $items[ $k % $ni ];
				echo '<div class="tdg__card">' . $render_card( $item ) . '</div>';
			} ?>
		</div>
	</div>
</div>
