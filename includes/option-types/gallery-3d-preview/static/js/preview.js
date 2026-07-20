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

	// Per-design fallbacks (mirror options.php defaults) — used when a value can't be read yet.
	var DEFAULTS = {
		carousel_ring: { drive: 'auto', allow_drag: 'no', speed: 16, direction: 'left', hover_behavior: 'slow', tilt: -28, ring_opening: 55, roll: 0, ring_size: 80, spacing: 100, perspective: 18, back_fade: 70, card_size: 21, card_ratio: '1-1', corner_radius: 6, padding: 0 },
		panorama_wall: { drive: 'continuous', allow_drag: 'no', speed: 20, direction: 'left', hover_behavior: 'slow', rows: 5, columns: 11, curvature: -100, tilt: 0, gap: 5, edge_fade: 0, perspective: 68, card_size: 20, card_ratio: '16-9', corner_radius: 2, padding: 0 },
		card_sphere: { drive: 'continuous', allow_drag: 'no', speed: 20, direction: 'left', hover_behavior: 'slow', globe_size: 70, card_size: 20, gap: 2.5, back_fade: 55, tilt: 0, perspective: 55, card_ratio: '16-9', corner_radius: 2, padding: 0 },
		orbit_globe: { drive: 'continuous', allow_drag: 'yes', speed: 20, direction: 'left', hover_behavior: 'slow', globe_size: 50, card_size: 28, gap: 2.5, back_fade: 55, tilt: 27, card_ratio: '1-1', corner_radius: 2 }
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
	// The design_settings multi-picker is a SIBLING option, so scope up to the modal/form (not the
	// preview's own .fw-inner) and pick the multi-picker that actually holds design choices.
	function findDesignMp($preview) {
		var $root = $preview.closest('.media-modal, .fw-options-modal, form');
		if (!$root.length) { $root = $(document.body); }
		var $found = $();
		$root.find('.fw-option-type-multi-picker').each(function () {
			var $m = $(this);
			if ($m.find('.choice-group[data-choice-key="carousel_ring"], .choice-group[data-choice-key="panorama_wall"], .choice-group[data-choice-key="card_sphere"]').length) {
				$found = $m; return false;
			}
		});
		return $found;
	}

	function readValues($preview) {
		var $mp = findDesignMp($preview);
		if (!$mp.length) { return null; }
		var $chosen = $mp.find('.choice-group.chosen').first();
		if (!$chosen.length) { $chosen = $mp.find('.choice-group').first(); }
		var design = ($chosen.attr('data-choice-key') || 'carousel_ring').replace(/-/g, '_');
		if (!DEFAULTS[design]) { design = 'carousel_ring'; }

		var o = $.extend({}, DEFAULTS[design]);
		$chosen.find('.fw-backend-option-descriptor[data-fw-option-id]').each(function () {
			var $d = $(this);
			var key = $d.attr('data-fw-option-id');
			var type = $d.attr('data-fw-option-type');
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
			var $stage = $preview.find('.fw-gallery-3d-preview__stage');
			if (window.upwGallery3dBumpGen) { window.upwGallery3dBumpGen(); } // kill the previous scene's loops
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
			// Pin the whole preview OPTION — its parent (the options list) is tall so sticky travels, and
			// the option's own padding keeps the card inset (the spacing). Also add the framework's
			// .fw-bottom-border-hidden helper to the SAME wrapper (it carries fw-backend-option-design-
			// default) so the option's bottom-border separator doesn't ride along as a line under the
			// pinned card. Classes are added here, not in PHP, since only the JS knows the wrapper.
			$preview.closest('.fw-backend-option').addClass('fw-g3d-sticky fw-bottom-border-hidden');
			var id = 'p' + Math.round(Math.random() * 1e9);
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

	// preview.js has no hard dependency on fw-events.js, so it may load first — wait for fwEvents.
	function boot() {
		if (typeof fwEvents === 'undefined' || !fwEvents.on) { return setTimeout(boot, 50); }
		fwEvents.on('fw:options:init', onInit);
		onInit(); // catch any preview already in the DOM
	}
	boot();

})(jQuery);
