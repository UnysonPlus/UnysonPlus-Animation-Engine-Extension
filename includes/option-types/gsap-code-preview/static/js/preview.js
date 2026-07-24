/*
 * Scroll Motion — "Show generated GSAP" (admin only, READ-ONLY teaching panel).
 *
 * Each preview host lives INSIDE one effect group (reveal / stagger / parallax / pin / scrub) and
 * carries data-effect. On init + on every fw:options:change it reads that group's sibling option
 * values from the modal DOM and prints the GSAP those settings generate — mirroring upw-gsap.js.
 *
 * ⚠ KEEP IN SYNC with modules/scroll-motion/static/js/upw-gsap.js. This is a faithful *approximation*
 * for teaching (labelled as such in the UI), not the byte-exact runtime call — the runtime uses
 * fromTo + a compound scale/blur; here we favour the clearest equivalent (gsap.from) a learner can
 * read and copy. The ease/duration/start/scrub/marker mapping IS exact.
 *
 * Nothing here can break the modal: it only READS siblings and writes into its own <pre>. Any throw
 * is caught and the panel keeps its last good code.
 */
(function ($) {
	'use strict';

	// Style preset → ease + duration (mirrors STYLES in upw-gsap.js).
	var STYLES = {
		subtle:   { ease: 'power2.out',         duration: 0.6 },
		standard: { ease: 'power3.out',         duration: 0.9 },
		dramatic: { ease: 'expo.out',           duration: 1.2 },
		bounce:   { ease: 'back.out(1.7)',      duration: 1.0 },
		elastic:  { ease: 'elastic.out(1,0.5)', duration: 1.3 }
	};

	function offsetFor(dir, dist) {
		var o = {};
		dir = dir || 'up';
		if (dir.indexOf('up') > -1) o.y = dist;
		else if (dir.indexOf('down') > -1) o.y = -dist;
		if (dir.indexOf('left') > -1) o.x = dist;
		else if (dir.indexOf('right') > -1) o.x = -dist;
		return o;
	}

	// Pretty-print a JS object literal one prop per line at a given indent.
	function obj(o, indent) {
		var pad = new Array(indent + 1).join('  ');
		var inner = new Array(indent + 2).join('  ');
		var keys = Object.keys(o);
		if (!keys.length) { return '{}'; }
		var lines = keys.map(function (k) {
			var v = o[k];
			var val = (v && v.__raw) ? v.__raw : (typeof v === 'string' ? '"' + v + '"' : v);
			return inner + k + ': ' + val;
		});
		return '{\n' + lines.join(',\n') + '\n' + pad + '}';
	}
	var raw = function (s) { return { __raw: s }; };

	// Read the effect group's values. The preview host sits inside .choice-group[data-choice-key=effect];
	// scope to that group and read each descriptor.
	function readGroup($host, effect) {
		var o = {};
		var $group = $host.closest('.choice-group[data-choice-key="' + effect + '"]');
		if (!$group.length) { $group = $host.closest('.fw-backend-option-type-group, .choice-group').first(); }
		$group.find('.fw-backend-option-descriptor[data-fw-option-id]').each(function () {
			var $d = $(this);
			var key = $d.attr('data-fw-option-id');
			if (!key) { return; }
			// Skip nested picker internals we handle explicitly (advanced.*).
			var $sel = $d.children().find('select').addBack('select').first();
			if ($d.find('.fw-irs-range-slider').length) {
				var $inp = $d.find('.fw-irs-range-slider').first();
				var irs = $inp.data && $inp.data('ionRangeSlider');
				o[key] = irs && irs.result ? irs.result.from : parseFloat($inp.val());
			} else if ($d.find(':checkbox').length) {
				o[key] = $d.find(':checkbox').first().is(':checked') ? 'yes' : 'no';
			} else if ($d.find('input[type="text"], input[type="number"]').length) {
				o[key] = $d.find('input[type="text"], input[type="number"]').first().val();
			} else if ($sel.length) {
				o[key] = $sel.val();
			}
		});
		// Advanced picker: read mode + custom.* if present in the group.
		var $adv = $group.find('.fw-backend-option-descriptor[data-fw-option-id="mode"]').first();
		if ($adv.length) { o._advMode = $adv.find('select').val(); }
		return o;
	}

	// Resolve the effective ease: Advanced override wins, else the Style preset.
	function easeOf(o, style) {
		if (o._advMode === 'custom') {
			var e = o.ease;
			if (e === 'custom') { e = (o.ease_custom || '').trim(); }
			if (e && e !== '') { return e; }
		}
		return (STYLES[style] || STYLES.standard).ease;
	}
	function scrubOf(o) {
		if (o._advMode === 'custom') {
			var sm = parseFloat(o.scrub_smooth);
			if (!isNaN(sm) && sm > 0) { return sm; }
		}
		return true;
	}
	function markersOf(o) { return o._advMode === 'custom' && o.markers === 'yes'; }

	// Build the GSAP code string for one effect.
	function codeFor(effect, o) {
		var style = o.style || 'standard';
		var pre = STYLES[style] || STYLES.standard;
		var ease = easeOf(o, style);
		var start = o.start || 'top 85%';
		var startShown = /^[a-z]+ [a-z0-9%]+$/i.test(start) ? start : 'top 85%';

		if (effect === 'reveal' || effect === 'stagger') {
			var dist = parseFloat(o.distance); if (isNaN(dist)) { dist = 50; }
			var vars = { opacity: 0 };
			var off = offsetFor(o.direction || 'up', dist);
			if (off.y != null) { vars.y = off.y; }
			if (off.x != null) { vars.x = off.x; }
			vars.duration = pre.duration;
			vars.ease = ease;
			if (effect === 'stagger') {
				var each = parseFloat(o.stagger_each); if (isNaN(each)) { each = 0.12; }
				vars.stagger = each;
			}
			var st = { trigger: raw('el'), start: startShown };
			if (o.once === 'no') { st.toggleActions = 'play none none reverse'; }
			if (markersOf(o)) { st.markers = true; }
			vars.scrollTrigger = raw(obj(st, 1));
			var target = effect === 'stagger' ? 'el.children' : 'el';
			return 'gsap.from(' + target + ', ' + obj(vars, 0) + ');';
		}

		if (effect === 'parallax') {
			var axis = o.axis === 'horizontal' ? 'xPercent' : 'yPercent';
			var speed = parseFloat(o.speed); if (isNaN(speed)) { speed = 20; }
			var from = {}; from[axis] = -speed;
			var to = {}; to[axis] = speed; to.ease = 'none';
			var stp = { trigger: raw('el'), start: 'top bottom', end: 'bottom top', scrub: scrubOf(o) };
			if (markersOf(o)) { stp.markers = true; }
			to.scrollTrigger = raw(obj(stp, 1));
			return 'gsap.fromTo(el, ' + obj(from, 0) + ',\n  ' + obj(to, 0) + ');';
		}

		if (effect === 'pin') {
			var len = parseFloat(o.pin_length); if (isNaN(len)) { len = 100; }
			var stc = { trigger: raw('el'), start: 'top top', end: raw('"+=' + len + '%"'), pin: true, anticipatePin: 1 };
			if (markersOf(o)) { stc.markers = true; }
			return 'ScrollTrigger.create(' + obj(stc, 0) + ');';
		}

		if (effect === 'scrub') {
			var f = { opacity: 0 };
			var t = { opacity: 1, ease: 'none' };
			var sts = { trigger: raw('el'), start: startShown, end: 'center center', scrub: scrubOf(o) };
			if (markersOf(o)) { sts.markers = true; }
			t.scrollTrigger = raw(obj(sts, 1));
			return 'gsap.fromTo(el, ' + obj(f, 0) + ',\n  ' + obj(t, 0) + ');';
		}

		return '// (no preview for this effect yet)';
	}

	function render($host) {
		try {
			var effect = $host.attr('data-effect') || '';
			var o = readGroup($host, effect);
			var code = codeFor(effect, o);
			$host.find('code').text(code);
		} catch (e) { /* keep last good code */ }
	}

	var debounced = {};
	function scheduleRender($host, id) {
		clearTimeout(debounced[id]);
		debounced[id] = setTimeout(function () { render($host); }, 120);
	}

	// Mirror gallery-3d-preview: the framework dispatches through its own `fwEvents` bus + the
	// `fw.options.on.change` API — NOT jQuery DOM events. Binding to $(document).on('fw:options:*')
	// silently never fires (that was the bug that left the panel empty).
	function onInit(data) {
		var $els = ( data && data.$elements ) ? data.$elements : $(document.body);
		$els.find('[data-fw-gsap-code]:not(.fw-gsap-code-init)').each(function () {
			var $host = $(this).addClass('fw-gsap-code-init');
			var id = 'g' + Math.round(Math.random() * 1e9);
			render($host);
			$host.on('click', '[data-copy]', function () {
				var txt = $host.find('code').text();
				try {
					if (navigator.clipboard) { navigator.clipboard.writeText(txt); }
					else { var ta = document.createElement('textarea'); ta.value = txt; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta); }
					var $b = $host.find('[data-copy]'); var t = $b.text(); $b.text('Copied'); setTimeout(function () { $b.text(t); }, 1200);
				} catch (e2) {}
			});
			// Re-render on ANY option change in this modal (effect switch, sliders, selects, Advanced).
			if (window.fw && fw.options && fw.options.on && fw.options.on.change) {
				fw.options.on.change(function () { scheduleRender($host, id); });
			}
		});
	}

	// No hard dependency on fw-events.js, so it may load first — wait for fwEvents.
	function boot() {
		if (typeof fwEvents === 'undefined' || !fwEvents.on) { return setTimeout(boot, 50); }
		fwEvents.on('fw:options:init', onInit);
		onInit(); // catch any panel already in the DOM
	}
	boot();
})(jQuery);
