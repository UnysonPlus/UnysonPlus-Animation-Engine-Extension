/**
 * 3D Gallery — Carousel Ring driver.
 *
 * Lays a set of cards around a 3D ring (rotateY(iθ) translateZ(R)), spins the ring per the chosen
 * Motion (auto-rotate / drag / scroll-scrub / static), and fades cards as they turn to the back
 * (Back Fade). Radius, card px size and perspective are derived from the stage width so it stays
 * responsive. Pure CSS 3D + one rAF loop, no library. Honours reduced-motion (static ring).
 */
(function () {
	'use strict';
	if ( typeof window === 'undefined' || typeof document === 'undefined' ) { return; }
	var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	// Generation guard. On the front end this is inert (GEN stays 0, behaves identically). The admin
	// PREVIEW rebuilds the scene on every option change, but a driver's rAF loop is designed to run for
	// a page's lifetime and never stop. Shadowing requestAnimationFrame stamps each callback with the
	// current generation, so upwGallery3dBumpGen() lets every previous loop die instead of piling up.
	var GEN = 0;
	window.upwGallery3dBumpGen = function () { GEN++; };
	var _raf = window.requestAnimationFrame.bind( window );
	function requestAnimationFrame( fn ) { var g = GEN; return _raf( function ( t ) { if ( g === GEN ) { fn( t ); } } ); }

	function num( el, attr, dflt ) { var v = parseFloat( el.getAttribute( attr ) ); return isNaN( v ) ? dflt : v; }
	function clamp( v, a, b ) { return v < a ? a : ( v > b ? b : v ); }

	// Hover behaviour for auto-rotating drives: 'none' keeps full speed, 'pause' stops on hover,
	// 'slow' eases to a crawl. Returns a getter for the current speed multiplier so each driver can
	// scale its per-frame advance. (Legacy data-tdg-pause="1"/"0" is honoured as pause/none.)
	//
	// The hit area is the CARDS' bounding box, not the whole stage: the stage is much larger than the
	// artwork (side/top margins), so reacting out in a dead corner feels broken. We hit-test with maths
	// instead of an overlay <div>, because an overlay above the cards would swallow their lightbox
	// clicks + caption hovers, and one behind them would never fire when hovering an actual card.
	// The box is stable, so unlike per-card hover this needs no hysteresis — no flicker.
	function hoverFactor( el ) {
		var mode = el.getAttribute( 'data-tdg-hover' );
		if ( mode === null ) { mode = num( el, 'data-tdg-pause', 1 ) ? 'pause' : 'none'; }
		if ( mode !== 'pause' && mode !== 'slow' ) { return function () { return 1; }; }

		var over = false, box = null, elRect = null;
		// Union of the cards' rects, cached as offsets from the element's top-left.
		function measure() {
			elRect = el.getBoundingClientRect();
			var cards = el.querySelectorAll( '.tdg__card' );
			var minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity, i, r;
			for ( i = 0; i < cards.length; i++ ) {
				r = cards[ i ].getBoundingClientRect();
				if ( ! r.width || ! r.height ) { continue; }
				if ( r.left < minX )   { minX = r.left; }
				if ( r.top < minY )    { minY = r.top; }
				if ( r.right > maxX )  { maxX = r.right; }
				if ( r.bottom > maxY ) { maxY = r.bottom; }
			}
			box = ( minX < maxX ) ? { l: minX - elRect.left, t: minY - elRect.top, r: maxX - elRect.left, b: maxY - elRect.top } : null;
		}
		el.addEventListener( 'mousemove', function ( e ) {
			if ( ! box ) { measure(); }                 // measured lazily, once the 3D layout has settled
			if ( ! box || ! elRect ) { over = true; return; } // can't measure → fall back to the whole element
			var x = e.clientX - elRect.left, y = e.clientY - elRect.top;
			over = ( x >= box.l && x <= box.r && y >= box.t && y <= box.b );
		} );
		el.addEventListener( 'mouseleave', function () { over = false; } );
		// The box is element-relative, so scrolling only invalidates the element's own rect.
		window.addEventListener( 'scroll', function () { if ( box ) { elRect = el.getBoundingClientRect(); } }, { passive: true } );
		window.addEventListener( 'resize', function () { box = null; }, { passive: true } );

		return function () { return over ? ( mode === 'slow' ? 0.2 : 0 ) : 1; };
	}

	function initRing( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var ring  = el.querySelector( '.tdg__ring' );
		if ( ! stage || ! ring ) { return; }
		var cards = Array.prototype.slice.call( ring.querySelectorAll( '.tdg__card' ) );
		var n = cards.length;
		if ( ! n ) { return; }

		var drive    = el.getAttribute( 'data-tdg-drive' ) || 'auto';
		var speed    = num( el, 'data-tdg-speed', 16 );
		var dir      = num( el, 'data-tdg-dir', 1 );
		var momentum = num( el, 'data-tdg-momentum', 1 );
		var tilt     = num( el, 'data-tdg-tilt', -28 );
		var roll     = num( el, 'data-tdg-roll', 0 );
		var opening  = num( el, 'data-tdg-opening', 55 ); // how far you look INTO the ring (0 edge-on .. 100 open)
		var ringMul  = num( el, 'data-tdg-ring', 80 ) / 100;
		var spacing  = num( el, 'data-tdg-spacing', 100 ) / 100;
		var perspVal = num( el, 'data-tdg-persp', 40 );
		var backFade = clamp( num( el, 'data-tdg-backfade', 65 ) / 100, 0, 1 );
		// Card Size is % of the stage width, but scaled ×0.5 so the numbers line up with the animos
		// control (their 21% default = our card that fits the frame). Keeps the value portable.
		var cardPct  = num( el, 'data-tdg-card', 21 ) / 100 * 0.5;

		var step = 360 / n;
		var angle = 0, vel = 0, R = 0;

		function layout() {
			var W = stage.clientWidth || el.clientWidth || 1;
			var cardW = Math.max( 40, W * cardPct );
			// Radius that fits N cards around the ring. chordFit = the radius at which the card width
			// exactly equals the chord between neighbours (edge-to-edge). We scale it up (×1.35 bakes a
			// default gap; ring size + spacing tune it) and clamp so the flat cards can NEVER overlap.
			var chordFit = cardW / ( 2 * Math.sin( Math.PI / n ) );
			R = chordFit * 1.35 * ringMul * spacing;
			if ( R < chordFit * 1.03 ) { R = chordFit * 1.03; }
			// Perspective is WIDTH-RELATIVE so the depth reads the same at any stage size (a fixed px
			// value looked strong on a wide screen and flat on a narrow one). HIGHER value = STRONGER
			// perspective (closer camera, bigger px→smaller distance), matching the animos control.
			var pDist = W * Math.max( 0.35, Math.min( 1.25, 1.35 - perspVal * 0.013 ) );
			stage.style.perspective = pDist + 'px';
			// Eye sits below the ring centre so the near cards render lower with margin beneath them and
			// the whole loop lifts slightly above centre — the animos framing.
			stage.style.perspectiveOrigin = '50% 60%';
			cards.forEach( function ( c, i ) {
				c.__rot = i * step;
				c.style.width = cardW + 'px';
				c.style.marginLeft = ( -cardW / 2 ) + 'px';
				c.style.transform = 'rotateY(' + c.__rot + 'deg) translateZ(' + R + 'px)';
			} );
			// height comes from aspect-ratio; centre vertically once it's measured
			requestAnimationFrame( function () {
				cards.forEach( function ( c ) { c.style.marginTop = ( -( c.offsetHeight || cardW ) / 2 ) + 'px'; } );
			} );
		}

		// Ring Opening: a vertical squash of the whole projected ring. Low opening → the ring flattens
		// toward edge-on (the hole closes, cards compress); high opening → the loop opens up. Maps
		// 0..100 → scaleY 0.35..1.15 (55 ≈ 1.0, so the default is un-squashed).
		var openScale = clamp( 0.42 + opening * 0.0106, 0.3, 1.18 );

		function applyRing() {
			// scaleY (Ring Opening) is outermost, then rotateZ (Diagonal Tilt) rolls the oval diagonally,
			// rotateX tips it back, and rotateY spins the cards around the ring.
			ring.style.transform = 'scaleY(' + openScale + ') rotateZ(' + roll + 'deg) rotateX(' + tilt + 'deg) rotateY(' + angle + 'deg)';
			if ( backFade > 0 ) {
				for ( var i = 0; i < n; i++ ) {
					var facing = Math.cos( ( cards[ i ].__rot + angle ) * Math.PI / 180 ); // 1 front, -1 back
					cards[ i ].style.opacity = ( 1 - ( ( 1 - facing ) / 2 ) * backFade ).toFixed( 3 );
				}
			}
		}

		layout();
		applyRing();
		window.addEventListener( 'resize', function () { layout(); applyRing(); }, { passive: true } );

		// --- Motion: auto-rotate / scroll-scrub / static, with an independent "Drag to spin" layer ---
		var hoverF = hoverFactor( el );
		var allowDrag = num( el, 'data-tdg-allowdrag', 1 );
		var dragging = false, px = 0, scrollAngle = null;

		// Drag to spin layers over ANY base Motion: grabbing pauses the base drive, releasing flings (Drag
		// Momentum), then the base resumes. Kept even under reduced motion (user-initiated) and when static
		// (= pure drag). A Section background can't be grabbed (sc-bg-fill sets pointer-events:none).
		if ( allowDrag ) {
			el.style.cursor = 'grab';
			var flipDrag = 1;
			// Which half of the ring did you grab? A back card moves OPPOSITE a front card for the same spin,
			// so dragging one with the front's mapping feels reversed. Back cards are NOT hit-testable (the
			// stage plane sits over them), so the event target can't tell us — instead we find the card whose
			// on-screen centre is nearest the grab point (back cards are rendered, so their rects exist) and
			// use its facing (the same cos test the back-fade uses). Grab the back half → invert the delta for
			// the whole gesture so the grabbed card follows the cursor. Locked per gesture.
			var down = function ( x, y ) {
				dragging = true; px = x; vel = 0; el.style.cursor = 'grabbing'; flipDrag = 1;
				var best = null, bestD = Infinity;
				for ( var i = 0; i < n; i++ ) {
					var b = cards[ i ].getBoundingClientRect();
					var ddx = x - ( b.left + b.width / 2 ), ddy = y - ( b.top + b.height / 2 ), d = ddx * ddx + ddy * ddy;
					if ( d < bestD ) { bestD = d; best = cards[ i ]; }
				}
				if ( best && Math.cos( ( best.__rot + angle ) * Math.PI / 180 ) < 0 ) { flipDrag = -1; }
			};
			var move = function ( x ) { if ( ! dragging ) { return; } var dx = ( x - px ) * flipDrag; px = x; angle += dx * 0.3; vel = dx * 0.3; };
			var up = function () { dragging = false; el.style.cursor = 'grab'; };
			el.addEventListener( 'mousedown', function ( e ) { down( e.clientX, e.clientY ); e.preventDefault(); } );
			window.addEventListener( 'mousemove', function ( e ) { move( e.clientX ); } );
			window.addEventListener( 'mouseup', up );
			el.addEventListener( 'touchstart', function ( e ) { down( e.touches[ 0 ].clientX, e.touches[ 0 ].clientY ); }, { passive: true } );
			window.addEventListener( 'touchmove', function ( e ) { move( e.touches[ 0 ].clientX ); }, { passive: true } );
			window.addEventListener( 'touchend', up );
		}

		if ( drive === 'scroll' ) {
			var onScroll = function () {
				var r = el.getBoundingClientRect();
				var vh = window.innerHeight || 1;
				scrollAngle = dir * clamp( 1 - ( r.top + r.height / 2 ) / ( vh + r.height ), 0, 1 ) * 360;
			};
			window.addEventListener( 'scroll', onScroll, { passive: true } );
			onScroll();
		}

		var autoSpin = ( drive === 'auto' && ! reduce );
		// A render loop is only needed when something moves without a DOM event: auto spin, a momentum
		// fling, or scroll-scrub. Static with no drag is already rendered — nothing to loop.
		if ( ! ( autoSpin || allowDrag || drive === 'scroll' ) ) { return; }
		var last = 0;
		var loop = function ( t ) {
			if ( ! last ) { last = t; }
			var dt = ( t - last ) / 1000; last = t;
			if ( ! dragging ) {
				if ( autoSpin ) { angle += dir * ( 360 / speed ) * dt * hoverF(); }             // auto-rotate
				if ( momentum && Math.abs( vel ) > 0.02 ) { angle += vel; vel *= 0.95; }         // fling tail
				else if ( drive === 'scroll' && scrollAngle !== null ) { angle = scrollAngle; }  // scroll drives when idle
			}
			applyRing();
			requestAnimationFrame( loop );
		};
		requestAnimationFrame( loop );
	}

	/* ------------------------------------------------------------------ *
	 * Panorama Wall — a concave cylinder grid: the viewer sits at the centre of a cylinder whose inner
	 * wall is papered with a grid of cards (columns × rows). Each card is placed by rotateY(angle)
	 * translateZ(-R) so it faces the viewer; columns wrap around for seamless horizontal scroll and
	 * fade out at the rim (which also hides the recycle). Rows stack vertically to fill the frame.
	 * ------------------------------------------------------------------ */
	function initWall( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var wall  = el.querySelector( '.tdg__wall' );
		if ( ! stage || ! wall ) { return; }
		var rows = Array.prototype.slice.call( wall.querySelectorAll( '.tdg__row' ) );
		if ( ! rows.length ) { return; }

		var drive    = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed    = num( el, 'data-tdg-speed', 24 );
		var dir      = num( el, 'data-tdg-dir', 1 );
		var alt      = num( el, 'data-tdg-alt', 0 );
		// Curvature is SIGNED: negative = concave (wraps toward you), positive = convex (bulges away).
		// |curv| 0..150 = flat..tight.
		var curv     = num( el, 'data-tdg-curv', -100 );
		var tilt     = num( el, 'data-tdg-tilt', 0 );
		var gap      = num( el, 'data-tdg-gap', 5 ) / 100; // % of the card width
		var edge     = clamp( num( el, 'data-tdg-edge', 45 ) / 100, 0, 1 );
		var perspVal = num( el, 'data-tdg-persp', 55 );
		var cardPct  = num( el, 'data-tdg-card', 14 ) / 100;

		var rowData = rows.map( function ( rw ) { return { row: rw, cards: Array.prototype.slice.call( rw.querySelectorAll( '.tdg__card' ) ), y: 0 }; } );
		var C = rowData[ 0 ].cards.length; // columns per row
		if ( ! C ) { return; }
		// Concave pushes the cards AWAY (-Z, viewer inside the cylinder); convex pulls them toward the
		// viewer (+Z, the outside of a barrel bulging at you).
		var zSign = curv >= 0 ? 1 : -1;
		var R = 0, dTheta = 0, span = 0, base = 0, gapPx = 0;

		function layout() {
			var W = stage.clientWidth || el.clientWidth || 1;
			var cardW = Math.max( 40, W * cardPct );
			gapPx = cardW * gap;
			// Radius from |curvature| (inverse: tighter curve = smaller R). |curv| 150 → R ≈ cardW·2.9
			// (tight wrap); 100 → ≈4.3; near 0 → very large R = effectively flat.
			var amt = clamp( Math.abs( curv ) / 150, 0.08, 1 );
			R = cardW * 2.87 / amt;
			// Angular step so neighbouring columns sit edge-to-edge with the gap (chord = cardW + gap).
			dTheta = 2 * Math.asin( clamp( ( cardW + gapPx ) / ( 2 * R ), 0, 0.5 ) ) * 180 / Math.PI;
			span = C * dTheta;                               // full angular width the columns occupy
			stage.style.perspective = ( W * Math.max( 0.6, Math.min( 2.2, 2.4 - perspVal * 0.02 ) ) ) + 'px';
			rowData.forEach( function ( rd ) {
				rd.cards.forEach( function ( c, i ) { c.__col = i; c.style.width = cardW + 'px'; c.style.marginLeft = ( -cardW / 2 ) + 'px'; } );
			} );
			requestAnimationFrame( function () {
				var ch = ( rowData[ 0 ].cards[ 0 ] && rowData[ 0 ].cards[ 0 ].offsetHeight ) ? rowData[ 0 ].cards[ 0 ].offsetHeight : cardW * 0.5625;
				var rowH = ch + gapPx;
				var total = ( rowData.length - 1 ) * rowH;
				rowData.forEach( function ( rd, r ) {
					rd.y = r * rowH - total / 2;
					rd.cards.forEach( function ( c ) { c.style.marginTop = ( -ch / 2 ) + 'px'; } );
				} );
				applyWall();
			} );
		}

		function applyWall() {
			// Concave puts the viewer at the cylinder's centre (cards sit R away at -Z). Convex puts the
			// viewer OUTSIDE it, so push the whole wall back by 2R — the front cards then bulge toward
			// the viewer at the same working depth instead of flying into the camera.
			wall.style.transform = 'rotateX(' + tilt + 'deg)' + ( zSign > 0 ? ' translateZ(' + ( -2 * R ) + 'px)' : '' );
			var half = span / 2;
			rowData.forEach( function ( rd, r ) {
				var b = alt ? ( r % 2 ? -base : base ) : base;
				rd.row.style.transform = 'translateY(' + rd.y + 'px)';
				for ( var i = 0; i < rd.cards.length; i++ ) {
					var c = rd.cards[ i ];
					var raw = ( c.__col - ( C - 1 ) / 2 ) * dTheta + b;
					var ang = ( ( raw + half ) % span + span ) % span - half; // wrap into [-half, half)
					var af  = half > 0 ? Math.abs( ang ) / half : 0;          // 0 centre .. 1 rim
					c.style.transform = 'rotateY(' + ang + 'deg) translateZ(' + ( zSign * R ) + 'px)';
					// fade the outer 14% to 0 (hides the wrap pop), plus Edge Fade dims across the span
					var vis = af > 0.86 ? Math.max( 0, ( 1 - af ) / 0.14 ) : 1;
					c.style.opacity = ( vis * ( 1 - af * af * edge ) ).toFixed( 3 );
					c.style.zIndex = String( 1000 - Math.round( af * 1000 ) );
				}
			} );
		}

		layout();
		applyWall();
		window.addEventListener( 'resize', function () { layout(); applyWall(); }, { passive: true } );
		var hoverF = hoverFactor( el );
		var allowDrag = num( el, 'data-tdg-allowdrag', 1 );
		var momentum = num( el, 'data-tdg-momentum', 1 );
		var dragging = false, px = 0, vel = 0, scrollBase = null;
		// One loop = scroll by the full span (one recycle); Loop Duration is the seconds for that.
		var advance = function ( dt ) { base += dir * ( span / Math.max( 1, speed ) ) * dt * hoverF(); };

		// Drag to spin — layers over the base Motion (grabbing pauses it, releasing flings, then it resumes).
		if ( allowDrag ) {
			el.style.cursor = 'grab';
			var down = function ( x ) { dragging = true; px = x; vel = 0; el.style.cursor = 'grabbing'; };
			var move = function ( x ) { if ( ! dragging ) { return; } var dx = x - px; px = x; base -= dx * 0.12; vel = -dx * 0.12; };
			var up = function () { dragging = false; el.style.cursor = 'grab'; };
			el.addEventListener( 'mousedown', function ( e ) { down( e.clientX ); e.preventDefault(); } );
			window.addEventListener( 'mousemove', function ( e ) { move( e.clientX ); } );
			window.addEventListener( 'mouseup', up );
			el.addEventListener( 'touchstart', function ( e ) { down( e.touches[ 0 ].clientX ); }, { passive: true } );
			window.addEventListener( 'touchmove', function ( e ) { move( e.touches[ 0 ].clientX ); }, { passive: true } );
			window.addEventListener( 'touchend', up );
		}

		if ( drive === 'scroll' ) {
			var onScroll = function () {
				var r = el.getBoundingClientRect();
				var vh = window.innerHeight || 1;
				var prog = clamp( 1 - ( r.top + r.height / 2 ) / ( vh + r.height ), 0, 1 );
				scrollBase = dir * prog * span * 2;
			};
			window.addEventListener( 'scroll', onScroll, { passive: true } );
			onScroll();
		}

		var autoRun = ( drive === 'continuous' && ! reduce );
		if ( ! ( autoRun || allowDrag || drive === 'scroll' ) ) { return; }
		var last = 0;
		var loop = function ( t ) {
			if ( ! last ) { last = t; }
			var dt = ( t - last ) / 1000; last = t;
			if ( ! dragging ) {
				if ( autoRun ) { advance( dt ); }
				if ( momentum && Math.abs( vel ) > 0.02 ) { base += vel; vel *= 0.95; }
				else if ( drive === 'scroll' && scrollBase !== null ) { base = scrollBase; }
			}
			applyWall();
			requestAnimationFrame( loop );
		};
		requestAnimationFrame( loop );
	}

	/* ------------------------------------------------------------------ *
	 * Card Sphere — cards wrapped on a spinning sphere (disco ball). Each band is a horizontal ring at
	 * its latitude (radius = R·cosφ, height = R·sinφ); the card count per band is thinned toward the
	 * poles. Back hemisphere hidden; Back Fade dims cards toward the rim.
	 * ------------------------------------------------------------------ */
	function initGlobe( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var globe = el.querySelector( '.tdg__globe' );
		if ( ! stage || ! globe ) { return; }
		var bands = Array.prototype.slice.call( globe.querySelectorAll( '.tdg__band' ) );
		if ( ! bands.length ) { return; }

		var drive    = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed    = num( el, 'data-tdg-speed', 20 );
		var dir      = num( el, 'data-tdg-dir', 1 );
		var momentum = num( el, 'data-tdg-momentum', 1 );
		// Globe Size sets the sphere directly (diameter as % of the stage width); the band/card counts
		// are already derived in PHP from the same ratios, so here we only convert to pixels.
		var globePct = num( el, 'data-tdg-globe', 70 ) / 100;
		var maxLat   = num( el, 'data-tdg-maxlat', 80 );  // top/bottom band latitude (off the poles)
		var backFade = clamp( num( el, 'data-tdg-backfade', 55 ) / 100, 0, 1 );
		var tilt     = num( el, 'data-tdg-tilt', 0 );
		var perspVal = num( el, 'data-tdg-persp', 55 );
		var cardPct  = num( el, 'data-tdg-card', 20 ) / 100;

		var nRows = bands.length;
		var bandData = bands.map( function ( bw, r ) {
			// Latitude of each band, evenly spaced from -maxLat (bottom) to +maxLat (top). PHP emits the
			// matching per-band card count (fewer toward the poles), so we just lay out what's there.
			var lat = ( ( r + 0.5 ) / nRows * 2 - 1 ) * maxLat;
			return { band: bw, cards: Array.prototype.slice.call( bw.querySelectorAll( '.tdg__card' ) ), lat: lat, angle: 0 };
		} );
		if ( ! bandData[ 0 ].cards.length ) { return; }
		var R = 0;

		function layout() {
			var W = stage.clientWidth || el.clientWidth || 1;
			var H = stage.clientHeight || el.clientHeight || W;
			// A sphere is bounded by the stage's SHORTER side, so Globe Size and Card Size are both % of
			// that: it fits at any stage aspect, and sharing one base keeps R/cardW a pure ratio — which
			// is what makes the band/card counts PHP derived from the same ratio come out right.
			var base = Math.min( W, H );
			R = base * globePct / 2; // straight from Globe Size — nothing else resizes the globe
			// Card Size is a % of the GLOBE, so Globe Size is a pure zoom and Card Size sets the tiling
			// density (matching the counts PHP derived from R/cardW = 1/(2·card%)).
			var cardW = Math.max( 8, base * globePct * cardPct );
			stage.style.perspective = ( W * Math.max( 0.6, Math.min( 2.4, 2.6 - perspVal * 0.02 ) ) ) + 'px';
			bandData.forEach( function ( bd ) {
				var vis = bd.cards.length;
				bd.step = 360 / Math.max( 1, vis );
				bd.cards.forEach( function ( c, i ) {
					c.__rot = i * bd.step;
					c.style.width = cardW + 'px';
					c.style.marginLeft = ( -cardW / 2 ) + 'px';
					// Place on the sphere surface at (longitude __rot, latitude lat), tangent + facing out.
					c.style.transform = 'rotateY(' + c.__rot + 'deg) rotateX(' + bd.lat + 'deg) translateZ(' + R + 'px)';
				} );
			} );
			requestAnimationFrame( function () {
				bandData.forEach( function ( bd ) {
					bd.cards.forEach( function ( c ) { c.style.marginTop = ( -( c.offsetHeight || cardW ) / 2 ) + 'px'; } );
				} );
			} );
		}

		function applyGlobe() {
			var spin = bandData[ 0 ].angle;
			globe.style.transform = 'rotateX(' + tilt + 'deg) rotateY(' + spin + 'deg)';
			var spinR = spin * Math.PI / 180;
			// Back-hemisphere cards are auto-hidden by backface-visibility (CSS); here we only dim cards
			// toward the rim by their outward normal's z-component (frontZ = cosLat·cos(worldLon)).
			bandData.forEach( function ( bd ) {
				var cl = Math.cos( bd.lat * Math.PI / 180 );
				for ( var i = 0; i < bd.cards.length; i++ ) {
					var c = bd.cards[ i ];
					var frontZ = cl * Math.cos( c.__rot * Math.PI / 180 + spinR );
					c.style.opacity = backFade > 0 ? ( 1 - clamp( 1 - frontZ, 0, 1 ) * backFade ).toFixed( 3 ) : '1';
				}
			} );
		}

		layout();
		applyGlobe();
		window.addEventListener( 'resize', function () { layout(); applyGlobe(); }, { passive: true } );
		var hoverF = hoverFactor( el );
		var allowDrag = num( el, 'data-tdg-allowdrag', 1 );
		var dragging = false, px = 0, vel = 0, scrollAngle = null;
		var advance = function ( dt ) { var d = dir * ( 360 / speed ) * dt * hoverF(); bandData.forEach( function ( bd ) { bd.angle += d; } ); };

		// Drag to spin — layers over the base Motion (grabbing pauses it, releasing flings, then it resumes).
		if ( allowDrag ) {
			el.style.cursor = 'grab';
			var down = function ( x ) { dragging = true; px = x; vel = 0; el.style.cursor = 'grabbing'; };
			var move = function ( x ) { if ( ! dragging ) { return; } var dx = x - px; px = x; bandData.forEach( function ( bd ) { bd.angle += dx * 0.25; } ); vel = dx * 0.25; };
			var up = function () { dragging = false; el.style.cursor = 'grab'; };
			el.addEventListener( 'mousedown', function ( e ) { down( e.clientX ); e.preventDefault(); } );
			window.addEventListener( 'mousemove', function ( e ) { move( e.clientX ); } );
			window.addEventListener( 'mouseup', up );
			el.addEventListener( 'touchstart', function ( e ) { down( e.touches[ 0 ].clientX ); }, { passive: true } );
			window.addEventListener( 'touchmove', function ( e ) { move( e.touches[ 0 ].clientX ); }, { passive: true } );
			window.addEventListener( 'touchend', up );
		}

		if ( drive === 'scroll' ) {
			var onScroll = function () { var r = el.getBoundingClientRect(); var vh = window.innerHeight || 1; scrollAngle = dir * clamp( 1 - ( r.top + r.height / 2 ) / ( vh + r.height ), 0, 1 ) * 360; };
			window.addEventListener( 'scroll', onScroll, { passive: true } );
			onScroll();
		}

		var autoRun = ( drive === 'continuous' && ! reduce );
		if ( ! ( autoRun || allowDrag || drive === 'scroll' ) ) { return; }
		var last = 0;
		var loop = function ( t ) {
			if ( ! last ) { last = t; }
			var dt = ( t - last ) / 1000; last = t;
			if ( ! dragging ) {
				if ( autoRun ) { advance( dt ); }
				if ( momentum && Math.abs( vel ) > 0.02 ) { bandData.forEach( function ( bd ) { bd.angle += vel; } ); vel *= 0.95; }
				else if ( drive === 'scroll' && scrollAngle !== null ) { bandData.forEach( function ( bd ) { bd.angle = scrollAngle; } ); }
			}
			applyGlobe();
			requestAnimationFrame( loop );
		};
		requestAnimationFrame( loop );
	}

	function scan() {
		var i, els;
		els = document.querySelectorAll( '.tdg--carousel-ring' );
		for ( i = 0; i < els.length; i++ ) { initRing( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--panorama-wall' );
		for ( i = 0; i < els.length; i++ ) { initWall( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--card-sphere' );
		for ( i = 0; i < els.length; i++ ) { initGlobe( els[ i ] ); }
	}
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', scan ); } else { scan(); }
	window.upwGallery3dRescan = scan;

	// Init ONE element by its design class (used by the admin preview, which builds hosts on demand
	// rather than having them present at page load).
	window.upwGallery3dInitEl = function ( el ) {
		if ( ! el ) { return; }
		if ( el.classList.contains( 'tdg--carousel-ring' ) ) { initRing( el ); }
		else if ( el.classList.contains( 'tdg--panorama-wall' ) ) { initWall( el ); }
		else if ( el.classList.contains( 'tdg--card-sphere' ) ) { initGlobe( el ); }
	};
})();
