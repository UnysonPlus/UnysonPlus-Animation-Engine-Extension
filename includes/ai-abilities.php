<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * AI Assistant abilities for the Animation Engine.
 *
 *   animation-effects       the element effects (entrance, scroll effect, scroll reveal, hover, text
 *                           effects, parallax, physics …), their choices, and — for one effect — its settings
 *   animation-apply         apply (or remove) an effect on a page item by path; the effect name and every
 *                           setting are checked against the element's own effect options; works in the
 *                           builder panel (sandbox) and on saved pages (revision first)
 *   animation-site-modules  the site-wide modules (custom cursor, page transitions, preloader, scroll
 *                           progress, smooth scroll …) and whether each is on — switch them with the AI
 *                           Assistant's update_theme_settings, e.g. { animation_cursor: { enable: "yes" } }
 *   animation-gallery-designs  the Motion Gallery (gallery_3d) designs available — the 3D / flow / stack
 *                           layouts a gallery can take, incl. child-theme-registered custom designs — so an
 *                           agent replicating a source carousel/slider/3D gallery can try a native one first
 *
 * Effect values are the field's saved multi-picker value: { <picker>: "<effect>", "<effect>": { settings } },
 * and "none" (the field's off value) removes the effect.
 */

if ( ! function_exists( 'fw_ext_animation_engine_ai_register' ) ) :

	/** Site-wide module option ids (Theme Settings → Site-wide UX) with a short description. */
	function fw_ext_animation_engine_ai_modules() {
		return array(
			'animation_cursor'         => 'Custom cursor (dot, ring, blend …) across the site',
			'animation_pt'             => 'Page transitions between pages',
			'animation_preloader'      => 'A preloader shown while a page loads',
			'animation_scrollprog'     => 'A scroll-progress bar',
			'animation_smoothscroll'   => 'Smooth (inertia) scrolling',
			'animation_viewport_units' => 'Mobile viewport-height fix for full-height sections',
		);
	}

	/**
	 * The effect fields an element offers: id => { option, picker, off, label }.
	 *
	 * @param string $tag Element tag (or layout type).
	 * @return array
	 */
	/**
	 * Motion Gallery (gallery_3d) designs flattened to { key, label, group, coming_soon, custom }. Sourced
	 * from the shortcode's OWN options, so a child-theme design registered via the 'upw_gallery_3d_design_groups'
	 * filter appears here too. soon_* keys are placeholder families (render as a fallback, not their own
	 * design) and are flagged coming_soon.
	 *
	 * @return array list of { key, label, group, coming_soon, custom }
	 */
	function fw_ext_animation_engine_ai_gallery_designs() {
		$sc = function_exists( 'fw_ext' ) ? fw_ext( 'shortcodes' ) : null;
		$g3 = ( $sc && method_exists( $sc, 'get_shortcode' ) ) ? $sc->get_shortcode( 'gallery_3d' ) : null;
		if ( ! $g3 || ! method_exists( $g3, 'get_options' ) ) {
			return array();
		}
		$opts   = $g3->get_options();
		$groups = $opts['tab_design']['options']['design_settings']['picker']['design']['choices'] ?? array();
		// Keys a child theme added to the render whitelist (the filter applied to an EMPTY base returns only
		// the custom-registered designs), used to flag which designs are custom.
		$custom = array_values( (array) apply_filters( 'upw_gallery_3d_render_designs', array() ) );
		$out = array();
		foreach ( (array) $groups as $gkey => $group ) {
			$glabel  = ( is_array( $group ) && isset( $group['label'] ) ) ? wp_strip_all_tags( $group['label'] ) : (string) $gkey;
			$choices = ( is_array( $group ) && isset( $group['choices'] ) ) ? $group['choices'] : array();
			foreach ( (array) $choices as $dkey => $tile ) {
				$label = ( is_array( $tile ) && ! empty( $tile['label'] ) ) ? wp_strip_all_tags( $tile['label'] ) : (string) $dkey;
				$out[] = array(
					'key'         => (string) $dkey,
					'label'       => $label,
					'group'       => $glabel,
					'coming_soon' => ( strpos( (string) $dkey, 'soon_' ) === 0 ),
					'custom'      => in_array( (string) $dkey, $custom, true ),
				);
			}
		}
		return $out;
	}

	function fw_ext_animation_engine_ai_fields( $tag ) {
		$defs   = function_exists( 'upw_anim_field_defs' ) ? (array) upw_anim_field_defs() : array();
		$leaves = FW_AI_Schema::leaves( $tag );
		$out    = array();
		foreach ( $defs as $fid => $def ) {
			if ( isset( $leaves[ $fid ] ) && ( $leaves[ $fid ][0]['type'] ?? '' ) === 'multi-picker' ) {
				$out[ $fid ] = array( 'option' => $leaves[ $fid ][0] ) + (array) $def;
			}
		}
		return $out;
	}

	/**
	 * @param array $field One entry from fw_ext_animation_engine_ai_fields().
	 * @return array effect => label
	 */
	function fw_ext_animation_engine_ai_choices( array $field ) {
		$picker = (array) ( $field['option']['picker'][ $field['picker'] ] ?? array() );
		return FW_AI_Schema::flat_choices( $picker['choices'] ?? array() );
	}

	/**
	 * @param array  $field
	 * @param string $effect
	 * @return array setting id => option
	 */
	function fw_ext_animation_engine_ai_settings( array $field, $effect ) {
		$sub = $field['option']['choices'][ $effect ] ?? array();
		$out = array();
		foreach ( fw_extract_only_options( (array) $sub ) as $id => $opt ) {
			if ( ! in_array( $opt['type'] ?? '', FW_AI_Schema::UI_TYPES, true ) ) {
				$out[ $id ] = $opt;
			}
		}
		return $out;
	}

	function fw_ext_animation_engine_ai_register() {
		if ( ! function_exists( 'fw_ai_register_ability' ) ) {
			return;
		}

		fw_ai_register_ability( 'animation-effects', array(
			'label'       => __( 'List animation effects', 'fw' ),
			'description' => 'The Animation Engine effects an element can take (element defaults to text_block; most elements offer the same): each effect FIELD (animation = entrance, gsap_motion = scroll effect, scroll_reveal, interaction = hover, text_effect, parallax, physics …) with its effect choices. Pass field + effect to get that effect\'s settings (ids, types, choices, defaults) before animation_apply.',
			'input'       => array(
				'element' => array( 'type' => 'string', 'default' => 'text_block' ),
				'field'   => array( 'type' => 'string' ),
				'effect'  => array( 'type' => 'string' ),
			),
			'permission'  => 'edit_posts',
			'readonly'    => true,
			'panel'       => true,
			'execute'     => function ( $in ) {
				$fields = fw_ext_animation_engine_ai_fields( (string) ( $in['element'] ?? 'text_block' ) );
				if ( ! empty( $in['field'] ) ) {
					$f = $fields[ $in['field'] ] ?? null;
					if ( ! $f ) {
						return new WP_Error( 'fw_ae_ai_field', 'That element has no such effect field (see animation_effects without field).' );
					}
					$effect = (string) ( $in['effect'] ?? '' );
					if ( $effect === '' ) {
						return array( 'field' => $in['field'], 'label' => $f['label'], 'choices' => fw_ext_animation_engine_ai_choices( $f ) );
					}
					$settings = array();
					foreach ( fw_ext_animation_engine_ai_settings( $f, $effect ) as $id => $opt ) {
						$row = array( 'id' => (string) $id, 'type' => (string) $opt['type'] );
						if ( ! empty( $opt['label'] ) && is_string( $opt['label'] ) ) {
							$row['label'] = wp_strip_all_tags( $opt['label'] );
						}
						if ( in_array( $opt['type'], FW_AI_Schema::CHOICE_TYPES, true ) ) {
							$row['choices'] = array_keys( FW_AI_Schema::flat_choices( $opt['choices'] ?? array() ) );
						}
						if ( array_key_exists( 'value', $opt ) ) {
							$row['default'] = $opt['value'];
						}
						$settings[] = $row;
					}
					return array( 'field' => $in['field'], 'effect' => $effect, 'settings' => $settings );
				}
				$out = array();
				foreach ( $fields as $fid => $f ) {
					$out[] = array( 'field' => $fid, 'label' => (string) $f['label'], 'off' => (string) $f['off'], 'effects' => array_keys( fw_ext_animation_engine_ai_choices( $f ) ) );
				}
				return array( 'fields' => $out, 'note' => 'Use one or two effects per section, not on everything; respect reduced motion is on by default site-wide.' );
			},
		) );

		fw_ai_register_ability( 'animation-apply', array(
			'label'       => __( 'Apply an animation effect', 'fw' ),
			'description' => 'Applies an effect to one page item (path from get_page): field (e.g. gsap_motion, scroll_reveal, interaction, text_effect, animation), effect (one of that field\'s choices; "none" removes it) and optional settings for that effect (see animation_effects with field + effect). Works in the builder panel (applied unsaved) and on saved pages (revision first; undo reverts).',
			'input'       => array(
				'post_id'  => array( 'type' => 'integer' ),
				'path'     => array( 'type' => 'string' ),
				'field'    => array( 'type' => 'string' ),
				'effect'   => array( 'type' => 'string' ),
				'settings' => array( 'type' => 'object' ),
			),
			'required'    => array( 'post_id', 'path', 'field', 'effect' ),
			'permission'  => 'edit_post',
			'panel'       => true,
			'execute'     => 'fw_ext_animation_engine_ai_apply',
		) );

		fw_ai_register_ability( 'animation-site-modules', array(
			'label'       => __( 'List site-wide animation modules', 'fw' ),
			'description' => 'Site-wide Animation Engine modules — custom cursor, page transitions, preloader, scroll progress, smooth scroll, viewport fix — each with its Theme Settings id and whether it is on. Turn one on or configure it with update_theme_settings (e.g. { "animation_smoothscroll": { "enable": "yes" } }); describe_theme_settings with the id shows its options.',
			'permission'  => 'edit_theme_options',
			'readonly'    => true,
			'execute'     => function () {
				$out = array();
				foreach ( fw_ext_animation_engine_ai_modules() as $id => $desc ) {
					$v     = fw_get_db_settings_option( $id, null );
					$out[] = array( 'id' => $id, 'what' => $desc, 'enabled' => is_array( $v ) && ( $v['enable'] ?? 'no' ) === 'yes', 'available' => $v !== null || isset( FW_AI_Settings::leaves()[ $id ] ) );
				}
				$g = (array) fw_get_db_settings_option( 'animation_engine', array() );
				return array( 'modules' => $out, 'respect_reduced_motion' => ( $g['respect_reduced_motion'] ?? 'yes' ) === 'yes' );
			},
		) );

		fw_ai_register_ability( 'animation-gallery-designs', array(
			'label'       => __( 'List Motion Gallery designs', 'fw' ),
			'description' => 'The Motion Gallery (gallery_3d element) designs available on this site — the 3D / flow / stack layouts a gallery can take (carousel_ring, cover_flow, card_stack, device_cycler …), each with its group and whether it is a child-theme-registered custom design. Set one as a gallery_3d element\'s design_settings.design (via get_page / the builder). When replicating a source carousel, slider or 3D gallery, pick the closest native design here BEFORE writing a custom one.',
			'permission'  => 'edit_posts',
			'readonly'    => true,
			'execute'     => function () {
				$all        = fw_ext_animation_engine_ai_gallery_designs();
				$renderable = array_values( array_filter( $all, function ( $d ) { return empty( $d['coming_soon'] ); } ) );
				$soon       = array_values( array_map( function ( $d ) { return $d['key']; }, array_filter( $all, function ( $d ) { return ! empty( $d['coming_soon'] ); } ) ) );
				return array(
					'designs'      => $renderable,
					'coming_soon'  => $soon,
					'note'         => 'Set on a gallery_3d element via design_settings.design. A child theme can register its own design (filter upw_gallery_3d_design_groups + upw_gallery_3d_render_designs + window.upwGallery3d.registerDesign); such designs show custom:true. Honour reduced motion (on by default).',
				);
			},
		) );

		// Page timelines (Animation Creator 5g): the AI reads and writes the timelines the Live Editor's Animate makes.
		if ( function_exists( 'upw_page_timelines_save' ) ) {
			fw_ai_register_ability( 'page-timelines', array(
				'label'       => __( 'Read page timelines', 'fw' ),
				'description' => 'The page timelines of one page (keyframed motion of its elements, made in the Live Editor\'s Animate), the site-wide timelines, the saved motion presets and the curve keys. Format: { id, name, duration (s), trigger: { type: load|in-view|hover|click|scroll, target?: element unique_id or ".class", once?, leave?: "stay", toggle?, threshold?, delay?, pin?, length? (scroll, screen heights) }, repeat? (-1 forever), yoyo?, runsOn?: [desktop|tablet|phone], tracks: [ { target: element unique_id (from get_page) or ".class" (every element with it), stagger?, order?: first|last|center|random (class tracks), frames: [ { t (s), x?, y? (% of its own size), scale?, rotate? (deg), opacity? (0-1), blur? (1 = 10px), ease?: a curve key } ] } ] }. A property keyed later than t=0 should also be keyed at 0 with its rest value (x 0, y 0, scale 1, rotate 0, opacity 1, blur 0).',
				'input'       => array( 'post_id' => array( 'type' => 'integer' ) ),
				'required'    => array( 'post_id' ),
				'permission'  => 'edit_post',
				'readonly'    => true,
				'execute'     => function ( $in ) {
					$curves = array();
					if ( function_exists( 'sc_easing_defs' ) ) {
						foreach ( sc_easing_defs() as $k => $d ) {
							if ( 'default' !== $k && ! empty( $d['css'] ) && ( $d['group'] ?? '' ) !== 'Steps' ) {
								$curves[] = $k;
							}
						}
					}
					return array(
						'post_id'        => (int) $in['post_id'],
						'timelines'      => upw_page_timelines_get( (int) $in['post_id'] ),
						'site_timelines' => upw_site_timelines_get(),
						'presets'        => upw_timeline_presets_get(),
						'curves'         => $curves,
						'note'           => 'Write with page_timelines_save. Good defaults: entrances 0.5–0.8 s on soft_land or pop_over, a stagger of 0.08–0.15 s for a row of cards (one class track), at most one or two timelines per section. Site-wide timelines play on every page and take only class tracks.',
					);
				},
			) );

			fw_ai_register_ability( 'page-timelines-save', array(
				'label'       => __( 'Save page timelines', 'fw' ),
				'description' => 'Saves page timelines on a page (format: see page_timelines). mode "merge" (default) adds or replaces timelines by id and keeps the others; "replace" sets the whole list (an empty list removes them all). site_timelines (optional, needs Theme Settings rights) sets the site-wide list, played on every page (class tracks only). Every value is checked: unknown fields are dropped, numbers kept in range, curve keys turned into their CSS. Revision first: undo_change reverts. Someone with the page open in the Live Editor sees the change after reloading it.',
				'input'       => array(
					'post_id'        => array( 'type' => 'integer' ),
					'timelines'      => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
					'mode'           => array( 'type' => 'string', 'enum' => array( 'merge', 'replace' ) ),
					'site_timelines' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
				),
				'required'    => array( 'post_id', 'timelines' ),
				'permission'  => 'edit_post',
				'execute'     => 'fw_ext_animation_engine_ai_timelines_save',
			) );
		}
	}
	add_action( 'fw_ai_assistant_register_abilities', 'fw_ext_animation_engine_ai_register' );

	/** A keyframe's curve key → its CSS, so the runtime plays the curve (the editor does the same). */
	function fw_ext_animation_engine_ai_bake( $list ) {
		$defs = function_exists( 'sc_easing_defs' ) ? sc_easing_defs() : array();
		foreach ( $list as &$tl ) {
			foreach ( $tl['tracks'] as &$tr ) {
				foreach ( $tr['frames'] as &$f ) {
					$key = isset( $f['ease'] ) ? strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1_$2', (string) $f['ease'] ) ) : '';
					if ( '' !== $key && ! empty( $defs[ $key ]['css'] ) ) {
						$f['ease'] = $key;
						$f['e']    = (string) $defs[ $key ]['css'];
					}
				}
				unset( $f );
			}
			unset( $tr );
		}
		unset( $tl );
		return $list;
	}

	/**
	 * @param array $in
	 * @return array|WP_Error
	 */
	function fw_ext_animation_engine_ai_timelines_save( $in ) {
		$post_id = (int) $in['post_id'];
		if ( ! get_post( $post_id ) ) {
			return new WP_Error( 'fw_ae_ai_post', 'No such page.' );
		}
		$incoming = fw_ext_animation_engine_ai_bake( upw_page_timelines_clean( json_decode( wp_json_encode( $in['timelines'] ), true ) ) );
		$site     = isset( $in['site_timelines'] ) && is_array( $in['site_timelines'] );
		if ( $site && ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error( 'fw_ae_ai_site', 'Site-wide timelines need Theme Settings rights. Nothing was changed.' );
		}
		$spec = array( 'post_meta' => array( $post_id => array( UPW_PAGE_TIMELINES_META ) ) );
		if ( $site ) {
			$spec['options'] = array( UPW_SITE_TIMELINES_OPTION );
		}
		$rev = FW_AI_Toolkit::snapshot( $spec, 'unysonplus/page-timelines-save', 'Page timelines — ' . get_the_title( $post_id ) );

		$list = $incoming;
		if ( ( $in['mode'] ?? 'merge' ) !== 'replace' ) {
			$ids  = array_map( function ( $t ) { return $t['id']; }, $incoming );
			$keep = array_values( array_filter( upw_page_timelines_get( $post_id ), function ( $t ) use ( $ids ) { return ! in_array( $t['id'], $ids, true ); } ) );
			$list = array_merge( $keep, $incoming );
		}
		$saved = upw_page_timelines_save( $post_id, $list );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		$out = array(
			'ok'               => true,
			'post_id'          => $post_id,
			'timelines'        => $saved,
			'message'          => sprintf( 'Saved %d page timeline(s) on “%s”.', count( $saved ), get_the_title( $post_id ) ),
			'undo_revision_id' => (int) $rev,
		);
		if ( $site ) {
			$s = upw_site_timelines_save( fw_ext_animation_engine_ai_bake( upw_site_timelines_clean( json_decode( wp_json_encode( $in['site_timelines'] ), true ) ) ) );
			if ( is_wp_error( $s ) ) {
				return $s;
			}
			$out['site_timelines'] = $s;
		}
		return $out;
	}

	/**
	 * @param array $in
	 * @return array|WP_Error
	 */
	function fw_ext_animation_engine_ai_apply( $in ) {
		$post_id = (int) $in['post_id'];
		$tree    = FW_AI_Store::get_tree( $post_id );
		$idx     = FW_AI_Store::resolve( $tree, (string) $in['path'] );
		if ( is_wp_error( $idx ) ) {
			return $idx;
		}
		$node =& FW_AI_Store::node( $tree, $idx );
		$type = (string) ( $node['type'] ?? '' );
		$tag  = $type === 'simple' ? (string) ( $node['shortcode'] ?? '' ) : $type;

		$fields = fw_ext_animation_engine_ai_fields( $tag );
		$fid    = (string) $in['field'];
		if ( ! isset( $fields[ $fid ] ) ) {
			return new WP_Error( 'fw_ae_ai_field', sprintf( 'A %s has no "%s" effect field (it has: %s).', $tag, $fid, implode( ', ', array_keys( $fields ) ) ?: 'none' ) );
		}
		$f       = $fields[ $fid ];
		$effect  = (string) $in['effect'];
		$choices = fw_ext_animation_engine_ai_choices( $f );
		if ( $effect !== $f['off'] && ! isset( $choices[ $effect ] ) ) {
			return new WP_Error( 'fw_ae_ai_effect', sprintf( '"%s" is not a %s effect (choices: %s).', $effect, $fid, implode( ', ', array_keys( $choices ) ) ) );
		}
		$settings = isset( $in['settings'] ) ? (array) json_decode( wp_json_encode( $in['settings'] ), true ) : array();
		$errors   = array();
		if ( $settings && $effect !== $f['off'] ) {
			$schema = fw_ext_animation_engine_ai_settings( $f, $effect );
			foreach ( $settings as $k => $v ) {
				if ( ! isset( $schema[ $k ] ) ) {
					$errors[] = sprintf( 'settings.%s is not a setting of %s (valid: %s).', $k, $effect, implode( ', ', array_keys( $schema ) ) );
					continue;
				}
				FW_AI_Schema::check_deep( $schema[ $k ], $v, 'settings.' . $k, $errors );
			}
		}
		if ( $errors ) {
			return new WP_Error( 'fw_ae_ai_invalid', 'Nothing was changed: ' . implode( ' | ', $errors ) );
		}

		$atts = isset( $node['atts'] ) && is_array( $node['atts'] ) ? $node['atts'] : array();
		if ( $effect === $f['off'] ) {
			$atts[ $fid ] = array( $f['picker'] => $f['off'] );
		} else {
			$current = isset( $atts[ $fid ] ) && is_array( $atts[ $fid ] ) ? $atts[ $fid ] : array();
			$base    = isset( $current[ $effect ] ) && is_array( $current[ $effect ] ) ? $current[ $effect ] : array();
			$atts[ $fid ] = array( $f['picker'] => $effect, $effect => array_merge( $base, $settings ) );
		}
		$node['atts'] = $atts;
		unset( $node );

		$rev = FW_AI_Store::save_tree( $post_id, $tree, 'unysonplus/animation-apply', sprintf( '%s %s on %s at %s', $effect === $f['off'] ? 'Removed' : 'Applied ' . $effect, $fid, $tag, $in['path'] ) );
		if ( is_wp_error( $rev ) ) {
			return $rev;
		}
		return array(
			'ok'               => true,
			'message'          => $effect === $f['off'] ? sprintf( 'Removed the %s effect.', $f['label'] ) : sprintf( 'Applied %s (%s) to the %s.', $effect, $f['label'], str_replace( '_', ' ', $tag ) ),
			'post_id'          => $post_id,
			'path'             => (string) $in['path'],
			'value'            => $atts[ $fid ],
			'undo_revision_id' => (int) $rev,
		);
	}

endif;
