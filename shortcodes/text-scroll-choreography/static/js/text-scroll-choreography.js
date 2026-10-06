/* Text Scroll Choreography — Curve renderer (Phase 2). For a text layer with a Curve warp, render the line
 * as SVG text-on-an-arc (ported from the Marquee module's buildCurved geometry: chord→radius, then a single
 * centred <textPath>). The pose engine still poses the layer and the warp wrapper still tilts/skews it — the
 * arc is just the layer's content. The real <tag> is kept in the DOM (visually hidden) for screen readers /
 * SEO. Curve and a per-glyph Text Effect are mutually exclusive (an SVG line has no glyph spans to animate),
 * so view.php never sets both. */
(function () {
	'use strict';
	var SVGNS = 'http://www.w3.org/2000/svg', XLINK = 'http://www.w3.org/1999/xlink', uid = 0;

	function curveOne(wrap) {
		if (wrap.__curved) { return; }
		var curve = parseFloat(wrap.getAttribute('data-curve')) || 0;
		if (!curve) { return; }
		var tag = wrap.querySelector('h1,h2,h3,h4,h5,h6,p,div');
		if (!tag) { return; }
		var text = (tag.textContent || '').trim();
		if (!text) { return; }
		wrap.__curved = true;

		var cs = getComputedStyle(tag);
		var fs = parseFloat(cs.fontSize) || 32;
		var W  = Math.max(60, Math.round(tag.getBoundingClientRect().width || wrap.getBoundingClientRect().width || 600));
		var peak = curve / 100 * (W * 0.32), absP = Math.abs(peak), up = peak >= 0;
		var H  = Math.ceil(absP + fs * 1.7 + 16);
		var y0 = up ? (H - fs * 0.9) : (fs * 0.9);
		var s  = Math.max(1, absP), R = (W * W / 4 + s * s) / (2 * s);
		var id = 'fwtcpath' + (++uid);

		var svg = document.createElementNS(SVGNS, 'svg');
		svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
		svg.setAttribute('width', W); svg.setAttribute('height', H);
		svg.setAttribute('preserveAspectRatio', 'none');
		svg.setAttribute('class', 'fw-choreo__svg');
		svg.setAttribute('aria-hidden', 'true');

		var defs = document.createElementNS(SVGNS, 'defs'), path = document.createElementNS(SVGNS, 'path');
		path.setAttribute('id', id); path.setAttribute('fill', 'none');
		path.setAttribute('d', 'M 0 ' + y0.toFixed(1) + ' A ' + R.toFixed(2) + ' ' + R.toFixed(2) + ' 0 0 ' + (up ? 1 : 0) + ' ' + W + ' ' + y0.toFixed(1));
		defs.appendChild(path); svg.appendChild(defs);

		var t = document.createElementNS(SVGNS, 'text');
		t.setAttribute('fill', cs.color);
		t.setAttribute('font-family', cs.fontFamily);
		t.setAttribute('font-size', parseFloat(cs.fontSize));
		t.setAttribute('font-weight', cs.fontWeight);
		t.setAttribute('dominant-baseline', 'middle');
		t.setAttribute('letter-spacing', cs.letterSpacing === 'normal' ? '0' : cs.letterSpacing);

		var tp = document.createElementNS(SVGNS, 'textPath');
		tp.setAttributeNS(XLINK, 'href', '#' + id); tp.setAttribute('href', '#' + id);
		tp.textContent = text; t.appendChild(tp); svg.appendChild(t);

		// Keep the real heading for a11y/SEO, but hide it visually and show the arc.
		tag.style.position = 'absolute';
		tag.style.width = '1px'; tag.style.height = '1px';
		tag.style.overflow = 'hidden'; tag.style.clip = 'rect(0 0 0 0)';
		tag.style.whiteSpace = 'nowrap';
		wrap.appendChild(svg);

		// Centre the line on the arc.
		var pathLen = (path.getTotalLength && path.getTotalLength()) || W;
		var txtLen  = (tp.getComputedTextLength && tp.getComputedTextLength()) || W * 0.6;
		tp.setAttribute('startOffset', Math.max(0, (pathLen - txtLen) / 2).toFixed(1));
	}

	// ---- Scroll-scrubbed read-along (Phase 3) --------------------------------
	// For a layer with the "Scroll reveal" effect, split the line into pieces and light them across the
	// layer's appear → hold scroll window — glyphs resolve as the visitor reads down. Self-contained (no
	// text-effects dependency); one shared scroll handler; honours reduced motion.
	var REDUCE = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

	function splitPieces(tag, mode) {
		var text = tag.textContent || '';
		tag.textContent = '';
		var tokens = ( 'words' === mode ) ? text.split(/(\s+)/) : text.split('');
		var pieces = [];
		tokens.forEach(function (tok) {
			if ('' === tok) { return; }
			if (/^\s+$/.test(tok)) { tag.appendChild(document.createTextNode(tok)); return; }
			var s = document.createElement('span');
			s.className = 'fw-choreo__piece';
			s.textContent = tok;
			tag.appendChild(s);
			pieces.push(s);
		});
		return pieces;
	}

	var scrubItems = [];
	function buildScrub(wrap) {
		if (wrap.__scrub) { return; }
		wrap.__scrub = true;
		var tag = wrap.querySelector('h1,h2,h3,h4,h5,h6,p,div');
		if (!tag) { return; }
		var scene = wrap.closest ? wrap.closest('.fw-choreo') : null;
		if (!scene) { return; }
		var cfg = {}; try { cfg = JSON.parse(scene.getAttribute('data-choreo') || '{}'); } catch (e) {}
		var pieces = splitPieces(tag, wrap.getAttribute('data-scrub-split') === 'words' ? 'words' : 'chars');
		if (!pieces.length) { return; }
		if (REDUCE) { pieces.forEach(function (p) { p.classList.add('is-on'); }); return; }
		scrubItems.push({
			scene: scene, pieces: pieces, n: pieces.length,
			travel: Math.max(1, cfg.travel || 3),
			from: parseFloat(wrap.getAttribute('data-scrub-from')) || 0,
			to:   parseFloat(wrap.getAttribute('data-scrub-to')) || 100
		});
	}
	function updateScrub() {
		var sy = window.pageYOffset || window.scrollY || 0, vh = window.innerHeight || 1;
		for (var k = 0; k < scrubItems.length; k++) {
			var it = scrubItems[k];
			var startY = it.scene.getBoundingClientRect().top + sy;
			var at = ((sy - startY) / (it.travel * vh)) * 100;
			var span = (it.to - it.from) || 1;
			var t = (at - it.from) / span; t = t < 0 ? 0 : (t > 1 ? 1 : t);
			var active = Math.round(t * it.n);
			for (var i = 0; i < it.n; i++) { it.pieces[i].classList.toggle('is-on', i < active); }
		}
	}
	var sTick = false;
	function scheduleScrub() { if (!sTick) { sTick = true; requestAnimationFrame(function () { updateScrub(); sTick = false; }); } }

	function boot() {
		[].slice.call(document.querySelectorAll('.fw-choreo__text[data-curve]')).forEach(curveOne);
		[].slice.call(document.querySelectorAll('.fw-choreo__text[data-scrub]')).forEach(buildScrub);
		if (scrubItems.length) { updateScrub(); }
	}
	function ready() { if (document.fonts && document.fonts.ready) { document.fonts.ready.then(boot); } else { boot(); } }
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', ready); } else { ready(); }
	window.addEventListener('scroll', scheduleScrub, { passive: true });
	window.addEventListener('resize', scheduleScrub, { passive: true });
	window.fwTextChoreoRescan = boot;

	/* Re-init hook for surfaces that insert this element's markup after page load (the
	   block editor's preview, the Elementor editor's re-renders): they call every
	   window.fwShortcodeInit entry. The scan skips elements it has already set up, so
	   repeated calls are safe. */
	window.fwShortcodeInit = window.fwShortcodeInit || [];
	window.fwShortcodeInit.push( function () { boot(); } );
})();
