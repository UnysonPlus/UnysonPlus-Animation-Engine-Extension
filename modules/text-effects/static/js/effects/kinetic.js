(function(){"use strict";
var API=window.upwTextApi||(window.upwTextApi={}),H=window.upwText||(window.upwText={});
var reduce=API.reduce,targetsOf=API.targetsOf,onView=API.onView,piece=API.piece,wrapPieces=API.wrapPieces,wrapLines=API.wrapLines,translateFrom=API.translateFrom,doReveal=API.doReveal,cssEffect=API.cssEffect,cssTriggered=API.cssTriggered,FLAP=API.FLAP,GLYPH=API.GLYPH;
H.kinetic = function (el, target) {
		var pieces = wrapPieces(target, 'chars');
		var intensity = parseFloat(getComputedStyle(el).getPropertyValue('--text-kinetic')) || 4;
		for (var i = 0; i < pieces.length; i++) { pieces[i].style.transition = 'transform .35s cubic-bezier(.2,.7,.2,1)'; pieces[i].style.willChange = 'transform'; }
		var lastY = window.pageYOffset || 0, settle = null;
		window.addEventListener('scroll', function () {
			var y = window.pageYOffset || 0, vel = y - lastY; lastY = y;
			var sk = Math.max(-25, Math.min(25, vel * intensity * 0.35));
			for (var k = 0; k < pieces.length; k++) { pieces[k].style.transform = 'skewX(' + sk + 'deg) translateY(' + (sk * 0.25) + 'px)'; }
			if (settle) { clearTimeout(settle); }
			settle = setTimeout(function () { for (var k = 0; k < pieces.length; k++) { pieces[k].style.transform = 'none'; } }, 140);
		}, { passive: true });
	};
})();
