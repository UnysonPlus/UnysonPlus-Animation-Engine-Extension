/**
 * Scrollytelling style: Pixelate Resolve. When a step activates, its media layer resolves from
 * chunky pixel blocks to sharp on a Canvas 2D overlay (the Codrops image-pixel-loading look).
 * onActivate hook — runs the resolve each time a new layer becomes active.
 */
(function () {
	'use strict';

	function ramp(coarse, steps) {
		var start = Math.min(0.5, Math.max(0.01, 1 / coarse)), s = [];
		for (var i = 0; i < steps; i++) { s.push(start * Math.pow(1 / start, i / (steps - 1))); }
		s[s.length - 1] = 1;
		return s;
	}

	function resolve(layer, i, ctx) {
		var img = layer.querySelector('img');
		if (!img || !img.naturalWidth) { return; }

		var canvas = layer.__pxCanvas;
		if (!canvas) {
			canvas = document.createElement('canvas');
			canvas.className = 'upw-story-px';
			canvas.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;display:block;';
			if (getComputedStyle(layer).position === 'static') { layer.style.position = 'relative'; }
			layer.appendChild(canvas);
			layer.__pxCanvas = canvas;
			layer.__pxCtx = canvas.getContext('2d');
		}
		var c2d = layer.__pxCtx;
		if (!c2d) { return; }

		var dpr = Math.min(window.devicePixelRatio || 1, 2);
		var r = layer.getBoundingClientRect();
		canvas.width = Math.max(1, Math.round(r.width * dpr));
		canvas.height = Math.max(1, Math.round(r.height * dpr));
		var ratio = img.naturalWidth / img.naturalHeight;
		var coarse = 40 + (ctx.intensity || 0.5) * 140; // chunkier with higher intensity
		var sizes = ramp(coarse, 5);
		img.style.opacity = '0'; // hide the raw <img>; the canvas renders it

		function draw(size) {
			var w = canvas.width, h = canvas.height, cw = w, ch = h;
			if (w / h > ratio) { ch = Math.round(w / ratio); } else { cw = Math.round(h * ratio); }
			var cx = Math.round((w - cw) / 2), cy = Math.round((h - ch) / 2), sm = size >= 1;
			c2d.imageSmoothingEnabled = sm; c2d.webkitImageSmoothingEnabled = sm; c2d.mozImageSmoothingEnabled = sm;
			c2d.clearRect(0, 0, w, h);
			var sw = Math.max(1, Math.round(cw * size)), sh = Math.max(1, Math.round(ch * size));
			c2d.drawImage(img, 0, 0, sw, sh);
			c2d.drawImage(canvas, 0, 0, sw, sh, cx, cy, cw, ch);
		}

		var k = 0;
		(function step() {
			if (k >= sizes.length) { return; }
			draw(sizes[k]);
			k++;
			setTimeout(function () { requestAnimationFrame(step); }, k === 1 ? 120 : 70);
		})();
	}

	(window.upwStoryFx = window.upwStoryFx || {}).pixelate = { onActivate: resolve };
})();
