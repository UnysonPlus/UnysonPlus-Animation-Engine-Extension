/**
 * Animation Engine — Text Effects shared "reveal" engine (on-demand chunk).
 * Loads only when a reveal/mask effect is on the page; registers onto window.upwTextApi.
 *
 * Trigger is MULTI-SELECT: data-text-trigger is a space-separated list of view / load / click /
 * hover. view/load reveal the text once (it starts hidden); click/hover REPLAY the reveal (the
 * text starts visible when only interaction triggers are set, so it never sits blank). Each setup
 * returns { play, reset, count } so an interaction can snap back to hidden and re-play cleanly.
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

	function triggersOf(el) {
		var t = (el.getAttribute('data-text-trigger') || 'view').split(/\s+/).filter(Boolean);
		return t.length ? t : ['view'];
	}
	// Hidden until play only when an entrance trigger (view/load) is set; interaction-only starts
	// visible so the text is never blank at rest.
	function startsHidden(el) {
		var t = triggersOf(el);
		return t.indexOf('view') >= 0 || t.indexOf('load') >= 0;
	}

	function revealSetup(el, target, kind, offset, hidden) {
		var mode = el.getAttribute('data-text-split') || 'chars';
		var dir = el.getAttribute('data-text-dir') || '';
		var stagger = parseFloat(el.getAttribute('data-text-stagger')) || 0.03;
		var dur = parseFloat(el.getAttribute('data-text-duration')) || 0.6;
		if (kind === 'mask') { return maskSetup(el, target, mode, stagger, dur, offset, hidden); }
		var pieces = mode === 'lines' ? wrapLines(target) : wrapPieces(target, mode);
		if (!pieces.length) { return { play: function () {}, reset: function () {}, count: 0 }; }
		if (mode === 'lines') { target.classList.add('upw-text-lines'); }
		var preset = REVEAL[kind](dir), i;
		var order = []; for (i = 0; i < pieces.length; i++) { order.push(i); }
		if (preset.shuffle) { for (i = order.length - 1; i > 0; i--) { var j = (Math.random() * (i + 1)) | 0, t = order[i]; order[i] = order[j]; order[j] = t; } }
		var trans = [];
		for (i = 0; i < pieces.length; i++) {
			var p = pieces[i];
			var tstr = 'opacity ' + dur + 's ease, transform ' + dur + 's ' + preset.ease + (preset.filter ? ', filter ' + dur + 's ease' : '');
			trans[i] = tstr;
			p.style.transition = tstr;
			p.style.transitionDelay = ((offset + order[i]) * stagger) + 's';
			p.style.willChange = 'opacity, transform';
			if (preset.origin) { p.style.transformOrigin = preset.origin; }
		}
		function hide() { for (var k = 0; k < pieces.length; k++) { var q = pieces[k]; q.style.opacity = '0'; q.style.transform = preset.tf; if (preset.filter) { q.style.filter = preset.filter; } } }
		function show() { for (var k = 0; k < pieces.length; k++) { pieces[k].style.opacity = '1'; pieces[k].style.transform = 'none'; if (preset.filter) { pieces[k].style.filter = 'none'; } } }
		if (hidden) { hide(); }
		return {
			count: pieces.length,
			play: show,
			// Snap back to hidden without animating, so the next play() re-runs the transition.
			reset: function () { for (var k = 0; k < pieces.length; k++) { pieces[k].style.transition = 'none'; } hide(); void el.offsetWidth; for (k = 0; k < pieces.length; k++) { pieces[k].style.transition = trans[k]; } }
		};
	}

	function maskSetup(el, target, mode, stagger, dur, offset, hidden) {
		var pieces = mode === 'lines' ? wrapLines(target) : wrapPieces(target, mode);
		if (!pieces.length) { return { play: function () {}, reset: function () {}, count: 0 }; }
		var inners = [], trans = [];
		for (var i = 0; i < pieces.length; i++) {
			var p = pieces[i], inner = document.createElement('span'); inner.className = 'upw-text-inner';
			while (p.firstChild) { inner.appendChild(p.firstChild); }
			p.appendChild(inner);
			p.style.overflow = 'hidden';
			p.style.display = (mode === 'lines') ? 'block' : 'inline-block';
			inner.style.display = 'inline-block';
			var tstr = 'transform ' + dur + 's cubic-bezier(.5,0,.1,1)';
			trans[i] = tstr;
			inner.style.transition = tstr;
			inner.style.transitionDelay = ((offset + i) * stagger) + 's';
			inner.style.willChange = 'transform';
			if (hidden) { inner.style.transform = 'translateY(110%)'; }
			inners.push(inner);
		}
		return {
			count: pieces.length,
			play: function () { for (var k = 0; k < inners.length; k++) { inners[k].style.transform = 'translateY(0)'; } },
			reset: function () { for (var k = 0; k < inners.length; k++) { inners[k].style.transition = 'none'; inners[k].style.transform = 'translateY(110%)'; } void el.offsetWidth; for (k = 0; k < inners.length; k++) { inners[k].style.transition = trans[k]; } }
		};
	}

	// Bind every selected trigger to a { play, reset } pair.
	function bindReveal(el, s) {
		var t = triggersOf(el);
		var has = function (x) { return t.indexOf(x) >= 0; };
		var replay = function () { s.reset(); requestAnimationFrame(s.play); };
		if (has('load')) { requestAnimationFrame(s.play); }
		if (has('view')) { onView(el, s.play); }
		if (has('click')) { el.addEventListener('click', replay); }
		if (has('hover')) { el.addEventListener('mouseenter', replay); }
	}

	function doReveal(el, target, kind) {
		var s = revealSetup(el, target, kind, 0, startsHidden(el));
		bindReveal(el, s);
	}

	function doRevealCascade(wrap, targets, kind) {
		var hidden = startsHidden(wrap), subs = [], offset = 0;
		targets.forEach(function (target) { var s = revealSetup(wrap, target, kind, offset, hidden); subs.push(s); offset += s.count; });
		bindReveal(wrap, {
			play:  function () { for (var i = 0; i < subs.length; i++) { subs[i].play(); } },
			reset: function () { for (var i = 0; i < subs.length; i++) { subs[i].reset(); } }
		});
	}

	API.doReveal = doReveal;
	API.doRevealCascade = doRevealCascade;
})();
