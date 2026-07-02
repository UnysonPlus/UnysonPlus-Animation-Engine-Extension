/**
 * Model Viewer — light harness around Google's <model-viewer>.
 *
 * <model-viewer> already owns the render loop, camera, IBL lighting, poster and
 * off-screen pausing. Here we only: (1) strip auto-rotate under prefers-reduced-motion;
 * (2) drive the slim load-progress bar; (3) fall back to the poster image if 3D /
 * custom elements aren't supported.
 */
(function () {
	'use strict';

	var REDUCE = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var SUPPORTS_CE = 'customElements' in window;

	function initOne(root) {
		if (root.__modelReady) { return; }
		root.__modelReady = true;

		var mv = root.querySelector('model-viewer');
		if (!mv || !SUPPORTS_CE) { root.classList.add('is-unsupported'); return; }

		// Reduced motion: don't spin.
		if (REDUCE) { mv.removeAttribute('auto-rotate'); }

		var bar = root.querySelector('.fw-model__bar > i');

		mv.addEventListener('progress', function (e) {
			var p = (e.detail && typeof e.detail.totalProgress === 'number') ? e.detail.totalProgress : 1;
			if (bar) { bar.style.width = Math.round(p * 100) + '%'; }
			if (p >= 1) { root.classList.add('is-loaded'); }
		});

		mv.addEventListener('load', function () { root.classList.add('is-loaded'); });

		// Model failed to load (bad URL, decode error) → show the poster fallback.
		mv.addEventListener('error', function () { root.classList.add('is-unsupported'); });
	}

	function init() {
		Array.prototype.forEach.call(document.querySelectorAll('.fw-model'), initOne);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
