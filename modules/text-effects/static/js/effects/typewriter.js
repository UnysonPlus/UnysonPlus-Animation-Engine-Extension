(function(){"use strict";
var API=window.upwTextApi||(window.upwTextApi={}),H=window.upwText||(window.upwText={});
var reduce=API.reduce,targetsOf=API.targetsOf,onView=API.onView,piece=API.piece,wrapPieces=API.wrapPieces,wrapLines=API.wrapLines,translateFrom=API.translateFrom,doReveal=API.doReveal,cssEffect=API.cssEffect,cssTriggered=API.cssTriggered,FLAP=API.FLAP,GLYPH=API.GLYPH;
H.typewriter = function (el, target) {
		var speed = parseInt(el.getAttribute('data-text-speed'), 10) || 55;
		var caret = el.getAttribute('data-text-caret') === '1';
		var loop = el.getAttribute('data-text-loop') === '1';
		var full = target.textContent;
		target.textContent = '';
		if (caret) { target.classList.add('upw-text-caret'); }
		var token = 0; // a newer cycle supersedes any in-flight typing/erasing (clean replay)
		function type(cb, id) { var i = 0; (function s() { if (id !== token) { return; } target.textContent = full.slice(0, i); if (i++ < full.length) { setTimeout(s, speed); } else if (cb) { cb(); } })(); }
		function erase(cb, id) { var i = full.length; (function s() { if (id !== token) { return; } target.textContent = full.slice(0, i); if (i-- > 0) { setTimeout(s, speed / 1.7); } else if (cb) { cb(); } })(); }
		function cycle() {
			var id = ++token;
			if (caret) { target.classList.remove('upw-text-caret-done'); }
			type(function () {
				if (id !== token) { return; }
				if (loop) { setTimeout(function () { if (id !== token) { return; } erase(function () { if (id !== token) { return; } setTimeout(function () { if (id === token) { cycle(); } }, 350); }, id); }, 1400); }
				else if (caret) { target.classList.add('upw-text-caret-done'); }
			}, id);
		}
		API.bindTriggers(el, cycle);
	};
})();
