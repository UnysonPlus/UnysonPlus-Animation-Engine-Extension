(function(){"use strict";
var API=window.upwTextApi||(window.upwTextApi={}),H=window.upwText||(window.upwText={});
var reduce=API.reduce,targetsOf=API.targetsOf,onView=API.onView,piece=API.piece,wrapPieces=API.wrapPieces,wrapLines=API.wrapLines,translateFrom=API.translateFrom,doReveal=API.doReveal,cssEffect=API.cssEffect,cssTriggered=API.cssTriggered,FLAP=API.FLAP,GLYPH=API.GLYPH;
H.scramble = function (el, target) {
		var dur = (parseFloat(el.getAttribute('data-text-duration')) || 1.2) * 1000;
		var trigger = el.getAttribute('data-text-trigger') || 'view';
		var final = target.textContent, len = final.length;
		var CH = '!<>-_\\/[]{}—=+*^?#abcdef0123456789';
		function run() {
			var start = null;
			(function frame(t) {
				if (!start) { start = t; }
				var prog = Math.min(1, (t - start) / dur), rev = Math.floor(prog * len), out = '', i;
				for (i = 0; i < len; i++) {
					if (final[i] === ' ') { out += ' '; }
					else if (i < rev) { out += final[i]; }
					else { out += CH[(Math.random() * CH.length) | 0]; }
				}
				target.textContent = out;
				if (prog < 1) { requestAnimationFrame(frame); } else { target.textContent = final; }
			})(0);
		}
		if (trigger === 'load') { run(); } else { onView(el, run); }
	};
})();
