(function(){"use strict";
var API=window.upwTextApi||(window.upwTextApi={}),H=window.upwText||(window.upwText={});
var reduce=API.reduce,targetsOf=API.targetsOf,onView=API.onView,piece=API.piece,wrapPieces=API.wrapPieces,wrapLines=API.wrapLines,translateFrom=API.translateFrom,doReveal=API.doReveal,cssEffect=API.cssEffect,cssTriggered=API.cssTriggered,FLAP=API.FLAP,GLYPH=API.GLYPH;
H.letter_jump = function (el, target) {
		var pieces = wrapPieces(target, 'chars');
		for (var i = 0; i < pieces.length; i++) { pieces[i].classList.add('upw-text-jump-ch'); pieces[i].style.setProperty('--i', i); }
	};
})();
