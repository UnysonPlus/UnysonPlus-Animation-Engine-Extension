<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Totem Wall — a flat row of INDEPENDENT vertical cylinders ("totems"). Unlike
 * Sphere Cascade (every column on ONE shared sphere), each column here is its own vertical barrel:
 * it sits at a fixed X and its cards wrap around that column's own horizontal axis into a rounded
 * pillar, cascading vertically (up / down / alternate) and recycling at the wrap. Signed Curvature
 * curls each totem — concave (curves away) → near-flat → convex (bulges toward you); Zoom sets the
 * totem size. gallery-3d.js (initTotem) does the placement + cascade; here we emit a generous grid
 * of columns × cards (images cycle across the grid, the driver shows only what fits).
 *
 * @var callable $dp, $render_card
 * @var array    $items, $attr
 * @var bool     $as_bg
 */

/* Motion comes pre-parsed from view.php ($motion_* — the nested Motion picker w/ legacy fallback).
 * Totem direction is VERTICAL: up (default) / down / alternate columns. */
$drive     = $motion_mode;                                    // 'continuous' | 'scroll' | 'static'
$allowdrag = ( $dp( 'allow_drag', 'yes' ) === 'yes' || $motion_legacy_drag ) ? 1 : 0;
if ( ! empty( $as_bg ) ) { $drive = 'continuous'; $allowdrag = 0; }
$momentum = $dp( 'drag_momentum', 'yes' ) === 'yes' ? 1 : 0;
$speed  = max( 5, min( 90, $motion_speed ) );
$dir    = ( $motion_dir === 'down' ) ? -1 : 1;                 // up = +1
$alt    = ( $motion_dir === 'alternate' ) ? 1 : 0;
$hover  = $motion_hover;
$curv   = max( -150, min( 150, (float) $dp( 'curvature', -150 ) ) ); // signed: -concave .. +convex
$tilt   = max( -45, min( 45, (float) $dp( 'tilt', -45 ) ) );
$gap    = max( 0, min( 20, (float) $dp( 'gap', 0.5 ) ) );
$edge   = max( 0, min( 100, (float) $dp( 'edge_fade', 0 ) ) );
$zoom   = max( 0, min( 100, (float) $dp( 'zoom', 10 ) ) );

/* A generous fixed grid — Zoom (not a count) drives density, so the driver simply shows the totems
 * that fit and fades/clips the rim ones. Columns tall enough to wrap into a near-full cylinder. */
$cols    = 9;
$per_col = 14;

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
$attr['data-tdg-zoom']      = esc_attr( $zoom );
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
