<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * 3D Gallery design: Card Tunnel — an infinite perspective corridor. Four walls (ceiling / floor /
 * left / right), each a ribbon of cards tiled along the tunnel axis, recede to a central vanishing
 * point and fly Forward or Backward, recycling seamlessly. Tunnel Size sets the corridor width, Card
 * Length the per-card depth, Depth Fade dims the far end. gallery-3d.js (initTunnel) sizes + places
 * the cards to their walls; here we emit the four walls, each with N cards (images cycle across them).
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
$dir    = ( $motion_dir === 'backward' ) ? -1 : 1;            // forward = +1 (into the tunnel)
$hover  = $motion_hover;
$tsize  = max( 0, min( 120, (float) $dp( 'tunnel_size', 60 ) ) );
$clen   = max( 0, min( 120, (float) $dp( 'card_length', 30 ) ) );
$gap    = max( 0, min( 40, (float) $dp( 'gap', 2 ) ) );
$dfade  = max( 0, min( 100, (float) $dp( 'depth_fade', 0 ) ) );

/* Cards per wall — enough to tile the corridor to the vanishing point at the shortest Card Length
 * (the driver only lays out what it needs; extra depth just recedes into the dark). */
$per_wall = 18;

$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = esc_attr( $allowdrag );
$attr['data-tdg-momentum']  = esc_attr( $momentum );
$attr['data-tdg-speed']     = esc_attr( $speed );
$attr['data-tdg-dir']       = esc_attr( $dir );
$attr['data-tdg-hover']     = esc_attr( $hover );
$attr['data-tdg-tsize']     = esc_attr( $tsize );
$attr['data-tdg-clen']      = esc_attr( $clen );
$attr['data-tdg-gap']       = esc_attr( $gap );
$attr['data-tdg-dfade']     = esc_attr( $dfade );
$attr['data-tdg-count']     = esc_attr( count( $items ) );

$ni    = max( 1, count( $items ) );
$walls = array( 'top', 'bottom', 'left', 'right' );
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<div class="tdg__tunnel">
			<?php foreach ( $walls as $wi => $wall ) : ?>
				<div class="tdg__wall tdg__wall--<?php echo esc_attr( $wall ); ?>">
					<?php for ( $k = 0; $k < $per_wall; $k++ ) {
						$item = $items[ ( $k * 4 + $wi ) % $ni ]; // spread images across walls + depth
						echo '<div class="tdg__card">' . $render_card( $item ) . '</div>';
					} ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>
