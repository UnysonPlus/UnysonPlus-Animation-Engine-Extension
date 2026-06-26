/**
 * WebGL Object — renders each [data-webgl] element with Three.js.
 *
 * Presets: glass (refractive MeshPhysicalMaterial), metal, sphere, particles.
 * Guards: viewport-only render loop, pause when tab hidden, prefers-reduced-motion
 * (single static frame), no-WebGL / no-THREE fallback (poster or CSS gradient),
 * DPR + FPS caps, and dispose when the node leaves the DOM.
 */
(function () {
	'use strict';

	var THREE = window.THREE;
	var REDUCE = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	// Classic Ashima/McEwen simplex noise (3D), injected into the vertex shader.
	var SIMPLEX = [
		'vec4 permute(vec4 x){return mod(((x*34.0)+1.0)*x,289.0);}',
		'vec4 taylorInvSqrt(vec4 r){return 1.79284291400159-0.85373472095314*r;}',
		'float snoise(vec3 v){',
		'  const vec2 C=vec2(1.0/6.0,1.0/3.0); const vec4 D=vec4(0.0,0.5,1.0,2.0);',
		'  vec3 i=floor(v+dot(v,C.yyy)); vec3 x0=v-i+dot(i,C.xxx);',
		'  vec3 g=step(x0.yzx,x0.xyz); vec3 l=1.0-g; vec3 i1=min(g.xyz,l.zxy); vec3 i2=max(g.xyz,l.zxy);',
		'  vec3 x1=x0-i1+1.0*C.xxx; vec3 x2=x0-i2+2.0*C.xxx; vec3 x3=x0-1.0+3.0*C.xxx;',
		'  i=mod(i,289.0);',
		'  vec4 p=permute(permute(permute(i.z+vec4(0.0,i1.z,i2.z,1.0))+i.y+vec4(0.0,i1.y,i2.y,1.0))+i.x+vec4(0.0,i1.x,i2.x,1.0));',
		'  float n_=1.0/7.0; vec3 ns=n_*D.wyz-D.xzx;',
		'  vec4 j=p-49.0*floor(p*ns.z*ns.z);',
		'  vec4 x_=floor(j*ns.z); vec4 y_=floor(j-7.0*x_);',
		'  vec4 x=x_*ns.x+ns.yyyy; vec4 y=y_*ns.x+ns.yyyy; vec4 h=1.0-abs(x)-abs(y);',
		'  vec4 b0=vec4(x.xy,y.xy); vec4 b1=vec4(x.zw,y.zw);',
		'  vec4 s0=floor(b0)*2.0+1.0; vec4 s1=floor(b1)*2.0+1.0; vec4 sh=-step(h,vec4(0.0));',
		'  vec4 a0=b0.xzyw+s0.xzyw*sh.xxyy; vec4 a1=b1.xzyw+s1.xzyw*sh.zzww;',
		'  vec3 p0=vec3(a0.xy,h.x); vec3 p1=vec3(a0.zw,h.y); vec3 p2=vec3(a1.xy,h.z); vec3 p3=vec3(a1.zw,h.w);',
		'  vec4 norm=taylorInvSqrt(vec4(dot(p0,p0),dot(p1,p1),dot(p2,p2),dot(p3,p3)));',
		'  p0*=norm.x; p1*=norm.y; p2*=norm.z; p3*=norm.w;',
		'  vec4 m=max(0.6-vec4(dot(x0,x0),dot(x1,x1),dot(x2,x2),dot(x3,x3)),0.0); m=m*m;',
		'  return 42.0*dot(m*m,vec4(dot(p0,x0),dot(p1,x1),dot(p2,x2),dot(p3,x3)));',
		'}'
	].join('\n');

	function hasWebGL() {
		try {
			var c = document.createElement('canvas');
			return !!(window.WebGLRenderingContext && (c.getContext('webgl') || c.getContext('experimental-webgl')));
		} catch (e) { return false; }
	}

	function fallback(root) { root.classList.add('fw-webgl--fallback'); }

	function detailFor(quality) {
		if (quality === 'high') { return 6; }
		if (quality === 'low') { return 3; }
		return 4; // auto
	}

	// Auto quality downgrade hint for weak / mobile GPUs.
	function isWeak() {
		var mem = navigator.deviceMemory || 4;
		var cores = navigator.hardwareConcurrency || 4;
		var mobile = /Mobi|Android|iPhone|iPad/i.test(navigator.userAgent || '');
		return mobile || mem <= 2 || cores <= 2;
	}

	function makeEnv(renderer, a, b) {
		var size = 256;
		var c = document.createElement('canvas'); c.width = size; c.height = size;
		var ctx = c.getContext('2d');
		var g = ctx.createLinearGradient(0, 0, 0, size);
		g.addColorStop(0, b); g.addColorStop(0.5, a); g.addColorStop(1, '#05070d');
		ctx.fillStyle = g; ctx.fillRect(0, 0, size, size);
		var rg = ctx.createRadialGradient(size * 0.72, size * 0.28, 0, size * 0.72, size * 0.28, size * 0.55);
		rg.addColorStop(0, 'rgba(255,255,255,0.95)'); rg.addColorStop(1, 'rgba(255,255,255,0)');
		ctx.fillStyle = rg; ctx.fillRect(0, 0, size, size);
		// A second, softer highlight for richer reflections on dark backgrounds.
		var rg2 = ctx.createRadialGradient(size * 0.24, size * 0.7, 0, size * 0.24, size * 0.7, size * 0.42);
		rg2.addColorStop(0, 'rgba(255,255,255,0.55)'); rg2.addColorStop(1, 'rgba(255,255,255,0)');
		ctx.fillStyle = rg2; ctx.fillRect(0, 0, size, size);

		var tex = new THREE.CanvasTexture(c);
		tex.mapping = THREE.EquirectangularReflectionMapping;
		var pmrem = new THREE.PMREMGenerator(renderer);
		var env = pmrem.fromEquirectangular(tex).texture;
		tex.dispose(); pmrem.dispose();
		return env;
	}

	function buildMesh(cfg, env) {
		var preset = cfg.preset || 'glass';
		var opts = cfg.presetOpts || {};
		var colA = new THREE.Color(cfg.colorA || '#6aa6ff');

		if (preset === 'particles') {
			var count = Math.max(500, Math.min(12000, opts.particle_count || 4000));
			var pos = new Float32Array(count * 3);
			var off = 2 / count, inc = Math.PI * (3 - Math.sqrt(5));
			for (var i = 0; i < count; i++) {
				var y = i * off - 1 + off / 2;
				var r = Math.sqrt(Math.max(0, 1 - y * y));
				var phi = i * inc;
				pos[i * 3] = Math.cos(phi) * r;
				pos[i * 3 + 1] = y;
				pos[i * 3 + 2] = Math.sin(phi) * r;
			}
			var pg = new THREE.BufferGeometry();
			pg.setAttribute('position', new THREE.BufferAttribute(pos, 3));
			var pm = new THREE.PointsMaterial({
				color: colA, size: opts.particle_size || 0.02,
				transparent: true, opacity: 0.9, depthWrite: false,
				blending: THREE.AdditiveBlending, sizeAttenuation: true
			});
			return { object: new THREE.Points(pg, pm), uniforms: null, geometry: pg, material: pm };
		}

		var geo = new THREE.IcosahedronGeometry(1, detailFor(cfg.quality));
		var mat;
		if (preset === 'metal') {
			mat = new THREE.MeshPhysicalMaterial({
				color: colA, metalness: opts.metalness != null ? opts.metalness : 1,
				roughness: opts.roughness != null ? opts.roughness : 0.15,
				envMap: env, envMapIntensity: 1.2
			});
		} else if (preset === 'sphere') {
			mat = new THREE.MeshPhysicalMaterial({
				color: colA, metalness: 0,
				roughness: opts.roughness != null ? opts.roughness : 0.6,
				envMap: env, envMapIntensity: 0.6
			});
		} else { // glass
			mat = new THREE.MeshPhysicalMaterial({
				color: new THREE.Color('#ffffff'),
				metalness: 0, roughness: 0.04,
				transmission: 1, thickness: 1.2,
				ior: opts.ior != null ? opts.ior : 1.45,
				iridescence: opts.iridescence != null ? opts.iridescence : 0.3,
				iridescenceIOR: 1.3,
				clearcoat: 1, clearcoatRoughness: 0.12,
				specularIntensity: 1,
				envMap: env, envMapIntensity: 2,
				attenuationColor: colA, attenuationDistance: 1.6
			});
		}

		// Inject GPU noise displacement (+ recomputed normal) into the vertex shader.
		var uniforms = { uTime: { value: 0 }, uAmp: { value: (cfg.noiseAmount || 0) * 0.45 }, uFreq: { value: 1.1 } };
		mat.onBeforeCompile = function (shader) {
			shader.uniforms.uTime = uniforms.uTime;
			shader.uniforms.uAmp = uniforms.uAmp;
			shader.uniforms.uFreq = uniforms.uFreq;
			shader.vertexShader = 'uniform float uTime;uniform float uAmp;uniform float uFreq;\n' + SIMPLEX + '\n' + shader.vertexShader;
			shader.vertexShader = shader.vertexShader.replace(
				'#include <beginnormal_vertex>',
				[
					'#include <beginnormal_vertex>',
					'vec3 _p = position*uFreq + uTime;',
					'float _n = snoise(_p);',
					'float _e = 0.35;',
					'vec3 _grad = vec3(snoise(_p+vec3(_e,0.0,0.0))-_n, snoise(_p+vec3(0.0,_e,0.0))-_n, snoise(_p+vec3(0.0,0.0,_e))-_n);',
					'objectNormal = normalize(objectNormal - uAmp*(_grad - dot(_grad,objectNormal)*objectNormal));'
				].join('\n')
			);
			shader.vertexShader = shader.vertexShader.replace(
				'#include <begin_vertex>',
				'#include <begin_vertex>\ntransformed += normal * (_n * uAmp);'
			);
		};

		return { object: new THREE.Mesh(geo, mat), uniforms: uniforms, geometry: geo, material: mat };
	}

	function initOne(root) {
		if (root.__webglReady) { return; }
		root.__webglReady = true;

		var poster = root.querySelector('.fw-webgl__poster');

		// Background mode: fill the parent <section> and sit behind its content.
		// Relocate the canvas to be a direct child of the section so it positions
		// relative to the section (not its column) and the content paints on top.
		var bgMode = root.getAttribute('data-webgl-bg') === '1';
		var sectionEl = null;
		if (bgMode) {
			sectionEl = root.closest('section') || root.parentElement;
			if (sectionEl) {
				sectionEl.classList.add('fw-webgl-host');
				if (root.parentElement !== sectionEl) {
					sectionEl.insertBefore(root, sectionEl.firstChild);
				}
			}
		}

		if (!THREE || !hasWebGL()) { fallback(root); return; }
		// Reduce-motion: prefer a poster; otherwise we still draw ONE static frame below.
		if (REDUCE && poster) { fallback(root); return; }

		var cfg;
		try { cfg = JSON.parse(root.getAttribute('data-config') || '{}'); } catch (e) { cfg = {}; }
		if (cfg.quality === 'auto' && isWeak()) { cfg.quality = 'low'; }

		var host = root.querySelector('.fw-webgl__canvas') || root;
		var transparent = (cfg.background === 'transparent');

		var renderer;
		try {
			renderer = new THREE.WebGLRenderer({ alpha: transparent, antialias: cfg.quality !== 'low', powerPreference: 'high-performance' });
		} catch (e) { fallback(root); return; }

		var dprCap = cfg.dprCap || 2;
		if (cfg.quality === 'low') { dprCap = Math.min(dprCap, 1.5); }
		renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, dprCap));
		renderer.outputEncoding = THREE.sRGBEncoding;
		renderer.toneMapping = THREE.ACESFilmicToneMapping;
		renderer.toneMappingExposure = 1.2;
		if (!transparent) { renderer.setClearColor(new THREE.Color(cfg.background === 'solid' ? (cfg.bgColor || '#0b0f1a') : '#05070d'), 1); }
		host.appendChild(renderer.domElement);

		var scene = new THREE.Scene();
		var camera = new THREE.PerspectiveCamera(45, 1, 0.1, 100);
		// Closer camera → the object fills the frame (and bleeds slightly past the
		// edges, like Hatom's hero blobs) rather than floating small in the middle.
		camera.position.set(0, 0, 2.5);

		// Glass transmission needs an environment; metal/sphere use it for reflections.
		var env = makeEnv(renderer, cfg.colorA || '#6aa6ff', cfg.colorB || '#b388ff');
		scene.environment = env;

		var key = new THREE.DirectionalLight(0xffffff, 2.0); key.position.set(2, 3, 2); scene.add(key);
		var rim = new THREE.DirectionalLight(new THREE.Color(cfg.colorB || '#b388ff'), 1.5); rim.position.set(-3, -1, -2); scene.add(rim);
		var fill = new THREE.DirectionalLight(new THREE.Color(cfg.colorA || '#6aa6ff'), 0.8); fill.position.set(0, -2, 3); scene.add(fill);
		scene.add(new THREE.AmbientLight(0xffffff, 0.3));

		var built = buildMesh(cfg, env);
		var obj = built.object;
		var s = cfg.scale || 1; obj.scale.setScalar(s);
		scene.add(obj);

		var size = function () {
			var w = root.clientWidth || 1, h = root.clientHeight || 1;
			renderer.setSize(w, h, false);
			camera.aspect = w / h; camera.updateProjectionMatrix();
		};
		size();

		// Pointer + parallax. In background mode the canvas is click-through, so
		// listen on the window and measure against the section instead of the root.
		var px = 0, py = 0, tx = 0, ty = 0;
		if (cfg.pointerFollow) {
			var moveTarget = bgMode ? window : root;
			var rectSource = (bgMode && sectionEl) ? sectionEl : root;
			moveTarget.addEventListener('pointermove', function (e) {
				var r = rectSource.getBoundingClientRect();
				tx = ((e.clientX - r.left) / r.width - 0.5) * 2;
				ty = ((e.clientY - r.top) / r.height - 0.5) * 2;
			});
			if (!bgMode) {
				root.addEventListener('pointerleave', function () { tx = 0; ty = 0; });
			}
		}

		var clock = new THREE.Clock();
		var running = false, raf = 0, last = 0;
		var minDelta = (cfg.quality === 'low') ? (1 / 30) : 0;

		function renderFrame() {
			var t = clock.getElapsedTime();
			if (built.uniforms) { built.uniforms.uTime.value = t * (0.15 + (cfg.noiseSpeed || 0) * 0.6); }

			// Smooth the pointer toward its target.
			px += (tx - px) * 0.06; py += (ty - py) * 0.06;
			var ps = cfg.pointerFollow ? (cfg.pointerStrength || 0) : 0;

			// Continuous auto-spin + a pointer-driven tilt offset.
			obj.rotation.y += (cfg.autoRotate || 0) * 0.005 + px * 0.01 * ps;
			obj.rotation.x = py * 0.5 * ps;

			var pr = cfg.parallax || 0;
			camera.position.x += ((px * 0.4 * pr) - camera.position.x) * 0.05;
			camera.position.y += ((-py * 0.3 * pr) - camera.position.y) * 0.05;
			camera.lookAt(0, 0, 0);

			if (cfg.scrollLink) {
				var r = root.getBoundingClientRect();
				var prog = 1 - Math.max(0, Math.min(1, (r.top + r.height) / (window.innerHeight + r.height)));
				obj.rotation.z = (prog - 0.5) * 0.6;
				obj.scale.setScalar(s * (1 + (prog - 0.5) * 0.12));
			}

			renderer.render(scene, camera);
		}

		function loop() {
			if (!running) { return; }
			if (!document.body.contains(root)) { dispose(); return; }
			raf = requestAnimationFrame(loop);
			var now = clock.getElapsedTime();
			if (minDelta && (now - last) < minDelta) { return; }
			last = now;
			renderFrame();
		}

		function start() { if (running) { return; } running = true; loop(); }
		function stop() { running = false; if (raf) { cancelAnimationFrame(raf); raf = 0; } }

		function dispose() {
			stop();
			if (built.geometry) { built.geometry.dispose(); }
			if (built.material) { built.material.dispose(); }
			if (env) { env.dispose(); }
			renderer.dispose();
			if (renderer.domElement && renderer.domElement.parentNode) { renderer.domElement.parentNode.removeChild(renderer.domElement); }
		}
		root.__webglDispose = dispose;

		// Resize.
		if (window.ResizeObserver) {
			var ro = new ResizeObserver(function () { size(); if (!running) { renderFrame(); } });
			ro.observe(root);
		} else {
			window.addEventListener('resize', size);
		}

		// Reduce-motion (no poster): one static frame, no loop.
		if (REDUCE) { renderFrame(); return; }

		// Run only while in view; pause when the tab is hidden.
		if ('IntersectionObserver' in window) {
			var io = new IntersectionObserver(function (entries) {
				entries.forEach(function (en) { if (en.isIntersecting) { start(); } else { stop(); } });
			}, { threshold: 0.05 });
			io.observe(root);
		} else {
			start();
		}
		document.addEventListener('visibilitychange', function () {
			if (document.hidden) { stop(); } else if ('IntersectionObserver' in window) {
				var r = root.getBoundingClientRect();
				if (r.bottom > 0 && r.top < window.innerHeight) { start(); }
			}
		});
	}

	function init() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-webgl]'), initOne);
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
