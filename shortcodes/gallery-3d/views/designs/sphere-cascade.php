<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Sphere Cascade — the vertical, spherical sibling of Panorama Wall. A grid of
 * Columns × K cards is mapped onto a sphere (column = longitude, card = latitude) and CASCADES
 * vertically: each column's cards scroll along latitude and recycle. The signed Curvature bends
 * BOTH axes — concave bowl (viewer inside) → flat grid → full convex sphere. gallery-3d.js
 * (initCascade) does the placement + cascade; here we emit Columns columns, each K cards
 * (cycling the images, offset per column for variety).
 *
 * @var callable $dp, $render_card
 * @var array    $items, $attr
 * @var bool     $as_bg
 */

/* Motion comes pre-parsed from view.php ($motion_* — the nested Motion picker w/ legacy fallback).
 * Cascade direction is VERTICAL: up (default) / down / alternate columns. */
$drive     = $motion_mode;                                    // 'continuous' | 'scroll' | 'static'
$allowdrag = ( $dp( 'allow_drag', 'no' ) === 'yes' || $motion_legacy_drag ) ? 1 : 0;
if ( ! empty( $as_bg ) ) { $drive = 'continuous'; $allowdrag = 0; }
$momentum = $dp( 'drag_momentum', 'yes' ) === 'yes' ? 1 : 0;
$speed  = max( 5, min( 90, $motion_speed ) );
$dir    = ( $motion_dir === 'down' ) ? -1 : 1;                 // up = +1
$alt    = ( $motion_dir === 'alternate' ) ? 1 : 0;
$hover  = $motion_hover;
$cols   = max( 2, min( 5, (int) $dp( 'columns', 5 ) ) );
$curv   = max( -150, min( 150, (float) $dp( 'curvature', -100 ) ) ); // signed: -concave .. +convex
$tilt   = max( -45, min( 45, (float) $dp( 'tilt', 0 ) ) );
$gap    = max( 0.5, min( 20, (float) $dp( 'gap', 5 ) ) );
$edge   = max( 0, min( 100, (float) $dp( 'edge_fade', 0 ) ) );
$card   = max( 6, min( 40, (float) $dp( 'card_size', 18 ) ) );

/* Cards per column — enough to fill the latitude arc with a recycle buffer (the cascade wraps them).
 * Smaller cards → more per column. Fixed count is fine; the driver only lays out what's here. */
$per_col = max( 12, min( 28, (int) round( 320 / $card ) ) );

$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = esc_attr( $allowdrag );
$attr['data-tdg-momentum']  = esc_attr( $momentum );
$attr['data-tdg-speed']     = esc_attr( $speed );
$attr['data-tdg-dir']       = esc_attr( $dir );
$attr['data-tdg-alt']       = esc_attr( $alt );
$attr['data-tdg-hover']     = esc_attr( $hover );
$attr['data-tdg-curv']      = esc_attr( $curv );
$attr['data-tdg-tilt']      = esc_attr( $tilt );
$attr['data-tdg-gap']       = esc_attr( $gap );
$attr['data-tdg-edge']      = esc_attr( $edge );
$attr['data-tdg-card']      = esc_attr( $card );
$attr['data-tdg-count']     = esc_attr( count( $items ) );

$ni = max( 1, count( $items ) );
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<div class="tdg__cascade">
			<?php for ( $c = 0; $c < $cols; $c++ ) : ?>
				<div class="tdg__col" data-col="<?php echo (int) $c; ?>">
					<?php for ( $k = 0; $k < $per_col; $k++ ) {
						$item = $items[ ( $k * $cols + $c ) % $ni ]; // spread images across the grid
						echo '<div class="tdg__card">' . $render_card( $item ) . '</div>';
					} ?>
				</div>
			<?php endfor; ?>
		</div>
	</div>
</div>
