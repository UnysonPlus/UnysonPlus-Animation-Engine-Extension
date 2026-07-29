(function(){"use strict";
var API=window.upwTextApi||(window.upwTextApi={}),H=window.upwText||(window.upwText={});
var reduce=API.reduce,targetsOf=API.targetsOf,onView=API.onView,piece=API.piece,wrapPieces=API.wrapPieces,wrapLines=API.wrapLines,translateFrom=API.translateFrom,doReveal=API.doReveal,cssEffect=API.cssEffect,cssTriggered=API.cssTriggered,FLAP=API.FLAP,GLYPH=API.GLYPH;
// The gradient is CSS-driven (scoped off the wrapper's data-text), so it renders a static,
// centered spotlight with NO JS — which is exactly the reduced-motion fallback (the core's
// init() returns before calling this partial when reduce is on). Here we only add the LIVE
// pointer tracking, writing the cursor position (relative to the element) into --tt-x/--tt-y.
H.spotlight_text = function (el, target) {
	if (el._upwSpot) { return; } // one listener per wrapper (fn runs once per target)
	el._upwSpot = 1;
	var raf = 0, px = 0, py = 0;
	function apply() { raf = 0; el.style.setProperty('--tt-x', px + 'px'); el.style.setProperty('--tt-y', py + 'px'); }
	el.addEventListener('pointermove', function (e) {
		var r = el.getBoundingClientRect();
		px = e.clientX - r.left; py = e.clientY - r.top;
		if (!raf) { raf = (window.requestAnimationFrame || function (f) { return setTimeout(f, 16); })(apply); }
	}, { passive: true });
	el.addEventListener('pointerleave', function () {
		el.style.removeProperty('--tt-x'); el.style.removeProperty('--tt-y'); // back to centered
	}, { passive: true });
};
})();
