<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Vortex (Orbit) — a top-down whirlpool. Billboarded photos flow along a few
 * spiral arms (Arms, Twist) from the rim into a glowing eye (Show Eye Glow), sinking (Depth), shrinking
 * and darkening (Fade to Eye) on the way, then fade out and re-enter at the rim. Motion direction is
 * Inward or Outward. gallery-3d.js (initVortex) does the placement + motion. An original UnysonPlus design.
 *
 * @var callable $dp, $render_card
 * @var array    $items, $attr
 * @var bool     $as_bg
 * @var string   $motion_mode, $motion_dir, $motion_hover
 * @var float    $motion_speed
 * @var bool     $motion_legacy_drag
 */

$drive     = $motion_mode;
$allowdrag = ( $dp( 'allow_drag', 'yes' ) === 'yes' || $motion_legacy_drag ) ? 1 : 0;
if ( ! empty( $as_bg ) ) { $drive = 'continuous'; $allowdrag = 0; }
$momentum = $dp( 'drag_momentum', 'yes' ) === 'yes' ? 1 : 0;
$speed  = max( 6, min( 60, $motion_speed ) );
$dir    = ( $motion_dir === 'out' ) ? -1 : 1;
$hover  = $motion_hover;

$radius = max( 15, min( 55, (float) $dp( 'radius', 42 ) ) );
$arms   = (int) max( 1, min( 6, round( (float) $dp( 'arms', 3 ) ) ) );
$twist  = max( -3, min( 3, (float) $dp( 'twist', 0.8 ) ) );
$depth  = max( 0, min( 100, (float) $dp( 'depth', 60 ) ) );
$tilt   = max( 0, min( 80, (float) $dp( 'tilt', 52 ) ) );
$dfade  = max( 0, min( 100, (float) $dp( 'depth_fade', 55 ) ) );
$csize  = max( 4, min( 60, (float) $dp( 'card_size', 8 ) ) );

$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = esc_attr( $allowdrag );
$attr['data-tdg-momentum']  = esc_attr( $momentum );
$attr['data-tdg-speed']     = esc_attr( $speed );
$attr['data-tdg-dir']       = esc_attr( $dir );
$attr['data-tdg-hover']     = esc_attr( $hover );
$attr['data-tdg-radius']    = esc_attr( $radius );
$attr['data-tdg-arms']      = esc_attr( $arms );
$attr['data-tdg-twist']     = esc_attr( $twist );
$attr['data-tdg-depth']     = esc_attr( $depth );
$attr['data-tdg-tilt']      = esc_attr( $tilt );
$attr['data-tdg-dfade']     = esc_attr( $dfade );
$attr['data-tdg-card']      = esc_attr( $csize );

// The arms only read as spirals with enough cards on them: repeat the images up to 24.
$cards = $items;
if ( count( $cards ) ) {
	$k = 0;
	while ( count( $cards ) < 24 ) { $cards[] = $items[ $k % count( $items ) ]; $k++; }
}
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<div class="tdg__orbit">
			<?php if ( $dp( 'show_core', 'yes' ) === 'yes' ) { echo '<div class="tdg__focus" aria-hidden="true"></div>'; } ?>
			<?php foreach ( $cards as $item ) {
				echo '<div class="tdg__card">' . $render_card( $item ) . '</div>';
			} ?>
		</div>
	</div>
</div>
