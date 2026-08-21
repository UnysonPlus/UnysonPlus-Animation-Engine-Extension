/*
 * 3D Gallery — in-modal live preview (admin only).
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
		// Flat tabletop scatter + device frame. The preview always runs these in AUTO cycle (there is no
		// scroll in the modal), so the scatter's sets sweep in/out and the device cycles screens on a
		// timer — a lively, representative preview of the design regardless of the saved cycle mode.
		photo_scatter: { visible: 9, rotation: 12, size_variance: 30, spread: 90, card_size: 18, card_ratio: '3-4', corner_radius: 4, padding: 0, from: 'edges', exit: 'sweep' },
		device_cycler: { device: 'laptop', transition: 'fade', card_size: 62, card_ratio: '16-9', corner_radius: 10, padding: 0 },
		card_stack: { exit: 'down', behind: 3, stack_offset: 5, stack_scale: 6, card_size: 40, card_ratio: '4-3', corner_radius: 14, padding: 0 }
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
			var dStyle = style + '--tdg-dev-w:' + o.card_size + '%;';
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
		} catch (e) { if (window.console) { console.warn('[3D Gallery preview]', e); } }
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
