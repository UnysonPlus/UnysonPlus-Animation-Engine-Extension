<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery design: Scroll Choreography.
 *
 * A single gallery that morphs through an ordered list of FORMATIONS as the visitor scrolls a pinned
 * stage: the cards start as a stack, form into one arrangement, reform into another, then exit. Each
 * formation is a per-card pose; the runtime (initChoreo in gallery-3d.js) lerps every card between the
 * two bounding formations at the current scroll progress — generalizing photo_scatter's scatter→tidy
 * morph to N formations. The chosen Sequence preset + card size ride as data attributes; the pin and
 * scroll length are set centrally in view.php (this design forces Scroll-scrub + Pin).
 *
 * @var callable $dp          per-design option reader
 * @var callable $render_card renders one card's inner markup
 * @var array    $items       normalized image items
 * @var array    $attr        wrapper attributes (base class + style vars)
 */

/* Read the ordered Steps (each: a formation + its own hold). This is the per-step config the user
 * builds — add/reorder steps, set how long each look holds before morphing to the next. */
/* The accepted formation keys come from the SAME filterable list the Steps picker uses, so a design /
 * child-theme formation (registered via the `fw_gallery_3d_choreo_formations` filter + JS
 * registerChoreoForm) passes the whitelist without a core edit. */
$choreo_builtin = array( 'stack', 'grid', 'ring', 'fan', 'wall', 'spiral', 'wave', 'tunnel', 'carousel', 'column', 'sphere', 'coverflow', 'filmstrip', 'orbit', 'hero', 'focus', 'depthstack', 'mosaic', 'scatter' );
$choreo_valid   = array_keys( apply_filters( 'fw_gallery_3d_choreo_formations', array_fill_keys( $choreo_builtin, '' ) ) );
$steps_conf = $dp( 'steps', array() );
$seq   = array();
$holds = array();
if ( is_array( $steps_conf ) ) {
	foreach ( $steps_conf as $st ) {
		if ( ! is_array( $st ) ) { continue; }
		$f = isset( $st['formation'] ) ? (string) $st['formation'] : '';
		if ( ! in_array( $f, $choreo_valid, true ) ) { continue; }
		$seq[]   = $f;
		$holds[] = isset( $st['hold'] ) ? max( 0, min( 80, (int) $st['hold'] ) ) : 15;
	}
}
if ( count( $seq ) < 2 ) { // fall back to a sensible default if misconfigured
	$seq   = array( 'stack', 'grid', 'ring', 'scatter' );
	$holds = array( 8, 22, 22, 0 );
}
$stagger = max( 0, min( 60, (int) $dp( 'stagger', 25 ) ) );
$easing  = in_array( $dp( 'easing', 'smooth' ), array( 'smooth', 'back', 'out', 'in', 'linear' ), true ) ? $dp( 'easing', 'smooth' ) : 'smooth';
$card    = max( 8, min( 40, (float) $dp( 'card_size', 16 ) ) );

/* Soft cap — a choreography reads best with a handful of cards, and every card is posed every frame. */
if ( count( $items ) > 24 ) { $items = array_slice( $items, 0, 24 ); }

$attr['data-tdg-choreo']  = esc_attr( implode( ',', $seq ) );
$attr['data-tdg-holds']   = esc_attr( implode( ',', $holds ) );
$attr['data-tdg-len']     = esc_attr( max( 1, min( 6, (float) $dp( 'scroll_length', 3 ) ) ) );
$attr['data-tdg-stagger'] = esc_attr( $stagger );
$attr['data-tdg-easing']  = esc_attr( $easing );
$attr['data-tdg-card']    = esc_attr( $card );
$attr['data-tdg-count']   = esc_attr( count( $items ) );
/* Chain flag — when on, the runtime auto-groups this gallery with the adjacent chained galleries
 * (same parent) into one overlap sequence, no container css_class needed. */
if ( $dp( 'chain', false ) ) {
	$attr['data-tdg-chain'] = '1';
}
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<?php foreach ( $items as $item ) { echo '<div class="tdg__card">' . $render_card( $item ) . '</div>'; } ?>
	</div>
</div>
