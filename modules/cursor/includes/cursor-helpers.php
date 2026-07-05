<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Animation Engine — Cursor module: helpers.
 *
 * Setting reader, the style registry (single source of truth for the picker + validation) and
 * the typography → JS-props helper. Loaded first by cursor.php (the settings + enqueue parts
 * depend on these). All wrapped in function_exists guards.
 */

if ( ! function_exists( 'upw_cursor_setting' ) ) :
	/** Read a Cursor setting from the theme-scoped `animation_cursor` bucket. */
	function upw_cursor_setting( $key, $default = '' ) {
		if ( ! function_exists( 'fw_get_db_settings_option' ) ) {
			return $default;
		}
		$v = fw_get_db_settings_option( 'animation_cursor', array() );
		if ( is_array( $v ) && isset( $v[ $key ] ) && $v[ $key ] !== '' && $v[ $key ] !== null ) {
			return is_bool( $v[ $key ] ) ? ( $v[ $key ] ? 'yes' : 'no' ) : $v[ $key ];
		}
		return $default;
	}
endif;

if ( ! function_exists( 'upw_cursor_styles' ) ) :
	/** style-id => label. Single source of truth for the picker + validation. */
	function upw_cursor_styles() {
		return array(
			'none'     => __( 'None', 'fw' ),
			'dot'      => __( 'Dot', 'fw' ),
			'ring'     => __( 'Ring', 'fw' ),
			'dot_ring' => __( 'Dot + Ring', 'fw' ),
			'crosshair'=> __( 'Crosshair', 'fw' ),
			'brackets' => __( 'Brackets', 'fw' ),
			'square'   => __( 'Square', 'fw' ),
			'dashed'   => __( 'Dashed Ring', 'fw' ),
			'glow'     => __( 'Glow', 'fw' ),
			'gradient' => __( 'Gradient', 'fw' ),
			'blob'     => __( 'Blob', 'fw' ),
			'spotlight'=> __( 'Spotlight', 'fw' ),
			'comet'    => __( 'Comet', 'fw' ),
			'particles'=> __( 'Particle Trail', 'fw' ),
			'elastic'  => __( 'Elastic Ring', 'fw' ),
			'lens'     => __( 'Glass Lens', 'fw' ),
			'arrow'    => __( 'Directional Arrow', 'fw' ),
			'radar'    => __( 'Radar Pulse', 'fw' ),
			'plus'     => __( 'Plus', 'fw' ),
			'star'     => __( 'Sparkle', 'fw' ),
			'diamond'  => __( 'Diamond', 'fw' ),
			'dual_ring'=> __( 'Dual Ring', 'fw' ),
			'bullseye' => __( 'Bullseye', 'fw' ),
			'reticle'  => __( 'Camera Reticle', 'fw' ),
			'invert'   => __( 'Invert Disc', 'fw' ),
			'echo'     => __( 'Afterimage', 'fw' ),
			'firefly'  => __( 'Firefly', 'fw' ),
			'confetti' => __( 'Confetti Trail', 'fw' ),
			'bubble'   => __( 'Bubbles', 'fw' ),
			'spring'   => __( 'Spring Dot', 'fw' ),
			'streak'   => __( 'Motion Streak', 'fw' ),
			'rope'     => __( 'Rubber Band', 'fw' ),
			'metaball' => __( 'Gooey Metaball', 'fw' ),
			'label'    => __( 'Contextual Label', 'fw' ),
			'sticky'   => __( 'Sticky Cursor', 'fw' ),
			'word_trail' => __( 'Word Trail', 'fw' ),
			'reveal'   => __( 'Image Reveal', 'fw' ),
			'magnify'  => __( 'Magnify Lens', 'fw' ),
			'ink'      => __( 'Ink Brush', 'fw' ),
			'fluid'    => __( 'Fluid Smear', 'fw' ),
			'distort'  => __( 'Ripple Trail', 'fw' ),
			'custom'   => __( 'Custom Image', 'fw' ),
			'glyph'    => __( 'Glyph / Emoji', 'fw' ),
		);
	}
endif;

if ( ! function_exists( 'upw_cursor_font_props' ) ) :
	/**
	 * A typography-v2 value → JS style props, and enqueue its Google font if one is chosen.
	 * Shared by the Word Trail and Contextual Label styles.
	 */
	function upw_cursor_font_props( $wf ) {
		$wf = is_array( $wf ) ? $wf : array();
		if ( ! empty( $wf['google_font'] ) && ! empty( $wf['family'] ) ) {
			wp_enqueue_style(
				'upw-cursor-font-' . sanitize_title( $wf['family'] . ( isset( $wf['weight'] ) ? $wf['weight'] : '' ) ),
				'https://fonts.googleapis.com/css?family=' . str_replace( ' ', '+', $wf['family'] ) . ( ! empty( $wf['weight'] ) ? ':' . $wf['weight'] : '' ) . '&display=swap',
				array(),
				null
			);
		}
		return array(
			'family'        => isset( $wf['family'] ) ? (string) $wf['family'] : '',
			'weight'        => isset( $wf['weight'] ) ? (string) $wf['weight'] : '',
			'size'          => isset( $wf['size'] ) ? (int) $wf['size'] : 0,
			'lineHeight'    => ( isset( $wf['line-height'] ) && (int) $wf['line-height'] > 0 ) ? (int) $wf['line-height'] : 0,
			'letterSpacing' => isset( $wf['letter-spacing'] ) ? (int) $wf['letter-spacing'] : 0,
			'style'         => isset( $wf['style'] ) ? (string) $wf['style'] : '',
		);
	}
endif;
