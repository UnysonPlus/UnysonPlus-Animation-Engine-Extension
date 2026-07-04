/**
 * UnysonPlus Animation Engine — Horizontal Scroll Section core.
 *
 * Pins a .upw-hscroll section and translates its panel row across on scroll. The track-level
 * styles (standard/reverse/snap/wall/skew) and the drag-through mode live here; the per-PANEL
 * styles (coverflow/rotate3d/parallax/fade/blur/arc/wave/zigzag/grow) each ship as their own
 * partial that registers window.upwHsFx[<style>] — only the used style's partial is enqueued.
 * One passive scroll listener, no library.
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
		if (sec._upwHs) { return; }
		var track = trackOf(sec);
		if (!track) return;
		var panels = Array.prototype.slice.call(track.children);
		if (panels.length < 2) return;
		sec._upwHs = true;

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

		// Per-PANEL styles come from the on-demand registry (only the used one is loaded).
		function paintPanels() {
			var fx = window.upwHsFx && window.upwHsFx[style];
			if (!fx) return;
			var vw = window.innerWidth, cx = vw / 2;
			panels.forEach(function (panel, i) {
				var r = panel.getBoundingClientRect();
				var d = ((r.left + r.width / 2) - cx) / vw; // 0 at viewport centre
				panel.style.transformOrigin = 'center center';
				var out = fx(d, i, intensity, lastScrolled, clamp) || {};
				panel.style.transform = out.tf || '';
				panel.style.opacity = (out.op !== undefined && out.op !== '') ? out.op : '';
				panel.style.filter = out.fil || '';
			});
		}

		if (style === 'skew') {
			// Continuous loop so the skew eases back to 0 when scrolling stops. Driven by the
			// shared frame scheduler (window.upwAnimRaf) — pauses while the tab is hidden.
			var curSkew = 0;
			if (window.upwAnimRaf) {
				window.upwAnimRaf.add(function () {
					var s = scrolledNow();
					var target = clamp(vel * intensity * 3, -16, 16);
					vel *= 0.8;
					curSkew += (target - curSkew) * 0.15;
					if (Math.abs(curSkew) < 0.02) curSkew = 0;
					paintTrack(s, curSkew);
				});
			}
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

	// Defer so the per-panel-style partial (which loads after this core) registers first.
	if (document.readyState !== 'loading') { setTimeout(init, 0); } else { document.addEventListener('DOMContentLoaded', init); }
	window.upwHsRescan = init;
})();
