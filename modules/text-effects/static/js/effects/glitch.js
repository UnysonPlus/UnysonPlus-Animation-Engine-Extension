(function(){"use strict";
var API=window.upwTextApi||(window.upwTextApi={}),H=window.upwText||(window.upwText={});
var reduce=API.reduce,targetsOf=API.targetsOf,onView=API.onView,piece=API.piece,wrapPieces=API.wrapPieces,wrapLines=API.wrapLines,translateFrom=API.translateFrom,doReveal=API.doReveal,cssEffect=API.cssEffect,cssTriggered=API.cssTriggered,FLAP=API.FLAP,GLYPH=API.GLYPH;
H.glitch = function (el, target) {
		target.setAttribute('data-text-content', target.textContent);
		target.classList.add('upw-text-glitch');
		if ((el.getAttribute('data-text-trigger') || 'hover') === 'always') { target.classList.add('is-always'); }
	};
})();
