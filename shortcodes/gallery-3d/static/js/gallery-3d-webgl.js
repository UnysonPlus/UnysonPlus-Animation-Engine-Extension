/*!
 * Unyson+ Motion Gallery — WebGL designs (real 3D: depth, lighting, reflections).
 *
 * Three designs drawn with Three.js (the copy the Animation Engine already ships for WebGL Object), plugged
 * into the Motion Gallery through window.upwGallery3d.registerDesign, so they share its options, motion
 * modes (auto / scroll-scrub / static), lightbox and links:
 *   - webgl_mirror_ring  photos on a turning ring above a glossy floor that reflects them, lit by real lights
 *   - webgl_depth_field  photos floating at true depth in fog, drifting toward the viewer; the camera follows the pointer
 *   - webgl_wave_wall    a lit grid of photos on a rippling surface that catches the light as it moves
 *
 * The photos stay in the page as the usual accessible card list (visually hidden, still focusable); the canvas
 * draws them. Clicking a photo in 3D clicks its card (lightbox / link). Keyboard focus on a card brings it to
 * the front. No WebGL → the card list shows as a plain grid. Reduced motion / the Live Editor's Edit canvas →
 * one still frame. Rendering pauses while the gallery is off screen or the tab is hidden.
 */
( function () {
	'use strict';

	if ( ! window.upwGallery3d || ! window.upwGallery3d.registerDesign ) { return; }

	var EASE = function ( t ) { return t < 0.5 ? 4 * t * t * t : 1 - Math.pow( -2 * t + 2, 3 ) / 2; };

	function webglOK() {
		if ( ! window.THREE ) { return false; }
		try { var c = document.createElement( 'canvas' ); return !! ( c.getContext( 'webgl2' ) || c.getContext( 'webgl' ) ); } catch ( e ) { return false; }
	}

	/** The card ratio (h / w) from the gallery's --tdg-ratio ("3 / 4", "1", "auto"…). */
	function ratioOf( el ) {
		var r = String( window.getComputedStyle( el ).getPropertyValue( '--tdg-ratio' ) || '' ).trim();
		var m = r.match( /([\d.]+)\s*\/\s*([\d.]+)/ );
		if ( m ) { return parseFloat( m[ 2 ] ) / parseFloat( m[ 1 ] ); }
		var n = parseFloat( r );
		return n > 0 ? 1 / n : 4 / 3;
	}
	function radiusOf( el ) { return parseFloat( window.getComputedStyle( el ).getPropertyValue( '--tdg-radius' ) ) || 0; }

	/** A photo drawn cover-fit into a rounded card (canvas → texture). Resolves to a THREE texture. */
	function cardTexture( src, aspect, radiusFrac, cb, hue ) {
		var W = 512, H = Math.round( W * aspect );
		var cv = document.createElement( 'canvas' ); cv.width = W; cv.height = H;
		var g = cv.getContext( '2d' );
		var tex = new THREE.CanvasTexture( cv );
		tex.encoding = THREE.sRGBEncoding;
		tex.anisotropy = 4;
		var draw = function ( img ) {
			g.clearRect( 0, 0, W, H );
			var r = Math.min( W, H ) * radiusFrac;
			g.save();
			g.beginPath();
			g.moveTo( r, 0 ); g.lineTo( W - r, 0 ); g.quadraticCurveTo( W, 0, W, r ); g.lineTo( W, H - r ); g.quadraticCurveTo( W, H, W - r, H );
			g.lineTo( r, H ); g.quadraticCurveTo( 0, H, 0, H - r ); g.lineTo( 0, r ); g.quadraticCurveTo( 0, 0, r, 0 ); g.closePath();
			g.clip();
			if ( img ) {
				var s = Math.max( W / img.naturalWidth, H / img.naturalHeight ), dw = img.naturalWidth * s, dh = img.naturalHeight * s;
				g.drawImage( img, ( W - dw ) / 2, ( H - dh ) / 2, dw, dh );
			} else if ( hue != null ) {
				// No photo (the builder's preview placeholders): a coloured card, like the CSS designs show.
				var gr = g.createLinearGradient( 0, 0, W, H );
				gr.addColorStop( 0, 'hsl(' + hue + ',62%,60%)' ); gr.addColorStop( 1, 'hsl(' + ( ( hue + 40 ) % 360 ) + ',58%,42%)' );
				g.fillStyle = gr; g.fillRect( 0, 0, W, H );
			} else {
				g.fillStyle = '#d7dbe2'; g.fillRect( 0, 0, W, H );
			}
			g.restore();
			tex.needsUpdate = true;
			if ( cb ) { cb(); }
		};
		draw( null );
		if ( src ) {
			var img = new Image();
			img.crossOrigin = 'anonymous';
			img.decoding = 'async';
			img.onload = function () { draw( img ); };
			img.src = src;
		}
		return tex;
	}

	/** A vertical white→transparent gradient (alpha map for the mirror-floor reflections). */
	function fadeTexture() {
		var cv = document.createElement( 'canvas' ); cv.width = 4; cv.height = 128;
		var g = cv.getContext( '2d' ), gr = g.createLinearGradient( 0, 0, 0, 128 );
		gr.addColorStop( 0, '#000' ); gr.addColorStop( 0.55, '#000' ); gr.addColorStop( 1, '#fff' );
		g.fillStyle = gr; g.fillRect( 0, 0, 4, 128 );
		return new THREE.CanvasTexture( cv );
	}

	/**
	 * Shared scene plumbing for a WebGL design: renderer + camera in the stage, the cards' textures, lights,
	 * resize, visibility pause, hover + click picking (click → the card's own link / lightbox), keyboard focus,
	 * drag, motion modes and the intro. `design.build(api)` places the meshes; `design.frame(api, dt, t)` moves them.
	 */
	function mount( ctx, design ) {
		var el = ctx.el, stage = ctx.stage, THREE = window.THREE;
		el.classList.add( 'tdg--webgl' );
		if ( ! webglOK() ) { el.classList.add( 'tdg--webgl-fallback' ); return; }

		var still = !! ( ctx.reduce || ctx.IN_EDITOR );
		// The renderer (one WebGL context) exists only while the gallery is near the screen: browsers keep a
		// small number of live contexts per page, and a page with several galleries would lose the oldest.
		// attach() makes it when the gallery approaches; detach() frees it when it scrolls far away.
		var renderer = null, canvas = null;
		var attach = function () {
			if ( renderer ) { return true; }
			try { renderer = new THREE.WebGLRenderer( { antialias: true, alpha: true, powerPreference: 'high-performance' } ); }
			catch ( e ) { renderer = null; el.classList.add( 'tdg--webgl-fallback' ); return false; }
			renderer.setPixelRatio( Math.min( window.devicePixelRatio || 1, 2 ) );
			renderer.outputEncoding = THREE.sRGBEncoding;
			renderer.toneMapping = THREE.ACESFilmicToneMapping;
			renderer.toneMappingExposure = 1.05;
			canvas = renderer.domElement;
			canvas.className = 'tdg__webgl';
			canvas.setAttribute( 'aria-hidden', 'true' );
			stage.appendChild( canvas );
			api.renderer = renderer;
			resize();
			return true;
		};
		var detach = function () {
			if ( ! renderer ) { return; }
			try { renderer.dispose(); if ( renderer.forceContextLoss ) { renderer.forceContextLoss(); } } catch ( e ) {}
			if ( canvas && canvas.parentNode ) { canvas.parentNode.removeChild( canvas ); }
			renderer = null; canvas = null; api.renderer = null;
		};

		var scene = new THREE.Scene();
		var camera = new THREE.PerspectiveCamera( 35, 1, 0.1, 5000 );
		var aspect = ratioOf( el ), radPx = radiusOf( el );

		// Lights (Light: soft / studio / dramatic).
		var light = ctx.attr( 'data-tdg-light', 'studio' );
		var amb = new THREE.HemisphereLight( 0xffffff, 0x404858, light === 'dramatic' ? 0.35 : ( light === 'soft' ? 1.0 : 0.7 ) );
		var key = new THREE.DirectionalLight( 0xffffff, light === 'dramatic' ? 2.2 : ( light === 'soft' ? 0.6 : 1.3 ) );
		var rim = new THREE.DirectionalLight( 0xbfd4ff, light === 'dramatic' ? 1.4 : 0.5 );
		key.position.set( 3, 5, 6 ); rim.position.set( -4, 3, -6 );
		scene.add( amb, key, rim );

		var api = {
			ctx: ctx, el: el, THREE: THREE, scene: scene, camera: camera, renderer: null, aspect: aspect, key: key,
			cards: [], hover: -1, focus: -1, t: 0, drag: 0, dragV: 0, w: 1, h: 1,
			/** One card mesh per entry in `list` (indexes into ctx.cards); radius as a fraction of the card's short side. */
			makeCards: function ( count, w ) {
				var src = ctx.cards.map( function ( c ) { var im = c.querySelector( 'img' ); return im ? ( im.currentSrc || im.getAttribute( 'src' ) ) : ''; } );
				var radFrac = Math.min( 0.5, radPx / Math.max( 1, ( stage.clientWidth || 800 ) * 0.2 ) );
				var geo = new THREE.PlaneGeometry( w, w * aspect );
				var texCache = {};
				for ( var i = 0; i < count; i++ ) {
					var k = i % Math.max( 1, src.length );
					var tex = texCache[ k ] || ( texCache[ k ] = cardTexture( src[ k ], aspect, radFrac, function () { api.dirty = true; }, src[ k ] ? null : Math.round( k * 360 / Math.max( 1, src.length ) ) ) );
					var mat = new THREE.MeshStandardMaterial( { map: tex, roughness: api.roughness != null ? api.roughness : 0.55, metalness: 0, side: THREE.DoubleSide, transparent: true, alphaTest: 0.02 } );
					var m = new THREE.Mesh( geo, mat );
					m.userData.card = k;
					m.userData.appear = 0;
					api.cards.push( m );
				}
				return api.cards;
			},
			dirty: true
		};

		// Intro: the cards fade and rise in one after another when the gallery first comes into view.
		var introStart = -1;
		var intro = function ( t ) {
			if ( still ) { api.cards.forEach( function ( m ) { m.userData.appear = 1; } ); return; }
			if ( introStart < 0 ) { return; }
			api.cards.forEach( function ( m, i ) { m.userData.appear = Math.max( 0, Math.min( 1, ( t - introStart - i * 0.06 ) / 0.7 ) ); } );
		};

		design.build( api );

		// Size.
		var resize = function () {
			var w = stage.clientWidth || el.clientWidth || 800, h = stage.clientHeight || el.clientHeight || 500;
			api.w = w; api.h = h;
			if ( renderer ) { renderer.setSize( w, h, false ); }
			camera.aspect = w / h;
			camera.updateProjectionMatrix();
			if ( design.resize ) { design.resize( api ); }
			api.dirty = true;
		};
		resize();
		if ( window.ResizeObserver ) { new ResizeObserver( resize ).observe( stage ); } else { window.addEventListener( 'resize', resize ); }

		// Picking: hover cursor + click → the card's own link (lightbox / page).
		var ray = new THREE.Raycaster(), ndc = new THREE.Vector2( 9, 9 ), downX = 0, downY = 0, dragging = false, moved = 0, lastX = 0;
		var pick = function () {
			ray.setFromCamera( ndc, camera );
			var hit = ray.intersectObjects( api.cards, false );
			for ( var i = 0; i < hit.length; i++ ) { if ( hit[ i ].object.visible && hit[ i ].object.userData.appear > 0.5 ) { return api.cards.indexOf( hit[ i ].object ); } }
			return -1;
		};
		var setNdc = function ( e ) {
			var r = stage.getBoundingClientRect();
			ndc.set( ( ( e.clientX - r.left ) / r.width ) * 2 - 1, -( ( e.clientY - r.top ) / r.height ) * 2 + 1 );
			api.pointer = { x: ndc.x, y: ndc.y };
		};
		var allowDrag = ctx.attr( 'data-tdg-allowdrag', '0' ) === '1';
		var momentum = ctx.attr( 'data-tdg-momentum', '1' ) === '1';
		stage.addEventListener( 'pointermove', function ( e ) {
			setNdc( e );
			if ( dragging ) {
				var dx = e.clientX - lastX; lastX = e.clientX; moved += Math.abs( dx );
				api.drag += dx / Math.max( 200, api.w ) * Math.PI; api.dragV = dx / Math.max( 200, api.w ) * Math.PI;
				api.dirty = true;
				return;
			}
			var i = pick();
			if ( i !== api.hover ) { api.hover = i; api.dirty = true; }
			stage.style.cursor = i >= 0 ? 'pointer' : ( allowDrag ? 'grab' : '' );
		} );
		stage.addEventListener( 'pointerleave', function () { api.hover = -1; api.pointer = null; stage.style.cursor = ''; api.dirty = true; } );
		stage.addEventListener( 'pointerdown', function ( e ) {
			downX = e.clientX; downY = e.clientY; moved = 0; lastX = e.clientX;
			if ( allowDrag ) { dragging = true; api.grabbed = true; try { stage.setPointerCapture( e.pointerId ); } catch ( err ) {} stage.style.cursor = 'grabbing'; }
		} );
		var release = function () { if ( dragging ) { dragging = false; api.grabbed = false; if ( ! momentum ) { api.dragV = 0; } } };
		stage.addEventListener( 'pointerup', function ( e ) {
			var wasDrag = dragging && moved > 6;
			release();
			if ( wasDrag || Math.abs( e.clientX - downX ) + Math.abs( e.clientY - downY ) > 6 ) { return; }
			setNdc( e );
			var i = pick();
			if ( i < 0 ) { return; }
			var card = ctx.cards[ api.cards[ i ].userData.card ];
			var link = card && card.querySelector( 'a.tdg__link' );
			if ( link ) { link.click(); }
		} );
		stage.addEventListener( 'pointercancel', release );

		// Keyboard: the (visually hidden) card links stay focusable; focusing one brings its photo forward.
		ctx.cards.forEach( function ( c, k ) {
			c.addEventListener( 'focusin', function () {
				for ( var i = 0; i < api.cards.length; i++ ) { if ( api.cards[ i ].userData.card === k ) { api.focus = i; break; } }
				el.classList.add( 'tdg--webgl-focus' ); api.dirty = true;
			} );
			c.addEventListener( 'focusout', function () { api.focus = -1; el.classList.remove( 'tdg--webgl-focus' ); api.dirty = true; } );
		} );

		// Motion: auto / continuous loop, scroll-scrub, or static; hover can pause or slow it.
		var drive = ctx.attr( 'data-tdg-drive', 'continuous' );
		var loopS = Math.max( 1, ctx.num( 'data-tdg-speed', 20 ) );
		var hoverMode = ctx.attr( 'data-tdg-hover', 'slow' );
		var rounds = ctx.num( 'data-tdg-rounds', 1 );
		var phase = 0, pace = 1;
		api.progress = function () {
			if ( drive === 'scroll' ) { return ctx.progress() * rounds; }
			return phase;
		};

		// Visibility: render only while on screen and the tab is visible.
		var visible = false;
		if ( 'IntersectionObserver' in window ) {
			new IntersectionObserver( function ( en ) {
				visible = en[ 0 ].isIntersecting;
				if ( visible ) { attach(); api.dirty = true; if ( introStart < 0 ) { introStart = api.t; } }
			}, { rootMargin: '200px' } ).observe( el );
			new IntersectionObserver( function ( en ) { if ( ! en[ 0 ].isIntersecting ) { detach(); } }, { rootMargin: '1200px' } ).observe( el );
		} else { visible = true; introStart = 0; attach(); }

		var last = performance.now();
		var frame = function ( now ) {
			// The gallery was removed (the builder's preview rebuilds its scene on every change): stop and free the GPU.
			if ( ! el.isConnected ) { detach(); return; }
			window.requestAnimationFrame( frame );
			var dt = Math.min( 0.05, ( now - last ) / 1000 ); last = now;
			if ( ! visible || document.hidden || ! renderer ) { return; }
			api.t += dt;
			if ( ! still ) {
				var target = ( api.hover >= 0 || api.focus >= 0 ) ? ( hoverMode === 'pause' ? 0 : ( hoverMode === 'slow' ? 0.25 : 1 ) ) : 1;
				if ( api.grabbed ) { target = 0; }
				pace += ( target - pace ) * Math.min( 1, dt * 4 );
				if ( drive !== 'scroll' && drive !== 'static' ) { phase += dt / loopS * pace; }
				if ( ! api.grabbed && Math.abs( api.dragV ) > 1e-4 ) { api.drag += api.dragV; api.dragV *= 0.94; }
				intro( api.t );
			} else if ( api.t < 0.05 ) { intro( 0 ); }
			if ( still && ! api.dirty ) { return; }
			design.frame( api, dt, api.t );
			renderer.render( scene, camera );
			api.dirty = false;
		};
		if ( still ) { intro( 0 ); }
		window.requestAnimationFrame( frame );
		if ( drive === 'scroll' ) { window.addEventListener( 'scroll', function () { api.dirty = true; }, { passive: true } ); }
	}

	/* ---------------------------------------------------------------- Mirror Ring */
	window.upwGallery3d.registerDesign( 'webgl_mirror_ring', function ( ctx ) {
		var reflect = ctx.num( 'data-tdg-reflect', 45 ) / 100;
		var ringSize = ctx.num( 'data-tdg-ring', 90 ) / 100;
		var camHeight = ctx.num( 'data-tdg-camh', 14 );
		var cardPct = ctx.num( 'data-tdg-card', 20 ) / 100;
		var floor = ctx.attr( 'data-tdg-floor', '' );
		var dir = ctx.num( 'data-tdg-dir', 1 );
		var ring, R, cw, ch, floorY;
		mount( ctx, {
			build: function ( api ) {
				var THREE = api.THREE, n = Math.max( 3, Math.min( 40, ctx.cards.length ) );
				cw = 1; ch = cw * api.aspect;
				// Radius so neighbours sit about a card apart, scaled by Ring Size.
				R = Math.max( 1.2, ( n * cw * 1.25 ) / ( 2 * Math.PI ) ) * ( 0.6 + ringSize * 0.6 );
				ring = new THREE.Group();
				api.scene.add( ring );
				api.makeCards( n, cw );
				floorY = -ch / 2 - 0.06;
				var fade = fadeTexture();
				api.mirrors = [];
				api.cards.forEach( function ( m, i ) {
					var a = i / n * Math.PI * 2;
					m.userData.a = a;
					ring.add( m );
					if ( reflect > 0 ) {
						var mm = new THREE.MeshBasicMaterial( { map: m.material.map, alphaMap: fade, transparent: true, opacity: reflect * 0.7, side: THREE.DoubleSide, depthWrite: false } );
						var mirror = new THREE.Mesh( m.geometry, mm );
						mirror.scale.y = -1;
						ring.add( mirror );
						api.mirrors.push( mirror );
					}
				} );
				// The floor: a soft disc of the floor colour, fading out at its edge.
				var cv = document.createElement( 'canvas' ); cv.width = cv.height = 256;
				var g = cv.getContext( '2d' ), gr = g.createRadialGradient( 128, 128, 10, 128, 128, 128 );
				var col = floor || 'rgba(120,130,150,1)';
				gr.addColorStop( 0, col ); gr.addColorStop( 1, 'rgba(0,0,0,0)' );
				g.globalAlpha = 0.28; g.fillStyle = gr; g.fillRect( 0, 0, 256, 256 );
				var disc = new THREE.Mesh( new THREE.PlaneGeometry( R * 3.2, R * 3.2 ), new THREE.MeshBasicMaterial( { map: new THREE.CanvasTexture( cv ), transparent: true, depthWrite: false } ) );
				disc.rotation.x = -Math.PI / 2;
				disc.position.y = floorY - 0.001;
				api.scene.add( disc );
			},
			resize: function ( api ) {
				// Frame the ring: a card spans about Card Size of the stage width at the front.
				var tanH = Math.tan( api.camera.fov * Math.PI / 360 );
				// Fit by width (a front card spans Card Size of the stage) AND by height (the card plus its
				// reflection must fit), so a short, wide stage pulls back instead of cropping.
				var dist = Math.max( ( cw / cardPct ) / ( 2 * tanH * api.camera.aspect ), ( ch * ( 1.9 + reflect * 0.6 ) ) / ( 2 * tanH ) ) + R;
				var ang = camHeight * Math.PI / 180;
				api.camera.position.set( 0, Math.sin( ang ) * dist, Math.cos( ang ) * dist );
				api.camera.lookAt( 0, floorY * 0.4, 0 );
			},
			frame: function ( api ) {
				var turn = api.progress() * Math.PI * 2 * dir + api.drag;
				if ( api.focus >= 0 ) { turn = -api.cards[ api.focus ].userData.a; }
				ring.rotation.y += ( turn - ring.rotation.y ) * ( api.focus >= 0 ? 0.08 : 1 );
				api.cards.forEach( function ( m, i ) {
					var a = m.userData.a, ap = EASE( m.userData.appear );
					var lift = ( api.hover === i || api.focus === i ) ? 0.08 : 0;
					m.position.set( Math.sin( a ) * R, ( 1 - ap ) * -0.4 + lift, Math.cos( a ) * R );
					m.rotation.y = a;
					m.material.opacity = ap;
					if ( api.mirrors && api.mirrors[ i ] ) {
						var mr = api.mirrors[ i ];
						mr.position.set( m.position.x, 2 * floorY - m.position.y, m.position.z );
						mr.rotation.y = a;
						mr.material.opacity = reflect * 0.7 * ap;
					}
				} );
			}
		} );
	} );

	/* ---------------------------------------------------------------- Depth Field */
	window.upwGallery3d.registerDesign( 'webgl_depth_field', function ( ctx ) {
		var count = Math.max( 4, Math.min( 60, ctx.num( 'data-tdg-count', 18 ) ) );
		var depth = ctx.num( 'data-tdg-depth', 60 ) / 100;
		var spread = ctx.num( 'data-tdg-spread', 100 ) / 100;
		var fogAmt = ctx.num( 'data-tdg-fog', 55 ) / 100;
		var parallax = ctx.num( 'data-tdg-parallax', 40 ) / 100;
		var fogCol = ctx.attr( 'data-tdg-fogcolor', '' );
		var dir = ctx.num( 'data-tdg-dir', 1 );
		var D, seeds = [], camX = 0, camY = 0;
		var rand = function ( i, k ) { var x = Math.sin( i * 127.1 + k * 311.7 ) * 43758.5453; return x - Math.floor( x ); };
		mount( ctx, {
			build: function ( api ) {
				var THREE = api.THREE;
				D = 6 + depth * 24;
				// Fog fades into the colour behind the gallery: the Fog Colour, else the Stage Background, else the
				// first solid background up the page (so a white page fades to white, a dark band to dark).
				var col = fogCol || window.getComputedStyle( ctx.el ).getPropertyValue( '--tdg-bg' ).trim();
				if ( ! col || col === 'transparent' ) {
					for ( var n = ctx.el; n && n.nodeType === 1; n = n.parentElement ) {
						var bg = window.getComputedStyle( n ).backgroundColor;
						if ( bg && ! /rgba\([^)]*,\s*0\)|transparent/.test( bg ) ) { col = bg; break; }
					}
				}
				col = col || '#ffffff';
				try { api.scene.fog = new THREE.Fog( new THREE.Color( col ), 2 + ( 1 - fogAmt ) * D * 0.6, D * ( 1.05 + ( 1 - fogAmt ) * 0.8 ) ); } catch ( e ) {}
				api.makeCards( count, 1.5 );
				api.cards.forEach( function ( m, i ) {
					// An evenly spread (low-discrepancy) layout rather than plain random, so the field fills the frame
					// at every depth instead of clumping: x / y step by the golden ratio, depth is stratified.
					var gx = ( ( i * 0.6180339887 + rand( i, 1 ) * 0.12 ) % 1 ), gy = ( ( i * 0.7548776662 + 0.37 + rand( i, 2 ) * 0.12 ) % 1 );
					seeds.push( { x: gx * 2 - 1, y: gy * 2 - 1, z: ( i + rand( i, 3 ) * 0.8 ) / count, r: ( rand( i, 4 ) - 0.5 ) * 0.5, b: rand( i, 5 ) * 6.28 } );
					api.scene.add( m );
				} );
			},
			resize: function ( api ) { api.camera.position.set( 0, 0, 3 ); api.camera.lookAt( 0, 0, -D / 2 ); },
			frame: function ( api, dt, t ) {
				var p = api.progress() * dir;
				var spanX = 2.6 * spread * Math.max( 1, api.camera.aspect ), spanY = 1.6 * spread;
				if ( api.pointer && parallax > 0 ) { camX += ( api.pointer.x * parallax * 0.9 - camX ) * 0.06; camY += ( api.pointer.y * parallax * 0.6 - camY ) * 0.06; }
				else { camX *= 0.96; camY *= 0.96; }
				api.camera.position.set( camX, camY, 3 );
				api.camera.lookAt( camX * 0.3, camY * 0.3, -D / 2 );
				api.cards.forEach( function ( m, i ) {
					var s = seeds[ i ], ap = EASE( m.userData.appear );
					var z = ( ( s.z + p ) % 1 + 1 ) % 1;                     // 0 = far, 1 = at the viewer
					var zz = -D + z * ( D + 1.2 );
					m.position.set( s.x * spanX * ( 0.5 + z * 0.6 ), s.y * spanY * ( 0.5 + z * 0.6 ) + Math.sin( t * 0.6 + s.b ) * 0.05, zz );
					m.rotation.set( 0, s.r, s.r * 0.3 );
					var near = Math.max( 0, Math.min( 1, ( z - 0.86 ) / 0.14 ) );   // fade out as it passes the viewer
					var hov = ( api.hover === i || api.focus === i ) ? 1.12 : 1;
					m.scale.setScalar( hov );
					m.material.opacity = ap * ( 1 - near ) * Math.min( 1, z * 6 );
					m.visible = m.material.opacity > 0.01;
				} );
			}
		} );
	} );

	/* ---------------------------------------------------------------- Wave Wall */
	window.upwGallery3d.registerDesign( 'webgl_wave_wall', function ( ctx ) {
		var cols = Math.max( 2, Math.min( 12, ctx.num( 'data-tdg-cols', 6 ) ) );
		var rows = Math.max( 1, Math.min( 8, ctx.num( 'data-tdg-rows', 3 ) ) );
		var amp = ctx.num( 'data-tdg-amp', 40 ) / 100;
		var wl = ctx.num( 'data-tdg-wavelength', 80 ) / 100;
		var view = ctx.num( 'data-tdg-view', 25 );
		var lightAng = ctx.num( 'data-tdg-lightang', -30 );
		var gloss = ctx.num( 'data-tdg-gloss', 40 ) / 100;
		var gap = ctx.num( 'data-tdg-gap', 8 ) / 100;
		var dir = ctx.num( 'data-tdg-dir', 1 );
		var cw = 1, ch, W, H;
		mount( ctx, {
			build: function ( api ) {
				api.roughness = 0.9 - gloss * 0.75;
				ch = cw * api.aspect;
				api.makeCards( cols * rows, cw );
				W = cols * cw * ( 1 + gap ); H = rows * ch * ( 1 + gap );
				api.cards.forEach( function ( m, i ) {
					m.material.metalness = gloss * 0.25;
					m.userData.c = i % cols; m.userData.r = Math.floor( i / cols );
					api.scene.add( m );
				} );
				var la = lightAng * Math.PI / 180;
				api.key.position.set( Math.sin( la ) * 8, 4, Math.cos( la ) * 8 );
			},
			resize: function ( api ) {
				var fit = Math.max( W / api.camera.aspect, H ) * 1.3;
				var dist = fit / ( 2 * Math.tan( api.camera.fov * Math.PI / 360 ) );
				var va = view * Math.PI / 180;
				api.camera.position.set( 0, -Math.sin( va ) * dist * 0.35, Math.cos( va ) * dist );
				api.camera.lookAt( 0, 0, 0 );
			},
			frame: function ( api ) {
				var ph = api.progress() * Math.PI * 2 * dir;
				var k = Math.PI * 2 / Math.max( 0.5, wl * 6 );
				var A = amp * 0.9;
				api.cards.forEach( function ( m, i ) {
					var x = ( m.userData.c - ( cols - 1 ) / 2 ) * cw * ( 1 + gap ), y = ( ( rows - 1 ) / 2 - m.userData.r ) * ch * ( 1 + gap );
					var z = A * Math.sin( x * k - ph ) * Math.cos( y * k * 0.7 + ph * 0.6 );
					var dzdx = A * k * Math.cos( x * k - ph ) * Math.cos( y * k * 0.7 + ph * 0.6 );
					var dzdy = -A * k * 0.7 * Math.sin( x * k - ph ) * Math.sin( y * k * 0.7 + ph * 0.6 );
					var ap = EASE( m.userData.appear );
					var lift = ( api.hover === i || api.focus === i ) ? 0.35 : 0;
					m.position.set( x, y, z + lift - ( 1 - ap ) * 1.5 );
					m.rotation.set( Math.atan( dzdy ), -Math.atan( dzdx ), 0 );
					m.material.opacity = ap;
				} );
			}
		} );
	} );
} )();
