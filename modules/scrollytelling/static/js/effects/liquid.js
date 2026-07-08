/**
 * Scrollytelling style: Liquid (WebGL). Renders the media on a canvas overlay and warps between
 * the outgoing and incoming image through a displacement (noise) shader when the step changes.
 * Self-contained raw WebGL (no Three.js). onActivate style — the core still toggles the layers'
 * .is-active underneath, so at any failure (no WebGL, cross-origin image, context loss) the
 * instance tears itself down (removes the canvas + .sc-liquid-on) and the plain CSS crossfade
 * fallback takes over. State is PER SECTION (stored on the section element), so multiple Liquid
 * stories on one page each drive their own canvas.
 */
(function () {
	'use strict';

	var VERT = 'attribute vec2 aPos; varying vec2 vUv; void main(){ vUv = aPos*0.5+0.5; gl_Position = vec4(aPos,0.0,1.0); }';
	var FRAG = [
		'precision highp float;',
		'varying vec2 vUv;',
		'uniform sampler2D uTexA, uTexB;',
		'uniform vec2 uScaleA, uScaleB;',
		'uniform float uMix, uStrength, uTime;',
		'float hash(vec2 p){ return fract(sin(dot(p, vec2(127.1,311.7)))*43758.5453); }',
		'float noise(vec2 p){ vec2 i=floor(p), f=fract(p);',
		'  float a=hash(i), b=hash(i+vec2(1.,0.)), c=hash(i+vec2(0.,1.)), d=hash(i+vec2(1.,1.));',
		'  vec2 u=f*f*(3.-2.*f); return mix(a,b,u.x)+(c-a)*u.y*(1.-u.x)+(d-b)*u.x*u.y; }',
		'void main(){',
		'  float m = smoothstep(0.0, 1.0, uMix);',
		'  float peak = sin(uMix*3.14159265);',
		'  vec2 fl = vec2(noise(vUv*4.0+uTime), noise(vUv*4.0+10.0-uTime)) - 0.5;',
		'  vec2 d = fl * uStrength * peak;',
		'  vec2 uvA = (vUv-0.5)*uScaleA + 0.5 + d*(1.0-m);',
		'  vec2 uvB = (vUv-0.5)*uScaleB + 0.5 - d*m;',
		'  gl_FragColor = mix(texture2D(uTexA, uvA), texture2D(uTexB, uvB), m);',
		'}'
	].join('\n');

	function compile(gl, type, src) { var s = gl.createShader(type); gl.shaderSource(s, src); gl.compileShader(s); return gl.getShaderParameter(s, gl.COMPILE_STATUS) ? s : null; }
	function coverScale(iw, ih, cw, ch) { var ir = iw / ih, cr = cw / ch; return (ir > cr) ? [cr / ir, 1] : [1, ir / cr]; }

	function makeInstance(ctx) {
		var media = ctx.media, section = ctx.section;
		var imgs = [];
		for (var k = 0; k < ctx.layers.length; k++) {
			var im = ctx.layers[k].tagName === 'IMG' ? ctx.layers[k] : ctx.layers[k].querySelector('img');
			if (!im) { return null; }
			imgs.push(im);
		}

		var canvas = document.createElement('canvas');
		canvas.className = 'sc-liquid-canvas';
		var gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
		if (!gl) { return null; }
		var vs = compile(gl, gl.VERTEX_SHADER, VERT), fs = compile(gl, gl.FRAGMENT_SHADER, FRAG);
		if (!vs || !fs) { return null; }
		var prog = gl.createProgram(); gl.attachShader(prog, vs); gl.attachShader(prog, fs); gl.linkProgram(prog);
		if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) { return null; }
		gl.useProgram(prog);

		var buf = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, buf);
		gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW);
		var aPos = gl.getAttribLocation(prog, 'aPos'); gl.enableVertexAttribArray(aPos); gl.vertexAttribPointer(aPos, 2, gl.FLOAT, false, 0, 0);

		var U = {}; ['uTexA', 'uTexB', 'uScaleA', 'uScaleB', 'uMix', 'uStrength', 'uTime'].forEach(function (n) { U[n] = gl.getUniformLocation(prog, n); });
		gl.uniform1i(U.uTexA, 0); gl.uniform1i(U.uTexB, 1);
		gl.uniform1f(U.uStrength, 0.12 + (ctx.intensity || 0.5) * 0.22);

		var texes = [], ready = [], dead = false;
		var from = 0, to = 0, mix = 1, raf = 0, t0 = null, t = 0;

		// Tear the instance down and hand the panel back to the CSS crossfade fallback.
		function bail() {
			if (dead) { return; }
			dead = true;
			section.__upwLiquidFailed = true;
			if (raf) { cancelAnimationFrame(raf); raf = 0; }
			window.removeEventListener('resize', size);
			try {
				for (var i = 0; i < texes.length; i++) { if (texes[i]) { gl.deleteTexture(texes[i]); } }
				if (buf) { gl.deleteBuffer(buf); }
				if (prog) { gl.deleteProgram(prog); }
			} catch (e) {}
			if (canvas.parentNode) { canvas.parentNode.removeChild(canvas); }
			section.classList.remove('sc-liquid-on');
		}

		imgs.forEach(function (img, i) {
			var tex = gl.createTexture(); texes[i] = tex; ready[i] = false;
			function up() {
				if (dead || !img.naturalWidth) { return; }
				gl.bindTexture(gl.TEXTURE_2D, tex);
				gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, true);
				gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
				gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
				gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
				gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
				try { gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, img); ready[i] = true; render(); }
				catch (e) { bail(); } // cross-origin taint (sync OR on late load) → restore the CSS fallback
			}
			if (img.complete && img.naturalWidth) { up(); } else { img.addEventListener('load', up, { once: true }); }
		});
		if (dead) { return null; } // a same-origin-but-already-loaded image tainted synchronously

		if (getComputedStyle(media).position === 'static') { media.style.position = 'relative'; }
		media.appendChild(canvas);
		section.classList.add('sc-liquid-on');
		canvas.addEventListener('webglcontextlost', function (e) { e.preventDefault(); bail(); }, false);

		function size() {
			if (dead) { return; }
			var r = media.getBoundingClientRect(), dpr = Math.min(window.devicePixelRatio || 1, 2);
			canvas.width = Math.max(1, Math.round(r.width * dpr));
			canvas.height = Math.max(1, Math.round(r.height * dpr));
			gl.viewport(0, 0, canvas.width, canvas.height);
			render();
		}
		function render() {
			if (dead || !ready[from] || !ready[to]) { return; }
			var cw = canvas.width, ch = canvas.height;
			var sa = coverScale(imgs[from].naturalWidth, imgs[from].naturalHeight, cw, ch);
			var sb = coverScale(imgs[to].naturalWidth, imgs[to].naturalHeight, cw, ch);
			gl.activeTexture(gl.TEXTURE0); gl.bindTexture(gl.TEXTURE_2D, texes[from]);
			gl.activeTexture(gl.TEXTURE1); gl.bindTexture(gl.TEXTURE_2D, texes[to]);
			gl.uniform2f(U.uScaleA, sa[0], sa[1]); gl.uniform2f(U.uScaleB, sb[0], sb[1]);
			gl.uniform1f(U.uMix, mix); gl.uniform1f(U.uTime, t);
			gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
		}
		function frame(ts) {
			if (dead) { return; }
			if (t0 === null) { t0 = ts; }
			var dur = parseFloat(getComputedStyle(section).getPropertyValue('--story-trans')) || 0.6;
			var d = (ts - t0) / 1000; t = d;
			mix = Math.min(1, d / dur);
			render();
			if (mix < 1) { raf = requestAnimationFrame(frame); } else { from = to; }
		}
		window.addEventListener('resize', size);
		size();

		return {
			go: function (i) {
				if (dead || (i === to && mix >= 1)) { return; }
				from = (mix < 1) ? from : to; to = i; mix = 0; t0 = null;
				if (raf) { cancelAnimationFrame(raf); } raf = requestAnimationFrame(frame);
			},
			dispose: bail
		};
	}

	(window.upwStoryFx = window.upwStoryFx || {}).liquid = {
		onActivate: function (layer, i, ctx) {
			var section = ctx.section;
			if (section.__upwLiquidFailed) { return; }
			var inst = section.__upwLiquid;
			if (!inst) {
				inst = makeInstance(ctx);
				if (!inst) { section.__upwLiquidFailed = true; return; }
				section.__upwLiquid = inst;
				// Register for teardown on a builder rescan (see scrollytelling-core.js cleanup).
				(section.__upwStoryCleanup = section.__upwStoryCleanup || []).push(inst.dispose);
			}
			inst.go(i);
		}
	};
})();
