(function(){"use strict";
var API=window.upwTextApi||(window.upwTextApi={}),H=window.upwText||(window.upwText={});
var reduce=API.reduce,targetsOf=API.targetsOf,onView=API.onView,piece=API.piece,wrapPieces=API.wrapPieces,wrapLines=API.wrapLines,translateFrom=API.translateFrom,doReveal=API.doReveal,cssEffect=API.cssEffect,cssTriggered=API.cssTriggered,FLAP=API.FLAP,GLYPH=API.GLYPH;
H.countup = function (el, target) {
		var t = target, raw = t.textContent.trim();
		var m = raw.match(/^([^\d-]*)(-?[\d.,]+)(.*)$/);
		if (!m) { return; }
		var pre = m[1], numStr = m[2], suf = m[3];
		var decimals = (numStr.split('.')[1] || '').length, hasComma = numStr.indexOf(',') >= 0;
		var goal = parseFloat(numStr.replace(/,/g, ''));
		if (isNaN(goal)) { return; }
		var dur = (parseFloat(el.getAttribute('data-text-duration')) || 1.6) * 1000;
		function fmt(n) {
			var s = decimals ? n.toFixed(decimals) : Math.round(n).toString();
			if (hasComma) { var pp = s.split('.'); pp[0] = pp[0].replace(/\B(?=(\d{3})+(?!\d))/g, ','); s = pp.join('.'); }
			return pre + s + suf;
		}
		t.textContent = fmt(0);
		function run() { var start = null; (function f(tm) { if (!start) { start = tm; } var p = Math.min(1, (tm - start) / dur); t.textContent = fmt(goal * (1 - Math.pow(1 - p, 3))); if (p < 1) { requestAnimationFrame(f); } else { t.textContent = fmt(goal); } })(0); }
		if ((el.getAttribute('data-text-trigger') || 'view') === 'load') { run(); } else { onView(el, run); }
	};
})();
