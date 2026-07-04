(function(){"use strict";
var API=window.upwTextApi||(window.upwTextApi={}),H=window.upwText||(window.upwText={});
var reduce=API.reduce,targetsOf=API.targetsOf,onView=API.onView,piece=API.piece,wrapPieces=API.wrapPieces,wrapLines=API.wrapLines,translateFrom=API.translateFrom,doReveal=API.doReveal,cssEffect=API.cssEffect,cssTriggered=API.cssTriggered,FLAP=API.FLAP,GLYPH=API.GLYPH;
H.strikebox = function (el, target) {
		var t = target;
		t.setAttribute('data-text-shape', el.getAttribute('data-text-shape') || 'strike');
		t.classList.add('upw-text-strikebox');
		if ((el.getAttribute('data-text-trigger') || 'view') === 'view') { onView(el, function () { t.classList.add('is-on'); }); }
	};
})();
