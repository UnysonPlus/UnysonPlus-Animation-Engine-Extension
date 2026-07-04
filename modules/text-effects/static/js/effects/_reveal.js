/**
 * Animation Engine — Text Effects shared "reveal" engine (on-demand chunk).
 * Loads only when a reveal/mask effect is on the page; registers onto window.upwTextApi.
 */
(function () {
	'use strict';
	var API = window.upwTextApi;
	if (!API) { return; }
	var onView = API.onView, wrapPieces = API.wrapPieces, wrapLines = API.wrapLines;

	function translateFrom(dir, amt) {
		amt = amt || '0.85em';
		if (dir === 'left') { return 'translateX(-' + amt + ')'; }
		if (dir === 'right') { return 'translateX(' + amt + ')'; }
		if (dir === 'down') { return 'translateY(-' + amt + ')'; }
		return 'translateY(' + amt + ')'; // up (default)
	}

	var REVEAL = {
		split_reveal: function (dir) { return { tf: translateFrom(dir || 'up'), ease: 'cubic-bezier(.2,.7,.2,1)' }; },
		blur:   function () { return { tf: 'translateY(.3em)', filter: 'blur(8px)', ease: 'ease-out' }; },
		flip3d: function () { return { tf: 'perspective(500px) rotateX(-90deg)', origin: 'center bottom', ease: 'cubic-bezier(.2,.7,.2,1)' }; },
		scale:  function () { return { tf: 'scale(.3)', ease: 'cubic-bezier(.34,1.56,.64,1)' }; },
		slide:  function (dir) { return { tf: translateFrom(dir || 'left', '1.2em'), ease: 'cubic-bezier(.2,.7,.2,1)' }; },
		bounce: function () { return { tf: 'translateY(.9em)', ease: 'cubic-bezier(.28,1.6,.5,1)' }; },
		random: function () { return { tf: 'scale(.5) translateY(.3em)', ease: 'ease-out', shuffle: true }; },
		skew:   function () { return { tf: 'skewY(7deg) translateY(.5em)', ease: 'cubic-bezier(.2,.7,.2,1)' }; }
	};

	function revealSetup(el, target, kind, offset) {
		var mode = el.getAttribute('data-text-split') || 'chars';
		var dir = el.getAttribute('data-text-dir') || '';
		var stagger = parseFloat(el.getAttribute('data-text-stagger')) || 0.03;
		var dur = parseFloat(el.getAttribute('data-text-duration')) || 0.6;
		if (kind === 'mask') { return maskSetup(el, target, mode, stagger, dur, offset); }
		var pieces = mode === 'lines' ? wrapLines(target) : wrapPieces(target, mode);
		if (!pieces.length) { return { play: function () {}, count: 0 }; }
		if (mode === 'lines') { target.classList.add('upw-text-lines'); }
		var preset = REVEAL[kind](dir), i;
		var order = []; for (i = 0; i < pieces.length; i++) { order.push(i); }
		if (preset.shuffle) { for (i = order.length - 1; i > 0; i--) { var j = (Math.random() * (i + 1)) | 0, t = order[i]; order[i] = order[j]; order[j] = t; } }
		for (i = 0; i < pieces.length; i++) {
			var p = pieces[i];
			p.style.opacity = '0';
			p.style.transform = preset.tf;
			if (preset.filter) { p.style.filter = preset.filter; }
			if (preset.origin) { p.style.transformOrigin = preset.origin; }
			p.style.transition = 'opacity ' + dur + 's ease, transform ' + dur + 's ' + preset.ease + (preset.filter ? ', filter ' + dur + 's ease' : '');
			p.style.transitionDelay = ((offset + order[i]) * stagger) + 's';
			p.style.willChange = 'opacity, transform';
		}
		return {
			count: pieces.length,
			play: function () { for (var k = 0; k < pieces.length; k++) { pieces[k].style.opacity = '1'; pieces[k].style.transform = 'none'; if (preset.filter) { pieces[k].style.filter = 'none'; } } }
		};
	}

	function maskSetup(el, target, mode, stagger, dur, offset) {
		var pieces = mode === 'lines' ? wrapLines(target) : wrapPieces(target, mode);
		if (!pieces.length) { return { play: function () {}, count: 0 }; }
		var inners = [];
		for (var i = 0; i < pieces.length; i++) {
			var p = pieces[i], inner = document.createElement('span'); inner.className = 'upw-text-inner';
			while (p.firstChild) { inner.appendChild(p.firstChild); }
			p.appendChild(inner);
			p.style.overflow = 'hidden';
			p.style.display = (mode === 'lines') ? 'block' : 'inline-block';
			inner.style.display = 'inline-block';
			inner.style.transform = 'translateY(110%)';
			inner.style.transition = 'transform ' + dur + 's cubic-bezier(.5,0,.1,1)';
			inner.style.transitionDelay = ((offset + i) * stagger) + 's';
			inner.style.willChange = 'transform';
			inners.push(inner);
		}
		return { count: pieces.length, play: function () { for (var k = 0; k < inners.length; k++) { inners[k].style.transform = 'translateY(0)'; } } };
	}

	function doReveal(el, target, kind) {
		var s = revealSetup(el, target, kind, 0);
		if ((el.getAttribute('data-text-trigger') || 'view') === 'load') { requestAnimationFrame(s.play); } else { onView(el, s.play); }
	}

	function doRevealCascade(wrap, targets, kind) {
		var plays = [], offset = 0;
		targets.forEach(function (target) { var s = revealSetup(wrap, target, kind, offset); plays.push(s.play); offset += s.count; });
		function playAll() { for (var i = 0; i < plays.length; i++) { plays[i](); } }
		if ((wrap.getAttribute('data-text-trigger') || 'view') === 'load') { requestAnimationFrame(playAll); } else { onView(wrap, playAll); }
	}

	API.doReveal = doReveal;
	API.doRevealCascade = doRevealCascade;
})();
