<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Studio Scene — plays a scene made in the Unyson+ Animation Studio.
 *
 * The scene file is stored as text in the `scene` att. The bundled scene player (static/js/vendor/
 * scene-player.js, a copy of the Studio's published player) mounts every `[data-upw-scene]` block on
 * the page. In the builder, "Open in Animation Studio" opens the Studio in a new window and the two
 * talk over postMessage: the Studio says hello, we send it this element's scene, and its "Save to
 * page" sends the edited scene back into the field. We only accept messages from the Studio's own
 * address; the Studio only sends back to the window that opened it.
 *
 * Helpers here are prefixed upw_studio_scene_ and loaded once, at registration.
 */

/** The Animation Studio's address (filterable, e.g. to a local copy while developing the Studio). */
function upw_studio_scene_studio_url() {
	/**
	 * Filters the Animation Studio address used by "Open in Animation Studio".
	 *
	 * @param string $url The Studio URL, ending in a slash.
	 */
	return trailingslashit( apply_filters( 'fw_studio_scene_studio_url', 'https://animation-studio.unysonplus.com/' ) );
}

/**
 * Reads the `scene` att: a scene file, or the Studio's embed code (the scene sits in its
 * <script type="application/json">). Returns the decoded scene array, or null if it isn't one.
 *
 * @param string $raw
 * @return array|null
 */
function upw_studio_scene_parse( $raw ) {
	$raw = trim( (string) $raw );
	if ( $raw === '' ) {
		return null;
	}
	if ( strpos( $raw, '<script' ) !== false && preg_match( '#<script[^>]*type=["\']application/json["\'][^>]*>(.*?)</script>#is', $raw, $m ) ) {
		$raw = trim( $m[1] );
	}
	$scene = json_decode( $raw, true );
	if ( ! is_array( $scene ) || ( $scene['format'] ?? '' ) !== 'unysonplus-scene' || ! isset( $scene['layers'] ) || ! is_array( $scene['layers'] ) ) {
		return null;
	}
	return $scene;
}

/**
 * The scene's words as plain text for search engines and screen readers, in reading order: the headline
 * as a heading, each act's headline one level below, the rest as paragraphs. A port of the player's
 * sceneTranscript() (packages/player/src/transcript.ts in the Studio); keep the two in step.
 *
 * @param array  $scene    A parsed scene.
 * @param string $headline 'h1' | 'h2' | 'h3' | 'p' (the scene's own setting when empty).
 * @return array List of array( 'level' => 0-6, 'text' => string ); empty for a decorative scene.
 */
function upw_studio_scene_transcript_items( array $scene, $headline = '' ) {
	$t = isset( $scene['transcript'] ) && is_array( $scene['transcript'] ) ? $scene['transcript'] : array();
	if ( ! empty( $t['decorative'] ) ) {
		return array();
	}
	$levels = array( 'h1', 'h2', 'h3', 'p' );
	if ( ! in_array( $headline, $levels, true ) ) {
		$headline = isset( $t['headline'] ) && in_array( $t['headline'], $levels, true ) ? $t['headline'] : 'h2';
	}
	$top   = 'p' === $headline ? 0 : (int) substr( $headline, 1 );
	$clean = function ( $s ) {
		return trim( preg_replace( '/\s+/u', ' ', (string) $s ) );
	};
	$num   = function ( $v ) {
		return is_numeric( $v ) ? (float) $v : 0.0;
	};

	// Every text layer (and 3D text), with when it appears and which top-level container (act) it is in.
	$entries = array();
	$walk    = function ( array $list, $offset, $container ) use ( &$walk, &$entries, $clean, $num ) {
		foreach ( array_values( $list ) as $i => $l ) {
			if ( ! is_array( $l ) ) {
				continue;
			}
			$type = isset( $l['type'] ) ? $l['type'] : '';
			$c    = $container < 0 ? ( 'group' === $type ? $i : -1 ) : $container;
			$own  = $num( isset( $l['start'] ) ? $l['start'] : 0 );
			$ins  = array();
			foreach ( ( isset( $l['clips'] ) && is_array( $l['clips'] ) ? $l['clips'] : array() ) as $clip ) {
				if ( isset( $clip['params']['direction'] ) && 'out' === $clip['params']['direction'] ) {
					continue;
				}
				$ins[] = $num( isset( $clip['start'] ) ? $clip['start'] : 0 );
			}
			$at  = $offset + max( $own, $ins ? min( $ins ) : $own );
			$box = isset( $l['box'] ) && is_array( $l['box'] ) ? $l['box'] : array();
			$y   = $num( isset( $box['y'] ) ? $box['y'] : 0 );
			$x   = $num( isset( $box['x'] ) ? $box['x'] : 0 );
			if ( 'text' === $type && '' !== $clean( isset( $l['text'] ) ? $l['text'] : '' ) ) {
				$entries[] = array( 'text' => $clean( $l['text'] ), 'size' => $num( isset( $l['style']['fontSize'] ) ? $l['style']['fontSize'] : 0 ), 'at' => $at, 'y' => $y, 'x' => $x, 'container' => $c, 'model' => false );
			}
			if ( 'model' === $type && isset( $l['model']['kind'] ) && 'text' === $l['model']['kind'] && '' !== $clean( isset( $l['model']['text'] ) ? $l['model']['text'] : '' ) ) {
				$entries[] = array( 'text' => $clean( $l['model']['text'] ), 'size' => 0.0, 'at' => $at, 'y' => $y, 'x' => $x, 'container' => $c, 'model' => true );
			}
			if ( 'group' === $type && isset( $l['layers'] ) && is_array( $l['layers'] ) ) {
				$walk( $l['layers'], $offset + $own, $c );
			}
		}
	};
	$walk( isset( $scene['layers'] ) && is_array( $scene['layers'] ) ? $scene['layers'] : array(), 0.0, -1 );
	if ( ! $entries ) {
		return array();
	}

	// Sizes: the median text size, and the biggest per container; 3D text counts as its container's headline.
	$flat = array();
	foreach ( $entries as $e ) {
		if ( ! $e['model'] ) {
			$flat[] = $e['size'];
		}
	}
	sort( $flat );
	$median = $flat ? $flat[ (int) floor( count( $flat ) / 2 ) ] : 1.0;
	$max_in = array();
	foreach ( $entries as $e ) {
		if ( ! $e['model'] ) {
			$max_in[ $e['container'] ] = max( isset( $max_in[ $e['container'] ] ) ? $max_in[ $e['container'] ] : 0.0, $e['size'] );
		}
	}
	foreach ( $entries as $k => $e ) {
		if ( $e['model'] ) {
			$entries[ $k ]['size']     = max( isset( $max_in[ $e['container'] ] ) ? $max_in[ $e['container'] ] : 0.0, $median * 1.5 );
			$max_in[ $e['container'] ] = $entries[ $k ]['size'];
		}
	}

	// By time, then top to bottom, then left to right; ties keep their order (as the player's stable sort).
	$keys = array_keys( $entries );
	usort( $keys, function ( $a, $b ) use ( $entries ) {
		foreach ( array( 'at', 'y', 'x' ) as $f ) {
			if ( $entries[ $a ][ $f ] < $entries[ $b ][ $f ] ) {
				return -1;
			}
			if ( $entries[ $a ][ $f ] > $entries[ $b ][ $f ] ) {
				return 1;
			}
		}
		return $a - $b;
	} );
	$sorted = array();
	foreach ( $keys as $k ) {
		$sorted[] = $entries[ $k ];
	}
	$entries = $sorted;

	$scene_max  = max( $max_in );
	$scene_head = null;
	foreach ( $entries as $e ) {
		if ( $max_in[ $e['container'] ] >= $scene_max ) {
			$scene_head = $e['container'];
			break;
		}
	}
	$is_big     = function ( $e ) use ( $max_in, $median, $scene_head ) {
		return $e['size'] >= $max_in[ $e['container'] ] * 0.75 && ( $e['container'] === $scene_head || $e['size'] >= $median * 1.4 );
	};
	$is_display = function ( $e ) use ( $max_in, $median ) {
		return $e['size'] >= $max_in[ $e['container'] ] * 0.3 && $e['size'] >= $median * 1.4;
	};

	$items = array();
	$seen  = array();
	$n     = count( $entries );
	for ( $i = 0; $i < $n; $i++ ) {
		$e = $entries[ $i ];
		if ( $is_display( $e ) || $is_big( $e ) ) {
			// A run of display lines in one container reads as one heading when it holds the headline.
			$j = $i;
			while ( $j + 1 < $n && $entries[ $j + 1 ]['container'] === $e['container'] && ( $is_display( $entries[ $j + 1 ] ) || $is_big( $entries[ $j + 1 ] ) ) ) {
				$j++;
			}
			$run     = array_slice( $entries, $i, $j - $i + 1 );
			$has_big = false;
			foreach ( $run as $r ) {
				$has_big = $has_big || $is_big( $r );
			}
			if ( $top && $has_big ) {
				$text = implode( ' ', array_map( function ( $r ) {
					return $r['text'];
				}, $run ) );
				if ( ! isset( $seen[ $text ] ) ) {
					$seen[ $text ] = true;
					$items[]       = array( 'level' => $e['container'] === $scene_head ? $top : min( 6, $top + 1 ), 'text' => $text );
				}
				$i = $j;
				continue;
			}
		}
		if ( isset( $seen[ $e['text'] ] ) ) {
			continue;
		}
		$seen[ $e['text'] ] = true;
		$items[]            = array( 'level' => 0, 'text' => $e['text'] );
	}
	return $items;
}

/**
 * The transcript as a visually hidden block (the player keeps it and hides the animated copy from
 * screen readers). Empty for a decorative scene or one without text.
 *
 * @param array  $scene
 * @param string $headline See upw_studio_scene_transcript_items().
 * @return string HTML.
 */
function upw_studio_scene_transcript_html( array $scene, $headline = '' ) {
	$items = upw_studio_scene_transcript_items( $scene, $headline );
	if ( ! $items ) {
		return '';
	}
	$html = '';
	foreach ( $items as $item ) {
		$tag   = $item['level'] ? 'h' . (int) $item['level'] : 'p';
		$html .= '<' . $tag . '>' . esc_html( $item['text'] ) . '</' . $tag . '>';
	}
	$hidden = 'position:absolute;width:1px;height:1px;margin:-1px;padding:0;border:0;overflow:hidden;clip:rect(0 0 0 0);clip-path:inset(50%);white-space:nowrap';
	return '<div class="upw-scene-text" style="' . esc_attr( $hidden ) . '">' . $html . '</div>';
}

/** The builder buttons + live summary under the Scene field (rendered by an `html` option). */
function upw_studio_scene_tools_html() {
	return '<div class="upw-studio-tools" data-studio="' . esc_attr( upw_studio_scene_studio_url() ) . '">'
		. '<div class="upw-studio-tools__row">'
		. '<button type="button" class="button button-primary upw-studio-open">' . esc_html__( 'Open in Animation Studio', 'fw' ) . '</button>'
		. '<a class="upw-studio-new" href="' . esc_url( upw_studio_scene_studio_url() ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open the Studio in a new tab', 'fw' ) . '</a>'
		. '</div>'
		. '<p class="upw-studio-status" role="status" aria-live="polite"></p>'
		. '<p class="upw-studio-hint">' . esc_html__( 'The Studio opens in a new window with this scene loaded. Click “Save to page” there to bring your changes back, then update the page.', 'fw' ) . '</p>'
		. '</div>';
}

class FW_Shortcode_Studio_Scene extends FW_Shortcode {

	public function _init() {
		// The builder's edit window: the round-trip script, loaded wherever shortcode admin scripts load.
		add_action( 'fw:ext:shortcodes:enqueue-shortcodes-admin-scripts', array( $this, '_action_admin_scripts' ) );
	}

	public function _action_admin_scripts() {
		$ext = fw_ext( 'animation-engine' );
		if ( ! $ext ) {
			return;
		}
		$base = $ext->get_declared_URI( '/shortcodes/studio-scene/static' );
		$dir  = __DIR__ . '/static';
		$ver  = $ext->manifest->get_version();
		$jsv  = file_exists( $dir . '/js/studio-scene-admin.js' ) ? $ver . '.' . filemtime( $dir . '/js/studio-scene-admin.js' ) : $ver;
		$cssv = file_exists( $dir . '/css/studio-scene-admin.css' ) ? $ver . '.' . filemtime( $dir . '/css/studio-scene-admin.css' ) : $ver;

		wp_enqueue_style( 'fw-studio-scene-admin', $base . '/css/studio-scene-admin.css', array(), $cssv );
		wp_enqueue_script( 'fw-studio-scene-admin', $base . '/js/studio-scene-admin.js', array( 'jquery' ), $jsv, true );
		wp_localize_script( 'fw-studio-scene-admin', 'upwStudioScene', array(
			'studioUrl' => upw_studio_scene_studio_url(),
			'i18n'      => array(
				'empty'       => __( 'No scene yet. Paste one above, or open the Studio to make one.', 'fw' ),
				'invalid'     => __( 'This isn’t a scene the player understands. Paste a scene file or the Studio’s embed code.', 'fw' ),
				/* translators: 1: scene title, 2: number of layers, 3: length in seconds */
				'summary'     => __( '“%1$s”: %2$s layers, %3$s s', 'fw' ),
				'untitled'    => __( 'Untitled scene', 'fw' ),
				'waiting'     => __( 'Opening the Studio…', 'fw' ),
				'connected'   => __( 'The Studio is open with this scene. Click “Save to page” there to bring changes back.', 'fw' ),
				'saved'       => __( 'Scene updated from the Studio. Update the page to keep it.', 'fw' ),
				'blocked'     => __( 'The browser blocked the Studio window. Allow pop-ups for this site, then try again.', 'fw' ),
			),
		) );
	}
}
