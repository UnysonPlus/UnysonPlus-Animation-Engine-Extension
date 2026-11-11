<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Dual Orbit (Orbit) — two concentric rings of billboarded photos on one tilted
 * disc. Cards alternate between the outer and inner ring; the inner ring turns the other way (Counter-
 * rotate) at its own speed (Inner Ring Speed), so the two rings gear past each other. Front cards are
 * brighter (Focus Brightness); the far arc dims (Depth Fade). gallery-3d.js (initDualOrbit) does the
 * placement + motion; here we emit one card per image (images cycle). An original UnysonPlus design.
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

$radius  = max( 15, min( 55, (float) $dp( 'radius', 33 ) ) );
$inner   = max( 25, min( 85, (float) $dp( 'inner', 50 ) ) );
$tilt    = max( 0, min( 80, (float) $dp( 'tilt', 62 ) ) );
$counter = $dp( 'counter', 'yes' ) === 'yes' ? 1 : 0;
$ispeed  = max( 50, min( 300, (float) $dp( 'inner_speed', 150 ) ) );
$glow    = max( 100, min( 220, (float) $dp( 'focus_glow', 130 ) ) );
$dfade   = max( 0, min( 100, (float) $dp( 'depth_fade', 45 ) ) );
$csize   = max( 4, min( 60, (float) $dp( 'card_size', 11 ) ) );

$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = esc_attr( $allowdrag );
$attr['data-tdg-momentum']  = esc_attr( $momentum );
$attr['data-tdg-speed']     = esc_attr( $speed );
$attr['data-tdg-dir']       = esc_attr( $dir );
$attr['data-tdg-hover']     = esc_attr( $hover );
$attr['data-tdg-radius']    = esc_attr( $radius );
$attr['data-tdg-inner']     = esc_attr( $inner );
$attr['data-tdg-tilt']      = esc_attr( $tilt );
$attr['data-tdg-counter']   = esc_attr( $counter );
$attr['data-tdg-ispeed']    = esc_attr( $ispeed );
$attr['data-tdg-glow']      = esc_attr( $glow );
$attr['data-tdg-dfade']     = esc_attr( $dfade );
$attr['data-tdg-card']      = esc_attr( $csize );

// Two rings need enough cards to read as rings: repeat the images up to 15 when the gallery is short.
$cards = $items;
if ( count( $cards ) ) {
	$k = 0;
	while ( count( $cards ) < 15 ) { $cards[] = $items[ $k % count( $items ) ]; $k++; }
}
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<div class="tdg__orbit">
			<?php if ( $dp( 'show_paths', 'yes' ) === 'yes' ) { echo '<div class="tdg__track" aria-hidden="true"></div><div class="tdg__track" aria-hidden="true"></div>'; } ?>
			<?php foreach ( $cards as $item ) {
				echo '<div class="tdg__card">' . $render_card( $item ) . '</div>';
			} ?>
		</div>
	</div>
</div>
