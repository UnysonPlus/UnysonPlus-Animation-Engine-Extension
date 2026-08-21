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

	// Shared drag wiring (mouse + touch) — extracted from the 5 designs that each repeated it verbatim.
	// Passes BOTH (clientX, clientY) to the design's down/move callbacks; each uses the coordinate it
	// needs (X for the horizontal designs, Y for the vertical cascade, both for the ring's hit-test).
	function attachDrag( el, down, move, up ) {
		el.addEventListener( 'mousedown', function ( e ) { down( e.clientX, e.clientY ); e.preventDefault(); } );
		window.addEventListener( 'mousemove', function ( e ) { move( e.clientX, e.clientY ); } );
		window.addEventListener( 'mouseup', up );
		el.addEventListener( 'touchstart', function ( e ) { down( e.touches[ 0 ].clientX, e.touches[ 0 ].clientY ); }, { passive: true } );
		window.addEventListener( 'touchmove', function ( e ) { move( e.touches[ 0 ].clientX, e.touches[ 0 ].clientY ); }, { passive: true } );
		window.addEventListener( 'touchend', up );
	}

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
			attachDrag( el, down, move, up );
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
			attachDrag( el, down, move, up );
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
			attachDrag( el, down, move, up );
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
			attachDrag( el, down, move, up );
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
		var visible = clamp( num( el, 'data-tdg-visible', 9 ), 3, 50 );
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
		var centerClear = !! num( el, 'data-tdg-center-clear', 0 ); // ring the cards around a centered heading
		var stage   = el.querySelector( '.tdg__stage' );
		// One-set galleries still animate in the scroll modes (a single set scatters in→out, or the
		// scroll_cycle final grid resolves); only the timed/click cyclers have nothing to shuffle to.
		if ( pool <= N && cycleMd !== 'scroll' && cycleMd !== 'scroll_cycle' ) { cycleMd = 'off'; }

		var setIdx = 0, hovering = false, timer = 0, inView = true, entered = false;

		// One scattered placement for the N slots: a jittered ceil(√N) grid, shuffled so neighbours
		// don't correlate — a natural pile without collision maths. The grid is mapped EDGE-TO-EDGE
		// (the outer rows/cols sit at the frame edges) and `spread` expands the field around centre —
		// ≥ ~95 lets edge cards BLEED off-frame (clipped by the stage's overflow:hidden) for a true
		// edge-to-edge fill instead of an inset island in the middle.
		function placements() {
			var g = Math.ceil( Math.sqrt( N ) ), cells = [], i;
			for ( i = 0; i < g * g; i++ ) { cells.push( i ); }
			for ( i = cells.length - 1; i > 0; i-- ) { var j = ( Math.random() * ( i + 1 ) ) | 0, t = cells[ i ]; cells[ i ] = cells[ j ]; cells[ j ] = t; }
			var out = [], denom = ( g > 1 ) ? ( g - 1 ) : 1, reach = spread * 106;   // spread is already 0–1
			for ( i = 0; i < N; i++ ) {
				var c  = cells[ i ], nx = ( c % g ) / denom, ny = ( ( c / g ) | 0 ) / denom;   // 0..1 edge-to-edge
				var jx = ( Math.random() - 0.5 ) * ( 1 / g ), jy = ( Math.random() - 0.5 ) * ( 1 / g );
				var px = 50 + ( ( nx + jx ) - 0.5 ) * reach, py = 50 + ( ( ny + jy ) - 0.5 ) * reach;
				if ( centerClear ) {
					// Push any card that lands in a central ellipse outward so a centered heading stays
					// readable — the cards "ring" the text. The zone is wider than tall (headings are wide).
					var nx = ( px - 50 ) / 30, ny = ( py - 50 ) / 22;
					if ( nx * nx + ny * ny < 1 ) {
						var ang = Math.atan2( py - 50, px - 50 ); if ( isNaN( ang ) ) { ang = Math.random() * Math.PI * 2; }
						px = 50 + Math.cos( ang ) * ( 30 + Math.random() * 16 );
						py = 50 + Math.sin( ang ) * ( 24 + Math.random() * 14 );
					}
				}
				out.push( {
					x: px, y: py,
					r: ( Math.random() * 2 - 1 ) * rotMax,
					s: 1 + ( Math.random() * 2 - 1 ) * sizeVar / 2,
					z: 1 + ( ( Math.random() * N ) | 0 ),
				} );
			}
			return out;
		}

		// The TIDY target for the scroll-driven "scatter -> organize" morph: N cards in an even,
		// upright grid (no rotation, uniform size), rows centred. Cards lerp from the scattered
		// placement to this as the visitor scrolls through the scene.
		function organized() {
			var cols = Math.max( 2, Math.min( N, Math.round( Math.sqrt( N ) * 1.4 ) ) );
			var rows = Math.ceil( N / cols );
			var out = [], padx = 0.06, pady = 0.10, i;
			for ( i = 0; i < N; i++ ) {
				var col = i % cols, row = ( i / cols ) | 0;
				var inRow = Math.min( cols, N - row * cols );   // centre a short final row
				var rowPad = ( cols - inRow ) / 2;
				out.push( {
					x: ( padx + ( ( col + rowPad + 0.5 ) / cols ) * ( 1 - padx * 2 ) ) * 100,
					y: ( pady + ( ( row + 0.5 ) / rows ) * ( 1 - pady * 2 ) ) * 100,
					r: 0, s: 1, z: i + 1,
				} );
			}
			return out;
		}

		function applySet( idx, animateIn ) {
			var start = idx * N, i, k = 0;
			for ( i = 0; i < pool; i++ ) {
				var card = cards[ i ];
				var active = ( i >= start && i < start + N ) || ( start + N > pool && i < ( start + N ) % pool );
				if ( ! active ) { card.classList.remove( 'is-set' ); card.style.transitionDelay = '0ms'; card.style.opacity = ''; continue; }
				var p = pts[ k ], off = offDir( p, from );
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
				} else { // directional sweep — fly out per "Photos exit toward" (nearest edge by default)
					var off = offDir( p, exitStyle );
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

		// Shared progress reader for the scroll-driven modes. Resolves the parent scrollytelling
		// Stage LAZILY — the module adds `.upw-story--stage` AFTER this gallery inits, so caching it
		// once would miss it and fall back to the gallery's own progress (frozen while the Stage is
		// pinned → no motion). A RANGED persist layer remaps the story's 0→1 to its own beat slice so
		// the effect completes within its beats. Re-checks each frame until found, then caches.
		var storyEl = null, scene = null;
		function progOf() {
			if ( ! storyEl && el.closest ) { storyEl = el.closest( '.upw-story--stage' ); }
			if ( storyEl && typeof storyEl.__storyProgress === 'number' ) {
				var pr = storyEl.__storyProgress;
				if ( scene === null ) { scene = ( el.closest && el.closest( '.upw-story-scene' ) ) || false; }
				if ( scene && storyEl.__storyBeats ) {
					// Remap the story's 0→1 to THIS scene's beat slice so the effect completes within its
					// own scroll span: a ranged persist layer uses [__pFrom, __pTo]; a normal beat scene
					// (a gallery placed inside one) uses its single [__beatIndex, __beatIndex].
					var from, to;
					if ( typeof scene.__pFrom === 'number' ) { from = scene.__pFrom; to = scene.__pTo; }
					else if ( typeof scene.__beatIndex === 'number' ) { from = scene.__beatIndex; to = scene.__beatIndex; }
					if ( from !== undefined ) {
						var lo = from / storyEl.__storyBeats, hi = ( to + 1 ) / storyEl.__storyBeats;
						if ( hi > lo ) { pr = Math.max( 0, Math.min( 1, ( pr - lo ) / ( hi - lo ) ) ); }
					}
				}
				return pr;
			}
			return scrollProgress( el, stage );
		}
		function clamp01( t ) { return t < 0 ? 0 : t > 1 ? 1 : t; }
		function ease( t ) { t = clamp01( t ); return t * t * ( 3 - 2 * t ); }
		function lerp( a, b, t ) { return a + ( b - a ) * t; }

		// Off-stage displacement (dx,dy in %) for a resting position, per an enter/exit direction.
		// 'random' is resolved ONCE at setup (stable across frames); 'gather'/'fade' are non-directional
		// (0,0 — those exits are handled specially in the render).
		function offDir( p, mode ) {
			if ( mode === 'random' ) { mode = [ 'up', 'right', 'down', 'left' ][ ( Math.random() * 4 ) | 0 ]; }
			if ( mode === 'top' || mode === 'up' ) { return { dx: 0, dy: -130 }; }
			if ( mode === 'bottom' || mode === 'down' ) { return { dx: 0, dy: 130 }; }
			if ( mode === 'left' ) { return { dx: -130, dy: 0 }; }
			if ( mode === 'right' ) { return { dx: 130, dy: 0 }; }
			if ( mode === 'sides' ) { return { dx: p.x < 50 ? -130 : 130, dy: 0 }; }
			if ( mode === 'gather' || mode === 'fade' ) { return { dx: 0, dy: 0 }; }
			var dl = p.x, dr = 100 - p.x, dt = p.y, db = 100 - p.y, m = Math.min( dl, dr, dt, db ); // edges/sweep — nearest
			if ( m === dt ) { return { dx: 0, dy: -130 }; }
			if ( m === db ) { return { dx: 0, dy: 130 }; }
			if ( m === dl ) { return { dx: -130, dy: 0 }; }
			return { dx: 130, dy: 0 };
		}

		// SCROLL mode: the pile organizes itself as you scroll. Cards lerp from the scattered
		// placement to the tidy grid by scroll progress (0 = scattered pile, 1 = organized grid) —
		// the poly "find your files -> your search results" moment. Content stays live, editable cards.
		if ( cycleMd === 'scroll' ) {
			var scat = pts, org = organized(), setEls = [], i2;
			for ( i2 = 0; i2 < pool; i2++ ) {
				var c2 = cards[ i2 ];
				if ( i2 < N ) {
					c2.classList.add( 'is-set' );
					c2.style.transition = 'none';      // scroll-driven — no CSS transition lag
					c2.style.transitionDelay = '0ms';
					c2.style.opacity = '1';
					setEls.push( c2 );
				} else { c2.classList.remove( 'is-set' ); c2.style.opacity = ''; }
			}
			var renderScroll = function () {
				var t = ease( progOf() );   // smoothstep for a soft settle
				for ( var k = 0; k < setEls.length; k++ ) {
					var a = scat[ k ], b = org[ k ], card = setEls[ k ];
					if ( ! a || ! b || ! card ) { continue; }
					card.style.width  = ( cardPct * ( a.s + ( b.s - a.s ) * t ) ) + '%';
					card.style.left   = ( a.x + ( b.x - a.x ) * t ) + '%';
					card.style.top    = ( a.y + ( b.y - a.y ) * t ) + '%';
					card.style.transform = 'translate(-50%,-50%) rotate(' + ( a.r + ( b.r - a.r ) * t ) + 'deg)';
					card.style.zIndex = Math.round( a.z + ( b.z - a.z ) * t );
				}
			};
			// Drive with a rAF loop WHILE IN VIEW (not just scroll events): inside a pinned Stage the
			// element's own position is frozen but story progress keeps advancing, and smooth-scroll
			// libraries don't always emit a 'scroll' per frame — rAF reads the freshest progress.
			var sraf = 0, sInView = true;
			var loop = function () { if ( ! sInView ) { sraf = 0; return; } renderScroll(); sraf = requestAnimationFrame( loop ); };
			if ( 'IntersectionObserver' in window ) {
				new IntersectionObserver( function ( es ) {
					sInView = es[ 0 ].isIntersecting;
					if ( sInView && ! sraf ) { sraf = requestAnimationFrame( loop ); }
				}, { threshold: 0 } ).observe( storyEl || el );
			} else { sraf = requestAnimationFrame( loop ); }
			window.addEventListener( 'resize', renderScroll, { passive: true } );
			renderScroll();
			return;   // scroll mode fully handles placement + motion
		}

		// SCROLL-CYCLE mode (Poly's cinematic pile): as the visitor scrolls, each SET of photos flies
		// in from the chosen edge, holds scattered, then flies back out — one set after the next.
		// Optionally the LAST set arrives as a tidy grid (entering from the bottom, the "search
		// results" resolution) with a couple of corner stragglers from the previous set. Fully
		// scroll-scrubbed; content stays live, editable cards — no baked footage.
		if ( cycleMd === 'scroll_cycle' ) {
			var setsN    = Math.max( 1, Math.ceil( pool / N ) );
			var finalOrg = !! num( el, 'data-tdg-final-org', 1 );
			var corners  = !! num( el, 'data-tdg-corners', 0 );
			var orgG     = organized();
			// Per-set, per-slot poses (scatter target, resting target, enter/exit offsets), computed
			// ONCE so positions are stable frame to frame.
			var setData = [], s, k;
			for ( s = 0; s < setsN; s++ ) {
				var placeS = placements(), isFin = ( s === setsN - 1 ), poses = [];
				for ( k = 0; k < placeS.length; k++ ) {
					var scatP = placeS[ k ];
					var orgP  = ( isFin && finalOrg && orgG[ k ] ) ? orgG[ k ] : null;
					var restP = orgP || scatP;
					poses.push( {
						scat: scatP, rest: restP, org: !! orgP,
						enterOff: offDir( restP, ( isFin && finalOrg ) ? 'bottom' : from ),
						exitOff:  offDir( scatP, exitStyle ),
						gJx: ( Math.random() * 2 - 1 ) * 15, gJy: ( Math.random() * 2 - 1 ) * 12, gR: ( Math.random() * 2 - 1 ) * 17,
						corner: null,
					} );
				}
				setData.push( { isFin: isFin, poses: poses } );
			}
			// Corner stragglers: pin two cards of the set JUST BEFORE the final one to the corners so
			// they linger as the grid enters (bounded — never accumulates across the whole scroll).
			if ( corners && setsN >= 2 ) {
				var pen = setData[ setsN - 2 ].poses, spots = [ { x: 9, y: 12 }, { x: 91, y: 14 } ];
				for ( var ci = 0; ci < Math.min( 2, pen.length ); ci++ ) { pen[ ci ].corner = spots[ ci ]; }
			}
			function setCard( card, L, T, tx, ty, r, w, z, op ) {
				card.style.left = L + '%'; card.style.top = T + '%';
				card.style.transform = 'translate(-50%,-50%) translate(' + tx + '%,' + ty + '%) rotate(' + r + 'deg)';
				card.style.width = w + '%'; card.style.zIndex = z; card.style.opacity = op;
			}
			// Position ONE card given its set-local progress u (may be <0 before its turn, >1 after).
			function placeCard( card, po, u, isFin ) {
				var scatP = po.scat, restP = po.rest, w0 = cardPct * scatP.s, wR = cardPct * restP.s, e, x;
				if ( u <= 0 ) { // not entered yet — parked at its enter-offstage, hidden
					setCard( card, restP.x, restP.y, po.enterOff.dx, po.enterOff.dy, restP.r, wR, scatP.z, 0 ); return;
				}
				if ( isFin && po.org ) { // final organized set: enter once, then rest forever (no exit)
					e = ease( u / 0.5 );
					setCard( card, restP.x, restP.y, po.enterOff.dx * ( 1 - e ), po.enterOff.dy * ( 1 - e ), restP.r, wR, 30 + scatP.z, clamp01( u / 0.12 ) ); return;
				}
				if ( u < 0.28 ) { // ENTER: slide from the enter edge to the scatter pose
					e = ease( u / 0.28 );
					setCard( card, scatP.x, scatP.y, po.enterOff.dx * ( 1 - e ), po.enterOff.dy * ( 1 - e ), scatP.r, w0, scatP.z, clamp01( u / 0.09 ) );
				} else if ( u <= 0.72 ) { // HOLD: resting scatter (a long dwell so the set reads clearly)
					setCard( card, scatP.x, scatP.y, 0, 0, scatP.r, w0, scatP.z, 1 );
				} else if ( u < 1 ) { // EXIT: clear per the exit direction/style
					x = ease( ( u - 0.72 ) / 0.28 );
					if ( exitStyle === 'gather' ) {
						// Gather into a centred OVERLAPPING PILE (poly's "all your files" stack): converge to
						// centre with a loose stack spread + natural lean, keeping FULL size so the cards
						// overlap into a visible pile (not a shrinking cluster). It forms over the first ~70%
						// and HOLDS, then fades only at the very end so the next set can enter; later cards
						// stack on top (raised z).
						var pileT = ease( clamp01( x / 0.7 ) );
						setCard( card, lerp( scatP.x, 50 + po.gJx, pileT ), lerp( scatP.y, 50 + po.gJy, pileT ), 0, 0, lerp( scatP.r, po.gR, pileT ), w0, 30 + scatP.z, x < 0.86 ? 1 : ( 1 - ( x - 0.86 ) / 0.14 ) );
					} else if ( exitStyle === 'fade' ) {
						setCard( card, scatP.x, scatP.y, 0, 0, scatP.r, w0, scatP.z, 1 - x );
					} else { // directional sweep (nearest/up/down/sides)
						setCard( card, scatP.x, scatP.y, po.exitOff.dx * x, po.exitOff.dy * x, scatP.r * ( 1 + 0.6 * x ), w0, scatP.z, 1 - clamp01( ( x - 0.2 ) / 0.8 ) );
					}
				} else { // PAST exit — corner straggler stays; everyone else is gone
					if ( po.corner ) { setCard( card, po.corner.x, po.corner.y, 0, 0, scatP.r * 0.6, w0 * 0.9, 5, 0.5 ); }
					else { setCard( card, scatP.x, scatP.y, po.exitOff.dx, po.exitOff.dy, scatP.r, w0, scatP.z, 0 ); }
				}
			}
			for ( var ii = 0; ii < pool; ii++ ) { cards[ ii ].classList.add( 'is-set' ); cards[ ii ].style.transition = 'none'; cards[ ii ].style.transitionDelay = '0ms'; }

			if ( reduce ) { // reduced motion: show each set's resting layout (final grid visible), no scrub
				for ( var sr = 0; sr < setsN; sr++ ) {
					for ( var kr = 0; kr < setData[ sr ].poses.length; kr++ ) {
						var ir = sr * N + kr; if ( ir >= pool ) { break; }
						var pr2 = setData[ sr ].poses[ kr ];
						setCard( cards[ ir ], pr2.rest.x, pr2.rest.y, 0, 0, pr2.rest.r, cardPct * pr2.rest.s, pr2.scat.z, setData[ sr ].isFin ? 1 : 0 );
					}
				}
				return;
			}
			var renderCycle = function () {
				var p = progOf();
				for ( var s2 = 0; s2 < setsN; s2++ ) {
					var sd = setData[ s2 ], u = ( p - s2 / setsN ) * setsN;
					for ( var k2 = 0; k2 < sd.poses.length; k2++ ) {
						var idx = s2 * N + k2; if ( idx >= pool ) { break; }
						placeCard( cards[ idx ], sd.poses[ k2 ], u, sd.isFin );
					}
				}
			};
			var craf = 0, cInView = true;
			var cloop = function () { if ( ! cInView ) { craf = 0; return; } renderCycle(); craf = requestAnimationFrame( cloop ); };
			if ( 'IntersectionObserver' in window ) {
				new IntersectionObserver( function ( es ) { cInView = es[ 0 ].isIntersecting; if ( cInView && ! craf ) { craf = requestAnimationFrame( cloop ); } }, { threshold: 0 } ).observe( storyEl || el );
			} else { craf = requestAnimationFrame( cloop ); }
			window.addEventListener( 'resize', renderCycle, { passive: true } );
			renderCycle();
			return;   // scroll-cycle mode fully handles placement + motion
		}

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

	/* Device Cycler — a device frame whose SCREEN steps through the images one at a time. The active
	 * screen index comes from scroll progress (parent Scroll Story Stage when present, else the
	 * gallery's own viewport travel) or an automatic dwell timer; CSS cross-fades / slides between
	 * them. Every screen is a live editable image. */
	function initDeviceCycler( el ) {
		if ( el.__tdgInit ) { return; } el.__tdgInit = true;
		var cards = el.querySelectorAll( '.tdg__screen .tdg__card' );
		var N = cards.length;
		if ( ! N ) { return; }
		var mode  = el.getAttribute( 'data-tdg-cycle' ) || 'scroll';
		var dwell = clamp( num( el, 'data-tdg-dwell', 3 ), 1, 12 ) * 1000;
		var stage = el.querySelector( '.tdg__stage' );
		var cur = -1;
		function activate( i ) {
			i = Math.max( 0, Math.min( N - 1, i ) );
			if ( i === cur ) { return; } cur = i;
			for ( var k = 0; k < N; k++ ) { cards[ k ].classList.toggle( 'is-active', k === i ); cards[ k ].classList.toggle( 'is-past', k < i ); }
		}
		activate( 0 );
		if ( reduce || mode === 'off' || N < 2 ) { return; }

		if ( mode === 'scroll' ) {
			var storyEl = null, pScene = null;
			var progOf = function () {
				if ( ! storyEl && el.closest ) { storyEl = el.closest( '.upw-story--stage' ); }
				if ( storyEl && typeof storyEl.__storyProgress === 'number' ) {
					var pr = storyEl.__storyProgress;
					if ( pScene === null ) { pScene = ( el.closest && el.closest( '.upw-story-scene--persist' ) ) || false; }
					if ( pScene && storyEl.__storyBeats && typeof pScene.__pFrom === 'number' ) {
						var lo = pScene.__pFrom / storyEl.__storyBeats, hi = ( pScene.__pTo + 1 ) / storyEl.__storyBeats;
						if ( hi > lo ) { pr = Math.max( 0, Math.min( 1, ( pr - lo ) / ( hi - lo ) ) ); }
					}
					return pr;
				}
				return scrollProgress( el, stage );
			};
			var raf = 0, inView = true;
			var loop = function () { if ( ! inView ) { raf = 0; return; } activate( Math.floor( progOf() * N ) ); raf = requestAnimationFrame( loop ); };
			if ( 'IntersectionObserver' in window ) {
				new IntersectionObserver( function ( es ) { inView = es[ 0 ].isIntersecting; if ( inView && ! raf ) { raf = requestAnimationFrame( loop ); } }, { threshold: 0 } ).observe( el );
			} else { raf = requestAnimationFrame( loop ); }
			return;
		}

		// auto — dwell timer, paused while off-screen.
		var idx = 0, timer = 0, vis = true;
		function tick() { if ( vis ) { idx = ( idx + 1 ) % N; activate( idx ); } timer = setTimeout( tick, dwell ); }
		if ( 'IntersectionObserver' in window ) {
			new IntersectionObserver( function ( es ) { vis = es[ 0 ].isIntersecting; }, { threshold: 0.2 } ).observe( el );
		}
		timer = setTimeout( tick, dwell );
	}

	/* ------------------------------------------------------------------ *
	 * Sphere Cascade — the vertical, spherical sibling of Panorama Wall. A grid of Columns × K cards is
	 * mapped onto a sphere (column = longitude, card = latitude) and CASCADES vertically: each column's
	 * cards scroll along latitude and recycle. Signed Curvature bends BOTH axes from a concave bowl
	 * (viewer inside) through flat to a full convex sphere. Edge Fade dims toward the rim; the latitude
	 * wrap edge fades to hide the recycle. Cards sit tangent to the surface (like Card Sphere).
	 * ------------------------------------------------------------------ */
	function initCascade( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage   = el.querySelector( '.tdg__stage' );
		var cascade = el.querySelector( '.tdg__cascade' );
		if ( ! stage || ! cascade ) { return; }
		var cols = Array.prototype.slice.call( cascade.querySelectorAll( '.tdg__col' ) );
		if ( ! cols.length ) { return; }

		var drive    = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed    = num( el, 'data-tdg-speed', 20 );
		var dir      = num( el, 'data-tdg-dir', 1 );   // +1 up, -1 down
		var alt      = num( el, 'data-tdg-alt', 0 );   // alternate: odd columns cascade the other way
		var curv     = num( el, 'data-tdg-curv', -100 ); // signed: -concave .. +convex
		var tilt     = num( el, 'data-tdg-tilt', 0 );
		var gap      = num( el, 'data-tdg-gap', 5 ) / 100;
		var edge     = clamp( num( el, 'data-tdg-edge', 0 ) / 100, 0, 1 );
		var cardPct  = num( el, 'data-tdg-card', 18 ) / 100;

		var colData = cols.map( function ( cw ) { return { col: cw, cards: Array.prototype.slice.call( cw.querySelectorAll( '.tdg__card' ) ) }; } );
		var C = colData.length;                 // columns
		var K = colData[ 0 ].cards.length;      // cards per column
		if ( ! K ) { return; }
		var zSign = curv >= 0 ? 1 : -1;
		var R = 0, dPhi = 0, dTheta = 0, lonSpan = 0, latSpan = 0, Y = 0, vel = 0;

		function layout() {
			var W = stage.clientWidth || el.clientWidth || 1;
			var cardW = Math.max( 30, W * cardPct );
			var cardH = ( colData[ 0 ].cards[ 0 ] && colData[ 0 ].cards[ 0 ].offsetHeight ) ? colData[ 0 ].cards[ 0 ].offsetHeight : cardW * 0.5625;
			var gapPx = cardW * gap;
			// Radius from |curvature| (inverse: tighter curve = smaller R = more wrap). Same mapping as
			// the Panorama Wall so the two read as siblings.
			var amt = clamp( Math.abs( curv ) / 150, 0.08, 1 );
			R = cardW * 2.87 / amt;
			// Angular steps so neighbours sit edge-to-edge with the gap, on each axis.
			dPhi   = 2 * Math.asin( clamp( ( cardW + gapPx ) / ( 2 * R ), 0, 0.5 ) ) * 180 / Math.PI;
			dTheta = 2 * Math.asin( clamp( ( cardH + gapPx ) / ( 2 * R ), 0, 0.5 ) ) * 180 / Math.PI;
			lonSpan = C * dPhi;
			latSpan = K * dTheta;
			stage.style.perspective = ( W * 1.2 ) + 'px';
			colData.forEach( function ( cd ) {
				cd.cards.forEach( function ( c ) { c.style.width = cardW + 'px'; c.style.marginLeft = ( -cardW / 2 ) + 'px'; c.style.marginTop = ( -cardH / 2 ) + 'px'; } );
			} );
		}

		function applyCascade() {
			// Centre the sphere so its NEAR pole sits at the working depth (z≈0) for ANY radius: cards are
			// at translateZ(zSign·R), so translate the container by -zSign·R. Concave (curv<0) → edges bow
			// TOWARD the viewer (a bowl); convex (curv>0) → centre bulges toward the viewer (a ball). At
			// low |curvature| R is huge, angles tiny → a flat grid filling the frame (no runaway depth).
			cascade.style.transform = 'rotateX(' + tilt + 'deg) translateZ(' + ( -zSign * R ) + 'px)';
			var lonHalf = lonSpan / 2, latHalf = latSpan / 2;
			for ( var j = 0; j < C; j++ ) {
				var cd  = colData[ j ];
				var phi = ( j - ( C - 1 ) / 2 ) * dPhi;                 // fixed column longitude
				var nph = lonHalf > 0 ? Math.abs( phi ) / lonHalf : 0;
				var sdir = alt ? ( j % 2 ? -1 : 1 ) : 1;               // alternate columns cascade opposite
				for ( var k = 0; k < K; k++ ) {
					var c = cd.cards[ k ];
					var raw = ( k - ( K - 1 ) / 2 ) * dTheta + Y * sdir;
					var theta = ( ( raw + latHalf ) % latSpan + latSpan ) % latSpan - latHalf; // wrap into [-latHalf,latHalf)
					var nth = latHalf > 0 ? Math.abs( theta ) / latHalf : 0;
					c.style.transform = 'rotateY(' + phi + 'deg) rotateX(' + ( -theta ) + 'deg) translateZ(' + ( zSign * R ) + 'px)';
					// radial rim fade (both axes) + a hard fade at the latitude wrap edge to hide the recycle
					var af  = Math.min( 1, Math.sqrt( nph * nph + nth * nth ) );
					var vis = nth > 0.86 ? Math.max( 0, ( 1 - nth ) / 0.14 ) : 1;
					c.style.opacity = ( vis * ( 1 - af * af * edge ) ).toFixed( 3 );
					c.style.zIndex = String( 1000 - Math.round( af * 1000 ) );
				}
			}
		}

		layout();
		applyCascade();
		window.addEventListener( 'resize', function () { layout(); applyCascade(); }, { passive: true } );
		var hoverF = hoverFactor( el );
		var allowDrag = num( el, 'data-tdg-allowdrag', 1 );
		var momentum = num( el, 'data-tdg-momentum', 1 );
		var dragging = false, py = 0, scrollY = null;
		var advance = function ( dt ) { Y += dir * ( latSpan / Math.max( 1, speed ) ) * dt * hoverF(); }; // one loop = one full latitude span

		if ( allowDrag ) {
			el.style.cursor = 'grab';
			var down = function ( x, y ) { dragging = true; py = y; vel = 0; el.style.cursor = 'grabbing'; };
			var move = function ( x, y ) { if ( ! dragging ) { return; } var dy = y - py; py = y; Y += dy * 0.15; vel = dy * 0.15; };
			var up = function () { dragging = false; el.style.cursor = 'grab'; };
			attachDrag( el, down, move, up );
		}

		if ( drive === 'scroll' ) {
			var onScroll = function () { scrollY = dir * scrollProgress( el, stage ) * latSpan * 2; };
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
				if ( momentum && Math.abs( vel ) > 0.02 ) { Y += vel; vel *= 0.95; }
				else if ( drive === 'scroll' && scrollY !== null ) { Y = scrollY; }
			}
			applyCascade();
			requestAnimationFrame( loop );
		};
		requestAnimationFrame( loop );
	}

	/* Totem Wall — a flat row of INDEPENDENT vertical cylinders ("totems"). Where Sphere Cascade maps
	 * every column onto ONE shared sphere, here each column is its own vertical barrel: the column sits
	 * at a fixed X and its cards wrap around that column's own horizontal axis (rotateX) into a rounded
	 * pillar. The columns cascade vertically (up / down / alternate) and the cards recycle at the wrap.
	 * Signed Curvature curls each column: negative concave (curves away) → near-flat → positive convex
	 * (bulges toward you into a rounded totem). Zoom sets totem size (fewer, larger totems as it rises).
	 * Shares the cascade DOM (.tdg__cascade > .tdg__col > .tdg__card) and driver shape. */
	function initTotem( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage   = el.querySelector( '.tdg__stage' );
		var cascade = el.querySelector( '.tdg__cascade' );
		if ( ! stage || ! cascade ) { return; }
		var cols = Array.prototype.slice.call( cascade.querySelectorAll( '.tdg__col' ) );
		if ( ! cols.length ) { return; }

		var drive = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed = num( el, 'data-tdg-speed', 20 );
		var dir   = num( el, 'data-tdg-dir', 1 );   // +1 up, -1 down
		var alt   = num( el, 'data-tdg-alt', 0 );   // alternate: odd columns cascade the other way
		var curv  = num( el, 'data-tdg-curv', -150 ); // signed: -concave .. +convex
		var tilt  = num( el, 'data-tdg-tilt', -45 );
		var gap   = num( el, 'data-tdg-gap', 0.5 ) / 100;
		var edge  = clamp( num( el, 'data-tdg-edge', 0 ) / 100, 0, 1 );
		var zoom  = clamp( num( el, 'data-tdg-zoom', 10 ), 0, 100 );

		var colData = cols.map( function ( cw ) { return { col: cw, cards: Array.prototype.slice.call( cw.querySelectorAll( '.tdg__card' ) ) }; } );
		var C = colData.length;                 // totems (columns)
		var K = colData[ 0 ].cards.length;      // cards per totem
		if ( ! K ) { return; }
		var zSign = curv >= 0 ? 1 : -1;
		var R = 0, dTheta = 0, latSpan = 0, pitchX = 0, cardW = 0, cardH = 0, Y = 0, vel = 0;

		function layout() {
			var W = stage.clientWidth || el.clientWidth || 1;
			// Zoom → card width as a fraction of the stage: higher zoom = larger totems, fewer visible
			// (the rim totems fall off the sides and fade). Matches the animos "Zoom" behaviour.
			var cardPct = 0.10 + ( zoom / 100 ) * 0.30;
			cardW = Math.max( 30, W * cardPct );
			cardH = ( colData[ 0 ].cards[ 0 ] && colData[ 0 ].cards[ 0 ].offsetHeight ) ? colData[ 0 ].cards[ 0 ].offsetHeight : cardW * 0.5625;
			var gapPx = cardW * gap;
			// Radius from |curvature| (inverse: tighter curve = smaller R = more wrap), keyed to card
			// HEIGHT because the totem wraps vertically. Same 2.87 constant as its horizontal sibling
			// (Panorama Wall) so the two curves read alike.
			var amt = clamp( Math.abs( curv ) / 150, 0.08, 1 );
			R = cardH * 2.87 / amt;
			dTheta = 2 * Math.asin( clamp( ( cardH + gapPx ) / ( 2 * R ), 0, 0.5 ) ) * 180 / Math.PI;
			latSpan = K * dTheta;
			pitchX  = cardW + gapPx;                // horizontal spacing between totems
			stage.style.perspective = ( W * 1.2 ) + 'px';
			colData.forEach( function ( cd ) {
				cd.cards.forEach( function ( c ) { c.style.width = cardW + 'px'; c.style.marginLeft = ( -cardW / 2 ) + 'px'; c.style.marginTop = ( -cardH / 2 ) + 'px'; } );
			} );
		}

		function applyTotem() {
			// The whole wall only tips (Tilt). Each column is centred at the stage origin, then pushed to
			// its X slot and depth-centred so its NEAR face sits at z≈0 for ANY radius (cards translateZ
			// (zSign·R); the column translates by -zSign·R). So concave (curv<0) curls the totem away, and
			// convex (curv>0) bulges it toward the viewer — each column independent, in a flat row.
			cascade.style.transform = 'rotateX(' + tilt + 'deg)';
			var latHalf = latSpan / 2;
			var xHalf   = ( C - 1 ) / 2;
			for ( var j = 0; j < C; j++ ) {
				var cd = colData[ j ];
				var x  = ( j - xHalf ) * pitchX;
				cd.col.style.transform = 'translateX(' + x + 'px) translateZ(' + ( -zSign * R ) + 'px)';
				var nx   = xHalf > 0 ? Math.abs( j - xHalf ) / xHalf : 0; // 0 centre column .. 1 rim column
				var sdir = alt ? ( j % 2 ? -1 : 1 ) : 1;                  // alternate columns cascade opposite
				for ( var k = 0; k < K; k++ ) {
					var c   = cd.cards[ k ];
					var raw = ( k - ( K - 1 ) / 2 ) * dTheta + Y * sdir;
					var theta = ( ( raw + latHalf ) % latSpan + latSpan ) % latSpan - latHalf; // wrap into [-latHalf,latHalf)
					var nth = latHalf > 0 ? Math.abs( theta ) / latHalf : 0;
					c.style.transform = 'rotateX(' + ( -theta ) + 'deg) translateZ(' + ( zSign * R ) + 'px)';
					// hard fade at the wrap edge hides the recycle; Edge Fade dims the rim totems (left/right).
					var vis = nth > 0.86 ? Math.max( 0, ( 1 - nth ) / 0.14 ) : 1;
					c.style.opacity = ( vis * ( 1 - nx * nx * edge ) ).toFixed( 3 );
					c.style.zIndex = String( 1000 - Math.round( nth * 1000 ) );
				}
			}
		}

		layout();
		applyTotem();
		window.addEventListener( 'resize', function () { layout(); applyTotem(); }, { passive: true } );
		var hoverF = hoverFactor( el );
		var allowDrag = num( el, 'data-tdg-allowdrag', 1 );
		var momentum = num( el, 'data-tdg-momentum', 1 );
		var dragging = false, py = 0, scrollY = null;
		var advance = function ( dt ) { Y += dir * ( latSpan / Math.max( 1, speed ) ) * dt * hoverF(); }; // one loop = one latitude span

		if ( allowDrag ) {
			el.style.cursor = 'grab';
			var down = function ( x, y ) { dragging = true; py = y; vel = 0; el.style.cursor = 'grabbing'; };
			var move = function ( x, y ) { if ( ! dragging ) { return; } var dy = y - py; py = y; Y += dy * 0.15; vel = dy * 0.15; };
			var up = function () { dragging = false; el.style.cursor = 'grab'; };
			attachDrag( el, down, move, up );
		}

		if ( drive === 'scroll' ) {
			var onScroll = function () { scrollY = dir * scrollProgress( el, stage ) * latSpan * 2; };
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
				if ( momentum && Math.abs( vel ) > 0.02 ) { Y += vel; vel *= 0.95; }
				else if ( drive === 'scroll' && scrollY !== null ) { Y = scrollY; }
			}
			applyTotem();
			requestAnimationFrame( loop );
		};
		requestAnimationFrame( loop );
	}

	/* Parallax Totem — the depth-scatter sibling of Totem Wall. Same flat row of vertical totems
	 * (columns wrapping their cards around a horizontal axis, cascading vertically), but every card also
	 * gets a SEEDED depth (translateZ), size and position jitter, and drifts at a depth-varied speed —
	 * near cards scroll faster than far ones (the parallax). At Scatter/Size/Parallax all 0 it is a
	 * clean vertical grid; raised, it becomes a floating depth field. Seeded so the layout is stable. */
	function initParallax( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage   = el.querySelector( '.tdg__stage' );
		var cascade = el.querySelector( '.tdg__cascade' );
		if ( ! stage || ! cascade ) { return; }
		var cols = Array.prototype.slice.call( cascade.querySelectorAll( '.tdg__col' ) );
		if ( ! cols.length ) { return; }

		var drive    = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed    = num( el, 'data-tdg-speed', 20 );
		var dir      = num( el, 'data-tdg-dir', 1 );   // +1 up, -1 down
		var curv     = num( el, 'data-tdg-curv', -150 );
		var gap      = num( el, 'data-tdg-gap', 0.5 ) / 100;
		var edge     = clamp( num( el, 'data-tdg-edge', 0 ) / 100, 0, 1 );
		var zoom     = clamp( num( el, 'data-tdg-zoom', 10 ), 0, 100 );
		var scatter  = clamp( num( el, 'data-tdg-scatter', 0 ) / 100, 0, 1 );
		var sizeVar  = clamp( num( el, 'data-tdg-sizevar', 0 ) / 100, 0, 1 );
		var parallax = clamp( num( el, 'data-tdg-parallax', 0 ) / 100, 0, 1 );

		// Stable per-card pseudo-random in [0,1) from the (column,row,salt) triple — no Math.random so the
		// scatter never reshuffles between frames or reloads.
		function rnd( c, k, salt ) { var x = Math.sin( ( c + 1 ) * 127.1 + ( k + 1 ) * 311.7 + salt * 74.7 ) * 43758.5453; return x - Math.floor( x ); }

		var colData = cols.map( function ( cw ) { return { col: cw, cards: Array.prototype.slice.call( cw.querySelectorAll( '.tdg__card' ) ) }; } );
		var C = colData.length;
		var K = colData[ 0 ].cards.length;
		if ( ! K ) { return; }
		var zSign = curv >= 0 ? 1 : -1;
		var R = 0, dTheta = 0, latSpan = 0, pitchX = 0, cardW = 0, cardH = 0, Y = 0, vel = 0;
		// bake the seeded per-card variation once
		colData.forEach( function ( cd, c ) {
			cd.meta = cd.cards.map( function ( _, k ) {
				var d = rnd( c, k, 1 ) * 2 - 1;      // depth −1 (far) .. +1 (near)
				return {
					depth: d,
					size:  1 + sizeVar * ( rnd( c, k, 2 ) - 0.5 ) * 1.3,
					jx:    ( rnd( c, k, 3 ) - 0.5 ),
					jy:    ( rnd( c, k, 4 ) - 0.5 ),
					spd:   1 + parallax * d * 0.85    // near cards drift faster (the parallax)
				};
			} );
		} );

		function layout() {
			var W = stage.clientWidth || el.clientWidth || 1;
			var cardPct = 0.10 + ( zoom / 100 ) * 0.30;
			cardW = Math.max( 30, W * cardPct );
			cardH = ( colData[ 0 ].cards[ 0 ] && colData[ 0 ].cards[ 0 ].offsetHeight ) ? colData[ 0 ].cards[ 0 ].offsetHeight : cardW * 0.5625;
			var gapPx = cardW * gap;
			var amt = clamp( Math.abs( curv ) / 150, 0.08, 1 );
			R = cardH * 2.87 / amt;
			dTheta = 2 * Math.asin( clamp( ( cardH + gapPx ) / ( 2 * R ), 0, 0.5 ) ) * 180 / Math.PI;
			latSpan = K * dTheta;
			pitchX  = cardW + gapPx;
			stage.style.perspective = ( W * 1.2 ) + 'px';
			colData.forEach( function ( cd ) {
				cd.cards.forEach( function ( c ) { c.style.width = cardW + 'px'; c.style.marginLeft = ( -cardW / 2 ) + 'px'; c.style.marginTop = ( -cardH / 2 ) + 'px'; } );
			} );
		}

		function applyParallax() {
			cascade.style.transform = ''; // no tilt on the parallax field
			var latHalf = latSpan / 2, xHalf = ( C - 1 ) / 2;
			for ( var j = 0; j < C; j++ ) {
				var cd = colData[ j ];
				var x  = ( j - xHalf ) * pitchX;
				cd.col.style.transform = 'translateX(' + x + 'px) translateZ(' + ( -zSign * R ) + 'px)';
				var nx = xHalf > 0 ? Math.abs( j - xHalf ) / xHalf : 0;
				for ( var k = 0; k < K; k++ ) {
					var c = cd.cards[ k ], m = cd.meta[ k ];
					var raw = ( k - ( K - 1 ) / 2 ) * dTheta + Y * m.spd;   // parallax: per-card drift rate
					var theta = ( ( raw + latHalf ) % latSpan + latSpan ) % latSpan - latHalf;
					var nth = latHalf > 0 ? Math.abs( theta ) / latHalf : 0;
					var jitX  = m.jx * scatter * pitchX * 0.85;
					var jitY  = m.jy * scatter * cardH * 1.3;
					var depthZ = m.depth * parallax * cardW * 1.6;         // seeded depth offset (parallax volume)
					c.style.transform = 'translate3d(' + jitX.toFixed( 1 ) + 'px,' + jitY.toFixed( 1 ) + 'px,' + depthZ.toFixed( 1 ) + 'px) '
						+ 'rotateX(' + ( -theta ) + 'deg) translateZ(' + ( zSign * R ) + 'px) scale(' + m.size.toFixed( 3 ) + ')';
					var vis = nth > 0.86 ? Math.max( 0, ( 1 - nth ) / 0.14 ) : 1;
					var dfade = m.depth < 0 ? ( 1 + m.depth * parallax * 0.55 ) : 1; // far cards dim a touch
					c.style.opacity = ( vis * ( 1 - nx * nx * edge ) * dfade ).toFixed( 3 );
					c.style.zIndex = String( 1000 + Math.round( m.depth * 400 ) - Math.round( nth * 600 ) );
				}
			}
		}

		layout();
		applyParallax();
		window.addEventListener( 'resize', function () { layout(); applyParallax(); }, { passive: true } );
		var hoverF = hoverFactor( el );
		var allowDrag = num( el, 'data-tdg-allowdrag', 1 );
		var momentum = num( el, 'data-tdg-momentum', 1 );
		var dragging = false, py = 0, scrollY = null;
		var advance = function ( dt ) { Y += dir * ( latSpan / Math.max( 1, speed ) ) * dt * hoverF(); };

		if ( allowDrag ) {
			el.style.cursor = 'grab';
			var down = function ( x, y ) { dragging = true; py = y; vel = 0; el.style.cursor = 'grabbing'; };
			var move = function ( x, y ) { if ( ! dragging ) { return; } var dy = y - py; py = y; Y += dy * 0.15; vel = dy * 0.15; };
			var up = function () { dragging = false; el.style.cursor = 'grab'; };
			attachDrag( el, down, move, up );
		}

		if ( drive === 'scroll' ) {
			var onScroll = function () { scrollY = dir * scrollProgress( el, stage ) * latSpan * 2; };
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
				if ( momentum && Math.abs( vel ) > 0.02 ) { Y += vel; vel *= 0.95; }
				else if ( drive === 'scroll' && scrollY !== null ) { Y = scrollY; }
			}
			applyParallax();
			requestAnimationFrame( loop );
		};
		requestAnimationFrame( loop );
	}

	/* Card Tunnel — an infinite perspective corridor. Four walls (ceiling / floor / left / right), each a
	 * ribbon of cards tiled along the tunnel axis (Z), receding to a central vanishing point. The whole
	 * set flies Forward or Backward and recycles seamlessly (a card fading out at the near mouth is the
	 * same one reappearing, dim, at the far end). Tunnel Size sets the corridor width; Card Length the
	 * per-card depth; Depth Fade dims the far end into the dark. Cards are sized to their wall (not by
	 * card ratio), so the images fill each wall segment. */
	function initTunnel( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage  = el.querySelector( '.tdg__stage' );
		var tunnel = el.querySelector( '.tdg__tunnel' );
		if ( ! stage || ! tunnel ) { return; }
		var walls = [ 'top', 'bottom', 'left', 'right' ].map( function ( n ) {
			var w = el.querySelector( '.tdg__wall--' + n );
			return { name: n, cards: w ? Array.prototype.slice.call( w.querySelectorAll( '.tdg__card' ) ) : [] };
		} );
		var N = walls[ 0 ].cards.length;
		if ( ! N ) { return; }

		var drive = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed = num( el, 'data-tdg-speed', 20 );
		var dir   = num( el, 'data-tdg-dir', 1 );                 // +1 forward (into the tunnel), -1 backward
		var tsize = clamp( num( el, 'data-tdg-tsize', 60 ), 0, 120 ) / 100;
		var clen  = clamp( num( el, 'data-tdg-clen', 30 ), 0, 120 ) / 100;
		var gap   = clamp( num( el, 'data-tdg-gap', 2 ), 0, 40 ) / 100;
		var dfade = clamp( num( el, 'data-tdg-dfade', 0 ) / 100, 0, 1 );
		var W = 0, H = 0, L = 0, step = 0, D = 0, Y = 0, vel = 0;

		function layout() {
			var SW = stage.clientWidth || el.clientWidth || 1;
			var SH = stage.clientHeight || el.clientHeight || 1;
			W = SW * 0.5 * tsize; H = SH * 0.5 * tsize;          // half tunnel width / height
			L = SW * ( 0.15 + clen * 0.6 );                       // per-card depth (Z-length)
			step = L + L * gap;                                    // card + gap along Z
			D = step * N;                                          // full tunnel length → seamless wrap
			stage.style.perspective = ( SW * 0.72 ) + 'px';
			walls.forEach( function ( wl ) {
				var horiz = ( wl.name === 'top' || wl.name === 'bottom' );
				var w = horiz ? ( 2 * W ) : L, h = horiz ? L : ( 2 * H );
				wl.cards.forEach( function ( c ) { c.style.width = w + 'px'; c.style.height = h + 'px'; c.style.marginLeft = ( -w / 2 ) + 'px'; c.style.marginTop = ( -h / 2 ) + 'px'; } );
			} );
		}

		function applyTunnel() {
			tunnel.style.transform = '';
			for ( var wi = 0; wi < walls.length; wi++ ) {
				var wl = walls[ wi ];
				for ( var i = 0; i < N; i++ ) {
					var c = wl.cards[ i ];
					var raw  = i * step - Y * dir;
					var zpos = ( ( raw % D ) + D ) % D;             // [0,D)
					var z    = -zpos;                               // 0 near mouth .. -D far end
					var pFar = zpos / D;                            // 0 near .. 1 far
					var base;
					if ( wl.name === 'top' )         { base = 'translate3d(0,' + ( -H ) + 'px,' + z + 'px) rotateX(-90deg)'; }
					else if ( wl.name === 'bottom' ) { base = 'translate3d(0,' + ( H )  + 'px,' + z + 'px) rotateX(90deg)'; }
					else if ( wl.name === 'left' )   { base = 'translate3d(' + ( -W ) + 'px,0,' + z + 'px) rotateY(90deg)'; }
					else                             { base = 'translate3d(' + ( W )  + 'px,0,' + z + 'px) rotateY(-90deg)'; }
					c.style.transform = base;
					// fade both wrap seams (near mouth + far end) so the recycle is invisible either direction,
					// plus the optional Depth Fade dimming toward the far end.
					var seam = pFar < 0.06 ? pFar / 0.06 : ( pFar > 0.94 ? ( 1 - pFar ) / 0.06 : 1 );
					c.style.opacity = ( seam * ( 1 - dfade * pFar * pFar ) ).toFixed( 3 );
					c.style.zIndex = String( 1000 - Math.round( pFar * 1000 ) );
				}
			}
		}

		layout();
		applyTunnel();
		window.addEventListener( 'resize', function () { layout(); applyTunnel(); }, { passive: true } );
		var hoverF = hoverFactor( el );
		var allowDrag = num( el, 'data-tdg-allowdrag', 1 );
		var momentum = num( el, 'data-tdg-momentum', 1 );
		var dragging = false, py = 0, scrollY = null;
		var advance = function ( dt ) { Y += ( D / Math.max( 1, speed ) ) * dt * hoverF(); }; // one loop = whole tunnel

		if ( allowDrag ) {
			el.style.cursor = 'grab';
			var down = function ( x, y ) { dragging = true; py = y; vel = 0; el.style.cursor = 'grabbing'; };
			var move = function ( x, y ) { if ( ! dragging ) { return; } var dy = y - py; py = y; var d = -dy * step * 0.05; Y += d; vel = d; };
			var up = function () { dragging = false; el.style.cursor = 'grab'; };
			attachDrag( el, down, move, up );
		}

		if ( drive === 'scroll' ) {
			var onScroll = function () { scrollY = scrollProgress( el, stage ) * D * 2; };
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
				if ( momentum && Math.abs( vel ) > 0.02 ) { Y += vel; vel *= 0.95; }
				else if ( drive === 'scroll' && scrollY !== null ) { Y = scrollY; }
			}
			applyTunnel();
			requestAnimationFrame( loop );
		};
		requestAnimationFrame( loop );
	}

	/* Spiral Stream — cards threaded along a 3D helix that streams past the camera. Each card sits at a
	 * point on the spiral (angle = position along it × Spiral Turns) at a tapering radius (Taper narrows
	 * the coil into a vortex or flares it out) and rotates around its own vertical axis as it goes — so
	 * you see fronts, edges and mirrored backs, upright throughout. The whole helix tips by Ring Tilt;
	 * Perspective sets the depth, Back Fade dims the tail, Scale Pulse breathes the card sizes. */
	function initSpiral( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage  = el.querySelector( '.tdg__stage' );
		var spiral = el.querySelector( '.tdg__spiral' );
		if ( ! stage || ! spiral ) { return; }
		var cards = Array.prototype.slice.call( spiral.querySelectorAll( '.tdg__card' ) );
		var Nc = cards.length;
		if ( ! Nc ) { return; }

		var drive = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed = num( el, 'data-tdg-speed', 20 );
		var dir   = num( el, 'data-tdg-dir', 1 );
		var turns = clamp( num( el, 'data-tdg-turns', 1 ), 0.25, 6 );
		var size  = clamp( num( el, 'data-tdg-size', 35 ), 0, 100 ) / 100;
		var taper = clamp( num( el, 'data-tdg-taper', -90 ), -100, 100 ) / 100;
		var cardPct = clamp( num( el, 'data-tdg-card', 12 ), 0, 60 ) / 100;
		var backFade = clamp( num( el, 'data-tdg-backfade', 10 ) / 100, 0, 1 );
		var perspPct = clamp( num( el, 'data-tdg-persp', 0 ), 0, 100 ) / 100;
		var tilt  = num( el, 'data-tdg-tilt', -45 );
		var gap   = clamp( num( el, 'data-tdg-gap', 0 ) / 100, 0, 1 );
		var pulse = clamp( num( el, 'data-tdg-pulse', 0 ) / 100, 0, 1 );
		var R = 0, depth = 0, cardW = 0, S = 0, vel = 0;

		function layout() {
			var W = stage.clientWidth || el.clientWidth || 1;
			var H = stage.clientHeight || el.clientHeight || 1;
			R = W * 0.5 * size;                              // helix radius (swing of the coil)
			depth = W * ( 0.9 + gap * 1.8 );                  // axial length; Card Gap stretches the coil
			cardW = Math.max( 20, W * cardPct );
			stage.style.perspective = ( W * ( 2.2 - perspPct * 1.5 ) ) + 'px'; // higher % = stronger depth
			cards.forEach( function ( c ) { c.style.width = cardW + 'px'; c.style.marginLeft = ( -cardW / 2 ) + 'px'; } );
			cards.forEach( function ( c ) { c.style.marginTop = ( -( c.offsetHeight || cardW ) / 2 ) + 'px'; } );
		}

		function applySpiral() {
			// Tilt the coil axis, then push the (centred) coil back ALONG that axis so it recedes cleanly —
			// nothing sits on top of the camera (no blow-up) and the tilt pivots the coil, not the stage.
			var backoff = depth * 0.5 + ( stage.clientWidth || 1 ) * 0.35;
			spiral.style.transform = 'translateZ(' + ( -backoff ) + 'px) rotateX(' + tilt + 'deg)';
			for ( var i = 0; i < Nc; i++ ) {
				var c = cards[ i ];
				var t0 = Nc > 1 ? i / ( Nc - 1 ) : 0;
				var t  = ( ( t0 + S * dir ) % 1 + 1 ) % 1;      // stream: shift along the helix and wrap
				var ang = t * turns * Math.PI * 2;
				var rf = Math.max( 0.04, 1 + taper * ( t * 2 - 1 ) ); // Taper radius profile (vortex ↔ flare)
				var r = R * rf;
				var x = r * Math.cos( ang ), y = r * Math.sin( ang );
				var z = ( 0.5 - t ) * depth;                    // coil centred on its axis (pushed back by the container)
				var sc = 1 + pulse * 0.5 * Math.sin( ang * 1.3 + S * Math.PI * 2 );
				c.style.transform = 'translate3d(' + x.toFixed( 1 ) + 'px,' + y.toFixed( 1 ) + 'px,' + z.toFixed( 1 ) + 'px) '
					+ 'rotateY(' + ( ang * 180 / Math.PI ).toFixed( 1 ) + 'deg) scale(' + sc.toFixed( 3 ) + ')';
				// seam fade at both ends of the coil (hide the wrap) + Back Fade dimming toward the tail
				var seam = t < 0.05 ? t / 0.05 : ( t > 0.95 ? ( 1 - t ) / 0.05 : 1 );
				c.style.opacity = ( seam * ( 1 - backFade * t ) ).toFixed( 3 );
				c.style.zIndex = String( 1000 + Math.round( z ) );
			}
		}

		layout();
		applySpiral();
		window.addEventListener( 'resize', function () { layout(); applySpiral(); }, { passive: true } );
		var hoverF = hoverFactor( el );
		var allowDrag = num( el, 'data-tdg-allowdrag', 1 );
		var momentum = num( el, 'data-tdg-momentum', 1 );
		var dragging = false, py = 0, scrollY = null;
		var advance = function ( dt ) { S += ( 1 / Math.max( 1, speed ) ) * dt * hoverF(); }; // one loop = one card slot cycle

		if ( allowDrag ) {
			el.style.cursor = 'grab';
			var down = function ( x, y ) { dragging = true; py = y; vel = 0; el.style.cursor = 'grabbing'; };
			var move = function ( x, y ) { if ( ! dragging ) { return; } var dy = y - py; py = y; var d = -dy * 0.0015; S += d; vel = d; };
			var up = function () { dragging = false; el.style.cursor = 'grab'; };
			attachDrag( el, down, move, up );
		}

		if ( drive === 'scroll' ) {
			var onScroll = function () { scrollY = scrollProgress( el, stage ) * 2; };
			window.addEventListener( 'scroll', onScroll, { passive: true } );
			onScroll();
		}

		var autoRun = ( drive === 'continuous' && ! reduce );
		if ( ! ( autoRun || allowDrag || drive === 'scroll' ) ) { return; }
		var last = 0;
		var loop = function ( tm ) {
			if ( ! last ) { last = tm; }
			var dt = ( tm - last ) / 1000; last = tm;
			if ( ! dragging ) {
				if ( autoRun ) { advance( dt ); }
				if ( momentum && Math.abs( vel ) > 0.00002 ) { S += vel; vel *= 0.95; }
				else if ( drive === 'scroll' && scrollY !== null ) { S = scrollY; }
			}
			applySpiral();
			requestAnimationFrame( loop );
		};
		requestAnimationFrame( loop );
	}

	/* Depth Stack — a deck of cards receding straight into DEPTH (z), the front one the hero. The stack
	 * streams toward the camera: the front card flies out and fades while the next comes forward and a
	 * new one fades in at the back. Spread (in a chosen angle, Fan) or Scatter fans the deck out; Wobble
	 * jitters each card's tilt; Depth Fade dims and Depth Blur softens the cards toward the back (a
	 * depth-of-field falloff). Cards billboard (face the camera) so they stay readable. */
	function initDepthStack( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var deck  = el.querySelector( '.tdg__depth' );
		if ( ! stage || ! deck ) { return; }
		var cards = Array.prototype.slice.call( deck.querySelectorAll( '.tdg__card' ) );
		var N = cards.length;
		if ( ! N ) { return; }

		var drive = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed = num( el, 'data-tdg-speed', 20 );
		var dir   = num( el, 'data-tdg-dir', 1 );
		var gap   = clamp( num( el, 'data-tdg-gap', 18 ), 0, 80 ) / 100;
		var cardPct = clamp( num( el, 'data-tdg-card', 20 ), 4, 80 ) / 100;
		var spread = clamp( num( el, 'data-tdg-spread', 0 ), 0, 120 ) / 100;
		var ang   = num( el, 'data-tdg-angle', -180 ) * Math.PI / 180;
		var wobble = clamp( num( el, 'data-tdg-wobble', 0 ) / 100, 0, 1 );
		var dFade = clamp( num( el, 'data-tdg-dfade', 0 ) / 100, 0, 1 );
		var dBlur = clamp( num( el, 'data-tdg-dblur', 0 ) / 100, 0, 1 );
		var scatter = ( el.getAttribute( 'data-tdg-layout' ) === 'scatter' );

		function rnd( i, s ) { var x = Math.sin( ( i + 1 ) * 91.7 + s * 47.3 ) * 43758.5453; return x - Math.floor( x ); }
		var meta = cards.map( function ( _, i ) { return { rot: rnd( i, 1 ) * 2 - 1, sx: rnd( i, 2 ) * 2 - 1, sy: rnd( i, 3 ) * 2 - 1 }; } );

		var totalDepth = 0, cardW = 0, spreadMag = 0, W = 1;
		function layout() {
			W = stage.clientWidth || el.clientWidth || 1;
			cardW = Math.max( 24, W * cardPct );
			totalDepth = W * ( 1.2 + gap * 3.2 );
			spreadMag = W * 0.62 * spread;
			stage.style.perspective = ( W * 1.5 ) + 'px';
			cards.forEach( function ( c ) { c.style.width = cardW + 'px'; c.style.marginLeft = ( -cardW / 2 ) + 'px'; } );
			cards.forEach( function ( c ) { c.style.marginTop = ( -( c.offsetHeight || cardW ) / 2 ) + 'px'; } );
		}

		function applyDepth() {
			deck.style.transform = '';
			for ( var i = 0; i < N; i++ ) {
				var c = cards[ i ], m = meta[ i ];
				var raw = i / N - S * dir;
				// d in [-0.15, 0.85): 0 = hero front, <0 = flying out past the camera, →0.85 = far back
				var d = ( ( raw + 0.15 ) % 1 + 1 ) % 1 - 0.15;
				var z = -d * totalDepth;
				var ox, oy;
				if ( scatter ) { ox = m.sx * spreadMag; oy = m.sy * spreadMag; }             // fixed random scatter
				else { var f = Math.max( 0, d ); ox = spreadMag * f * Math.cos( ang ); oy = spreadMag * f * Math.sin( ang ); } // fan: more spread toward the back
				var rot = m.rot * wobble * 22;
				c.style.transform = 'translate3d(' + ox.toFixed( 1 ) + 'px,' + oy.toFixed( 1 ) + 'px,' + z.toFixed( 1 ) + 'px) rotateZ(' + rot.toFixed( 1 ) + 'deg)';
				var bl = dBlur * Math.max( 0, d ) * 18;
				c.style.filter = bl > 0.3 ? ( 'blur(' + bl.toFixed( 1 ) + 'px)' ) : '';
				// fade the fly-out (d<0) and the far entry (d→0.85); Depth Fade dims toward the back
				var fadeOut = d < 0 ? Math.max( 0, 1 + d / 0.15 ) : 1;
				var fadeIn  = d > 0.80 ? Math.max( 0, ( 0.85 - d ) / 0.05 ) : 1;
				c.style.opacity = ( fadeOut * fadeIn * ( 1 - dFade * Math.max( 0, d ) ) ).toFixed( 3 );
				c.style.zIndex = String( 2000 - Math.round( ( d + 0.15 ) * 1000 ) );
			}
		}

		var S = 0, vel = 0;
		layout();
		applyDepth();
		window.addEventListener( 'resize', function () { layout(); applyDepth(); }, { passive: true } );
		var hoverF = hoverFactor( el );
		var allowDrag = num( el, 'data-tdg-allowdrag', 1 );
		var momentum = num( el, 'data-tdg-momentum', 1 );
		var dragging = false, py = 0, scrollY = null;
		var advance = function ( dt ) { S += ( 1 / Math.max( 1, speed ) ) * dt * hoverF(); };

		if ( allowDrag ) {
			el.style.cursor = 'grab';
			var down = function ( x, y ) { dragging = true; py = y; vel = 0; el.style.cursor = 'grabbing'; };
			var move = function ( x, y ) { if ( ! dragging ) { return; } var dy = y - py; py = y; var dd = -dy * 0.0016; S += dd; vel = dd; };
			var up = function () { dragging = false; el.style.cursor = 'grab'; };
			attachDrag( el, down, move, up );
		}

		if ( drive === 'scroll' ) {
			var onScroll = function () { scrollY = scrollProgress( el, stage ) * ( N > 1 ? N - 1 : 1 ); };
			window.addEventListener( 'scroll', onScroll, { passive: true } );
			onScroll();
		}

		var autoRun = ( drive === 'continuous' && ! reduce );
		if ( ! ( autoRun || allowDrag || drive === 'scroll' ) ) { return; }
		var last = 0;
		var loop = function ( tm ) {
			if ( ! last ) { last = tm; }
			var dt = ( tm - last ) / 1000; last = tm;
			if ( ! dragging ) {
				if ( autoRun ) { advance( dt ); }
				if ( momentum && Math.abs( vel ) > 0.00002 ) { S += vel; vel *= 0.95; }
				else if ( drive === 'scroll' && scrollY !== null ) { S = scrollY; }
			}
			applyDepth();
			requestAnimationFrame( loop );
		};
		requestAnimationFrame( loop );
	}

	/* Card Reel — a vertical cover-flow carousel. A single column of cards wraps around a horizontal
	 * cylinder: the card at the centre sits flat and forward (the focus), the ones above and below tilt
	 * back and recede along the curve. The reel flows up/down continuously, or "Stop at centre" snaps
	 * each card to the middle with a dwell. The 3D Curve bends the cylinder (flat ↔ tight barrel); Card
	 * Size and Gap set the spacing. Lives in the Carousel & Flow family. */
	function initReel( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var reel  = el.querySelector( '.tdg__reel' );
		if ( ! stage || ! reel ) { return; }
		var cards = Array.prototype.slice.call( reel.querySelectorAll( '.tdg__card' ) );
		var N = cards.length;
		if ( ! N ) { return; }

		var drive = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed = num( el, 'data-tdg-speed', 20 );
		var dir   = num( el, 'data-tdg-dir', 1 );                 // +1 up, -1 down
		var curve = clamp( num( el, 'data-tdg-curve', -100 ), -100, 100 );
		var cardPct = clamp( num( el, 'data-tdg-card', 22 ), 6, 60 ) / 100;
		var gap   = clamp( num( el, 'data-tdg-gap', 1 ), 0, 20 ) / 100;
		var snap  = ( el.getAttribute( 'data-tdg-snap' ) === 'yes' );
		var horiz = ( el.getAttribute( 'data-tdg-axis' ) === 'h' );  // Film Strip = horizontal; Card Reel = vertical
		var dTheta = 0, R = 0, zSign = 1, cardW = 0, cardH = 0, pos = 0, vel = 0;

		function layout() {
			var W = stage.clientWidth || el.clientWidth || 1;
			cardW = Math.max( 30, W * cardPct );
			cardH = ( cards[ 0 ] && cards[ 0 ].offsetHeight ) ? cards[ 0 ].offsetHeight : cardW;
			var dim = horiz ? cardW : cardH;                    // cards step along the reel's own axis
			var step = dim + dim * gap;
			var amt = clamp( Math.abs( curve ) / 100, 0.06, 1 );
			dTheta = amt * 34;                                     // degrees between cards on the cylinder
			var half = dTheta / 2 * Math.PI / 180;
			R = step / ( 2 * Math.max( 0.02, Math.sin( half ) ) ); // radius so neighbours sit edge-to-edge
			zSign = curve >= 0 ? -1 : 1;                           // negative curve = edges bend BACK (the default look)
			stage.style.perspective = ( W * 1.4 ) + 'px';
			cards.forEach( function ( c ) { c.style.width = cardW + 'px'; c.style.marginLeft = ( -cardW / 2 ) + 'px'; } );
			cards.forEach( function ( c ) { c.style.marginTop = ( -( c.offsetHeight || cardW ) / 2 ) + 'px'; } );
		}

		function applyReel() {
			reel.style.transform = '';
			for ( var i = 0; i < N; i++ ) {
				var c = cards[ i ];
				var p = ( ( i - pos ) % N + N ) % N;               // 0..N
				if ( p > N / 2 ) { p -= N; }                        // wrap into [-N/2, N/2)
				var th = p * dTheta;                                // degrees from centre
				var thr = th * Math.PI / 180;
				var s = R * Math.sin( thr );                        // position along the reel axis
				var z = zSign * ( R * Math.cos( thr ) - R );
				c.style.transform = horiz
					? ( 'translate3d(' + s.toFixed( 1 ) + 'px,0,' + z.toFixed( 1 ) + 'px) rotateY(' + ( zSign * th ).toFixed( 1 ) + 'deg)' )
					: ( 'translate3d(0,' + s.toFixed( 1 ) + 'px,' + z.toFixed( 1 ) + 'px) rotateX(' + ( zSign * -th ).toFixed( 1 ) + 'deg)' );
				var a = Math.abs( th );
				c.style.opacity = ( a > 90 ? Math.max( 0, ( 120 - a ) / 30 ) : 1 ).toFixed( 3 ); // fade cards rounding to the back
				c.style.zIndex = String( 1000 - Math.round( a ) );
			}
		}

		layout();
		applyReel();
		window.addEventListener( 'resize', function () { layout(); applyReel(); }, { passive: true } );
		var hoverF = hoverFactor( el );
		var allowDrag = num( el, 'data-tdg-allowdrag', 1 );
		var momentum = num( el, 'data-tdg-momentum', 1 );
		var dragging = false, pc = 0, scrollY = null, target = 0, dwellT = 0;
		var perCard = Math.max( 0.5, speed / Math.max( 1, N ) );  // seconds a card holds centre (snap)
		var advance = function ( dt ) {
			if ( snap ) {
				dwellT += dt;
				if ( dwellT >= perCard ) { dwellT = 0; target += dir; }
				pos += ( target - pos ) * Math.min( 1, dt * 6 ); // ease toward the snapped target
			} else {
				pos += dir * ( N / Math.max( 1, speed ) ) * dt * hoverF();
			}
		};

		if ( allowDrag ) {
			el.style.cursor = 'grab';
			var down = function ( x, y ) { dragging = true; pc = horiz ? x : y; vel = 0; el.style.cursor = 'grabbing'; };
			var move = function ( x, y ) { if ( ! dragging ) { return; } var cur = horiz ? x : y; var delta = cur - pc; pc = cur; var d = -delta / Math.max( 1, ( horiz ? cardW : cardH ) * 0.9 ); pos += d; vel = d; };
			var up = function () { dragging = false; el.style.cursor = 'grab'; if ( snap ) { target = Math.round( pos ); } };
			attachDrag( el, down, move, up );
		}

		if ( drive === 'scroll' ) {
			var onScroll = function () { scrollY = dir * scrollProgress( el, stage ) * N; };
			window.addEventListener( 'scroll', onScroll, { passive: true } );
			onScroll();
		}

		var autoRun = ( drive === 'continuous' && ! reduce );
		if ( ! ( autoRun || allowDrag || drive === 'scroll' ) ) { return; }
		var last = 0;
		var loop = function ( tm ) {
			if ( ! last ) { last = tm; }
			var dt = ( tm - last ) / 1000; last = tm;
			if ( ! dragging ) {
				if ( autoRun ) { advance( dt ); }
				if ( momentum && Math.abs( vel ) > 0.0005 ) { pos += vel; vel *= 0.92; }
				else if ( drive === 'scroll' && scrollY !== null ) { pos = snap ? Math.round( scrollY ) + ( scrollY - Math.round( scrollY ) ) : scrollY; }
			}
			applyReel();
			requestAnimationFrame( loop );
		};
		requestAnimationFrame( loop );
	}

	/* Wheel Carousel — cards fanned on a big Ferris-style wheel whose hub sits below the frame, so only
	 * the top arc shows. Each card is rotated tangent to the wheel (upright at 12 o'clock = the focus,
	 * tilting away toward the sides) and the wheel steps around, card by card. The step uses a spring so
	 * Overshoot lets it sail past and settle, Anticipation winds it back before it goes, and Hold pauses
	 * on each card. Wheel Size sets the radius, Card Size the cards. A flat (2D) wheel — no depth. */
	function initWheel( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var wheel = el.querySelector( '.tdg__wheel' );
		if ( ! stage || ! wheel ) { return; }
		var cards = Array.prototype.slice.call( wheel.querySelectorAll( '.tdg__card' ) );
		var N = cards.length;
		if ( ! N ) { return; }

		var drive = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed = num( el, 'data-tdg-speed', 20 );
		var dir   = num( el, 'data-tdg-dir', 1 );                 // +1 clockwise, -1 counter-clockwise
		var cardPct = clamp( num( el, 'data-tdg-card', 40 ), 6, 90 ) / 100;
		var wheelPct = clamp( num( el, 'data-tdg-wheel', 70 ), 20, 160 ) / 100;
		var antic = clamp( num( el, 'data-tdg-antic', 0 ) / 100, 0, 1 );
		var over  = clamp( num( el, 'data-tdg-over', 0 ) / 100, 0, 1 );
		var hold  = clamp( num( el, 'data-tdg-hold', 0 ) / 100, 0, 1 );
		var Rw = 0, cx = 0, cy = 0, dPhi = 0, cardW = 0, cardH = 0;
		var C = 0, vel = 0, target = 0, stepTimer = 0;
		var HALF = 1.45;                                          // visible half-arc (radians) from the top

		function layout() {
			var W = stage.clientWidth || el.clientWidth || 1;
			var H = stage.clientHeight || el.clientHeight || 1;
			Rw = Math.min( W, H ) * wheelPct;
			cardW = Math.max( 30, W * cardPct );
			cardH = ( cards[ 0 ] && cards[ 0 ].offsetHeight ) ? cards[ 0 ].offsetHeight : cardW;
			cx = W / 2;
			cy = H * 0.4 + Rw;                                     // hub below the frame → only the top arc shows
			dPhi = 2 * Math.PI / N;                                // evenly around the wheel (Card Size sets the overlap)
			cards.forEach( function ( c ) { c.style.width = cardW + 'px'; c.style.marginLeft = ( -cardW / 2 ) + 'px'; } );
			cards.forEach( function ( c ) { c.style.marginTop = ( -( c.offsetHeight || cardW ) / 2 ) + 'px'; } );
		}

		function applyWheel() {
			wheel.style.transform = '';
			var TOP = -Math.PI / 2;
			for ( var i = 0; i < N; i++ ) {
				var c = cards[ i ];
				var phi = TOP + ( i - C ) * dPhi;
				phi = ( ( phi - TOP + Math.PI ) % ( 2 * Math.PI ) + 2 * Math.PI ) % ( 2 * Math.PI ) - Math.PI + TOP; // wrap around the hub
				var d = phi - TOP;                                 // signed distance from the top (radians)
				var x = cx + Rw * Math.cos( phi ) - ( stage.clientWidth || 1 ) / 2;
				var y = cy + Rw * Math.sin( phi ) - ( stage.clientHeight || 1 ) / 2;
				var nd = Math.abs( d ) / HALF;
				var sc = Math.max( 0.5, 1 - nd * 0.16 );           // gentle shrink toward the rim
				c.style.transform = 'translate3d(' + x.toFixed( 1 ) + 'px,' + y.toFixed( 1 ) + 'px,0) rotate(' + ( d * 180 / Math.PI ).toFixed( 1 ) + 'deg) scale(' + sc.toFixed( 3 ) + ')';
				c.style.opacity = ( Math.abs( d ) > HALF ? Math.max( 0, ( HALF + 0.25 - Math.abs( d ) ) / 0.25 ) : 1 ).toFixed( 3 );
				c.style.zIndex = String( 1000 - Math.round( Math.abs( d ) * 200 ) );
			}
		}

		layout();
		applyWheel();
		window.addEventListener( 'resize', function () { layout(); applyWheel(); }, { passive: true } );
		var hoverF = hoverFactor( el );
		var allowDrag = num( el, 'data-tdg-allowdrag', 1 );
		var momentum = num( el, 'data-tdg-momentum', 1 );
		var dragging = false, pc = 0, scrollY = null;
		var stepInterval = Math.max( 0.55, speed / 10 ) * ( 1 + hold * 2.2 ); // seconds per card (Hold lengthens the dwell)
		var K = 95, zeta = clamp( 1 - over * 0.78, 0.22, 1 );      // spring: Overshoot lowers the damping
		var damp = 2 * Math.sqrt( K ) * zeta;
		var advance = function ( dt ) {
			stepTimer += dt * hoverF();
			if ( stepTimer >= stepInterval ) { stepTimer = 0; target += dir; if ( antic > 0 ) { vel -= dir * antic * 7; } } // Anticipation: wind back
			var a = K * ( target - C ) - damp * vel;
			vel += a * dt; C += vel * dt;
		};

		if ( allowDrag ) {
			el.style.cursor = 'grab';
			var down = function ( x, y ) { dragging = true; pc = x; };
			var move = function ( x, y ) { if ( ! dragging ) { return; } var dx = x - pc; pc = x; C -= dx / Math.max( 1, Rw * dPhi ); };
			var up = function () { dragging = false; target = Math.round( C ); };
			attachDrag( el, down, move, up );
		}

		if ( drive === 'scroll' ) {
			var onScroll = function () { scrollY = dir * scrollProgress( el, stage ) * N; };
			window.addEventListener( 'scroll', onScroll, { passive: true } );
			onScroll();
		}

		var autoRun = ( drive === 'continuous' && ! reduce );
		if ( ! ( autoRun || allowDrag || drive === 'scroll' ) ) { return; }
		var last = 0;
		var loop = function ( tm ) {
			if ( ! last ) { last = tm; }
			var dt = Math.min( 0.05, ( tm - last ) / 1000 ); last = tm;
			if ( ! dragging ) {
				if ( autoRun ) { advance( dt ); }
				else if ( drive === 'scroll' && scrollY !== null ) { var a2 = K * ( scrollY - C ) - damp * vel; vel += a2 * dt; C += vel * dt; }
			}
			applyWheel();
			requestAnimationFrame( loop );
		};
		requestAnimationFrame( loop );
	}

	/* Flow carousels — one runtime for two center-focus carousels that both slide horizontally OR
	 * vertically (data-tdg-axis):
	 *   • Carousel Flow (mode 'carousel') — flat: the centre card is full size, the side cards scale
	 *     down (Side Card Scale) and fade. No tilt.
	 *   • Cover Flow (mode 'cover') — the classic album flow: the centre card faces you, the immediate
	 *     neighbours tilt to Side Tilt and recede, and further cards stack compressed behind them.
	 * Both flow continuously or, with Stop at Centre, snap each card to the middle with a springy dwell. */
	function initFlow( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var flow  = el.querySelector( '.tdg__flow' );
		if ( ! stage || ! flow ) { return; }
		var cards = Array.prototype.slice.call( flow.querySelectorAll( '.tdg__card' ) );
		var N = cards.length;
		if ( ! N ) { return; }

		var mode  = ( el.getAttribute( 'data-tdg-flow' ) === 'cover' ) ? 'cover' : 'carousel';
		var vert  = ( el.getAttribute( 'data-tdg-axis' ) === 'v' );
		var drive = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed = num( el, 'data-tdg-speed', 20 );
		var dir   = num( el, 'data-tdg-dir', 1 );
		var snap  = ( el.getAttribute( 'data-tdg-snap' ) === 'yes' );
		var cardPct   = clamp( num( el, 'data-tdg-card', mode === 'cover' ? 46 : 50 ), 6, 90 ) / 100;
		var gap       = clamp( num( el, 'data-tdg-gap', mode === 'cover' ? 4 : 5 ), 0, 40 ) / 100;
		var sideScale = clamp( num( el, 'data-tdg-sidescale', 82 ), 20, 100 ) / 100; // carousel
		var sideTilt  = clamp( num( el, 'data-tdg-tilt', 40 ), 0, 90 );              // cover
		var cardW = 0, cardH = 0, pos = 0, vel = 0, target = 0, dwellT = 0;

		function layout() {
			var W = stage.clientWidth || el.clientWidth || 1;
			cardW = Math.max( 30, W * cardPct );
			cardH = ( cards[ 0 ] && cards[ 0 ].offsetHeight ) ? cards[ 0 ].offsetHeight : cardW;
			stage.style.perspective = ( W * 1.4 ) + 'px';
			cards.forEach( function ( c ) { c.style.width = cardW + 'px'; c.style.marginLeft = ( -cardW / 2 ) + 'px'; } );
			cards.forEach( function ( c ) { c.style.marginTop = ( -( c.offsetHeight || cardW ) / 2 ) + 'px'; } );
		}

		function applyFlow() {
			flow.style.transform = '';
			var span = vert ? cardH : cardW;
			for ( var i = 0; i < N; i++ ) {
				var c = cards[ i ];
				var d = ( ( i - pos ) % N + N ) % N; if ( d > N / 2 ) { d -= N; } // wrap into [-N/2, N/2)
				var ad = Math.abs( d );
				var axisPos, rot = 0, z = 0, sc = 1, op = 1;
				if ( mode === 'cover' ) {
					var cl = Math.max( -1, Math.min( 1, d ) ), ext = d - cl;
					axisPos = cl * span * ( 0.6 + gap ) + ext * span * 0.15;
					rot = -cl * sideTilt;                            // immediate neighbours tilt to ±Side Tilt
					z = -( Math.abs( cl ) * span * 0.55 + Math.abs( ext ) * span * 0.16 );
					op = ad > 3 ? Math.max( 0, ( 3.6 - ad ) / 0.6 ) : 1;
				} else {
					axisPos = d * span * ( 0.94 + gap );             // uniform slots; sides slide off-frame
					sc = 1 - ( 1 - sideScale ) * Math.min( ad, 1 );  // centre full, sides scaled
					op = Math.max( 0, 1 - 0.5 * Math.min( ad, 1 ) - ( ad > 1 ? 0.28 * ( ad - 1 ) : 0 ) );
					z = -Math.min( ad, 1 ) * span * 0.04;
				}
				var pre = vert ? ( 'translate3d(0,' + axisPos.toFixed( 1 ) + 'px,' + z.toFixed( 1 ) + 'px)' )
				               : ( 'translate3d(' + axisPos.toFixed( 1 ) + 'px,0,' + z.toFixed( 1 ) + 'px)' );
				var rt = ( mode === 'cover' && rot ) ? ( vert ? ( ' rotateX(' + ( -rot ).toFixed( 1 ) + 'deg)' ) : ( ' rotateY(' + rot.toFixed( 1 ) + 'deg)' ) ) : '';
				c.style.transform = pre + rt + ' scale(' + sc.toFixed( 3 ) + ')';
				c.style.opacity = op.toFixed( 3 );
				c.style.zIndex = String( 1000 - Math.round( ad * 100 ) );
			}
		}

		layout();
		applyFlow();
		window.addEventListener( 'resize', function () { layout(); applyFlow(); }, { passive: true } );
		var hoverF = hoverFactor( el );
		var allowDrag = num( el, 'data-tdg-allowdrag', 1 );
		var momentum = num( el, 'data-tdg-momentum', 1 );
		var dragging = false, pcoord = 0, scrollY = null;
		var stepInterval = Math.max( 0.6, speed / 8 );
		var K = 90, damp = 2 * Math.sqrt( 90 ) * 0.95;
		var advance = function ( dt ) {
			if ( snap ) {
				dwellT += dt * hoverF();
				if ( dwellT >= stepInterval ) { dwellT = 0; target += dir; }
				var a = K * ( target - pos ) - damp * vel; vel += a * dt; pos += vel * dt;
			} else {
				pos += dir * ( N / Math.max( 1, speed ) ) * dt * hoverF();
			}
		};

		if ( allowDrag ) {
			el.style.cursor = 'grab';
			var down = function ( x, y ) { dragging = true; pcoord = vert ? y : x; vel = 0; el.style.cursor = 'grabbing'; };
			var move = function ( x, y ) { if ( ! dragging ) { return; } var cur = vert ? y : x; var delta = cur - pcoord; pcoord = cur; var dd = -delta / Math.max( 1, ( vert ? cardH : cardW ) * 0.9 ); pos += dd; vel = dd; };
			var up = function () { dragging = false; el.style.cursor = 'grab'; if ( snap ) { target = Math.round( pos ); } };
			attachDrag( el, down, move, up );
		}

		if ( drive === 'scroll' ) {
			var onScroll = function () { scrollY = dir * scrollProgress( el, stage ) * N; };
			window.addEventListener( 'scroll', onScroll, { passive: true } );
			onScroll();
		}

		var autoRun = ( drive === 'continuous' && ! reduce );
		if ( ! ( autoRun || allowDrag || drive === 'scroll' ) ) { return; }
		var last = 0;
		var loop = function ( tm ) {
			if ( ! last ) { last = tm; }
			var dt = Math.min( 0.05, ( tm - last ) / 1000 ); last = tm;
			if ( ! dragging ) {
				if ( autoRun ) { advance( dt ); }
				if ( momentum && Math.abs( vel ) > 0.0005 && ! snap ) { pos += vel; vel *= 0.9; }
				else if ( drive === 'scroll' && scrollY !== null ) { pos = scrollY; }
			}
			applyFlow();
			requestAnimationFrame( loop );
		};
		requestAnimationFrame( loop );
	}

	/* Card Stack — a deck of image cards. The top card is featured; the rest peek behind it (offset up,
	 * shrunk) forming a stack. A continuous "position" (0 = card 0 on top … N-1 = last on top) advances
	 * the deck: as position passes a card it PEELS OFF (translates out the exit edge + fades) while the
	 * next rises to the top. Driven by a parent Scroll Story stage's progress (scrubs with the scene),
	 * else the gallery's own travel; or an auto dwell timer. Content stays live, editable cards. */
	function initCardStack( el ) {
		if ( el.__tdgInit ) { return; } el.__tdgInit = true;
		var cards = el.querySelectorAll( '.tdg__deck > .tdg__card' );
		var N = cards.length;
		if ( ! N ) { return; }
		var mode      = el.getAttribute( 'data-tdg-cycle' ) || 'scroll';
		var dwell     = clamp( num( el, 'data-tdg-dwell', 2.5 ), 1, 12 ) * 1000;
		var exit      = el.getAttribute( 'data-tdg-exit' ) || 'down';
		var behind    = clamp( num( el, 'data-tdg-behind', 3 ), 1, 6 );
		var offset    = clamp( num( el, 'data-tdg-offset', 5 ), 0, 14 );
		var scaleStep = clamp( num( el, 'data-tdg-scale', 6 ), 0, 18 ) / 100;
		var stage     = el.querySelector( '.tdg__stage' );
		var ev = ( exit === 'up' ) ? { x: 0, y: -1, r: 0 } : ( exit === 'left' ) ? { x: -1, y: 0, r: -1 }
			: ( exit === 'right' ) ? { x: 1, y: 0, r: 1 } : { x: 0, y: 1, r: 0 };

		function place( card, d ) {
			var tf, op, z;
			if ( d >= 0 ) {
				// on top (d≈0) or waiting BEHIND: nudge up + shrink per depth; only `behind` peek out.
				var dd = Math.min( d, behind + 1 );
				var sc = Math.max( 0.4, 1 - dd * scaleStep );
				tf = 'translate3d(0,' + ( -dd * offset ).toFixed(2) + '%,0) scale(' + sc.toFixed(3) + ')';
				op = d > behind ? 0 : Math.max( 0, 1 - dd * 0.14 );
				z  = 1000 - Math.round( d * 20 );
			} else {
				// already PASSED — peel off the exit edge, fade, sit above the new top while leaving.
				var e = -d, k = Math.min( 1.25, e );
				tf = 'translate3d(' + ( ev.x * k * 120 ).toFixed(1) + '%,' + ( ev.y * k * 120 ).toFixed(1) + '%,0) rotate(' + ( ev.r * -7 * k ).toFixed(1) + 'deg) scale(' + ( 1 + k * 0.06 ).toFixed(3) + ')';
				op = Math.max( 0, 1 - e * 1.5 );
				z  = 1000 + Math.round( e * 8 );
			}
			card.style.transform = tf; card.style.opacity = op; card.style.zIndex = z;
		}
		function render( pos ) { for ( var i = 0; i < N; i++ ) { place( cards[ i ], i - pos ); } }
		render( 0 );
		if ( reduce || mode === 'off' || N < 2 ) { return; }

		if ( mode === 'scroll' ) {
			// progress from a parent Scroll Story stage (remapped to a ranged-persist slice if present),
			// else the gallery's own travel through the viewport — matches the other scroll designs.
			var storyEl = null, pScene = null;
			var progOf = function () {
				if ( ! storyEl && el.closest ) { storyEl = el.closest( '.upw-story--stage' ); }
				if ( storyEl && typeof storyEl.__storyProgress === 'number' ) {
					var pr = storyEl.__storyProgress;
					if ( pScene === null ) { pScene = ( el.closest && el.closest( '.upw-story-scene' ) ) || false; }
					if ( pScene && storyEl.__storyBeats ) {
						var from, to;
						if ( typeof pScene.__pFrom === 'number' ) { from = pScene.__pFrom; to = pScene.__pTo; }
						else if ( typeof pScene.__beatIndex === 'number' ) { from = pScene.__beatIndex; to = pScene.__beatIndex; }
						if ( from !== undefined ) { var lo = from / storyEl.__storyBeats, hi = ( to + 1 ) / storyEl.__storyBeats; if ( hi > lo ) { pr = Math.max( 0, Math.min( 1, ( pr - lo ) / ( hi - lo ) ) ); } }
					}
					return pr;
				}
				return scrollProgress( el, stage );
			};
			var raf = 0, inView = true;
			var loop = function () { if ( ! inView ) { raf = 0; return; } render( progOf() * ( N - 1 ) ); raf = requestAnimationFrame( loop ); };
			if ( 'IntersectionObserver' in window ) {
				new IntersectionObserver( function ( es ) { inView = es[ 0 ].isIntersecting; if ( inView && ! raf ) { raf = requestAnimationFrame( loop ); } }, { threshold: 0 } ).observe( storyEl || el );
			} else { raf = requestAnimationFrame( loop ); }
			return;
		}

		// auto — ease a continuous position toward an advancing integer target; on wrap, re-stack at 0.
		var pos = 0, target = 0, vis = true, araf = 0;
		var frame = function () { pos += ( target - pos ) * 0.12; render( pos ); araf = requestAnimationFrame( frame ); };
		var timer = setInterval( function () {
			if ( ! vis ) { return; }
			if ( target >= N - 1 ) { target = 0; pos = 0; } else { target += 1; }
		}, dwell );
		if ( 'IntersectionObserver' in window ) {
			new IntersectionObserver( function ( es ) { vis = es[ 0 ].isIntersecting; }, { threshold: 0.2 } ).observe( el );
		}
		araf = requestAnimationFrame( frame );
		void timer;
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
		els = document.querySelectorAll( '.tdg--sphere-cascade' );
		for ( i = 0; i < els.length; i++ ) { initCascade( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--totem-wall' );
		for ( i = 0; i < els.length; i++ ) { initTotem( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--parallax-totem' );
		for ( i = 0; i < els.length; i++ ) { initParallax( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--card-tunnel' );
		for ( i = 0; i < els.length; i++ ) { initTunnel( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--spiral-stream' );
		for ( i = 0; i < els.length; i++ ) { initSpiral( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--depth-stack' );
		for ( i = 0; i < els.length; i++ ) { initDepthStack( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--card-reel' );
		for ( i = 0; i < els.length; i++ ) { initReel( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--film-strip' );
		for ( i = 0; i < els.length; i++ ) { initReel( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--wheel-carousel' );
		for ( i = 0; i < els.length; i++ ) { initWheel( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--carousel-flow, .tdg--cover-flow' );
		for ( i = 0; i < els.length; i++ ) { initFlow( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--photo-scatter' );
		for ( i = 0; i < els.length; i++ ) { initScatter( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--device-cycler' );
		for ( i = 0; i < els.length; i++ ) { initDeviceCycler( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--card-stack' );
		for ( i = 0; i < els.length; i++ ) { initCardStack( els[ i ] ); }
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
		else if ( el.classList.contains( 'tdg--sphere-cascade' ) ) { initCascade( el ); }
		else if ( el.classList.contains( 'tdg--totem-wall' ) ) { initTotem( el ); }
		else if ( el.classList.contains( 'tdg--parallax-totem' ) ) { initParallax( el ); }
		else if ( el.classList.contains( 'tdg--card-tunnel' ) ) { initTunnel( el ); }
		else if ( el.classList.contains( 'tdg--spiral-stream' ) ) { initSpiral( el ); }
		else if ( el.classList.contains( 'tdg--depth-stack' ) ) { initDepthStack( el ); }
		else if ( el.classList.contains( 'tdg--card-reel' ) ) { initReel( el ); }
		else if ( el.classList.contains( 'tdg--film-strip' ) ) { initReel( el ); }
		else if ( el.classList.contains( 'tdg--wheel-carousel' ) ) { initWheel( el ); }
		else if ( el.classList.contains( 'tdg--carousel-flow' ) || el.classList.contains( 'tdg--cover-flow' ) ) { initFlow( el ); }
		else if ( el.classList.contains( 'tdg--photo-scatter' ) ) { initScatter( el ); }
		else if ( el.classList.contains( 'tdg--device-cycler' ) ) { initDeviceCycler( el ); }
		else if ( el.classList.contains( 'tdg--card-stack' ) ) { initCardStack( el ); }
	};
})();
