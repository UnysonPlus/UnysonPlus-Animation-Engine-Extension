(function(){"use strict";
var API=window.upwTextApi||(window.upwTextApi={}),H=window.upwText||(window.upwText={});
var reduce=API.reduce,targetsOf=API.targetsOf,onView=API.onView,piece=API.piece,wrapPieces=API.wrapPieces,wrapLines=API.wrapLines,translateFrom=API.translateFrom,doReveal=API.doReveal,cssEffect=API.cssEffect,cssTriggered=API.cssTriggered,FLAP=API.FLAP,GLYPH=API.GLYPH;
H.rotating_words = function (el, target) {
		var t = target;
		var extra = (el.getAttribute('data-text-words') || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean);
		var list = [t.textContent.trim()].concat(extra);
		if (list.length < 2) { return; }
		var interval = (parseFloat(el.getAttribute('data-text-interval')) || 1.8) * 1000, i = 0;
		t.style.display = 'inline-block';
		t.style.transition = 'opacity .3s ease, transform .3s ease';
		setInterval(function () {
			t.style.opacity = '0'; t.style.transform = 'translateY(-.25em)';
			setTimeout(function () {
				i = (i + 1) % list.length; t.textContent = list[i]; t.style.transform = 'translateY(.25em)';
				requestAnimationFrame(function () { t.style.opacity = '1'; t.style.transform = 'none'; });
			}, 300);
		}, interval);
	};
})();
