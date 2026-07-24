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

	// Scroll-scrub progress 0..1, shared by every design. Pass-through mode: the element's travel
	// through the viewport. Pinned mode (.tdg--pinned, "Pin while scrubbing"): progress through the
	// pinned stretch — the stage sticks while the stretched wrapper passes, so the span is
	// wrapperH − stageH and the stick offset mirrors the CSS `top:` rule (viewport-centred stage).
	function scrollProgress( el, stage ) {
		var r = el.getBoundingClientRect();
		var vh = window.innerHeight || 1;
		if ( el.classList.contains( 'tdg--pinned' ) ) {
			var sh = ( stage && stage.offsetHeight ) || 1;
			var stick = Math.max( 0, ( vh - sh ) / 2 );
			return clamp( ( stick - r.top ) / Math.max( 1, r.height - sh ), 0, 1 );
		}
		return clamp( 1 - ( r.top + r.height / 2 ) / ( vh + r.height ), 0, 1 );
	}

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
			var onScroll = function () { scrollAngle = dir * scrollProgress( el, stage ) * 360; };
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
			var onScroll = function () { scrollBase = dir * scrollProgress( el, stage ) * span * 2; };
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
			var onScroll = function () { scrollAngle = dir * scrollProgress( el, stage ) * 360; };
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

	/* ------------------------------------------------------------------ *
	 * Orbit Globe — cards distributed through a sphere VOLUME (Fibonacci points), each BILLBOARDED
	 * (always facing the camera) rather than tangent to the surface like Card Sphere. The whole cloud
	 * spins; the stage perspective makes near cards big + far cards small, and Back Fade dims the far
	 * side — a depth-of-field orbit. Each card is positioned every frame from its rotated unit vector.
	 * ------------------------------------------------------------------ */
	function initOrbit( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var orbit = el.querySelector( '.tdg__orbit' );
		if ( ! stage || ! orbit ) { return; }
		var cards = Array.prototype.slice.call( orbit.querySelectorAll( '.tdg__card' ) );
		if ( ! cards.length ) { return; }

		var drive    = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed    = num( el, 'data-tdg-speed', 20 );
		var dir      = num( el, 'data-tdg-dir', 1 );
		var momentum = num( el, 'data-tdg-momentum', 1 );
		var globePct = num( el, 'data-tdg-globe', 50 ) / 100;
		var backFade = clamp( num( el, 'data-tdg-backfade', 55 ) / 100, 0, 1 );
		var tilt     = num( el, 'data-tdg-tilt', 27 );
		var cardPct  = num( el, 'data-tdg-card', 28 ) / 100;

		var n = cards.length;
		// Fibonacci sphere: an even spread of unit directions, one per card.
		var GA = Math.PI * ( 3 - Math.sqrt( 5 ) ); // golden angle
		cards.forEach( function ( c, i ) {
			var uy = 1 - ( i / Math.max( 1, n - 1 ) ) * 2;      // 1 .. -1
			var rr = Math.sqrt( Math.max( 0, 1 - uy * uy ) );
			var th = i * GA;
			c.__u = [ Math.cos( th ) * rr, uy, Math.sin( th ) * rr ];
		} );
		var R = 0, tiltR = tilt * Math.PI / 180;
		var angle = 0, vel = 0;

		function layout() {
			var W = stage.clientWidth || el.clientWidth || 1;
			var H = stage.clientHeight || el.clientHeight || W;
			var base = Math.min( W, H );
			R = base * globePct / 2;
			var cardW = Math.max( 8, base * globePct * cardPct );
			// Perspective IS the effect here (near big / far small); orbit has no perspective control, so
			// it is fixed + width-relative to read the same at any stage size.
			stage.style.perspective = ( W * 0.9 ) + 'px';
			cards.forEach( function ( c ) {
				c.style.width = cardW + 'px';
				c.style.marginLeft = ( -cardW / 2 ) + 'px';
			} );
			requestAnimationFrame( function () {
				cards.forEach( function ( c ) { c.style.marginTop = ( -( c.offsetHeight || cardW ) / 2 ) + 'px'; } );
			} );
		}

		function applyOrbit() {
			var ar = angle * Math.PI / 180, sa = Math.sin( ar ), ca = Math.cos( ar );
			var st = Math.sin( tiltR ), ct = Math.cos( tiltR );
			for ( var i = 0; i < n; i++ ) {
				var u = cards[ i ].__u;
				var x = u[ 0 ] * ca + u[ 2 ] * sa;      // spin around Y
				var z = -u[ 0 ] * sa + u[ 2 ] * ca;
				var y2 = u[ 1 ] * ct - z * st;          // then tilt around X
				var z2 = u[ 1 ] * st + z * ct;
				var c = cards[ i ];
				c.style.transform = 'translate3d(' + ( x * R ).toFixed( 1 ) + 'px,' + ( y2 * R ).toFixed( 1 ) + 'px,' + ( z2 * R ).toFixed( 1 ) + 'px)';
				// far side (z2 < 0) fades toward the back
				c.style.opacity = backFade > 0 ? ( 1 - clamp( ( 1 - z2 ) / 2, 0, 1 ) * backFade ).toFixed( 3 ) : '1';
			}
		}

		layout();
		applyOrbit();
		window.addEventListener( 'resize', function () { layout(); applyOrbit(); }, { passive: true } );
		var hoverF = hoverFactor( el );
		var allowDrag = num( el, 'data-tdg-allowdrag', 1 );
		var dragging = false, px = 0, scrollAngle = null;
		var advance = function ( dt ) { angle += dir * ( 360 / speed ) * dt * hoverF(); };

		// Drag to spin — layers over the base Motion (grabbing pauses it, releasing flings, then resumes).
		if ( allowDrag ) {
			el.style.cursor = 'grab';
			var down = function ( x ) { dragging = true; px = x; vel = 0; el.style.cursor = 'grabbing'; };
			var move = function ( x ) { if ( ! dragging ) { return; } var dx = x - px; px = x; angle += dx * 0.25; vel = dx * 0.25; };
			var up = function () { dragging = false; el.style.cursor = 'grab'; };
			el.addEventListener( 'mousedown', function ( e ) { down( e.clientX ); e.preventDefault(); } );
			window.addEventListener( 'mousemove', function ( e ) { move( e.clientX ); } );
			window.addEventListener( 'mouseup', up );
			el.addEventListener( 'touchstart', function ( e ) { down( e.touches[ 0 ].clientX ); }, { passive: true } );
			window.addEventListener( 'touchmove', function ( e ) { move( e.touches[ 0 ].clientX ); }, { passive: true } );
			window.addEventListener( 'touchend', up );
		}

		if ( drive === 'scroll' ) {
			var onScroll = function () { scrollAngle = dir * scrollProgress( el, stage ) * 360; };
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
				if ( momentum && Math.abs( vel ) > 0.02 ) { angle += vel; vel *= 0.95; }
				else if ( drive === 'scroll' && scrollAngle !== null ) { angle = scrollAngle; }
			}
			applyOrbit();
			requestAnimationFrame( loop );
		};
		requestAnimationFrame( loop );
	}

	/**
	 * Photo Scatter — photos scattered flat on a tabletop. The pool is server-rendered; the driver
	 * picks which cards are "on the table", places them on a jittered grid (desk-like, no heavy
	 * collision maths), glides them in from the chosen edge with a stagger, dwells, sweeps them out
	 * and slides the next set in. Auto / click / off cycling; hover-pause; reduced-motion = static.
	 */
	function initScatter( el ) {
		if ( el.__tdgInit ) { return; } el.__tdgInit = true;
		var cards   = el.querySelectorAll( '.tdg__plane > .tdg__card' );
		var pool    = cards.length;
		if ( ! pool ) { return; }
		var visible = clamp( num( el, 'data-tdg-visible', 9 ), 3, 16 );
		var N       = Math.min( visible, pool );
		var sets    = Math.max( 1, Math.ceil( pool / N ) );
		var cycleMd = el.getAttribute( 'data-tdg-cycle' ) || 'auto';
		var dwell   = clamp( num( el, 'data-tdg-dwell', 6 ), 2, 20 ) * 1000;
		var hpause  = !! num( el, 'data-tdg-hpause', 1 );
		var from    = el.getAttribute( 'data-tdg-from' ) || 'edges';
		var rotMax  = clamp( num( el, 'data-tdg-rot', 12 ), 0, 35 );
		var sizeVar = clamp( num( el, 'data-tdg-sizevar', 30 ), 0, 60 ) / 100;
		var spread  = clamp( num( el, 'data-tdg-spread', 90 ), 50, 100 ) / 100;
		var cardPct = clamp( num( el, 'data-tdg-card', 18 ), 8, 40 );
		var exitStyle = el.getAttribute( 'data-tdg-exit' ) || 'sweep'; // sweep | gather | fade
		if ( pool <= N ) { cycleMd = 'off'; } // one set — nothing to shuffle to

		var setIdx = 0, hovering = false, timer = 0, inView = true, entered = false;

		// One scattered placement for the N slots: a jittered ceil(√N) grid inside the spread box,
		// shuffled so neighbours don't correlate — reads as a natural pile without collision maths.
		function placements() {
			var g = Math.ceil( Math.sqrt( N ) ), cells = [], i;
			for ( i = 0; i < g * g; i++ ) { cells.push( i ); }
			for ( i = cells.length - 1; i > 0; i-- ) { var j = ( Math.random() * ( i + 1 ) ) | 0, t = cells[ i ]; cells[ i ] = cells[ j ]; cells[ j ] = t; }
			var out = [], pad = ( 1 - spread ) / 2;
			for ( i = 0; i < N; i++ ) {
				var c  = cells[ i ], cx = ( c % g + 0.5 ) / g, cy = ( ( c / g | 0 ) + 0.5 ) / g;
				var jx = ( Math.random() - 0.5 ) * ( 0.55 / g ), jy = ( Math.random() - 0.5 ) * ( 0.55 / g );
				out.push( {
					x: ( pad + ( cx + jx ) * spread ) * 100,
					y: ( pad + ( cy + jy ) * spread ) * 100,
					r: ( Math.random() * 2 - 1 ) * rotMax,
					s: 1 + ( Math.random() * 2 - 1 ) * sizeVar / 2,
					z: 1 + ( ( Math.random() * N ) | 0 ),
				} );
			}
			return out;
		}

		// Off-stage start/exit transform for a slot, per the "Glide in from" choice.
		function offstage( p ) {
			var side;
			if ( from === 'top' ) { side = 'top'; }
			else if ( from === 'sides' ) { side = ( p.x < 50 ) ? 'left' : 'right'; }
			else if ( from === 'random' ) { side = [ 'top', 'right', 'bottom', 'left' ][ ( Math.random() * 4 ) | 0 ]; }
			else { // edges — leave via the nearest edge
				var dl = p.x, dr = 100 - p.x, dt = p.y, db = 100 - p.y;
				var m = Math.min( dl, dr, dt, db );
				side = ( m === dt ) ? 'top' : ( m === db ) ? 'bottom' : ( m === dl ) ? 'left' : 'right';
			}
			var dx = 0, dy = 0;
			if ( side === 'top' ) { dy = -130; } else if ( side === 'bottom' ) { dy = 130; }
			else if ( side === 'left' ) { dx = -130; } else { dx = 130; }
			return { dx: dx, dy: dy };
		}

		function applySet( idx, animateIn ) {
			var start = idx * N, i, k = 0;
			for ( i = 0; i < pool; i++ ) {
				var card = cards[ i ];
				var active = ( i >= start && i < start + N ) || ( start + N > pool && i < ( start + N ) % pool );
				if ( ! active ) { card.classList.remove( 'is-set' ); card.style.transitionDelay = '0ms'; card.style.opacity = ''; continue; }
				var p = pts[ k ], off = offstage( p );
				card.classList.add( 'is-set' );
				card.style.width  = ( cardPct * p.s ) + '%';
				card.style.zIndex = p.z;
				if ( animateIn ) {
					card.style.transitionDuration = '0ms';
					card.style.transform = 'translate(-50%,-50%) translate(' + off.dx + '%,' + off.dy + '%) rotate(' + ( p.r * 2 ) + 'deg)';
					card.style.left = p.x + '%'; card.style.top = p.y + '%';
					card.style.opacity = '0';
					void card.offsetWidth; // reflow → transition from the off-stage pose
					card.style.transitionDuration = '';
					card.style.transitionDelay = ( k * 90 ) + 'ms';
				} else {
					card.style.left = p.x + '%'; card.style.top = p.y + '%';
				}
				card.style.transform = 'translate(-50%,-50%) rotate(' + p.r + 'deg)';
				card.style.opacity = '1';
				k++;
			}
		}

		function sweepOut( done ) {
			var start = setIdx * N, i, k = 0;
			for ( i = 0; i < pool; i++ ) {
				var card = cards[ i ];
				if ( ! card.classList.contains( 'is-set' ) ) { continue; }
				var p = pts[ k ] || pts[ 0 ];
				if ( exitStyle === 'gather' ) {
					// Converge to a loose stack at the centre (the poly "collect into a pile" moment):
					// each card slides to ~centre with a small residual offset + a lean, then fades.
					var jx = ( Math.random() * 2 - 1 ) * 6, jy = ( Math.random() * 2 - 1 ) * 6;
					card.style.transitionDelay = ( k * 45 ) + 'ms';
					card.style.left = ( 50 + jx ) + '%'; card.style.top = ( 50 + jy ) + '%';
					card.style.transform = 'translate(-50%,-50%) rotate(' + ( ( Math.random() * 2 - 1 ) * 10 ) + 'deg) scale(0.92)';
					card.style.opacity = '0';
				} else if ( exitStyle === 'fade' ) {
					card.style.transitionDelay = ( k * 40 ) + 'ms';
					card.style.opacity = '0';
				} else { // sweep — fly out the nearest edge (default)
					var off = offstage( p );
					card.style.transitionDelay = ( k * 60 ) + 'ms';
					card.style.transform = 'translate(-50%,-50%) translate(' + off.dx + '%,' + off.dy + '%) rotate(' + ( p.r * 1.6 ) + 'deg)';
					card.style.opacity = '0';
				}
				k++;
			}
			setTimeout( done, 700 + N * 60 );
		}

		function shuffle() {
			if ( sets < 2 ) { return; }
			sweepOut( function () {
				setIdx = ( setIdx + 1 ) % sets;
				pts = placements();
				applySet( setIdx, true );
				schedule();
			} );
		}

		function schedule() {
			if ( cycleMd !== 'auto' ) { return; }
			clearTimeout( timer );
			timer = setTimeout( function tick() {
				if ( ! inView || ( hpause && hovering ) ) { timer = setTimeout( tick, 600 ); return; }
				shuffle();
			}, dwell );
		}

		var pts = placements();
		if ( reduce ) { applySet( 0, false ); return; } // reduced motion: static scatter, no cycling

		// Enter once the scatter is actually in view (and pause auto-cycling off-screen).
		if ( 'IntersectionObserver' in window ) {
			var io = new IntersectionObserver( function ( es ) {
				inView = es[ 0 ].isIntersecting;
				if ( inView && ! entered ) { entered = true; applySet( 0, true ); schedule(); }
			}, { threshold: 0.15 } );
			io.observe( el );
		} else { entered = true; applySet( 0, true ); schedule(); }

		el.addEventListener( 'mouseenter', function () { hovering = true; } );
		el.addEventListener( 'mouseleave', function () { hovering = false; } );
		if ( cycleMd === 'click' ) {
			el.addEventListener( 'click', function ( e ) {
				if ( e.target && e.target.closest && e.target.closest( 'a' ) ) { return; } // links keep their job
				shuffle();
			} );
		}
	}

	function scan() {
		var i, els;
		els = document.querySelectorAll( '.tdg--carousel-ring' );
		for ( i = 0; i < els.length; i++ ) { initRing( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--panorama-wall' );
		for ( i = 0; i < els.length; i++ ) { initWall( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--card-sphere' );
		for ( i = 0; i < els.length; i++ ) { initGlobe( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--orbit-globe' );
		for ( i = 0; i < els.length; i++ ) { initOrbit( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--photo-scatter' );
		for ( i = 0; i < els.length; i++ ) { initScatter( els[ i ] ); }
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
		else if ( el.classList.contains( 'tdg--orbit-globe' ) ) { initOrbit( el ); }
		else if ( el.classList.contains( 'tdg--photo-scatter' ) ) { initScatter( el ); }
	};
})();
