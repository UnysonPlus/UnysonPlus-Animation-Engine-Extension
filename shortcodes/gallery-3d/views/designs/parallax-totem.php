<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Parallax Totem — the depth-scatter sibling of Totem Wall. Same flat row of
 * vertical totems (columns wrapping their cards around a horizontal axis, cascading vertically), but
 * every card also gets a SEEDED depth, size and position jitter and drifts at a depth-varied speed —
 * near cards scroll faster than far ones (the parallax). At Scatter / Size variance / Parallax depth
 * all 0 it is a clean vertical grid; raised, it becomes a floating depth field. gallery-3d.js
 * (initParallax) does the placement; here we emit a generous grid of columns × cards.
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
$dir    = ( $motion_dir === 'down' ) ? -1 : 1;                 // up = +1 (no alternate for this design)
$hover  = $motion_hover;
$curv     = max( -150, min( 150, (float) $dp( 'curvature', -150 ) ) );
$gap      = max( 0, min( 20, (float) $dp( 'gap', 0.5 ) ) );
$edge     = max( 0, min( 100, (float) $dp( 'edge_fade', 0 ) ) );
$zoom     = max( 0, min( 100, (float) $dp( 'zoom', 10 ) ) );
$scatter  = max( 0, min( 100, (float) $dp( 'scatter', 0 ) ) );
$sizevar  = max( 0, min( 100, (float) $dp( 'size_variance', 0 ) ) );
$parallax = max( 0, min( 100, (float) $dp( 'parallax_depth', 0 ) ) );

$cols    = 9;
$per_col = 14;

$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = esc_attr( $allowdrag );
$attr['data-tdg-momentum']  = esc_attr( $momentum );
$attr['data-tdg-speed']     = esc_attr( $speed );
$attr['data-tdg-dir']       = esc_attr( $dir );
$attr['data-tdg-hover']     = esc_attr( $hover );
$attr['data-tdg-curv']      = esc_attr( $curv );
$attr['data-tdg-gap']       = esc_attr( $gap );
$attr['data-tdg-edge']      = esc_attr( $edge );
$attr['data-tdg-zoom']      = esc_attr( $zoom );
$attr['data-tdg-scatter']   = esc_attr( $scatter );
$attr['data-tdg-sizevar']   = esc_attr( $sizevar );
$attr['data-tdg-parallax']  = esc_attr( $parallax );
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
