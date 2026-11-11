<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Orbit Swarm (Orbit) — every billboarded photo keeps its own orbit around one
 * centre: its own radius (Spread), its own pace (Swirl: inner orbits run faster, so cards overtake each
 * other) and its own rise and sink above the plane (Height Drift). Now and then the swarm gathers toward
 * the centre and scatters back out (Gather). gallery-3d.js (initOrbitSwarm) does the placement + motion;
 * here we emit one card per image (images cycle). An original UnysonPlus design.
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
$dir    = ( $motion_dir === 'ccw' ) ? -1 : 1;
$hover  = $motion_hover;

$radius   = max( 15, min( 55, (float) $dp( 'radius', 31 ) ) );
$spread   = max( 0, min( 90, (float) $dp( 'spread', 55 ) ) );
$swirl    = max( 0, min( 100, (float) $dp( 'swirl', 60 ) ) );
$height   = max( 0, min( 100, (float) $dp( 'height', 30 ) ) );
$cohesion = max( 0, min( 100, (float) $dp( 'cohesion', 45 ) ) );
$tilt     = max( 0, min( 80, (float) $dp( 'tilt', 55 ) ) );
$glow     = max( 100, min( 220, (float) $dp( 'focus_glow', 130 ) ) );
$dfade    = max( 0, min( 100, (float) $dp( 'depth_fade', 45 ) ) );
$csize    = max( 4, min( 60, (float) $dp( 'card_size', 9 ) ) );

$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = esc_attr( $allowdrag );
$attr['data-tdg-momentum']  = esc_attr( $momentum );
$attr['data-tdg-speed']     = esc_attr( $speed );
$attr['data-tdg-dir']       = esc_attr( $dir );
$attr['data-tdg-hover']     = esc_attr( $hover );
$attr['data-tdg-radius']    = esc_attr( $radius );
$attr['data-tdg-spread']    = esc_attr( $spread );
$attr['data-tdg-swirl']     = esc_attr( $swirl );
$attr['data-tdg-height']    = esc_attr( $height );
$attr['data-tdg-cohesion']  = esc_attr( $cohesion );
$attr['data-tdg-tilt']      = esc_attr( $tilt );
$attr['data-tdg-glow']      = esc_attr( $glow );
$attr['data-tdg-dfade']     = esc_attr( $dfade );
$attr['data-tdg-card']      = esc_attr( $csize );

// A swarm needs a crowd: repeat the images up to 16 when the gallery is short.
$cards = $items;
if ( count( $cards ) ) {
	$k = 0;
	while ( count( $cards ) < 16 ) { $cards[] = $items[ $k % count( $items ) ]; $k++; }
}
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<div class="tdg__orbit">
			<?php foreach ( $cards as $item ) {
				echo '<div class="tdg__card">' . $render_card( $item ) . '</div>';
			} ?>
		</div>
	</div>
</div>
