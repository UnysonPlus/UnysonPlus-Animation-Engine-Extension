(function(){"use strict";
var API=window.upwTextApi||(window.upwTextApi={}),H=window.upwText||(window.upwText={});
var reduce=API.reduce,targetsOf=API.targetsOf,onView=API.onView,piece=API.piece,wrapPieces=API.wrapPieces,wrapLines=API.wrapLines,translateFrom=API.translateFrom,doReveal=API.doReveal,cssEffect=API.cssEffect,cssTriggered=API.cssTriggered,FLAP=API.FLAP,GLYPH=API.GLYPH;
H.matrix = function (el, target) {
		var pieces = wrapPieces(target, 'chars');
		var dur = (parseFloat(el.getAttribute('data-text-duration')) || 1.4) * 1000;
		for (var i = 0; i < pieces.length; i++) { pieces[i].setAttribute('data-f', pieces[i].textContent); }
		function run() {
			var start = null, len = pieces.length;
			for (var k = 0; k < len; k++) { pieces[k].classList.add('upw-text-matrix'); }
			(function f(t) {
				if (!start) { start = t; }
				var prog = Math.min(1, (t - start) / dur), rev = Math.floor(prog * len), j;
				for (j = 0; j < len; j++) {
					if (j < rev) { pieces[j].textContent = pieces[j].getAttribute('data-f'); pieces[j].classList.remove('upw-text-matrix'); }
					else { pieces[j].textContent = GLYPH[(Math.random() * GLYPH.length) | 0]; }
				}
				if (prog < 1) { requestAnimationFrame(f); }
				else { for (j = 0; j < len; j++) { pieces[j].textContent = pieces[j].getAttribute('data-f'); pieces[j].classList.remove('upw-text-matrix'); } }
			})(0);
		}
		if ((el.getAttribute('data-text-trigger') || 'view') === 'load') { run(); } else { onView(el, run); }
	};
})();
