/**
 * UnysonPlus Animation Engine — Horizontal Scroll Section (8 styles).
 *
 * Pins a .upw-hscroll section (sticky 100vh) and translates its panel row across as the visitor
 * scrolls through the section's height. One passive scroll listener drives a per-style transform,
 * tuned by one "intensity" knob. The "drag" style is a free horizontal drag-through instead (no
 * pin). No library.
 */
(function () {
	'use strict';
	if (typeof window === 'undefined' || typeof document === 'undefined') return;
	var cfg = window.upwHScrollCfg || {};

	function reducedMotion() { return cfg.reducedMotion && window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches; }
	function isMobile() { return cfg.disableMobile && window.matchMedia && window.matchMedia('(max-width: 767px)').matches; }
	function clamp(v, a, b) { return v < a ? a : (v > b ? b : v); }
	function num(el, attr, d) { var v = parseFloat(el.getAttribute(attr)); return isNaN(v) ? d : v; }

	function trackOf(sec) {
		var row = sec.querySelector('.fw-row') || sec.querySelector('.upw-row');
		if (row && row.children.length >= 2) return row;
		var kids = sec.children;
		for (var i = 0; i < kids.length; i++) { if (kids[i].children && kids[i].children.length >= 2) return kids[i]; }
		return null;
	}

	function initSection(sec) {
		var track = trackOf(sec);
		if (!track) return;
		var panels = Array.prototype.slice.call(track.children);
		if (panels.length < 2) return;

		var style = sec.getAttribute('data-hs-style') || 'standard';
		var intensity = clamp(num(sec, 'data-hs-intensity', 0.5), 0, 1);

		// Pin wrapper: move the track into a sticky 100vh box (drag mode un-pins it below).
		var pin = document.createElement('div');
		pin.className = 'upw-hs-pin';
		sec.insertBefore(pin, track);
		pin.appendChild(track);
		track.classList.add('upw-hs-track');
		panels.forEach(function (p) { p.classList.add('upw-hs-panel'); });

		if (style === 'drag') { initDrag(sec, pin, track); return; }

		function distance() {
			var d = track.scrollWidth - window.innerWidth;
			sec.style.height = (window.innerHeight + Math.max(0, d)) + 'px';
			return Math.max(0, d);
		}
		var dist = distance();
		if (style === 'snap') { track.style.transition = 'transform .5s cubic-bezier(.22,1,.36,1)'; }

		var lastScrolled = 0, vel = 0;
		function scrolledNow() {
			var top = sec.getBoundingClientRect().top;
			var s = clamp(-top, 0, dist);
			vel = s - lastScrolled; lastScrolled = s;
			return s;
		}

		function paintTrack(scrolled, skewDeg) {
			var baseX;
			if (style === 'reverse') { baseX = -(dist - scrolled); }
			else if (style === 'snap') { var step = panels[0].getBoundingClientRect().width || 1; baseX = clamp(-Math.round(scrolled / step) * step, -dist, 0); }
			else { baseX = -scrolled; }
			var t = 'translate3d(' + baseX + 'px,0,0)';
			if (skewDeg) { t += ' skewX(' + skewDeg.toFixed(2) + 'deg)'; }
			// Perspective Wall tilts the whole strip in 3D so panels recede toward one side.
			if (style === 'wall') { t = 'perspective(1400px) rotateY(' + (-intensity * 22).toFixed(1) + 'deg) ' + t; }
			track.style.transform = t;
		}

		var PANEL_STYLES = { coverflow: 1, rotate3d: 1, parallax: 1, fade: 1, blur: 1, arc: 1, wave: 1, zigzag: 1, grow: 1 };
		function paintPanels() {
			if (!PANEL_STYLES[style]) return;
			var vw = window.innerWidth, cx = vw / 2;
			panels.forEach(function (panel, i) {
				var r = panel.getBoundingClientRect();
				var d = ((r.left + r.width / 2) - cx) / vw; // 0 at viewport centre
				var tf = '', op = '', fil = '';
				panel.style.transformOrigin = 'center center';
				switch (style) {
					case 'coverflow':
						tf = 'scale(' + (1 - clamp(Math.abs(d) * intensity * 1.1, 0, 0.4)).toFixed(3) + ')';
						op = (1 - clamp(Math.abs(d) * intensity * 1.4, 0, 0.65)).toFixed(3);
						break;
					case 'rotate3d':
						tf = 'perspective(1200px) rotateY(' + clamp(-d * intensity * 90, -55, 55).toFixed(2) + 'deg) scale(' + (1 - Math.abs(d) * 0.1).toFixed(3) + ')';
						break;
					case 'parallax':
						tf = 'translate3d(' + (((i % 2) ? 1 : -1) * lastScrolled * 0.08 * intensity).toFixed(1) + 'px,0,0)';
						break;
					case 'fade':
						op = (1 - clamp(Math.abs(d) * intensity * 1.6, 0, 0.82)).toFixed(3);
						break;
					case 'blur':
						fil = 'blur(' + clamp(Math.abs(d) * intensity * 9, 0, 10).toFixed(2) + 'px)';
						op = (1 - clamp(Math.abs(d) * intensity * 0.5, 0, 0.4)).toFixed(3);
						break;
					case 'arc':
						tf = 'translate3d(0,' + (-intensity * 60 * Math.max(0, 1 - (d * 2) * (d * 2))).toFixed(1) + 'px,0)';
						break;
					case 'wave':
						tf = 'translate3d(0,' + (Math.sin(d * Math.PI * 3) * intensity * 40).toFixed(1) + 'px,0)';
						break;
					case 'zigzag':
						tf = 'translate3d(0,' + (((i % 2) ? 1 : -1) * intensity * 40).toFixed(1) + 'px,0)';
						break;
					case 'grow':
						var g = Math.max(0, d);
						tf = 'scale(' + (1 - clamp(g * intensity * 1.2, 0, 0.4)).toFixed(3) + ')';
						op = (1 - clamp(g * intensity * 1.5, 0, 0.7)).toFixed(3);
						break;
				}
				panel.style.transform = tf;
				panel.style.opacity = op;
				panel.style.filter = fil;
			});
		}

		if (style === 'skew') {
			// Continuous loop so the skew eases back to 0 when scrolling stops.
			var curSkew = 0;
			(function loop() {
				var s = scrolledNow();
				var target = clamp(vel * intensity * 3, -16, 16);
				vel *= 0.8;
				curSkew += (target - curSkew) * 0.15;
				if (Math.abs(curSkew) < 0.02) curSkew = 0;
				paintTrack(s, curSkew);
				requestAnimationFrame(loop);
			})();
		} else {
			var pending = false;
			function tick() { pending = false; var s = scrolledNow(); paintTrack(s, 0); paintPanels(); }
			function onScroll() { if (pending) return; pending = true; requestAnimationFrame(tick); }
			window.addEventListener('scroll', onScroll, { passive: true });
			window.addEventListener('resize', function () { dist = distance(); onScroll(); }, { passive: true });
			window.addEventListener('load', function () { dist = distance(); onScroll(); });
			tick();
		}
	}

	// Free horizontal drag-through: not pinned, panel-height strip; pointer/touch drag + inertia.
	function initDrag(sec, pin, track) {
		pin.classList.add('upw-hs-drag');
		var x = 0, min = 0, startX = 0, startPos = 0, dragging = false, vx = 0, last = 0, raf = null;
		function bounds() { min = Math.min(0, pin.clientWidth - track.scrollWidth); }
		function apply() { x = clamp(x, min, 0); track.style.transform = 'translate3d(' + x + 'px,0,0)'; }
		function px(e) { return e.touches ? e.touches[0].clientX : e.clientX; }
		function down(e) { dragging = true; track.classList.add('is-grabbing'); startX = px(e); startPos = x; vx = 0; last = startX; if (raf) { cancelAnimationFrame(raf); raf = null; } }
		function move(e) { if (!dragging) return; var cx = px(e); x = startPos + (cx - startX); vx = cx - last; last = cx; apply(); if (e.cancelable && Math.abs(cx - startX) > 4) e.preventDefault(); }
		function up() { if (!dragging) return; dragging = false; track.classList.remove('is-grabbing'); inertia(); }
		function inertia() { if (Math.abs(vx) < 0.4) return; x += vx; vx *= 0.94; apply(); raf = requestAnimationFrame(inertia); }
		track.addEventListener('mousedown', down);
		window.addEventListener('mousemove', move);
		window.addEventListener('mouseup', up);
		track.addEventListener('touchstart', down, { passive: true });
		track.addEventListener('touchmove', move, { passive: false });
		track.addEventListener('touchend', up);
		window.addEventListener('resize', function () { bounds(); apply(); });
		window.addEventListener('load', function () { bounds(); apply(); });
		bounds(); apply();
	}

	function init() {
		if (reducedMotion() || isMobile()) return;
		var els = document.querySelectorAll('.upw-hscroll');
		Array.prototype.forEach.call(els, initSection);
	}

	document.readyState !== 'loading' ? init() : document.addEventListener('DOMContentLoaded', init);
})();
