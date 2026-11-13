/*
 * Motion Gallery — in-modal live preview (admin only).
 *
 * On fw:options:init it finds each preview host, reads its sibling `design_settings` values from the
 * DOM (same modal), builds the scene with PLACEHOLDER cards, and drives it with the element's REAL
 * runtime (window.upwGallery3dInitEl). It rebuilds on fw:options:change — which the framework already
 * broadcasts for selects (native) + the design picker, and which the slider option was wired to fire.
 *
 * ⚠ KEEP THE TILING MATH IN SYNC with the plugin's views/designs/*.php. The driver derives its angular
 * step from how many cards each row/band contains, so the counts below must match the PHP.
 *
 * Nothing here can break the modal: it only READS siblings and writes into its own host. If a read
 * fails it falls back to the design defaults; if anything throws it is caught and the host is left
 * with the last good scene.
 */
(function ($) {
	'use strict';

	var RATIOS = { '1-1': [1, 1], '4-3': [4, 3], '3-4': [3, 4], '16-9': [16, 9], '9-16': [9, 16] };
	var MAXLAT = 80; // must match card-sphere.php
	var rad = function (d) { return (d * Math.PI) / 180; };

	// ─── ADDING A NEW DESIGN — the only two hooks ────────────────────────────────────────────────
	//   1. DEFAULTS['<your_design_key>'] = { …geometry defaults mirroring options.php… }
	//   2. a `if (design === '<your_design_key>') { … return html; }` branch in buildScene() below.
	// That is ALL. Detection is registry-driven off DEFAULTS: findDesignMp() recognises the design
	// picker by its DIRECT-CHILD choice-groups matching a DEFAULTS key, and readValues() resolves the
	// chosen design from the picker's own value (not the fragile `.chosen` class). So a design added to
	// DEFAULTS is detected automatically — no key lists to keep in sync, and no other design breaks.
	// (Miss step 2 and only that one design falls back to the carousel_ring scene — a clear "add your
	// build branch" signal, never a silent wrong-design-everywhere bug.)
	// ─────────────────────────────────────────────────────────────────────────────────────────────
	// Per-design fallbacks (mirror options.php defaults) — used when a value can't be read yet.
	var DEFAULTS = {
		carousel_ring: { drive: 'auto', allow_drag: 'yes', speed: 16, direction: 'left', hover_behavior: 'slow', tilt: -28, ring_opening: 55, roll: 0, ring_size: 80, spacing: 100, perspective: 18, back_fade: 70, card_size: 21, card_ratio: '1-1', corner_radius: 6, padding: 0 },
		panorama_wall: { drive: 'continuous', allow_drag: 'yes', speed: 20, direction: 'left', hover_behavior: 'slow', rows: 5, columns: 11, curvature: -100, tilt: 0, gap: 5, edge_fade: 0, perspective: 68, card_size: 20, card_ratio: '16-9', corner_radius: 2, padding: 0 },
		card_sphere: { drive: 'continuous', allow_drag: 'yes', speed: 20, direction: 'left', hover_behavior: 'slow', globe_size: 70, card_size: 20, gap: 2.5, back_fade: 55, tilt: 0, perspective: 55, card_ratio: '16-9', corner_radius: 2, padding: 0 },
		orbit_globe: { drive: 'continuous', allow_drag: 'yes', speed: 20, direction: 'left', hover_behavior: 'slow', globe_size: 50, card_size: 28, gap: 2.5, back_fade: 55, tilt: 27, card_ratio: '1-1', corner_radius: 2 },
		sphere_cascade: { drive: 'continuous', allow_drag: 'yes', speed: 20, direction: 'up', hover_behavior: 'slow', columns: 5, curvature: -100, tilt: 0, gap: 5, edge_fade: 0, card_size: 18, card_ratio: '16-9', corner_radius: 4, padding: 13 },
		totem_wall: { drive: 'continuous', allow_drag: 'yes', speed: 20, direction: 'up', hover_behavior: 'slow', zoom: 10, curvature: -150, tilt: -45, gap: 0.5, edge_fade: 0, card_ratio: '16-9', corner_radius: 0, padding: 0 },
		parallax_totem: { drive: 'continuous', allow_drag: 'yes', speed: 20, direction: 'up', hover_behavior: 'slow', zoom: 10, scatter: 0, size_variance: 0, parallax_depth: 0, curvature: -150, gap: 0.5, edge_fade: 0, card_ratio: '16-9', corner_radius: 0, padding: 0 },
		card_tunnel: { drive: 'continuous', allow_drag: 'yes', speed: 20, direction: 'forward', hover_behavior: 'slow', tunnel_size: 60, card_length: 30, gap: 2, depth_fade: 0, corner_radius: 0 },
		spiral_stream: { drive: 'continuous', allow_drag: 'yes', speed: 20, direction: 'forward', hover_behavior: 'slow', card_count: 8, spiral_turns: 1, spiral_size: 35, taper: -90, card_gap: 0, ring_tilt: -45, perspective: 0, back_fade: 10, scale_pulse: 0, card_size: 12, card_ratio: '3-4', corner_radius: 0, padding: 0 },
		depth_stack: { drive: 'continuous', allow_drag: 'yes', speed: 20, direction: 'forward', hover_behavior: 'slow', card_count: 4, depth_gap: 18, spread: 0, spread_angle: -180, wobble: 0, depth_fade: 0, depth_blur: 0, layout: 'fan', card_size: 20, card_ratio: '4-5', corner_radius: 0, padding: 0 },
		card_reel: { drive: 'continuous', allow_drag: 'yes', speed: 20, direction: 'up', hover_behavior: 'slow', snap: 'no', curve_3d: -100, card_size: 22, gap: 1, card_ratio: '1-1', corner_radius: 0, padding: 0 },
		film_strip: { drive: 'continuous', allow_drag: 'yes', speed: 20, direction: 'left', hover_behavior: 'slow', snap: 'no', curve_3d: -100, card_size: 22, gap: 1, card_ratio: '1-1', corner_radius: 0, padding: 0 },
		wheel_carousel: { drive: 'continuous', allow_drag: 'yes', speed: 20, direction: 'cw', hover_behavior: 'slow', wheel_size: 70, card_size: 40, anticipation: 0, overshoot: 0, hold: 0, card_ratio: '1-1', corner_radius: 0, padding: 0 },
		cover_flow: { drive: 'continuous', allow_drag: 'yes', speed: 20, direction: 'forward', hover_behavior: 'slow', snap: 'yes', orientation: 'horizontal', side_tilt: 40, card_size: 46, gap: 4, card_ratio: '1-1', corner_radius: 0, padding: 0 },
		carousel_flow: { drive: 'continuous', allow_drag: 'yes', speed: 20, direction: 'forward', hover_behavior: 'slow', snap: 'yes', orientation: 'horizontal', side_scale: 82, card_size: 50, gap: 5, card_ratio: '1-1', corner_radius: 0, padding: 0 },
		mosaic_marquee: { drive: 'continuous', allow_drag: 'yes', speed: 24, direction: 'left', hover_behavior: 'slow', layout: '1', card_height: 55, gap: 3, card_ratio: '1-1', corner_radius: 18, padding: 6, background: '' },
		hero_reel: { drive: 'continuous', allow_drag: 'yes', speed: 24, direction: 'left', hover_behavior: 'slow', card_size: 18, card_spacing: 4, active_scale: 112, active_lift: 8, hero_opacity: 55, ken_burns: 60, hold: 55, card_ratio: '4-3', corner_radius: 8, padding: 0, background: '' },
		focus_slider: { drive: 'continuous', allow_drag: 'yes', speed: 24, direction: 'left', hover_behavior: 'slow', axis: 'horizontal', card_size: 78, center_scale: 200, slide_portion: 60, card_spacing: 6, hold: 50, zigzag: 0, zigzag_mode: 'alternate', card_ratio: '1-1', corner_radius: 8, padding: 0, background: '' },
		// Flat tabletop scatter + device frame. The preview always runs these in AUTO cycle (there is no
		// scroll in the modal), so the scatter's sets sweep in/out and the device cycles screens on a
		// timer — a lively, representative preview of the design regardless of the saved cycle mode.
		photo_scatter: { visible: 9, rotation: 12, size_variance: 30, spread: 90, card_size: 18, card_ratio: '3-4', corner_radius: 4, padding: 0, from: 'edges', exit: 'sweep' },
		device_cycler: { device: 'laptop', transition: 'fade', card_size: 62, card_ratio: '16-9', corner_radius: 10, padding: 0 },
		card_stack: { exit: 'down', behind: 3, stack_offset: 5, stack_scale: 6, card_size: 40, card_ratio: '4-3', corner_radius: 14, padding: 0 },
		// Isometric family — all five share the .tdg__iso DOM (one combined branch in buildScene()).
		tilt_deck:   { drive: 'continuous', allow_drag: 'yes', speed: 12, direction: 'forward', hover_behavior: 'slow', columns: 4, depth_rows: 5, gap: 12, iso_tilt: 58, lift: 60, focus_glow: 135, depth_fade: 30, card_size: 16, card_ratio: '1-1', corner_radius: 6, padding: 0 },
		iso_orbit:   { drive: 'continuous', allow_drag: 'yes', speed: 18, direction: 'cw', hover_behavior: 'slow', radius: 30, iso_tilt: 58, lift: 70, focus_glow: 140, depth_fade: 35, card_size: 16, card_ratio: '1-1', corner_radius: 6, padding: 0 },
		iso_stack:   { drive: 'continuous', allow_drag: 'yes', speed: 14, direction: 'up', hover_behavior: 'slow', gap: 40, spiral: 40, iso_tilt: 58, lift: 70, focus_glow: 140, depth_fade: 40, card_size: 17, card_ratio: '1-1', corner_radius: 6, padding: 0 },
		iso_cascade: { drive: 'continuous', allow_drag: 'yes', speed: 12, direction: 'down', hover_behavior: 'slow', spread: 60, rise: 50, iso_tilt: 58, lift: 70, focus_glow: 140, depth_fade: 45, card_size: 17, card_ratio: '1-1', corner_radius: 6, padding: 0 },
		iso_fan:     { drive: 'continuous', allow_drag: 'yes', speed: 16, direction: 'left', hover_behavior: 'slow', reach: 34, arc: 130, iso_tilt: 58, lift: 70, focus_glow: 140, depth_fade: 40, card_size: 16, card_ratio: '1-1', corner_radius: 6, padding: 0 },
		// Orbit family — both share the .tdg__orbit DOM (one combined branch in buildScene()).
		orbit_bloom: { drive: 'continuous', allow_drag: 'yes', speed: 22, direction: 'cw', hover_behavior: 'slow', radius: 32, tilt: 58, bloom: 70, focus_glow: 130, depth_fade: 45, card_size: 16, card_ratio: '1-1', corner_radius: 6, padding: 0 },
		focus_orbit: { drive: 'continuous', allow_drag: 'yes', speed: 20, direction: 'cw', hover_behavior: 'slow', radius: 36, tilt: 30, hero: 70, focus_glow: 150, depth_fade: 45, card_size: 15, card_ratio: '1-1', corner_radius: 6, padding: 0 },
		dual_orbit:  { drive: 'continuous', allow_drag: 'yes', speed: 20, direction: 'cw', hover_behavior: 'slow', radius: 33, inner: 50, tilt: 62, counter: 'yes', inner_speed: 150, show_paths: 'yes', focus_glow: 130, depth_fade: 45, card_size: 11, card_ratio: '1-1', corner_radius: 6, padding: 0 },
		comet_orbit: { drive: 'continuous', allow_drag: 'yes', speed: 24, direction: 'cw', hover_behavior: 'slow', radius: 33, eccentricity: 62, tilt: 45, show_focus: 'yes', show_paths: 'yes', flare: 70, depth_fade: 40, card_size: 12, card_ratio: '1-1', corner_radius: 6, padding: 0 },
		orbit_swarm: { drive: 'continuous', allow_drag: 'yes', speed: 26, direction: 'cw', hover_behavior: 'slow', radius: 31, spread: 55, swirl: 60, height: 30, cohesion: 45, tilt: 55, focus_glow: 130, depth_fade: 45, card_size: 9, card_ratio: '1-1', corner_radius: 6, padding: 0 },
		ring_tilt:   { drive: 'continuous', allow_drag: 'yes', speed: 30, direction: 'cw', hover_behavior: 'slow', radius: 40, tilt: 70, lift: 20, wobble: 8, show_core: 'yes', core_size: 34, core_color: '#2f74e6', focus_glow: 125, depth_fade: 50, card_size: 12, card_ratio: '4-3', corner_radius: 6, padding: 0 },
		gyro_orbit:  { drive: 'continuous', allow_drag: 'yes', speed: 22, direction: 'cw', hover_behavior: 'slow', radius: 24, rings: 3, lean: 62, spin: 35, show_paths: 'yes', show_core: 'yes', focus_glow: 130, depth_fade: 50, card_size: 9, card_ratio: '1-1', corner_radius: 6, padding: 0 },
		infinity_orbit: { drive: 'continuous', allow_drag: 'yes', speed: 26, direction: 'cw', hover_behavior: 'slow', radius: 38, loop: 100, cross: 40, tilt: 50, show_paths: 'yes', focus_glow: 130, depth_fade: 45, card_size: 11, card_ratio: '1-1', corner_radius: 6, padding: 0 },
		vortex:      { drive: 'continuous', allow_drag: 'yes', speed: 30, direction: 'in', hover_behavior: 'slow', radius: 42, arms: 3, twist: 0.8, depth: 60, tilt: 52, show_core: 'yes', depth_fade: 55, card_size: 8, card_ratio: '1-1', corner_radius: 6, padding: 0 },
		pendulum_orbit: { drive: 'continuous', allow_drag: 'yes', speed: 8, direction: 'cw', hover_behavior: 'slow', radius: 36, arc: 130, swing: 45, tilt: 58, focus_glow: 140, depth_fade: 45, card_size: 8, card_ratio: '1-1', corner_radius: 6, padding: 0 },
		moon_orbit:  { drive: 'continuous', allow_drag: 'yes', speed: 30, direction: 'cw', hover_behavior: 'slow', radius: 33, planets: 4, moon_radius: 8, moon_speed: 300, moon_size: 50, tilt: 55, focus_glow: 130, depth_fade: 45, card_size: 11, card_ratio: '1-1', corner_radius: 6, padding: 0 },
		tiered_orbit: { drive: 'continuous', allow_drag: 'yes', speed: 26, direction: 'cw', hover_behavior: 'slow', radius: 28, tiers: 3, gap: 16, taper: 35, elevation: 22, alternate: 'yes', show_paths: 'yes', focus_glow: 130, depth_fade: 50, card_size: 8, card_ratio: '1-1', corner_radius: 6, padding: 0 },
		// Grid family — cards in a cols×rows grid on .tdg__grid (one combined branch in buildScene()).
		wave_grid:   { drive: 'continuous', allow_drag: 'yes', speed: 5, direction: 'out', hover_behavior: 'slow', columns: 6, rows: 4, gap: 14, tilt: 55, amplitude: 35, wavelength: 3.5, origin: 'center', focus_glow: 130, card_size: 11, card_ratio: '1-1', corner_radius: 6, padding: 0 },
		flip_grid:   { drive: 'continuous', allow_drag: 'no', speed: 4, direction: 'fwd', hover_behavior: 'slow', columns: 4, rows: 3, gap: 8, flip_axis: 'y', order: 'wave', tilt: 8, card_size: 15, card_ratio: '1-1', corner_radius: 6, padding: 0 },
		magnet_grid: { drive: 'continuous', speed: 10, direction: 'roam', hover_behavior: 'slow', columns: 5, rows: 3, gap: 14, reach: 22, lift: 90, lean: 10, focus_glow: 130, card_size: 12, card_ratio: '1-1', corner_radius: 6, padding: 0 },
		// Reveal & Wipe family — one large photo in a .tdg__frame, photos from a hidden .tdg__pool.
		blind_reveal:  { drive: 'continuous', allow_drag: 'no', speed: 4, direction: 'fwd', hover_behavior: 'slow', frame_size: 70, slats: 10, orientation: 'v', stagger: 50, gap: 2, card_ratio: '16-9', corner_radius: 10, padding: 0 },
		iris_wipe:     { drive: 'continuous', allow_drag: 'no', speed: 4, direction: 'fwd', hover_behavior: 'slow', frame_size: 70, shape: 'circle', zoom: 12, transition: 45, card_ratio: '16-9', corner_radius: 10, padding: 0 },
		tile_dissolve: { drive: 'continuous', allow_drag: 'no', speed: 4, direction: 'fwd', hover_behavior: 'slow', frame_size: 70, columns: 8, rows: 5, pattern: 'corner', fly: 60, card_ratio: '16-9', corner_radius: 10, padding: 0 },
		// Spotlight & Focus family — cards on .tdg__grid; light / blur / a magnifying lens.
		spotlight_wall: { drive: 'continuous', speed: 3, direction: 'fwd', hover_behavior: 'none', follow_pointer: 'yes', columns: 5, rows: 3, gap: 10, spot_size: 80, shadow: 35, greyscale: 100, lift: 8, move_time: 35, order: 'random', card_size: 13, card_ratio: '1-1', corner_radius: 6, padding: 0 },
		focus_pull:     { drive: 'continuous', speed: 3, direction: 'front', hover_behavior: 'none', layers: 3, photos: 15, depth: 45, blur: 7, dim: 30, drift: 30, move_time: 40, card_size: 15, card_ratio: '1-1', corner_radius: 6, padding: 0 },
		loupe:          { drive: 'continuous', speed: 12, direction: 'roam', hover_behavior: 'none', follow_pointer: 'yes', columns: 6, rows: 4, gap: 8, lens_size: 24, zoom: 220, lens_ring: '#ffffff', card_size: 10, card_ratio: '1-1', corner_radius: 6, padding: 0 },
		card_fan:       { drive: 'continuous', speed: 2, direction: 'ltr', hover_behavior: 'none', follow_pointer: 'yes', cards: 7, spread: 70, pivot: 120, lift: 35, straighten: 60, dim: 25, sway: 3, move_time: 45, card_size: 20, card_ratio: '3-4', corner_radius: 12, padding: 0 },
		shuffle_deck:   { drive: 'continuous', speed: 3, direction: 'alternate', hover_behavior: 'none', behind: 4, offset: 4, jitter: 4, slide: 75, slide_tilt: 10, move_time: 55, card_size: 34, card_ratio: '4-3', corner_radius: 12, padding: 0 },
		polaroid_drop:  { drive: 'continuous', speed: 2, direction: 'above', hover_behavior: 'none', pile: 6, when_full: 'oldest', scatter: 45, max_tilt: 14, move_time: 45, frame_color: '#ffffff', card_size: 24, card_ratio: '4-5', corner_radius: 3, padding: 0 },
		phone_lineup:   { drive: 'continuous', speed: 3, direction: 'left', hover_behavior: 'none', snap: 'yes', allow_drag: 'yes', drag_momentum: 'yes', phones: 7, spacing: 70, depth: 50, turn: 25, float: 3, thickness: 8, move_time: 45, frame_color: '#3a3c41', card_size: 16, card_ratio: '9-16', corner_radius: 0, padding: 0 },
		window_stack:   { drive: 'continuous', speed: 3, direction: 'toward', hover_behavior: 'none', behind: 4, gap: 30, offset: 8, tilt: 16, yaw: 22, dim: 30, move_time: 55, chrome_color: '#e9eaee', card_size: 44, card_ratio: '16-9', corner_radius: 0, padding: 0 },
		webgl_mirror_ring: { drive: 'continuous', allow_drag: 'yes', speed: 24, direction: 'left', hover_behavior: 'slow', ring_size: 90, camera_height: 14, reflection: 45, floor_color: '', lighting: 'studio', card_size: 20, card_ratio: '3-4', corner_radius: 12, padding: 0 },
		webgl_depth_field: { drive: 'continuous', speed: 20, direction: 'toward', hover_behavior: 'slow', cards: 18, depth: 60, spread: 100, fog: 55, fog_color: '', parallax: 40, lighting: 'studio', card_size: 18, card_ratio: '4-3', corner_radius: 12, padding: 0 },
		webgl_wave_wall: { drive: 'continuous', speed: 8, direction: 'left', hover_behavior: 'slow', columns: 6, rows: 3, wave_height: 40, wave_length: 80, view_angle: 25, light_angle: -30, gloss: 40, gap: 8, lighting: 'studio', card_ratio: '4-3', corner_radius: 8, padding: 0 },
		webgl_liquid_slider: { drive: 'continuous', speed: 5, direction: 'left', hover_behavior: 'slow', swipe: 'yes', effect: 'liquid', strength: 50, move_time: 35, pointer: 30, zoom: 6, corner_radius: 12, padding: 0 },
		webgl_particle_morph: { drive: 'continuous', speed: 5, direction: 'left', hover_behavior: 'slow', swipe: 'yes', effect: 'explode', density: 140, particle: 90, scatter: 50, move_time: 50, repel: 40, padding: 0 },
		webgl_flip_book: { drive: 'continuous', speed: 4, direction: 'left', hover_behavior: 'slow', swipe: 'yes', card_size: 60, tilt: 25, curl: 60, move_time: 45, cover_color: '#2b303b', lighting: 'studio', card_ratio: '3-4', corner_radius: 4, padding: 0 },
		webgl_shatter: { drive: 'continuous', speed: 4, direction: 'left', hover_behavior: 'slow', swipe: 'yes', shape: 'shards', burst: 'toward', origin: 'center', pieces: 14, spin: 60, force: 60, move_time: 45, padding: 0 },
		webgl_cloth_banners: { drive: 'continuous', speed: 40, direction: 'left', hover_behavior: 'slow', banners: 4, wind: 50, ripples: 2, gap: 14, view_angle: 8, rod_color: '#3a3c41', lighting: 'studio', card_ratio: '3-4', corner_radius: 0, padding: 0 },
		webgl_fold_strip: { drive: 'continuous', speed: 40, direction: 'left', hover_behavior: 'slow', allow_drag: 'yes', drag_momentum: 'yes', panels: 6, fold: 35, unfold: 'yes', view_angle: 12, lighting: 'studio', card_ratio: '3-4', corner_radius: 0, padding: 0 },
		webgl_water_pool: { drive: 'continuous', speed: 60, direction: 'left', hover_behavior: 'none', columns: 4, rows: 2, gap: 4, refraction: 50, rain: 30, shine: 50, water_color: '#2a8fbf', card_ratio: '4-3', corner_radius: 8, padding: 0 },
		webgl_jelly_grid: { drive: 'continuous', speed: 8, direction: 'left', hover_behavior: 'none', columns: 5, rows: 3, gap: 6, jelly: 60, wobble: 60, wave: 30, view_angle: 10, gloss: 40, lighting: 'studio', card_ratio: '4-3', corner_radius: 10, padding: 0 },
		webgl_stage_curtain: { drive: 'continuous', speed: 5, direction: 'left', hover_behavior: 'slow', swipe: 'yes', pleats: 10, opening: 88, swag: 50, move_time: 50, valance: 'yes', curtain_color: '#7a1424', padding: 0 },
		webgl_gallery_walk: { drive: 'continuous', speed: 40, direction: 'left', hover_behavior: 'none', wall_color: '#e9e4dc', floor_color: '#3a3029', frame_color: '#1c1c1c', mat: 'yes', light_pools: 60, spacing: 100, look_around: 40, card_ratio: '3-4', padding: 0 },
		webgl_holo_cards: { drive: 'continuous', speed: 6, direction: 'left', hover_behavior: 'none', columns: 4, rows: 1, gap: 10, foil: 70, pattern: 'rainbow', tilt: 18, glare: 50, card_ratio: '3-4', corner_radius: 14, padding: 0 },
		webgl_domino_row: { drive: 'continuous', speed: 10, direction: 'left', hover_behavior: 'none', dominoes: 8, spacing: 50, view_angle: 55, camera_height: 18, domino_color: '#f4f2ee', shadows: 'yes', card_ratio: '3-4', corner_radius: 4, padding: 0 },
		webgl_photo_bubbles: { drive: 'continuous', speed: 40, direction: 'left', hover_behavior: 'none', bubbles: 12, card_size: 22, iridescence: 70, depth: 50, wobble: 50, pointer_push: 50, padding: 0 },
		webgl_photo_cube: { drive: 'continuous', speed: 4, direction: 'left', hover_behavior: 'slow', swipe: 'yes', tumble: 'turn', card_size: 55, tilt: 18, move_time: 40, float: 40, cube_color: '#22252c', shadows: 'yes', lighting: 'studio', corner_radius: 6, padding: 0 },
		webgl_film_ribbon: { drive: 'continuous', speed: 40, direction: 'left', hover_behavior: 'slow', frames: 5, wave: 50, twist: 40, film_color: '#16171b', sprocket_holes: 'yes', lighting: 'studio', card_ratio: '4-3', padding: 0 },
		webgl_pixel_blocks: { drive: 'continuous', speed: 5, direction: 'left', hover_behavior: 'slow', swipe: 'yes', pattern: 'diagonal', blocks: 14, depth: 40, gap: 4, pointer_push: 50, move_time: 50, side_color: '#2b2f38', lighting: 'studio', padding: 0 },
		webgl_ferris_wheel: { drive: 'continuous', speed: 40, direction: 'left', hover_behavior: 'slow', allow_drag: 'yes', drag_momentum: 'yes', gondolas: 10, card_size: 26, swing: 50, view_angle: 12, wheel_color: '#e9ecf2', bulbs: 'yes', lighting: 'studio', card_ratio: '4-3', corner_radius: 6, padding: 0 },
		webgl_photo_mobile: { drive: 'continuous', speed: 40, direction: 'left', hover_behavior: 'none', photos: 7, card_size: 18, breeze: 50, sway: 50, arm_color: '#3a3c41', lighting: 'studio', card_ratio: '3-4', corner_radius: 4, padding: 0 },
		webgl_photo_fall: { drive: 'continuous', speed: 30, direction: 'left', hover_behavior: 'slow', photos: 18, card_size: 16, flutter: 50, wind: 0, depth: 50, pointer_push: 50, lighting: 'studio', card_ratio: '4-3', corner_radius: 4, padding: 0 },
		webgl_lenticular: { drive: 'continuous', speed: 5, direction: 'left', hover_behavior: 'slow', swipe: 'yes', ridges: 28, tilt: 30, card_size: 72, move_time: 40, follow: 50, frame_color: '#e9ecf2', lighting: 'studio', card_ratio: '4-3', corner_radius: 8, padding: 0 },
		webgl_jigsaw: { drive: 'continuous', speed: 6, direction: 'left', hover_behavior: 'slow', swipe: 'yes', pieces: 8, scatter: 50, pointer_lift: 50, move_time: 60, board_color: '#1d2027', lighting: 'studio', padding: 0 },
		webgl_paper_fold: { drive: 'continuous', speed: 6, direction: 'left', hover_behavior: 'slow', swipe: 'yes', order: 'side', card_size: 80, move_time: 60, follow: 40, paper_color: '#f4f1ea', lighting: 'studio', card_ratio: '4-3', corner_radius: 0, padding: 0 },
		webgl_slot_reels: { drive: 'continuous', speed: 5, direction: 'left', hover_behavior: 'slow', swipe: 'yes', reels: 3, spins: 2, stagger: 40, card_size: 78, move_time: 55, follow: 40, frame_color: '#1d2027', lighting: 'studio', card_ratio: '4-3', padding: 0 },
		webgl_peel: { drive: 'continuous', speed: 5, direction: 'left', hover_behavior: 'slow', swipe: 'yes', corner: 'cycle', curl: 50, lift: 40, move_time: 45, paper_color: '#f4f1ea', corner_radius: 12, padding: 0 },
		webgl_bookshelf: { drive: 'continuous', speed: 4, direction: 'left', hover_behavior: 'slow', books: 16, shelves: 1, pull: 100, move_time: 45, wood_color: '#6b4a32', lighting: 'studio', card_ratio: '3-4', padding: 0 },
		webgl_snow_globe: { drive: 'continuous', speed: 6, direction: 'left', hover_behavior: 'slow', swipe: 'yes', snow: 60, shake: 50, stir: 50, card_size: 85, move_time: 55, base_color: '#3b2a20', lighting: 'studio', card_ratio: '4-3', corner_radius: 6, padding: 0 },
		webgl_tv_wall: { drive: 'continuous', speed: 5, direction: 'left', hover_behavior: 'slow', tvs: 9, body_color: '#d8c9ad', mixed_colors: 'yes', scanlines: 50, static: 50, curve: 50, lighting: 'studio', padding: 0 },
		device_trio:    { drive: 'continuous', speed: 3, direction: 'sway', hover_behavior: 'none', follow_pointer: 'yes', card_size: 48, depth: 30, float: 2, tilt: 10, card_ratio: '1-1', corner_radius: 0, padding: 0 }
	};

	// ---- placeholder cards (a gradient tile — the real photos render on the front end) ----
	function cardHtml(i, n) {
		var h = Math.round((i * 360) / Math.max(1, n));
		var bg = 'linear-gradient(135deg,hsl(' + h + ',62%,60%),hsl(' + ((h + 40) % 360) + ',58%,42%))';
		return '<div class="tdg__card"><div class="tdg__inner" style="background:' + bg + '"></div></div>';
	}

	function attrStr(o) { return Object.keys(o).map(function (k) { return k + '="' + o[k] + '"'; }).join(' '); }

	function wrapStyle(o) {
		var r = RATIOS[o.card_ratio] || RATIOS['1-1'];
		return 'height:100%;--tdg-radius:' + o.corner_radius + 'px;--tdg-pad:' + o.padding + '%;' +
			'--tdg-ratio:' + r[0] + ' / ' + r[1] + ';--tdg-bg:transparent;' +
			'--tdg-shadow:0 14px 40px -8px rgba(0,0,0,.45);';
	}

	// bands + per-band counts — mirrors card-sphere.php (Card Size is a % of the GLOBE)
	function sphereBands(o) {
		var r = RATIOS[o.card_ratio] || RATIOS['16-9'];
		var cardAspect = r[1] / r[0];
		var rOverCard = 50 / o.card_size;
		var gapFrac = o.gap / 100;
		var rows = Math.max(3, Math.min(24, Math.round((rOverCard * 2 * rad(MAXLAT)) / (cardAspect + gapFrac))));
		var bands = [];
		for (var b = 0; b < rows; b++) {
			var lat = ((b + 0.5) / rows) * 2 * MAXLAT - MAXLAT;
			bands.push(Math.max(3, Math.round((2 * Math.PI * rOverCard * Math.cos(rad(lat))) / (1 + gapFrac))));
		}
		return { rows: rows, bands: bands };
	}

	function buildScene(design, o) {
		var style = wrapStyle(o);
		var shared = {
			'data-tdg-drive': o.drive, 'data-tdg-speed': o.speed,
			'data-tdg-dir': o.direction === 'right' ? -1 : 1, 'data-tdg-hover': o.hover_behavior,
			'data-tdg-momentum': 1, 'data-tdg-allowdrag': o.allow_drag === 'yes' ? 1 : 0
		};
		var i, cards;

		if (design === 'panorama_wall') {
			var a = attrStr($.extend({}, shared, {
				'data-tdg-alt': o.direction === 'alternate' ? 1 : 0, 'data-tdg-rows': o.rows,
				'data-tdg-curv': o.curvature, 'data-tdg-tilt': o.tilt, 'data-tdg-gap': o.gap,
				'data-tdg-edge': o.edge_fade, 'data-tdg-persp': o.perspective, 'data-tdg-card': o.card_size,
				'data-tdg-count': 12
			}));
			var rows = '';
			for (var r = 0; r < o.rows; r++) {
				cards = '';
				for (i = 0; i < o.columns; i++) { cards += cardHtml((i + r) % 12, 12); }
				rows += '<div class="tdg__row">' + cards + '</div>';
			}
			return '<div class="tdg tdg--panorama-wall" style="' + style + '" ' + a + '><div class="tdg__stage"><div class="tdg__wall">' + rows + '</div></div></div>';
		}

		if (design === 'card_sphere') {
			var sb = sphereBands(o);
			var as = attrStr($.extend({}, shared, {
				'data-tdg-globe': o.globe_size, 'data-tdg-maxlat': MAXLAT, 'data-tdg-rows': sb.rows,
				'data-tdg-backfade': o.back_fade, 'data-tdg-tilt': o.tilt, 'data-tdg-persp': o.perspective,
				'data-tdg-card': o.card_size, 'data-tdg-count': 12
			}));
			var html = '';
			sb.bands.forEach(function (cnt, b) {
				cards = '';
				for (var k = 0; k < cnt; k++) { cards += cardHtml((k + b) % 12, 12); }
				html += '<div class="tdg__band">' + cards + '</div>';
			});
			return '<div class="tdg tdg--card-sphere" style="' + style + '" ' + as + '><div class="tdg__stage"><div class="tdg__globe">' + html + '</div></div></div>';
		}

		if (design === 'orbit_globe') {
			// count mirrors orbit-globe.php (denser cloud when cards are smaller / gap tighter)
			var no = Math.max(14, Math.min(90, Math.round(5 / (o.card_size / 100) / (1 + o.gap / 100))));
			var ao = attrStr($.extend({}, shared, {
				'data-tdg-globe': o.globe_size, 'data-tdg-backfade': o.back_fade, 'data-tdg-tilt': o.tilt,
				'data-tdg-card': o.card_size, 'data-tdg-count': 12
			}));
			cards = '';
			for (i = 0; i < no; i++) { cards += cardHtml(i % 12, 12); }
			return '<div class="tdg tdg--orbit-globe" style="' + style + '" ' + ao + '><div class="tdg__stage"><div class="tdg__orbit">' + cards + '</div></div></div>';
		}

		if (design === 'photo_scatter') {
			// Flat scatter — mirror photo-scatter.php DOM. 18 placeholder cards so even a large "Cards
			// per Set" still has a next set to cycle to; forced to AUTO so it animates in the modal.
			var aps = attrStr({
				'data-tdg-cycle': 'auto', 'data-tdg-dwell': 3, 'data-tdg-hpause': 0,
				'data-tdg-from': o.from, 'data-tdg-exit': o.exit,
				'data-tdg-visible': o.visible, 'data-tdg-rot': o.rotation, 'data-tdg-sizevar': o.size_variance,
				'data-tdg-spread': o.spread, 'data-tdg-card': o.card_size, 'data-tdg-count': 18
			});
			cards = '';
			for (i = 0; i < 18; i++) { cards += cardHtml(i, 18); }
			return '<div class="tdg tdg--photo-scatter" style="' + style + '" ' + aps + '><div class="tdg__stage"><div class="tdg__plane">' + cards + '</div></div></div>';
		}

		if (design === 'device_cycler') {
			// Device frame whose screen cycles — mirror device-cycler.php DOM (frame → screen → plane →
			// cards, first .is-active). Forced to AUTO so the screens cross-fade in the modal.
			var dev = o.device || 'laptop';
			var adc = attrStr({
				'data-tdg-device': dev, 'data-tdg-cycle': 'auto', 'data-tdg-dwell': 2,
				'data-tdg-transition': o.transition, 'data-tdg-count': 6
			});
			cards = '';
			for (i = 0; i < 6; i++) {
				var dh = Math.round((i * 360) / 6);
				var dbg = 'linear-gradient(135deg,hsl(' + dh + ',62%,60%),hsl(' + ((dh + 40) % 360) + ',58%,42%))';
				cards += '<div class="tdg__card' + (i === 0 ? ' is-active' : '') + '"><div class="tdg__inner" style="background:' + dbg + '"></div></div>';
			}
			// Orientation follows the device: laptop/browser landscape, phone portrait — swap the ratio if needed.
			var drp = /^(\d+)-(\d+)$/.exec(o.card_ratio || '16-9'); var drw = drp ? +drp[1] : 16, drh = drp ? +drp[2] : 9;
			if ((dev === 'laptop' || dev === 'browser') && drw < drh) { var dtmp = drw; drw = drh; drh = dtmp; }
			if (dev === 'phone' && drw > drh) { var dtmp2 = drw; drw = drh; drh = dtmp2; }
			var dStyle = style + '--tdg-dev-w:' + o.card_size + '%;--tdg-ratio:' + drw + ' / ' + drh + ';';
			return '<div class="tdg tdg--device-cycler" style="' + dStyle + '" ' + adc + '>' +
				'<div class="tdg__stage"><div class="tdg__device tdg__device--' + dev + '"><div class="tdg__screen">' +
				'<div class="tdg__plane">' + cards + '</div></div></div></div></div>';
		}

		if (design === 'sphere_cascade') {
			// per-column count mirrors sphere-cascade.php
			var perCol = Math.max(12, Math.min(28, Math.round(320 / o.card_size)));
			var ac = attrStr($.extend({}, shared, {
				'data-tdg-dir': o.direction === 'down' ? -1 : 1, // cascade is vertical (up/down), not left/right
				'data-tdg-alt': o.direction === 'alternate' ? 1 : 0,
				'data-tdg-curv': o.curvature, 'data-tdg-tilt': o.tilt, 'data-tdg-gap': o.gap,
				'data-tdg-edge': o.edge_fade, 'data-tdg-card': o.card_size, 'data-tdg-count': 12
			}));
			var colsHtml = '';
			for (var cc = 0; cc < o.columns; cc++) {
				cards = '';
				for (i = 0; i < perCol; i++) { cards += cardHtml((i * o.columns + cc) % 12, 12); }
				colsHtml += '<div class="tdg__col">' + cards + '</div>';
			}
			return '<div class="tdg tdg--sphere-cascade" style="' + style + '" ' + ac + '><div class="tdg__stage"><div class="tdg__cascade">' + colsHtml + '</div></div></div>';
		}

		if (design === 'totem_wall') {
			// A flat row of independent vertical totems — mirror totem-wall.php DOM (9 columns × 14 cards).
			var twCols = 9, twPer = 14;
			var at = attrStr($.extend({}, shared, {
				'data-tdg-dir': o.direction === 'down' ? -1 : 1, // cascade is vertical (up/down), not left/right
				'data-tdg-alt': o.direction === 'alternate' ? 1 : 0,
				'data-tdg-curv': o.curvature, 'data-tdg-tilt': o.tilt, 'data-tdg-gap': o.gap,
				'data-tdg-edge': o.edge_fade, 'data-tdg-zoom': o.zoom, 'data-tdg-count': 12
			}));
			var twHtml = '';
			for (var tc = 0; tc < twCols; tc++) {
				cards = '';
				for (i = 0; i < twPer; i++) { cards += cardHtml((i * twCols + tc) % 12, 12); }
				twHtml += '<div class="tdg__col">' + cards + '</div>';
			}
			return '<div class="tdg tdg--totem-wall" style="' + style + '" ' + at + '><div class="tdg__stage"><div class="tdg__cascade">' + twHtml + '</div></div></div>';
		}

		if (design === 'parallax_totem') {
			// Depth-scatter sibling of Totem Wall — same 9×14 grid DOM; initParallax adds the depth/scatter.
			var ptCols = 9, ptPer = 14;
			var ap = attrStr($.extend({}, shared, {
				'data-tdg-dir': o.direction === 'down' ? -1 : 1,
				'data-tdg-curv': o.curvature, 'data-tdg-gap': o.gap, 'data-tdg-edge': o.edge_fade,
				'data-tdg-zoom': o.zoom, 'data-tdg-scatter': o.scatter, 'data-tdg-sizevar': o.size_variance,
				'data-tdg-parallax': o.parallax_depth, 'data-tdg-count': 12
			}));
			var ptHtml = '';
			for (var pc = 0; pc < ptCols; pc++) {
				cards = '';
				for (i = 0; i < ptPer; i++) { cards += cardHtml((i * ptCols + pc) % 12, 12); }
				ptHtml += '<div class="tdg__col">' + cards + '</div>';
			}
			return '<div class="tdg tdg--parallax-totem" style="' + style + '" ' + ap + '><div class="tdg__stage"><div class="tdg__cascade">' + ptHtml + '</div></div></div>';
		}

		if (design === 'card_tunnel') {
			// Four walls of cards receding to a vanishing point — mirror card-tunnel.php DOM (18/wall).
			var ctPer = 18, ctWalls = ['top', 'bottom', 'left', 'right'];
			var act = attrStr($.extend({}, shared, {
				'data-tdg-dir': o.direction === 'backward' ? -1 : 1,
				'data-tdg-tsize': o.tunnel_size, 'data-tdg-clen': o.card_length, 'data-tdg-gap': o.gap,
				'data-tdg-dfade': o.depth_fade, 'data-tdg-count': 12
			}));
			var ctHtml = '';
			for (var cw = 0; cw < 4; cw++) {
				cards = '';
				for (i = 0; i < ctPer; i++) { cards += cardHtml((i * 4 + cw) % 12, 12); }
				ctHtml += '<div class="tdg__wall tdg__wall--' + ctWalls[cw] + '">' + cards + '</div>';
			}
			return '<div class="tdg tdg--card-tunnel" style="' + style + '" ' + act + '><div class="tdg__stage"><div class="tdg__tunnel">' + ctHtml + '</div></div></div>';
		}

		if (design === 'spiral_stream') {
			// Cards threaded on a tapering helix — mirror spiral-stream.php (Card Count cards).
			var spN = Math.max(3, Math.min(48, o.card_count));
			var asp = attrStr($.extend({}, shared, {
				'data-tdg-dir': o.direction === 'backward' ? -1 : 1,
				'data-tdg-turns': o.spiral_turns, 'data-tdg-size': o.spiral_size, 'data-tdg-taper': o.taper,
				'data-tdg-card': o.card_size, 'data-tdg-backfade': o.back_fade, 'data-tdg-persp': o.perspective,
				'data-tdg-tilt': o.ring_tilt, 'data-tdg-gap': o.card_gap, 'data-tdg-pulse': o.scale_pulse,
				'data-tdg-count': 12
			}));
			cards = '';
			for (i = 0; i < spN; i++) { cards += cardHtml(i % 12, 12); }
			return '<div class="tdg tdg--spiral-stream" style="' + style + '" ' + asp + '><div class="tdg__stage"><div class="tdg__spiral">' + cards + '</div></div></div>';
		}

		if (design === 'depth_stack') {
			// Deck receding into depth — mirror depth-stack.php (Card Count cards).
			var dzN = Math.max(3, Math.min(50, o.card_count));
			var adz = attrStr($.extend({}, shared, {
				'data-tdg-dir': o.direction === 'backward' ? -1 : 1,
				'data-tdg-gap': o.depth_gap, 'data-tdg-card': o.card_size, 'data-tdg-spread': o.spread,
				'data-tdg-angle': o.spread_angle, 'data-tdg-wobble': o.wobble, 'data-tdg-dfade': o.depth_fade,
				'data-tdg-dblur': o.depth_blur, 'data-tdg-layout': o.layout, 'data-tdg-count': 12
			}));
			cards = '';
			for (i = 0; i < dzN; i++) { cards += cardHtml(i % 12, 12); }
			return '<div class="tdg tdg--depth-stack" style="' + style + '" ' + adz + '><div class="tdg__stage"><div class="tdg__depth">' + cards + '</div></div></div>';
		}

		if (design === 'card_reel') {
			// Vertical cover-flow carousel — mirror card-reel.php (a loop of cards on a cylinder).
			var crN = 9;
			var acr = attrStr($.extend({}, shared, {
				'data-tdg-dir': o.direction === 'down' ? -1 : 1,
				'data-tdg-curve': o.curve_3d, 'data-tdg-card': o.card_size, 'data-tdg-gap': o.gap,
				'data-tdg-snap': o.snap, 'data-tdg-count': 12
			}));
			cards = '';
			for (i = 0; i < crN; i++) { cards += cardHtml(i % 12, 12); }
			return '<div class="tdg tdg--card-reel" style="' + style + '" ' + acr + '><div class="tdg__stage"><div class="tdg__reel">' + cards + '</div></div></div>';
		}

		if (design === 'film_strip') {
			// Horizontal cover-flow strip — mirror film-strip.php (Card Reel runtime, axis = horizontal).
			var fsN = 9;
			var afs = attrStr($.extend({}, shared, {
				'data-tdg-dir': o.direction === 'right' ? -1 : 1,
				'data-tdg-curve': o.curve_3d, 'data-tdg-card': o.card_size, 'data-tdg-gap': o.gap,
				'data-tdg-snap': o.snap, 'data-tdg-axis': 'h', 'data-tdg-count': 12
			}));
			cards = '';
			for (i = 0; i < fsN; i++) { cards += cardHtml(i % 12, 12); }
			return '<div class="tdg tdg--film-strip" style="' + style + '" ' + afs + '><div class="tdg__stage"><div class="tdg__reel">' + cards + '</div></div></div>';
		}

		if (design === 'hero_reel') {
			// Reel of thumbnails over a dimmed Ken-Burns hero — mirror hero-reel.php (initHeroReel).
			var hrN = 9;
			var ahr = attrStr($.extend({}, shared, {
				'data-tdg-dir': o.direction === 'right' ? -1 : 1,
				'data-tdg-card': o.card_size, 'data-tdg-gap': o.card_spacing, 'data-tdg-ascale': o.active_scale,
				'data-tdg-alift': o.active_lift, 'data-tdg-hop': o.hero_opacity, 'data-tdg-kb': o.ken_burns, 'data-tdg-hold': o.hold, 'data-tdg-count': 12
			}));
			cards = '';
			for (i = 0; i < hrN; i++) { cards += cardHtml(i % 12, 12); }
			var hrBg = (o.background && String(o.background).trim()) ? o.background : '#101014';
			return '<div class="tdg tdg--hero-reel" style="' + style + '" ' + ahr + '><div class="tdg__stage" style="background:' + hrBg + '"><div class="tdg__hero"><span class="tdg__hero-layer"></span><span class="tdg__hero-layer"></span></div><div class="tdg__reel">' + cards + '</div></div></div>';
		}

		if (design === 'focus_slider') {
			// Centred-focus slider — mirror focus-slider.php (initFocusSlider).
			var afs2 = attrStr($.extend({}, shared, {
				'data-tdg-dir': o.direction === 'right' ? -1 : 1,
				'data-tdg-axis': o.axis === 'vertical' ? 'vertical' : 'horizontal',
				'data-tdg-card': o.card_size, 'data-tdg-cscale': o.center_scale, 'data-tdg-slide': o.slide_portion,
				'data-tdg-gap': o.card_spacing, 'data-tdg-zig': o.zigzag, 'data-tdg-zigmode': o.zigzag_mode,
				'data-tdg-hold': o.hold, 'data-tdg-count': 9
			}));
			cards = '';
			for (i = 0; i < 9; i++) { cards += cardHtml(i % 12, 12); }
			var fsBg = (o.background && String(o.background).trim()) ? o.background : '#101014';
			return '<div class="tdg tdg--focus-slider" style="' + style + '" ' + afs2 + '><div class="tdg__stage" style="background:' + fsBg + '"><div class="tdg__reel">' + cards + '</div></div></div>';
		}

		if (design === 'wheel_carousel') {
			// Cards fanned on a Ferris wheel — mirror wheel-carousel.php (a loop of cards on the arc).
			var wcN = 11;
			var awc = attrStr($.extend({}, shared, {
				'data-tdg-dir': o.direction === 'ccw' ? -1 : 1,
				'data-tdg-card': o.card_size, 'data-tdg-wheel': o.wheel_size, 'data-tdg-antic': o.anticipation,
				'data-tdg-over': o.overshoot, 'data-tdg-hold': o.hold, 'data-tdg-count': 12
			}));
			cards = '';
			for (i = 0; i < wcN; i++) { cards += cardHtml(i % 12, 12); }
			return '<div class="tdg tdg--wheel-carousel" style="' + style + '" ' + awc + '><div class="tdg__stage"><div class="tdg__wheel">' + cards + '</div></div></div>';
		}

		if (design === 'cover_flow' || design === 'carousel_flow') {
			// Center-focus carousels (Cover Flow / Carousel Flow) — mirror the *-flow.php DOM (a loop of cards).
			var flN = 9, cover = (design === 'cover_flow');
			var afl = attrStr($.extend({}, shared, {
				'data-tdg-dir': o.direction === 'backward' ? -1 : 1,
				'data-tdg-flow': cover ? 'cover' : 'carousel',
				'data-tdg-axis': o.orientation === 'vertical' ? 'v' : 'h',
				'data-tdg-card': o.card_size, 'data-tdg-gap': o.gap, 'data-tdg-snap': o.snap,
				'data-tdg-tilt': o.side_tilt, 'data-tdg-sidescale': o.side_scale, 'data-tdg-count': 12
			}));
			cards = '';
			for (i = 0; i < flN; i++) { cards += cardHtml(i % 12, 12); }
			return '<div class="tdg tdg--' + (cover ? 'cover' : 'carousel') + '-flow" style="' + style + '" ' + afl + '><div class="tdg__stage"><div class="tdg__flow">' + cards + '</div></div></div>';
		}

		if (design === 'mosaic_marquee') {
			// Airy bento marquee — mirror mosaic-marquee.php: one seed grid (initMosaic rebuilds the bento
			// and clones copies), dark stage background so gaps + empty cells show through.
			var rp = /^(\d+)-(\d+)$/.exec(o.card_ratio || '1-1'); var rw = rp ? rp[1] : 1, rh = rp ? rp[2] : 1;
			var amm = attrStr($.extend({}, shared, {
				'data-tdg-dir': o.direction === 'right' ? -1 : 1,
				'data-tdg-card': o.card_height, 'data-tdg-gap': o.gap, 'data-tdg-layout': o.layout,
				'data-tdg-rw': rw, 'data-tdg-rh': rh, 'data-tdg-corner': o.corner_radius, 'data-tdg-pad': o.padding, 'data-tdg-count': 12
			}));
			var one = ''; for (i = 0; i < 12; i++) { one += cardHtml(i % 12, 12); }
			var mmStyle = (o.background && String(o.background).trim()) ? ' style="background:' + o.background + '"' : '';
			return '<div class="tdg tdg--mosaic-marquee" style="' + style + '" ' + amm + '><div class="tdg__stage"' + mmStyle + '><div class="tdg__mosaic-track"><div class="tdg__mosaic">' + one + '</div></div></div></div>';
		}

		if (design === 'card_stack') {
			// Deck of cards — mirror card-stack.php DOM. Forced to AUTO so the deck advances in the modal.
			var acs = attrStr({
				'data-tdg-cycle': 'auto', 'data-tdg-dwell': 1.6, 'data-tdg-exit': o.exit,
				'data-tdg-behind': o.behind, 'data-tdg-offset': o.stack_offset, 'data-tdg-scale': o.stack_scale,
				'data-tdg-count': 8
			});
			cards = '';
			for (i = 0; i < 8; i++) { cards += cardHtml(i, 8); }
			var csStyle = style + '--tdg-card-w:' + o.card_size + '%;';
			return '<div class="tdg tdg--card-stack" style="' + csStyle + '" ' + acs + '><div class="tdg__stage"><div class="tdg__deck">' + cards + '</div></div></div>';
		}

		// Isometric family (tilt_deck · iso_orbit · iso_stack · iso_cascade · iso_fan) — all share the
		// .tdg__iso plane DOM; each adds its own data-tdg-* geometry. Mirrors views/designs/iso-*.php.
		if (design === 'tilt_deck' || design === 'iso_orbit' || design === 'iso_stack' || design === 'iso_cascade' || design === 'iso_fan') {
			var revMap = { tilt_deck: 'backward', iso_orbit: 'ccw', iso_stack: 'down', iso_cascade: 'up', iso_fan: 'right' };
			var isoShared = $.extend({}, shared, {
				'data-tdg-dir': o.direction === revMap[design] ? -1 : 1,
				'data-tdg-tilt': o.iso_tilt, 'data-tdg-lift': o.lift, 'data-tdg-glow': o.focus_glow,
				'data-tdg-dfade': o.depth_fade, 'data-tdg-card': o.card_size
			});
			var isoN;
			if (design === 'tilt_deck') { isoShared['data-tdg-cols'] = o.columns; isoShared['data-tdg-gap'] = o.gap; isoN = Math.max(6, o.columns * o.depth_rows); }
			else if (design === 'iso_orbit') { isoShared['data-tdg-radius'] = o.radius; isoN = 12; }
			else if (design === 'iso_stack') { isoShared['data-tdg-gap'] = o.gap; isoShared['data-tdg-spiral'] = o.spiral; isoN = 9; }
			else if (design === 'iso_cascade') { isoShared['data-tdg-spread'] = o.spread; isoShared['data-tdg-rise'] = o.rise; isoN = 10; }
			else { isoShared['data-tdg-reach'] = o.reach; isoShared['data-tdg-arc'] = o.arc; isoN = 11; }
			cards = '';
			for (i = 0; i < isoN; i++) { cards += cardHtml(i % 12, 12); }
			return '<div class="tdg tdg--' + design.replace(/_/g, '-') + '" style="' + style + '" ' + attrStr(isoShared) + '><div class="tdg__stage"><div class="tdg__iso">' + cards + '</div></div></div>';
		}

		// Orbit family (orbit_bloom · focus_orbit) — billboarded cards on a tilted orbit disc (.tdg__orbit).
		if (design === 'orbit_bloom' || design === 'focus_orbit') {
			var ob = $.extend({}, shared, {
				'data-tdg-dir': o.direction === 'ccw' ? -1 : 1,
				'data-tdg-radius': o.radius, 'data-tdg-tilt': o.tilt,
				'data-tdg-glow': o.focus_glow, 'data-tdg-dfade': o.depth_fade,
				'data-tdg-card': o.card_size, 'data-tdg-momentum': 1
			});
			if (design === 'orbit_bloom') { ob['data-tdg-bloom'] = o.bloom; } else { ob['data-tdg-hero'] = o.hero; }
			cards = '';
			for (i = 0; i < 12; i++) { cards += cardHtml(i % 12, 12); }
			return '<div class="tdg tdg--' + design.replace(/_/g, '-') + '" style="' + style + '" ' + attrStr(ob) + '><div class="tdg__stage"><div class="tdg__orbit">' + cards + '</div></div></div>';
		}

		// More Orbit designs (dual_orbit · comet_orbit · orbit_swarm · ring_tilt) — same .tdg__orbit DOM,
		// each with its own data-tdg-* geometry and extras (orbit paths, focus glow, planet). Mirrors
		// views/designs/<dashed>.php.
		if (design === 'dual_orbit' || design === 'comet_orbit' || design === 'orbit_swarm' || design === 'ring_tilt') {
			var oa = $.extend({}, shared, {
				'data-tdg-dir': o.direction === 'ccw' ? -1 : 1,
				'data-tdg-radius': o.radius, 'data-tdg-tilt': o.tilt,
				'data-tdg-dfade': o.depth_fade, 'data-tdg-card': o.card_size, 'data-tdg-momentum': 1
			});
			var extra = '', oN = 12;
			if (design === 'dual_orbit') {
				oa['data-tdg-inner'] = o.inner; oa['data-tdg-counter'] = o.counter === 'yes' ? 1 : 0;
				oa['data-tdg-ispeed'] = o.inner_speed; oa['data-tdg-glow'] = o.focus_glow; oN = 15;
				if (o.show_paths === 'yes') { extra = '<div class="tdg__track"></div><div class="tdg__track"></div>'; }
			} else if (design === 'comet_orbit') {
				oa['data-tdg-ecc'] = o.eccentricity; oa['data-tdg-flare'] = o.flare;
				if (o.show_paths === 'yes') { extra += '<div class="tdg__track"></div>'; }
				if (o.show_focus === 'yes') { extra += '<div class="tdg__focus"></div>'; }
			} else if (design === 'orbit_swarm') {
				oa['data-tdg-spread'] = o.spread; oa['data-tdg-swirl'] = o.swirl; oa['data-tdg-height'] = o.height;
				oa['data-tdg-cohesion'] = o.cohesion; oa['data-tdg-glow'] = o.focus_glow; oN = 16;
			} else {
				oa['data-tdg-lift'] = o.lift; oa['data-tdg-wobble'] = o.wobble; oa['data-tdg-core'] = o.core_size;
				oa['data-tdg-glow'] = o.focus_glow; oN = 16;
				if (o.show_core === 'yes') { extra = '<div class="tdg__planet" style="--tdg-core:' + (/^#[0-9a-f]{3,8}$/i.test(o.core_color) ? o.core_color : '#2f74e6') + '"></div>'; }
			}
			cards = '';
			for (i = 0; i < oN; i++) { cards += cardHtml(i % 12, 12); }
			return '<div class="tdg tdg--' + design.replace(/_/g, '-') + '" style="' + style + '" ' + attrStr(oa) + '><div class="tdg__stage"><div class="tdg__orbit">' + extra + cards + '</div></div></div>';
		}

		// Gyro Orbit · Infinity Orbit · Vortex — .tdg__orbit DOM with their own geometry + extras.
		// Mirrors views/designs/gyro-orbit.php, infinity-orbit.php, vortex.php.
		if (design === 'gyro_orbit' || design === 'infinity_orbit' || design === 'vortex') {
			var og = $.extend({}, shared, {
				'data-tdg-radius': o.radius, 'data-tdg-dfade': o.depth_fade, 'data-tdg-card': o.card_size, 'data-tdg-momentum': 1
			});
			var gx = '', gN;
			if (design === 'gyro_orbit') {
				var gR = Math.max(2, Math.min(4, Math.round(o.rings)));
				$.extend(og, { 'data-tdg-dir': o.direction === 'ccw' ? -1 : 1, 'data-tdg-rings': gR, 'data-tdg-lean': o.lean, 'data-tdg-spin': o.spin, 'data-tdg-glow': o.focus_glow });
				if (o.show_paths === 'yes') { for (i = 0; i < gR; i++) { gx += '<div class="tdg__track"></div>'; } }
				if (o.show_core === 'yes') { gx += '<div class="tdg__focus"></div>'; }
				gN = Math.max(12, gR * 5);
			} else if (design === 'infinity_orbit') {
				$.extend(og, { 'data-tdg-dir': o.direction === 'ccw' ? -1 : 1, 'data-tdg-loop': o.loop, 'data-tdg-cross': o.cross, 'data-tdg-tilt': o.tilt, 'data-tdg-paths': o.show_paths === 'yes' ? 1 : 0, 'data-tdg-glow': o.focus_glow });
				gN = 14;
			} else {
				$.extend(og, { 'data-tdg-dir': o.direction === 'out' ? -1 : 1, 'data-tdg-arms': o.arms, 'data-tdg-twist': o.twist, 'data-tdg-depth': o.depth, 'data-tdg-tilt': o.tilt });
				if (o.show_core === 'yes') { gx = '<div class="tdg__focus"></div>'; }
				gN = 24;
			}
			cards = '';
			for (i = 0; i < gN; i++) { cards += cardHtml(i % 12, 12); }
			return '<div class="tdg tdg--' + design.replace(/_/g, '-') + '" style="' + style + '" ' + attrStr(og) + '><div class="tdg__stage"><div class="tdg__orbit">' + gx + cards + '</div></div></div>';
		}

		// Pendulum Orbit · Moon Orbit · Tiered Orbit — .tdg__orbit DOM. Mirrors pendulum-orbit.php,
		// moon-orbit.php, tiered-orbit.php.
		if (design === 'pendulum_orbit' || design === 'moon_orbit' || design === 'tiered_orbit') {
			var op = $.extend({}, shared, {
				'data-tdg-dir': o.direction === 'ccw' ? -1 : 1, 'data-tdg-radius': o.radius, 'data-tdg-glow': o.focus_glow,
				'data-tdg-dfade': o.depth_fade, 'data-tdg-card': o.card_size, 'data-tdg-momentum': 1
			});
			var px = '', pN;
			if (design === 'pendulum_orbit') {
				$.extend(op, { 'data-tdg-arc': o.arc, 'data-tdg-swing': o.swing, 'data-tdg-tilt': o.tilt });
				pN = 9;
			} else if (design === 'moon_orbit') {
				var pl = Math.max(2, Math.min(6, Math.round(o.planets)));
				$.extend(op, { 'data-tdg-planets': pl, 'data-tdg-moonr': o.moon_radius, 'data-tdg-mspeed': o.moon_speed, 'data-tdg-msize': o.moon_size, 'data-tdg-tilt': o.tilt });
				pN = Math.max(12, pl * 4);
			} else {
				var tr = Math.max(2, Math.min(5, Math.round(o.tiers)));
				$.extend(op, { 'data-tdg-tiers': tr, 'data-tdg-gap': o.gap, 'data-tdg-taper': o.taper, 'data-tdg-elev': o.elevation, 'data-tdg-alt': o.alternate === 'yes' ? 1 : 0 });
				if (o.show_paths === 'yes') { for (i = 0; i < tr; i++) { px += '<div class="tdg__track"></div>'; } }
				pN = Math.max(12, tr * 7);
			}
			cards = '';
			for (i = 0; i < pN; i++) { cards += cardHtml(i % 12, 12); }
			return '<div class="tdg tdg--' + design.replace(/_/g, '-') + '" style="' + style + '" ' + attrStr(op) + '><div class="tdg__stage"><div class="tdg__orbit">' + px + cards + '</div></div></div>';
		}

		// Grid family (wave_grid · flip_grid · magnet_grid) — a cols×rows grid on .tdg__grid. Mirrors
		// views/designs/wave-grid.php, flip-grid.php, magnet-grid.php.
		if (design === 'wave_grid' || design === 'flip_grid' || design === 'magnet_grid') {
			var gc = Math.max(1, Math.round(o.columns)), gr = Math.max(1, Math.round(o.rows)), gcells = gc * gr;
			var gg = $.extend({}, shared, { 'data-tdg-cols': gc, 'data-tdg-gap': o.gap, 'data-tdg-card': o.card_size });
			var gbody = '', gpool = '', gcls = '';
			if (design === 'wave_grid') {
				$.extend(gg, { 'data-tdg-dir': o.direction === 'in' ? -1 : 1, 'data-tdg-tilt': o.tilt, 'data-tdg-amp': o.amplitude, 'data-tdg-wl': o.wavelength,
					'data-tdg-origin': Math.max(0, ['center', 'left', 'corner'].indexOf(o.origin)), 'data-tdg-glow': o.focus_glow, 'data-tdg-momentum': 1 });
				for (i = 0; i < gcells; i++) { gbody += cardHtml(i % 12, 12); }
			} else if (design === 'flip_grid') {
				// The flip refills a face's .tdg__inner CONTENTS (keeping the element), so the placeholder
				// colour must live in a child, not on the inner itself.
				var fInner = function (k) { var h = Math.round(k * 360 / 20); return '<div class="tdg__inner"><span style="display:block;width:100%;height:100%;background:linear-gradient(135deg,hsl(' + h + ',62%,60%),hsl(' + ((h + 40) % 360) + ',58%,42%))"></span></div>'; };
				var np = 20, fFor = function (j, p) { return ((j + p * (gcells + 1)) % np + np) % np; };
				$.extend(gg, { 'data-tdg-dir': o.direction === 'back' ? -1 : 1, 'data-tdg-axis': o.flip_axis === 'x' ? 'x' : 'y',
					'data-tdg-order': Math.max(0, ['wave', 'random', 'rows'].indexOf(o.order)), 'data-tdg-tilt': o.tilt, 'data-tdg-allowdrag': o.allow_drag === 'yes' ? 1 : 0, 'data-tdg-momentum': 1 });
				for (i = 0; i < gcells; i++) { gbody += '<div class="tdg__card"><div class="tdg__face tdg__face--a">' + fInner(fFor(i, 0)) + '</div><div class="tdg__face tdg__face--b" aria-hidden="true">' + fInner(fFor(i, 1)) + '</div></div>'; }
				for (i = 0; i < np; i++) { gpool += '<div class="tdg__poolitem">' + fInner(i) + '</div>'; }
				gpool = '<div class="tdg__pool" hidden>' + gpool + '</div>';
				if (o.flip_axis === 'x') { gcls = ' tdg--flip-x'; }
			} else {
				$.extend(gg, { 'data-tdg-roam': o.direction === 'center' ? 0.4 : 1, 'data-tdg-reach': o.reach, 'data-tdg-lift': o.lift, 'data-tdg-lean': o.lean,
					'data-tdg-glow': o.focus_glow, 'data-tdg-allowdrag': 0 });
				for (i = 0; i < gcells; i++) { gbody += cardHtml(i % 12, 12); }
			}
			return '<div class="tdg tdg--' + design.replace(/_/g, '-') + gcls + '" style="' + style + '" ' + attrStr(gg) + '><div class="tdg__stage"><div class="tdg__grid">' + gbody + '</div></div>' + gpool + '</div>';
		}

		// Reveal & Wipe family (blind_reveal · iris_wipe · tile_dissolve) — an empty .tdg__frame plus a
		// hidden pool the runtime reads photo URLs from (so the placeholders here must be real <img>s).
		// Mirrors views/designs/blind-reveal.php, iris-wipe.php, tile-dissolve.php.
		if (design === 'blind_reveal' || design === 'iris_wipe' || design === 'tile_dissolve') {
			var rv = $.extend({}, shared, { 'data-tdg-dir': o.direction === 'back' ? -1 : 1, 'data-tdg-frame': o.frame_size,
				'data-tdg-allowdrag': o.allow_drag === 'yes' ? 1 : 0, 'data-tdg-momentum': 1 });
			var rcls = '';
			if (design === 'blind_reveal') {
				$.extend(rv, { 'data-tdg-slats': o.slats, 'data-tdg-orient': o.orientation === 'h' ? 'h' : 'v', 'data-tdg-stagger': o.stagger, 'data-tdg-gap': o.gap });
				if (o.orientation === 'h') { rcls = ' tdg--blind-h'; }
			} else if (design === 'iris_wipe') {
				$.extend(rv, { 'data-tdg-shape': o.shape, 'data-tdg-zoom': o.zoom, 'data-tdg-dur': o.transition });
			} else {
				$.extend(rv, { 'data-tdg-cols': o.columns, 'data-tdg-rows': o.rows, 'data-tdg-pattern': Math.max(0, ['corner', 'random', 'center'].indexOf(o.pattern)), 'data-tdg-fly': o.fly });
			}
			var rpool = '';
			for (i = 0; i < 6; i++) {
				var rh = i * 60;
				var rsvg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 160 90'><defs><linearGradient id='g' x1='0' y1='0' x2='1' y2='1'><stop offset='0' stop-color='hsl(" + rh + ",62%,62%)'/><stop offset='1' stop-color='hsl(" + ((rh + 50) % 360) + ",58%,40%)'/></linearGradient></defs><rect width='160' height='90' fill='url(%23g)'/><circle cx='112' cy='30' r='22' fill='rgba(255,255,255,.22)'/></svg>";
				rpool += '<div class="tdg__poolitem"><div class="tdg__inner"><span class="tdg__link"><img class="tdg__img" src="data:image/svg+xml;utf8,' + rsvg.replace(/"/g, '&quot;') + '" alt=""></span></div></div>';
			}
			return '<div class="tdg tdg--' + design.replace(/_/g, '-') + rcls + '" style="' + style + '" ' + attrStr(rv) + '><div class="tdg__stage"><div class="tdg__frame"></div></div><div class="tdg__pool" hidden>' + rpool + '</div></div>';
		}

		// Spotlight & Focus family (spotlight_wall · focus_pull · loupe) — cards on .tdg__grid. Mirrors
		// views/designs/spotlight-wall.php, focus-pull.php, loupe.php.
		if (design === 'spotlight_wall' || design === 'focus_pull' || design === 'loupe') {
			var sf = $.extend({}, shared, { 'data-tdg-card': o.card_size, 'data-tdg-allowdrag': 0 });
			var sN, sStyle = style;
			if (design === 'spotlight_wall') {
				$.extend(sf, { 'data-tdg-dir': o.direction === 'back' ? -1 : 1, 'data-tdg-cols': o.columns, 'data-tdg-gap': o.gap, 'data-tdg-spot': o.spot_size,
					'data-tdg-dim': o.shadow, 'data-tdg-grey': o.greyscale, 'data-tdg-lift': o.lift, 'data-tdg-move': o.move_time,
					'data-tdg-order': o.order === 'reading' ? 0 : 1, 'data-tdg-follow': o.follow_pointer === 'yes' ? 1 : 0 });
				sN = Math.max(1, Math.round(o.columns)) * Math.max(1, Math.round(o.rows));
			} else if (design === 'focus_pull') {
				$.extend(sf, { 'data-tdg-dir': o.direction === 'back' ? -1 : 1, 'data-tdg-layers': o.layers, 'data-tdg-depth': o.depth, 'data-tdg-blur': o.blur,
					'data-tdg-dim': o.dim, 'data-tdg-drift': o.drift, 'data-tdg-move': o.move_time });
				sN = Math.max(4, Math.round(o.photos));
			} else {
				$.extend(sf, { 'data-tdg-roam': o.direction === 'center' ? 0.45 : 1, 'data-tdg-cols': o.columns, 'data-tdg-gap': o.gap, 'data-tdg-lens': o.lens_size,
					'data-tdg-zoom': o.zoom, 'data-tdg-follow': o.follow_pointer === 'yes' ? 1 : 0 });
				sN = Math.max(2, Math.round(o.columns)) * Math.max(1, Math.round(o.rows));
				if (/^#[0-9a-f]{3,8}$/i.test(o.lens_ring)) { sStyle += '--tdg-lens-ring:' + o.lens_ring + ';'; }
			}
			cards = '';
			for (i = 0; i < sN; i++) { cards += cardHtml(i % 12, 12); }
			return '<div class="tdg tdg--' + design.replace(/_/g, '-') + '" style="' + sStyle + '" ' + attrStr(sf) + '><div class="tdg__stage"><div class="tdg__grid">' + cards + '</div></div></div>';
		}

		// Stack & Scatter additions (card_fan · shuffle_deck · polaroid_drop) — cards on .tdg__grid. Mirrors
		// views/designs/card-fan.php, shuffle-deck.php, polaroid-drop.php.
		if (design === 'card_fan' || design === 'shuffle_deck' || design === 'polaroid_drop') {
			var kf = $.extend({}, shared, { 'data-tdg-card': o.card_size, 'data-tdg-allowdrag': 0 });
			var kN = 12, kStyle = style;
			if (design === 'card_fan') {
				$.extend(kf, { 'data-tdg-dir': o.direction === 'rtl' ? -1 : 1, 'data-tdg-spread': o.spread, 'data-tdg-pivot': o.pivot, 'data-tdg-lift': o.lift,
					'data-tdg-straight': o.straighten, 'data-tdg-dim': o.dim, 'data-tdg-sway': o.sway, 'data-tdg-move': o.move_time,
					'data-tdg-follow': o.follow_pointer === 'yes' ? 1 : 0 });
				kN = Math.max(3, Math.min(15, Math.round(o.cards)));
			} else if (design === 'shuffle_deck') {
				$.extend(kf, { 'data-tdg-side': o.direction === 'right' ? 1 : (o.direction === 'left' ? -1 : 0), 'data-tdg-behind': o.behind, 'data-tdg-offset': o.offset,
					'data-tdg-jitter': o.jitter, 'data-tdg-slide': o.slide, 'data-tdg-tilt': o.slide_tilt, 'data-tdg-move': o.move_time });
				kN = 8;
			} else {
				var kPile = Math.max(1, Math.min(20, Math.round(o.pile)));
				$.extend(kf, { 'data-tdg-from': o.direction === 'camera' ? 'camera' : 'above', 'data-tdg-pile': kPile, 'data-tdg-scatter': o.scatter,
					'data-tdg-tilt': o.max_tilt, 'data-tdg-sweep': o.when_full === 'sweep' ? 1 : 0, 'data-tdg-move': o.move_time });
				kN = 12 * Math.ceil((kPile + 1) / 12);
				if (/^#[0-9a-f]{3,8}$/i.test(o.frame_color)) { kStyle += '--tdg-frame:' + o.frame_color + ';'; }
			}
			cards = '';
			for (i = 0; i < kN; i++) {
				// Polaroid: the colour goes on a child, so the print's frame (the .tdg__inner background) still shows.
				cards += design === 'polaroid_drop'
					? cardHtml(i % 12, 12).replace('" style="background:', '"><span style="display:block;width:100%;height:100%;background:').replace('"></div></div>', '"></span></div></div>')
					: cardHtml(i % 12, 12);
			}
			return '<div class="tdg tdg--' + design.replace(/_/g, '-') + '" style="' + kStyle + '" ' + attrStr(kf) + '><div class="tdg__stage"><div class="tdg__grid">' + cards + '</div></div></div>';
		}

		// Devices & Screens additions (phone_lineup · window_stack · device_trio). Mirrors
		// views/designs/phone-lineup.php, window-stack.php, device-trio.php.
		if (design === 'phone_lineup' || design === 'window_stack') {
			var df = $.extend({}, shared, { 'data-tdg-card': o.card_size });
			var dN = 8, dStyle = style;
			if (design === 'phone_lineup') {
				$.extend(df, { 'data-tdg-dir': o.direction === 'right' ? -1 : 1, 'data-tdg-spacing': o.spacing, 'data-tdg-depth': o.depth, 'data-tdg-turn': o.turn,
					'data-tdg-bob': o.float, 'data-tdg-thick': o.thickness, 'data-tdg-snap': o.snap === 'yes' ? 1 : 0, 'data-tdg-move': o.move_time,
					'data-tdg-allowdrag': o.allow_drag === 'yes' ? 1 : 0, 'data-tdg-momentum': o.drag_momentum === 'yes' ? 1 : 0 });
				dN = Math.max(3, Math.min(15, Math.round(o.phones)));
				if (/^#[0-9a-f]{3,8}$/i.test(o.frame_color)) { dStyle += '--tdg-frame:' + o.frame_color + ';'; }
			} else {
				$.extend(df, { 'data-tdg-allowdrag': 0, 'data-tdg-exit': o.direction === 'down' ? 'down' : 'toward', 'data-tdg-behind': o.behind, 'data-tdg-gap': o.gap,
					'data-tdg-offset': o.offset, 'data-tdg-tilt': o.tilt, 'data-tdg-yaw': o.yaw, 'data-tdg-dim': o.dim, 'data-tdg-move': o.move_time });
				if (/^#[0-9a-f]{3,8}$/i.test(o.chrome_color)) { dStyle += '--tdg-chrome:' + o.chrome_color + ';'; }
			}
			cards = '';
			for (i = 0; i < dN; i++) { cards += cardHtml(i % 12, 12); }
			return '<div class="tdg tdg--' + design.replace(/_/g, '-') + '" style="' + dStyle + '" ' + attrStr(df) + '><div class="tdg__stage"><div class="tdg__grid">' + cards + '</div></div></div>';
		}
		if (design === 'device_trio') {
			var tf = $.extend({}, shared, { 'data-tdg-card': o.card_size, 'data-tdg-allowdrag': 0, 'data-tdg-sway': o.direction === 'still' ? 0 : 1,
				'data-tdg-float': o.float, 'data-tdg-depth': o.depth, 'data-tdg-tilt': o.tilt, 'data-tdg-follow': o.follow_pointer === 'yes' ? 1 : 0 });
			var trio = '';
			['laptop', 'tablet', 'phone'].forEach(function (kind, d) {
				trio += '<div class="tdg__device tdg__device--' + kind + '"><div class="tdg__screen">';
				for (var j = 0; j < 4; j++) { trio += cardHtml((d + j * 3) % 12, 12).replace('class="tdg__card"', 'class="tdg__card' + (j === 0 ? ' is-active' : '') + '"'); }
				trio += '</div></div>';
			});
			return '<div class="tdg tdg--device-trio" style="' + style + '" ' + attrStr(tf) + '><div class="tdg__stage"><div class="tdg__trio">' + trio + '</div></div></div>';
		}

		// WebGL designs (real 3D) — the same markup as the front end (views/designs/webgl-common.php); the
		// WebGL driver (gallery-3d-webgl.js, enqueued with this preview) draws the cards on a canvas.
		if (design.indexOf('webgl_') === 0) {
			var wa = { 'data-tdg-light': o.lighting || 'studio' };
			if (design === 'webgl_mirror_ring') {
				$.extend(wa, { 'data-tdg-ring': o.ring_size, 'data-tdg-camh': o.camera_height, 'data-tdg-reflect': o.reflection, 'data-tdg-card': o.card_size });
				if (o.floor_color) { wa['data-tdg-floor'] = o.floor_color; }
			} else if (design === 'webgl_depth_field') {
				$.extend(wa, { 'data-tdg-count': o.cards, 'data-tdg-depth': o.depth, 'data-tdg-spread': o.spread, 'data-tdg-fog': o.fog, 'data-tdg-parallax': o.parallax, 'data-tdg-dir': o.direction === 'away' ? -1 : 1, 'data-tdg-allowdrag': 0 });
				if (o.fog_color) { wa['data-tdg-fogcolor'] = o.fog_color; }
			} else if (design === 'webgl_liquid_slider') {
				$.extend(wa, { 'data-tdg-effect': o.effect, 'data-tdg-strength': o.strength, 'data-tdg-movetime': o.move_time, 'data-tdg-ptr': o.pointer, 'data-tdg-zoom': o.zoom, 'data-tdg-allowdrag': 0, 'data-tdg-swipe': o.swipe === 'no' ? 0 : 1 });
			} else if (design === 'webgl_particle_morph') {
				$.extend(wa, { 'data-tdg-effect': o.effect, 'data-tdg-density': o.density, 'data-tdg-psize': o.particle, 'data-tdg-scatter': o.scatter, 'data-tdg-movetime': o.move_time, 'data-tdg-repel': o.repel, 'data-tdg-allowdrag': 0, 'data-tdg-swipe': o.swipe === 'no' ? 0 : 1 });
			} else if (design === 'webgl_pixel_blocks') {
				$.extend(wa, { 'data-tdg-pattern': o.pattern, 'data-tdg-blocks': o.blocks, 'data-tdg-depth': o.depth, 'data-tdg-gap': o.gap, 'data-tdg-push': o.pointer_push, 'data-tdg-movetime': o.move_time, 'data-tdg-side': o.side_color || '#2b2f38', 'data-tdg-allowdrag': 0, 'data-tdg-swipe': o.swipe === 'no' ? 0 : 1 });
			} else if (design === 'webgl_ferris_wheel') {
				$.extend(wa, { 'data-tdg-count': o.gondolas, 'data-tdg-card': o.card_size, 'data-tdg-swing': o.swing, 'data-tdg-view': o.view_angle, 'data-tdg-wheel': o.wheel_color || '#e9ecf2', 'data-tdg-bulbs': o.bulbs === 'no' ? 0 : 1, 'data-tdg-allowdrag': o.allow_drag === 'no' ? 0 : 1 });
			} else if (design === 'webgl_photo_mobile') {
				$.extend(wa, { 'data-tdg-count': o.photos, 'data-tdg-card': o.card_size, 'data-tdg-breeze': o.breeze, 'data-tdg-sway': o.sway, 'data-tdg-arm': o.arm_color || '#3a3c41', 'data-tdg-allowdrag': 0 });
			} else if (design === 'webgl_photo_fall') {
				$.extend(wa, { 'data-tdg-count': o.photos, 'data-tdg-card': o.card_size, 'data-tdg-flutter': o.flutter, 'data-tdg-wind': o.wind, 'data-tdg-depth': o.depth, 'data-tdg-push': o.pointer_push, 'data-tdg-dir': o.direction === 'right' ? -1 : 1, 'data-tdg-allowdrag': 0 });
			} else if (design === 'webgl_lenticular') {
				$.extend(wa, { 'data-tdg-ridges': o.ridges, 'data-tdg-tilt': o.tilt, 'data-tdg-card': o.card_size, 'data-tdg-movetime': o.move_time, 'data-tdg-follow': o.follow, 'data-tdg-frame': o.frame_color || '#e9ecf2', 'data-tdg-allowdrag': 0, 'data-tdg-swipe': o.swipe === 'no' ? 0 : 1 });
			} else if (design === 'webgl_jigsaw') {
				$.extend(wa, { 'data-tdg-pieces': o.pieces, 'data-tdg-scatter': o.scatter, 'data-tdg-lift': o.pointer_lift, 'data-tdg-movetime': o.move_time, 'data-tdg-board': o.board_color || '#1d2027', 'data-tdg-allowdrag': 0, 'data-tdg-swipe': o.swipe === 'no' ? 0 : 1 });
			} else if (design === 'webgl_paper_fold') {
				$.extend(wa, { 'data-tdg-order': o.order, 'data-tdg-card': o.card_size, 'data-tdg-movetime': o.move_time, 'data-tdg-follow': o.follow, 'data-tdg-paper': o.paper_color || '#f4f1ea', 'data-tdg-allowdrag': 0, 'data-tdg-swipe': o.swipe === 'no' ? 0 : 1 });
			} else if (design === 'webgl_slot_reels') {
				$.extend(wa, { 'data-tdg-reels': o.reels, 'data-tdg-spins': o.spins, 'data-tdg-stagger': o.stagger, 'data-tdg-card': o.card_size, 'data-tdg-movetime': o.move_time, 'data-tdg-follow': o.follow, 'data-tdg-frame': o.frame_color || '#1d2027', 'data-tdg-allowdrag': 0, 'data-tdg-swipe': o.swipe === 'no' ? 0 : 1 });
			} else if (design === 'webgl_peel') {
				$.extend(wa, { 'data-tdg-corner': o.corner, 'data-tdg-curl': o.curl, 'data-tdg-lift': o.lift, 'data-tdg-movetime': o.move_time, 'data-tdg-paper': o.paper_color || '#f4f1ea', 'data-tdg-allowdrag': 0, 'data-tdg-swipe': o.swipe === 'no' ? 0 : 1 });
			} else if (design === 'webgl_bookshelf') {
				$.extend(wa, { 'data-tdg-count': o.books, 'data-tdg-shelves': o.shelves, 'data-tdg-pull': o.pull, 'data-tdg-movetime': o.move_time, 'data-tdg-wood': o.wood_color || '#6b4a32', 'data-tdg-allowdrag': 0 });
			} else if (design === 'webgl_snow_globe') {
				$.extend(wa, { 'data-tdg-snow': o.snow, 'data-tdg-shake': o.shake, 'data-tdg-stir': o.stir, 'data-tdg-card': o.card_size, 'data-tdg-movetime': o.move_time, 'data-tdg-base': o.base_color || '#3b2a20', 'data-tdg-allowdrag': 0, 'data-tdg-swipe': o.swipe === 'no' ? 0 : 1 });
			} else if (design === 'webgl_tv_wall') {
				$.extend(wa, { 'data-tdg-count': o.tvs, 'data-tdg-body': o.body_color || '#d8c9ad', 'data-tdg-mixed': o.mixed_colors === 'no' ? 0 : 1, 'data-tdg-scan': o.scanlines, 'data-tdg-static': o.static, 'data-tdg-curve': o.curve, 'data-tdg-allowdrag': 0 });
			} else if (design === 'webgl_photo_bubbles') {
				$.extend(wa, { 'data-tdg-count': o.bubbles, 'data-tdg-card': o.card_size, 'data-tdg-irid': o.iridescence, 'data-tdg-depth': o.depth, 'data-tdg-wobble': o.wobble, 'data-tdg-push': o.pointer_push, 'data-tdg-dir': o.direction === 'right' ? -1 : 1, 'data-tdg-allowdrag': 0 });
			} else if (design === 'webgl_photo_cube') {
				$.extend(wa, { 'data-tdg-tumble': o.tumble, 'data-tdg-card': o.card_size, 'data-tdg-tilt': o.tilt, 'data-tdg-movetime': o.move_time, 'data-tdg-float': o.float, 'data-tdg-body': o.cube_color || '#22252c', 'data-tdg-shadows': o.shadows === 'no' ? 0 : 1, 'data-tdg-allowdrag': 0, 'data-tdg-swipe': o.swipe === 'no' ? 0 : 1 });
			} else if (design === 'webgl_film_ribbon') {
				$.extend(wa, { 'data-tdg-frames': o.frames, 'data-tdg-wave': o.wave, 'data-tdg-twist': o.twist, 'data-tdg-film': o.film_color || '#16171b', 'data-tdg-holes': o.sprocket_holes === 'no' ? 0 : 1, 'data-tdg-allowdrag': 0 });
			} else if (design === 'webgl_gallery_walk') {
				$.extend(wa, { 'data-tdg-wall': o.wall_color || '#e9e4dc', 'data-tdg-floor': o.floor_color || '#3a3029', 'data-tdg-frame': o.frame_color || '#1c1c1c', 'data-tdg-mat': o.mat === 'no' ? 0 : 1, 'data-tdg-pools': o.light_pools, 'data-tdg-spacing': o.spacing, 'data-tdg-look': o.look_around, 'data-tdg-allowdrag': 0 });
			} else if (design === 'webgl_holo_cards') {
				$.extend(wa, { 'data-tdg-cols': o.columns, 'data-tdg-rows': o.rows, 'data-tdg-gap': o.gap, 'data-tdg-foil': o.foil, 'data-tdg-pattern': o.pattern, 'data-tdg-tilt': o.tilt, 'data-tdg-glare': o.glare, 'data-tdg-allowdrag': 0 });
			} else if (design === 'webgl_domino_row') {
				$.extend(wa, { 'data-tdg-count': o.dominoes, 'data-tdg-spacing': o.spacing, 'data-tdg-view': o.view_angle, 'data-tdg-camh': o.camera_height, 'data-tdg-body': o.domino_color || '#f4f2ee', 'data-tdg-shadows': o.shadows === 'no' ? 0 : 1, 'data-tdg-allowdrag': 0 });
			} else if (design === 'webgl_water_pool') {
				$.extend(wa, { 'data-tdg-cols': o.columns, 'data-tdg-rows': o.rows, 'data-tdg-gap': o.gap, 'data-tdg-refract': o.refraction, 'data-tdg-rain': o.rain, 'data-tdg-shine': o.shine, 'data-tdg-water': o.water_color || '#2a8fbf', 'data-tdg-allowdrag': 0 });
			} else if (design === 'webgl_jelly_grid') {
				$.extend(wa, { 'data-tdg-cols': o.columns, 'data-tdg-rows': o.rows, 'data-tdg-gap': o.gap, 'data-tdg-jelly': o.jelly, 'data-tdg-wobble': o.wobble, 'data-tdg-wave': o.wave, 'data-tdg-view': o.view_angle, 'data-tdg-gloss': o.gloss, 'data-tdg-allowdrag': 0 });
			} else if (design === 'webgl_stage_curtain') {
				$.extend(wa, { 'data-tdg-pleats': o.pleats, 'data-tdg-open': o.opening, 'data-tdg-swag': o.swag, 'data-tdg-movetime': o.move_time, 'data-tdg-valance': o.valance === 'no' ? 0 : 1, 'data-tdg-curtain': o.curtain_color || '#7a1424', 'data-tdg-allowdrag': 0, 'data-tdg-swipe': o.swipe === 'no' ? 0 : 1 });
			} else if (design === 'webgl_shatter') {
				$.extend(wa, { 'data-tdg-shape': o.shape, 'data-tdg-burst': o.burst, 'data-tdg-origin': o.origin, 'data-tdg-pieces': o.pieces, 'data-tdg-spin': o.spin, 'data-tdg-force': o.force, 'data-tdg-movetime': o.move_time, 'data-tdg-allowdrag': 0, 'data-tdg-swipe': o.swipe === 'no' ? 0 : 1 });
			} else if (design === 'webgl_cloth_banners') {
				$.extend(wa, { 'data-tdg-banners': o.banners, 'data-tdg-wind': o.wind, 'data-tdg-ripples': o.ripples, 'data-tdg-gap': o.gap, 'data-tdg-view': o.view_angle, 'data-tdg-rod': o.rod_color || '#3a3c41', 'data-tdg-allowdrag': 0 });
			} else if (design === 'webgl_fold_strip') {
				$.extend(wa, { 'data-tdg-panels': o.panels, 'data-tdg-fold': o.fold, 'data-tdg-unfold': o.unfold === 'no' ? 0 : 1, 'data-tdg-view': o.view_angle, 'data-tdg-allowdrag': o.allow_drag === 'no' ? 0 : 1 });
			} else if (design === 'webgl_flip_book') {
				$.extend(wa, { 'data-tdg-card': o.card_size, 'data-tdg-tilt': o.tilt, 'data-tdg-curl': o.curl, 'data-tdg-movetime': o.move_time, 'data-tdg-cover': o.cover_color || '#2b303b', 'data-tdg-allowdrag': 0, 'data-tdg-swipe': o.swipe === 'no' ? 0 : 1 });
			} else {
				$.extend(wa, { 'data-tdg-cols': o.columns, 'data-tdg-rows': o.rows, 'data-tdg-amp': o.wave_height, 'data-tdg-wavelength': o.wave_length, 'data-tdg-view': o.view_angle, 'data-tdg-lightang': o.light_angle, 'data-tdg-gloss': o.gloss, 'data-tdg-gap': o.gap, 'data-tdg-allowdrag': 0 });
			}
			var wcards = '';
			for (i = 0; i < 12; i++) { wcards += cardHtml(i, 12).replace('<div class="tdg__card">', '<li class="tdg__card">').replace(/<\/div><\/div>$/, '</div></li>'); }
			return '<div class="tdg tdg--' + design.replace(/_/g, '-') + '" style="' + style + ';--tdg-bg:#0d1017" ' + attrStr($.extend({}, shared, wa)) + '><div class="tdg__stage"><ul class="tdg__grid tdg__webgl-list">' + wcards + '</ul></div></div>';
		}

		// carousel_ring
		var ar = attrStr($.extend({}, shared, {
			'data-tdg-tilt': o.tilt, 'data-tdg-roll': o.roll, 'data-tdg-opening': o.ring_opening,
			'data-tdg-ring': o.ring_size, 'data-tdg-spacing': o.spacing, 'data-tdg-persp': o.perspective,
			'data-tdg-backfade': o.back_fade, 'data-tdg-card': o.card_size, 'data-tdg-count': 12
		}));
		cards = '';
		for (i = 0; i < 12; i++) { cards += cardHtml(i, 12); }
		return '<div class="tdg tdg--carousel-ring" style="' + style + '" ' + ar + '><div class="tdg__stage"><div class="tdg__ring">' + cards + '</div></div></div>';
	}

	// ---- read the sibling design_settings values from the DOM (same modal) ----
	// A registered design key (from DEFAULTS), or null. The ONE place detection + the registry agree,
	// so adding a design to DEFAULTS is all it takes for detection to recognise it — no key lists to
	// keep in sync here.
	function knownDesign(k) { k = (k || '').replace(/-/g, '_'); return DEFAULTS[k] ? k : null; }

	// The design picker's OWN value (image-picker: a <select>, a checked input, or a JSON-array hidden
	// input, depending on layout). This is the authoritative choice — unlike the `.chosen` class, it is
	// correct the instant the user picks, so the preview never lags a step behind.
	function pickerValue($pg) {
		var v = $pg.find('select').first().val();
		if (v) { return v; }
		var $c = $pg.find('input[type=radio]:checked, input[type=checkbox]:checked').first();
		if ($c.length && $c.val()) { return $c.val(); }
		var $h = $pg.find('input[type=hidden]').first();
		if ($h.length && $h.val()) {
			var hv = $h.val();
			try { var arr = JSON.parse(hv); if (arr && arr.length) { return arr[0]; } } catch (e) {}
			if (typeof hv === 'string' && hv.charAt(0) !== '[' && hv.charAt(0) !== '{') { return hv; }
		}
		var $sel = $pg.find('.selected, .active, [aria-selected="true"]').first();
		if ($sel.length) { return $sel.attr('data-value') || $sel.attr('data-fw-value') || $sel.attr('value') || $sel.attr('data-choice-key'); }
		return null;
	}

	// The design_settings multi-picker is a SIBLING option, so scope up to the modal/form. It is the
	// multi-picker whose DIRECT-CHILD choice-groups are registered designs — registry-driven, so it
	// recognises any new design automatically and never matches the nested Motion / Source / Click pickers.
	function findDesignMp($preview) {
		var $root = $preview.closest('.media-modal, .fw-options-modal, form');
		if (!$root.length) { $root = $(document.body); }
		var $found = $();
		$root.find('.fw-option-type-multi-picker').each(function () {
			var $m = $(this), hit = false;
			$m.find('> .choice-group[data-choice-key]').each(function () {
				if (knownDesign($(this).attr('data-choice-key'))) { hit = true; return false; }
			});
			if (hit) { $found = $m; return false; }
		});
		return $found;
	}

	function readValues($preview) {
		var $mp = findDesignMp($preview);
		if (!$mp.length) { return null; }

		// AUTHORITATIVE detection = the picker's value (see pickerValue). The `.chosen` class is NOT used
		// to pick the design: the multi-picker stamps `.chosen` on the FIRST group at init and only
		// corrects it asynchronously, so reading it showed EVERY design as carousel_ring. Fallbacks (a
		// chosen design group, then the first design group) only run if the value can't be read.
		var rawPick = pickerValue( $mp.find('.picker-group').first() );
		// A "coming soon" family tile (soon_*): not a real design — show a placeholder, don't fall back
		// to a spinning carousel (which would be confusing while the reveal says "coming soon").
		if ( rawPick && String( rawPick ).indexOf( 'soon_' ) === 0 ) { return { soon: true }; }
		var design = knownDesign( rawPick );
		if (!design) { $mp.find('> .choice-group.chosen[data-choice-key]').each(function () { design = knownDesign($(this).attr('data-choice-key')); return !design; }); }
		if (!design) { $mp.find('> .choice-group[data-choice-key]').each(function () { design = knownDesign($(this).attr('data-choice-key')); return !design; }); }
		if (!design) { design = 'carousel_ring'; }

		// Read leaves from the SELECTED design's own group (matched by key) — the revealed, populated
		// one, never a lazily-empty sibling.
		var $chosen = $mp.find('> .choice-group[data-choice-key]').filter(function () { return knownDesign($(this).attr('data-choice-key')) === design; }).first();
		if (!$chosen.length) { $chosen = $mp.find('> .choice-group.chosen, > .choice-group').first(); }

		var o = $.extend({}, DEFAULTS[design]);
		$chosen.find('.fw-backend-option-descriptor[data-fw-option-id]').each(function () {
			var $d = $(this);
			var key = $d.attr('data-fw-option-id');
			var type = $d.attr('data-fw-option-type');
			// Skip anything inside a NESTED choice-group that isn't the active one (the Motion picker's
			// hidden mode branches — otherwise auto's `direction` and scroll's `direction` would both be
			// read, with the last one winning).
			if ($d.parentsUntil($chosen, '.choice-group').not('.chosen').length) { return; }
			// The nested Motion picker's own select (`mode`) IS the drive.
			if (key === 'mode' && type === 'select') {
				var mv = $d.find('select').val();
				if (mv != null) { o.drive = mv; }
				return;
			}
			if (!(key in o)) { return; } // only geometry/shared keys we know
			if (type === 'slider' || type === 'short-slider') {
				var $inp = $d.find('.fw-irs-range-slider');
				var irs = $inp.data('ionRangeSlider');
				var v = irs && irs.result ? irs.result.from : parseFloat($inp.val());
				if (!isNaN(v)) { o[key] = v; }
			} else if (type === 'select') {
				var sv = $d.find('select').val();
				if (sv != null) { o[key] = sv; }
			} else if (type === 'switch') {
				o[key] = $d.find(':checkbox').is(':checked') ? 'yes' : 'no';
			} else if (type === 'color-picker') {
				var cv = $d.find('input[type=text], input').first().val();
				if (cv && /^#[0-9a-f]{3,8}$/i.test(cv)) { o[key] = cv; }
			}
		});
		return { design: design, o: o };
	}

	function render($preview) {
		try {
			var read = readValues($preview);
			if (!read) { return; } // design picker not in the DOM yet — leave the stage as-is
			var $stage = $preview.find('.fw-gallery-3d-preview__stage');
			if (window.upwGallery3dBumpGen) { window.upwGallery3dBumpGen(); } // kill the previous scene's loops
			if (read.soon) { $stage.html('<div class="tdg-soon"><div class="tdg-soon__badge">' + 'COMING SOON' + '</div><div class="tdg-soon__t">This design isn’t available yet</div></div>'); return; }
			$stage.html(buildScene(read.design, read.o));
			var el = $stage.children('.tdg')[0];
			if (el && window.upwGallery3dInitEl) { window.upwGallery3dInitEl(el); }
		} catch (e) { if (window.console) { console.warn('[Motion Gallery preview]', e); } }
	}

	var debounced = {};
	function scheduleRender($preview, id) {
		clearTimeout(debounced[id]);
		debounced[id] = setTimeout(function () { render($preview); }, 120);
	}

	function onInit(data) {
		var $els = ( data && data.$elements ) ? data.$elements : $(document.body);
		var n = $els.find('.fw-gallery-3d-preview:not(.fw-preview-initialized)').length;
		$els.find('.fw-gallery-3d-preview:not(.fw-preview-initialized)').each(function () {
			var $preview = $(this).addClass('fw-preview-initialized');
			$preview.data('g3dId', 'p' + Math.round(Math.random() * 1e9));
			// Pin the whole preview OPTION — its parent (the options list) is tall so sticky travels, and
			// the option's own padding keeps the card inset (the spacing). Also add the framework's
			// .fw-bottom-border-hidden helper to the SAME wrapper (it carries fw-backend-option-design-
			// default) so the option's bottom-border separator doesn't ride along as a line under the
			// pinned card. Classes are added here, not in PHP, since only the JS knows the wrapper.
			$preview.closest('.fw-backend-option').addClass('fw-g3d-sticky fw-bottom-border-hidden');
			var id = $preview.data('g3dId');
			render($preview);

			// Dark / Light stage background toggle (bound once — the bar is outside the rebuilt stage).
			$preview.on('click', '.fw-gallery-3d-preview__bg button', function () {
				var light = $(this).attr('data-bg') === 'light';
				$preview.toggleClass('is-light', light);
				$preview.find('.fw-gallery-3d-preview__bg button').removeClass('is-active');
				$(this).addClass('is-active');
			});

			// Rebuild on ANY option change in this modal (slider / select / design picker).
			if (window.fw && fw.options && fw.options.on && fw.options.on.change) {
				fw.options.on.change(function () { scheduleRender($preview, id); });
			}
		});
	}

	// A design switch lazily injects + inits the newly-chosen design's nested group AFTER the change
	// event fired, so init any NEW previews and refresh already-initialized ones (their picker value is
	// authoritative now, so they land on the right design + its revealed leaves).
	function onOptionsInit(data) {
		onInit(data);
		$(document.body).find('.fw-gallery-3d-preview.fw-preview-initialized').each(function () {
			var $preview = $(this);
			scheduleRender($preview, $preview.data('g3dId'));
		});
	}

	// preview.js has no hard dependency on fw-events.js, so it may load first — wait for fwEvents.
	function boot() {
		if (typeof fwEvents === 'undefined' || !fwEvents.on) { return setTimeout(boot, 50); }
		fwEvents.on('fw:options:init', onOptionsInit);
		onInit(); // catch any preview already in the DOM
	}
	boot();

})(jQuery);
