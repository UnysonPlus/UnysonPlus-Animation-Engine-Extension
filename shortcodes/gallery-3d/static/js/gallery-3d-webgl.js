/*!
 * Unyson+ Motion Gallery — WebGL designs (real 3D: depth, lighting, reflections).
 *
 * Designs drawn with Three.js (the copy the Animation Engine already ships for WebGL Object), plugged
 * into the Motion Gallery through window.upwGallery3d.registerDesign, so they share its options, motion
 * modes (auto / scroll-scrub / static), lightbox and links:
 *   - webgl_mirror_ring  photos on a turning ring above a glossy floor that reflects them, lit by real lights
 *   - webgl_depth_field  photos floating at true depth in fog, drifting toward the viewer; the camera follows the pointer
 *   - webgl_wave_wall    a lit grid of photos on a rippling surface that catches the light as it moves
 *   - webgl_liquid_slider one photo at a time; the next flows in through a shader (liquid, ripple, slices, swirl)
 *   - webgl_particle_morph each photo drawn as thousands of particles that scatter and gather into the next
 *   - webgl_flip_book    a 3D book whose pages bend and catch the light as they turn
 *   - webgl_shatter      the photo breaks into lit shards that fly off, revealing the next
 *   - webgl_cloth_banners photos on fabric banners hanging from a rod, rippling in the wind
 *   - webgl_fold_strip   an accordion-folded strip of photos flowing sideways; unfolds on hover
 *   - webgl_water_pool   photos at the bottom of a pool; the pointer sends ripples that bend them and catch the light
 *   - webgl_jelly_grid   a lit grid of photos that bulge and wobble like jelly under the pointer
 *   - webgl_stage_curtain one photo at a time behind a theatre curtain that closes and opens for each
 *   - webgl_gallery_walk a walk down a gallery corridor, the photos framed on both walls
 *   - webgl_holo_cards   holographic trading cards that tilt toward the pointer, foil shifting colour
 *   - webgl_domino_row   photos standing as dominoes that topple in turn and stand back up
 *   - webgl_photo_bubbles photos inside floating soap bubbles with iridescent rims
 *   - webgl_photo_cube   one photo at a time on a lit cube that turns a quarter-turn for the next
 *   - webgl_film_ribbon  the photos on a strip of film flowing and twisting through space
 *   - webgl_pixel_blocks one photo built from 3D blocks that flip over in a wave to the next
 *   - webgl_ferris_wheel the photos ride in gondolas on a turning, lit Ferris wheel
 *   - webgl_photo_mobile the photos hang from a balanced mobile that turns and stirs in the pointer's breeze
 *   - webgl_photo_fall   the photos drift down, tumbling like leaves; the pointer blows them about
 *   - webgl_lenticular   a ridged lenticular card: one photo from one side, the next from the other, flipping as it tilts
 *   - webgl_jigsaw       one photo at a time; the next one's jigsaw pieces fly in and snap together
 *   - webgl_paper_fold   one photo at a time; it folds into a small note that unfolds as the next
 *   - webgl_slot_reels   the photo split across spinning reels that stop one by one on the next
 *   - webgl_peel         one photo at a time; it peels off from a corner like a sticker, revealing the next
 *   - webgl_bookshelf    the photos are book covers on a shelf; one at a time a book slides out and turns to show it
 *   - webgl_snow_globe   one photo at a time inside a snow globe that shakes and swirls its snow for each change
 *   - webgl_tv_wall      a stacked wall of retro TVs, each flickering through static to its next photo
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

	// Checked once per page, and the test context released at once: each check used to hold a live WebGL context,
	// so a page with many galleries ran past the browser's limit before any of them drew a frame.
	var webglSupport = null;
	function webglOK() {
		if ( ! window.THREE ) { return false; }
		if ( webglSupport !== null ) { return webglSupport; }
		try {
			var c = document.createElement( 'canvas' ), gl = c.getContext( 'webgl2' ) || c.getContext( 'webgl' );
			webglSupport = !! gl;
			var lose = gl && gl.getExtension( 'WEBGL_lose_context' );
			if ( lose ) { lose.loseContext(); }
		} catch ( e ) { webglSupport = false; }
		return webglSupport;
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
	/* ================================================================ One photo at a time
	 * Shared by Liquid Slider, Particle Morph and Flip Book: the full-size photo source, a photo loader that
	 * keeps a placeholder until the image arrives, and the step timer (hold, then move for Move Time of the
	 * step; swipe and keyboard focus jump to a photo). */

	/** The best source for a photo shown at stage size: the card's full image (data-tdg-full), else its image. */
	function fullSrc( card ) {
		var f = card.getAttribute( 'data-tdg-full' );
		if ( f ) { return f; }
		var im = card.querySelector( 'img' );
		return im ? ( im.currentSrc || im.getAttribute( 'src' ) || '' ) : '';
	}

	/** A photo as something drawable: { el: canvas | img, aspect: w / h }. Until it loads (and in the builder's
	 *  preview, which has no photos) it is a placeholder: a coloured gradient, or dark grey for a real photo. */
	function loadPhoto( src, hue, cb ) {
		var ph = document.createElement( 'canvas' ); ph.width = 320; ph.height = 200;
		var g = ph.getContext( '2d' );
		if ( src ) { g.fillStyle = '#2a2e36'; g.fillRect( 0, 0, 320, 200 ); }
		else {
			var gr = g.createLinearGradient( 0, 0, 320, 200 );
			gr.addColorStop( 0, 'hsl(' + hue + ',62%,62%)' ); gr.addColorStop( 1, 'hsl(' + ( ( hue + 40 ) % 360 ) + ',58%,36%)' );
			g.fillStyle = gr; g.fillRect( 0, 0, 320, 200 );
			g.fillStyle = 'rgba(255,255,255,.3)'; g.beginPath(); g.arc( 226, 78, 36, 0, 6.2832 ); g.fill();
			g.fillStyle = 'rgba(0,0,0,.18)'; g.beginPath(); g.moveTo( 0, 200 ); g.lineTo( 110, 96 ); g.lineTo( 200, 170 ); g.lineTo( 250, 130 ); g.lineTo( 320, 190 ); g.lineTo( 320, 200 ); g.fill();
		}
		var p = { el: ph, aspect: 1.6 };
		if ( src ) {
			var img = new Image();
			img.crossOrigin = 'anonymous';
			img.decoding = 'async';
			img.onload = function () { p.el = img; p.aspect = img.naturalWidth / Math.max( 1, img.naturalHeight ); if ( cb ) { cb( p ); } };
			img.src = src;
		}
		return p;
	}

	/**
	 * Put a loaded photo into a texture that already holds its placeholder. The texture is freed first: on
	 * WebGL 2 its storage was sized for the small placeholder, and updating it in place with a larger photo
	 * fails ("Offset overflows texture dimensions"), leaving the placeholder showing. Freeing it makes the
	 * next draw create it again at the photo's own size.
	 */
	function swapImage( tex, el ) { tex.dispose(); tex.image = el; tex.needsUpdate = true; }

	function mod( i, n ) { return ( ( i % n ) + n ) % n; }

	/**
	 * The step timer. Position p shows step floor(p); each step holds, then spends Move Time of it moving to
	 * the next. Scroll-scrub walks through `steps` positions across the scroll; Static holds the first.
	 * state() → { a, b, e }: showing step a, moving toward step b by e (0..1, eased). go(to) animates a jump
	 * (swipe, keyboard focus); `period` is how many steps make one full cycle (for the nearest way round).
	 */
	function stepper( api, ctx, steps, mt, period ) {
		var drive = ctx.attr( 'data-tdg-drive', 'continuous' ), rounds = ctx.num( 'data-tdg-rounds', 1 ), dir = ctx.num( 'data-tdg-dir', 1 );
		var still = !! ( ctx.reduce || ctx.IN_EDITOR ), manual = 0, jump = null;
		var base = function () {
			if ( drive === 'scroll' ) { return ctx.progress() * rounds * Math.max( 1, steps - 1 ); }
			if ( drive === 'static' ) { return 0; }
			return api.progress() * dir;
		};
		var st = {
			state: function ( t ) {
				if ( jump ) {
					var q = Math.min( 1, ( t - jump.t0 ) / jump.d );
					if ( q < 1 ) { return { a: jump.from, b: jump.to, e: EASE( q ) }; }
					manual = jump.to - base(); jump = null;
				}
				var pos = base() + manual, i = Math.floor( pos ), l = pos - i;
				return { a: i, b: i + 1, e: EASE( Math.max( 0, Math.min( 1, ( l - ( 1 - mt ) ) / mt ) ) ) };
			},
			rest: function () { var s = st.state( api.t ); return s.e > 0.5 ? s.b : s.a; },
			/** Jump to the step nearest `target` (mod period) — or `to` exactly when `exact`. */
			go: function ( target, exact ) {
				var from = st.rest(), to = target;
				if ( ! exact ) { var d = mod( target - from, period ); if ( d > period / 2 ) { d -= period; } to = from + d; }
				if ( to === from ) { return; }
				if ( still ) { manual = to - base(); api.dirty = true; return; }
				jump = { from: from, to: to, t0: api.t, d: 0.9 };
				api.dirty = true;
			}
		};
		// Swipe left / right on the stage for the next / previous photo (not while the scroll drives it).
		if ( ctx.attr( 'data-tdg-swipe', '1' ) === '1' && drive !== 'scroll' ) {
			var x0 = null, y0 = 0;
			ctx.stage.addEventListener( 'pointerdown', function ( e ) { x0 = e.clientX; y0 = e.clientY; } );
			ctx.stage.addEventListener( 'pointerup', function ( e ) {
				if ( x0 === null ) { return; }
				var dx = e.clientX - x0, dy = e.clientY - y0; x0 = null;
				if ( Math.abs( dx ) > 40 && Math.abs( dx ) > Math.abs( dy ) ) { st.go( st.rest() + ( dx < 0 ? 1 : -1 ), true ); }
			} );
			ctx.stage.style.cursor = 'grab';
		}
		return st;
	}

	/* ---------------------------------------------------------------- Liquid Slider */
	var SLIDER_FS = [
		'uniform sampler2D tA; uniform sampler2D tB;',
		'uniform float aA, aB, pa, p, str, mode, time, ptrAmt, rad, zA, zB, op;',
		'uniform vec2 ptr; uniform vec2 res;',
		'varying vec2 vUv;',
		'float h1( vec2 q ) { return fract( sin( dot( q, vec2( 127.1, 311.7 ) ) ) * 43758.5453 ); }',
		'float n2( vec2 q ) { vec2 i = floor( q ), f = fract( q ); f = f * f * ( 3.0 - 2.0 * f );',
		'  return mix( mix( h1( i ), h1( i + vec2( 1.0, 0.0 ) ), f.x ), mix( h1( i + vec2( 0.0, 1.0 ) ), h1( i + vec2( 1.0, 1.0 ) ), f.x ), f.y ); }',
		'float fbm( vec2 q ) { float v = 0.0, a = 0.5; for ( int i = 0; i < 4; i++ ) { v += a * n2( q ); q *= 2.03; a *= 0.5; } return v; }',
		// Cover-fit: the photo fills the stage, cropped, never stretched; z zooms in.
		'vec2 cover( vec2 uv, float ia, float z ) { vec2 s = pa > ia ? vec2( 1.0, ia / pa ) : vec2( pa / ia, 1.0 ); return ( uv - 0.5 ) * s / z + 0.5; }',
		'void main() {',
		'  vec2 uv = vUv;',
		// A soft ripple around the pointer.
		'  if ( ptrAmt > 0.0 ) { vec2 d = ( uv - ptr ) * vec2( pa, 1.0 ); float r = length( d );',
		'    uv += ( r > 1e-4 ? d / r : vec2( 0.0 ) ) * vec2( 1.0 / pa, 1.0 ) * sin( r * 38.0 - time * 5.0 ) * 0.006 * ptrAmt * exp( -r * 5.0 ); }',
		'  vec2 ua = uv, ub = uv; float m; float e = p; float tp = -9.0, te = 0.0, bn = -9.0, bt = 0.0, shB = 0.0; vec3 shC = vec3( 0.0 );',
		'  if ( mode < 0.5 ) {',                                   // liquid: the next photo flows in along a noise front
		'    float n = clamp( ( fbm( uv * 3.0 + vec2( time * 0.03, 0.0 ) ) - 0.15 ) / 0.7, 0.0, 1.0 );',
		'    m = smoothstep( n - 0.12, n + 0.12, e * 1.24 - 0.12 );',
		'    ua = uv + ( n - 0.5 ) * str * 0.35 * e; ub = uv - ( n - 0.5 ) * str * 0.35 * ( 1.0 - e );',
		'  } else if ( mode < 1.5 ) {',                            // ripple: a ring of waves opens from the centre
		'    vec2 c = ( uv - 0.5 ) * vec2( pa, 1.0 ); float d = length( c ) / ( 0.5 * length( vec2( pa, 1.0 ) ) );',
		'    float r = e * 1.2 - 0.1; m = 1.0 - smoothstep( r - 0.08, r + 0.08, d );',
		'    vec2 dirv = length( c ) > 1e-4 ? normalize( c ) * vec2( 1.0 / pa, 1.0 ) : vec2( 0.0 );',
		'    float w = sin( d * 30.0 - e * 25.0 ) * str * 0.04 * sin( e * 3.14159 );',
		'    ua = uv + dirv * w; ub = uv + dirv * w;',
		'  } else if ( mode < 2.5 ) {',                            // slices: strips slide in one after another
		'    float idx = floor( uv.y * 9.0 ); float lp = clamp( ( e - idx / 9.0 * 0.4 ) / 0.6, 0.0, 1.0 ); lp = lp * lp * ( 3.0 - 2.0 * lp );',
		'    m = step( 1.0 - lp, uv.x ); ub.x = uv.x - ( 1.0 - lp ); ua.x = uv.x + lp * 0.3 * str;',
		'  } else if ( mode < 3.5 ) {',                             // swirl: the photo twists into the next
		'    vec2 c = ( uv - 0.5 ) * vec2( pa, 1.0 ); float r = length( c );',
		'    float ang = max( 0.0, 1.0 - r * 1.4 ) * str * 6.0 * sin( e * 3.14159 ); float cs = cos( ang ), sn = sin( ang );',
		'    c = vec2( c.x * cs - c.y * sn, c.x * sn + c.y * cs ); ua = c * vec2( 1.0 / pa, 1.0 ) + 0.5; ub = ua;',
		'    m = smoothstep( 0.35, 0.65, e );',
		'  } else if ( mode < 4.5 ) {',                             // pixelate: blocks grow, swap, then sharpen
		'    float bs = 0.002 + sin( e * 3.14159 ) * ( 0.02 + str * 0.08 ); vec2 bq = vec2( bs / pa, bs );',
		'    ua = ( floor( uv / bq ) + 0.5 ) * bq; ub = ua; m = smoothstep( 0.45, 0.55, e );',
		'  } else if ( mode < 5.5 ) {',                             // glitch: bands jump sideways while it swaps
		'    float tk = floor( time * 14.0 ); float band = floor( uv.y * 22.0 );',
		'    float jump = ( h1( vec2( band, tk ) ) - 0.5 ) * str * 0.35 * sin( e * 3.14159 ) * step( 0.45, h1( vec2( band, tk + 7.0 ) ) );',
		'    ua.x += jump; ub.x += jump; m = smoothstep( 0.4, 0.6, e + ( h1( vec2( band, 3.0 ) ) - 0.5 ) * 0.5 * sin( e * 3.14159 ) );',
		'  } else if ( mode < 6.5 ) {',                             // kaleidoscope: the photo folds into mirrored segments and out again
		'    vec2 c = ( uv - 0.5 ) * vec2( pa, 1.0 ); float r = length( c ), a = atan( c.y, c.x ), seg = 6.2832 / 6.0;',
		'    float ak = abs( mod( a + e * 3.14159, seg ) - seg * 0.5 ); vec2 kuv = vec2( cos( ak ), sin( ak ) ) * r * vec2( 1.0 / pa, 1.0 ) + 0.5;',
		'    ua = mix( uv, kuv, sin( e * 3.14159 ) * ( 0.4 + str * 0.6 ) ); ub = ua; m = smoothstep( 0.4, 0.6, e );',
		'  } else if ( mode < 7.5 ) {',                             // torn paper: the photo is torn away from the right
		'    tp = uv.x + ( fbm( vec2( uv.y * 7.0, 1.7 ) ) - 0.5 ) * 0.1 * ( 0.4 + str ); te = 1.15 - e * 1.3;',
		'    m = smoothstep( te - 0.002, te + 0.002, tp );',
		'  } else if ( mode < 8.5 ) {',                             // burn: the photo burns away along a glowing edge
		'    bn = fbm( uv * 4.0 + 3.1 ); bt = e * 1.35 - 0.2; m = 1.0 - smoothstep( bt - 0.004, bt + 0.004, bn );',
		'  } else if ( mode < 9.5 ) {',                             // zoom blur: rush in, swap, settle
		'    m = smoothstep( 0.4, 0.6, e );',
		'  } else if ( mode < 10.5 ) {',                            // mosaic: squares switch over in random order
		'    vec2 cell = floor( uv * vec2( 14.0 * pa, 14.0 ) ); float dl = h1( cell ) * 0.7; m = clamp( ( e - dl ) / 0.3, 0.0, 1.0 );',
		'  } else if ( mode < 11.5 ) {',                            // ink: the next photo spreads in like ink, with a dark edge
		'    bn = fbm( uv * 2.4 + 7.3 ) * 0.7 + length( ( uv - 0.5 ) * vec2( pa, 1.0 ) ) * 0.45; bt = e * 1.45 - 0.2;',
		'    m = 1.0 - smoothstep( bt - 0.006, bt + 0.006, bn );',
		'  } else if ( mode < 12.5 ) {',                            // spiral: the next photo winds in from the centre
		'    vec2 c = ( uv - 0.5 ) * vec2( pa, 1.0 ); float r = length( c ) / ( 0.5 * length( vec2( pa, 1.0 ) ) );',
		'    float sv = fract( atan( c.y, c.x ) / 6.2832 + 0.5 + r * 1.2 ) * 0.35 + r * 0.75, th = e * 1.25 - 0.1;',
		'    m = 1.0 - smoothstep( th - 0.015, th + 0.015, sv );',
		'  } else if ( mode < 13.5 ) {',                            // light leak: a warm flare washes over as it changes
		'    m = smoothstep( 0.35, 0.65, e );',
		'  } else if ( mode < 14.5 ) {',                            // stretch: slides across, stretched and smeared by its speed
		'    float sx = 1.0 + sin( e * 3.14159 ) * str * 1.6; m = step( 1.0 - e, uv.x );',
		'    ua.x = 1.0 + ( uv.x - ( 1.0 - e ) ) / sx; ub.x = ( uv.x - ( 1.0 - e ) ) / sx;',
		'  } else if ( mode < 15.5 ) {',                            // halftone: the next photo comes in as growing dots, sweeping across
		'    vec2 g = vec2( uv.x * pa, uv.y ) / ( 0.028 + str * 0.04 ); g = vec2( g.x + g.y, g.y - g.x ) * 0.7071;',
		'    float sw = clamp( e * 1.7 - ( uv.x * 0.5 + ( 1.0 - uv.y ) * 0.2 ), 0.0, 1.0 ), rr = sw * 0.82;',
		'    m = 1.0 - smoothstep( rr - 0.06, rr + 0.02, length( fract( g ) - 0.5 ) );',
		'  } else if ( mode < 16.5 ) {',                            // pinch: the photo is drawn into the centre and out again as the next
		'    vec2 c = ( uv - 0.5 ) * vec2( pa, 1.0 ); float r = length( c ), k = sin( e * 3.14159 ) * ( 0.6 + str * 2.4 );',
		'    float rn = pow( r / 0.8, 1.0 + k ) * 0.8; ua = ( r > 1e-4 ? c / r * rn : c ) * vec2( 1.0 / pa, 1.0 ) + 0.5; ub = ua;',
		'    m = smoothstep( 0.4, 0.6, e );',
		'  } else if ( mode < 17.5 ) {',                            // wind: the photo streaks away row by row, the next blowing in behind
		'    float row = h1( vec2( floor( uv.y * 90.0 ), 4.0 ) ), fp = e * 1.6 - row * 0.45 - 0.1;',
		'    m = 1.0 - smoothstep( fp - 0.02, fp + 0.02, uv.x );',
		'    ua.x = uv.x - ( 1.0 - smoothstep( 0.0, 0.35, uv.x - fp ) ) * ( 0.06 + str * 0.25 ) * step( 0.0, e );',
		'    ub.x = uv.x + ( 1.0 - smoothstep( 0.0, 0.25, fp - uv.x ) ) * ( 0.03 + str * 0.1 ) * ( 1.0 - e );',
		'  } else if ( mode < 18.5 ) {',                            // focus: the photo blurs out of focus, swaps, and sharpens again
		'    m = smoothstep( 0.42, 0.58, e );',
		'  } else {',                                              // shutter: camera aperture blades close and open on the next photo
		'    vec2 c = ( uv - 0.5 ) * vec2( pa, 1.0 ); float rot = e * 1.6, dp = 0.0;',
		'    for ( int k = 0; k < 6; k++ ) { float an = rot + float( k ) * 1.0472; dp = max( dp, dot( c, vec2( cos( an ), sin( an ) ) ) ); }',
		'    float ap = abs( 1.0 - 2.0 * e ) * 0.5 * length( vec2( pa, 1.0 ) ) * 1.18;',
		'    m = step( 0.5, e ); shB = smoothstep( ap - 0.004, ap + 0.004, dp );',
		'    float sect = fract( ( atan( c.y, c.x ) - rot ) / 1.0472 );',
		'    shC = vec3( 0.07, 0.075, 0.09 ) * ( 0.75 + 0.6 * sect ) + vec3( 0.35 ) * smoothstep( 0.012, 0.0, abs( dp - ap ) ) * step( 0.001, ap );',
		'  }',
		'  vec4 ca = texture2D( tA, cover( ua, aA, zA ) ), cb = texture2D( tB, cover( ub, aB, zB ) );',
		'  if ( mode > 8.5 && mode < 9.5 ) { vec4 sa = vec4( 0.0 ), sb = vec4( 0.0 ); float bl = sin( e * 3.14159 ) * 0.22 * ( 0.4 + str );',
		'    for ( int i = 0; i < 10; i++ ) { float f = 1.0 - bl * float( i ) / 10.0;',
		'      sa += texture2D( tA, cover( ( uv - 0.5 ) * f / ( 1.0 + e * 0.35 ) + 0.5, aA, zA ) ); sb += texture2D( tB, cover( ( uv - 0.5 ) * f * ( 1.0 - ( 1.0 - e ) * 0.25 ) + 0.5, aB, zB ) ); }',
		'    ca = sa / 10.0; cb = sb / 10.0; }',
		'  if ( mode > 13.5 && mode < 14.5 ) { vec4 sa = vec4( 0.0 ), sb = vec4( 0.0 ); float bl = sin( e * 3.14159 ) * 0.07 * ( 0.4 + str );',
		'    for ( int i = 0; i < 10; i++ ) { vec2 f = vec2( ( float( i ) / 9.0 - 0.5 ) * bl, 0.0 );',
		'      sa += texture2D( tA, cover( ua + f, aA, zA ) ); sb += texture2D( tB, cover( ub + f, aB, zB ) ); }',
		'    ca = sa / 10.0; cb = sb / 10.0; }',
		'  if ( mode > 17.5 && mode < 18.5 ) { vec4 sa = vec4( 0.0 ), sb = vec4( 0.0 ); float br = sin( e * 3.14159 ) * ( 0.006 + str * 0.03 );',
		'    for ( int i = 0; i < 12; i++ ) { float an = float( i ) * 0.5236; vec2 o = vec2( cos( an ) / pa, sin( an ) ) * br;',
		'      sa += texture2D( tA, cover( ua + o, aA, zA ) ) + texture2D( tA, cover( ua + o * 0.5, aA, zA ) ); sb += texture2D( tB, cover( ub + o, aB, zB ) ) + texture2D( tB, cover( ub + o * 0.5, aB, zB ) ); }',
		'    ca = sa / 24.0; cb = sb / 24.0; }',
		'  vec3 col = mix( ca.rgb, cb.rgb, m );',
		'  col = mix( col, shC, shB );',
		'  if ( tp > -8.0 && e > 0.0 && e < 1.0 ) { float dd2 = tp - te;',
		'    col *= 1.0 - 0.35 * smoothstep( -0.06, 0.0, dd2 ) * ( 1.0 - m );',
		'    col = mix( col, vec3( 0.96, 0.95, 0.92 ), smoothstep( 0.014, 0.0, abs( dd2 + 0.006 ) ) * 0.95 ); }',
		'  if ( bn > -8.0 && e > 0.0 && e < 1.0 && mode < 8.5 ) { float bd = bn - bt;',
		'    col *= 1.0 - 0.75 * smoothstep( 0.09, 0.02, bd ) * step( 0.0, bd );',
		'    col += vec3( 1.0, 0.48, 0.12 ) * ( 1.0 - smoothstep( 0.0, 0.035, abs( bd ) ) ) * 1.6; }',
		'  if ( mode > 12.5 && mode < 13.5 ) { vec2 lc = uv - vec2( 0.1 + e * 0.8, 0.72 - e * 0.2 );',
		'    col += vec3( 1.0, 0.6, 0.28 ) * sin( e * 3.14159 ) * ( 0.45 + str * 0.6 ) * exp( -dot( lc * vec2( pa * 0.8, 1.0 ), lc * vec2( pa * 0.8, 1.0 ) ) * 2.2 ); }',
		'  if ( bn > -8.0 && e > 0.0 && e < 1.0 && mode > 10.5 ) { float bd = bn - bt;',
		'    col = mix( col, vec3( 0.05, 0.06, 0.08 ), ( 1.0 - smoothstep( 0.0, 0.028, abs( bd + 0.004 ) ) ) * 0.88 ); }',
		// Rounded corners (Corner Radius), anti-aliased.
		'  vec2 px = vUv * res; vec2 q = abs( px - res * 0.5 ) - ( res * 0.5 - rad ); float dd = length( max( q, 0.0 ) ) - rad;',
		'  gl_FragColor = vec4( col, clamp( 0.5 - dd, 0.0, 1.0 ) * op );',
		'}'
	].join( '\n' );
	var PLAIN_VS = 'varying vec2 vUv; void main() { vUv = uv; gl_Position = projectionMatrix * modelViewMatrix * vec4( position, 1.0 ); }';

	window.upwGallery3d.registerDesign( 'webgl_liquid_slider', function ( ctx ) {
		if ( ! ctx.cards.length ) { return; }
		var effect = { liquid: 0, ripple: 1, slices: 2, swirl: 3, pixelate: 4, glitch: 5, kaleido: 6, tear: 7, burn: 8, zoom: 9, mosaic: 10, ink: 11, spiral: 12, leak: 13, stretch: 14, halftone: 15, pinch: 16, wind: 17, focus: 18, shutter: 19 }[ ctx.attr( 'data-tdg-effect', 'liquid' ) ] || 0;
		var strength = ctx.num( 'data-tdg-strength', 50 ) / 100;
		var mt = Math.max( 0.1, Math.min( 0.9, ctx.num( 'data-tdg-movetime', 35 ) / 100 ) );
		var ptrAmt = ctx.num( 'data-tdg-ptr', 30 ) / 100, zoom = ctx.num( 'data-tdg-zoom', 6 ) / 100;
		var n = ctx.cards.length, photos = [], texs = [], plane, u, st, ptrOn = 0;
		mount( ctx, {
			build: function ( api ) {
				var THREE = api.THREE;
				ctx.cards.forEach( function ( c, k ) {
					var tex = new THREE.Texture();
					tex.generateMipmaps = false; tex.minFilter = THREE.LinearFilter; tex.magFilter = THREE.LinearFilter;
					var p = loadPhoto( fullSrc( c ), Math.round( k * 360 / n ), function ( pp ) { swapImage( tex, pp.el ); api.dirty = true; } );
					tex.image = p.el; tex.needsUpdate = true;
					photos.push( p ); texs.push( tex );
				} );
				u = {
					tA: { value: texs[ 0 ] }, tB: { value: texs[ 0 ] }, aA: { value: 1.6 }, aB: { value: 1.6 }, pa: { value: 1.6 },
					p: { value: 0 }, str: { value: strength }, mode: { value: effect }, time: { value: 0 },
					ptr: { value: new THREE.Vector2( 0.5, 0.5 ) }, ptrAmt: { value: 0 }, res: { value: new THREE.Vector2( 800, 500 ) },
					rad: { value: radiusOf( ctx.el ) }, zA: { value: 1 }, zB: { value: 1 }, op: { value: 1 }
				};
				// Raw colours in and out (no tone mapping), so the photo shows exactly as uploaded.
				plane = new THREE.Mesh( new THREE.PlaneGeometry( 1, 1 ), new THREE.ShaderMaterial( { uniforms: u, vertexShader: PLAIN_VS, fragmentShader: SLIDER_FS, transparent: true, depthWrite: false, toneMapped: false } ) );
				plane.userData.card = 0; plane.userData.appear = 0;
				api.cards.push( plane ); api.scene.add( plane );
				st = stepper( api, ctx, n, mt, n );
				ctx.cards.forEach( function ( c, k ) { c.addEventListener( 'focusin', function () { st.go( k ); } ); } );
			},
			resize: function ( api ) {
				api.camera.position.set( 0, 0, 5 ); api.camera.lookAt( 0, 0, 0 );
				var h = 2 * 5 * Math.tan( api.camera.fov * Math.PI / 360 );
				plane.scale.set( h * api.camera.aspect, h, 1 );
				u.res.value.set( api.w, api.h ); u.pa.value = api.w / Math.max( 1, api.h );
			},
			frame: function ( api, dt, t ) {
				var s = st.state( t ), a = mod( s.a, n ), b = mod( s.b, n );
				u.tA.value = texs[ a ]; u.tB.value = texs[ b ]; u.aA.value = photos[ a ].aspect; u.aB.value = photos[ b ].aspect;
				u.p.value = s.e; u.time.value = t;
				// A slow zoom on each photo (Slow Zoom), each on its own rhythm.
				u.zA.value = 1 + zoom * ( 0.5 + 0.5 * Math.sin( t * 0.16 + a * 1.7 ) );
				u.zB.value = 1 + zoom * ( 0.5 + 0.5 * Math.sin( t * 0.16 + b * 1.7 ) );
				ptrOn += ( ( api.pointer ? 1 : 0 ) - ptrOn ) * Math.min( 1, dt * 4 );
				if ( api.pointer ) { u.ptr.value.set( ( api.pointer.x + 1 ) / 2, ( api.pointer.y + 1 ) / 2 ); }
				u.ptrAmt.value = ptrAmt * ptrOn;
				plane.userData.card = s.e > 0.5 ? b : a;
				u.op.value = EASE( plane.userData.appear );
			}
		} );
	} );

	/* ---------------------------------------------------------------- Particle Morph */
	var MORPH_VS = [
		'attribute vec3 cA; attribute vec3 cB; attribute vec4 rnd;',
		'uniform float p, time, scatter, mode, psize, ptrAmt, op, spanW, spanH;',
		'uniform vec2 ptr;',
		'varying vec3 vCol; varying float vA;',
		'void main() {',
		'  vec3 pos = position; float r = rnd.w;',
		'  float q = clamp( p * 1.6 - r * 0.6, 0.0, 1.0 ); float bell = sin( q * 3.14159 );',
		'  vec3 off;',
		'  if ( mode < 0.5 ) { off = rnd.xyz * vec3( spanW, spanH, 6.0 ) * scatter * bell; }',                 // explode
		'  else if ( mode < 1.5 ) { float ang = bell * scatter * 4.0 * ( 0.6 + r ); float c = cos( ang ), s = sin( ang );',   // swirl
		'    off = vec3( pos.x * c - pos.y * s - pos.x, pos.x * s + pos.y * c - pos.y, rnd.z * bell * scatter * 3.0 ); }',
		'  else if ( mode < 2.5 ) { off = vec3( bell * scatter * spanW * ( 0.6 + r ), sin( pos.x * 3.0 + time + r * 6.28 ) * bell * scatter * 0.4, rnd.z * bell * scatter * 2.0 ); }', // drift
		'  else { off = vec3( rnd.x * bell * scatter * 0.3, -bell * scatter * spanH * ( 0.5 + r * 1.2 ), 0.0 ); }', // rain
		'  pos += off;',
		'  pos.z += sin( pos.x * 2.0 + time * 1.2 + r * 6.28 ) * 0.03;',
		'  vec2 d = pos.xy - ptr; float dl = length( d ); float push = ptrAmt * exp( -dl * dl * 4.0 );',
		'  pos.xy += ( dl > 1e-4 ? d / dl : vec2( 0.0 ) ) * push * 0.35; pos.z += push * 0.6;',
		'  vCol = mix( cA, cB, smoothstep( 0.35, 0.65, q ) ); vA = op;',
		'  vec4 mv = modelViewMatrix * vec4( pos, 1.0 );',
		'  gl_Position = projectionMatrix * mv;',
		'  gl_PointSize = psize * ( 5.0 / -mv.z ) * ( 1.0 + bell * 0.4 );',
		'}'
	].join( '\n' );
	var MORPH_FS = [
		'varying vec3 vCol; varying float vA;',
		'void main() { vec2 c = gl_PointCoord - 0.5; float d = dot( c, c ); if ( d > 0.25 ) { discard; }',
		'  gl_FragColor = vec4( vCol, vA * smoothstep( 0.25, 0.16, d ) ); }'
	].join( '\n' );

	window.upwGallery3d.registerDesign( 'webgl_particle_morph', function ( ctx ) {
		if ( ! ctx.cards.length ) { return; }
		var effect = { explode: 0, swirl: 1, drift: 2, rain: 3 }[ ctx.attr( 'data-tdg-effect', 'explode' ) ] || 0;
		var density = Math.max( 40, Math.min( 240, ctx.num( 'data-tdg-density', 140 ) ) );
		var psize = ctx.num( 'data-tdg-psize', 90 ) / 100, scatter = ctx.num( 'data-tdg-scatter', 50 ) / 100;
		var mt = Math.max( 0.1, Math.min( 0.9, ctx.num( 'data-tdg-movetime', 50 ) / 100 ) ), repel = ctx.num( 'data-tdg-repel', 40 ) / 100;
		var n = ctx.cards.length, photos = [], cache = {}, pts = null, u, hit, st, cols = 0, rows = 0, halfW = 1, halfH = 1, lastA = -1, lastB = -1, ptrOn = 0;
		// Each photo sampled at the particle grid (cover-fit), cached per grid size.
		var colours = function ( k ) {
			var key = k + ':' + cols + 'x' + rows;
			if ( cache[ key ] ) { return cache[ key ]; }
			var cv = document.createElement( 'canvas' ); cv.width = cols; cv.height = rows;
			var g = cv.getContext( '2d' ), src = photos[ k ].el, sw = src.naturalWidth || src.width, sh = src.naturalHeight || src.height;
			var s = Math.max( cols / sw, rows / sh );
			g.drawImage( src, ( cols - sw * s ) / 2, ( rows - sh * s ) / 2, sw * s, sh * s );
			var out = new Float32Array( cols * rows * 3 ), d;
			try { d = g.getImageData( 0, 0, cols, rows ).data; } catch ( e ) { d = null; }   // a photo from another site without CORS
			for ( var y = 0; y < rows; y++ ) {
				for ( var x = 0; x < cols; x++ ) {
					var i = y * cols + x, j = ( ( rows - 1 - y ) * cols + x ) * 4;   // canvas rows run top-down, the grid bottom-up
					out[ i * 3 ] = d ? d[ j ] / 255 : 0.6; out[ i * 3 + 1 ] = d ? d[ j + 1 ] / 255 : 0.64; out[ i * 3 + 2 ] = d ? d[ j + 2 ] / 255 : 0.7;
				}
			}
			cache[ key ] = out;
			return out;
		};
		var rand = function ( i, k ) { var x = Math.sin( i * 12.9898 + k * 78.233 ) * 43758.5453; return x - Math.floor( x ); };
		mount( ctx, {
			build: function ( api ) {
				var THREE = api.THREE;
				ctx.cards.forEach( function ( c, k ) {
					photos.push( loadPhoto( fullSrc( c ), Math.round( k * 360 / n ), function () {
						for ( var key in cache ) { if ( key.indexOf( k + ':' ) === 0 ) { delete cache[ key ]; } }
						lastA = lastB = -1; api.dirty = true;
					} ) );
				} );
				u = {
					p: { value: 0 }, time: { value: 0 }, scatter: { value: scatter }, mode: { value: effect }, psize: { value: 3 },
					ptrAmt: { value: 0 }, op: { value: 1 }, spanW: { value: 1 }, spanH: { value: 1 }, ptr: { value: new THREE.Vector2( 99, 99 ) }
				};
				// An invisible plane over the stage: clicks on the picture open the current photo's card.
				hit = new THREE.Mesh( new THREE.PlaneGeometry( 1, 1 ), new THREE.MeshBasicMaterial( { transparent: true, opacity: 0, depthWrite: false } ) );
				hit.userData.card = 0; hit.userData.appear = 0;
				api.cards.push( hit ); api.scene.add( hit );
				st = stepper( api, ctx, n, mt, n );
				ctx.cards.forEach( function ( c, k ) { c.addEventListener( 'focusin', function () { st.go( k ); } ); } );
			},
			resize: function ( api ) {
				var THREE = api.THREE;
				api.camera.position.set( 0, 0, 5 ); api.camera.lookAt( 0, 0, 0 );
				halfH = 5 * Math.tan( api.camera.fov * Math.PI / 360 ); halfW = halfH * api.camera.aspect;
				hit.scale.set( halfW * 2, halfH * 2, 1 );
				u.spanW.value = halfW; u.spanH.value = halfH;
				var c2 = density, r2 = Math.max( 10, Math.round( density * api.h / Math.max( 1, api.w ) ) );
				var dpr = Math.min( window.devicePixelRatio || 1, 2 );
				u.psize.value = ( api.w / c2 ) * dpr * psize * 1.15;
				if ( c2 === cols && r2 === rows && pts ) { return; }
				cols = c2; rows = r2;
				var N = cols * rows, pos = new Float32Array( N * 3 ), rnd = new Float32Array( N * 4 );
				for ( var y = 0; y < rows; y++ ) {
					for ( var x = 0; x < cols; x++ ) {
						var i = y * cols + x;
						pos[ i * 3 ] = ( ( x + 0.5 ) / cols * 2 - 1 ) * halfW; pos[ i * 3 + 1 ] = ( ( y + 0.5 ) / rows * 2 - 1 ) * halfH; pos[ i * 3 + 2 ] = 0;
						var th = rand( i, 1 ) * 6.2832, ph = Math.acos( rand( i, 2 ) * 2 - 1 ), rr = 0.3 + rand( i, 3 ) * 0.7;
						rnd[ i * 4 ] = Math.sin( ph ) * Math.cos( th ) * rr; rnd[ i * 4 + 1 ] = Math.sin( ph ) * Math.sin( th ) * rr; rnd[ i * 4 + 2 ] = Math.cos( ph ) * rr; rnd[ i * 4 + 3 ] = rand( i, 4 );
					}
				}
				var geo = new THREE.BufferGeometry();
				geo.setAttribute( 'position', new THREE.BufferAttribute( pos, 3 ) );
				geo.setAttribute( 'rnd', new THREE.BufferAttribute( rnd, 4 ) );
				geo.setAttribute( 'cA', new THREE.BufferAttribute( new Float32Array( N * 3 ), 3 ) );
				geo.setAttribute( 'cB', new THREE.BufferAttribute( new Float32Array( N * 3 ), 3 ) );
				if ( pts ) { api.scene.remove( pts ); pts.geometry.dispose(); pts.geometry = geo; }
				else { pts = new THREE.Points( geo, new THREE.ShaderMaterial( { uniforms: u, vertexShader: MORPH_VS, fragmentShader: MORPH_FS, transparent: true, depthWrite: false, toneMapped: false } ) ); }
				pts.frustumCulled = false;
				api.scene.add( pts );
				lastA = lastB = -1;
			},
			frame: function ( api, dt, t ) {
				if ( ! pts ) { return; }
				var s = st.state( t ), a = mod( s.a, n ), b = mod( s.b, n ), g = pts.geometry;
				if ( a !== lastA ) { g.attributes.cA.array.set( colours( a ) ); g.attributes.cA.needsUpdate = true; lastA = a; }
				if ( b !== lastB ) { g.attributes.cB.array.set( colours( b ) ); g.attributes.cB.needsUpdate = true; lastB = b; }
				u.p.value = s.e; u.time.value = t;
				ptrOn += ( ( api.pointer ? 1 : 0 ) - ptrOn ) * Math.min( 1, dt * 4 );
				if ( api.pointer ) { u.ptr.value.set( api.pointer.x * halfW, api.pointer.y * halfH ); }
				u.ptrAmt.value = repel * ptrOn;
				hit.userData.card = s.e > 0.5 ? b : a;
				u.op.value = EASE( hit.userData.appear );
			}
		} );
	} );

	/* ---------------------------------------------------------------- Flip Book */
	window.upwGallery3d.registerDesign( 'webgl_flip_book', function ( ctx ) {
		if ( ! ctx.cards.length ) { return; }
		var size = ctx.num( 'data-tdg-card', 60 ) / 100, tilt = ctx.num( 'data-tdg-tilt', 25 ), curl = ctx.num( 'data-tdg-curl', 60 ) / 100;
		var mt = Math.max( 0.1, Math.min( 0.9, ctx.num( 'data-tdg-movetime', 45 ) / 100 ) ), coverCol = ctx.attr( 'data-tdg-cover', '#2b303b' );
		var n = ctx.cards.length, SEG = 40, pw = 1, ph, texs = [], left, right, turnF, turnB, book, st, mats = [];
		mount( ctx, {
			build: function ( api ) {
				var THREE = api.THREE;
				ph = pw * api.aspect;
				// Page photos (corner radius relative to a page's width on screen).
				var pagePx = Math.max( 60, ( ctx.stage.clientWidth || 800 ) * size / 2 );
				var radFrac = Math.min( 0.2, radiusOf( ctx.el ) / pagePx );
				ctx.cards.forEach( function ( c, k ) {
					var src = fullSrc( c );
					texs.push( cardTexture( src, api.aspect, radFrac, function () { api.dirty = true; }, src ? null : Math.round( k * 360 / n ) ) );
				} );
				var mat = function ( side ) { var m = new THREE.MeshStandardMaterial( { map: texs[ 0 ], roughness: 0.78, metalness: 0, side: side, transparent: true, alphaTest: 0.02 } ); mats.push( m ); return m; };
				book = new THREE.Group();
				left = new THREE.Mesh( new THREE.PlaneGeometry( pw, ph ).translate( -pw / 2, 0, 0 ), mat( THREE.FrontSide ) );
				right = new THREE.Mesh( new THREE.PlaneGeometry( pw, ph ).translate( pw / 2, 0, 0 ), mat( THREE.FrontSide ) );
				// The turning page: one bendable strip, drawn twice — its front, and its back with the photo mirrored
				// so it reads the right way round once the page lies on the left.
				var gF = new THREE.PlaneGeometry( pw, ph, SEG, 1 ), gB = gF.clone(), uv = gB.attributes.uv;
				for ( var i = 0; i < uv.count; i++ ) { uv.setX( i, 1 - uv.getX( i ) ); }
				turnF = new THREE.Mesh( gF, mat( THREE.FrontSide ) );
				turnB = new THREE.Mesh( gB, mat( THREE.BackSide ) );
				// The cover board and the paper edges under the pages.
				var coverM = new THREE.MeshStandardMaterial( { color: new THREE.Color( coverCol ).convertSRGBToLinear(), roughness: 0.6, transparent: true } );
				var paperM = new THREE.MeshStandardMaterial( { color: new THREE.Color( 0xf3efe6 ).convertSRGBToLinear(), roughness: 0.9, transparent: true } );
				mats.push( coverM, paperM );
				var cover = new THREE.Mesh( new THREE.BoxGeometry( pw * 2 + 0.08, ph + 0.08, 0.04 ), coverM ); cover.position.z = -0.045;
				var stackL = new THREE.Mesh( new THREE.BoxGeometry( pw, ph, 0.022 ), paperM ); stackL.position.set( -pw / 2, 0, -0.012 );
				var stackR = stackL.clone(); stackR.position.x = pw / 2;
				book.add( cover, stackL, stackR, left, right, turnF, turnB );
				api.scene.add( book );
				[ left, right, turnF, turnB ].forEach( function ( m ) { m.userData.card = 0; m.userData.appear = 0; api.cards.push( m ); } );
				api.key.position.set( -3, 4, 7 );
				var steps = Math.max( 1, Math.ceil( n / 2 ) );
				st = stepper( api, ctx, steps, mt, n );
				// Keyboard focus on a photo turns to the spread that shows it (even photos on the right, odd on the left).
				ctx.cards.forEach( function ( c, k ) { c.addEventListener( 'focusin', function () { st.go( Math.floor( ( k + 1 ) / 2 ) ); } ); } );
			},
			resize: function ( api ) {
				var tanH = Math.tan( api.camera.fov * Math.PI / 360 );
				var dist = Math.max( ( pw * 2 / size ) / ( 2 * tanH * api.camera.aspect ), ( ph * 1.45 ) / ( 2 * tanH ) );
				var ta = tilt * Math.PI / 180;
				api.camera.position.set( 0, -Math.sin( ta ) * dist, Math.cos( ta ) * dist );
				api.camera.lookAt( 0, 0, 0 );
			},
			frame: function ( api ) {
				var s = st.state( api.t ), k, th;
				if ( s.b === s.a + 1 ) { k = s.a; th = Math.PI * s.e; }
				else if ( s.b === s.a - 1 ) { k = s.b; th = Math.PI * ( 1 - s.e ); }
				else { k = s.e > 0.5 ? s.b : s.a; th = 0; }       // a jump across several spreads lands directly
				var L = mod( 2 * k - 1, n ), R = mod( 2 * k + 2, n ), F = mod( 2 * k, n ), B = mod( 2 * k + 1, n );
				left.material.map = texs[ L ]; right.material.map = texs[ R ]; turnF.material.map = texs[ F ]; turnB.material.map = texs[ B ];
				left.userData.card = L; right.userData.card = R; turnF.userData.card = F; turnB.userData.card = B;
				// Bend: each strip's angle from the spine lags behind the turn by Page Curl (the free edge trails),
				// never dipping below the pages; integrate the strips outward from the spine.
				var pos = turnF.geometry.attributes.position, du = pw / SEG, bend = curl * 1.4 * Math.sin( th );
				var xs = [ 0 ], zs = [ 0 ], x = 0, z = 0;
				for ( var j = 1; j <= SEG; j++ ) {
					var f = ( j - 0.5 ) / SEG, phi = Math.max( 0, Math.min( Math.PI, th - bend * Math.pow( f, 1.3 ) ) );
					x += Math.cos( phi ) * du; z += Math.sin( phi ) * du; xs.push( x ); zs.push( z );
				}
				var lift = 0.004;
				for ( var r = 0; r < 2; r++ ) {
					var y = r === 0 ? ph / 2 : -ph / 2;
					for ( j = 0; j <= SEG; j++ ) { pos.setXYZ( r * ( SEG + 1 ) + j, xs[ j ], y, zs[ j ] + lift ); }
				}
				pos.needsUpdate = true;
				turnF.geometry.computeVertexNormals();
				var pb = turnB.geometry.attributes.position; pb.array.set( pos.array ); pb.needsUpdate = true;
				turnB.geometry.computeVertexNormals();
				var ap = EASE( left.userData.appear );
				mats.forEach( function ( m ) { m.opacity = ap; } );
				book.position.y = ( 1 - ap ) * -0.25;
			}
		} );
	} );
	/* ---------------------------------------------------------------- Shatter */
	window.upwGallery3d.registerDesign( 'webgl_shatter', function ( ctx ) {
		if ( ! ctx.cards.length ) { return; }
		var pieces = Math.max( 4, Math.min( 30, ctx.num( 'data-tdg-pieces', 14 ) ) );
		var shape = ctx.attr( 'data-tdg-shape', 'shards' ), burst = ctx.attr( 'data-tdg-burst', 'toward' ), origin = ctx.attr( 'data-tdg-origin', 'center' );
		var spin = ctx.num( 'data-tdg-spin', 60 ) / 100, force = ctx.num( 'data-tdg-force', 60 ) / 100;
		var mt = Math.max( 0.1, Math.min( 0.9, ctx.num( 'data-tdg-movetime', 45 ) / 100 ) );
		var n = ctx.cards.length, photos = [], texs = [], shards = [], mesh = null, back, st, halfW = 1, halfH = 1, pa = 1.6, lastE = -1;
		var rand = function ( i, k ) { var x = Math.sin( i * 91.345 + k * 47.853 ) * 43758.5453; return x - Math.floor( x ); };
		var L = { x: -0.4, y: 0.5, z: 0.77 };
		// Cover-fit a photo on a stage-shaped plane through the texture's own repeat / offset.
		var cover = function ( k ) {
			var t = texs[ k ], ia = photos[ k ].aspect;
			if ( pa > ia ) { t.repeat.set( 1, ia / pa ); t.offset.set( 0, ( 1 - ia / pa ) / 2 ); } else { t.repeat.set( pa / ia, 1 ); t.offset.set( ( 1 - pa / ia ) / 2, 0 ); }
			return t;
		};
		mount( ctx, {
			build: function ( api ) {
				var THREE = api.THREE;
				ctx.cards.forEach( function ( c, k ) {
					var tex = new THREE.Texture();
					tex.generateMipmaps = false; tex.minFilter = THREE.LinearFilter;
					var p = loadPhoto( fullSrc( c ), Math.round( k * 360 / n ), function ( pp ) { swapImage( tex, pp.el ); api.dirty = true; lastE = -1; } );
					tex.image = p.el; tex.needsUpdate = true;
					photos.push( p ); texs.push( tex );
				} );
				back = new THREE.Mesh( new THREE.PlaneGeometry( 1, 1 ), new THREE.MeshBasicMaterial( { map: texs[ 0 ], transparent: true, toneMapped: false } ) );
				back.position.z = -0.02;
				back.userData.card = 0; back.userData.appear = 0;
				api.scene.add( back );
				st = stepper( api, ctx, n, mt, n );
				ctx.cards.forEach( function ( c, k ) { c.addEventListener( 'focusin', function () { st.go( k ); } ); } );
			},
			resize: function ( api ) {
				var THREE = api.THREE;
				api.camera.position.set( 0, 0, 5 ); api.camera.lookAt( 0, 0, 0 );
				halfH = 5 * Math.tan( api.camera.fov * Math.PI / 360 ); halfW = halfH * api.camera.aspect; pa = api.w / Math.max( 1, api.h );
				back.scale.set( halfW * 2, halfH * 2, 1 );
				// The pieces: a jittered grid; Shards splits each cell into two triangles that break apart, Tiles keeps it square.
				var cols = pieces, rows = Math.max( 2, Math.round( pieces * api.h / Math.max( 1, api.w ) ) ), P = [];
				for ( var j = 0; j <= rows; j++ ) {
					P[ j ] = [];
					for ( var i = 0; i <= cols; i++ ) {
						var edgeX = i === 0 || i === cols, edgeY = j === 0 || j === rows, jit = shape === 'shards' ? 0.38 : 0;
						P[ j ][ i ] = [ ( i + ( edgeX ? 0 : ( rand( i * 31 + j, 1 ) - 0.5 ) * jit ) ) / cols, ( j + ( edgeY ? 0 : ( rand( i * 31 + j, 2 ) - 0.5 ) * jit ) ) / rows ];
					}
				}
				shards = [];
				var tris = function ( list ) {
					var cx = 0, cy = 0, cnt = 0;
					list.forEach( function ( t ) { t.forEach( function ( v ) { cx += v[ 0 ]; cy += v[ 1 ]; cnt++; } ); } );
					cx /= cnt; cy /= cnt;
					var o = origin === 'left' ? [ 0, 0.5 ] : ( origin === 'top' ? [ 0.5, 1 ] : [ 0.5, 0.5 ] );
					var dx = ( cx - o[ 0 ] ) * pa, dy = cy - o[ 1 ], dl = Math.sqrt( dx * dx + dy * dy ) || 1;
					var maxD = origin === 'center' ? 0.5 * Math.sqrt( pa * pa + 1 ) : ( origin === 'left' ? Math.sqrt( pa * pa + 0.25 ) : Math.sqrt( pa * pa / 4 + 1 ) );
					var k = shards.length, ax = rand( k, 3 ) - 0.5, ay = rand( k, 4 ) - 0.5, az = ( rand( k, 5 ) - 0.5 ) * 0.6, al = Math.sqrt( ax * ax + ay * ay + az * az ) || 1;
					shards.push( {
						tris: list, c: [ cx, cy ], dir: [ dx / dl, dy / dl ],
						d: origin === 'random' ? rand( k, 6 ) * 0.8 : Math.min( 1, dl / maxD ) * 0.6 + rand( k, 6 ) * 0.2,
						axis: new THREE.Vector3( ax / al, ay / al, az / al ), sp: 0.6 + rand( k, 7 ) * 0.8
					} );
				};
				for ( j = 0; j < rows; j++ ) {
					for ( i = 0; i < cols; i++ ) {
						var a = P[ j ][ i ], b = P[ j ][ i + 1 ], c = P[ j + 1 ][ i + 1 ], d = P[ j + 1 ][ i ];
						if ( shape === 'shards' ) { var flip = rand( i, j ) > 0.5; tris( [ flip ? [ a, b, c ] : [ a, b, d ] ] ); tris( [ flip ? [ a, c, d ] : [ b, c, d ] ] ); }
						else { tris( [ [ a, b, c ], [ a, c, d ] ] ); }
					}
				}
				var N = 0; shards.forEach( function ( s ) { N += s.tris.length * 3; } );
				var geo = new THREE.BufferGeometry(), uv = new Float32Array( N * 2 ), vi = 0;
				shards.forEach( function ( s ) { s.at = vi; s.tris.forEach( function ( t ) { t.forEach( function ( v ) { uv[ vi * 2 ] = v[ 0 ]; uv[ vi * 2 + 1 ] = v[ 1 ]; vi++; } ); } ); } );
				geo.setAttribute( 'position', new THREE.BufferAttribute( new Float32Array( N * 3 ), 3 ) );
				geo.setAttribute( 'uv', new THREE.BufferAttribute( uv, 2 ) );
				geo.setAttribute( 'color', new THREE.BufferAttribute( new Float32Array( N * 4 ), 4 ) );
				if ( mesh ) { mesh.geometry.dispose(); mesh.geometry = geo; }
				else {
					// Unlit photo colours (like the back plane, so a resting picture matches it exactly); each shard is
					// brightened or shaded by how it turns toward the light, written into its vertex colour.
					mesh = new THREE.Mesh( geo, new THREE.MeshBasicMaterial( { map: texs[ 0 ], vertexColors: true, transparent: true, side: THREE.DoubleSide, toneMapped: false, depthWrite: false } ) );
					mesh.frustumCulled = false;
					mesh.userData.card = 0; mesh.userData.appear = 0;
					api.cards.push( mesh, back );
					api.scene.add( mesh );
				}
				lastE = -1;
			},
			frame: function ( api, dt, t ) {
				if ( ! mesh ) { return; }
				var THREE = api.THREE, s = st.state( t ), a = mod( s.a, n ), b = mod( s.b, n ), e = s.e;
				mesh.material.map = cover( a ); back.material.map = cover( b );
				mesh.userData.card = a; back.userData.card = b;
				var ap = EASE( mesh.userData.appear );
				mesh.material.opacity = ap; back.material.opacity = e > 0 ? ap : 0;
				if ( e === lastE ) { return; }
				lastE = e;
				var pos = mesh.geometry.attributes.position.array, col = mesh.geometry.attributes.color.array;
				var q4 = new THREE.Quaternion(), v = new THREE.Vector3(), nrm = new THREE.Vector3();
				shards.forEach( function ( sh, k ) {
					var q = Math.max( 0, Math.min( 1, ( e - sh.d * 0.55 ) / 0.45 ) ), qe = q * q;
					q4.setFromAxisAngle( sh.axis, q * Math.PI * ( 0.4 + spin * 2.2 ) * sh.sp );
					var cx = ( sh.c[ 0 ] - 0.5 ) * 2 * halfW, cy = ( sh.c[ 1 ] - 0.5 ) * 2 * halfH, tx = 0, ty = 0, tz = 0;
					if ( burst === 'fall' ) { ty = -qe * ( 0.4 + force ) * halfH * 3.2 * sh.sp; tx = sh.dir[ 0 ] * q * 0.4; tz = q * 0.8; }
					else if ( burst === 'outward' ) { tx = sh.dir[ 0 ] * q * ( 0.3 + force ) * halfW * 1.6 * sh.sp; ty = sh.dir[ 1 ] * q * ( 0.3 + force ) * halfH * 1.6 * sh.sp; tz = q * 0.6; }
					else { tx = sh.dir[ 0 ] * q * ( 0.3 + force ) * halfW * 0.7; ty = sh.dir[ 1 ] * q * ( 0.3 + force ) * halfH * 0.7; tz = qe * ( 0.5 + force ) * 4.2; }
					nrm.set( 0, 0, 1 ).applyQuaternion( q4 );
					var lit = Math.max( 0.35, Math.min( 1.7, 1 + 0.9 * ( nrm.x * L.x + nrm.y * L.y + nrm.z * L.z - L.z ) ) );
					var alpha = 1 - Math.max( 0, Math.min( 1, ( q - 0.55 ) / 0.45 ) );
					var vi = sh.at;
					sh.tris.forEach( function ( tri ) {
						tri.forEach( function ( p ) {
							v.set( ( p[ 0 ] - 0.5 ) * 2 * halfW - cx, ( p[ 1 ] - 0.5 ) * 2 * halfH - cy, 0 ).applyQuaternion( q4 );
							pos[ vi * 3 ] = cx + v.x + tx; pos[ vi * 3 + 1 ] = cy + v.y + ty; pos[ vi * 3 + 2 ] = v.z + tz;
							col[ vi * 4 ] = col[ vi * 4 + 1 ] = col[ vi * 4 + 2 ] = q > 0 ? lit : 1; col[ vi * 4 + 3 ] = alpha;
							vi++;
						} );
					} );
				} );
				mesh.geometry.attributes.position.needsUpdate = true;
				mesh.geometry.attributes.color.needsUpdate = true;
				mesh.geometry.computeBoundingSphere();
			}
		} );
	} );

	/* ---------------------------------------------------------------- Cloth Banners */
	window.upwGallery3d.registerDesign( 'webgl_cloth_banners', function ( ctx ) {
		if ( ! ctx.cards.length ) { return; }
		var vis = Math.max( 2, Math.min( 8, ctx.num( 'data-tdg-banners', 4 ) ) ), wind = ctx.num( 'data-tdg-wind', 50 ) / 100;
		var ripples = Math.max( 0.5, Math.min( 5, ctx.num( 'data-tdg-ripples', 2 ) ) ), gap = ctx.num( 'data-tdg-gap', 14 ) / 100;
		var view = ctx.num( 'data-tdg-view', 8 ), rodCol = ctx.attr( 'data-tdg-rod', '#3a3c41' ), dir = ctx.num( 'data-tdg-dir', 1 );
		var n = ctx.cards.length, SX = 16, SY = 20, cw = 1, ch, sp, texs = [], banners = [], group, gust = [];
		mount( ctx, {
			build: function ( api ) {
				var THREE = api.THREE;
				ch = cw * api.aspect; sp = cw * ( 1 + gap );
				api.roughness = 0.9;
				var radFrac = Math.min( 0.3, radiusOf( ctx.el ) / Math.max( 60, ( ctx.stage.clientWidth || 800 ) / ( vis + 1 ) ) );
				ctx.cards.forEach( function ( c, k ) { var src = fullSrc( c ); texs.push( cardTexture( src, api.aspect, radFrac, function () { api.dirty = true; }, src ? null : Math.round( k * 360 / n ) ) ); } );
				group = new THREE.Group();
				for ( var j = 0; j <= vis; j++ ) {
					var g = new THREE.PlaneGeometry( cw, ch, SX, SY ).translate( 0, -ch / 2, 0 );
					g.userData.base = Float32Array.from( g.attributes.position.array );
					var m = new THREE.Mesh( g, new THREE.MeshStandardMaterial( { map: texs[ 0 ], roughness: 0.9, metalness: 0, side: THREE.DoubleSide, transparent: true, alphaTest: 0.02 } ) );
					m.userData.card = 0; m.userData.appear = 0;
					banners.push( m ); api.cards.push( m ); group.add( m ); gust.push( 0 );
				}
				var rodW = vis * sp + sp;
				var rod = new THREE.Mesh( new THREE.CylinderGeometry( 0.028, 0.028, rodW, 12 ), new THREE.MeshStandardMaterial( { color: new THREE.Color( rodCol ).convertSRGBToLinear(), roughness: 0.4, metalness: 0.6 } ) );
				rod.rotation.z = Math.PI / 2; rod.position.y = 0.035;
				group.add( rod );
				group.position.y = ch / 2;
				api.scene.add( group );
				api.key.position.set( -4, 3, 5 );
			},
			resize: function ( api ) {
				var tanH = Math.tan( api.camera.fov * Math.PI / 360 );
				var dist = Math.max( ( vis * sp ) / api.camera.aspect, ch * 1.3 ) * 1.06 / ( 2 * tanH );
				var va = view * Math.PI / 180;
				api.camera.position.set( 0, -Math.sin( va ) * dist, Math.cos( va ) * dist );
				api.camera.lookAt( 0, 0, 0 );
			},
			frame: function ( api, dt, t ) {
				var off = ( ctx.attr( 'data-tdg-drive', 'continuous' ) === 'static' ? 0 : api.progress() * n * dir ), base = Math.floor( off ), fr = off - base;
				var W = vis * sp, k2 = Math.PI * 2 * ripples / ch;
				banners.forEach( function ( m, j ) {
					var photo = mod( base + j, n );
					m.material.map = texs[ photo ]; m.userData.card = photo;
					var x = ( j - fr - vis / 2 + 0.5 ) * sp;
					m.position.x = x;
					// Fade a banner as it slides in or out at the ends of the rod.
					var edge = Math.max( 0, Math.min( 1, ( W / 2 + sp * 0.5 - Math.abs( x ) ) / ( sp * 0.7 ) ) );
					var ap = EASE( m.userData.appear );
					m.material.opacity = ap * edge;
					m.visible = m.material.opacity > 0.01;
					m.position.y = ( 1 - ap ) * 0.3;
					// Wind: travelling waves down and across the cloth, stronger toward the free bottom edge; a pointed-at
					// banner catches a gust.
					gust[ j ] += ( ( api.hover === j || api.focus === j ? 1 : 0 ) - gust[ j ] ) * Math.min( 1, dt * 3 );
					var A = cw * 0.22 * ( wind + gust[ j ] * 0.6 ), ph = j * 1.7 + base * 0.9, g = m.geometry, b = g.userData.base, p = g.attributes.position.array;
					for ( var i = 0; i < b.length; i += 3 ) {
						var x0 = b[ i ], y0 = b[ i + 1 ], hang = Math.pow( Math.max( 0, -y0 / ch ), 1.15 );
						var w1 = Math.sin( -y0 * k2 + x0 * 2.1 - t * ( 1.6 + wind * 2.2 ) + ph );
						var w2 = Math.sin( x0 * 5.3 - t * 1.3 + ph * 2.3 ) * 0.35;
						p[ i ] = x0 + A * 0.12 * hang * Math.sin( t * 1.1 + ph );
						p[ i + 1 ] = y0 + A * 0.05 * hang * hang * w1;
						p[ i + 2 ] = A * hang * ( w1 + w2 );
					}
					g.attributes.position.needsUpdate = true;
					g.computeVertexNormals();
				} );
			}
		} );
	} );

	/* ---------------------------------------------------------------- Fold Strip */
	window.upwGallery3d.registerDesign( 'webgl_fold_strip', function ( ctx ) {
		if ( ! ctx.cards.length ) { return; }
		var vis = Math.max( 3, Math.min( 12, ctx.num( 'data-tdg-panels', 6 ) ) ), foldMax = ctx.num( 'data-tdg-fold', 35 ) * Math.PI / 180;
		var unfold = ctx.attr( 'data-tdg-unfold', '1' ) === '1', view = ctx.num( 'data-tdg-view', 12 ), dir = ctx.num( 'data-tdg-dir', 1 );
		var n = ctx.cards.length, M = vis + 2, w = 1, ch, texs = [], panels = [], fold = foldMax, lift = [];
		mount( ctx, {
			build: function ( api ) {
				var THREE = api.THREE;
				ch = w * api.aspect;
				var radFrac = Math.min( 0.3, radiusOf( ctx.el ) / Math.max( 60, ( ctx.stage.clientWidth || 800 ) / vis ) );
				ctx.cards.forEach( function ( c, k ) { var src = fullSrc( c ); texs.push( cardTexture( src, api.aspect, radFrac, function () { api.dirty = true; }, src ? null : Math.round( k * 360 / n ) ) ); } );
				var geo = new THREE.PlaneGeometry( w, ch );
				for ( var j = 0; j < M; j++ ) {
					var m = new THREE.Mesh( geo, new THREE.MeshStandardMaterial( { map: texs[ 0 ], roughness: 0.6, metalness: 0, side: THREE.DoubleSide, transparent: true, alphaTest: 0.02 } ) );
					m.userData.card = 0; m.userData.appear = 0;
					panels.push( m ); api.cards.push( m ); api.scene.add( m ); lift.push( 0 );
				}
				api.key.position.set( 6, 3, 5 );
			},
			resize: function ( api ) {
				var tanH = Math.tan( api.camera.fov * Math.PI / 360 );
				var dist = Math.max( ( vis * w * Math.cos( foldMax ) ) / api.camera.aspect, ch * 1.35 ) * 1.05 / ( 2 * tanH );
				var va = view * Math.PI / 180;
				api.camera.position.set( 0, Math.sin( va ) * dist, Math.cos( va ) * dist );
				api.camera.lookAt( 0, 0, 0 );
			},
			frame: function ( api, dt ) {
				// Unfold (flatten) while the pointer is over the strip.
				var target = ( unfold && ( api.hover >= 0 || api.focus >= 0 || api.pointer ) ) ? 0 : foldMax;
				fold += ( target - fold ) * Math.min( 1, dt * 4 );
				var drive = ctx.attr( 'data-tdg-drive', 'continuous' );
				var off = ( drive === 'static' ? 0 : api.progress() * n * dir ) - api.drag / Math.PI * vis;
				var base = Math.floor( off ), fr = off - base, cs = Math.cos( fold );
				panels.forEach( function ( m, j ) {
					var k = base + j, photo = mod( k, n ), u = j - fr - M / 2 + 0.5;
					m.material.map = texs[ photo ]; m.userData.card = photo;
					// The zigzag: every panel centre sits on one line; panels alternate their turn, so the edges
					// alternate toward and away from you and the light falls differently on each fold.
					m.position.x = u * w * cs;
					m.rotation.y = mod( k, 2 ) ? fold : -fold;
					lift[ j ] += ( ( api.hover === j || api.focus === j ? 1 : 0 ) - lift[ j ] ) * Math.min( 1, dt * 6 );
					var ap = EASE( m.userData.appear );
					m.position.z = lift[ j ] * 0.25 - ( 1 - ap ) * 0.8;
					m.position.y = 0;
					var edge = Math.max( 0, Math.min( 1, ( M / 2 - 0.6 - Math.abs( u ) ) / 0.9 ) );
					m.material.opacity = ap * edge;
					m.visible = m.material.opacity > 0.01;
				} );
			}
		} );
	} );
	/* ---------------------------------------------------------------- Water Pool */
	var POOL_FS = [
		'uniform sampler2D tex; uniform float pa, ga, off, time, refr, light, tint, op; uniform vec3 water; uniform vec4 drops[10];',
		'varying vec2 vUv;',
		'float height( vec2 p ) {',
		'  float h = 0.010 * sin( p.x * 6.0 + time * 0.9 ) * sin( p.y * 5.0 - time * 0.7 ) + 0.007 * sin( ( p.x + p.y ) * 9.0 + time * 1.3 );',
		'  for ( int i = 0; i < 10; i++ ) { vec4 d = drops[ i ]; if ( d.w <= 0.0 ) { continue; }',
		'    float r = length( p - d.xy ), front = d.z * 0.42, x = ( r - front ) * 7.0;',
		'    h += d.w * exp( -d.z * 1.2 ) * sin( ( r - front ) * 42.0 ) * exp( -x * x ); }',
		'  return h;',
		'}',
		'void main() {',
		'  vec2 p = vec2( vUv.x * pa, vUv.y ); float e = 0.004, h = height( p );',
		'  vec2 g = vec2( height( p + vec2( e, 0.0 ) ) - h, height( p + vec2( 0.0, e ) ) - h ) / e;',
		'  vec2 q = vUv + g * refr * 0.03;',                                                      // the floor seen through the moving surface
		'  vec2 s = pa > ga ? vec2( pa / ga, 1.0 ) : vec2( 1.0, ga / pa );',
		'  vec2 guv = ( q - 0.5 ) * s * 1.06 + 0.5; guv.x = fract( guv.x + off );',
		'  vec3 col = ( guv.y < 0.0 || guv.y > 1.0 ) ? water * 0.55 : texture2D( tex, guv ).rgb;',
		'  col = mix( col, water, tint );',
		'  vec3 nrm = normalize( vec3( -g * 0.6, 1.0 ) );',
		'  float spec = pow( max( dot( nrm, normalize( vec3( -0.35, 0.45, 1.0 ) ) ), 0.0 ), 60.0 );',  // the light on the water
		'  col += ( spec * 0.55 + clamp( length( g ) * 0.08, 0.0, 0.25 ) ) * light;',
		'  gl_FragColor = vec4( col, op );',
		'}'
	].join( '\n' );

	window.upwGallery3d.registerDesign( 'webgl_water_pool', function ( ctx ) {
		if ( ! ctx.cards.length ) { return; }
		var cols = Math.max( 2, Math.min( 8, ctx.num( 'data-tdg-cols', 4 ) ) ), rows = Math.max( 1, Math.min( 5, ctx.num( 'data-tdg-rows', 2 ) ) );
		var gap = ctx.num( 'data-tdg-gap', 4 ) / 100, refr = ctx.num( 'data-tdg-refract', 50 ) / 100, rain = ctx.num( 'data-tdg-rain', 30 ) / 100;
		var light = ctx.num( 'data-tdg-shine', 50 ) / 100, waterCol = ctx.attr( 'data-tdg-water', '#2a8fbf' ), dir = ctx.num( 'data-tdg-dir', 1 );
		var n = ctx.cards.length, photos = [], cv, g2, tex, plane, u, drops = [], lastDrop = 0, lastPt = null, nextRain = 1, ga = 1;
		var aspect = ratioOf( ctx.el ), radPx = radiusOf( ctx.el );
		var hexRgb = function ( h ) { var c = document.createElement( 'canvas' ).getContext( '2d' ); c.fillStyle = h; var m = c.fillStyle.match( /^#([0-9a-f]{6})$/i ); var v = m ? parseInt( m[ 1 ], 16 ) : 0x2a8fbf; return [ ( v >> 16 & 255 ) / 255, ( v >> 8 & 255 ) / 255, ( v & 255 ) / 255 ]; };
		var wc = hexRgb( waterCol );
		// The pool floor: every photo laid out in one canvas (one texture), with gaps of floor between them.
		var compose = function () {
			var W = 2048, cw = W / cols, ch = cw * aspect, H = Math.round( ch * rows ), gp = cw * gap / 2;
			if ( cv.width !== W || cv.height !== H ) { cv.width = W; cv.height = H; }
			g2.fillStyle = 'rgb(' + Math.round( wc[ 0 ] * 140 ) + ',' + Math.round( wc[ 1 ] * 140 ) + ',' + Math.round( wc[ 2 ] * 140 ) + ')';
			g2.fillRect( 0, 0, W, H );
			for ( var r = 0; r < rows; r++ ) {
				for ( var c = 0; c < cols; c++ ) {
					var p = photos[ mod( r * cols + c, n ) ], x = c * cw + gp, y = r * ch + gp, w = cw - gp * 2, h = ch - gp * 2;
					var sw = p.el.naturalWidth || p.el.width, sh = p.el.naturalHeight || p.el.height, sc = Math.max( w / sw, h / sh ), rad = Math.min( w, h ) * Math.min( 0.5, radPx / 200 );
					g2.save(); g2.beginPath();
					if ( g2.roundRect ) { g2.roundRect( x, y, w, h, rad ); } else { g2.rect( x, y, w, h ); }
					g2.clip();
					g2.drawImage( p.el, x + ( w - sw * sc ) / 2, y + ( h - sh * sc ) / 2, sw * sc, sh * sc );
					g2.restore();
				}
			}
			ga = W / H;
			if ( u ) { u.ga.value = ga; }
			if ( tex ) { tex.needsUpdate = true; }
		};
		mount( ctx, {
			build: function ( api ) {
				var THREE = api.THREE;
				ctx.cards.forEach( function ( c, k ) { photos.push( loadPhoto( fullSrc( c ), Math.round( k * 360 / n ), function () { compose(); api.dirty = true; } ) ); } );
				cv = document.createElement( 'canvas' ); g2 = cv.getContext( '2d' );
				compose();
				tex = new THREE.CanvasTexture( cv ); tex.generateMipmaps = false; tex.minFilter = THREE.LinearFilter;
				var dv = []; for ( var i = 0; i < 10; i++ ) { dv.push( new THREE.Vector4( 0, 0, 0, 0 ) ); }
				u = { tex: { value: tex }, pa: { value: 1.6 }, ga: { value: ga }, off: { value: 0 }, time: { value: 0 }, refr: { value: refr }, light: { value: light },
					tint: { value: 0.12 }, op: { value: 1 }, water: { value: new THREE.Vector3( wc[ 0 ], wc[ 1 ], wc[ 2 ] ) }, drops: { value: dv } };
				plane = new THREE.Mesh( new THREE.PlaneGeometry( 1, 1 ), new THREE.ShaderMaterial( { uniforms: u, vertexShader: PLAIN_VS, fragmentShader: POOL_FS, transparent: true, depthWrite: false, toneMapped: false } ) );
				plane.userData.card = 0; plane.userData.appear = 0;
				api.cards.push( plane ); api.scene.add( plane );
			},
			resize: function ( api ) {
				api.camera.position.set( 0, 0, 5 ); api.camera.lookAt( 0, 0, 0 );
				var h = 2 * 5 * Math.tan( api.camera.fov * Math.PI / 360 );
				plane.scale.set( h * api.camera.aspect, h, 1 );
				u.pa.value = api.w / Math.max( 1, api.h );
			},
			frame: function ( api, dt, t ) {
				var pa = u.pa.value, drive = ctx.attr( 'data-tdg-drive', 'continuous' );
				var off = drive === 'static' ? 0 : api.progress() * dir;
				u.off.value = off - Math.floor( off ); u.time.value = t;
				// Drops: the moving pointer, a click, and (Rain) a drop now and then somewhere on the pool.
				var add = function ( x, y, amp ) { drops.push( { x: x, y: y, t0: t, a: amp } ); if ( drops.length > 10 ) { drops.shift(); } };
				if ( api.pointer ) {
					var px = ( api.pointer.x + 1 ) / 2 * pa, py = ( api.pointer.y + 1 ) / 2;
					var moved = lastPt ? Math.hypot( px - lastPt[ 0 ], py - lastPt[ 1 ] ) : 1;
					if ( moved > 0.05 && t - lastDrop > 0.12 ) { add( px, py, 0.016 ); lastDrop = t; lastPt = [ px, py ]; }
				} else { lastPt = null; }
				if ( rain > 0 && t > nextRain ) { add( Math.random() * pa, Math.random(), 0.012 ); nextRain = t + ( 0.4 + Math.random() * 1.6 ) / Math.max( 0.15, rain ); }
				for ( var i = 0; i < 10; i++ ) {
					var d = drops[ i ], v = u.drops.value[ i ];
					if ( d && t - d.t0 < 4 ) { v.set( d.x, d.y, t - d.t0, d.a ); } else { v.set( 0, 0, 0, 0 ); }
				}
				// Which photo is under the pointer (for the click).
				if ( api.pointer ) {
					var s = pa > ga ? [ pa / ga, 1 ] : [ 1, ga / pa ];
					var gx = ( ( api.pointer.x + 1 ) / 2 - 0.5 ) * s[ 0 ] * 1.06 + 0.5, gy = ( ( api.pointer.y + 1 ) / 2 - 0.5 ) * s[ 1 ] * 1.06 + 0.5;
					gx = gx + u.off.value; gx -= Math.floor( gx );
					var r = Math.floor( ( 1 - gy ) * rows ), c = Math.floor( gx * cols );
					plane.userData.card = ( gy >= 0 && gy <= 1 ) ? mod( r * cols + c, n ) : 0;
				}
				u.op.value = EASE( plane.userData.appear );
			}
		} );
	} );

	/* ---------------------------------------------------------------- Jelly Grid */
	window.upwGallery3d.registerDesign( 'webgl_jelly_grid', function ( ctx ) {
		if ( ! ctx.cards.length ) { return; }
		var cols = Math.max( 2, Math.min( 10, ctx.num( 'data-tdg-cols', 5 ) ) ), rows = Math.max( 1, Math.min( 6, ctx.num( 'data-tdg-rows', 3 ) ) );
		var gap = ctx.num( 'data-tdg-gap', 6 ) / 100, jelly = ctx.num( 'data-tdg-jelly', 60 ) / 100, wobble = ctx.num( 'data-tdg-wobble', 60 ) / 100;
		var wave = ctx.num( 'data-tdg-wave', 30 ) / 100, view = ctx.num( 'data-tdg-view', 10 ), gloss = ctx.num( 'data-tdg-gloss', 40 ) / 100;
		var n = ctx.cards.length, S = 12, cw = 1, ch, W, H, springs = [], ptr = null, ray, plane0, hitV;
		mount( ctx, {
			build: function ( api ) {
				var THREE = api.THREE;
				ch = cw * api.aspect; W = cols * cw * ( 1 + gap ); H = rows * ch * ( 1 + gap );
				api.roughness = 0.85 - gloss * 0.7;
				var src = ctx.cards.map( function ( c ) { return fullSrc( c ); } ), radFrac = Math.min( 0.4, radiusOf( ctx.el ) / Math.max( 60, ( ctx.stage.clientWidth || 800 ) / cols ) );
				var texs = src.map( function ( s, k ) { return cardTexture( s, api.aspect, radFrac, function () { api.dirty = true; }, s ? null : Math.round( k * 360 / n ) ); } );
				for ( var i = 0; i < cols * rows; i++ ) {
					var k = i % n, g = new THREE.PlaneGeometry( cw, ch, S, S );
					g.userData.base = Float32Array.from( g.attributes.position.array );
					var m = new THREE.Mesh( g, new THREE.MeshStandardMaterial( { map: texs[ k ], roughness: api.roughness, metalness: gloss * 0.2, side: THREE.DoubleSide, transparent: true, alphaTest: 0.02 } ) );
					m.userData.card = k; m.userData.appear = 0;
					m.position.set( ( i % cols - ( cols - 1 ) / 2 ) * cw * ( 1 + gap ), ( ( rows - 1 ) / 2 - Math.floor( i / cols ) ) * ch * ( 1 + gap ), 0 );
					api.cards.push( m ); api.scene.add( m );
					springs.push( { a: 0, v: 0, j: 0 } );
				}
				ray = new THREE.Raycaster(); plane0 = new THREE.Plane( new THREE.Vector3( 0, 0, 1 ), 0 ); hitV = new THREE.Vector3();
				api.key.position.set( -3, 4, 6 );
				// A click (or tap) on a photo makes it jiggle, before the lightbox / link opens.
				ctx.stage.addEventListener( 'pointerdown', function () { if ( api.hover >= 0 ) { springs[ api.hover ].v += 6; springs[ api.hover ].j = 1; } } );
			},
			resize: function ( api ) {
				var tanH = Math.tan( api.camera.fov * Math.PI / 360 );
				var dist = Math.max( W / api.camera.aspect, H ) * 1.12 / ( 2 * tanH );
				var va = view * Math.PI / 180;
				api.camera.position.set( 0, -Math.sin( va ) * dist, Math.cos( va ) * dist );
				api.camera.lookAt( 0, 0, 0 );
			},
			frame: function ( api, dt, t ) {
				var THREE = api.THREE;
				ptr = null;
				if ( api.pointer ) { ray.setFromCamera( new THREE.Vector2( api.pointer.x, api.pointer.y ), api.camera ); if ( ray.ray.intersectPlane( plane0, hitV ) ) { ptr = [ hitV.x, hitV.y ]; } }
				var drive = ctx.attr( 'data-tdg-drive', 'continuous' ), ph = ( drive === 'static' ? 0 : api.progress() * Math.PI * 2 ), sig = cw * 0.9;
				api.cards.forEach( function ( m, i ) {
					var sp = springs[ i ], cx = m.position.x, cy = m.position.y;
					// Spring toward how close the pointer is: it overshoots and settles, which is the wobble.
					var near = ptr ? Math.exp( -( ( ptr[ 0 ] - cx ) * ( ptr[ 0 ] - cx ) + ( ptr[ 1 ] - cy ) * ( ptr[ 1 ] - cy ) ) / ( sig * sig * 1.4 ) ) : 0;
					sp.v += ( ( near - sp.a ) * 90 - sp.v * ( 14 - wobble * 11 ) ) * dt;
					sp.a += sp.v * dt;
					sp.j = Math.max( 0, sp.j - dt * 0.8 );
					var ap = EASE( m.userData.appear ), g = m.geometry, b = g.userData.base, p = g.attributes.position.array;
					var idle = wave * 0.12 * Math.sin( cx * 1.3 - ph + cy * 0.7 );
					for ( var v = 0; v < b.length; v += 3 ) {
						var x0 = b[ v ], y0 = b[ v + 1 ], uu = x0 / cw + 0.5, vv = y0 / ch + 0.5, dome = Math.sin( uu * Math.PI ) * Math.sin( vv * Math.PI );
						var bulge = 0;
						if ( ptr ) { var dx = ptr[ 0 ] - ( cx + x0 ), dy = ptr[ 1 ] - ( cy + y0 ); bulge = Math.exp( -( dx * dx + dy * dy ) / ( sig * sig * 0.35 ) ); }
						var z = jelly * ( 0.85 * sp.a * dome + 0.4 * bulge * dome ) + idle * dome;
						z += wobble * 0.18 * ( Math.abs( sp.v ) * 0.12 + sp.j ) * Math.sin( uu * Math.PI * 2 + t * 13 ) * Math.sin( vv * Math.PI );
						p[ v ] = x0 * ( 1 + 0.1 * jelly * sp.a ); p[ v + 1 ] = y0 * ( 1 + 0.1 * jelly * sp.a ); p[ v + 2 ] = z;
					}
					g.attributes.position.needsUpdate = true;
					g.computeVertexNormals();
					m.material.opacity = ap;
					m.position.z = -( 1 - ap ) * 1.2;
				} );
			}
		} );
	} );

	/* ---------------------------------------------------------------- Stage Curtain */
	window.upwGallery3d.registerDesign( 'webgl_stage_curtain', function ( ctx ) {
		if ( ! ctx.cards.length ) { return; }
		var colr = ctx.attr( 'data-tdg-curtain', '#7a1424' ), pleats = Math.max( 4, Math.min( 20, ctx.num( 'data-tdg-pleats', 10 ) ) );
		var openTo = ctx.num( 'data-tdg-open', 88 ) / 100, swag = ctx.num( 'data-tdg-swag', 50 ) / 100, valance = ctx.attr( 'data-tdg-valance', '1' ) === '1';
		var mt = Math.max( 0.1, Math.min( 0.9, ctx.num( 'data-tdg-movetime', 50 ) / 100 ) );
		var n = ctx.cards.length, photos = [], texs = [], back, halves = [], st, halfW = 1, halfH = 1, pa = 1.6, SX = 48, SY = 16, val = null;
		var cover = function ( k ) {
			var t = texs[ k ], ia = photos[ k ].aspect;
			if ( pa > ia ) { t.repeat.set( 1, ia / pa ); t.offset.set( 0, ( 1 - ia / pa ) / 2 ); } else { t.repeat.set( pa / ia, 1 ); t.offset.set( ( 1 - pa / ia ) / 2, 0 ); }
			return t;
		};
		mount( ctx, {
			build: function ( api ) {
				var THREE = api.THREE;
				ctx.cards.forEach( function ( c, k ) {
					var tex = new THREE.Texture(); tex.generateMipmaps = false; tex.minFilter = THREE.LinearFilter;
					var p = loadPhoto( fullSrc( c ), Math.round( k * 360 / n ), function ( pp ) { swapImage( tex, pp.el ); api.dirty = true; } );
					tex.image = p.el; tex.needsUpdate = true; photos.push( p ); texs.push( tex );
				} );
				back = new THREE.Mesh( new THREE.PlaneGeometry( 1, 1 ), new THREE.MeshBasicMaterial( { map: texs[ 0 ], transparent: true, toneMapped: false } ) );
				back.userData.card = 0; back.userData.appear = 0;
				api.cards.push( back ); api.scene.add( back );
				var velvet = new THREE.MeshStandardMaterial( { color: new THREE.Color( colr ).convertSRGBToLinear(), roughness: 0.95, metalness: 0, side: THREE.DoubleSide } );
				for ( var s = 0; s < 2; s++ ) {
					var g = new THREE.PlaneGeometry( 1, 1, SX, SY );
					var m = new THREE.Mesh( g, velvet ); m.userData.side = s ? 1 : -1;
					halves.push( m ); api.scene.add( m );
				}
				if ( valance ) { val = new THREE.Mesh( new THREE.BoxGeometry( 1, 1, 0.12, 64, 1, 1 ), velvet ); api.scene.add( val ); }
				api.key.position.set( -5, 3, 3.5 ); api.key.intensity *= 1.3;   // from the side, so the pleats shade
				st = stepper( api, ctx, n, mt, n );
				ctx.cards.forEach( function ( c, k ) { c.addEventListener( 'focusin', function () { st.go( k ); } ); } );
			},
			resize: function ( api ) {
				api.camera.position.set( 0, 0, 5 ); api.camera.lookAt( 0, 0, 0 );
				halfH = 5 * Math.tan( api.camera.fov * Math.PI / 360 ); halfW = halfH * api.camera.aspect; pa = api.w / Math.max( 1, api.h );
				back.scale.set( halfW * 2, halfH * 2, 1 );
				if ( val ) {
					val.scale.set( halfW * 2.1, halfH * 0.16, 1 ); val.position.set( 0, halfH - halfH * 0.06, 0.45 );
					var vp = val.geometry.attributes.position;   // a pleated valance: scallop its front face
					for ( var i = 0; i < vp.count; i++ ) { if ( vp.getZ( i ) > 0 ) { vp.setZ( i, 0.06 + 0.04 * Math.abs( Math.sin( vp.getX( i ) * Math.PI * 18 ) ) ); } }
					vp.needsUpdate = true; val.geometry.computeVertexNormals();
				}
			},
			frame: function ( api, dt, t ) {
				var s = st.state( t ), a = mod( s.a, n ), b = mod( s.b, n ), e = s.e;
				// The curtain closes over the first half of the change, the photo swaps behind it, and it opens again.
				var shown = e < 0.5 ? a : b;
				back.material.map = cover( shown ); back.userData.card = shown;
				var ap = EASE( back.userData.appear );
				back.material.opacity = Math.min( 1, ap * 2 );
				var o = Math.abs( 1 - 2 * e ) * ap;                       // 1 = open, 0 = closed (also closed before the intro)
				// Open, each half stays gathered at its side of the stage (Opening sets how much of the stage it leaves clear).
				var W = halfW * 1.04, Hh = halfH * 1.04, k = pleats * Math.PI, inner = o * halfW * openTo * 0.84, gather = ( W - inner ) / W;
				halves.forEach( function ( m ) {
					var sd = m.userData.side, p = m.geometry.attributes.position;
					for ( var j = 0; j <= SY; j++ ) {
						var vy = 1 - j / SY;                               // 1 = top, 0 = bottom
						for ( var i = 0; i <= SX; i++ ) {
							var uu = i / SX;                               // 0 = outer edge, 1 = the inner (meeting) edge
							// Gathered toward the outer edge as it opens; the inner bottom corner lifts into a swag.
							var lift = o * swag * 0.55 * Math.pow( uu, 1.6 ) * Math.pow( 1 - vy, 2 );
							var x = sd * ( W - uu * ( W - inner ) );
							var y = -Hh + vy * Hh * 2 + lift * Hh * 2 * ( 1 - vy * 0.2 );
							var depth = 0.07 + 0.1 * ( 1 - gather );
							var z = 0.35 + depth * Math.sin( uu * k * gather + t * 0.6 * ( 1 - o ) ) + 0.02 * Math.sin( vy * 6 + t * 0.8 + uu * 3 );
							p.setXYZ( j * ( SX + 1 ) + ( sd > 0 ? SX - i : i ), x, y, z );
						}
					}
					p.needsUpdate = true;
					m.geometry.computeVertexNormals();
				} );
			}
		} );
	} );
	/* ---------------------------------------------------------------- Gallery Walk */
	window.upwGallery3d.registerDesign( 'webgl_gallery_walk', function ( ctx ) {
		if ( ! ctx.cards.length ) { return; }
		var wallCol = ctx.attr( 'data-tdg-wall', '#e9e4dc' ), floorCol = ctx.attr( 'data-tdg-floor', '#3a3029' ), frameCol = ctx.attr( 'data-tdg-frame', '#1c1c1c' );
		var mat = ctx.attr( 'data-tdg-mat', '1' ) === '1', look = ctx.num( 'data-tdg-look', 40 ) / 100, spacing = ctx.num( 'data-tdg-spacing', 100 ) / 100;
		var dir = ctx.num( 'data-tdg-dir', 1 ), lit = ctx.num( 'data-tdg-pools', 60 ) / 100;
		var n = ctx.cards.length, M = 7, W = 2.2, S, ph, pw = 1.1, slots = [], texs = [], yaw = 0, pitch = 0;
		mount( ctx, {
			build: function ( api ) {
				var THREE = api.THREE;
				ph = pw * api.aspect; S = ( pw + 1.4 ) * spacing + 0.6;
				var lin = function ( c ) { return new THREE.Color( c ).convertSRGBToLinear(); };
				var src = ctx.cards.map( function ( c ) { return fullSrc( c ); } );
				texs = src.map( function ( s, k ) { return cardTexture( s, api.aspect, 0, function () { api.dirty = true; }, s ? null : Math.round( k * 360 / n ) ); } );
				// The room: floor, two walls and a ceiling, long enough to look endless in the fog.
				var L = S * M * 2 + 20, room = new THREE.Group();
				var wallM = new THREE.MeshStandardMaterial( { color: lin( wallCol ), roughness: 0.95 } );
				var floor = new THREE.Mesh( new THREE.PlaneGeometry( W * 2, L ), new THREE.MeshStandardMaterial( { color: lin( floorCol ), roughness: 0.32, metalness: 0.1 } ) );
				floor.rotation.x = -Math.PI / 2; floor.position.set( 0, -1.3, -L / 2 + 6 );
				var ceil = new THREE.Mesh( new THREE.PlaneGeometry( W * 2, L ), new THREE.MeshStandardMaterial( { color: lin( wallCol ).multiplyScalar( 0.7 ), roughness: 1 } ) );
				ceil.rotation.x = Math.PI / 2; ceil.position.set( 0, 1.9, -L / 2 + 6 );
				var wl = new THREE.Mesh( new THREE.PlaneGeometry( L, 3.2 ), wallM ); wl.rotation.y = Math.PI / 2; wl.position.set( -W, 0.3, -L / 2 + 6 );
				var wr = new THREE.Mesh( new THREE.PlaneGeometry( L, 3.2 ), wallM ); wr.rotation.y = -Math.PI / 2; wr.position.set( W, 0.3, -L / 2 + 6 );
				// An end wall far down the corridor, so the haze ends in the room's own colour.
				var endW = new THREE.Mesh( new THREE.PlaneGeometry( W * 2, 3.2 ), wallM ); endW.position.set( 0, 0.3, -L + 6 );
				room.add( floor, ceil, wl, wr, endW );
				api.scene.add( room );
				// A soft pool of light on the wall around each picture.
				var cv = document.createElement( 'canvas' ); cv.width = cv.height = 128;
				var g = cv.getContext( '2d' ), gr = g.createRadialGradient( 64, 54, 4, 64, 64, 64 );
				gr.addColorStop( 0, 'rgba(255,244,220,0.9)' ); gr.addColorStop( 1, 'rgba(255,244,220,0)' );
				g.fillStyle = gr; g.fillRect( 0, 0, 128, 128 );
				var poolTex = new THREE.CanvasTexture( cv );
				var frameM = new THREE.MeshStandardMaterial( { color: lin( frameCol ), roughness: 0.45, metalness: 0.3 } );
				var matM = new THREE.MeshStandardMaterial( { color: 0xf6f4ef, roughness: 0.9 } );
				for ( var j = 0; j < M; j++ ) {
					for ( var side = -1; side <= 1; side += 2 ) {
						var grp = new THREE.Group();
						var fw = pw * ( mat ? 1.36 : 1.08 ), fh = ph + pw * ( mat ? 0.36 : 0.08 );
						var frame = new THREE.Mesh( new THREE.BoxGeometry( fw, fh, 0.06 ), frameM ); frame.position.z = -0.03;
						grp.add( frame );
						if ( mat ) { var m2 = new THREE.Mesh( new THREE.PlaneGeometry( pw * 1.28, ph + pw * 0.28 ), matM ); m2.position.z = 0.002; grp.add( m2 ); }
						var photo = new THREE.Mesh( new THREE.PlaneGeometry( pw, ph ), new THREE.MeshStandardMaterial( { map: texs[ 0 ], roughness: 0.5 } ) );
						photo.position.z = 0.006; grp.add( photo );
						var pool = new THREE.Mesh( new THREE.PlaneGeometry( fw * 2.2, fh * 2.2 ), new THREE.MeshBasicMaterial( { map: poolTex, transparent: true, opacity: lit * 0.55, depthWrite: false, blending: THREE.AdditiveBlending } ) );
						pool.position.z = -0.065; grp.add( pool );
						grp.rotation.y = side < 0 ? Math.PI / 2 : -Math.PI / 2;
						photo.userData.card = 0; photo.userData.appear = 0;
						api.cards.push( photo );
						api.scene.add( grp );
						slots.push( { g: grp, photo: photo, side: side, j: j } );
					}
				}
				try { api.scene.fog = new THREE.Fog( lin( wallCol ).multiplyScalar( 0.55 ), S * 2.2, S * ( M - 1.2 ) ); } catch ( e ) {}
				api.key.position.set( 0, 5, 3 ); api.key.intensity *= 0.8;
			},
			resize: function ( api ) { api.camera.fov = 55; api.camera.updateProjectionMatrix(); },
			frame: function ( api, dt ) {
				var drive = ctx.attr( 'data-tdg-drive', 'continuous' ), pairs = Math.max( 1, Math.ceil( n / 2 ) );
				var pos = ( drive === 'static' ? 0 : api.progress() * pairs * dir ), base = Math.floor( pos ), fr = pos - base;
				slots.forEach( function ( s ) {
					var k = base + s.j, photo = mod( 2 * k + ( s.side > 0 ? 1 : 0 ), n );
					s.photo.material.map = texs[ photo ]; s.photo.userData.card = photo;
					s.g.position.set( s.side * ( W - 0.01 ), 0.15, -( s.j - fr + 0.6 ) * S );
					var ap = EASE( s.photo.userData.appear );
					s.g.visible = ap > 0.02;
					s.g.scale.setScalar( 0.85 + 0.15 * ap );
				} );
				// Look round toward the pointer.
				var ty = api.pointer ? -api.pointer.x * look * 0.6 : 0, tp = api.pointer ? api.pointer.y * look * 0.25 : 0;
				yaw += ( ty - yaw ) * Math.min( 1, dt * 3 ); pitch += ( tp - pitch ) * Math.min( 1, dt * 3 );
				api.camera.position.set( 0, 0.15, 0 );
				api.camera.rotation.set( pitch, yaw, 0, 'YXZ' );
			}
		} );
	} );

	/* ---------------------------------------------------------------- Holo Cards */
	var HOLO_FS = [
		'uniform sampler2D map; uniform float foil, mode, glare, time, op, tiltAmt; uniform vec2 tilt, gpos;',
		'varying vec2 vUv;',
		'float hh( vec2 q ) { return fract( sin( dot( q, vec2( 12.9898, 78.233 ) ) ) * 43758.5453 ); }',
		'void main() {',
		'  vec4 base = texture2D( map, vUv ); if ( base.a < 0.02 ) { discard; }',
		'  float ang = dot( tilt, vec2( 1.3, 0.8 ) ) + ( vUv.x * 0.9 + vUv.y * 0.6 ) + time * 0.04;',
		'  vec3 rainbow = 0.5 + 0.5 * cos( 6.2832 * ( ang + vec3( 0.0, 0.33, 0.67 ) ) );',
		'  float mask = 1.0;',
		'  if ( mode > 0.5 && mode < 1.5 ) { vec2 c = floor( vUv * 70.0 ); float s = hh( c ); mask = step( 0.86, s ) * ( 0.5 + 0.5 * sin( time * 3.0 + s * 40.0 + ang * 9.0 ) ) * 2.0; }',
		'  else if ( mode > 1.5 ) { mask = 0.5 + 0.5 * sin( ( vUv.x + vUv.y ) * 140.0 + ang * 12.0 ); }',
		'  float amt = foil * ( 0.3 + 0.7 * tiltAmt ) * mask;',
		'  vec3 col = 1.0 - ( 1.0 - base.rgb ) * ( 1.0 - rainbow * amt * 0.55 );',        // screen-blend the foil over the photo
		'  vec2 d = vUv - gpos; col += exp( -dot( d, d ) * 9.0 ) * glare * 0.55;',          // the glare follows the pointer
		'  gl_FragColor = vec4( col, base.a * op );',
		'}'
	].join( '\n' );

	window.upwGallery3d.registerDesign( 'webgl_holo_cards', function ( ctx ) {
		if ( ! ctx.cards.length ) { return; }
		var cols = Math.max( 1, Math.min( 8, ctx.num( 'data-tdg-cols', 4 ) ) ), rows = Math.max( 1, Math.min( 4, ctx.num( 'data-tdg-rows', 1 ) ) );
		var gap = ctx.num( 'data-tdg-gap', 10 ) / 100, foil = ctx.num( 'data-tdg-foil', 70 ) / 100, tiltMax = ctx.num( 'data-tdg-tilt', 18 ) * Math.PI / 180;
		var glare = ctx.num( 'data-tdg-glare', 50 ) / 100, pattern = { rainbow: 0, sparkle: 1, lines: 2 }[ ctx.attr( 'data-tdg-pattern', 'rainbow' ) ] || 0;
		var n = ctx.cards.length, cw = 1, ch, W, H, ray, plane0, hitV, state = [];
		mount( ctx, {
			build: function ( api ) {
				var THREE = api.THREE;
				ch = cw * api.aspect; W = cols * cw * ( 1 + gap ); H = rows * ch * ( 1 + gap );
				var radFrac = Math.min( 0.4, radiusOf( ctx.el ) / Math.max( 60, ( ctx.stage.clientWidth || 800 ) / ( cols + 1 ) ) );
				var texs = ctx.cards.map( function ( c, k ) {
					var s = fullSrc( c ), t = cardTexture( s, api.aspect, radFrac, function () { api.dirty = true; }, s ? null : Math.round( k * 360 / n ) );
					t.encoding = THREE.LinearEncoding;   // raw colours into the foil shader
					return t;
				} );
				for ( var i = 0; i < cols * rows; i++ ) {
					var u = { map: { value: texs[ i % n ] }, foil: { value: foil }, mode: { value: pattern }, glare: { value: glare }, time: { value: 0 }, op: { value: 1 }, tiltAmt: { value: 0 }, tilt: { value: new THREE.Vector2() }, gpos: { value: new THREE.Vector2( 0.5, 0.5 ) } };
					var m = new THREE.Mesh( new THREE.PlaneGeometry( cw, ch ), new THREE.ShaderMaterial( { uniforms: u, vertexShader: PLAIN_VS, fragmentShader: HOLO_FS, transparent: true, toneMapped: false } ) );
					m.userData.card = i % n; m.userData.appear = 0; m.userData.u = u;
					m.userData.home = new THREE.Vector3( ( i % cols - ( cols - 1 ) / 2 ) * cw * ( 1 + gap ), ( ( rows - 1 ) / 2 - Math.floor( i / cols ) ) * ch * ( 1 + gap ), 0 );
					api.cards.push( m ); api.scene.add( m );
					state.push( { rx: 0, ry: 0, lift: 0, gx: 0.5, gy: 0.5 } );
				}
				ray = new THREE.Raycaster(); plane0 = new THREE.Plane( new THREE.Vector3( 0, 0, 1 ), 0 ); hitV = new THREE.Vector3();
			},
			resize: function ( api ) {
				var tanH = Math.tan( api.camera.fov * Math.PI / 360 );
				var dist = Math.max( W / api.camera.aspect, H ) * 1.15 / ( 2 * tanH );
				api.camera.position.set( 0, 0, dist ); api.camera.lookAt( 0, 0, 0 );
			},
			frame: function ( api, dt, t ) {
				var THREE = api.THREE, ptr = null;
				if ( api.pointer ) { ray.setFromCamera( new THREE.Vector2( api.pointer.x, api.pointer.y ), api.camera ); if ( ray.ray.intersectPlane( plane0, hitV ) ) { ptr = [ hitV.x, hitV.y ]; } }
				var drive = ctx.attr( 'data-tdg-drive', 'continuous' ), ph = drive === 'static' ? 0 : api.progress() * Math.PI * 2;
				api.cards.forEach( function ( m, i ) {
					var s = state[ i ], h = m.userData.home, u = m.userData.u;
					var lx = ptr ? ( ptr[ 0 ] - h.x ) / ( cw * 0.5 ) : 0, ly = ptr ? ( ptr[ 1 ] - h.y ) / ( ch * 0.5 ) : 0;
					var over = ptr && Math.abs( lx ) <= 1.15 && Math.abs( ly ) <= 1.15;
					// Over a card: it tilts toward the pointer; otherwise it sways gently with the motion, so the foil still shimmers.
					var tx = over ? -ly * tiltMax : Math.sin( ph + i * 0.9 ) * tiltMax * 0.35, ty = over ? lx * tiltMax : Math.cos( ph * 0.8 + i * 1.3 ) * tiltMax * 0.35;
					var k = Math.min( 1, dt * 8 );
					s.rx += ( tx - s.rx ) * k; s.ry += ( ty - s.ry ) * k; s.lift += ( ( over ? 1 : 0 ) - s.lift ) * k;
					s.gx += ( ( over ? ( lx + 1 ) / 2 : 0.5 + s.ry / tiltMax * 0.3 ) - s.gx ) * k; s.gy += ( ( over ? ( ly + 1 ) / 2 : 0.5 - s.rx / tiltMax * 0.3 ) - s.gy ) * k;
					var ap = EASE( m.userData.appear );
					m.rotation.set( s.rx, s.ry, 0 );
					m.position.set( h.x, h.y - ( 1 - ap ) * 0.4, s.lift * 0.25 );
					m.scale.setScalar( 1 + s.lift * 0.06 );
					u.tilt.value.set( s.ry / Math.max( 0.01, tiltMax ), s.rx / Math.max( 0.01, tiltMax ) );
					u.tiltAmt.value = Math.min( 1, Math.sqrt( s.rx * s.rx + s.ry * s.ry ) / Math.max( 0.01, tiltMax ) );
					u.gpos.value.set( s.gx, s.gy ); u.time.value = t; u.op.value = ap;
				} );
			}
		} );
	} );

	/* ---------------------------------------------------------------- Domino Row */
	window.upwGallery3d.registerDesign( 'webgl_domino_row', function ( ctx ) {
		if ( ! ctx.cards.length ) { return; }
		var count = Math.max( 4, Math.min( 16, ctx.num( 'data-tdg-count', 8 ) ) ), spacing = ctx.num( 'data-tdg-spacing', 50 ) / 100;
		var view = ctx.num( 'data-tdg-view', 55 ), height = ctx.num( 'data-tdg-camh', 18 ), bodyCol = ctx.attr( 'data-tdg-body', '#f4f2ee' );
		var shadows = ctx.attr( 'data-tdg-shadows', '1' ) === '1';
		var n = ctx.cards.length, cw = 1, ch, th, gapX, dominoes = [], key, span;
		mount( ctx, {
			build: function ( api ) {
				var THREE = api.THREE;
				ch = cw * api.aspect; th = cw * 0.1; gapX = ch * spacing;
				span = ( count - 1 ) * ( th + gapX );
				var body = new THREE.MeshStandardMaterial( { color: new THREE.Color( bodyCol ).convertSRGBToLinear(), roughness: 0.5 } );
				var radFrac = Math.min( 0.3, radiusOf( ctx.el ) / 200 );
				var texs = ctx.cards.map( function ( c, k ) { var s = fullSrc( c ); return cardTexture( s, api.aspect, radFrac, function () { api.dirty = true; }, s ? null : Math.round( k * 360 / n ) ); } );
				for ( var i = 0; i < count; i++ ) {
					var k = i % n;
					// Box faces: +x, -x, +y, -y, +z, -z. The photo is on the face toward the camera (−x) and on the back (+x).
					var photoM = new THREE.MeshStandardMaterial( { map: texs[ k ], roughness: 0.45 } );
					var box = new THREE.Mesh( new THREE.BoxGeometry( th, ch, cw ), [ photoM, photoM, body, body, body, body ] );
					box.position.set( -th / 2, ch / 2, 0 );             // the pivot (group origin) is the front-bottom edge
					box.castShadow = true; box.receiveShadow = true;
					var pivot = new THREE.Group(); pivot.add( box );
					pivot.position.set( -span / 2 + i * ( th + gapX ) + th, 0, 0 );
					box.userData.card = k; box.userData.appear = 0;
					api.cards.push( box ); api.scene.add( pivot );
					dominoes.push( pivot );
				}
				var floor = new THREE.Mesh( new THREE.PlaneGeometry( span * 4 + 10, 12 ), new THREE.ShadowMaterial( { opacity: 0.22 } ) );
				floor.rotation.x = -Math.PI / 2; floor.receiveShadow = true;
				api.scene.add( floor );
				key = api.key; key.position.set( -2, 6, 4 );
				if ( shadows ) {
					key.castShadow = true; key.shadow.mapSize.set( 1024, 1024 );
					var sc = key.shadow.camera; sc.left = -span / 2 - 3; sc.right = span / 2 + 3; sc.top = 4; sc.bottom = -4; sc.near = 0.5; sc.far = 20; sc.updateProjectionMatrix();
					key.shadow.bias = -0.0008;
				}
			},
			resize: function ( api ) {
				// Fit the camera to the row's corners (standing, plus the last domino lying flat): back away along the
				// view direction until every corner sits inside the frame, whatever the View Angle and Camera Height.
				var THREE = api.THREE, va = view * Math.PI / 180, ha = height * Math.PI / 180;
				var x0 = -span / 2 - th, x1 = span / 2 + th + ch, cx = ( x0 + x1 ) / 2, cy = ch * 0.45;
				var dirV = new THREE.Vector3( -Math.sin( va ) * Math.cos( ha ), Math.sin( ha ), Math.cos( va ) * Math.cos( ha ) ).normalize();
				var corners = [];
				[ x0, x1 ].forEach( function ( x ) { [ 0, ch ].forEach( function ( y ) { [ -cw / 2, cw / 2 ].forEach( function ( z ) { corners.push( new THREE.Vector3( x, y, z ) ); } ); } ); } );
				// The near end of the row projects larger and lower, so centre the projected row with a view offset and
				// come in from far away until it fills the frame.
				var v = new THREE.Vector3(), cam = api.camera;
				var fit = function ( dist ) {
					cam.clearViewOffset();
					cam.position.set( cx + dirV.x * dist, cy + dirV.y * dist, dirV.z * dist );
					cam.lookAt( cx, cy, 0 ); cam.updateMatrixWorld();
					var mnx = 9, mxx = -9, mny = 9, mxy = -9, ok = true;
					corners.forEach( function ( c ) { v.copy( c ).project( cam ); if ( v.z >= 1 ) { ok = false; } mnx = Math.min( mnx, v.x ); mxx = Math.max( mxx, v.x ); mny = Math.min( mny, v.y ); mxy = Math.max( mxy, v.y ); } );
					return { ok: ok, mx: ( mnx + mxx ) / 2, my: ( mny + mxy ) / 2, hw: ( mxx - mnx ) / 2, hh: ( mxy - mny ) / 2 };
				};
				var dist = 80, best = null, r;
				for ( var it = 0; it < 140; it++ ) {
					r = fit( dist );
					if ( ! r.ok || r.hw > 0.86 || r.hh > 0.8 ) { break; }
					best = { d: dist, r: r }; dist *= 0.96;
				}
				if ( ! best ) { best = { d: 80, r: fit( 80 ) }; }
				fit( best.d );
				cam.setViewOffset( api.w, api.h, best.r.mx * api.w / 2, -best.r.my * api.h / 2, api.w, api.h );
			},
			frame: function ( api ) {
				if ( shadows && api.renderer && ! api.renderer.shadowMap.enabled ) { api.renderer.shadowMap.enabled = true; api.renderer.shadowMap.type = api.THREE.PCFSoftShadowMap; }
				// One cycle: they topple one after another, rest, stand back up from the far end, rest.
				var drive = ctx.attr( 'data-tdg-drive', 'continuous' ), p, fall;
				if ( drive === 'scroll' ) { fall = Math.max( 0, Math.min( 1, api.progress() ) ); p = -1; }
				else if ( drive === 'static' ) { fall = 0; p = -1; }
				else { p = api.progress(); p -= Math.floor( p ); }
				var lean = Math.asin( Math.min( 1, ( gapX + th * 0.2 ) / ch ) );
				dominoes.forEach( function ( d, i ) {
					var q;
					if ( p < 0 ) { q = Math.max( 0, Math.min( 1, fall * ( count + 1.5 ) - i ) ); }
					else if ( p < 0.42 ) { q = Math.max( 0, Math.min( 1, ( p / 0.42 ) * ( count + 1.5 ) - i ) ); }
					else if ( p < 0.55 ) { q = 1; }
					else if ( p < 0.88 ) { q = 1 - Math.max( 0, Math.min( 1, ( ( p - 0.55 ) / 0.33 ) * ( count + 1.5 ) - ( count - 1 - i ) ) ); }
					else { q = 0; }
					var a = ( i === count - 1 ? Math.PI / 2 - th / ch : lean ) * q * q;          // falls faster as it goes over
					d.rotation.z = -a;
					var ap = EASE( api.cards[ i ].userData.appear );
					d.position.y = ( 1 - ap ) * 0.6; d.visible = ap > 0.01;
				} );
			}
		} );
	} );
	/* ---------------------------------------------------------------- Photo Bubbles */
	var BUBBLE_FS = [
		'uniform sampler2D map; uniform float op, irid, time, seed;',
		'varying vec2 vUv;',
		'void main() {',
		'  vec2 c = vUv - 0.5; float r = length( c ) * 2.0; if ( r > 1.0 ) { discard; }',
		'  vec2 uvp = 0.5 + c * ( 0.82 + 0.18 * r * r );',                                      // the bubble bulges the photo like a lens
		'  vec3 photo = texture2D( map, uvp ).rgb;',
		'  float f = smoothstep( 0.5, 1.0, r ); float ang = atan( c.y, c.x );',
		'  vec3 film = 0.5 + 0.5 * cos( 6.2832 * ( r * 1.4 + ang * 0.16 + time * 0.05 + seed + vec3( 0.0, 0.33, 0.67 ) ) );',
		'  vec3 col = mix( photo, film, f * irid * 0.7 );',                                       // thin-film colours toward the rim
		'  col *= 1.0 - f * 0.18;',
		'  vec2 hd = c - vec2( -0.17, 0.19 ); col += exp( -dot( hd, hd ) * 90.0 ) * 0.85;',        // the window highlight
		'  col += exp( -pow( ( r - 0.93 ) * 18.0, 2.0 ) ) * 0.22;',                             // a bright rim
		'  gl_FragColor = vec4( col, smoothstep( 1.0, 0.965, r ) * op );',
		'}'
	].join( '\n' );

	window.upwGallery3d.registerDesign( 'webgl_photo_bubbles', function ( ctx ) {
		if ( ! ctx.cards.length ) { return; }
		var count = Math.max( 4, Math.min( 30, ctx.num( 'data-tdg-count', 12 ) ) ), size = ctx.num( 'data-tdg-card', 22 ) / 100;
		var irid = ctx.num( 'data-tdg-irid', 70 ) / 100, depth = ctx.num( 'data-tdg-depth', 50 ) / 100, wob = ctx.num( 'data-tdg-wobble', 50 ) / 100;
		var push = ctx.num( 'data-tdg-push', 50 ) / 100, dir = ctx.num( 'data-tdg-dir', 1 );
		var n = ctx.cards.length, seeds = [], halfW = 1, halfH = 1, ray, plane0, hitV;
		var rand = function ( i, k ) { var x = Math.sin( i * 78.233 + k * 12.9898 ) * 43758.5453; return x - Math.floor( x ); };
		mount( ctx, {
			build: function ( api ) {
				var THREE = api.THREE;
				var texs = ctx.cards.map( function ( c, k ) { var s = fullSrc( c ), t = cardTexture( s, 1, 0, function () { api.dirty = true; }, s ? null : Math.round( k * 360 / n ) ); t.encoding = THREE.LinearEncoding; return t; } );
				var geo = new THREE.PlaneGeometry( 1, 1 );
				for ( var i = 0; i < count; i++ ) {
					var u = { map: { value: texs[ i % n ] }, op: { value: 1 }, irid: { value: irid }, time: { value: 0 }, seed: { value: rand( i, 9 ) } };
					var m = new THREE.Mesh( geo, new THREE.ShaderMaterial( { uniforms: u, vertexShader: PLAIN_VS, fragmentShader: BUBBLE_FS, transparent: true, depthWrite: false, toneMapped: false } ) );
					m.userData.card = i % n; m.userData.appear = 0; m.userData.u = u;
					api.cards.push( m ); api.scene.add( m );
					// Even spread across the width (golden-ratio steps), a staggered start height, a depth and a size.
					seeds.push( { x: ( i * 0.6180339887 + 0.13 ) % 1, y: ( i + rand( i, 1 ) * 0.7 ) / count, z: ( rand( i, 2 ) - 0.5 ) * 2, s: 0.65 + rand( i, 3 ) * 0.7, ph: rand( i, 4 ) * 6.28, px: 0, py: 0, hov: 0 } );
				}
				ray = new THREE.Raycaster(); plane0 = new THREE.Plane( new THREE.Vector3( 0, 0, 1 ), 0 ); hitV = new THREE.Vector3();
			},
			resize: function ( api ) {
				api.camera.position.set( 0, 0, 6 ); api.camera.lookAt( 0, 0, 0 );
				halfH = 6 * Math.tan( api.camera.fov * Math.PI / 360 ); halfW = halfH * api.camera.aspect;
			},
			frame: function ( api, dt, t ) {
				var THREE = api.THREE, drive = ctx.attr( 'data-tdg-drive', 'continuous' ), ptr = null;
				var rise = drive === 'static' ? 0 : api.progress() * dir;
				if ( api.pointer ) { ray.setFromCamera( new THREE.Vector2( api.pointer.x, api.pointer.y ), api.camera ); if ( ray.ray.intersectPlane( plane0, hitV ) ) { ptr = [ hitV.x, hitV.y ]; } }
				var base = halfW * 2 * size;
				// Back to front, so nearer bubbles draw over farther ones.
				api.cards.forEach( function ( m, i ) {
					var s = seeds[ i ], u = m.userData.u, ap = EASE( m.userData.appear );
					var z = s.z * depth * 2.2, k = 6 / ( 6 - z );                         // perspective scale of this depth
					var span = halfH * 2 + base * 1.6;
					var y = ( ( ( s.y + rise ) % 1 ) + 1 ) % 1 * span - span / 2;
					var x = ( s.x * 2 - 1 ) * ( halfW - base * 0.4 ) / k + Math.sin( t * 0.7 + s.ph ) * wob * base * 0.25;
					// The pointer nudges nearby bubbles away; they drift back.
					if ( ptr && push > 0 ) {
						var dx = x * k - ptr[ 0 ], dy = y * k - ptr[ 1 ], d2 = dx * dx + dy * dy, r = base * 1.2;
						if ( d2 < r * r * 4 ) { var f = push * 0.9 * Math.exp( -d2 / ( r * r ) ); s.px += dx * f * dt * 3; s.py += dy * f * dt * 3; }
					}
					s.px *= 0.97; s.py *= 0.97;
					s.hov += ( ( api.hover === i ? 1 : 0 ) - s.hov ) * Math.min( 1, dt * 6 );
					m.position.set( x + s.px, y + s.py, z );
					var sz = base * s.s * ( 1 + s.hov * 0.12 ) * ( 0.6 + 0.4 * ap ) * ( 1 + Math.sin( t * 1.3 + s.ph ) * 0.025 * wob );
					m.scale.set( sz * ( 1 + Math.sin( t * 2.1 + s.ph ) * 0.03 * wob ), sz * ( 1 - Math.sin( t * 2.1 + s.ph ) * 0.03 * wob ), 1 );
					m.renderOrder = Math.round( ( z + 10 ) * 10 );
					u.op.value = ap * Math.min( 1, ( span / 2 - Math.abs( y ) ) / ( base * 0.6 ) + 0.2 );
					u.time.value = t;
				} );
			}
		} );
	} );

	/* ---------------------------------------------------------------- Photo Cube */
	window.upwGallery3d.registerDesign( 'webgl_photo_cube', function ( ctx ) {
		if ( ! ctx.cards.length ) { return; }
		var size = ctx.num( 'data-tdg-card', 55 ) / 100, tumble = ctx.attr( 'data-tdg-tumble', 'turn' ), tilt = ctx.num( 'data-tdg-tilt', 18 );
		var mt = Math.max( 0.1, Math.min( 0.9, ctx.num( 'data-tdg-movetime', 40 ) / 100 ) ), bodyCol = ctx.attr( 'data-tdg-body', '#22252c' );
		var shadows = ctx.attr( 'data-tdg-shadows', '1' ) === '1', floatAmt = ctx.num( 'data-tdg-float', 40 ) / 100;
		var n = ctx.cards.length, cube, mats = [], texs = [], flips = [], st, E = 1;
		// The face that turns to the camera at each quarter-turn: box faces are +x, -x, +y, -y, +z, -z.
		var ORDER = tumble === 'roll' ? [ 4, 2, 5, 3 ] : [ 4, 1, 5, 0 ];
		mount( ctx, {
			build: function ( api ) {
				var THREE = api.THREE;
				var radFrac = Math.min( 0.2, radiusOf( ctx.el ) / 300 );
				// Rolling forward brings the back face (−z) round upside down and mirrored, so it wears a copy of
				// each photo turned half a turn (kept in step when the photo loads).
				texs = ctx.cards.map( function ( c, k ) { var s = fullSrc( c ); return cardTexture( s, 1, radFrac, function () { api.dirty = true; if ( flips[ k ] ) { flips[ k ].needsUpdate = true; } }, s ? null : Math.round( k * 360 / n ) ); } );
				flips = texs.map( function ( t ) { var f = t.clone(); f.center.set( 0.5, 0.5 ); f.rotation = Math.PI; f.needsUpdate = true; return f; } );
				var body = new THREE.Color( bodyCol ).convertSRGBToLinear();
				for ( var f = 0; f < 6; f++ ) { mats.push( new THREE.MeshStandardMaterial( { color: 0xffffff, map: texs[ 0 ], roughness: 0.42, metalness: 0.05, transparent: true } ) ); }
				// Faces that never turn to the camera (the ones on the turning axis) stay plain.
				( tumble === 'roll' ? [ 0, 1 ] : [ 2, 3 ] ).forEach( function ( f ) { mats[ f ].map = null; mats[ f ].color = body; } );
				cube = new THREE.Mesh( new THREE.BoxGeometry( E, E, E ), mats );
				cube.castShadow = true;
				cube.userData.card = 0; cube.userData.appear = 0;
				api.cards.push( cube ); api.scene.add( cube );
				var floor = new THREE.Mesh( new THREE.PlaneGeometry( 12, 12 ), new THREE.ShadowMaterial( { opacity: 0.25 } ) );
				floor.rotation.x = -Math.PI / 2; floor.position.y = -E * 0.85; floor.receiveShadow = true;
				api.scene.add( floor );
				var key = api.key; key.position.set( -2.5, 5, 4 );
				if ( shadows ) { key.castShadow = true; key.shadow.mapSize.set( 1024, 1024 ); var sc = key.shadow.camera; sc.left = sc.bottom = -3; sc.right = sc.top = 3; sc.near = 0.5; sc.far = 20; sc.updateProjectionMatrix(); key.shadow.radius = 4; }
				st = stepper( api, ctx, n, mt, n );
				ctx.cards.forEach( function ( c, k ) { c.addEventListener( 'focusin', function () { st.go( k ); } ); } );
			},
			resize: function ( api ) {
				var tanH = Math.tan( api.camera.fov * Math.PI / 360 ), ta = tilt * Math.PI / 180;
				var dist = ( E * 1.25 / size ) / ( 2 * tanH ) + E * 0.5;
				api.camera.position.set( 0, Math.sin( ta ) * dist, Math.cos( ta ) * dist );
				api.camera.lookAt( 0, -E * 0.08, 0 );
			},
			frame: function ( api, dt, t ) {
				if ( shadows && api.renderer && ! api.renderer.shadowMap.enabled ) { api.renderer.shadowMap.enabled = true; api.renderer.shadowMap.type = api.THREE.PCFSoftShadowMap; }
				var s = st.state( t ), q = s.a + ( s.b - s.a ) * s.e;
				// The face showing each step carries that step's photo (the one turning in, the next).
				[ s.a, s.b ].forEach( function ( k ) { var f = ORDER[ mod( k, 4 ) ]; mats[ f ].map = ( tumble === 'roll' && f === 5 ? flips : texs )[ mod( k, n ) ]; } );
				cube.userData.card = mod( s.e > 0.5 ? s.b : s.a, n );
				var ang = q * Math.PI / 2;
				if ( tumble === 'roll' ) { cube.rotation.set( ang, 0, 0 ); } else { cube.rotation.set( 0, ang, 0 ); }
				// A little lift at mid-turn (the cube's corner swings out), plus a gentle float.
				var ap = EASE( cube.userData.appear );
				cube.position.y = Math.sin( s.e * Math.PI ) * E * 0.12 + Math.sin( t * 1.2 ) * E * 0.03 * floatAmt - ( 1 - ap ) * 0.6;
				mats.forEach( function ( m ) { m.opacity = ap; } );
			}
		} );
	} );

	/* ---------------------------------------------------------------- Film Ribbon */
	window.upwGallery3d.registerDesign( 'webgl_film_ribbon', function ( ctx ) {
		if ( ! ctx.cards.length ) { return; }
		var vis = Math.max( 3, Math.min( 10, ctx.num( 'data-tdg-frames', 5 ) ) ), wave = ctx.num( 'data-tdg-wave', 50 ) / 100, twist = ctx.num( 'data-tdg-twist', 40 ) / 100;
		var filmCol = ctx.attr( 'data-tdg-film', '#16171b' ), holes = ctx.attr( 'data-tdg-holes', '1' ) === '1', dir = ctx.num( 'data-tdg-dir', 1 );
		var n = ctx.cards.length, photos = [], cv, g2, tex, strip, SEG = 240, L, Wd, frameAspect, ray, hoverUv = -1;
		var aspect = ratioOf( ctx.el );
		// The film: every photo in one strip canvas, with the dark film border and its sprocket holes.
		var draw = function () {
			var fw = Math.max( 160, Math.min( 512, Math.floor( 4096 / n ) ) ), ph = Math.round( fw * aspect ), bd = Math.round( fw * 0.16 ), H = ph + bd * 2;
			if ( cv.width !== fw * n || cv.height !== H ) { cv.width = fw * n; cv.height = H; }
			g2.fillStyle = filmCol; g2.fillRect( 0, 0, cv.width, H );
			for ( var k = 0; k < n; k++ ) {
				var p = photos[ k ], x = k * fw + fw * 0.05, w = fw * 0.9;
				var sw = p.el.naturalWidth || p.el.width, sh = p.el.naturalHeight || p.el.height, sc = Math.max( w / sw, ph / sh );
				g2.save(); g2.beginPath(); g2.rect( x, bd, w, ph ); g2.clip();
				g2.drawImage( p.el, x + ( w - sw * sc ) / 2, bd + ( ph - sh * sc ) / 2, sw * sc, sh * sc );
				g2.restore();
				if ( holes ) {
					g2.fillStyle = 'rgba(235,235,230,0.9)';
					for ( var hI = 0; hI < 4; hI++ ) {
						var hx = k * fw + fw * ( 0.1 + hI * 0.235 ), hw = fw * 0.1, hh = bd * 0.42;
						[ bd * 0.29, H - bd * 0.29 - hh ].forEach( function ( hy ) { g2.beginPath(); if ( g2.roundRect ) { g2.roundRect( hx, hy, hw, hh, hh * 0.25 ); } else { g2.rect( hx, hy, hw, hh ); } g2.fill(); } );
					}
				}
			}
			frameAspect = H / fw;
			if ( tex ) { tex.needsUpdate = true; }
		};
		mount( ctx, {
			build: function ( api ) {
				var THREE = api.THREE;
				ctx.cards.forEach( function ( c, k ) { photos.push( loadPhoto( fullSrc( c ), Math.round( k * 360 / n ), function () { draw(); api.dirty = true; } ) ); } );
				cv = document.createElement( 'canvas' ); g2 = cv.getContext( '2d' ); draw();
				tex = new THREE.CanvasTexture( cv ); tex.encoding = THREE.sRGBEncoding; tex.wrapS = THREE.RepeatWrapping; tex.anisotropy = 4;
				Wd = frameAspect; L = vis * 1.9;                                 // one frame = 1 unit along the film
				var geo = new THREE.PlaneGeometry( 1, 1, SEG, 1 );
				tex.repeat.set( L / n, 1 );
				strip = new THREE.Mesh( geo, new THREE.MeshStandardMaterial( { map: tex, roughness: 0.4, metalness: 0.15, side: THREE.DoubleSide, transparent: true } ) );
				strip.frustumCulled = false;
				strip.userData.card = 0; strip.userData.appear = 0;
				api.cards.push( strip ); api.scene.add( strip );
				ray = new THREE.Raycaster();
				api.key.position.set( 2, 5, 6 );
			},
			resize: function ( api ) {
				var tanH = Math.tan( api.camera.fov * Math.PI / 360 );
				var dist = Math.max( ( vis * 1.0 ) / api.camera.aspect, Wd * ( 2.2 + wave * 1.5 ) ) / ( 2 * tanH );
				api.camera.position.set( 0, 0.4, dist ); api.camera.lookAt( 0, 0, 0 );
			},
			frame: function ( api, dt, t ) {
				var THREE = api.THREE, drive = ctx.attr( 'data-tdg-drive', 'continuous' );
				var flow = drive === 'static' ? 0 : api.progress() * n * dir;
				tex.offset.x = ( flow / n ) % 1;
				// Lay the film along a gentle curve through space, twisting about its own length as it goes.
				var pos = strip.geometry.attributes.position, P = new THREE.Vector3(), Pn = new THREE.Vector3(), T = new THREE.Vector3(), N = new THREE.Vector3(), B = new THREE.Vector3(), up = new THREE.Vector3( 0, 1, 0 );
				var ph = t * 0.25;
				var path = function ( s, out ) { return out.set( s, Math.sin( s * 0.55 + ph ) * Wd * 0.9 * wave, Math.sin( s * 0.37 + 1.3 + ph * 0.7 ) * 1.6 * wave ); };
				for ( var i = 0; i <= SEG; i++ ) {
					var s = -L / 2 + L * i / SEG;
					path( s, P ); path( s + 0.01, Pn ); T.subVectors( Pn, P ).normalize();
					N.crossVectors( T, up ).normalize(); B.crossVectors( N, T );        // B ≈ up, across the film
					var tw = Math.sin( s * 0.45 + ph * 1.3 ) * twist * 1.1;
					var bx = B.x * Math.cos( tw ) + N.x * Math.sin( tw ), by = B.y * Math.cos( tw ) + N.y * Math.sin( tw ), bz = B.z * Math.cos( tw ) + N.z * Math.sin( tw );
					pos.setXYZ( i, P.x - bx * Wd / 2, P.y - by * Wd / 2, P.z - bz * Wd / 2 );            // top row of the plane (uv.y = 1) is index 0..SEG
					pos.setXYZ( i + SEG + 1, P.x + bx * Wd / 2, P.y + by * Wd / 2, P.z + bz * Wd / 2 );
				}
				// PlaneGeometry's first row is the top edge: put it on the +B side so the photos stand upright.
				for ( i = 0; i <= SEG; i++ ) {
					var ax = pos.getX( i ), ay = pos.getY( i ), az = pos.getZ( i );
					pos.setXYZ( i, pos.getX( i + SEG + 1 ), pos.getY( i + SEG + 1 ), pos.getZ( i + SEG + 1 ) ); pos.setXYZ( i + SEG + 1, ax, ay, az );
				}
				pos.needsUpdate = true;
				strip.geometry.computeVertexNormals();
				strip.geometry.computeBoundingSphere();
				// Which photo is under the pointer (for the click): the film's own u at the hit point.
				if ( api.pointer ) {
					ray.setFromCamera( new THREE.Vector2( api.pointer.x, api.pointer.y ), api.camera );
					var hit = ray.intersectObject( strip, false )[ 0 ];
					if ( hit && hit.uv ) { var u = hit.uv.x * tex.repeat.x + tex.offset.x; strip.userData.card = mod( Math.floor( ( u - Math.floor( u ) ) * n ), n ); }
				}
				strip.material.opacity = EASE( strip.userData.appear );
			}
		} );
	} );
	/* ---------------------------------------------------------------- Pixel Blocks */
	var BLOCK_VS = [
		'attribute vec3 cellInfo;',                                                  // x, y = the block's column and row; z = unused
		'uniform vec2 grid; uniform float bs;',
		'varying vec2 vUvA; varying vec2 vUvB; varying float vFace; varying vec3 vN;',
		'void main() {',
		'  vec2 lp = position.xy / bs + 0.5;',
		'  vUvA = ( cellInfo.xy + lp ) / grid;',
		'  vUvB = ( cellInfo.xy + vec2( lp.x, 1.0 - lp.y ) ) / grid;',                // the back shows the next photo upright once the block has flipped
		'  vFace = normal.z > 0.5 ? 1.0 : ( normal.z < -0.5 ? -1.0 : 0.0 );',
		'  vN = normalize( mat3( modelMatrix * instanceMatrix ) * normal );',
		'  gl_Position = projectionMatrix * viewMatrix * modelMatrix * instanceMatrix * vec4( position, 1.0 );',
		'}'
	].join( '\n' );
	var BLOCK_FS = [
		'uniform sampler2D tA; uniform sampler2D tB; uniform float aA, aB, pa, op, amb; uniform vec3 side, lightDir;',
		'varying vec2 vUvA; varying vec2 vUvB; varying float vFace; varying vec3 vN;',
		'vec2 cover( vec2 uv, float ia ) { vec2 s = pa > ia ? vec2( 1.0, ia / pa ) : vec2( pa / ia, 1.0 ); return ( uv - 0.5 ) * s + 0.5; }',
		'void main() {',
		'  vec3 base = side;',
		'  if ( vFace > 0.5 ) { base = texture2D( tA, cover( vUvA, aA ) ).rgb; } else if ( vFace < -0.5 ) { base = texture2D( tB, cover( vUvB, aB ) ).rgb; }',
		'  vec3 n = normalize( vN ); float d = max( dot( n, normalize( lightDir ) ), 0.0 );',
		'  float sp = pow( max( dot( reflect( -normalize( lightDir ), n ), vec3( 0.0, 0.0, 1.0 ) ), 0.0 ), 30.0 ) * 0.25;',
		'  vec3 col = base * ( amb + ( 1.0 - amb ) * d * 1.15 ) + sp;',
		'  gl_FragColor = vec4( col, op );',
		'}'
	].join( '\n' );

	window.upwGallery3d.registerDesign( 'webgl_pixel_blocks', function ( ctx ) {
		if ( ! ctx.cards.length ) { return; }
		var across = Math.max( 6, Math.min( 30, ctx.num( 'data-tdg-blocks', 14 ) ) ), depth = ctx.num( 'data-tdg-depth', 40 ) / 100;
		var gap = ctx.num( 'data-tdg-gap', 4 ) / 100, pattern = ctx.attr( 'data-tdg-pattern', 'diagonal' ), push = ctx.num( 'data-tdg-push', 50 ) / 100;
		var mt = Math.max( 0.1, Math.min( 0.9, ctx.num( 'data-tdg-movetime', 50 ) / 100 ) ), sideCol = ctx.attr( 'data-tdg-side', '#2b2f38' );
		var light = ctx.attr( 'data-tdg-light', 'studio' );
		var n = ctx.cards.length, photos = [], texs = [], mesh = null, u, st, cols = 0, rows = 0, cell = 1, halfW = 1, halfH = 1, delays = [], sink = [];
		var ray, plane0, hitV, dummy;
		var rand = function ( i, k ) { var x = Math.sin( i * 45.164 + k * 94.673 ) * 43758.5453; return x - Math.floor( x ); };
		mount( ctx, {
			build: function ( api ) {
				var THREE = api.THREE;
				ctx.cards.forEach( function ( c, k ) {
					var tex = new THREE.Texture(); tex.generateMipmaps = false; tex.minFilter = THREE.LinearFilter;
					var p = loadPhoto( fullSrc( c ), Math.round( k * 360 / n ), function ( pp ) { swapImage( tex, pp.el ); api.dirty = true; } );
					tex.image = p.el; tex.needsUpdate = true; photos.push( p ); texs.push( tex );
				} );
				var sc = new THREE.Color( sideCol );
				u = { tA: { value: texs[ 0 ] }, tB: { value: texs[ 0 ] }, aA: { value: 1.6 }, aB: { value: 1.6 }, pa: { value: 1.6 }, op: { value: 1 },
					amb: { value: light === 'dramatic' ? 0.45 : ( light === 'soft' ? 0.8 : 0.62 ) }, side: { value: new THREE.Vector3( sc.r, sc.g, sc.b ) },
					grid: { value: new THREE.Vector2( 1, 1 ) }, bs: { value: 1 }, lightDir: { value: new THREE.Vector3( -0.45, 0.55, 0.7 ) } };
				st = stepper( api, ctx, n, mt, n );
				ctx.cards.forEach( function ( c, k ) { c.addEventListener( 'focusin', function () { st.go( k ); } ); } );
				ray = new THREE.Raycaster(); plane0 = new THREE.Plane( new THREE.Vector3( 0, 0, 1 ), 0 ); hitV = new THREE.Vector3(); dummy = new THREE.Object3D();
			},
			resize: function ( api ) {
				var THREE = api.THREE;
				api.camera.position.set( 0, 0, 6 ); api.camera.lookAt( 0, 0, 0 );
				halfH = 6 * Math.tan( api.camera.fov * Math.PI / 360 ) * 0.9; halfW = halfH * api.camera.aspect;
				u.pa.value = api.w / Math.max( 1, api.h );
				var c2 = across, r2 = Math.max( 3, Math.round( across * api.h / Math.max( 1, api.w ) ) );
				if ( c2 === cols && r2 === rows && mesh ) { return; }
				cols = c2; rows = r2; cell = halfW * 2 / cols;
				var bs = cell * ( 1 - gap );
				u.grid.value.set( cols, rows ); u.bs.value = bs;
				var geo = new THREE.BoxGeometry( bs, bs, Math.max( 0.02, bs * depth ) );
				var N = cols * rows, info = new Float32Array( N * 3 );
				delays = []; sink = [];
				for ( var j = 0; j < rows; j++ ) {
					for ( var i = 0; i < cols; i++ ) {
						var k = j * cols + i; info[ k * 3 ] = i; info[ k * 3 + 1 ] = j;
						var d;
						if ( pattern === 'rows' ) { d = ( rows - 1 - j ) / Math.max( 1, rows - 1 ); }
						else if ( pattern === 'random' ) { d = rand( k, 1 ); }
						else if ( pattern === 'center' ) { var dx = ( i + 0.5 ) / cols - 0.5, dy = ( j + 0.5 ) / rows - 0.5; d = Math.min( 1, Math.sqrt( dx * dx * u.pa.value * u.pa.value + dy * dy ) / ( 0.5 * Math.sqrt( u.pa.value * u.pa.value + 1 ) ) ); }
						else { d = ( i + ( rows - 1 - j ) ) / Math.max( 1, cols + rows - 2 ); }
						delays.push( d ); sink.push( 0 );
					}
				}
				geo.setAttribute( 'cellInfo', new THREE.InstancedBufferAttribute( info, 3 ) );
				if ( mesh ) { api.scene.remove( mesh ); mesh.geometry.dispose(); mesh.dispose && mesh.dispose(); api.cards.length = 0; }
				mesh = new THREE.InstancedMesh( geo, new THREE.ShaderMaterial( { uniforms: u, vertexShader: BLOCK_VS, fragmentShader: BLOCK_FS, transparent: true, toneMapped: false } ), N );
				mesh.frustumCulled = false;
				mesh.userData.card = 0; mesh.userData.appear = 1;
				api.cards.push( mesh ); api.scene.add( mesh );
			},
			frame: function ( api, dt, t ) {
				if ( ! mesh ) { return; }
				var THREE = api.THREE, s = st.state( t ), a = mod( s.a, n ), b = mod( s.b, n ), e = s.e;
				u.tA.value = texs[ a ]; u.tB.value = texs[ b ]; u.aA.value = photos[ a ].aspect; u.aB.value = photos[ b ].aspect;
				mesh.userData.card = e > 0.5 ? b : a;
				var ptr = null;
				if ( api.pointer && push > 0 ) { ray.setFromCamera( new THREE.Vector2( api.pointer.x, api.pointer.y ), api.camera ); if ( ray.ray.intersectPlane( plane0, hitV ) ) { ptr = [ hitV.x, hitV.y ]; } }
				// Intro: the blocks fly in from behind, in the flip order.
				var intro = Math.min( 1, ( api.t - ( api.introT || ( api.introT = api.t ) ) ) / 1.2 );
				if ( ctx.reduce || ctx.IN_EDITOR ) { intro = 1; }
				for ( var j = 0; j < rows; j++ ) {
					for ( var i = 0; i < cols; i++ ) {
						var k = j * cols + i, d = delays[ k ];
						var q = Math.max( 0, Math.min( 1, ( e * 1.6 - d * 0.6 ) ) ); q = q * q * ( 3 - 2 * q );
						var x = ( i + 0.5 ) * cell - halfW, y = ( j + 0.5 ) * cell - rows * cell / 2;
						var target = 0;
						if ( ptr ) { var dx = ptr[ 0 ] - x, dy = ptr[ 1 ] - y; target = Math.exp( -( dx * dx + dy * dy ) / ( cell * cell * 6 ) ); }
						sink[ k ] += ( target - sink[ k ] ) * Math.min( 1, dt * 8 );
						var ia = Math.max( 0, Math.min( 1, intro * 1.6 - d * 0.6 ) );
						dummy.position.set( x, y, Math.sin( q * Math.PI ) * cell * 1.2 - sink[ k ] * push * cell * 1.6 - ( 1 - ia ) * 4 );
						dummy.rotation.set( q * Math.PI, 0, 0 );
						if ( ptr && sink[ k ] > 0.01 ) { dummy.rotation.x += -( ptr[ 1 ] - y ) / ( cell * 3 ) * sink[ k ] * push * 0.5; dummy.rotation.y = ( ptr[ 0 ] - x ) / ( cell * 3 ) * sink[ k ] * push * 0.5; } else { dummy.rotation.y = 0; }
						dummy.updateMatrix();
						mesh.setMatrixAt( k, dummy.matrix );
					}
				}
				mesh.instanceMatrix.needsUpdate = true;
				u.op.value = Math.min( 1, intro * 1.5 );
			}
		} );
	} );

	/* ---------------------------------------------------------------- Ferris Wheel */
	window.upwGallery3d.registerDesign( 'webgl_ferris_wheel', function ( ctx ) {
		if ( ! ctx.cards.length ) { return; }
		var count = Math.max( 6, Math.min( 16, ctx.num( 'data-tdg-count', 10 ) ) ), size = ctx.num( 'data-tdg-card', 26 ) / 100;
		var wheelCol = ctx.attr( 'data-tdg-wheel', '#e9ecf2' ), bulbs = ctx.attr( 'data-tdg-bulbs', '1' ) === '1', view = ctx.num( 'data-tdg-view', 12 );
		var swing = ctx.num( 'data-tdg-swing', 50 ) / 100, dir = ctx.num( 'data-tdg-dir', 1 );
		var n = ctx.cards.length, R = 3, wheel, gondolas = [], bulbList = [], cw, ch, lastAng = 0, angVel = 0;
		mount( ctx, {
			build: function ( api ) {
				var THREE = api.THREE;
				var lin = function ( c ) { return new THREE.Color( c ).convertSRGBToLinear(); };
				// A gondola's width from Card Size; the rim leaves room for them to hang without touching.
				cw = Math.min( 1.6, R * 2 * Math.PI / count * 0.62 ) * ( size / 0.2 ) * 0.9; ch = cw * api.aspect;
				var metal = new THREE.MeshStandardMaterial( { color: lin( wheelCol ), roughness: 0.35, metalness: 0.55 } );
				wheel = new THREE.Group();
				[ -0.28, 0.28 ].forEach( function ( z ) {
					var ring = new THREE.Mesh( new THREE.TorusGeometry( R, 0.05, 8, 96 ), metal ); ring.position.z = z; wheel.add( ring );
					var inner = new THREE.Mesh( new THREE.TorusGeometry( R * 0.42, 0.035, 8, 64 ), metal ); inner.position.z = z; wheel.add( inner );
					for ( var i = 0; i < count; i++ ) {
						var a = i / count * Math.PI * 2, spoke = new THREE.Mesh( new THREE.CylinderGeometry( 0.025, 0.025, R, 6 ), metal );
						spoke.position.set( Math.cos( a ) * R / 2, Math.sin( a ) * R / 2, z ); spoke.rotation.z = a - Math.PI / 2; wheel.add( spoke );
					}
				} );
				var hub = new THREE.Mesh( new THREE.CylinderGeometry( 0.22, 0.22, 0.75, 20 ), metal ); hub.rotation.x = Math.PI / 2; wheel.add( hub );
				api.scene.add( wheel );
				// The A-frame legs (they stay still).
				var legH = R + ch + 0.6;
				[ -1, 1 ].forEach( function ( sx ) { [ -0.45, 0.45 ].forEach( function ( z ) {
					var leg = new THREE.Mesh( new THREE.CylinderGeometry( 0.06, 0.08, legH * 1.04, 8 ), metal );
					leg.position.set( sx * legH * 0.22, -legH / 2, z * 0.85 ); leg.rotation.z = sx * 0.43; api.scene.add( leg );
				} ); } );
				var radFrac = Math.min( 0.3, radiusOf( ctx.el ) / 200 );
				var texs = ctx.cards.map( function ( c, k ) { var s = fullSrc( c ); return cardTexture( s, api.aspect, radFrac, function () { api.dirty = true; }, s ? null : Math.round( k * 360 / n ) ); } );
				var rodM = new THREE.MeshStandardMaterial( { color: lin( wheelCol ), roughness: 0.4, metalness: 0.5 } );
				var frameM = new THREE.MeshStandardMaterial( { color: lin( wheelCol ), roughness: 0.4, metalness: 0.4 } );
				for ( var i = 0; i < count; i++ ) {
					var g = new THREE.Group();                                       // pivot at the rim pin
					var rod = new THREE.Mesh( new THREE.CylinderGeometry( 0.02, 0.02, 0.35, 6 ), rodM ); rod.position.y = -0.17; g.add( rod );
					var frame = new THREE.Mesh( new THREE.BoxGeometry( cw * 1.08, ch * 1.08, 0.06 ), frameM ); frame.position.set( 0, -0.35 - ch / 2, -0.035 ); g.add( frame );
					var photo = new THREE.Mesh( new THREE.PlaneGeometry( cw, ch ), new THREE.MeshStandardMaterial( { map: texs[ i % n ], roughness: 0.5, transparent: true, alphaTest: 0.02 } ) );
					photo.position.set( 0, -0.35 - ch / 2, 0.001 ); g.add( photo );
					photo.userData.card = i % n; photo.userData.appear = 0;
					api.cards.push( photo ); api.scene.add( g );
					gondolas.push( { g: g, a: i / count * Math.PI * 2, sw: 0, sv: 0, hov: 0 } );
				}
				if ( bulbs ) {
					var bulbM = new THREE.MeshBasicMaterial( { color: 0xffe6a8, toneMapped: false } );
					for ( var b = 0; b < count * 4; b++ ) {
						var ba = b / ( count * 4 ) * Math.PI * 2, bulb = new THREE.Mesh( new THREE.SphereGeometry( 0.045, 8, 6 ), bulbM.clone() );
						bulb.position.set( Math.cos( ba ) * R, Math.sin( ba ) * R, 0.33 ); wheel.add( bulb ); bulbList.push( bulb );
					}
				}
				api.key.position.set( -3, 4, 6 );
			},
			resize: function ( api ) {
				var tanH = Math.tan( api.camera.fov * Math.PI / 360 ), va = view * Math.PI / 180;
				var w = ( R + cw * 0.6 ) * 2, h = R * 2 + ch + 0.9 + 0.5;
				var dist = Math.max( w / api.camera.aspect, h ) * 1.12 / ( 2 * tanH );
				api.camera.position.set( Math.sin( va ) * dist, -( ch * 0.25 ), Math.cos( va ) * dist );
				api.camera.lookAt( 0, -( ch + 0.35 ) / 2, 0 );
			},
			frame: function ( api, dt, t ) {
				var drive = ctx.attr( 'data-tdg-drive', 'continuous' );
				var ang = ( drive === 'static' ? 0 : api.progress() * Math.PI * 2 * dir ) - api.drag;
				var av = dt > 0 ? ( ang - lastAng ) / dt : 0; angVel += ( av - angVel ) * Math.min( 1, dt * 4 ); lastAng = ang;
				wheel.rotation.z = ang;
				gondolas.forEach( function ( go, i ) {
					var a = go.a + ang, ap = EASE( api.cards[ i ].userData.appear );
					go.g.position.set( Math.cos( a ) * R, Math.sin( a ) * R, 0.56 );
					// They hang straight down and swing: a spring nudged by how fast the wheel turns, plus a breeze.
					var force = -angVel * Math.cos( a ) * 0.04 * swing + Math.sin( t * 1.1 + i * 1.7 ) * 0.02 * swing;
					go.sv += ( force * 8 - go.sw * 9 - go.sv * 1.6 ) * dt; go.sw += go.sv * dt;
					go.hov += ( ( api.hover === i ? 1 : 0 ) - go.hov ) * Math.min( 1, dt * 6 );
					go.g.rotation.z = go.sw;
					go.g.scale.setScalar( ( 0.6 + 0.4 * ap ) * ( 1 + go.hov * 0.08 ) );
					api.cards[ i ].material.opacity = ap;
				} );
				bulbList.forEach( function ( b, i ) { var v = 0.55 + 0.45 * Math.max( 0, Math.sin( t * 3 + i * 0.9 ) ); b.material.color.setRGB( v, v * 0.9, v * 0.62 ); } );
			}
		} );
	} );

	/* ---------------------------------------------------------------- Photo Mobile */
	window.upwGallery3d.registerDesign( 'webgl_photo_mobile', function ( ctx ) {
		if ( ! ctx.cards.length ) { return; }
		var count = Math.max( 3, Math.min( 12, ctx.num( 'data-tdg-count', 7 ) ) ), size = ctx.num( 'data-tdg-card', 18 ) / 100;
		var breeze = ctx.num( 'data-tdg-breeze', 50 ) / 100, sway = ctx.num( 'data-tdg-sway', 50 ) / 100;
		var armCol = ctx.attr( 'data-tdg-arm', '#3a3c41' ), dir = ctx.num( 'data-tdg-dir', 1 );
		var n = ctx.cards.length, nodes = [], cw, ch, root, lastPtr = null, gust = 0, extentW = 4, extentH = 4;
		mount( ctx, {
			build: function ( api ) {
				var THREE = api.THREE;
				var lin = function ( c ) { return new THREE.Color( c ).convertSRGBToLinear(); };
				cw = size * 5.2; ch = cw * api.aspect;
				var armM = new THREE.MeshStandardMaterial( { color: lin( armCol ), roughness: 0.5, metalness: 0.4 } );
				var strM = new THREE.MeshBasicMaterial( { color: lin( armCol ) } );
				var radFrac = Math.min( 0.3, radiusOf( ctx.el ) / 200 );
				var texs = ctx.cards.map( function ( c, k ) { var s = fullSrc( c ); return cardTexture( s, api.aspect, radFrac, function () { api.dirty = true; }, s ? null : Math.round( k * 360 / n ) ); } );
				var leaf = 0, depthMax = 0;
				var string = function ( len ) { var m = new THREE.Mesh( new THREE.CylinderGeometry( 0.008, 0.008, len, 4 ), strM ); m.position.y = -len / 2; return m; };
				// A balanced tree: each arm holds `k` photos split between its two ends; a single photo hangs on a string.
				var build = function ( k, level, armLen ) {
					var g = new THREE.Group(); depthMax = Math.max( depthMax, level );
					if ( k === 1 ) {
						var sl = 0.35 + ( leaf % 3 ) * 0.18; g.add( string( sl ) );
						var photo = new THREE.Mesh( new THREE.PlaneGeometry( cw, ch ), new THREE.MeshStandardMaterial( { map: texs[ leaf % n ], roughness: 0.55, side: THREE.DoubleSide, transparent: true, alphaTest: 0.02 } ) );
						photo.position.y = -sl - ch / 2;
						photo.userData.card = leaf % n; photo.userData.appear = 0;
						api.cards.push( photo ); g.add( photo ); leaf++;
						nodes.push( { g: g, spin: 0, sp: 0.35 + ( leaf % 4 ) * 0.12, ph: leaf * 1.3, leaf: true } );
						return g;
					}
					var drop = 0.45;
					g.add( string( drop ) );
					var arm = new THREE.Group(); arm.position.y = -drop; g.add( arm );
					var bar = new THREE.Mesh( new THREE.CylinderGeometry( 0.025, 0.025, armLen * 2, 8 ), armM ); bar.rotation.z = Math.PI / 2; arm.add( bar );
					var left = Math.ceil( k / 2 ), right = k - left;
					var l = build( left, level + 1, armLen * 0.55 ); l.position.x = -armLen; arm.add( l );
					var r = build( right, level + 1, armLen * 0.55 ); r.position.x = armLen; arm.add( r );
					nodes.push( { g: arm, spin: 0, sp: ( 0.18 + level * 0.1 ) * ( level % 2 ? -1 : 1 ), ph: level * 2.1 + armLen, leaf: false } );
					return g;
				};
				var top = cw * 2.4 + 0.4;
				root = build( count, 0, top );
				var hook = new THREE.Mesh( new THREE.TorusGeometry( 0.08, 0.02, 6, 16 ), armM ); hook.position.y = 0.1; root.add( hook );
				api.scene.add( root );
				// Measure the hanging mobile (arms at rest are at their widest) and centre it.
				root.updateMatrixWorld( true );
				var box = new THREE.Box3().setFromObject( root );
				extentW = Math.max( 1, box.max.x - box.min.x ); extentH = Math.max( 1, box.max.y - box.min.y );
				root.position.set( -( box.min.x + box.max.x ) / 2, -( box.min.y + box.max.y ) / 2, 0 );
				api.key.position.set( -3, 4, 5 );
			},
			resize: function ( api ) {
				var tanH = Math.tan( api.camera.fov * Math.PI / 360 );
				// The arms turn, so a photo can swing half the mobile's width toward the camera: stay well beyond that.
				var dist = Math.max( Math.max( extentW / api.camera.aspect, extentH ) * 1.1 / ( 2 * tanH ), extentW * 1.05 );
				api.camera.position.set( 0, -0.3, dist ); api.camera.lookAt( 0, 0, 0 );
			},
			frame: function ( api, dt, t ) {
				var drive = ctx.attr( 'data-tdg-drive', 'continuous' ), p = drive === 'static' ? 0 : api.progress() * Math.PI * 2 * dir;
				// The pointer moving across the mobile is a breeze: it spins the arms faster for a while.
				if ( api.pointer ) { if ( lastPtr ) { gust += Math.min( 0.5, Math.abs( api.pointer.x - lastPtr.x ) + Math.abs( api.pointer.y - lastPtr.y ) ) * breeze * 6; } lastPtr = { x: api.pointer.x, y: api.pointer.y }; } else { lastPtr = null; }
				gust *= Math.pow( 0.35, dt );
				nodes.forEach( function ( nd ) {
					nd.spin += nd.sp * gust * dt * 2.5;
					var base = nd.leaf ? Math.sin( p * 1.7 + nd.ph ) * 0.9 : p * nd.sp * 2.2;
					nd.g.rotation.y = base + nd.spin + ( nd.leaf ? 0 : Math.sin( t * 0.4 + nd.ph ) * 0.2 * sway );
					nd.g.rotation.z = Math.sin( t * 0.9 + nd.ph ) * 0.05 * sway;
				} );
				api.cards.forEach( function ( m ) { var ap = EASE( m.userData.appear ); m.material.opacity = ap; m.scale.setScalar( 0.7 + 0.3 * ap ); } );
			}
		} );
	} );

	/* ---------------------------------------------------------------- Photo Fall */
	window.upwGallery3d.registerDesign( 'webgl_photo_fall', function ( ctx ) {
		if ( ! ctx.cards.length ) { return; }
		var count = Math.max( 6, Math.min( 40, ctx.num( 'data-tdg-count', 18 ) ) ), size = ctx.num( 'data-tdg-card', 16 ) / 100;
		var flutter = ctx.num( 'data-tdg-flutter', 50 ) / 100, wind = ctx.num( 'data-tdg-wind', 0 ) / 100, depth = ctx.num( 'data-tdg-depth', 50 ) / 100;
		var push = ctx.num( 'data-tdg-push', 50 ) / 100, dir = ctx.num( 'data-tdg-dir', 1 );
		var CAM = 8, items = [], tanH = 0.3, cw = 1, lastPtr = null, v3 = null;
		var rand = function ( i, k ) { var x = Math.sin( i * 12.9898 + k * 78.233 ) * 43758.5453; return x - Math.floor( x ); };
		mount( ctx, {
			build: function ( api ) {
				api.roughness = 0.6;
				api.makeCards( count, 1 );
				api.cards.forEach( function ( m, i ) {
					api.scene.add( m );
					items.push( {
						x: ( i + 0.5 ) / count * 0.9 + 0.05 + ( rand( i, 1 ) - 0.5 ) / count, off: rand( i, 2 ),
						z: -depth * 7 * rand( i, 3 ) + 1.2 * rand( i, 9 ), sp: 0.85 + rand( i, 4 ) * 0.3, fs: 0.7 + rand( i, 5 ) * 0.6, ph: rand( i, 6 ) * 6.283,
						ox: 0, oy: 0, vx: 0, vy: 0, spin: 0, hov: 0
					} );
				} );
				v3 = new api.THREE.Vector3();
				api.key.position.set( -3, 5, 6 );
			},
			resize: function ( api ) {
				api.camera.position.set( 0, 0, CAM ); api.camera.lookAt( 0, 0, 0 );
				tanH = Math.tan( api.camera.fov * Math.PI / 360 );
				var hh = CAM * tanH, hw = hh * api.camera.aspect;
				// A photo's width: Photo Size as a share of the stage's short side (so a tall stage keeps them readable).
				cw = size * 2 * Math.min( hw, hh * 1.6 );
			},
			frame: function ( api, dt, t ) {
				var drive = ctx.attr( 'data-tdg-drive', 'continuous' ), p = drive === 'static' ? 0 : api.progress() * dir;
				var still = ctx.reduce || ctx.IN_EDITOR;
				// Sweeping the pointer across is a gust: photos near it are blown along with it, spinning.
				var gx = 0, gy = 0, ptr = api.pointer;
				if ( ptr && lastPtr ) { gx = Math.max( -0.3, Math.min( 0.3, ptr.x - lastPtr.x ) ); gy = Math.max( -0.3, Math.min( 0.3, ptr.y - lastPtr.y ) ); }
				lastPtr = ptr ? { x: ptr.x, y: ptr.y } : null;
				items.forEach( function ( it, i ) {
					var m = api.cards[ i ], hh = ( CAM - it.z ) * tanH, hw = hh * api.camera.aspect, span = hh * 2 + cw * 2.2;
					var f = mod( it.off + p * it.sp, 1 );
					var y = hh + cw * 1.1 - f * span;
					var x = ( it.x * 2 - 1 ) * hw * 0.95 + Math.sin( t * it.fs * 0.9 + it.ph ) * cw * 0.5 * flutter + wind * ( f - 0.5 ) * hw * 0.9;
					if ( ptr && push > 0 && ! still ) {
						v3.set( x + it.ox, y + it.oy, it.z ).project( api.camera );
						var dx = ( v3.x - ptr.x ) * api.camera.aspect, dy = v3.y - ptr.y, fall = Math.exp( -( dx * dx + dy * dy ) / 0.06 );
						it.vx += gx * fall * push * 60 * hw; it.vy += gy * fall * push * 60 * hh; it.spin += ( Math.abs( gx ) + Math.abs( gy ) ) * fall * push * 30;
					}
					it.vx -= it.ox * 0.6 * dt; it.vy -= it.oy * 0.6 * dt;
					it.vx *= Math.pow( 0.25, dt ); it.vy *= Math.pow( 0.25, dt ); it.spin *= Math.pow( 0.3, dt );
					it.ox += it.vx * dt; it.oy += it.vy * dt;
					it.hov += ( ( api.hover === i || api.focus === i ? 1 : 0 ) - it.hov ) * Math.min( 1, dt * 6 );
					var ap = EASE( m.userData.appear ), k = 1 - it.hov;
					m.position.set( x + it.ox, y + it.oy, it.z + it.hov * 0.6 );
					// A leaf's flutter: rocking side to side, a slow turn, and a tumble that speeds up in a gust.
					m.rotation.set(
						( Math.sin( t * it.fs * 0.8 + it.ph ) * 0.7 * flutter + 0.15 ) * k,
						( Math.sin( t * it.fs * 0.5 + it.ph * 1.7 ) * 1.0 * flutter + it.spin * 0.4 ) * k,
						( Math.sin( t * it.fs * 0.6 + it.ph * 2.3 ) * 0.5 * flutter + it.spin * 0.2 ) * k );
					m.scale.setScalar( cw * ( 0.7 + 0.3 * ap ) * ( 1 + it.hov * 0.15 ) );
					m.material.opacity = ap;
				} );
			}
		} );
	} );

	/* ---------------------------------------------------------------- Lenticular */
	window.upwGallery3d.registerDesign( 'webgl_lenticular', function ( ctx ) {
		if ( ! ctx.cards.length ) { return; }
		var ridges = Math.max( 10, Math.min( 60, ctx.num( 'data-tdg-ridges', 28 ) ) ), tilt = Math.max( 15, Math.min( 50, ctx.num( 'data-tdg-tilt', 30 ) ) ) * Math.PI / 180;
		var size = ctx.num( 'data-tdg-card', 72 ) / 100, mt = Math.max( 0.1, Math.min( 0.9, ctx.num( 'data-tdg-movetime', 40 ) / 100 ) );
		var follow = ctx.num( 'data-tdg-follow', 50 ) / 100, frameCol = ctx.attr( 'data-tdg-frame', '#e9ecf2' );
		var n = ctx.cards.length, group, matL, matR, texs = [], st, rx = 0, ry = 0, ap = 0;
		mount( ctx, {
			build: function ( api ) {
				var THREE = api.THREE, H = api.aspect, w = 1 / ridges;
				// The ridges are deep enough that, turned by Tilt, each side hides the other completely, even at the card's far
				// edge, which perspective sees at a shallower angle.
				var d = ( w / 2 ) * Math.tan( Math.min( 1.45, Math.PI / 2 - tilt + 0.26 ) );
				var mk = function ( right ) {
					var pos = [], nor = [], uv = [], idx = [];
					var nx = right ? d : -d, nz = w / 2, nl = Math.sqrt( nx * nx + nz * nz );
					for ( var r = 0; r < ridges; r++ ) {
						var x0 = -0.5 + r * w + ( right ? w / 2 : 0 ), x1 = x0 + w / 2, z0 = right ? d : 0, z1 = right ? 0 : d, b = pos.length / 3;
						// Each face carries its whole ridge's slice of the photo (not half), so the side turned to the
						// viewer shows the photo whole rather than in steps.
						var u0 = r * w, u1 = u0 + w;
						[ [ x0, -H / 2, z0, u0 ], [ x1, -H / 2, z1, u1 ], [ x1, H / 2, z1, u1 ], [ x0, H / 2, z0, u0 ] ].forEach( function ( v ) {
							pos.push( v[ 0 ], v[ 1 ], v[ 2 ] ); nor.push( nx / nl, 0, nz / nl ); uv.push( v[ 3 ], v[ 1 ] / H + 0.5 );
						} );
						idx.push( b, b + 1, b + 2, b, b + 2, b + 3 );
					}
					var g = new THREE.BufferGeometry();
					g.setAttribute( 'position', new THREE.Float32BufferAttribute( pos, 3 ) ); g.setAttribute( 'normal', new THREE.Float32BufferAttribute( nor, 3 ) );
					g.setAttribute( 'uv', new THREE.Float32BufferAttribute( uv, 2 ) ); g.setIndex( idx );
					return g;
				};
				var radFrac = Math.min( 0.3, radiusOf( ctx.el ) / 300 );
				texs = ctx.cards.map( function ( c, k ) { var s = fullSrc( c ); return cardTexture( s, H, radFrac, function () { api.dirty = true; }, s ? null : Math.round( k * 360 / n ) ); } );
				matL = new THREE.MeshStandardMaterial( { map: texs[ 0 ], roughness: 0.32, metalness: 0.05, transparent: true, alphaTest: 0.02 } );
				matR = new THREE.MeshStandardMaterial( { map: texs[ Math.min( 1, n - 1 ) ], roughness: 0.32, metalness: 0.05, transparent: true, alphaTest: 0.02 } );
				var L = new THREE.Mesh( mk( false ), matL ), R = new THREE.Mesh( mk( true ), matR );
				L.userData.card = 0; L.userData.appear = 0; R.userData.card = 0; R.userData.appear = 0;
				var back = new THREE.Mesh( new THREE.BoxGeometry( 1.06, H + 0.06, 0.05 ), new THREE.MeshStandardMaterial( { color: new THREE.Color( frameCol ).convertSRGBToLinear(), roughness: 0.45, metalness: 0.2 } ) );
				back.position.z = -0.026;
				group = new THREE.Group(); group.add( back, L, R );
				api.cards.push( L, R ); api.scene.add( group );
				st = stepper( api, ctx, n, mt, n );
				ctx.cards.forEach( function ( c, k ) { c.addEventListener( 'focusin', function () { st.go( k ); } ); } );
				api.key.position.set( -2, 4, 6 );
			},
			resize: function ( api ) {
				api.camera.position.set( 0, 0, 6 ); api.camera.lookAt( 0, 0, 0 );
				var hh = 6 * Math.tan( api.camera.fov * Math.PI / 360 ), hw = hh * api.camera.aspect;
				var s = Math.min( hh * 2 * size / ( api.aspect + 0.06 ), hw * 2 * 0.86 / 1.06 );
				group.scale.setScalar( s );
			},
			frame: function ( api, dt, t ) {
				var s = st.state( t ), odd = mod( s.a, 2 ) === 1, a = mod( s.a, n ), b = mod( s.b, n );
				// Even steps: the left-facing side shows step a and the right-facing side the next; odd steps swap sides,
				// so each swing of the card turns to the next photo.
				matL.map = texs[ odd ? b : a ]; matR.map = texs[ odd ? a : b ];
				var side = odd ? -1 : 1, base = side * tilt * ( 1 - 2 * s.e );
				var still = ctx.reduce || ctx.IN_EDITOR, px = api.pointer && ! still ? api.pointer.x : 0, py = api.pointer && ! still ? api.pointer.y : 0;
				ry += ( base - px * 0.35 * follow - ry ) * Math.min( 1, dt * 6 );
				rx += ( -py * 0.3 * follow - rx ) * Math.min( 1, dt * 6 );
				ap = EASE( api.cards[ 0 ].userData.appear );
				group.rotation.set( rx + ( still ? 0 : Math.sin( t * 0.7 ) * 0.03 ), ry + ( 1 - ap ) * side * 0.8, 0 );
				group.position.y = still ? 0 : Math.sin( t * 0.9 ) * 0.04;
				var shown = s.e > 0.5 ? b : a;
				api.cards.forEach( function ( m ) { m.userData.card = shown; } );
				matL.opacity = matR.opacity = ap;
			}
		} );
	} );

	/* ---------------------------------------------------------------- Jigsaw */
	var JIG_VS = [
		'attribute vec2 cell; attribute vec4 tabs; attribute float alpha;',
		'uniform vec2 grid;',
		'varying vec2 vP; varying vec2 vUv; varying vec4 vTabs; varying float vA; varying vec3 vN;',
		'void main() {',
		'  vP = position.xy + 0.5;',                                                 // the piece's own square is 0..1; the knobs reach past it
		'  vUv = ( cell + vP ) / grid; vTabs = tabs; vA = alpha;',
		'  vN = normalize( mat3( modelMatrix * instanceMatrix ) * vec3( 0.0, 0.0, 1.0 ) );',
		'  gl_Position = projectionMatrix * viewMatrix * modelMatrix * instanceMatrix * vec4( position, 1.0 );',
		'}'
	].join( '\n' );
	var JIG_FS = [
		'uniform sampler2D tP; uniform float aP, pa, amb, op; uniform vec3 lightDir;',
		'varying vec2 vP; varying vec2 vUv; varying vec4 vTabs; varying float vA; varying vec3 vN;',
		'vec2 cover( vec2 uv, float ia ) { vec2 s = pa > ia ? vec2( 1.0, ia / pa ) : vec2( pa / ia, 1.0 ); return ( uv - 0.5 ) * s + 0.5; }',
		// A knob (s = +1) or a hole (s = -1) in the middle of one edge; 0 = a flat edge (the photo's border).
		'float tab( float d, vec2 p, vec2 c, vec2 nrm, float s ) {',
		'  if ( abs( s ) < 0.5 ) { return d; }',
		'  vec2 k = c + nrm * s * 0.17, pc = p - c, kc = k - c; float h = clamp( dot( pc, kc ) / dot( kc, kc ), 0.0, 1.0 );',
		'  float dk = min( length( p - k ) - 0.13, length( pc - kc * h ) - 0.075 );',   // a round head on a short neck
		'  return s > 0.0 ? min( d, dk ) : max( d, -dk ); }',
		'float shape( vec2 p ) {',
		'  vec2 q = abs( p - 0.5 ) - 0.5; float d = length( max( q, 0.0 ) ) + min( max( q.x, q.y ), 0.0 );',
		'  d = tab( d, p, vec2( 0.5, 1.0 ), vec2( 0.0, 1.0 ), vTabs.x ); d = tab( d, p, vec2( 1.0, 0.5 ), vec2( 1.0, 0.0 ), vTabs.y );',
		'  d = tab( d, p, vec2( 0.5, 0.0 ), vec2( 0.0, -1.0 ), vTabs.z ); d = tab( d, p, vec2( 0.0, 0.5 ), vec2( -1.0, 0.0 ), vTabs.w );',
		'  return d; }',
		'void main() {',
		'  float d = shape( vP ); if ( d > 0.012 || vA < 0.005 ) { discard; }',
		'  vec2 gr = vec2( shape( vP + vec2( 0.01, 0.0 ) ) - shape( vP - vec2( 0.01, 0.0 ) ), shape( vP + vec2( 0.0, 0.01 ) ) - shape( vP - vec2( 0.0, 0.01 ) ) );',
		'  vec3 col = texture2D( tP, cover( vUv, aP ) ).rgb;',
		// A bevelled edge: lit on the top-left, shaded on the bottom-right, with a fine dark cut line.
		'  float bev = 1.0 - smoothstep( 0.0, 0.035, -d ); float hl = length( gr ) > 1e-5 ? dot( normalize( gr ), vec2( -0.7071, 0.7071 ) ) : 0.0;',
		'  col *= 1.0 + bev * hl * 0.28; col *= 1.0 - 0.35 * smoothstep( 0.014, 0.0, -d );',
		'  float df = max( dot( normalize( vN ), normalize( lightDir ) ), 0.0 ); col *= amb + ( 1.0 - amb ) * df * 1.12;',
		'  gl_FragColor = vec4( col, vA * op * clamp( ( 0.012 - d ) / 0.012, 0.0, 1.0 ) );',
		'}'
	].join( '\n' );

	window.upwGallery3d.registerDesign( 'webgl_jigsaw', function ( ctx ) {
		if ( ! ctx.cards.length ) { return; }
		var across = Math.max( 4, Math.min( 16, ctx.num( 'data-tdg-pieces', 8 ) ) ), scatter = ctx.num( 'data-tdg-scatter', 50 ) / 100;
		var lift = ctx.num( 'data-tdg-lift', 50 ) / 100, mt = Math.max( 0.1, Math.min( 0.9, ctx.num( 'data-tdg-movetime', 60 ) / 100 ) );
		var boardCol = ctx.attr( 'data-tdg-board', '#1d2027' ), light = ctx.attr( 'data-tdg-light', 'studio' );
		var n = ctx.cards.length, photos = [], texs = [], meshA = null, meshB = null, uA, uB, board, st, cols = 0, rows = 0, cell = 1, halfW = 1, halfH = 1;
		var delays = [], scat = [], lifted = [], ray, plane0, hitV, dummy;
		var rand = function ( i, k ) { var x = Math.sin( i * 45.164 + k * 94.673 ) * 43758.5453; return x - Math.floor( x ); };
		var uniforms = function ( THREE ) {
			return { tP: { value: texs[ 0 ] }, aP: { value: 1.6 }, pa: { value: 1.6 }, op: { value: 1 }, grid: { value: new THREE.Vector2( 1, 1 ) },
				amb: { value: light === 'dramatic' ? 0.5 : ( light === 'soft' ? 0.82 : 0.66 ) }, lightDir: { value: new THREE.Vector3( -0.4, 0.5, 0.75 ) } };
		};
		mount( ctx, {
			build: function ( api ) {
				var THREE = api.THREE;
				ctx.cards.forEach( function ( c, k ) {
					var tex = new THREE.Texture(); tex.generateMipmaps = false; tex.minFilter = THREE.LinearFilter;
					var p = loadPhoto( fullSrc( c ), Math.round( k * 360 / n ), function ( pp ) { swapImage( tex, pp.el ); api.dirty = true; } );
					tex.image = p.el; tex.needsUpdate = true; photos.push( p ); texs.push( tex );
				} );
				uA = uniforms( THREE ); uB = uniforms( THREE );
				board = new THREE.Mesh( new THREE.PlaneGeometry( 1, 1 ), new THREE.MeshStandardMaterial( { color: new THREE.Color( boardCol ).convertSRGBToLinear(), roughness: 0.9 } ) );
				board.position.z = -0.03; api.scene.add( board );
				st = stepper( api, ctx, n, mt, n );
				ctx.cards.forEach( function ( c, k ) { c.addEventListener( 'focusin', function () { st.go( k ); } ); } );
				ray = new THREE.Raycaster(); plane0 = new THREE.Plane( new THREE.Vector3( 0, 0, 1 ), 0 ); hitV = new THREE.Vector3(); dummy = new THREE.Object3D();
			},
			resize: function ( api ) {
				var THREE = api.THREE;
				api.camera.position.set( 0, 0, 6 ); api.camera.lookAt( 0, 0, 0 );
				var vh = 6 * Math.tan( api.camera.fov * Math.PI / 360 ), vw = vh * api.camera.aspect;
				var c2 = across, r2 = Math.max( 3, Math.round( across * api.h / Math.max( 1, api.w ) ) );
				cell = Math.min( vw * 2 * 0.9 / c2, vh * 2 * 0.9 / r2 ); halfW = c2 * cell / 2; halfH = r2 * cell / 2;
				board.scale.set( halfW * 2, halfH * 2, 1 );
				[ uA, uB ].forEach( function ( u ) { u.pa.value = c2 / r2; u.grid.value.set( c2, r2 ); } );
				if ( c2 === cols && r2 === rows && meshA ) { return; }
				cols = c2; rows = r2;
				var N = cols * rows, cellA = new Float32Array( N * 2 ), tabA = new Float32Array( N * 4 );
				var sH = function ( i, j ) { return rand( i * 31 + j, 3 ) > 0.5 ? 1 : -1; }, sV = function ( i, j ) { return rand( i * 17 + j * 5, 7 ) > 0.5 ? 1 : -1; };
				delays = []; scat = []; lifted = [];
				for ( var j = 0; j < rows; j++ ) {
					for ( var i = 0; i < cols; i++ ) {
						var k = j * cols + i;
						cellA[ k * 2 ] = i; cellA[ k * 2 + 1 ] = j;
						tabA[ k * 4 ] = j < rows - 1 ? sH( i, j ) : 0; tabA[ k * 4 + 1 ] = i < cols - 1 ? sV( i, j ) : 0;
						tabA[ k * 4 + 2 ] = j > 0 ? -sH( i, j - 1 ) : 0; tabA[ k * 4 + 3 ] = i > 0 ? -sV( i - 1, j ) : 0;
						delays.push( rand( k, 1 ) ); lifted.push( 0 );
						// Where each piece comes from: out to the sides and toward the camera, turned.
						var ang = rand( k, 2 ) * Math.PI * 2;
						scat.push( { x: Math.cos( ang ) * ( 0.5 + rand( k, 4 ) ) * halfW * 1.2, y: Math.sin( ang ) * ( 0.5 + rand( k, 5 ) ) * halfH * 1.2, z: 1.5 + rand( k, 6 ) * 2.5, r: ( rand( k, 8 ) - 0.5 ) * 3 } );
					}
				}
				var mkMesh = function ( u ) {
					var geo = new THREE.PlaneGeometry( 1.7, 1.7 );
					geo.setAttribute( 'cell', new THREE.InstancedBufferAttribute( cellA, 2 ) );
					geo.setAttribute( 'tabs', new THREE.InstancedBufferAttribute( tabA, 4 ) );
					var al = new THREE.InstancedBufferAttribute( new Float32Array( N ).fill( 1 ), 1 ); al.setUsage( THREE.DynamicDrawUsage ); geo.setAttribute( 'alpha', al );
					var m = new THREE.InstancedMesh( geo, new THREE.ShaderMaterial( { uniforms: u, vertexShader: JIG_VS, fragmentShader: JIG_FS, transparent: true, toneMapped: false } ), N );
					m.frustumCulled = false; m.userData.card = 0; m.userData.appear = 1;
					return m;
				};
				if ( meshA ) { api.scene.remove( meshA, meshB ); meshA.geometry.dispose(); meshB.geometry.dispose(); api.cards.length = 0; }
				meshA = mkMesh( uA ); meshB = mkMesh( uB ); meshB.renderOrder = 1;
				api.cards.push( meshA, meshB ); api.scene.add( meshA, meshB );
			},
			frame: function ( api, dt, t ) {
				if ( ! meshA ) { return; }
				var THREE = api.THREE, s = st.state( t ), a = mod( s.a, n ), b = mod( s.b, n ), e = s.e;
				uA.tP.value = texs[ a ]; uA.aP.value = photos[ a ].aspect; uB.tP.value = texs[ b ]; uB.aP.value = photos[ b ].aspect;
				meshA.userData.card = meshB.userData.card = e > 0.5 ? b : a;
				var still = ctx.reduce || ctx.IN_EDITOR;
				// Intro: the first photo's pieces fly in and assemble.
				var intro = still ? 1 : Math.min( 1, ( api.t - ( api.introT || ( api.introT = api.t ) ) ) / 1.6 );
				var hi = -1;
				if ( api.pointer && lift > 0 && ! still ) {
					ray.setFromCamera( new THREE.Vector2( api.pointer.x, api.pointer.y ), api.camera );
					if ( ray.ray.intersectPlane( plane0, hitV ) ) {
						var ci = Math.floor( ( hitV.x + halfW ) / cell ), cj = Math.floor( ( hitV.y + halfH ) / cell );
						if ( ci >= 0 && ci < cols && cj >= 0 && cj < rows ) { hi = cj * cols + ci; }
					}
				}
				var alA = meshA.geometry.attributes.alpha, alB = meshB.geometry.attributes.alpha;
				var place = function ( mesh, k, q, extraZ, extraTilt ) {
					var i = k % cols, j = Math.floor( k / cols ), sc = scat[ k ], w = 1 - q;
					var hx = ( i + 0.5 ) * cell - halfW, hy = ( j + 0.5 ) * cell - halfH;
					dummy.position.set( hx + sc.x * w * scatter * 2, hy + sc.y * w * scatter * 2, sc.z * w * ( 0.4 + scatter ) + extraZ );
					dummy.rotation.set( w * 0.7 + extraTilt, w * 0.4, sc.r * w * ( 0.3 + scatter ) );
					dummy.scale.setScalar( cell * ( 1 + w * 0.25 ) );
					dummy.updateMatrix(); mesh.setMatrixAt( k, dummy.matrix );
				};
				for ( var k = 0; k < delays.length; k++ ) {
					var d = delays[ k ];
					lifted[ k ] += ( ( k === hi ? 1 : 0 ) - lifted[ k ] ) * Math.min( 1, dt * 8 );
					var qi = Math.max( 0, Math.min( 1, intro * 1.7 - d * 0.7 ) ); qi = qi * qi * ( 3 - 2 * qi );
					place( meshA, k, qi, lifted[ k ] * lift * cell * 0.8, -lifted[ k ] * lift * 0.25 );
					alA.setX( k, Math.min( 1, qi * 6 ) );
					// The next photo's pieces fly in, in a random order, and land on top.
					var q = Math.max( 0, Math.min( 1, e * 1.7 - d * 0.7 ) ); q = q * q * ( 3 - 2 * q );
					place( meshB, k, q, 0.002, 0 );
					alB.setX( k, e > 0 ? Math.min( 1, q * 6 ) : 0 );
				}
				meshA.instanceMatrix.needsUpdate = true; meshB.instanceMatrix.needsUpdate = true;
				alA.needsUpdate = true; alB.needsUpdate = true;
				board.material.opacity = 1;
			}
		} );
	} );

	/* ---------------------------------------------------------------- Paper Fold */
	window.upwGallery3d.registerDesign( 'webgl_paper_fold', function ( ctx ) {
		if ( ! ctx.cards.length ) { return; }
		var size = ctx.num( 'data-tdg-card', 80 ) / 100, mt = Math.max( 0.2, Math.min( 0.9, ctx.num( 'data-tdg-movetime', 60 ) / 100 ) );
		var follow = ctx.num( 'data-tdg-follow', 40 ) / 100, paperCol = ctx.attr( 'data-tdg-paper', '#f4f1ea' ), order = ctx.attr( 'data-tdg-order', 'side' );
		var n = ctx.cards.length, texs = [], matF, root, holder, sideP = [], topG, st, H = 0.75, rx = 0, ry = 0;
		var seg = function ( e, a, b ) { var q = Math.max( 0, Math.min( 1, ( e - a ) / ( b - a ) ) ); return EASE( q ); };
		mount( ctx, {
			build: function ( api ) {
				var THREE = api.THREE; H = api.aspect;
				var radFrac = Math.min( 0.3, radiusOf( ctx.el ) / 300 );
				texs = ctx.cards.map( function ( c, k ) { var s = fullSrc( c ); return cardTexture( s, H, radFrac, function () { api.dirty = true; }, s ? null : Math.round( k * 360 / n ) ); } );
				matF = new THREE.MeshStandardMaterial( { map: texs[ 0 ], roughness: 0.6, transparent: true, alphaTest: 0.02 } );
				var matB = new THREE.MeshStandardMaterial( { color: new THREE.Color( paperCol ).convertSRGBToLinear(), roughness: 0.85, side: THREE.BackSide } );
				// One quarter of the sheet: the photo's quarter on the front, plain paper on the back.
				var quad = function ( qx, qy ) {
					var g = new THREE.PlaneGeometry( 0.5, H / 2 ), uv = g.attributes.uv;
					for ( var i = 0; i < uv.count; i++ ) { uv.setXY( i, ( qx + uv.getX( i ) ) / 2, ( qy + uv.getY( i ) ) / 2 ); }
					var grp = new THREE.Group(), f = new THREE.Mesh( g, matF ), b = new THREE.Mesh( g, matB );
					f.userData.card = 0; f.userData.appear = 0; api.cards.push( f );
					grp.add( f, b ); grp.position.set( qx ? 0.25 : -0.25, qy ? H / 4 : -H / 4, 0 );
					return grp;
				};
				// The right half folds over the left (a pivot on the centre line), then the top half folds down over the
				// bottom. Small z steps keep the folded layers apart (each flips to the front as it turns over).
				root = new THREE.Group(); holder = new THREE.Group(); holder.add( root );
				var bl = quad( 0, 0 ), brP = new THREE.Group(), br = quad( 1, 0 ); br.position.z = -0.004; brP.add( br );
				topG = new THREE.Group(); topG.position.z = -0.008;
				var tl = quad( 0, 1 ), trP = new THREE.Group(), tr = quad( 1, 1 ); tr.position.z = -0.004; trP.add( tr );
				topG.add( tl, trP ); root.add( bl, brP, topG );
				sideP = [ brP, trP ];
				api.scene.add( holder );
				st = stepper( api, ctx, n, mt, n );
				ctx.cards.forEach( function ( c, k ) { c.addEventListener( 'focusin', function () { st.go( k ); } ); } );
				api.key.position.set( -3, 4, 6 );
			},
			resize: function ( api ) {
				api.camera.position.set( 0, 0, 6 ); api.camera.lookAt( 0, 0, 0 );
				var hh = 6 * Math.tan( api.camera.fov * Math.PI / 360 ), hw = hh * api.camera.aspect;
				holder.scale.setScalar( Math.min( hw * 2 * size, hh * 2 * size / H ) );
			},
			frame: function ( api, dt, t ) {
				var s = st.state( t ), a = mod( s.a, n ), b = mod( s.b, n ), e = s.e;
				// Fold in half, in half again, then unfold as the next photo (the folded note shows the paper's back).
				var f1 = order === 'top' ? seg( e, 0.25, 0.5 ) : seg( e, 0, 0.25 ), f2 = order === 'top' ? seg( e, 0, 0.25 ) : seg( e, 0.25, 0.5 );
				var u2 = order === 'top' ? seg( e, 0.75, 1 ) : seg( e, 0.5, 0.75 ), u1 = order === 'top' ? seg( e, 0.5, 0.75 ) : seg( e, 0.75, 1 );
				var al = e < 0.5 ? f1 : 1 - u1, be = e < 0.5 ? f2 : 1 - u2;
				matF.map = texs[ e < 0.5 ? a : b ];
				sideP.forEach( function ( p ) { p.rotation.y = -al * Math.PI; } );
				topG.rotation.x = be * Math.PI;
				root.position.set( al * 0.25, be * H / 4, 0 );
				var folded = al * be, still = ctx.reduce || ctx.IN_EDITOR;
				root.position.z = Math.sin( Math.min( 1, al + be ) * Math.PI / 2 ) * 0.15;
				root.scale.setScalar( 1 + folded * 0.4 );
				root.rotation.z = folded * Math.sin( t * 2 ) * 0.05;
				var px = api.pointer && ! still ? api.pointer.x : 0, py = api.pointer && ! still ? api.pointer.y : 0;
				ry += ( px * 0.35 * follow - ry ) * Math.min( 1, dt * 5 ); rx += ( -py * 0.3 * follow - rx ) * Math.min( 1, dt * 5 );
				var ap = EASE( api.cards[ 0 ].userData.appear );
				holder.rotation.set( rx + ( 1 - ap ) * 0.6, ry, 0 );
				holder.position.y = ( 1 - ap ) * -0.6;
				matF.opacity = ap;
				api.cards.forEach( function ( m ) { m.userData.card = e > 0.5 ? b : a; } );
			}
		} );
	} );

	/* ---------------------------------------------------------------- Slot Reels */
	window.upwGallery3d.registerDesign( 'webgl_slot_reels', function ( ctx ) {
		if ( ! ctx.cards.length ) { return; }
		var R = Math.max( 2, Math.min( 6, ctx.num( 'data-tdg-reels', 3 ) ) ), spins = Math.max( 0, Math.min( 6, ctx.num( 'data-tdg-spins', 2 ) ) );
		var stg = ctx.num( 'data-tdg-stagger', 40 ) / 100, size = ctx.num( 'data-tdg-card', 78 ) / 100, mt = Math.max( 0.2, Math.min( 0.9, ctx.num( 'data-tdg-movetime', 55 ) / 100 ) );
		var follow = ctx.num( 'data-tdg-follow', 40 ) / 100, frameCol = ctx.attr( 'data-tdg-frame', '#1d2027' );
		// Six faces per drum, whatever the number of photos: the face a reel starts on shows the photo now showing,
		// the face it lands on the next, and the others the photos after it, so the drum stays a compact size.
		var n = ctx.cards.length, F = 6, drums = [], texs = [], holder, H = 0.75, apo = 0.65, rx = 0, ry = 0, st, faceMats = [];
		mount( ctx, {
			build: function ( api ) {
				var THREE = api.THREE; H = api.aspect;
				texs = ctx.cards.map( function ( c, k ) { var s = fullSrc( c ); return cardTexture( s, H, 0, function () { api.dirty = true; }, s ? null : Math.round( k * 360 / n ) ); } );
				apo = H / ( 2 * Math.tan( Math.PI / F ) );
				var wr = 1 / R, lin = function ( c ) { return new THREE.Color( c ).convertSRGBToLinear(); };
				var frameM = new THREE.MeshStandardMaterial( { color: lin( frameCol ), roughness: 0.4, metalness: 0.5 } );
				holder = new THREE.Group();
				for ( var r = 0; r < R; r++ ) {
					// Each reel carries its own vertical slice of the photo, so together the reels show it whole.
					var g = new THREE.PlaneGeometry( wr * 0.97, H, 1, 1 ), uv = g.attributes.uv;
					for ( var i = 0; i < uv.count; i++ ) { uv.setX( i, ( r + uv.getX( i ) ) / R ); }
					var drum = new THREE.Group(); drum.position.set( -0.5 + ( r + 0.5 ) * wr, 0, -apo );
					var mats = [];
					for ( var f = 0; f < F; f++ ) {
						var ph = f / F * Math.PI * 2, mat = new THREE.MeshStandardMaterial( { map: texs[ 0 ], roughness: 0.45, metalness: 0.05, transparent: true } );
						var m = new THREE.Mesh( g, mat );
						m.position.set( 0, apo * Math.sin( ph ), apo * Math.cos( ph ) ); m.rotation.x = -ph;
						m.userData.card = 0; m.userData.appear = 1;
						drum.add( m ); api.cards.push( m ); mats.push( mat ); faceMats.push( mat );
					}
					holder.add( drum ); drums.push( { g: drum, mats: mats, faces: drum.children } );
				}
				// The cabinet: a frame round the reel window, thin dividers, and shade over the drums' curve above and below.
				var outH = apo * 2 + 0.1, bz = 0.05;
				[ 1, -1 ].forEach( function ( sy ) { var bar = new THREE.Mesh( new THREE.BoxGeometry( 1.12, 0.07, 0.1 ), frameM ); bar.position.set( 0, sy * ( outH / 2 + 0.02 ), bz ); holder.add( bar ); } );
				[ 1, -1 ].forEach( function ( sx ) { var side = new THREE.Mesh( new THREE.BoxGeometry( 0.06, outH + 0.11, 0.1 ), frameM ); side.position.set( sx * 0.53, 0, bz ); holder.add( side ); } );
				for ( var d = 1; d < R; d++ ) { var dv = new THREE.Mesh( new THREE.BoxGeometry( 0.012, outH, 0.03 ), frameM ); dv.position.set( -0.5 + d * wr, 0, 0.02 ); holder.add( dv ); }
				var cv = document.createElement( 'canvas' ); cv.width = 4; cv.height = 128;
				var gc = cv.getContext( '2d' ), gr = gc.createLinearGradient( 0, 0, 0, 128 ), fc = new THREE.Color( frameCol );
				var rgb = Math.round( fc.r * 255 ) + ',' + Math.round( fc.g * 255 ) + ',' + Math.round( fc.b * 255 );
				// Clear over the photo itself; the faces above and below show dimly, then fade into the cabinet.
				var e0 = 0.5 - H / ( 2 * outH );
				[ [ 0, 1 ], [ e0 * 0.35, 0.96 ], [ e0 - 0.005, 0.45 ], [ e0 + 0.002, 0 ], [ 1 - e0 - 0.002, 0 ], [ 1 - e0 + 0.005, 0.45 ], [ 1 - e0 * 0.35, 0.96 ], [ 1, 1 ] ].forEach( function ( q ) {
					gr.addColorStop( Math.max( 0, Math.min( 1, q[ 0 ] ) ), 'rgba(' + rgb + ',' + q[ 1 ] + ')' );
				} );
				gc.fillStyle = gr; gc.fillRect( 0, 0, 4, 128 );
				var shTex = new THREE.CanvasTexture( cv ); shTex.encoding = THREE.sRGBEncoding;
				var shade = new THREE.Mesh( new THREE.PlaneGeometry( 1.0, outH ), new THREE.MeshBasicMaterial( { map: shTex, transparent: true, depthWrite: false } ) );
				shade.position.z = 0.01; shade.renderOrder = 2; holder.add( shade );
				var back = new THREE.Mesh( new THREE.PlaneGeometry( 1.1, outH ), new THREE.MeshBasicMaterial( { color: 0x0b0c10 } ) ); back.position.z = -apo * 2.05; holder.add( back );
				api.scene.add( holder );
				st = stepper( api, ctx, n, mt, n );
				ctx.cards.forEach( function ( c, k ) { c.addEventListener( 'focusin', function () { st.go( k ); } ); } );
				api.key.position.set( -2, 5, 6 );
			},
			resize: function ( api ) {
				api.camera.position.set( 0, 0, 7 ); api.camera.lookAt( 0, 0, 0 );
				var hh = 7 * Math.tan( api.camera.fov * Math.PI / 360 ), hw = hh * api.camera.aspect;
				holder.scale.setScalar( Math.min( hw * 2 * size / 1.12, hh * 2 * 0.9 / ( apo * 2 + 0.25 ) ) );
			},
			frame: function ( api, dt, t ) {
				var s = st.state( t ), step = Math.PI * 2 / F, still = ctx.reduce || ctx.IN_EDITOR;
				var gap = s.b - s.a, faceGap = gap === 0 ? 0 : ( mod( gap, F ) || F ) * ( gap > 0 ? 1 : -1 );
				if ( gap < 0 ) { faceGap = -( mod( -gap, F ) || F ); }
				var fa = mod( s.a, F ), fb = mod( fa + faceGap, F );
				var total = faceGap * step + ( faceGap >= 0 ? 1 : -1 ) * spins * Math.PI * 2;
				if ( ! api.introT ) { api.introT = api.t; }
				var intro = still ? 1 : Math.min( 1, ( api.t - api.introT ) / 1.8 );
				var span = 1 - stg * ( R - 1 ) / R, a = mod( s.a, n ), b = mod( s.b, n ), ap = Math.min( 1, intro * 3 );
				drums.forEach( function ( d, r ) {
					var er = Math.max( 0, Math.min( 1, ( s.e - r * stg / R ) / span ) ), ir = Math.max( 0, Math.min( 1, ( intro - r * stg / R ) / span ) );
					var eased = 1 - Math.pow( 1 - er, 3 ), bounce = er > 0.82 && er < 1 ? Math.sin( ( er - 0.82 ) / 0.18 * Math.PI ) * 0.05 : 0;
					d.g.rotation.x = fa * step + total * eased - bounce * ( total >= 0 ? 1 : -1 ) - ( 1 - ( 1 - Math.pow( 1 - ir, 3 ) ) ) * Math.PI * 4;
					// The faces' photos: the start face this photo, the landing face the next, the rest the ones after.
					for ( var f = 0; f < F; f++ ) {
						var k = mod( a + mod( f - fa, F ), n );
						if ( f === fb ) { k = fa === fb ? ( er > 0.5 ? b : a ) : b; } else if ( f === fa ) { k = a; }
						d.mats[ f ].map = texs[ k ]; d.mats[ f ].opacity = ap; d.faces[ f ].userData.card = k;
					}
				} );
				var px = api.pointer && ! still ? api.pointer.x : 0, py = api.pointer && ! still ? api.pointer.y : 0;
				ry += ( px * 0.3 * follow - ry ) * Math.min( 1, dt * 5 ); rx += ( -py * 0.25 * follow - rx ) * Math.min( 1, dt * 5 );
				holder.rotation.set( rx, ry, 0 );
			}
		} );
	} );

	/* ---------------------------------------------------------------- Peel */
	var PEEL_VS = [
		'uniform float c, R, W, H, flap; uniform vec2 dir;',
		'varying vec2 vUv; varying float vTh; varying float vS;',
		'void main() {',
		'  vec3 p = vec3( position.xy * vec2( W, H ), 0.0 ); vUv = uv; vTh = 0.0;',
		'  float s = dot( p.xy, dir ) - c; vS = s;',
		// The peeled part wraps round a cylinder at the curl line, then lies flat, face down, on top.
		'  if ( flap > 0.5 && s > 0.0 ) { float th = s / R;',
		'    if ( th < 3.14159 ) { p.xy += dir * ( R * sin( th ) - s ); p.z = R * ( 1.0 - cos( th ) ); vTh = th; }',
		'    else { p.xy += dir * ( -( s - 3.14159 * R ) - s ); p.z = 2.0 * R; vTh = 3.14159; } }',
		'  gl_Position = projectionMatrix * modelViewMatrix * vec4( p, 1.0 );',
		'}'
	].join( '\n' );
	var PEEL_FS = [
		'uniform sampler2D tP; uniform float aP, pa, op, rad, flap, R; uniform vec2 res; uniform vec3 paper;',
		'varying vec2 vUv; varying float vTh; varying float vS;',
		'vec2 cover( vec2 uv, float ia ) { vec2 s = pa > ia ? vec2( 1.0, ia / pa ) : vec2( pa / ia, 1.0 ); return ( uv - 0.5 ) * s + 0.5; }',
		'void main() {',
		'  vec2 px = vUv * res; vec2 q = abs( px - res * 0.5 ) - ( res * 0.5 - rad ); float dd = length( max( q, 0.0 ) ) - rad;',
		'  float a = clamp( 0.5 - dd, 0.0, 1.0 ) * op; if ( a < 0.01 ) { discard; }',
		'  vec3 col;',
		'  if ( vTh < 1.5708 ) { col = texture2D( tP, cover( vUv, aP ) ).rgb * ( 0.6 + 0.4 * max( cos( vTh ), 0.0 ) ); }',
		'  else { col = paper * ( 0.7 + 0.3 * max( -cos( vTh ), 0.0 ) ); }',
		'  col += vec3( 0.2 ) * exp( -pow( ( vTh - 1.35 ) * 2.6, 2.0 ) ) * step( 0.001, vTh );',     // a sheen on the curl
		// The sheet underneath: a soft shadow just beyond the curl line.
		'  if ( flap < 0.5 && vS > 0.0 ) { col *= 1.0 - 0.5 * exp( -vS / ( R * 1.6 ) ); }',
		'  gl_FragColor = vec4( col, a );',
		'}'
	].join( '\n' );

	window.upwGallery3d.registerDesign( 'webgl_peel', function ( ctx ) {
		if ( ! ctx.cards.length ) { return; }
		var corner = ctx.attr( 'data-tdg-corner', 'cycle' ), curl = ctx.num( 'data-tdg-curl', 50 ) / 100, lift = ctx.num( 'data-tdg-lift', 40 ) / 100;
		var mt = Math.max( 0.15, Math.min( 0.9, ctx.num( 'data-tdg-movetime', 45 ) / 100 ) ), paperCol = ctx.attr( 'data-tdg-paper', '#f4f1ea' );
		var n = ctx.cards.length, photos = [], texs = [], uA, uB, top, under, st, W = 4, Hh = 3, ptrLift = 0, ray, plane0, hitV;
		var CORNERS = { 'top-right': [ 1, 1 ], 'top-left': [ -1, 1 ], 'bottom-left': [ -1, -1 ], 'bottom-right': [ 1, -1 ] }, CYCLE = [ 'top-right', 'bottom-left', 'top-left', 'bottom-right' ];
		mount( ctx, {
			build: function ( api ) {
				var THREE = api.THREE;
				ctx.cards.forEach( function ( c, k ) {
					var tex = new THREE.Texture(); tex.generateMipmaps = false; tex.minFilter = THREE.LinearFilter;
					var p = loadPhoto( fullSrc( c ), Math.round( k * 360 / n ), function ( pp ) { swapImage( tex, pp.el ); api.dirty = true; } );
					tex.image = p.el; tex.needsUpdate = true; photos.push( p ); texs.push( tex );
				} );
				var pc = new THREE.Color( paperCol );
				var mkU = function ( flap ) {
					return { tP: { value: texs[ 0 ] }, aP: { value: 1.6 }, pa: { value: 1.6 }, op: { value: 1 }, rad: { value: radiusOf( ctx.el ) }, res: { value: new THREE.Vector2( 800, 500 ) },
						paper: { value: new THREE.Vector3( pc.r, pc.g, pc.b ) }, c: { value: 99 }, R: { value: 0.3 }, W: { value: 4 }, H: { value: 3 }, flap: { value: flap }, dir: { value: new THREE.Vector2( 0.7071, 0.7071 ) } };
				};
				uA = mkU( 1 ); uB = mkU( 0 );
				var mat = function ( u ) { return new THREE.ShaderMaterial( { uniforms: u, vertexShader: PEEL_VS, fragmentShader: PEEL_FS, transparent: true, side: THREE.DoubleSide, toneMapped: false } ); };
				top = new THREE.Mesh( new THREE.PlaneGeometry( 1, 1, 90, 90 ), mat( uA ) ); top.frustumCulled = false;
				under = new THREE.Mesh( new THREE.PlaneGeometry( 1, 1 ), mat( uB ) ); under.frustumCulled = false; under.position.z = -0.004;
				top.userData.card = 0; top.userData.appear = 0;
				api.cards.push( top ); api.scene.add( under, top );
				st = stepper( api, ctx, n, mt, n );
				ctx.cards.forEach( function ( c, k ) { c.addEventListener( 'focusin', function () { st.go( k ); } ); } );
				ray = new THREE.Raycaster(); plane0 = new THREE.Plane( new THREE.Vector3( 0, 0, 1 ), 0 ); hitV = new THREE.Vector3();
			},
			resize: function ( api ) {
				api.camera.position.set( 0, 0, 6 ); api.camera.lookAt( 0, 0, 0 );
				var hh = 6 * Math.tan( api.camera.fov * Math.PI / 360 ), hw = hh * api.camera.aspect;
				W = hw * 2 * 0.84; Hh = hh * 2 * 0.84;
				[ uA, uB ].forEach( function ( u ) { u.W.value = W; u.H.value = Hh; u.pa.value = W / Hh; u.res.value.set( api.w * 0.84, api.h * 0.84 ); u.R.value = Math.min( W, Hh ) * ( 0.04 + curl * 0.12 ); } );
			},
			frame: function ( api, dt, t ) {
				var s = st.state( t ), a = mod( s.a, n ), b = mod( s.b, n ), e = s.e, still = ctx.reduce || ctx.IN_EDITOR;
				uA.tP.value = texs[ a ]; uA.aP.value = photos[ a ].aspect; uB.tP.value = texs[ b ]; uB.aP.value = photos[ b ].aspect;
				var cn = CORNERS[ corner === 'cycle' ? CYCLE[ mod( s.a, 4 ) ] : corner ] || CORNERS[ 'top-right' ];
				var dl = Math.sqrt( W * W + Hh * Hh ), dx = cn[ 0 ] * W / dl, dy = cn[ 1 ] * Hh / dl;
				// Peel along the diagonal toward the corner; the curl line runs square to it.
				var D = Math.abs( dx ) * W / 2 + Math.abs( dy ) * Hh / 2, Rr = uA.R.value;
				// A pointer near the peeling corner lifts it a little more.
				var target = 0;
				if ( api.pointer && ! still ) {
					ray.setFromCamera( new api.THREE.Vector2( api.pointer.x, api.pointer.y ), api.camera );
					if ( ray.ray.intersectPlane( plane0, hitV ) ) { var qx = hitV.x - cn[ 0 ] * W / 2, qy = hitV.y - cn[ 1 ] * Hh / 2; target = Math.exp( -( qx * qx + qy * qy ) / ( Rr * Rr * 40 ) ); }
				}
				ptrLift += ( target - ptrLift ) * Math.min( 1, dt * 6 );
				var c0 = D - Rr * ( 0.15 + lift * 1.6 ) - ptrLift * Rr * 3 * lift, c1 = -D - Math.PI * Rr * 1.2;
				uA.dir.value.set( dx, dy ); uB.dir.value.set( dx, dy );
				uA.c.value = c0 + ( c1 - c0 ) * e; uB.c.value = uA.c.value;
				var ap = EASE( top.userData.appear );
				uA.op.value = ap * ( 1 - Math.max( 0, Math.min( 1, ( e - 0.86 ) / 0.14 ) ) ); uB.op.value = ap;
				top.userData.card = e > 0.5 ? b : a;
			}
		} );
	} );

	/* ---------------------------------------------------------------- Bookshelf */
	window.upwGallery3d.registerDesign( 'webgl_bookshelf', function ( ctx ) {
		if ( ! ctx.cards.length ) { return; }
		var count = Math.max( 6, Math.min( 30, ctx.num( 'data-tdg-count', 16 ) ) ), shelves = Math.max( 1, Math.min( 3, ctx.num( 'data-tdg-shelves', 1 ) ) );
		var mt = Math.max( 0.15, Math.min( 0.9, ctx.num( 'data-tdg-movetime', 45 ) / 100 ) ), woodCol = ctx.attr( 'data-tdg-wood', '#6b4a32' );
		var pull = ctx.num( 'data-tdg-pull', 100 ) / 100, n = ctx.cards.length, books = [], st, group, W = 4, Ht = 3, D = 1, perm = [];
		var rand = function ( i, k ) { var x = Math.sin( i * 91.345 + k * 47.853 ) * 43758.5453; return x - Math.floor( x ); };
		mount( ctx, {
			build: function ( api ) {
				var THREE = api.THREE, lin = function ( c ) { return new THREE.Color( c ).convertSRGBToLinear(); };
				var spines = [];
				var texs = ctx.cards.map( function ( c, k ) { var s = fullSrc( c ); spines[ k ] = [];
					return cardTexture( s, api.aspect, 0, function () { api.dirty = true; spines[ k ].forEach( function ( t ) { t.needsUpdate = true; } ); }, s ? null : Math.round( k * 360 / n ) ); } );
				var H = 1; D = H / api.aspect;                         // the cover is the book's side: depth × height
				var wood = new THREE.MeshStandardMaterial( { color: lin( woodCol ), roughness: 0.78 } );
				var pages = new THREE.MeshStandardMaterial( { color: lin( '#efe8d8' ), roughness: 0.92 } );
				var backM = new THREE.MeshStandardMaterial( { color: lin( '#2a2d33' ), roughness: 0.6 } );
				group = new THREE.Group();
				var perShelf = Math.ceil( count / shelves ), rowH = H * 1.14 + 0.07, widths = [], made = 0;
				for ( var r = 0; r < shelves; r++ ) {
					var x = 0, rowBooks = [];
					for ( var j = 0; j < perShelf && made < count; j++ ) {
						var i = made++, k = i % n, th = H * ( 0.15 + rand( i, 1 ) * 0.1 ), h = H * ( 0.84 + rand( i, 2 ) * 0.16 );
						// The spine: a narrow vertical slice of the same photo, a touch darker, so the row reads as one set.
						var sp = texs[ k ].clone(); sp.repeat.set( 0.1, 1 ); sp.offset.set( 0.45, 0 ); sp.needsUpdate = true; spines[ k ].push( sp );
						var spineM = new THREE.MeshStandardMaterial( { map: sp, color: 0xcfcfcf, roughness: 0.55 } );
						var coverM = new THREE.MeshStandardMaterial( { map: texs[ k ], roughness: 0.5 } );
						var m = new THREE.Mesh( new THREE.BoxGeometry( th, h, D ), [ coverM, backM, pages, pages, spineM, pages ] );
						m.userData.card = k; m.userData.appear = 0;
						rowBooks.push( { m: m, x: x + th / 2, h: h, th: th, row: r, q: 0, hov: 0 } );
						x += th + 0.012;
					}
					widths.push( x ); books = books.concat( rowBooks );
				}
				W = Math.max.apply( null, widths ) + 0.3; Ht = shelves * rowH + 0.08;
				// Shelves: a board under each row, sides, and a back panel.
				for ( var s2 = 0; s2 < shelves; s2++ ) {
					var y0 = Ht / 2 - 0.04 - ( s2 + 1 ) * rowH;
					var board = new THREE.Mesh( new THREE.BoxGeometry( W, 0.07, D * 1.12 ), wood ); board.position.set( 0, y0 + 0.035, 0 ); group.add( board );
				}
				var top = new THREE.Mesh( new THREE.BoxGeometry( W, 0.07, D * 1.12 ), wood ); top.position.set( 0, Ht / 2 - 0.035, 0 ); group.add( top );
				[ -1, 1 ].forEach( function ( sx ) { var side = new THREE.Mesh( new THREE.BoxGeometry( 0.07, Ht, D * 1.12 ), wood ); side.position.set( sx * ( W / 2 - 0.035 ), 0, 0 ); group.add( side ); } );
				var back = new THREE.Mesh( new THREE.PlaneGeometry( W, Ht ), new THREE.MeshStandardMaterial( { color: lin( woodCol ), roughness: 0.9 } ) );
				back.material.color.multiplyScalar( 0.55 ); back.position.z = -D * 0.56; group.add( back );
				books.forEach( function ( b ) {
					var rowW = widths[ b.row ], y0 = Ht / 2 - 0.04 - ( b.row + 1 ) * rowH + 0.07;
					b.home = new THREE.Vector3( b.x - rowW / 2, y0 + b.h / 2, 0 );
					b.m.position.copy( b.home ); group.add( b.m ); api.cards.push( b.m );
				} );
				// Books come out in a shuffled order, not left to right.
				for ( var p = 0; p < books.length; p++ ) { perm.push( p ); }
				for ( var q = perm.length - 1; q > 0; q-- ) { var z = Math.floor( rand( q, 9 ) * ( q + 1 ) ), tmp = perm[ q ]; perm[ q ] = perm[ z ]; perm[ z ] = tmp; }
				api.scene.add( group );
				st = stepper( api, ctx, books.length, mt, books.length );
				api.key.position.set( -3, 4, 6 );
			},
			resize: function ( api ) {
				var tanH = Math.tan( api.camera.fov * Math.PI / 360 );
				var dist = Math.max( W / api.camera.aspect, Ht ) * 1.08 / ( 2 * tanH ) + D;
				api.camera.position.set( 0, Ht * 0.06, dist ); api.camera.lookAt( 0, 0, 0 );
			},
			frame: function ( api, dt, t ) {
				var s = st.state( t ), nb = books.length, ba = perm[ mod( s.a, nb ) ], bb = perm[ mod( s.b, nb ) ], drive = ctx.attr( 'data-tdg-drive', 'continuous' );
				books.forEach( function ( b, i ) {
					// Autoplay: one book out at a time; the pointer pulls out the book under it.
					var target = drive === 'static' ? 0 : ( i === ba ? 1 - s.e : 0 ) + ( i === bb ? s.e : 0 );
					b.hov += ( ( api.hover === i || api.focus === i ? 1 : 0 ) - b.hov ) * Math.min( 1, dt * 7 );
					var q = Math.min( 1, Math.max( target, b.hov ) ) * pull;
					var slide = EASE( Math.min( 1, q * 2 ) ), turn = EASE( Math.max( 0, q * 2 - 1 ) );
					// Turned to face out, the cover is as wide as the book is deep: near either end it slides toward
					// the middle so the cover stays inside the bookcase.
					// Pulled forward it is nearer the camera, so perspective enlarges it: allow for that too.
					var zf = b.home.z + slide * D * 1.05 + turn * 0.25, camZ = api.camera.position.z, k = camZ / Math.max( 0.5, camZ - zf - D * 0.03 );
					var lim = Math.max( 0, ( W / 2 - 0.08 ) / k - D * 0.53 ), cx = Math.max( -lim, Math.min( lim, b.home.x ) );
					b.m.position.set( b.home.x + ( cx - b.home.x ) * turn, b.home.y + turn * 0.04, zf );
					b.m.rotation.set( 0, -turn * Math.PI / 2, 0 );
					b.m.scale.setScalar( 1 + turn * 0.06 );
					var ap = EASE( b.m.userData.appear );
					b.m.position.y -= ( 1 - ap ) * 0.4;
				} );
			}
		} );
	} );

	/* ---------------------------------------------------------------- Snow Globe */
	var GLASS_VS = 'varying vec3 vN; varying vec3 vV; void main() { vN = normalize( normalMatrix * normal ); vec4 mv = modelViewMatrix * vec4( position, 1.0 ); vV = normalize( -mv.xyz ); gl_Position = projectionMatrix * mv; }';
	var GLASS_FS = [
		'uniform vec3 tint; uniform float op;',
		'varying vec3 vN; varying vec3 vV;',
		'void main() {',
		'  vec3 n = normalize( vN ), v = normalize( vV );',
		'  float fr = pow( 1.0 - max( dot( n, v ), 0.0 ), 2.6 );',
		'  vec3 L = normalize( vec3( -0.5, 0.7, 0.6 ) ); float sp = pow( max( dot( reflect( -L, n ), v ), 0.0 ), 70.0 );',
		'  vec3 L2 = normalize( vec3( 0.6, 0.2, 0.7 ) ); float sp2 = pow( max( dot( reflect( -L2, n ), v ), 0.0 ), 18.0 ) * 0.25;',
		'  gl_FragColor = vec4( tint * fr * 0.9 + vec3( sp + sp2 ), clamp( fr * 0.75 + sp + sp2 + 0.03, 0.0, 1.0 ) * op );',
		'}'
	].join( '\n' );

	window.upwGallery3d.registerDesign( 'webgl_snow_globe', function ( ctx ) {
		if ( ! ctx.cards.length ) { return; }
		var snow = ctx.num( 'data-tdg-snow', 60 ) / 100, shake = ctx.num( 'data-tdg-shake', 50 ) / 100, stir = ctx.num( 'data-tdg-stir', 50 ) / 100;
		var baseCol = ctx.attr( 'data-tdg-base', '#3b2a20' ), size = ctx.num( 'data-tdg-card', 85 ) / 100, mt = Math.max( 0.2, Math.min( 0.9, ctx.num( 'data-tdg-movetime', 55 ) / 100 ) );
		var n = ctx.cards.length, R = 1, texs = [], pA, pB, globe, holder, pts, pos, vel, N = 0, st, floorY = -0.62, lastPtr = null, gust = 0, glassU;
		mount( ctx, {
			build: function ( api ) {
				var THREE = api.THREE, lin = function ( c ) { return new THREE.Color( c ).convertSRGBToLinear(); };
				var radFrac = Math.min( 0.3, radiusOf( ctx.el ) / 200 );
				texs = ctx.cards.map( function ( c, k ) { var s = fullSrc( c ); return cardTexture( s, api.aspect, radFrac, function () { api.dirty = true; }, s ? null : Math.round( k * 360 / n ) ); } );
				holder = new THREE.Group(); globe = new THREE.Group(); holder.add( globe );
				// The photo stands inside the globe, on a drift of snow.
				var pw = R * 1.22, ph = pw * api.aspect; if ( ph > R * 1.25 ) { ph = R * 1.25; pw = ph / api.aspect; }
				var mk = function () { var m = new THREE.Mesh( new THREE.PlaneGeometry( pw, ph ), new THREE.MeshBasicMaterial( { map: texs[ 0 ], transparent: true, toneMapped: false } ) ); m.position.set( 0, floorY + ph / 2 + 0.02, 0 ); globe.add( m ); return m; };
				pA = mk(); pB = mk(); pB.position.z = 0.002;
				pA.userData.card = 0; pA.userData.appear = 0; api.cards.push( pA );
				var drift = new THREE.Mesh( new THREE.SphereGeometry( R * 0.97, 48, 12, 0, Math.PI * 2, Math.PI * 0.72, Math.PI * 0.28 ), new THREE.MeshStandardMaterial( { color: 0xf4f6fa, roughness: 0.95 } ) );
				globe.add( drift );
				var cap = new THREE.Mesh( new THREE.CircleGeometry( Math.sin( Math.PI * 0.28 ) * R * 0.97, 48 ), new THREE.MeshStandardMaterial( { color: 0xeef1f6, roughness: 0.95 } ) );
				cap.rotation.x = -Math.PI / 2; cap.position.y = -Math.cos( Math.PI * 0.28 ) * R * 0.97; globe.add( cap );
				floorY = cap.position.y;
				pA.position.y = pB.position.y = floorY + ph / 2 - 0.02;
				// Snow: points with a round soft sprite, simulated on the CPU.
				N = Math.round( 60 + snow * 1100 );
				pos = new Float32Array( N * 3 ); vel = new Float32Array( N * 3 );
				for ( var i = 0; i < N; i++ ) {
					var a = Math.random() * 6.283, rr = Math.sqrt( Math.random() ) * R * 0.85;
					pos[ i * 3 ] = Math.cos( a ) * rr; pos[ i * 3 + 2 ] = Math.sin( a ) * rr; pos[ i * 3 + 1 ] = floorY + 0.02 + Math.random() * R * 1.5;
				}
				var cv = document.createElement( 'canvas' ); cv.width = cv.height = 32; var g = cv.getContext( '2d' ), gr = g.createRadialGradient( 16, 16, 0, 16, 16, 16 );
				gr.addColorStop( 0, 'rgba(255,255,255,1)' ); gr.addColorStop( 0.5, 'rgba(255,255,255,.85)' ); gr.addColorStop( 1, 'rgba(255,255,255,0)' ); g.fillStyle = gr; g.fillRect( 0, 0, 32, 32 );
				var geo = new THREE.BufferGeometry(); geo.setAttribute( 'position', new THREE.BufferAttribute( pos, 3 ) );
				pts = new THREE.Points( geo, new THREE.PointsMaterial( { size: R * 0.045, map: new THREE.CanvasTexture( cv ), transparent: true, depthWrite: false, color: 0xffffff } ) );
				pts.frustumCulled = false; globe.add( pts );
				// The glass: a rim-lit shell with highlights (drawn last, over the photo and snow).
				glassU = { tint: { value: new THREE.Vector3( 0.75, 0.88, 1.0 ) }, op: { value: 1 } };
				var glass = new THREE.Mesh( new THREE.SphereGeometry( R, 64, 48 ), new THREE.ShaderMaterial( { uniforms: glassU, vertexShader: GLASS_VS, fragmentShader: GLASS_FS, transparent: true, depthWrite: false, toneMapped: false } ) );
				glass.renderOrder = 5; globe.add( glass );
				// The base.
				var baseM = new THREE.MeshStandardMaterial( { color: lin( baseCol ), roughness: 0.45, metalness: 0.15 } );
				var base = new THREE.Mesh( new THREE.CylinderGeometry( R * 0.8, R * 0.98, R * 0.46, 64 ), baseM ); base.position.y = -R * 0.97; holder.add( base );
				var lip = new THREE.Mesh( new THREE.TorusGeometry( R * 0.79, R * 0.035, 12, 64 ), baseM ); lip.rotation.x = Math.PI / 2; lip.position.y = -R * 0.74; holder.add( lip );
				api.scene.add( holder );
				st = stepper( api, ctx, n, mt, n );
				ctx.cards.forEach( function ( c, k ) { c.addEventListener( 'focusin', function () { st.go( k ); } ); } );
				api.key.position.set( -3, 5, 6 );
			},
			resize: function ( api ) {
				var tanH = Math.tan( api.camera.fov * Math.PI / 360 ), h = R * 2.3, w = R * 2.05;
				var dist = Math.max( w / api.camera.aspect, h ) / ( 2 * tanH ) / size;
				api.camera.position.set( 0, R * 0.25, dist ); api.camera.lookAt( 0, -R * 0.12, 0 );
			},
			frame: function ( api, dt, t ) {
				var s = st.state( t ), a = mod( s.a, n ), b = mod( s.b, n ), e = s.e, still = ctx.reduce || ctx.IN_EDITOR;
				pA.material.map = texs[ a ]; pB.material.map = texs[ b ];
				var f = Math.max( 0, Math.min( 1, ( e - 0.4 ) / 0.25 ) ), ap = EASE( pA.userData.appear );
				pA.material.opacity = ( 1 - f ) * ap; pB.material.opacity = f * ap; pB.visible = f > 0.001;
				pA.userData.card = e > 0.5 ? b : a;
				// The shake: the globe rocks for the first part of the change and throws the snow up.
				var sh = Math.sin( Math.min( 1, e / 0.45 ) * Math.PI ) * shake;
				globe.rotation.z = Math.sin( t * 17 ) * 0.07 * sh; holder.position.x = Math.sin( t * 23 ) * 0.035 * sh;
				if ( api.pointer && ! still ) { if ( lastPtr ) { gust += Math.min( 0.4, Math.abs( api.pointer.x - lastPtr.x ) + Math.abs( api.pointer.y - lastPtr.y ) ) * stir * 4; } lastPtr = { x: api.pointer.x, y: api.pointer.y }; } else { lastPtr = null; }
				gust = Math.min( 1.5, gust * Math.pow( 0.4, dt ) );
				if ( still ) { return; }
				var energy = sh * 2.2 + gust, d = Math.min( dt, 0.033 ), Rin = R * 0.93;
				for ( var i = 0; i < N; i++ ) {
					var ix = i * 3, x = pos[ ix ], y = pos[ ix + 1 ], z = pos[ ix + 2 ];
					if ( energy > 0.01 ) {
						// Swirl round the centre and up, with a little randomness per flake.
						var k = energy * ( 0.6 + ( i % 7 ) / 10 );
						vel[ ix ] += ( -z * 2.2 + ( Math.random() - 0.5 ) * 1.5 ) * k * d;
						vel[ ix + 1 ] += ( 2.6 * ( 1 - ( y - floorY ) / ( R * 1.9 ) ) + ( Math.random() - 0.5 ) ) * k * d;
						vel[ ix + 2 ] += ( x * 2.2 + ( Math.random() - 0.5 ) * 1.5 ) * k * d;
					}
					vel[ ix + 1 ] -= 0.22 * d;                                    // a slow fall
					var drag = Math.pow( 0.18, d ); vel[ ix ] *= drag; vel[ ix + 1 ] *= drag; vel[ ix + 2 ] *= drag;
					x += vel[ ix ] * d; y += vel[ ix + 1 ] * d; z += vel[ ix + 2 ] * d;
					var l = Math.sqrt( x * x + y * y + z * z );
					if ( l > Rin ) { x *= Rin / l; y *= Rin / l; z *= Rin / l; vel[ ix ] *= -0.3; vel[ ix + 1 ] *= -0.3; vel[ ix + 2 ] *= -0.3; }
					if ( y < floorY + 0.015 ) { y = floorY + 0.015; vel[ ix ] *= 0.2; vel[ ix + 1 ] = 0; vel[ ix + 2 ] *= 0.2; }
					pos[ ix ] = x; pos[ ix + 1 ] = y; pos[ ix + 2 ] = z;
				}
				pts.geometry.attributes.position.needsUpdate = true;
				pts.material.opacity = ap; glassU.op.value = ap;
			}
		} );
	} );

	/* ---------------------------------------------------------------- TV Wall */
	var TV_FS = [
		'uniform sampler2D tA; uniform sampler2D tB; uniform float aA, aB, sa, m, stat, time, seed, scan, curve, op, lines;',
		'varying vec2 vUv;',
		'float h1( vec2 q ) { return fract( sin( dot( q, vec2( 127.1, 311.7 ) ) ) * 43758.5453 ); }',
		'vec2 cover( vec2 uv, float ia ) { vec2 s = sa > ia ? vec2( 1.0, ia / sa ) : vec2( sa / ia, 1.0 ); return ( uv - 0.5 ) * s + 0.5; }',
		'void main() {',
		'  vec2 c = vUv - 0.5;',
		'  vec2 uv = 0.5 + c * ( 1.0 + curve * 0.6 * dot( c, c ) ) / ( 1.0 + curve * 0.12 );',          // a bulging glass screen
		'  float inside = step( 0.0, uv.x ) * step( uv.x, 1.0 ) * step( 0.0, uv.y ) * step( uv.y, 1.0 );',
		'  float row = floor( uv.y * 120.0 ); uv.x += ( h1( vec2( row, floor( time * 30.0 ) + seed ) ) - 0.5 ) * 0.07 * stat;',
		'  vec3 col = mix( texture2D( tA, cover( uv, aA ) ).rgb, texture2D( tB, cover( uv, aB ) ).rgb, m );',
		'  float nz = h1( floor( uv * vec2( 200.0, 150.0 ) ) + floor( time * 24.0 ) * 1.37 + seed );',
		'  col = mix( col, vec3( nz ), clamp( stat, 0.0, 1.0 ) * 0.9 );',
		'  col *= 1.0 - scan * 0.3 * ( 0.5 + 0.5 * sin( uv.y * lines * 6.2832 ) );',
		'  col *= 1.0 - 0.65 * pow( clamp( length( c * vec2( 1.0, 1.15 ) ) * 1.3, 0.0, 1.0 ), 2.4 );',
		'  gl_FragColor = vec4( col * inside * 1.08, op );',
		'}'
	].join( '\n' );

	window.upwGallery3d.registerDesign( 'webgl_tv_wall', function ( ctx ) {
		if ( ! ctx.cards.length ) { return; }
		var count = Math.max( 4, Math.min( 20, ctx.num( 'data-tdg-count', 9 ) ) ), bodyCol = ctx.attr( 'data-tdg-body', '#d8c9ad' ), mixed = ctx.attr( 'data-tdg-mixed', '1' ) === '1';
		var scan = ctx.num( 'data-tdg-scan', 50 ) / 100, stat = ctx.num( 'data-tdg-static', 50 ) / 100, curve = ctx.num( 'data-tdg-curve', 50 ) / 100, dir = ctx.num( 'data-tdg-dir', 1 );
		var n = ctx.cards.length, photos = [], texs = [], tvs = [], group, W = 4, Ht = 3;
		var PALETTE = [ '#d8c9ad', '#3a3d44', '#b5452f', '#6f8a7a', '#c99a3b', '#e8e4da', '#2f4a6b' ];
		var rand = function ( i, k ) { var x = Math.sin( i * 63.71 + k * 17.13 ) * 43758.5453; return x - Math.floor( x ); };
		mount( ctx, {
			build: function ( api ) {
				var THREE = api.THREE, lin = function ( c ) { return new THREE.Color( c ).convertSRGBToLinear(); };
				ctx.cards.forEach( function ( c, k ) {
					var tex = new THREE.Texture(); tex.generateMipmaps = false; tex.minFilter = THREE.LinearFilter;
					var p = loadPhoto( fullSrc( c ), Math.round( k * 360 / n ), function ( pp ) { swapImage( tex, pp.el ); api.dirty = true; } );
					tex.image = p.el; tex.needsUpdate = true; photos.push( p ); texs.push( tex );
				} );
				// Lay the sets out in rows that suit the stage's shape, stacked from the bottom like a shop window.
				var sw0 = ctx.stage.clientWidth || 1200, sh0 = ctx.stage.clientHeight || 600;
				var cols = Math.max( 2, Math.min( count, Math.round( Math.sqrt( count * ( sw0 / sh0 ) / 1.3 ) ) ) ), rows = Math.ceil( count / cols );
				var TW = 1.36, TH = 1.04, TD = 0.85, sw = 0.94, shh = 0.72;
				group = new THREE.Group();
				var dark = new THREE.MeshStandardMaterial( { color: 0x15161a, roughness: 0.5 } ), knobM = new THREE.MeshStandardMaterial( { color: 0x2a2b30, roughness: 0.4, metalness: 0.4 } );
				for ( var i = 0; i < count; i++ ) {
					var r = Math.floor( i / cols ), cIdx = i % cols, inRow = Math.min( cols, count - r * cols );
					var sc = 0.86 + rand( i, 1 ) * 0.2, col = mixed ? PALETTE[ Math.floor( rand( i, 2 ) * PALETTE.length ) ] : bodyCol;
					var tv = new THREE.Group();
					var body = new THREE.Mesh( new THREE.BoxGeometry( TW, TH, TD ), new THREE.MeshStandardMaterial( { color: lin( col ), roughness: 0.55, metalness: 0.08 } ) ); tv.add( body );
					var bezel = new THREE.Mesh( new THREE.PlaneGeometry( sw * 1.09, shh * 1.12 ), dark ); bezel.position.set( -0.17, 0, TD / 2 + 0.003 ); tv.add( bezel );
					var u = { tA: { value: texs[ 0 ] }, tB: { value: texs[ 0 ] }, aA: { value: 1.6 }, aB: { value: 1.6 }, sa: { value: sw / shh }, m: { value: 0 }, stat: { value: 0 },
						time: { value: 0 }, seed: { value: rand( i, 3 ) * 50 }, scan: { value: scan }, curve: { value: curve }, op: { value: 1 }, lines: { value: 80 } };
					var screen = new THREE.Mesh( new THREE.PlaneGeometry( sw, shh ), new THREE.ShaderMaterial( { uniforms: u, vertexShader: PLAIN_VS, fragmentShader: TV_FS, transparent: true, toneMapped: false } ) );
					screen.position.set( -0.17, 0, TD / 2 + 0.006 ); tv.add( screen );
					[ 0.18, -0.12 ].forEach( function ( ky ) { var kn = new THREE.Mesh( new THREE.CylinderGeometry( 0.065, 0.065, 0.05, 20 ), knobM ); kn.rotation.x = Math.PI / 2; kn.position.set( TW / 2 - 0.15, ky, TD / 2 + 0.02 ); tv.add( kn ); } );
					var grille = new THREE.Mesh( new THREE.PlaneGeometry( 0.16, 0.2 ), dark ); grille.position.set( TW / 2 - 0.15, -0.33, TD / 2 + 0.003 ); tv.add( grille );
					tv.scale.setScalar( sc );
					var x = ( cIdx - ( inRow - 1 ) / 2 ) * TW * 1.06, y = r * TH * 1.02 + TH * sc / 2;
					tv.position.set( x, y, ( rand( i, 4 ) - 0.5 ) * 0.25 ); tv.rotation.set( 0, ( rand( i, 5 ) - 0.5 ) * 0.12, ( rand( i, 6 ) - 0.5 ) * 0.05 );
					screen.userData.card = i % n; screen.userData.appear = 0;
					group.add( tv ); api.cards.push( screen );
					tvs.push( { g: tv, u: u, delay: rand( i, 7 ), k: i, hov: 0, home: tv.position.clone(), sc: sc } );
				}
				W = cols * TW * 1.06; Ht = rows * TH * 1.02;
				group.position.y = -Ht / 2;
				api.scene.add( group );
				api.key.position.set( -3, 4, 6 );
			},
			resize: function ( api ) {
				var tanH = Math.tan( api.camera.fov * Math.PI / 360 );
				var dist = Math.max( W / api.camera.aspect, Ht ) * 1.1 / ( 2 * tanH ) + 0.5;
				api.camera.position.set( 0, Ht * 0.04, dist ); api.camera.lookAt( 0, 0, 0 );
				// One scanline per ~3.5 screen pixels: finer lines alias into rings on a small set.
				var pxPerUnit = api.h / ( 2 * dist * tanH );
				tvs.forEach( function ( tv ) { tv.u.lines.value = Math.max( 24, Math.min( 160, 0.72 * tv.sc * pxPerUnit / 3.5 ) ); } );
			},
			frame: function ( api, dt, t ) {
				var drive = ctx.attr( 'data-tdg-drive', 'continuous' ), p = drive === 'static' ? 0 : api.progress() * dir;
				tvs.forEach( function ( tv, i ) {
					// Each set changes on its own beat: Loop Duration is the time between one set's changes.
					var local = p + tv.delay, step = Math.floor( local ), f = local - step, w = 0.16;
					var a = mod( tv.k + step, n ), b = mod( tv.k + step + 1, n );
					var sw2 = f > 1 - w ? ( f - ( 1 - w ) ) / w : 0;
					tv.hov += ( ( api.hover === i || api.focus === i ? 1 : 0 ) - tv.hov ) * Math.min( 1, dt * 6 );
					tv.u.tA.value = texs[ a ]; tv.u.tB.value = texs[ b ]; tv.u.aA.value = photos[ a ].aspect; tv.u.aB.value = photos[ b ].aspect;
					tv.u.m.value = sw2 > 0.5 ? 1 : 0;
					tv.u.stat.value = ( Math.sin( sw2 * Math.PI ) * ( 0.4 + stat * 0.6 ) + stat * 0.04 ) * ( 1 - tv.hov );
					tv.u.time.value = t;
					var ap = EASE( api.cards[ i ].userData.appear );
					tv.u.op.value = ap;
					api.cards[ i ].userData.card = sw2 > 0.5 ? b : a;
					tv.g.position.set( tv.home.x, tv.home.y - ( 1 - ap ) * 0.5, tv.home.z + tv.hov * 0.35 );
					tv.g.scale.setScalar( tv.sc * ( 1 + tv.hov * 0.06 ) );
				} );
			}
		} );
	} );
} )();
