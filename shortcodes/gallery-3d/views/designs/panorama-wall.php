<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * 3D Gallery design: Panorama Wall — a concave cylinder grid. The viewer sits inside a cylinder whose
 * inner wall is tiled with a grid of cards (Columns × Rows). Columns wrap toward the viewer at the
 * left/right edges and scroll horizontally; rows stack to fill the frame. gallery-3d.js (initWall)
 * positions everything from the data-* attrs — we just emit Columns cards per Row (cycling the images,
 * offset per row for variety).
 *
 * @var callable $dp, $render_card
 * @var array    $items, $attr
 * @var bool     $as_bg
 */

/* Motion comes pre-parsed from view.php ($motion_* — the nested Motion picker w/ legacy fallback). */
$drive     = $motion_mode;                                    // 'continuous' | 'scroll' | 'static'
$allowdrag = ( $dp( 'allow_drag', 'no' ) === 'yes' || $motion_legacy_drag ) ? 1 : 0;
if ( ! empty( $as_bg ) ) { $drive = 'continuous'; $allowdrag = 0; } // a Section background is non-interactive → scroll, no drag
$momentum = $dp( 'drag_momentum', 'yes' ) === 'yes' ? 1 : 0;
$speed  = max( 5, min( 90, $motion_speed ) );
$dir    = ( $motion_dir === 'right' ) ? -1 : 1;
$alt    = ( $motion_dir === 'alternate' ) ? 1 : 0;
$hover  = $motion_hover;
$rows   = max( 1, min( 9, (int) $dp( 'rows', 5 ) ) );
$cols   = max( 3, min( 24, (int) $dp( 'columns', 11 ) ) );
$curv   = max( -150, min( 150, (float) $dp( 'curvature', -100 ) ) ); // signed: -concave .. +convex
$tilt   = max( -45, min( 45, (float) $dp( 'tilt', 0 ) ) );
$gap    = max( 0, min( 20, (float) $dp( 'gap', 5 ) ) );        // % of card width
$edge   = max( 0, min( 100, (float) $dp( 'edge_fade', 0 ) ) );
$persp  = max( 8, min( 100, (float) $dp( 'perspective', 68 ) ) );
$card   = max( 6, min( 40, (float) $dp( 'card_size', 20 ) ) );

$attr['data-tdg-drive'] = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = esc_attr( $allowdrag );
$attr['data-tdg-momentum']  = esc_attr( $momentum );
$attr['data-tdg-speed'] = esc_attr( $speed );
$attr['data-tdg-dir']   = esc_attr( $dir );
$attr['data-tdg-alt']   = esc_attr( $alt );
$attr['data-tdg-hover'] = esc_attr( $hover );
$attr['data-tdg-rows']  = esc_attr( $rows );
$attr['data-tdg-curv']  = esc_attr( $curv );
$attr['data-tdg-tilt']  = esc_attr( $tilt );
$attr['data-tdg-gap']   = esc_attr( $gap );
$attr['data-tdg-edge']  = esc_attr( $edge );
$attr['data-tdg-persp'] = esc_attr( $persp );
$attr['data-tdg-card']  = esc_attr( $card );
$attr['data-tdg-count'] = esc_attr( count( $items ) );

$ni = max( 1, count( $items ) );
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<div class="tdg__wall">
			<?php for ( $r = 0; $r < $rows; $r++ ) : ?>
				<div class="tdg__row" data-row="<?php echo (int) $r; ?>">
					<?php for ( $c = 0; $c < $cols; $c++ ) {
						$item = $items[ ( $c + $r ) % $ni ]; // offset per row so rows don't line up identically
						echo '<div class="tdg__card">' . $render_card( $item ) . '</div>';
					} ?>
				</div>
			<?php endfor; ?>
		</div>
	</div>
</div>
