<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Footer Watermark Text — Animation Engine motion effects.
 *
 * Adds four motion effects to the parent theme's Footer → Layout → Watermark Text seam
 * (unysonplus-theme 2.6.55+): Image Fill, Marquee, Parallax and a cursor-follow Sheen. They plug into
 * the theme through its documented filters/action — the theme keeps None/Reveal natively, and these
 * appear only when the Animation Engine is active:
 *
 *   - `unysonplus_footer_wordmark_effects`        — add our ids to the Effect select (id => label).
 *   - `unysonplus_footer_wordmark_effect_options` — each effect's own settings group (id => options).
 *   - `unysonplus_footer_wordmark_fit`            — Marquee opts out of the fit-to-width script.
 *   - `unysonplus_footer_wordmark_rendered`       — load this effect's CSS/JS + its config, on demand.
 *
 * Every effect: loads ONLY when it is the chosen effect (nothing ships otherwise), and switches off
 * under prefers-reduced-motion (CSS for Image Fill pan + Marquee; JS guards for Parallax + Sheen).
 */

if ( ! function_exists( 'upw_fwm_fx_ids' ) ) :

	/** The effect ids this extension owns. */
	function upw_fwm_fx_ids() {
		return array( 'image_fill', 'marquee', 'parallax', 'sheen' );
	}

	// 1. Effect choices in the Effect select (merged after the theme's None / Reveal).
	add_filter( 'unysonplus_footer_wordmark_effects', function ( $effects ) {
		return array_merge(
			(array) $effects,
			array(
				'image_fill' => __( 'Image Fill', 'fw' ),
				'marquee'    => __( 'Marquee', 'fw' ),
				'parallax'   => __( 'Parallax', 'fw' ),
				'sheen'      => __( 'Sheen (cursor light)', 'fw' ),
			)
		);
	} );

	// 2. Per-effect settings — each id's own options group, revealed when that effect is picked.
	add_filter( 'unysonplus_footer_wordmark_effect_options', function ( $opts ) {
		$opts['image_fill'] = array(
			'fwm_fill_image'     => array(
				'type'  => 'upload',
				'label' => __( 'Fill image', 'fw' ),
				'desc'  => __( 'A photo or texture painted inside the letters. (Applies to a TEXT wordmark.)', 'fw' ),
			),
			'fwm_fill_pan'       => array(
				'type'    => 'switch',
				'label'   => __( 'Slow pan', 'fw' ),
				'value'   => 'no',
				'desc'    => __( 'Drift the image inside the letters. Off under reduced motion.', 'fw' ),
			),
			'fwm_fill_pan_speed' => array(
				'type'       => 'slider',
				'label'      => __( 'Pan speed', 'fw' ),
				'value'      => 30,
				'properties' => array( 'min' => 5, 'max' => 100, 'step' => 1 ),
			),
			'fwm_fill_pan_dir'   => array(
				'type'    => 'select',
				'label'   => __( 'Pan direction', 'fw' ),
				'value'   => 'horizontal',
				'choices' => array(
					'horizontal' => __( 'Horizontal', 'fw' ),
					'vertical'   => __( 'Vertical', 'fw' ),
					'diagonal'   => __( 'Diagonal', 'fw' ),
				),
			),
		);
		$opts['marquee'] = array(
			'fwm_mq_speed' => array(
				'type'       => 'slider',
				'label'      => __( 'Scroll speed', 'fw' ),
				'value'      => 40,
				'properties' => array( 'min' => 5, 'max' => 100, 'step' => 1 ),
			),
			'fwm_mq_dir'   => array(
				'type'    => 'select',
				'label'   => __( 'Direction', 'fw' ),
				'value'   => 'left',
				'choices' => array(
					'left'  => __( 'Left', 'fw' ),
					'right' => __( 'Right', 'fw' ),
				),
			),
		);
		$opts['parallax'] = array(
			'fwm_plx_strength' => array(
				'type'       => 'slider',
				'label'      => __( 'Parallax strength', 'fw' ),
				'value'      => 40,
				'properties' => array( 'min' => 0, 'max' => 100, 'step' => 1 ),
				'desc'       => __( 'How much slower the wordmark drifts than the page. Off under reduced motion.', 'fw' ),
			),
		);
		$opts['sheen'] = array(
			'fwm_sheen_color' => array(
				'type'  => 'color-picker',
				'label' => __( 'Light colour', 'fw' ),
				'value' => '#ffffff',
			),
			'fwm_sheen_size'  => array(
				'type'       => 'slider',
				'label'      => __( 'Light size', 'fw' ),
				'value'      => 160,
				'properties' => array( 'min' => 40, 'max' => 500, 'step' => 10 ),
			),
		);
		return $opts;
	} );

	// 3. Marquee scrolls overflowing text, so it needs the natural width — opt out of the fit script.
	add_filter( 'unysonplus_footer_wordmark_fit', function ( $fit, $effect ) {
		return 'marquee' === $effect ? false : $fit;
	}, 10, 2 );

	/** Map an effect's saved settings to the compact runtime config the JS/CSS consume. */
	function upw_fwm_fx_runtime_conf( $fx, $s ) {
		$num = function ( $k, $d ) use ( $s ) { return isset( $s[ $k ] ) ? (float) $s[ $k ] : $d; };
		$str = function ( $k, $d ) use ( $s ) { return isset( $s[ $k ] ) && '' !== $s[ $k ] ? (string) $s[ $k ] : $d; };
		switch ( $fx ) {
			case 'image_fill':
				$img = isset( $s['fwm_fill_image']['url'] ) ? (string) $s['fwm_fill_image']['url'] : ( is_string( $s['fwm_fill_image'] ?? null ) ? $s['fwm_fill_image'] : '' );
				return array(
					'img'   => esc_url_raw( $img ),
					'pan'   => ( 'yes' === ( $s['fwm_fill_pan'] ?? 'no' ) ),
					'speed' => $num( 'fwm_fill_pan_speed', 30 ),
					'dir'   => $str( 'fwm_fill_pan_dir', 'horizontal' ),
				);
			case 'marquee':
				return array( 'speed' => $num( 'fwm_mq_speed', 40 ), 'dir' => $str( 'fwm_mq_dir', 'left' ) );
			case 'parallax':
				return array( 'strength' => $num( 'fwm_plx_strength', 40 ) );
			case 'sheen':
				return array( 'color' => $str( 'fwm_sheen_color', '#ffffff' ), 'size' => $num( 'fwm_sheen_size', 160 ) );
		}
		return array();
	}

	// 4. Load this effect's assets + its config — only when one of our effects is in use.
	add_action( 'unysonplus_footer_wordmark_rendered', function ( $cfg ) {
		$fx = isset( $cfg['effect'] ) ? (string) $cfg['effect'] : '';
		if ( ! in_array( $fx, upw_fwm_fx_ids(), true ) ) {
			return;
		}
		$ext = function_exists( 'fw_ext' ) ? fw_ext( 'animation-engine' ) : null;
		if ( ! $ext ) {
			return;
		}
		$ver  = $ext->manifest->get_version();
		$base = '/static/footer-wordmark';
		wp_enqueue_style( 'upw-fwm-fx', $ext->get_declared_URI( $base . '/footer-wordmark-fx.css' ), array(), $ver );
		wp_enqueue_script( 'upw-fwm-fx', $ext->get_declared_URI( $base . '/footer-wordmark-fx.js' ), array(), $ver, true );

		// Hand this instance's config to the runtime (paired to its wrapper in DOM order).
		$s    = isset( $cfg['effect_settings'] ) && is_array( $cfg['effect_settings'] ) ? $cfg['effect_settings'] : array();
		$conf = upw_fwm_fx_runtime_conf( $fx, $s );
		echo '<script>(window.__upwFwmFx=window.__upwFwmFx||[]).push(' . wp_json_encode( array( 'fx' => $fx, 's' => $conf ) ) . ');</script>';
	} );

endif;
