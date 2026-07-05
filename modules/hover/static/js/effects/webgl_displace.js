/**
 * Animation Engine — Hover "webgl_displace": GPU displacement + chromatic refraction on an image.
 *
 * Self-contained RAW WebGL (no Three.js) so it stays light and loads on-demand. Wraps the
 * element's <img> in a <canvas> that renders the same texture through a fragment shader:
 * noise-flow UV displacement ("liquid") plus an RGB channel split along the pointer direction
 * ("refract"). The distortion eases up while the pointer is over (or runs continuously when the
 * trigger is "always"). Falls back to the untouched <img> if there's no image, no WebGL, or the
 * image is cross-origin (tainted texture) — nothing breaks.
 *
 * Reads (stamped by hover-render.php):
 *   data-wd-style     refract | liquid | both   (default both)
 *   data-wd-strength  0.1..1   displacement amount
 *   data-wd-chroma    0..1     RGB-split amount
 *   data-wd-speed     0.2..2   flow speed
 *   data-wd-trigger   hover | always
 */
(function () {
	'use strict';

	var VERT = 'attribute vec2 aPos; varying vec2 vUv;' +
		'void main(){ vUv = aPos*0.5+0.5; gl_Position = vec4(aPos,0.0,1.0); }';

	var FRAG = [
		'precision highp float;',
		'varying vec2 vUv;',
		'uniform sampler2D uTex;',
		'uniform float uTime, uStrength, uChroma, uSpeed, uAmount, uMode;',
		'uniform vec2 uMouse;',
		'float hash(vec2 p){ return fract(sin(dot(p, vec2(127.1,311.7)))*43758.5453); }',
		'float noise(vec2 p){',
		'  vec2 i=floor(p), f=fract(p);',
		'  float a=hash(i), b=hash(i+vec2(1.,0.)), c=hash(i+vec2(0.,1.)), d=hash(i+vec2(1.,1.));',
		'  vec2 u=f*f*(3.-2.*f);',
		'  return mix(a,b,u.x)+(c-a)*u.y*(1.-u.x)+(d-b)*u.x*u.y;',
		'}',
		'void main(){',
		'  vec2 uv=vUv;',
		'  float t=uTime*uSpeed;',
		'  vec2 flow = vec2(noise(uv*3.0 + t + uMouse*2.0), noise(uv*3.0 - t + 10.0 - uMouse*2.0)) - 0.5;',
		'  float amt = uAmount * uStrength * 0.15;',
		'  vec2 disp = (uMode < 0.5) ? vec2(0.0) : flow*amt;',           // refract-only skips displacement
		'  vec2 base = uv + disp;',
		'  vec2 dir = normalize(uv - (uMouse+0.5) + 1e-5);',
		'  float ca = (uMode > 1.5 || uMode < 0.5) ? uChroma*uAmount*0.02 : 0.0;', // liquid-only skips split
		'  float r = texture2D(uTex, base + dir*ca).r;',
		'  float g = texture2D(uTex, base).g;',
		'  float b = texture2D(uTex, base - dir*ca).b;',
		'  float a = texture2D(uTex, base).a;',
		'  gl_FragColor = vec4(r,g,b,a);',
		'}'
	].join('\n');

	function compile(gl, type, src) {
		var s = gl.createShader(type);
		gl.shaderSource(s, src); gl.compileShader(s);
		return gl.getShaderParameter(s, gl.COMPILE_STATUS) ? s : null;
	}

	function modeVal(style) { return style === 'refract' ? 0.0 : (style === 'liquid' ? 1.0 : 2.0); }

	function run(el) {
		var img = el.tagName === 'IMG' ? el : el.querySelector('img');
		if (!img) { return; }

		var style   = el.getAttribute('data-wd-style') || 'both';
		var always  = el.getAttribute('data-wd-trigger') === 'always';
		var uStr    = parseFloat(el.getAttribute('data-wd-strength')) || 0.35;
		var uChr    = parseFloat(el.getAttribute('data-wd-chroma'));
		if (isNaN(uChr)) { uChr = 0.4; }
		var uSpd    = parseFloat(el.getAttribute('data-wd-speed')) || 0.6;

		function begin() {
			if (!img.naturalWidth) { return; }

			var canvas = document.createElement('canvas');
			canvas.className = 'sc-wd-canvas';
			var gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
			if (!gl) { return; }

			var vs = compile(gl, gl.VERTEX_SHADER, VERT);
			var fs = compile(gl, gl.FRAGMENT_SHADER, FRAG);
			if (!vs || !fs) { return; }
			var prog = gl.createProgram();
			gl.attachShader(prog, vs); gl.attachShader(prog, fs); gl.linkProgram(prog);
			if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) { return; }
			gl.useProgram(prog);

			// Full-screen quad (two triangles).
			var buf = gl.createBuffer();
			gl.bindBuffer(gl.ARRAY_BUFFER, buf);
			gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1,-1, 1,-1, -1,1, 1,1]), gl.STATIC_DRAW);
			var aPos = gl.getAttribLocation(prog, 'aPos');
			gl.enableVertexAttribArray(aPos);
			gl.vertexAttribPointer(aPos, 2, gl.FLOAT, false, 0, 0);

			// Upload the image as a texture (fails silently on cross-origin → we bail to the <img>).
			var tex = gl.createTexture();
			gl.bindTexture(gl.TEXTURE_2D, tex);
			gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, true);
			gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
			gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
			gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
			gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
			try {
				gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, img);
			} catch (e) { return; } // tainted (CORS) — leave the original image untouched

			var U = {
				time: gl.getUniformLocation(prog, 'uTime'),
				strength: gl.getUniformLocation(prog, 'uStrength'),
				chroma: gl.getUniformLocation(prog, 'uChroma'),
				speed: gl.getUniformLocation(prog, 'uSpeed'),
				amount: gl.getUniformLocation(prog, 'uAmount'),
				mode: gl.getUniformLocation(prog, 'uMode'),
				mouse: gl.getUniformLocation(prog, 'uMouse')
			};
			gl.uniform1f(U.strength, uStr);
			gl.uniform1f(U.chroma, uChr);
			gl.uniform1f(U.speed, uSpd);
			gl.uniform1f(U.mode, modeVal(style));

			// Insert the canvas over the image; hide the <img> only now that init succeeded.
			if (getComputedStyle(el).position === 'static') { el.style.position = 'relative'; }
			el.appendChild(canvas);
			el.classList.add('sc-wd-active');

			function size() {
				var r = img.getBoundingClientRect();
				var dpr = Math.min(window.devicePixelRatio || 1, 2);
				canvas.width = Math.max(1, Math.round(r.width * dpr));
				canvas.height = Math.max(1, Math.round(r.height * dpr));
				canvas.style.width = r.width + 'px';
				canvas.style.height = r.height + 'px';
				// Align precisely over the <img> within the (position:relative) element.
				canvas.style.left = (img.offsetLeft || 0) + 'px';
				canvas.style.top = (img.offsetTop || 0) + 'px';
				gl.viewport(0, 0, canvas.width, canvas.height);
			}
			size();
			if (window.ResizeObserver) { new ResizeObserver(size).observe(img); }
			else { window.addEventListener('resize', size); }

			var amount = always ? 1 : 0, target = always ? 1 : 0;
			var mx = 0, my = 0, raf = 0, t0 = null, running = false, inView = true;

			el.addEventListener('pointerenter', function () { if (!always) { target = 1; ensure(); } });
			el.addEventListener('pointerleave', function () { if (!always) { target = 0; } });
			el.addEventListener('pointermove', function (e) {
				var r = img.getBoundingClientRect();
				mx = (e.clientX - r.left) / r.width - 0.5;
				my = 0.5 - (e.clientY - r.top) / r.height;
			});

			function frame(ts) {
				if (t0 === null) { t0 = ts; }
				var t = (ts - t0) / 1000;
				amount += (target - amount) * 0.08;
				gl.uniform1f(U.time, t);
				gl.uniform1f(U.amount, amount);
				gl.uniform2f(U.mouse, mx, my);
				gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
				// Keep looping while active, animating in view, or still easing out.
				if (inView && (target > 0 || amount > 0.002 || always)) {
					raf = requestAnimationFrame(frame);
				} else { running = false; }
			}
			function ensure() { if (!running) { running = true; raf = requestAnimationFrame(frame); } }

			// Pause off-screen; resume (and redraw at least once) when back in view.
			if ('IntersectionObserver' in window) {
				new IntersectionObserver(function (ents) {
					inView = ents[0].isIntersecting;
					if (inView && (always || target > 0)) { ensure(); }
				}, { threshold: 0.01 }).observe(el);
			}

			ensure(); // draw the first (static) frame so the canvas isn't blank before hover
		}

		if (img.complete && img.naturalWidth) { begin(); }
		else { img.addEventListener('load', begin, { once: true }); }
	}

	(window.upwHoverFx = window.upwHoverFx || {}).webgl_displace = {
		pointer: true, reduceSkip: true, run: run
	};
})();
