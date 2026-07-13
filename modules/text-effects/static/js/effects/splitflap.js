(function(){"use strict";
var API=window.upwTextApi||(window.upwTextApi={}),H=window.upwText||(window.upwText={});
var reduce=API.reduce,targetsOf=API.targetsOf,onView=API.onView,piece=API.piece,wrapPieces=API.wrapPieces,wrapLines=API.wrapLines,translateFrom=API.translateFrom,doReveal=API.doReveal,cssEffect=API.cssEffect,cssTriggered=API.cssTriggered,FLAP=API.FLAP,GLYPH=API.GLYPH;
H.splitflap = function (el, target) {
		var pieces = wrapPieces(target, 'chars');
		var runId = 0; // a newer run supersedes any in-flight intervals (clean click/hover replay)
		function run() {
			var myId = ++runId;
			pieces.forEach(function (p, idx) {
				var fin = p.textContent, total = 8 + idx * 2, k = 0;
				var iv = setInterval(function () {
					if (myId !== runId) { clearInterval(iv); return; }
					k++;
					if (k >= total) { clearInterval(iv); p.textContent = fin; }
					else { p.textContent = FLAP[(Math.random() * FLAP.length) | 0]; }
				}, 45);
			});
		}
		API.bindTriggers(el, run);
	};
})();
