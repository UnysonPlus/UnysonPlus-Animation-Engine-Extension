(function(){"use strict";
var API=window.upwTextApi||(window.upwTextApi={}),H=window.upwText||(window.upwText={});
var reduce=API.reduce,targetsOf=API.targetsOf,onView=API.onView,piece=API.piece,wrapPieces=API.wrapPieces,wrapLines=API.wrapLines,translateFrom=API.translateFrom,doReveal=API.doReveal,cssEffect=API.cssEffect,cssTriggered=API.cssTriggered,FLAP=API.FLAP,GLYPH=API.GLYPH;
H.matrix = function (el, target) {
		var pieces = wrapPieces(target, 'chars');
		var dur = (parseFloat(el.getAttribute('data-text-duration')) || 1.4) * 1000;
		for (var i = 0; i < pieces.length; i++) { pieces[i].setAttribute('data-f', pieces[i].textContent); }
		var runId = 0; // a newer run supersedes any in-flight one (clean click/hover replay)
		function run() {
			var myId = ++runId, start = null, len = pieces.length;
			for (var k = 0; k < len; k++) { pieces[k].classList.add('upw-text-matrix'); }
			// One step per frame on the engine's shared frame loop (window.upwAnimRaf); returning false ends the run.
			var f = function (t) {
				if (myId !== runId) { return false; }
				if (!start) { start = t; }
				var prog = Math.min(1, (t - start) / dur), rev = Math.floor(prog * len), j;
				for (j = 0; j < len; j++) {
					if (j < rev) { pieces[j].textContent = pieces[j].getAttribute('data-f'); pieces[j].classList.remove('upw-text-matrix'); }
					else { pieces[j].textContent = GLYPH[(Math.random() * GLYPH.length) | 0]; }
				}
				if (prog >= 1) { for (j = 0; j < len; j++) { pieces[j].textContent = pieces[j].getAttribute('data-f'); pieces[j].classList.remove('upw-text-matrix'); } return false; }
			};
			if (f(0) !== false) { window.upwAnimRaf.add(f); }
		}
		API.bindTriggers(el, run);
	};
})();
