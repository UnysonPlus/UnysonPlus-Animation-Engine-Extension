<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Ring Tilt (Orbit) — a steeply tilted flat ring, like a planet's rings. Unlike
 * the other Orbit designs the photos are NOT turned to face the camera: they lie in the ring plane, top
 * edge pointing outward, and stand up by Card Lift (0 = flat, 90 = upright). The ring turns around an
 * optional shaded planet (Show Planet) and slowly rocks and precesses (Wobble). gallery-3d.js
 * (initRingTilt) does the placement + motion; here we emit one card per image (images cycle) plus the
 * planet. An original UnysonPlus design.
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

$radius = max( 15, min( 55, (float) $dp( 'radius', 40 ) ) );
$tilt   = max( 30, min( 85, (float) $dp( 'tilt', 70 ) ) );
$lift   = max( 0, min( 90, (float) $dp( 'lift', 20 ) ) );
$wobble = max( 0, min( 25, (float) $dp( 'wobble', 8 ) ) );
$core   = $dp( 'show_core', 'yes' ) === 'yes';
$csz    = max( 10, min( 70, (float) $dp( 'core_size', 34 ) ) );
$ccol   = (string) $dp( 'core_color', '#2f74e6' );
if ( ! preg_match( '/^#[0-9a-fA-F]{3,8}$/', $ccol ) ) { $ccol = '#2f74e6'; }
$glow   = max( 100, min( 220, (float) $dp( 'focus_glow', 125 ) ) );
$dfade  = max( 0, min( 100, (float) $dp( 'depth_fade', 50 ) ) );
$csize  = max( 4, min( 60, (float) $dp( 'card_size', 12 ) ) );

$attr['data-tdg-drive']     = esc_attr( $drive );
$attr['data-tdg-allowdrag'] = esc_attr( $allowdrag );
$attr['data-tdg-momentum']  = esc_attr( $momentum );
$attr['data-tdg-speed']     = esc_attr( $speed );
$attr['data-tdg-dir']       = esc_attr( $dir );
$attr['data-tdg-hover']     = esc_attr( $hover );
$attr['data-tdg-radius']    = esc_attr( $radius );
$attr['data-tdg-tilt']      = esc_attr( $tilt );
$attr['data-tdg-lift']      = esc_attr( $lift );
$attr['data-tdg-wobble']    = esc_attr( $wobble );
$attr['data-tdg-core']      = esc_attr( $csz );
$attr['data-tdg-glow']      = esc_attr( $glow );
$attr['data-tdg-dfade']     = esc_attr( $dfade );
$attr['data-tdg-card']      = esc_attr( $csize );

// A ring reads as a ring only with enough tiles: repeat the images up to 16 when the gallery is short.
$cards = $items;
if ( count( $cards ) ) {
	$k = 0;
	while ( count( $cards ) < 16 ) { $cards[] = $items[ $k % count( $items ) ]; $k++; }
}
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<div class="tdg__orbit">
			<?php if ( $core ) { echo '<div class="tdg__planet" aria-hidden="true" style="--tdg-core:' . esc_attr( $ccol ) . '"></div>'; } ?>
			<?php foreach ( $cards as $item ) {
				echo '<div class="tdg__card">' . $render_card( $item ) . '</div>';
			} ?>
		</div>
	</div>
</div>
