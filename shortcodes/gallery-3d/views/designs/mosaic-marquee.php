<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * 3D Gallery design: Mosaic Marquee — a horizontal marquee of cards packed masonry-style (varied
 * row/column spans) that scrolls sideways forever (Carousel & Flow family). The tiles fill a fixed
 * number of rows (from Card Height) via CSS grid dense packing; two identical copies of the grid ride
 * a track that wraps by exactly one copy's width for a seamless loop. Layout 1/2/3 are three patterns.
 * gallery-3d.js (initMosaic) sizes the grid + drives the scroll; here we emit two grids of cards.
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
$dir    = ( $motion_dir === 'right' ) ? -1 : 1;              // left = +1
$hover  = $motion_hover;
$cardH  = max( 10, min( 90, (float) $dp( 'card_height', 30 ) ) );
$gap    = max( 0, min( 8, (float) $dp( 'gap', 0.5 ) ) );
$layout = in_array( $dp( 'layout', '1' ), array( '1', '2', '3' ), true ) ? (string) $dp( 'layout', '1' ) : '1';

/* card_ratio → rw/rh for the grid cell aspect (cellW = cellH · rw/rh). */
$ratio_key = (string) $dp( 'card_ratio', '1-1' );
$rparts    = preg_match( '/^(\d+)-(\d+)$/', $ratio_key, $m ) ? array( (int) $m[1], (int) $m[2] ) : array( 1, 1 );

$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = esc_attr( $allowdrag );
$attr['data-tdg-momentum']  = esc_attr( $momentum );
$attr['data-tdg-speed']     = esc_attr( $speed );
$attr['data-tdg-dir']       = esc_attr( $dir );
$attr['data-tdg-hover']     = esc_attr( $hover );
$attr['data-tdg-card']      = esc_attr( $cardH );
$attr['data-tdg-gap']       = esc_attr( $gap );
$attr['data-tdg-layout']    = esc_attr( $layout );
$attr['data-tdg-rw']        = esc_attr( $rparts[0] );
$attr['data-tdg-rh']        = esc_attr( $rparts[1] );
$attr['data-tdg-corner']    = esc_attr( max( 0, min( 60, (float) $dp( 'corner_radius', 18 ) ) ) );
$attr['data-tdg-pad']       = esc_attr( max( 0, min( 20, (float) $dp( 'padding', 6 ) ) ) ); // stage inset (bg shows through)
$attr['data-tdg-count']     = esc_attr( count( $items ) );

/* The gaps + empty cells show the page/section behind the stage, which gives the design its airy,
 * floating-card look — so the background is TRANSPARENT unless the user picks one. The parent view
 * resolves $bg from the background field. */
$stage_style = ( isset( $bg ) && '' !== $bg ) ? 'background:' . esc_attr( $bg ) . ';' : '';

/* One card per image; pad small sets so the mosaic reads full. Two identical copies form the loop. */
$slots = $items;
if ( count( $slots ) > 0 ) {
	while ( count( $slots ) < 10 ) { $slots = array_merge( $slots, $items ); }
}
$slots = array_slice( $slots, 0, 24 );
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage" style="<?php echo $stage_style; ?>">
		<div class="tdg__mosaic-track">
			<?php
			/* Per-card Hover (when Hover Target = Each item): $tdg_hover_item (from view.php) carries the
			 * hover class + data-hover* attrs to stamp on every tile. initMosaic clones these cards, so the
			 * clones inherit the hover, and it re-scans hover after building. */
			$hov_cls = '';
			$hov_extra = '';
			if ( ! empty( $tdg_hover_item ) && is_array( $tdg_hover_item ) ) {
				$hi = $tdg_hover_item;
				if ( isset( $hi['class'] ) ) { $hov_cls = ' ' . trim( (string) $hi['class'] ); unset( $hi['class'] ); }
				if ( ! empty( $hi ) ) { $hov_extra = ' ' . fw_attr_to_html( $hi ); }
			}
			?>
			<?php for ( $copy = 0; $copy < 2; $copy++ ) : ?>
				<div class="tdg__mosaic" aria-hidden="<?php echo $copy ? 'true' : 'false'; ?>">
					<?php foreach ( $slots as $item ) {
						echo '<div class="tdg__card' . esc_attr( $hov_cls ) . '"' . $hov_extra . '>' . $render_card( $item ) . '</div>';
					} ?>
				</div>
			<?php endfor; ?>
		</div>
	</div>
</div>
