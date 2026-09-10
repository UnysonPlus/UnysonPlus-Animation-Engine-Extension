<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Depth Stack — a deck of cards receding straight into depth, the front card the
 * hero. The stack streams toward the camera (front flies out + fades, next comes forward, a new one
 * fades in at the back). Spread (Fan, along Spread Angle) or Scatter fans the deck out; Wobble jitters
 * the tilt; Depth Fade dims and Depth Blur softens the cards toward the back (depth-of-field). Cards
 * billboard so they stay readable. gallery-3d.js (initDepthStack) does the placement; here we emit
 * Card Count cards (images cycle).
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
$count   = max( 3, min( 50, (int) $dp( 'card_count', 4 ) ) );
$dgap    = max( 0, min( 80, (float) $dp( 'depth_gap', 18 ) ) );
$csize   = max( 4, min( 80, (float) $dp( 'card_size', 20 ) ) );
$spread  = max( 0, min( 120, (float) $dp( 'spread', 0 ) ) );
$sangle  = max( -180, min( 180, (float) $dp( 'spread_angle', -180 ) ) );
$wobble  = max( 0, min( 100, (float) $dp( 'wobble', 0 ) ) );
$dfade   = max( 0, min( 100, (float) $dp( 'depth_fade', 0 ) ) );
$dblur   = max( 0, min( 100, (float) $dp( 'depth_blur', 0 ) ) );
$layout  = in_array( $dp( 'layout', 'fan' ), array( 'fan', 'scatter', 'aisle', 'field' ), true ) ? $dp( 'layout', 'fan' ) : 'fan';

$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = esc_attr( $allowdrag );
$attr['data-tdg-momentum']  = esc_attr( $momentum );
$attr['data-tdg-speed']     = esc_attr( $speed );
$attr['data-tdg-dir']       = esc_attr( $dir );
$attr['data-tdg-hover']     = esc_attr( $hover );
$attr['data-tdg-gap']       = esc_attr( $dgap );
$attr['data-tdg-card']      = esc_attr( $csize );
$attr['data-tdg-spread']    = esc_attr( $spread );
$attr['data-tdg-angle']     = esc_attr( $sangle );
$attr['data-tdg-wobble']    = esc_attr( $wobble );
$attr['data-tdg-dfade']     = esc_attr( $dfade );
$attr['data-tdg-dblur']     = esc_attr( $dblur );
$attr['data-tdg-layout']    = esc_attr( $layout );
$attr['data-tdg-count']     = esc_attr( count( $items ) );

$ni = max( 1, count( $items ) );
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<div class="tdg__depth">
			<?php for ( $k = 0; $k < $count; $k++ ) {
				$item = $items[ $k % $ni ];
				echo '<div class="tdg__card">' . $render_card( $item ) . '</div>';
			} ?>
		</div>
	</div>
</div>
