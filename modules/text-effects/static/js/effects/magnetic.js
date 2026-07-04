(function(){"use strict";
var API=window.upwTextApi||(window.upwTextApi={}),H=window.upwText||(window.upwText={});
var reduce=API.reduce,targetsOf=API.targetsOf,onView=API.onView,piece=API.piece,wrapPieces=API.wrapPieces,wrapLines=API.wrapLines,translateFrom=API.translateFrom,doReveal=API.doReveal,cssEffect=API.cssEffect,cssTriggered=API.cssTriggered,FLAP=API.FLAP,GLYPH=API.GLYPH;
H.magnetic = function (el, target) {
		var pieces = wrapPieces(target, 'chars');
		var strength = parseFloat(el.getAttribute('data-text-strength')) || 0.4;
		for (var i = 0; i < pieces.length; i++) { pieces[i].style.transition = 'transform .2s ease-out'; pieces[i].style.willChange = 'transform'; }
		el.addEventListener('pointermove', function (e) {
			for (var k = 0; k < pieces.length; k++) {
				var r = pieces[k].getBoundingClientRect(), dx = e.clientX - (r.left + r.width / 2), dy = e.clientY - (r.top + r.height / 2);
				var d = Math.sqrt(dx * dx + dy * dy), max = 90;
				if (d < max && d > 0) { var f = (1 - d / max) * strength * 34; pieces[k].style.transform = 'translate(' + (dx / d * f).toFixed(1) + 'px,' + (dy / d * f).toFixed(1) + 'px)'; }
				else { pieces[k].style.transform = 'none'; }
			}
		}, { passive: true });
		el.addEventListener('pointerleave', function () { for (var k = 0; k < pieces.length; k++) { pieces[k].style.transform = 'none'; } }, { passive: true });
	};
})();
