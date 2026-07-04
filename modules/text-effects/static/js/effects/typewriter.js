(function(){"use strict";
var API=window.upwTextApi||(window.upwTextApi={}),H=window.upwText||(window.upwText={});
var reduce=API.reduce,targetsOf=API.targetsOf,onView=API.onView,piece=API.piece,wrapPieces=API.wrapPieces,wrapLines=API.wrapLines,translateFrom=API.translateFrom,doReveal=API.doReveal,cssEffect=API.cssEffect,cssTriggered=API.cssTriggered,FLAP=API.FLAP,GLYPH=API.GLYPH;
H.typewriter = function (el, target) {
		var speed = parseInt(el.getAttribute('data-text-speed'), 10) || 55;
		var caret = el.getAttribute('data-text-caret') === '1';
		var loop = el.getAttribute('data-text-loop') === '1';
		var trigger = el.getAttribute('data-text-trigger') || 'view';
		var full = target.textContent;
		target.textContent = '';
		if (caret) { target.classList.add('upw-text-caret'); }
		function type(cb) { var i = 0; (function s() { target.textContent = full.slice(0, i); if (i++ < full.length) { setTimeout(s, speed); } else if (cb) { cb(); } })(); }
		function erase(cb) { var i = full.length; (function s() { target.textContent = full.slice(0, i); if (i-- > 0) { setTimeout(s, speed / 1.7); } else if (cb) { cb(); } })(); }
		function cycle() { type(function () { if (loop) { setTimeout(function () { erase(function () { setTimeout(cycle, 350); }); }, 1400); } else if (caret) { target.classList.add('upw-text-caret-done'); } }); }
		if (trigger === 'load') { cycle(); } else { onView(el, cycle); }
	};
})();
