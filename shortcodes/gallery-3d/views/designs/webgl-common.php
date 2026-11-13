<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Motion Gallery — shared markup for the WebGL designs. Each card carries its full-size image URL
 * (data-tdg-full) for the designs that show one photo at the size of the stage.
 *
 * The photos are drawn by static/js/gallery-3d-webgl.js on a canvas it adds to the stage. The cards below
 * stay in the page as the accessible list: visually hidden while WebGL runs (still focusable; focusing one
 * brings its photo forward), and shown as a plain grid when WebGL is not available. Each design file sets its
 * own data-tdg-* attributes and then includes this.
 *
 * @var array    $items, $attr
 * @var callable $render_card, $card_style
 * @var int      $webgl_count  how many cards to emit (the pool repeats to fill it)
 */

$n     = count( $items );
$count = isset( $webgl_count ) ? (int) $webgl_count : $n;
$count = max( 1, min( 60, $count ) );
?>
<div <?php echo fw_attr_to_html( $attr ); ?>>
	<div class="tdg__stage">
		<ul class="tdg__grid tdg__webgl-list" role="list">
			<?php
			// One list entry per distinct photo (the canvas repeats them to fill the scene).
			for ( $i = 0; $n && $i < min( $n, $count ); $i++ ) {
				$full_url = ! empty( $items[ $i ]['full'] ) ? $items[ $i ]['full'] : '';
				echo '<li class="tdg__card"' . ( $full_url !== '' ? ' data-tdg-full="' . esc_url( $full_url ) . '"' : '' ) . $card_style( $items[ $i ] ) . '>' . $render_card( $items[ $i ] ) . '</li>';
			} ?>
		</ul>
	</div>
</div>
