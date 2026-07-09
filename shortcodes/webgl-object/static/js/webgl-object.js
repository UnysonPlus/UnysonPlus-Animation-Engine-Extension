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

	// ---- Full-screen shader presets (fragment shader on a quad) ----
	var QUAD_VS = 'varying vec2 vUv; void main(){ vUv = uv; gl_Position = vec4(position.xy, 0.0, 1.0); }';

	var FRAG_HEAD = [
		'precision highp float;',
		'varying vec2 vUv;',
		'uniform float uTime; uniform vec2 uResolution; uniform vec2 uMouse;',
		'uniform float uScroll; uniform vec3 uColorA; uniform vec3 uColorB;',
		'uniform float uP1; uniform float uP2; uniform float uP3;',
		'uniform sampler2D uTexture; uniform float uHasTex;'
	].join('\n');

	var FBM = 'float fbm(vec3 q){float f=0.0,a=0.5;for(int i=0;i<5;i++){f+=a*snoise(q);q*=2.0;a*=0.5;}return f;}';

	var FRAG = {
		gradient_mesh:
			'void main(){vec2 p=vUv;float t=uTime*(0.1+uP1*0.5);vec2 m=uMouse*0.15;' +
			'float a=0.5+0.5*sin(t+p.x*3.0+m.x);float b=0.5+0.5*sin(t*1.3+p.y*3.0+m.y);' +
			'float c=0.5+0.5*sin(t*0.7+(p.x+p.y)*2.5);vec3 col=mix(uColorA,uColorB,a);' +
			'col=mix(col,uColorB,b*0.5);col=mix(col,uColorA,c*0.35);' +
			'float g=fract(sin(dot(p,vec2(12.9898,78.233)))*43758.5453);col+=(g-0.5)*uP2;' +
			'gl_FragColor=vec4(col,1.0);}',
		plasma: SIMPLEX + FBM +
			'void main(){vec2 p=(vUv-0.5);p.x*=uResolution.x/uResolution.y;float s=uP1;' +
			'float t=uTime*(0.1+uP2*0.4);float n=fbm(vec3(p*s,t));' +
			'n=pow(0.5+0.5*n,mix(1.0,3.0,uP3));gl_FragColor=vec4(mix(uColorA,uColorB,n),1.0);}',
		aurora: SIMPLEX + FBM +
			'void main(){vec2 p=vUv;float t=uTime*(0.1+uP2*0.3);float warp=fbm(vec3(p*2.0,t));' +
			'float v=sin((p.y*uP1+warp)*3.1415);v=smoothstep(1.0-uP3,1.0,abs(v));' +
			'gl_FragColor=vec4(mix(uColorA,uColorB,p.y)*v,1.0);}',
		fluid: SIMPLEX +
			'void main(){vec2 p=vUv;vec2 m=uMouse*0.5;float t=uTime*0.2;' +
			'vec2 q=p+0.1*vec2(snoise(vec3(p*3.0+m,t)),snoise(vec3(p*3.0-m,t+5.0)));' +
			'float n=snoise(vec3(q*4.0,t))*(0.5+uP2);n=mix(n,n*0.5,uP1);' +
			'gl_FragColor=vec4(mix(uColorA,uColorB,0.5+0.5*n),1.0);}',
		dots: SIMPLEX +
			'void main(){float density=uP1;vec2 g=vUv*density;vec2 cell=fract(g)-0.5;' +
			'float field=0.5+0.5*snoise(vec3(floor(g)/max(density,1.0)*3.0,uTime*0.2));' +
			'float radius=mix(field,0.5,uP3)*uP2*0.5;float d=length(cell);' +
			'float dt=smoothstep(radius,radius-0.05,d);gl_FragColor=vec4(mix(uColorA,uColorB,dt),1.0);}',
		image_distort: SIMPLEX +
			'void main(){vec2 p=vUv;float amt=uP1;' +
			'vec2 flow=vec2(snoise(vec3(p*4.0,uTime*0.3)),snoise(vec3(p*4.0+10.0,uTime*0.3)));' +
			'vec2 uv=p+flow*amt*0.1;' +
			'gl_FragColor=vec4(uHasTex>0.5?texture2D(uTexture,uv).rgb:mix(uColorA,uColorB,p.y),1.0);}'
	};

	function isShaderPreset(p) { return FRAG.hasOwnProperty(p); }

	// ---- Image Particles: an image sampled into a grid of coloured points (a 3D THREE.Points
	// cloud) that drift and SCATTER away from the cursor. Not a full-screen quad — a real 3D path. ----
	var PARTICLE_VS = [
		'attribute vec3 aColor; attribute float aSeed;',
		'uniform float uTime, uSize, uRepel, uRadius, uJitter, uDrift, uDpr;',
		'uniform vec2 uMouse;',
		'varying vec3 vColor;',
		'void main(){',
		'  vColor = aColor;',
		'  vec3 p = position;',
		'  float ph = aSeed * 6.2831853;',
		'  p.z += sin(uTime*0.7 + ph) * uJitter * 3.0;',              // idle shimmer in depth
		'  p.x += sin(uTime*uDrift + ph) * uJitter;',
		'  p.y += cos(uTime*uDrift*0.9 + ph*1.3) * uJitter;',
		'  vec2 d = p.xy - uMouse;',                                    // cursor repel (plane space)
		'  float dist = length(d);',
		'  if (dist < uRadius) {',
		'    float f = 1.0 - dist/uRadius; f = f*f;',
		'    p.xy += normalize(d + vec2(1e-4)) * f * uRepel;',
		'    p.z  += f * uRepel * 1.5;',
		'  }',
		'  vec4 mv = modelViewMatrix * vec4(p, 1.0);',
		'  gl_Position = projectionMatrix * mv;',
		'  gl_PointSize = uSize * uDpr * (2.0 / max(0.1, -mv.z));',
		'}'
	].join('\n');
	var PARTICLE_FS = [
		'precision highp float;',
		'varying vec3 vColor;',
		'void main(){',
		'  vec2 c = gl_PointCoord - 0.5;',
		'  float a = smoothstep(0.5, 0.32, length(c));',              // round soft point
		'  if (a < 0.02) discard;',
		'  gl_FragColor = vec4(vColor, a);',
		'}'
	].join('\n');

	// Sample the image into a cols×rows grid of points; fill the geometry's position/colour/seed.
	function sampleImageInto(geo, img, cols) {
		var iw = img.naturalWidth || img.width, ih = img.naturalHeight || img.height;
		var aspect = (iw && ih) ? iw / ih : 1;
		var rows = Math.max(6, Math.round(cols / aspect));
		var cv = document.createElement('canvas'); cv.width = cols; cv.height = rows;
		var ctx = cv.getContext('2d');
		if (!ctx) { return false; }
		var data;
		try { ctx.drawImage(img, 0, 0, cols, rows); data = ctx.getImageData(0, 0, cols, rows).data; }
		catch (e) { return false; } // cross-origin taint
		var n = cols * rows, k = 0, w = 2 * aspect, h = 2;
		var pos = new Float32Array(n * 3), col = new Float32Array(n * 3), seed = new Float32Array(n);
		for (var y = 0; y < rows; y++) {
			for (var x = 0; x < cols; x++) {
				var idx = (y * cols + x) * 4, al = data[idx + 3] / 255;
				pos[k * 3]     = (cols > 1 ? (x / (cols - 1) - 0.5) : 0) * w;
				pos[k * 3 + 1] = -(rows > 1 ? (y / (rows - 1) - 0.5) : 0) * h;
				pos[k * 3 + 2] = 0;
				col[k * 3]     = (data[idx] / 255) * al;      // transparent pixels fade to black (invisible)
				col[k * 3 + 1] = (data[idx + 1] / 255) * al;
				col[k * 3 + 2] = (data[idx + 2] / 255) * al;
				seed[k] = Math.random();
				k++;
			}
		}
		geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
		geo.setAttribute('aColor', new THREE.BufferAttribute(col, 3));
		geo.setAttribute('aSeed', new THREE.BufferAttribute(seed, 1));
		geo.computeBoundingSphere();
		return true;
	}

	function buildImageParticles(cfg) {
		var o = cfg.presetOpts || {};
		var cols = Math.max(20, Math.min(240, Math.round(o.grid_density || 120)));
		var geo = new THREE.BufferGeometry();
		var num = function (v, d) { var f = parseFloat(v); return isNaN(f) ? d : f; };
		var uniforms = {
			uTime:   { value: 0 }, uMouse: { value: new THREE.Vector2(-999, -999) },
			uSize:   { value: num(o.point_size, 6) }, uRepel: { value: num(o.repel, 0.5) },
			uRadius: { value: num(o.radius, 0.35) }, uJitter: { value: num(o.jitter, 0.015) },
			uDrift:  { value: num(o.drift, 0.4) }, uDpr: { value: Math.min(window.devicePixelRatio || 1, 2) }
		};
		if (REDUCE) { uniforms.uJitter.value = 0; uniforms.uDrift.value = 0; } // no idle drift under reduce-motion
		var mat = new THREE.ShaderMaterial({
			uniforms: uniforms, transparent: true, depthWrite: false,
			vertexShader: PARTICLE_VS, fragmentShader: PARTICLE_FS
		});
		var points = new THREE.Points(geo, mat);
		points.visible = false;
		if (o.imageUrl) {
			var img = new Image();
			img.crossOrigin = 'anonymous';
			img.onload = function () { if (sampleImageInto(geo, img, cols)) { points.visible = true; } };
			img.src = o.imageUrl;
		}
		return { object: points, uniforms: uniforms, geometry: geo, material: mat };
	}

	// Map each preset's named sub-options onto the generic uP1..uP3 slots.
	function paramFor(cfg, n) {
		var o = cfg.presetOpts || {};
		var num = function (v, d) { return (v === undefined || v === null || v === '') ? d : parseFloat(v); };
		var t;
		switch (cfg.preset) {
			case 'gradient_mesh': t = [num(o.blend_speed, 0.4), num(o.grain, 0.15), 0]; break;
			case 'plasma':        t = [num(o.scale, 3), num(o.flow_speed, 0.5), num(o.contrast, 0.6)]; break;
			case 'aurora':        t = [num(o.band_count, 3), num(o.drift_speed, 0.4), num(o.softness, 0.5)]; break;
			case 'fluid':         t = [num(o.viscosity, 0.5), num(o.splat_strength, 0.6), 0]; break;
			case 'dots':          t = [num(o.grid_density, 40), num(o.dot_size, 0.5), (o.dot_style === 'halftone' ? 1 : 0)]; break;
			case 'image_distort': t = [num(o.strength, 0.3), 0, 0]; break;
			default:              t = [0, 0, 0];
		}
		return t[n - 1];
	}

	function buildShader(cfg) {
		var uniforms = {
			uTime:       { value: 0 },
			uResolution: { value: new THREE.Vector2(1, 1) },
			uMouse:      { value: new THREE.Vector2(0, 0) },
			uScroll:     { value: 0 },
			uColorA:     { value: new THREE.Color(cfg.colorA || '#6aa6ff') },
			uColorB:     { value: new THREE.Color(cfg.colorB || '#b388ff') },
			uP1:         { value: paramFor(cfg, 1) },
			uP2:         { value: paramFor(cfg, 2) },
			uP3:         { value: paramFor(cfg, 3) },
			uTexture:    { value: null },
			uHasTex:     { value: 0 }
		};
		var mat = new THREE.ShaderMaterial({
			vertexShader: QUAD_VS,
			fragmentShader: FRAG_HEAD + '\n' + FRAG[cfg.preset],
			uniforms: uniforms
		});
		var geo = new THREE.PlaneGeometry(2, 2);
		var tex = null;
		if (cfg.preset === 'image_distort' && cfg.presetOpts && cfg.presetOpts.imageUrl) {
			tex = new THREE.TextureLoader().load(cfg.presetOpts.imageUrl, function () { uniforms.uHasTex.value = 1; });
			uniforms.uTexture.value = tex;
			// hover_only starts un-distorted and eases up on pointer-over.
			if (cfg.presetOpts.hover_only === 'yes') { uniforms.uP1.value = 0; }
		}
		return { object: new THREE.Mesh(geo, mat), uniforms: uniforms, geometry: geo, material: mat, texture: tex, shader: true };
	}

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

		if (preset === 'image_particles') { return buildImageParticles(cfg); }

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

		var shaderMode = isShaderPreset(cfg.preset);

		var scene = new THREE.Scene();
		var camera, env = null, built, obj, s = cfg.scale || 1;

		if (shaderMode) {
			// Full-screen quad + fragment shader: no 3D camera/lights/env needed.
			camera = new THREE.OrthographicCamera(-1, 1, 1, -1, 0, 1);
			built = buildShader(cfg);
			obj = built.object;
			scene.add(obj);
		} else {
			camera = new THREE.PerspectiveCamera(45, 1, 0.1, 100);
			// Closer camera → the object fills the frame (and bleeds slightly past the
			// edges, like Hatom's hero blobs) rather than floating small in the middle.
			camera.position.set(0, 0, 2.5);

			// Image Particles is unlit (vertex-coloured points) — skip the env + lights it never uses.
			if (cfg.preset !== 'image_particles') {
				// Glass transmission needs an environment; metal/sphere use it for reflections.
				env = makeEnv(renderer, cfg.colorA || '#6aa6ff', cfg.colorB || '#b388ff');
				scene.environment = env;

				var key = new THREE.DirectionalLight(0xffffff, 2.0); key.position.set(2, 3, 2); scene.add(key);
				var rim = new THREE.DirectionalLight(new THREE.Color(cfg.colorB || '#b388ff'), 1.5); rim.position.set(-3, -1, -2); scene.add(rim);
				var fill = new THREE.DirectionalLight(new THREE.Color(cfg.colorA || '#6aa6ff'), 0.8); fill.position.set(0, -2, 3); scene.add(fill);
				scene.add(new THREE.AmbientLight(0xffffff, 0.3));
			}

			built = buildMesh(cfg, env);
			obj = built.object;
			obj.scale.setScalar(s);
			scene.add(obj);
		}

		var size = function () {
			var w = root.clientWidth || 1, h = root.clientHeight || 1;
			renderer.setSize(w, h, false);
			if (shaderMode) {
				built.uniforms.uResolution.value.set(w, h);
			} else {
				camera.aspect = w / h; camera.updateProjectionMatrix();
			}
		};
		size();

		// Pointer + parallax. In background mode the canvas is click-through, so
		// listen on the window and measure against the section instead of the root.
		var px = 0, py = 0, tx = 0, ty = 0;
		if (cfg.pointerFollow || cfg.preset === 'image_particles') {
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

		// Image Distortion "hover only": ramp the distortion up while the pointer is over.
		var hoverActive = 0;
		if (shaderMode && cfg.preset === 'image_distort' && cfg.presetOpts && cfg.presetOpts.hover_only === 'yes') {
			var hoverTarget = (bgMode && sectionEl) ? sectionEl : root;
			hoverTarget.addEventListener('pointerenter', function () { hoverActive = 1; });
			hoverTarget.addEventListener('pointerleave', function () { hoverActive = 0; });
		}

		var clock = new THREE.Clock();
		var running = false, raf = 0, last = 0;
		var minDelta = (cfg.quality === 'low') ? (1 / 30) : 0;

		function renderFrame() {
			var t = clock.getElapsedTime();

			// Smooth the pointer toward its target.
			px += (tx - px) * 0.06; py += (ty - py) * 0.06;

			// ---- Shader path: feed the quad's uniforms and draw. ----
			if (shaderMode) {
				var u = built.uniforms;
				u.uTime.value = t;
				u.uMouse.value.set(px, py);
				if (cfg.scrollLink) {
					var sr = root.getBoundingClientRect();
					u.uScroll.value = 1 - Math.max(0, Math.min(1, (sr.top + sr.height) / (window.innerHeight + sr.height)));
				}
				if (cfg.preset === 'image_distort' && cfg.presetOpts && cfg.presetOpts.hover_only === 'yes') {
					var want = hoverActive * (parseFloat(cfg.presetOpts.strength) || 0.3);
					u.uP1.value += (want - u.uP1.value) * 0.08;
				}
				renderer.render(scene, camera);
				return;
			}

			// ---- Image Particles: drive time + the cursor in plane space; no rotation/parallax. ----
			if (cfg.preset === 'image_particles') {
				built.uniforms.uTime.value = t;
				var vh = Math.tan(45 * Math.PI / 360) * camera.position.z; // visible half-height at z=0
				built.uniforms.uMouse.value.set(px * vh * camera.aspect, -py * vh);
				renderer.render(scene, camera);
				return;
			}

			// ---- 3D object path. ----
			if (built.uniforms) { built.uniforms.uTime.value = t * (0.15 + (cfg.noiseSpeed || 0) * 0.6); }
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
			if (built.texture) { built.texture.dispose(); }
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
		// Image Particles keeps a (motionless) loop so the image still renders once it loads and the
		// cursor scatter — a deliberate interaction, not autoplay — still responds; others draw once.
		if (REDUCE && cfg.preset !== 'image_particles') { renderFrame(); return; }

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
