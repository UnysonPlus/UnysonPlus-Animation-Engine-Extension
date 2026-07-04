(function(){"use strict";
var API=window.upwTextApi||(window.upwTextApi={}),H=window.upwText||(window.upwText={});
var reduce=API.reduce,targetsOf=API.targetsOf,onView=API.onView,piece=API.piece,wrapPieces=API.wrapPieces,wrapLines=API.wrapLines,translateFrom=API.translateFrom,doReveal=API.doReveal,cssEffect=API.cssEffect,cssTriggered=API.cssTriggered,FLAP=API.FLAP,GLYPH=API.GLYPH;
H.image_mask = function (el, target) {
		var t = target, img = el.getAttribute('data-text-img');
		if (img) { t.style.backgroundImage = 'url("' + img + '")'; }
		t.classList.add('upw-text-imgmask');
	};
})();
