<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Flip Grid (Grid) — a face-on Columns × Rows grid of two-sided cards. Every Loop
 * Duration a wave of 3D flips (Flip Order: a diagonal wave, random, or row by row) turns each card over
 * (Flip: sideways or head over heels) to show the next photo, so a small grid cycles through the whole
 * gallery. Each card's two faces are rendered here (face A = photo j, face B = the photo it flips to);
 * a hidden pool holds every photo once, so JS can refill a hidden face before it turns into view and the
 * lightbox can step through all of them. gallery-3d.js (initFlipGrid) does the placement + motion. An
 * original UnysonPlus design.
 *
 * @var callable $dp, $render_card
 * @var array    $items, $attr
 * @var bool     $as_bg
 * @var string   $motion_mode, $motion_dir, $motion_hover
 * @var float    $motion_speed
 * @var bool     $motion_legacy_drag
 */

$drive     = $motion_mode;
$allowdrag = ( $dp( 'allow_drag', 'no' ) === 'yes' || $motion_legacy_drag ) ? 1 : 0;
if ( ! empty( $as_bg ) ) { $drive = 'continuous'; $allowdrag = 0; }
$momentum = $dp( 'drag_momentum', 'yes' ) === 'yes' ? 1 : 0;
$speed  = max( 2, min( 15, $motion_speed ) );
$dir    = ( $motion_dir === 'back' ) ? -1 : 1;
$hover  = $motion_hover;

$cols   = (int) max( 1, min( 10, round( (float) $dp( 'columns', 4 ) ) ) );
$rows   = (int) max( 1, min( 8, round( (float) $dp( 'rows', 3 ) ) ) );
$gap    = max( 0, min( 60, (float) $dp( 'gap', 8 ) ) );
$axis   = $dp( 'flip_axis', 'y' ) === 'x' ? 'x' : 'y';
$order  = array_search( (string) $dp( 'order', 'wave' ), array( 'wave', 'random', 'rows' ), true );
$order  = ( $order === false ) ? 0 : $order;
$tilt   = max( 0, min( 40, (float) $dp( 'tilt', 8 ) ) );
$csize  = max( 4, min( 40, (float) $dp( 'card_size', 15 ) ) );

$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = esc_attr( $allowdrag );
$attr['data-tdg-momentum']  = esc_attr( $momentum );
$attr['data-tdg-speed']     = esc_attr( $speed );
$attr['data-tdg-dir']       = esc_attr( $dir );
$attr['data-tdg-hover']     = esc_attr( $hover );
$attr['data-tdg-cols']      = esc_attr( $cols );
$attr['data-tdg-gap']       = esc_attr( $gap );
$attr['data-tdg-axis']      = esc_attr( $axis );
$attr['data-tdg-order']     = esc_attr( $order );
$attr['data-tdg-tilt']      = esc_attr( $tilt );
$attr['data-tdg-card']      = esc_attr( $csize );
if ( $axis === 'x' ) { $attr['class'] = trim( ( isset( $attr['class'] ) ? $attr['class'] : '' ) . ' tdg--flip-x' ); }

$n     = count( $items );
$cells = $cols * $rows;
// Same mapping as gallery-3d.js imgFor(): which photo cell $j shows on page $p.
$img_for = function ( $j, $p ) use ( $n, $cells ) { return $n ? ( ( $j + $p * ( $cells + 1 ) ) % $n + $n ) % $n : 0; };
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<div class="tdg__grid">
			<?php
			for ( $j = 0; $n && $j < $cells; $j++ ) {
				echo '<div class="tdg__card">'
					. '<div class="tdg__face tdg__face--a">' . $render_card( $items[ $img_for( $j, 0 ) ] ) . '</div>'
					. '<div class="tdg__face tdg__face--b" aria-hidden="true" inert>' . $render_card( $items[ $img_for( $j, 1 ) ] ) . '</div>'
					. '</div>';
			} ?>
		</div>
	</div>
	<div class="tdg__pool" hidden>
		<?php foreach ( $items as $item ) { echo '<div class="tdg__poolitem">' . $render_card( $item ) . '</div>'; } ?>
	</div>
</div>
