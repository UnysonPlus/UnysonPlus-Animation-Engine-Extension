/**
 * Motion Gallery — Carousel Ring driver.
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

	// Registry for child-theme-registered Motion Gallery designs. A theme calls
	// window.upwGallery3d.registerDesign('my_design', build) where build(ctx) drives one instance.
	// This is the JS half of the PHP upw_gallery_3d_design_* filters — the pluggability the built-in
	// designs don't need (they're compiled in), so a new gallery design can ship from a child theme
	// (the gap → fallback → report loop: a motion the engine can't do gets a scoped child-theme design).
	var CUSTOM = {};
	function dashed( key ) { return String( key ).replace( /_/g, '-' ); }
	var _raf = window.requestAnimationFrame.bind( window );

	// Live Editor: the page-builder renders the front end inside an editing iframe (?fw-live-editor-frame=1)
	// and tracks the DOM to place its element-selection overlay. A gallery's per-frame style mutations flood
	// that tracking so NO element can be selected. So in the editor we let the scene settle for a moment, then
	// FREEZE the rAF loops — one static snapshot, no more churn. (Front end + admin preview are unaffected.)
	var IN_EDITOR = false;
	try { IN_EDITOR = /[?&]fw-live-editor(-frame)?=/.test( window.location.search || '' ); } catch ( e ) {}
	var EDITOR_FRAMES = 0, EDITOR_MAX = 80; // ~1.3s of frames to settle, then freeze
	function requestAnimationFrame( fn ) {
		if ( IN_EDITOR && EDITOR_FRAMES > EDITOR_MAX ) { return 0; } // frozen — stop re-scheduling the loop
		var g = GEN;
		return _raf( function ( t ) { if ( g === GEN ) { if ( IN_EDITOR ) { EDITOR_FRAMES++; } fn( t ); } } );
	}

	function num( el, attr, dflt ) { var v = parseFloat( el.getAttribute( attr ) ); return isNaN( v ) ? dflt : v; }
	function clamp( v, a, b ) { return v < a ? a : ( v > b ? b : v ); }

	// Shared drag wiring (mouse + touch) — extracted from the 5 designs that each repeated it verbatim.
	// Passes BOTH (clientX, clientY) to the design's down/move callbacks; each uses the coordinate it
	// needs (X for the horizontal designs, Y for the vertical cascade, both for the ring's hit-test).
	function attachDrag( el, down, move, up ) {
		if ( IN_EDITOR ) { return; } // no drag in the Live Editor — its mousedown/preventDefault would block element selection
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
		// Chained gallery: the chain master drives its slice of the sequence and sets `__tdgChainProgress`
		// (0..1) each frame. Return that so the design's own loop AND the entrance reveal scrub with the
		// chain instead of the element's own viewport position — this is what makes chaining design-agnostic.
		if ( el.__tdgChainProgress != null ) { return clamp( el.__tdgChainProgress, 0, 1 ); }
		// Live Editor: scroll-driven galleries would sit at progress 0 (cards hidden by the entrance) and
		// look empty. Pin a representative mid-frame so the cards are revealed and visible while editing.
		if ( IN_EDITOR ) { return 0.4; }
		var r = el.getBoundingClientRect();
		var vh = window.innerHeight || 1;
		if ( el.classList.contains( 'tdg--pinned' ) ) {
			var sh = ( stage && stage.offsetHeight ) || 1;
			var stick = Math.max( 0, ( vh - sh ) / 2 );
			return clamp( ( stick - r.top ) / Math.max( 1, r.height - sh ), 0, 1 );
		}
		return clamp( 1 - ( r.top + r.height / 2 ) / ( vh + r.height ), 0, 1 );
	}

	// ---- Built-in sequential Entrance / scroll choreography -------------------
	// Reveal the cards one by one as the gallery scrolls in — on EVERY design. The design owns each
	// .tdg__card transform; we pose the inner content layer (.tdg__inner), which the layout never
	// touches, so the reveal composes with the 3D motion instead of fighting it. Staggered by index.
	//
	// Two progress sources:
	//   • Pinned Scroll-scrub (data-tdg-drive=scroll + .tdg--pinned) → a full scroll CHOREOGRAPHY: the
	//     images fade in one by one over the FIRST slice of the pin, hold while the design scrubs (e.g.
	//     the ring spins its Rounds), then fade out one by one over the LAST slice. Driven by the pin.
	//   • Otherwise → a one-shot cascade as the gallery travels into the viewport (optional exit).
	function initReveal( el ) {
		var effect = el.getAttribute( 'data-tdg-entrance' );
		if ( ! effect || effect === 'none' || el.__tdgReveal ) { return; }
		el.__tdgReveal = true;

		var cards = el.querySelectorAll( '.tdg__card' ), layers = [], i;
		for ( i = 0; i < cards.length; i++ ) { var inr = cards[ i ].querySelector( '.tdg__inner' ); if ( inr ) { layers.push( inr ); } }
		var n = layers.length;
		if ( ! n ) { return; }
		if ( reduce ) { for ( i = 0; i < n; i++ ) { layers[ i ].style.opacity = ''; } return; }

		// Pinned scroll-scrub → choreography mode (enter + exit tied to the pin, never tears down).
		var scrub  = el.getAttribute( 'data-tdg-drive' ) === 'scroll' && el.classList.contains( 'tdg--pinned' );
		var stage  = scrub ? el.querySelector( '.tdg__stage' ) : null;
		var ENTER  = 0.16, EXIT = 0.84;   // first/last 16% of the pin = the in / out sequences

		var stagger = num( el, 'data-tdg-entrance-stagger', 60 ) / 100;   // 0..0.9 spread of the cascade
		var dist    = num( el, 'data-tdg-entrance-distance', 40 );        // px the card rises from
		var doExit  = scrub || el.getAttribute( 'data-tdg-exit' ) === 'yes';
		var span    = Math.max( 0.25, 1 - stagger );                      // each card's own reveal length
		var step    = n > 1 ? stagger / ( n - 1 ) : 0;                    // per-index delay
		function ease( t ) { return t <= 0 ? 0 : t >= 1 ? 1 : 1 - Math.pow( 1 - t, 3 ); }

		var ticking = false, done = false;
		function apply() {
			var enterP, exitP;
			if ( scrub ) {
				var P = scrollProgress( el, stage );                          // 0..1 across the pinned scrub
				enterP = clamp( P / ENTER, 0, 1 );
				exitP  = clamp( ( P - EXIT ) / ( 1 - EXIT ), 0, 1 );
			} else {
				var r = el.getBoundingClientRect(), vh = window.innerHeight || 1;
				enterP = clamp( ( vh - r.top ) / ( vh * 0.6 ), 0, 1 );        // rises in over ~60% vh
				exitP  = doExit ? clamp( ( vh * 0.15 - r.bottom ) / ( vh * 0.5 ), 0, 1 ) : 0;
			}
			var allIn = true;
			for ( var k = 0; k < n; k++ ) {
				var inL  = ease( clamp( ( enterP - k * step ) / span, 0, 1 ) );
				var outL = doExit ? ease( clamp( ( exitP - ( n - 1 - k ) * step ) / span, 0, 1 ) ) : 0;
				var v = inL * ( 1 - outL ), e = 1 - v, L = layers[ k ]; // e: 0 shown → 1 hidden
				L.style.opacity = v.toFixed( 3 );
				// Each effect poses the inner layer differently; all compose with the design's 3D transform.
				if ( effect === 'scale' )      { L.style.transform = 'scale(' + ( 0.82 + 0.18 * v ).toFixed( 3 ) + ')';          L.style.filter = ''; }
				else if ( effect === 'blur' )  { L.style.transform = '';                                                          L.style.filter = e > 0.01 ? 'blur(' + ( e * 9 ).toFixed( 1 ) + 'px)' : ''; }
				else if ( effect === 'slide' ) { L.style.transform = 'translateX(' + ( e * dist ).toFixed( 1 ) + 'px)';           L.style.filter = ''; }
				else if ( effect === 'flip' )  { L.style.transform = 'perspective(720px) rotateY(' + ( e * 70 ).toFixed( 1 ) + 'deg)'; L.style.filter = ''; }
				else                           { L.style.transform = 'translateY(' + ( e * dist ).toFixed( 1 ) + 'px)';           L.style.filter = ''; } // rise
				if ( v < 0.999 ) { allIn = false; }
			}
			// One-shot cascade with nothing left to watch → stop listening. Scrub/exit keep running.
			if ( allIn && ! doExit ) { teardown(); }
		}
		function onScroll() { if ( ticking || done ) { return; } ticking = true; requestAnimationFrame( function () { ticking = false; apply(); } ); }
		function teardown() { done = true; window.removeEventListener( 'scroll', onScroll ); window.removeEventListener( 'resize', onScroll ); }

		window.addEventListener( 'scroll', onScroll, { passive: true } );
		window.addEventListener( 'resize', onScroll, { passive: true } );
		apply();
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
		var sway     = num( el, 'data-tdg-sway', 0 );     // +/- degrees of vertical rock as the ring spins
		var opening  = num( el, 'data-tdg-opening', 55 ); // how far you look INTO the ring (0 edge-on .. 100 open)
		var ringMul  = num( el, 'data-tdg-ring', 80 ) / 100;
		var spacing  = num( el, 'data-tdg-spacing', 100 ) / 100;
		var perspVal = num( el, 'data-tdg-persp', 40 );
		var backFade = clamp( num( el, 'data-tdg-backfade', 65 ) / 100, 0, 1 );
		// Card Size is % of the stage width, but scaled ×0.5 so the numbers line up with the reference
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
			// perspective (closer camera, bigger px→smaller distance), matching the reference control.
			var pDist = W * Math.max( 0.35, Math.min( 1.25, 1.35 - perspVal * 0.013 ) );
			stage.style.perspective = pDist + 'px';
			// Eye sits below the ring centre so the near cards render lower with margin beneath them and
			// the whole loop lifts slightly above centre — the reference framing.
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
			// Vertical Sway: rock the ring up/down as it spins (two rocks per revolution) — turns a flat
			// carousel into an orbiting "product" showcase seen from shifting heights. 0 = off.
			var rx = sway ? ( tilt + sway * Math.sin( angle * Math.PI / 90 ) ) : tilt;
			ring.style.transform = 'scaleY(' + openScale + ') rotateZ(' + roll + 'deg) rotateX(' + rx.toFixed( 2 ) + 'deg) rotateY(' + angle + 'deg)';
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
			var rounds = num( el, 'data-tdg-rounds', 1 );   // full turns across the pinned scrub
			var onScroll = function () { scrollAngle = dir * scrollProgress( el, stage ) * 360 * rounds; };
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
				else if ( drive === 'scroll' && scrollAngle !== null ) { angle += ( scrollAngle - angle ) * 0.14; }  // scroll drives when idle (eased glide)
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
			var onScroll = function () { scrollBase = dir * scrollProgress( el, stage ) * span * 2 * num( el, 'data-tdg-rounds', 1 ); };
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
				else if ( drive === 'scroll' && scrollBase !== null ) { base += ( scrollBase - base ) * 0.14; }
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
			var rounds = num( el, 'data-tdg-rounds', 1 );   // full turns across the pinned scrub
			var onScroll = function () { scrollAngle = dir * scrollProgress( el, stage ) * 360 * rounds; };
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
				else if ( drive === 'scroll' && scrollAngle !== null ) { bandData.forEach( function ( bd ) { bd.angle += ( scrollAngle - bd.angle ) * 0.14; } ); }
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
			var rounds = num( el, 'data-tdg-rounds', 1 );   // full turns across the pinned scrub
			var onScroll = function () { scrollAngle = dir * scrollProgress( el, stage ) * 360 * rounds; };
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
				else if ( drive === 'scroll' && scrollAngle !== null ) { angle += ( scrollAngle - angle ) * 0.14; }
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
					// Converge to a loose stack at the centre (a "collect into a pile" moment):
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
		// a "find your files -> your search results" moment. Content stays live, editable cards.
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

		// SCROLL-CYCLE mode (a cinematic pile): as the visitor scrolls, each SET of photos flies
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
						// Gather into a centred OVERLAPPING PILE (an "all your files" stack): converge to
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
		var device = el.querySelector( '.tdg__device' );
		var cur = -1;

		// Fit the device to the stage: a tall device (a 9:16 phone) sized purely by Device Size (width)
		// can be TALLER than the stage and get clipped top/bottom by the stage's overflow. Reset to the
		// width-based size, and if that overflows the stage height, scale the width down so it fits.
		function fit() {
			if ( ! device || ! stage ) { return; }
			device.style.width = '';
			var maxH = ( stage.clientHeight || 1 ) * 0.95;
			var dh = device.offsetHeight;
			if ( dh > maxH ) { device.style.width = ( device.offsetWidth * ( maxH / dh ) ) + 'px'; }
		}
		fit();
		window.addEventListener( 'resize', fit, { passive: true } );
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
			var onScroll = function () { scrollY = dir * scrollProgress( el, stage ) * latSpan * 2 * num( el, 'data-tdg-rounds', 1 ); };
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
				else if ( drive === 'scroll' && scrollY !== null ) { Y += ( scrollY - Y ) * 0.14; }
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
			// (the rim totems fall off the sides and fade). Matches the reference "Zoom" behaviour.
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
			// Tilt tips the wall; perspective then foreshortens it and drags the projected centre down, so
			// re-centre with a translateY that scales with the radius and the tilt (keeps it filling the stage).
			var comp = R * Math.sin( tilt * Math.PI / 180 );
			cascade.style.transform = 'translateY(' + comp.toFixed( 1 ) + 'px) rotateX(' + tilt + 'deg)';
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
			var onScroll = function () { scrollY = dir * scrollProgress( el, stage ) * latSpan * 2 * num( el, 'data-tdg-rounds', 1 ); };
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
				else if ( drive === 'scroll' && scrollY !== null ) { Y += ( scrollY - Y ) * 0.14; }
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
			var onScroll = function () { scrollY = dir * scrollProgress( el, stage ) * latSpan * 2 * num( el, 'data-tdg-rounds', 1 ); };
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
				else if ( drive === 'scroll' && scrollY !== null ) { Y += ( scrollY - Y ) * 0.14; }
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
		var spiral = num( el, 'data-tdg-spiral', 0 );            // degrees of corridor roll per full pass
		var square = num( el, 'data-tdg-square', 0 );            // 1 = equal-sided (square) cross-section
		var W = 0, H = 0, L = 0, step = 0, D = 0, Y = 0, vel = 0;

		function layout() {
			var SW = stage.clientWidth || el.clientWidth || 1;
			var SH = stage.clientHeight || el.clientHeight || 1;
			W = SW * 0.5 * tsize; H = SH * 0.5 * tsize;          // half tunnel width / height
			if ( square ) { var sq = Math.min( SW, SH ) * 0.5 * tsize; W = sq; H = sq; }
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
			// Spiral: roll the whole corridor as it flies (rotateZ grows with travel), turning the straight
			// tube into a helix. 0 = off.
			tunnel.style.transform = spiral ? ( 'rotateZ(' + ( spiral * ( Y / ( D || 1 ) ) ).toFixed( 2 ) + 'deg)' ) : '';
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
			var onScroll = function () { scrollY = scrollProgress( el, stage ) * D * 2 * num( el, 'data-tdg-rounds', 1 ); };
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
				else if ( drive === 'scroll' && scrollY !== null ) { Y += ( scrollY - Y ) * 0.14; }
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
			var onScroll = function () { scrollY = scrollProgress( el, stage ) * 2 * num( el, 'data-tdg-rounds', 1 ); };
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
				else if ( drive === 'scroll' && scrollY !== null ) { S += ( scrollY - S ) * 0.14; }
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
		var layoutMode = el.getAttribute( 'data-tdg-layout' ) || 'fan'; // fan | scatter | aisle | field

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
				if ( layoutMode === 'scatter' ) { ox = m.sx * spreadMag; oy = m.sy * spreadMag; }        // fixed random scatter
				else if ( layoutMode === 'field' ) {                                                     // a cloud you fly INTO: wide at the back, funnelling past you near the front
					var ff = 0.28 + Math.max( 0, d ) * 1.25;
					ox = m.sx * spreadMag * ff; oy = m.sy * spreadMag * ff;
				}
				else if ( layoutMode === 'aisle' ) {                                                     // an aisle: cards to both sides, camera dollies down the middle (Dolly Gallery)
					var side = ( i % 2 ) ? 1 : -1;
					ox = side * spreadMag * ( 0.62 + 0.38 * Math.abs( m.sx ) );
					oy = m.sy * spreadMag * 0.22;
				}
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
			var onScroll = function () { scrollY = scrollProgress( el, stage ) * ( N > 1 ? N - 1 : 1 ) * num( el, 'data-tdg-rounds', 1 ); };
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
				else if ( drive === 'scroll' && scrollY !== null ) { S += ( scrollY - S ) * 0.14; if ( Math.abs( scrollY - S ) < 0.0004 ) { S = scrollY; } } // ease toward the scroll target (glide, not frame-lock)
			}
			applyDepth();
			requestAnimationFrame( loop );
		};
		requestAnimationFrame( loop );
	}

	/* Tilt Deck (Isometric) — photos lie flat on an isometric ground plane in a grid and march
	 * diagonally across it; the tile crossing the focus point lifts off the plane, rotates flat to
	 * face the viewer and brightens, then settles back as the next one rises. Pure CSS 3D, one rAF
	 * loop. The view emits cols*rows cards (images cycle). An original UnysonPlus design. */
	function initTiltDeck( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var plane = el.querySelector( '.tdg__iso' );
		if ( ! stage || ! plane ) { return; }
		var cards = Array.prototype.slice.call( plane.querySelectorAll( '.tdg__card' ) );
		var N = cards.length;
		if ( ! N ) { return; }

		var drive   = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed   = num( el, 'data-tdg-speed', 12 );
		var dir     = num( el, 'data-tdg-dir', 1 );
		var cols    = Math.max( 1, Math.round( num( el, 'data-tdg-cols', 4 ) ) );
		var gap     = clamp( num( el, 'data-tdg-gap', 12 ), 0, 60 ) / 100;
		var tilt    = clamp( num( el, 'data-tdg-tilt', 58 ), 20, 75 );
		var lift    = clamp( num( el, 'data-tdg-lift', 60 ) / 100, 0, 1 );
		var glow    = clamp( num( el, 'data-tdg-glow', 135 ), 100, 220 ) / 100;
		var dFade   = clamp( num( el, 'data-tdg-dfade', 30 ) / 100, 0, 1 );
		var cardPct = clamp( num( el, 'data-tdg-card', 16 ), 4, 60 ) / 100;
		var SPIN = 45; // the isometric Z rotation of the ground plane

		var rows = Math.max( 1, Math.ceil( N / cols ) );
		var cardW = 0, cell = 0, W = 1;
		function layout() {
			W = stage.clientWidth || el.clientWidth || 1;
			cardW = Math.max( 20, W * cardPct );
			cell  = cardW * ( 1 + gap );
			stage.style.perspective = ( W * 1.6 ) + 'px';
			plane.style.transform = 'rotateX(' + tilt + 'deg) rotateZ(' + SPIN + 'deg)';
			cards.forEach( function ( c ) { c.style.width = cardW + 'px'; c.style.marginLeft = ( -cardW / 2 ) + 'px'; } );
			cards.forEach( function ( c ) { c.style.marginTop = ( -( c.offsetHeight || cardW ) / 2 ) + 'px'; } );
		}

		function applyFrame() {
			for ( var i = 0; i < N; i++ ) {
				var c = cards[ i ];
				var col = i % cols, row = Math.floor( i / cols );
				var pos = ( ( ( row - S * dir ) % rows ) + rows ) % rows;          // 0..rows (the march, wrapped)
				var py  = ( pos - ( rows - 1 ) / 2 ) * cell;                        // plane Y (depth across the grid)
				var px  = ( col - ( cols - 1 ) / 2 ) * cell;                        // plane X (fixed per column)
				var k   = Math.max( 0, 1 - Math.abs( py ) / ( cell * 1.15 ) );      // focus proximity (peak at plane centre)
				k = k * k * ( 3 - 2 * k );                                          // smoothstep
				var edge = Math.min( pos, rows - pos );
				var seam = clamp( edge / 0.7, 0, 1 );                               // fade the wrap seam so recycling is invisible
				var lz = lift * k * cardW * 0.95;                                   // lift off the plane toward the viewer
				var rx = -tilt * lift * k;                                          // counter the plane tilt …
				var rz = -SPIN * lift * k;                                          // … and spin, so the focus tile faces you
				var sc = 1 + lift * k * 0.16;
				c.style.transform = 'translate3d(' + px.toFixed( 1 ) + 'px,' + py.toFixed( 1 ) + 'px,' + lz.toFixed( 1 ) + 'px) rotateZ(' + rz.toFixed( 1 ) + 'deg) rotateX(' + rx.toFixed( 1 ) + 'deg) scale(' + sc.toFixed( 3 ) + ')';
				var back = clamp( pos / rows, 0, 1 );                               // 0 front … 1 back
				var bright = ( 1 - dFade * back ) * ( 1 + ( glow - 1 ) * k );
				c.style.filter = 'brightness(' + bright.toFixed( 3 ) + ')';
				c.style.opacity = seam.toFixed( 3 );
			}
		}

		var S = 0, vel = 0;
		layout();
		applyFrame();
		window.addEventListener( 'resize', function () { layout(); applyFrame(); }, { passive: true } );
		var hoverF = hoverFactor( el );
		var allowDrag = num( el, 'data-tdg-allowdrag', 1 );
		var momentum = num( el, 'data-tdg-momentum', 1 );
		var dragging = false, py0 = 0, scrollTarget = null;
		var advance = function ( dt ) { S += ( rows / Math.max( 1, speed ) ) * dt * hoverF(); };

		if ( allowDrag ) {
			el.style.cursor = 'grab';
			var down = function ( x, y ) { dragging = true; py0 = y; vel = 0; el.style.cursor = 'grabbing'; };
			var move = function ( x, y ) { if ( ! dragging ) { return; } var dy = y - py0; py0 = y; var dd = -dy * 0.006; S += dd; vel = dd; };
			var up = function () { dragging = false; el.style.cursor = 'grab'; };
			attachDrag( el, down, move, up );
		}

		if ( drive === 'scroll' ) {
			var onScroll = function () { scrollTarget = scrollProgress( el, stage ) * rows * num( el, 'data-tdg-rounds', 1 ); };
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
				if ( momentum && Math.abs( vel ) > 0.00002 ) { S += vel; vel *= 0.94; }
				else if ( drive === 'scroll' && scrollTarget !== null ) { S += ( scrollTarget - S ) * 0.14; if ( Math.abs( scrollTarget - S ) < 0.0004 ) { S = scrollTarget; } }
			}
			applyFrame();
			requestAnimationFrame( loop );
		};
		requestAnimationFrame( loop );
	}

	/* Iso Orbit (Isometric) — tiles sit on a ring lying flat on the isometric ground plane and revolve
	 * around its centre; the tile at the near point of the orbit lifts off the plane, rotates flat to
	 * face the viewer and brightens, settling back as it carries on round. Far side dims. Pure CSS 3D,
	 * one rAF loop. The view emits one tile per image. An original UnysonPlus design. */
	function initIsoOrbit( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var plane = el.querySelector( '.tdg__iso' );
		if ( ! stage || ! plane ) { return; }
		var cards = Array.prototype.slice.call( plane.querySelectorAll( '.tdg__card' ) );
		var N = cards.length;
		if ( ! N ) { return; }

		var drive   = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed   = num( el, 'data-tdg-speed', 18 );
		var dir     = num( el, 'data-tdg-dir', 1 );
		var radPct  = clamp( num( el, 'data-tdg-radius', 30 ), 8, 60 ) / 100;
		var tilt    = clamp( num( el, 'data-tdg-tilt', 58 ), 20, 75 );
		var lift    = clamp( num( el, 'data-tdg-lift', 70 ) / 100, 0, 1 );
		var glow    = clamp( num( el, 'data-tdg-glow', 140 ), 100, 220 ) / 100;
		var dFade   = clamp( num( el, 'data-tdg-dfade', 35 ) / 100, 0, 1 );
		var cardPct = clamp( num( el, 'data-tdg-card', 16 ), 4, 60 ) / 100;
		var SPIN = 45; // the isometric Z rotation of the ground plane

		var cardW = 0, R = 0, W = 1, TWO = Math.PI * 2;
		function layout() {
			W = stage.clientWidth || el.clientWidth || 1;
			cardW = Math.max( 20, W * cardPct );
			R = W * radPct;
			stage.style.perspective = ( W * 1.6 ) + 'px';
			plane.style.transform = 'rotateX(' + tilt + 'deg) rotateZ(' + SPIN + 'deg)';
			cards.forEach( function ( c ) { c.style.width = cardW + 'px'; c.style.marginLeft = ( -cardW / 2 ) + 'px'; } );
			cards.forEach( function ( c ) { c.style.marginTop = ( -( c.offsetHeight || cardW ) / 2 ) + 'px'; } );
		}

		function applyFrame() {
			for ( var i = 0; i < N; i++ ) {
				var c = cards[ i ];
				var theta = ( i / N ) * TWO + S * dir;                 // position on the ring (radians)
				var px = Math.cos( theta ) * R;
				var py = Math.sin( theta ) * R;                        // +py = the near arc (front of the orbit)
				var near = ( Math.sin( theta ) + 1 ) / 2;              // 0 far … 1 near
				var k = near * near * ( 3 - 2 * near );                // smoothstep — emphasise the near arc
				var lz = lift * k * cardW * 0.95;                      // lift off the plane at the front …
				var rx = -tilt * lift * k;                             // … and face the viewer
				var rz = -SPIN * lift * k;
				var sc = 1 + lift * k * 0.18;
				c.style.transform = 'translate3d(' + px.toFixed( 1 ) + 'px,' + py.toFixed( 1 ) + 'px,' + lz.toFixed( 1 ) + 'px) rotateZ(' + rz.toFixed( 1 ) + 'deg) rotateX(' + rx.toFixed( 1 ) + 'deg) scale(' + sc.toFixed( 3 ) + ')';
				var bright = ( 1 - dFade * ( 1 - near ) ) * ( 1 + ( glow - 1 ) * k );
				c.style.filter = 'brightness(' + bright.toFixed( 3 ) + ')';
			}
		}

		var S = 0, vel = 0;
		layout();
		applyFrame();
		window.addEventListener( 'resize', function () { layout(); applyFrame(); }, { passive: true } );
		var hoverF = hoverFactor( el );
		var allowDrag = num( el, 'data-tdg-allowdrag', 1 );
		var momentum = num( el, 'data-tdg-momentum', 1 );
		var dragging = false, px0 = 0, scrollTarget = null;
		var advance = function ( dt ) { S += ( TWO / Math.max( 1, speed ) ) * dt * hoverF(); };

		if ( allowDrag ) {
			el.style.cursor = 'grab';
			var down = function ( x, y ) { dragging = true; px0 = x; vel = 0; el.style.cursor = 'grabbing'; };
			var move = function ( x, y ) { if ( ! dragging ) { return; } var dx = x - px0; px0 = x; var dd = dx * 0.006; S += dd; vel = dd; };
			var up = function () { dragging = false; el.style.cursor = 'grab'; };
			attachDrag( el, down, move, up );
		}

		if ( drive === 'scroll' ) {
			var onScroll = function () { scrollTarget = scrollProgress( el, stage ) * TWO * num( el, 'data-tdg-rounds', 1 ); };
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
				if ( momentum && Math.abs( vel ) > 0.00002 ) { S += vel; vel *= 0.94; }
				else if ( drive === 'scroll' && scrollTarget !== null ) { S += ( scrollTarget - S ) * 0.14; if ( Math.abs( scrollTarget - S ) < 0.0004 ) { S = scrollTarget; } }
			}
			applyFrame();
			requestAnimationFrame( loop );
		};
		requestAnimationFrame( loop );
	}

	/* Iso Stack (Isometric) — photo slabs stacked into a tower rising off the isometric ground plane,
	 * streaming upward on a gentle spiral; the slab passing through the focus height lifts to face the
	 * viewer and brightens, then flattens again as it carries on up and recycles. Pure CSS 3D, one rAF
	 * loop. The view emits one slab per image (images cycle). An original UnysonPlus design. */
	function initIsoStack( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var plane = el.querySelector( '.tdg__iso' );
		if ( ! stage || ! plane ) { return; }
		var cards = Array.prototype.slice.call( plane.querySelectorAll( '.tdg__card' ) );
		var N = cards.length;
		if ( ! N ) { return; }

		var drive   = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed   = num( el, 'data-tdg-speed', 8 );
		var dir     = num( el, 'data-tdg-dir', 1 );
		var sgap    = clamp( num( el, 'data-tdg-gap', 40 ), 10, 80 ) / 100;
		var spiral  = clamp( num( el, 'data-tdg-spiral', 40 ), 0, 100 ) / 100;
		var tilt    = clamp( num( el, 'data-tdg-tilt', 58 ), 20, 75 );
		var lift    = clamp( num( el, 'data-tdg-lift', 70 ) / 100, 0, 1 );
		var glow    = clamp( num( el, 'data-tdg-glow', 140 ), 100, 220 ) / 100;
		var dFade   = clamp( num( el, 'data-tdg-dfade', 40 ) / 100, 0, 1 );
		var cardPct = clamp( num( el, 'data-tdg-card', 17 ), 4, 60 ) / 100;
		var SPIN = 45, TWO = Math.PI * 2, BAND = 0.26;

		var cardW = 0, towerH = 0, driftR = 0, W = 1;
		function layout() {
			W = stage.clientWidth || el.clientWidth || 1;
			cardW = Math.max( 20, W * cardPct );
			towerH = cardW * N * sgap;
			driftR = spiral * cardW * 0.6;
			stage.style.perspective = ( W * 1.6 ) + 'px';
			plane.style.transform = 'rotateX(' + tilt + 'deg) rotateZ(' + SPIN + 'deg)';
			cards.forEach( function ( c ) { c.style.width = cardW + 'px'; c.style.marginLeft = ( -cardW / 2 ) + 'px'; } );
			cards.forEach( function ( c ) { c.style.marginTop = ( -( c.offsetHeight || cardW ) / 2 ) + 'px'; } );
		}

		function applyFrame() {
			for ( var i = 0; i < N; i++ ) {
				var c = cards[ i ];
				var pos = ( ( ( i / N + S * dir ) % 1 ) + 1 ) % 1;     // 0 bottom … 1 top (looping)
				var ang = pos * TWO;                                   // one gentle revolution up the tower
				var px = Math.cos( ang ) * driftR;
				var py = Math.sin( ang ) * driftR;
				var z = ( pos - 0.5 ) * towerH;                        // height off the plane
				var k = Math.max( 0, 1 - Math.abs( pos - 0.5 ) / BAND );
				k = k * k * ( 3 - 2 * k );                             // smoothstep focus band at mid height
				var seam = clamp( Math.min( pos, 1 - pos ) / 0.08, 0, 1 ); // fade the top/bottom recycle seam
				var lz = z + lift * k * cardW * 0.6;
				var rx = -tilt * lift * k;
				var rz = -SPIN * lift * k;
				var sc = 1 + lift * k * 0.16;
				c.style.transform = 'translate3d(' + px.toFixed( 1 ) + 'px,' + py.toFixed( 1 ) + 'px,' + lz.toFixed( 1 ) + 'px) rotateZ(' + rz.toFixed( 1 ) + 'deg) rotateX(' + rx.toFixed( 1 ) + 'deg) scale(' + sc.toFixed( 3 ) + ')';
				var bright = ( 1 - dFade * ( 1 - pos ) ) * ( 1 + ( glow - 1 ) * k );
				c.style.filter = 'brightness(' + bright.toFixed( 3 ) + ')';
				c.style.opacity = seam.toFixed( 3 );
			}
		}

		var S = 0, vel = 0;
		layout();
		applyFrame();
		window.addEventListener( 'resize', function () { layout(); applyFrame(); }, { passive: true } );
		var hoverF = hoverFactor( el );
		var allowDrag = num( el, 'data-tdg-allowdrag', 1 );
		var momentum = num( el, 'data-tdg-momentum', 1 );
		var dragging = false, py0 = 0, scrollTarget = null;
		var advance = function ( dt ) { S += ( 1 / Math.max( 1, speed ) ) * dt * hoverF(); };

		if ( allowDrag ) {
			el.style.cursor = 'grab';
			var down = function ( x, y ) { dragging = true; py0 = y; vel = 0; el.style.cursor = 'grabbing'; };
			var move = function ( x, y ) { if ( ! dragging ) { return; } var dy = y - py0; py0 = y; var dd = -dy * 0.0018; S += dd; vel = dd; };
			var up = function () { dragging = false; el.style.cursor = 'grab'; };
			attachDrag( el, down, move, up );
		}

		if ( drive === 'scroll' ) {
			var onScroll = function () { scrollTarget = scrollProgress( el, stage ) * num( el, 'data-tdg-rounds', 1 ); };
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
				if ( momentum && Math.abs( vel ) > 0.00002 ) { S += vel; vel *= 0.94; }
				else if ( drive === 'scroll' && scrollTarget !== null ) { S += ( scrollTarget - S ) * 0.14; if ( Math.abs( scrollTarget - S ) < 0.0004 ) { S = scrollTarget; } }
			}
			applyFrame();
			requestAnimationFrame( loop );
		};
		requestAnimationFrame( loop );
	}

	/* Iso Cascade (Isometric) — photos descend a diagonal staircase across the isometric ground plane:
	 * high and far at the back, stepping down and toward the viewer. The tile reaching the front of the
	 * cascade lifts flat to face you and brightens, then recycles to the back. Pure CSS 3D, one rAF
	 * loop. The view emits one tile per image (images cycle). An original UnysonPlus design. */
	function initIsoCascade( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var plane = el.querySelector( '.tdg__iso' );
		if ( ! stage || ! plane ) { return; }
		var cards = Array.prototype.slice.call( plane.querySelectorAll( '.tdg__card' ) );
		var N = cards.length;
		if ( ! N ) { return; }

		var drive   = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed   = num( el, 'data-tdg-speed', 12 );
		var dir     = num( el, 'data-tdg-dir', 1 );
		var spread  = clamp( num( el, 'data-tdg-spread', 60 ), 20, 100 ) / 100;
		var rise    = clamp( num( el, 'data-tdg-rise', 50 ), 10, 100 ) / 100;
		var tilt    = clamp( num( el, 'data-tdg-tilt', 58 ), 20, 75 );
		var lift    = clamp( num( el, 'data-tdg-lift', 70 ) / 100, 0, 1 );
		var glow    = clamp( num( el, 'data-tdg-glow', 140 ), 100, 220 ) / 100;
		var dFade   = clamp( num( el, 'data-tdg-dfade', 45 ) / 100, 0, 1 );
		var cardPct = clamp( num( el, 'data-tdg-card', 17 ), 4, 60 ) / 100;
		var SPIN = 45, FOCUS = 0.78, BAND = 0.15;

		var cardW = 0, spanX = 0, spanY = 0, riseH = 0, W = 1;
		function layout() {
			W = stage.clientWidth || el.clientWidth || 1;
			cardW = Math.max( 20, W * cardPct );
			spanX = W * 1.10 * spread;
			spanY = W * 0.26 * spread;
			riseH = cardW * ( 0.6 + rise * 2.2 );
			stage.style.perspective = ( W * 1.6 ) + 'px';
			plane.style.transform = 'rotateX(' + tilt + 'deg) rotateZ(' + SPIN + 'deg)';
			cards.forEach( function ( c ) { c.style.width = cardW + 'px'; c.style.marginLeft = ( -cardW / 2 ) + 'px'; } );
			cards.forEach( function ( c ) { c.style.marginTop = ( -( c.offsetHeight || cardW ) / 2 ) + 'px'; } );
		}

		function applyFrame() {
			for ( var i = 0; i < N; i++ ) {
				var c = cards[ i ];
				var t = ( ( ( i / N + S * dir ) % 1 ) + 1 ) % 1;      // 0 back(high/far) … 1 front(low/near)
				var px = ( t - 0.5 ) * spanX;
				var py = ( t - 0.5 ) * spanY;
				var baseZ = ( 1 - t ) * riseH;                        // descends from the back down to the front
				var k = Math.max( 0, 1 - Math.abs( t - FOCUS ) / BAND );
				k = k * k * ( 3 - 2 * k );                            // smoothstep focus near the front of the cascade
				var lz = baseZ + lift * k * cardW * 0.7;
				var rx = -tilt * lift * k;
				var rz = -SPIN * lift * k;
				var sc = 1 + lift * k * 0.16;
				c.style.transform = 'translate3d(' + px.toFixed( 1 ) + 'px,' + py.toFixed( 1 ) + 'px,' + lz.toFixed( 1 ) + 'px) rotateZ(' + rz.toFixed( 1 ) + 'deg) rotateX(' + rx.toFixed( 1 ) + 'deg) scale(' + sc.toFixed( 3 ) + ')';
				var seam = clamp( Math.min( t, 1 - t ) / 0.07, 0, 1 ); // fade the back/front recycle seam
				var bright = ( 1 - dFade * ( 1 - t ) ) * ( 1 + ( glow - 1 ) * k );
				c.style.filter = 'brightness(' + bright.toFixed( 3 ) + ')';
				c.style.opacity = seam.toFixed( 3 );
			}
		}

		var S = 0, vel = 0;
		layout();
		applyFrame();
		window.addEventListener( 'resize', function () { layout(); applyFrame(); }, { passive: true } );
		var hoverF = hoverFactor( el );
		var allowDrag = num( el, 'data-tdg-allowdrag', 1 );
		var momentum = num( el, 'data-tdg-momentum', 1 );
		var dragging = false, py0 = 0, scrollTarget = null;
		var advance = function ( dt ) { S += ( 1 / Math.max( 1, speed ) ) * dt * hoverF(); };

		if ( allowDrag ) {
			el.style.cursor = 'grab';
			var down = function ( x, y ) { dragging = true; py0 = y; vel = 0; el.style.cursor = 'grabbing'; };
			var move = function ( x, y ) { if ( ! dragging ) { return; } var dy = y - py0; py0 = y; var dd = dy * 0.0018; S += dd; vel = dd; };
			var up = function () { dragging = false; el.style.cursor = 'grab'; };
			attachDrag( el, down, move, up );
		}

		if ( drive === 'scroll' ) {
			var onScroll = function () { scrollTarget = scrollProgress( el, stage ) * num( el, 'data-tdg-rounds', 1 ); };
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
				if ( momentum && Math.abs( vel ) > 0.00002 ) { S += vel; vel *= 0.94; }
				else if ( drive === 'scroll' && scrollTarget !== null ) { S += ( scrollTarget - S ) * 0.14; if ( Math.abs( scrollTarget - S ) < 0.0004 ) { S = scrollTarget; } }
			}
			applyFrame();
			requestAnimationFrame( loop );
		};
		requestAnimationFrame( loop );
	}

	/* Iso Fan (Isometric) — tiles spread along a flat arc (a fan) on the isometric ground plane and
	 * sweep across it; the tile passing the centre-front of the fan lifts flat to face the viewer and
	 * brightens, then settles back as it sweeps on and recycles at the far edge. Pure CSS 3D, one rAF
	 * loop. The view emits one tile per image (images cycle). An original UnysonPlus design. */
	function initIsoFan( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var plane = el.querySelector( '.tdg__iso' );
		if ( ! stage || ! plane ) { return; }
		var cards = Array.prototype.slice.call( plane.querySelectorAll( '.tdg__card' ) );
		var N = cards.length;
		if ( ! N ) { return; }

		var drive   = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed   = num( el, 'data-tdg-speed', 16 );
		var dir     = num( el, 'data-tdg-dir', 1 );
		var reach   = clamp( num( el, 'data-tdg-reach', 34 ), 10, 60 ) / 100;
		var arc     = clamp( num( el, 'data-tdg-arc', 130 ), 40, 175 ) * Math.PI / 180;
		var tilt    = clamp( num( el, 'data-tdg-tilt', 58 ), 20, 75 );
		var lift    = clamp( num( el, 'data-tdg-lift', 70 ) / 100, 0, 1 );
		var glow    = clamp( num( el, 'data-tdg-glow', 140 ), 100, 220 ) / 100;
		var dFade   = clamp( num( el, 'data-tdg-dfade', 40 ) / 100, 0, 1 );
		var cardPct = clamp( num( el, 'data-tdg-card', 16 ), 4, 60 ) / 100;
		var SPIN = 45, edgeNear = Math.cos( arc / 2 );

		var cardW = 0, R = 0, W = 1;
		function layout() {
			W = stage.clientWidth || el.clientWidth || 1;
			cardW = Math.max( 20, W * cardPct );
			R = W * reach;
			stage.style.perspective = ( W * 1.6 ) + 'px';
			plane.style.transform = 'rotateX(' + tilt + 'deg) rotateZ(' + SPIN + 'deg)';
			cards.forEach( function ( c ) { c.style.width = cardW + 'px'; c.style.marginLeft = ( -cardW / 2 ) + 'px'; } );
			cards.forEach( function ( c ) { c.style.marginTop = ( -( c.offsetHeight || cardW ) / 2 ) + 'px'; } );
		}

		function applyFrame() {
			for ( var i = 0; i < N; i++ ) {
				var c = cards[ i ];
				var t = ( ( ( i / N + S * dir ) % 1 ) + 1 ) % 1;      // 0 … 1 sweeping across the fan
				var a = ( t - 0.5 ) * arc;                            // angle across the arc
				var px = Math.sin( a ) * R;
				var py = Math.cos( a ) * R - R * 0.62;                 // arc curves in depth — centre near, edges recede
				var near = ( Math.cos( a ) - edgeNear ) / ( 1 - edgeNear + 0.0001 ); // 0 edge … 1 centre
				var k = Math.max( 0, 1 - Math.abs( a ) / ( arc * 0.16 ) );
				k = k * k * ( 3 - 2 * k );                            // smoothstep focus at the centre-front
				var lz = lift * k * cardW * 0.95;
				var rx = -tilt * lift * k;
				var rz = -SPIN * lift * k;
				var sc = 1 + lift * k * 0.18;
				c.style.transform = 'translate3d(' + px.toFixed( 1 ) + 'px,' + py.toFixed( 1 ) + 'px,' + lz.toFixed( 1 ) + 'px) rotateZ(' + rz.toFixed( 1 ) + 'deg) rotateX(' + rx.toFixed( 1 ) + 'deg) scale(' + sc.toFixed( 3 ) + ')';
				var seam = clamp( Math.min( t, 1 - t ) / 0.06, 0, 1 ); // fade the tiles at the fan's edges (recycle)
				var bright = ( 1 - dFade * ( 1 - near ) ) * ( 1 + ( glow - 1 ) * k );
				c.style.filter = 'brightness(' + bright.toFixed( 3 ) + ')';
				c.style.opacity = seam.toFixed( 3 );
			}
		}

		var S = 0, vel = 0;
		layout();
		applyFrame();
		window.addEventListener( 'resize', function () { layout(); applyFrame(); }, { passive: true } );
		var hoverF = hoverFactor( el );
		var allowDrag = num( el, 'data-tdg-allowdrag', 1 );
		var momentum = num( el, 'data-tdg-momentum', 1 );
		var dragging = false, px0 = 0, scrollTarget = null;
		var advance = function ( dt ) { S += ( 1 / Math.max( 1, speed ) ) * dt * hoverF(); };

		if ( allowDrag ) {
			el.style.cursor = 'grab';
			var down = function ( x, y ) { dragging = true; px0 = x; vel = 0; el.style.cursor = 'grabbing'; };
			var move = function ( x, y ) { if ( ! dragging ) { return; } var dx = x - px0; px0 = x; var dd = dx * 0.0016; S += dd; vel = dd; };
			var up = function () { dragging = false; el.style.cursor = 'grab'; };
			attachDrag( el, down, move, up );
		}

		if ( drive === 'scroll' ) {
			var onScroll = function () { scrollTarget = scrollProgress( el, stage ) * num( el, 'data-tdg-rounds', 1 ); };
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
				if ( momentum && Math.abs( vel ) > 0.00002 ) { S += vel; vel *= 0.94; }
				else if ( drive === 'scroll' && scrollTarget !== null ) { S += ( scrollTarget - S ) * 0.14; if ( Math.abs( scrollTarget - S ) < 0.0004 ) { S = scrollTarget; } }
			}
			applyFrame();
			requestAnimationFrame( loop );
		};
		requestAnimationFrame( loop );
	}

	/* Orbit Bloom (Orbit) — a ring of billboarded cards that slowly orbits a tilted disc and BREATHES:
	 * it blooms from a tight central cluster out to a wide ring and back, cards fading + scaling up as
	 * they open. Front cards (nearer the camera) are bigger + brighter; the far arc dims. Pure CSS 3D,
	 * one rAF loop. The view emits one card per image (images cycle). An original UnysonPlus design. */
	function initOrbitBloom( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var orbit = el.querySelector( '.tdg__orbit' );
		if ( ! stage || ! orbit ) { return; }
		var cards = Array.prototype.slice.call( orbit.querySelectorAll( '.tdg__card' ) );
		var N = cards.length;
		if ( ! N ) { return; }

		var drive   = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed   = num( el, 'data-tdg-speed', 22 );
		var dir     = num( el, 'data-tdg-dir', 1 );
		var radPct  = clamp( num( el, 'data-tdg-radius', 32 ), 10, 60 ) / 100;
		var tilt    = clamp( num( el, 'data-tdg-tilt', 58 ), 0, 80 );
		var bloomAmt = clamp( num( el, 'data-tdg-bloom', 70 ) / 100, 0, 1 );
		var glow    = clamp( num( el, 'data-tdg-glow', 130 ), 100, 220 ) / 100;
		var dFade   = clamp( num( el, 'data-tdg-dfade', 45 ) / 100, 0, 1 );
		var cardPct = clamp( num( el, 'data-tdg-card', 16 ), 4, 60 ) / 100;
		var TWO = Math.PI * 2;

		var cardW = 0, R = 0, W = 1;
		function layout() {
			W = stage.clientWidth || el.clientWidth || 1;
			cardW = Math.max( 20, W * cardPct );
			R = W * radPct;
			stage.style.perspective = ( W * 1.7 ) + 'px';
			orbit.style.transform = 'rotateX(' + tilt + 'deg)';
			cards.forEach( function ( c ) { c.style.width = cardW + 'px'; c.style.marginLeft = ( -cardW / 2 ) + 'px'; } );
			cards.forEach( function ( c ) { c.style.marginTop = ( -( c.offsetHeight || cardW ) / 2 ) + 'px'; } );
		}

		function applyFrame() {
			var breath = ( 1 - Math.cos( S * TWO ) ) / 2;                 // 0 closed … 1 fully bloomed
			var Rnow = R * ( 1 - bloomAmt * 0.72 * ( 1 - breath ) );      // tight cluster when closed, wide ring when open
			for ( var i = 0; i < N; i++ ) {
				var c = cards[ i ];
				var theta = ( i / N ) * TWO + S * TWO * dir * 0.5;        // the ring also slowly orbits
				var x = Math.cos( theta ) * Rnow, y = Math.sin( theta ) * Rnow;
				var near = ( Math.sin( theta ) + 1 ) / 2;                 // bottom of the disc = nearer the camera
				var sc = ( 0.74 + 0.26 * near ) * ( 0.5 + 0.5 * breath ); // cards also scale up as they open
				c.style.transform = 'translate3d(' + x.toFixed( 1 ) + 'px,' + y.toFixed( 1 ) + 'px,0) rotateX(' + ( -tilt ) + 'deg) scale(' + sc.toFixed( 3 ) + ')';
				c.style.opacity = ( 0.45 + 0.55 * breath ).toFixed( 3 );  // fade in as they bloom
				var bright = ( 1 - dFade * ( 1 - near ) ) * ( 1 + ( glow - 1 ) * near );
				c.style.filter = 'brightness(' + bright.toFixed( 3 ) + ')';
				c.style.zIndex = String( Math.round( near * 100 ) );
			}
		}

		var S = 0, vel = 0;
		layout();
		applyFrame();
		window.addEventListener( 'resize', function () { layout(); applyFrame(); }, { passive: true } );
		var hoverF = hoverFactor( el );
		var allowDrag = num( el, 'data-tdg-allowdrag', 1 );
		var momentum = num( el, 'data-tdg-momentum', 1 );
		var dragging = false, px0 = 0, scrollTarget = null;
		var advance = function ( dt ) { S += ( 1 / Math.max( 1, speed ) ) * dt * hoverF(); };

		if ( allowDrag ) {
			el.style.cursor = 'grab';
			var down = function ( x, y ) { dragging = true; px0 = x; vel = 0; el.style.cursor = 'grabbing'; };
			var move = function ( x, y ) { if ( ! dragging ) { return; } var dx = x - px0; px0 = x; var dd = dx * 0.004; S += dd; vel = dd; };
			var up = function () { dragging = false; el.style.cursor = 'grab'; };
			attachDrag( el, down, move, up );
		}

		if ( drive === 'scroll' ) {
			var onScroll = function () { scrollTarget = scrollProgress( el, stage ) * num( el, 'data-tdg-rounds', 1 ); };
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
				if ( momentum && Math.abs( vel ) > 0.00002 ) { S += vel; vel *= 0.94; }
				else if ( drive === 'scroll' && scrollTarget !== null ) { S += ( scrollTarget - S ) * 0.14; if ( Math.abs( scrollTarget - S ) < 0.0004 ) { S = scrollTarget; } }
			}
			applyFrame();
			requestAnimationFrame( loop );
		};
		requestAnimationFrame( loop );
	}

	/* Focus Orbit (Orbit) — a ring of billboarded cards orbiting a tilted disc; the card reaching the
	 * front of the orbit pulls INTO the centre, lifts toward you and enlarges into a hero, then returns
	 * to the ring as the next card takes its place — a rolling central feature. Front cards are brighter;
	 * the far arc dims. Pure CSS 3D, one rAF loop. One card per image (images cycle). An original design. */
	function initFocusOrbit( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var orbit = el.querySelector( '.tdg__orbit' );
		if ( ! stage || ! orbit ) { return; }
		var cards = Array.prototype.slice.call( orbit.querySelectorAll( '.tdg__card' ) );
		var N = cards.length;
		if ( ! N ) { return; }

		var drive   = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed   = num( el, 'data-tdg-speed', 20 );
		var dir     = num( el, 'data-tdg-dir', 1 );
		var radPct  = clamp( num( el, 'data-tdg-radius', 30 ), 10, 55 ) / 100;
		var tilt    = clamp( num( el, 'data-tdg-tilt', 55 ), 0, 80 );
		var heroAmt = clamp( num( el, 'data-tdg-hero', 70 ) / 100, 0, 1 );
		var glow    = clamp( num( el, 'data-tdg-glow', 150 ), 100, 240 ) / 100;
		var dFade   = clamp( num( el, 'data-tdg-dfade', 45 ) / 100, 0, 1 );
		var cardPct = clamp( num( el, 'data-tdg-card', 18 ), 4, 60 ) / 100;
		var TWO = Math.PI * 2;

		var cardW = 0, R = 0, W = 1;
		function layout() {
			W = stage.clientWidth || el.clientWidth || 1;
			cardW = Math.max( 20, W * cardPct );
			R = W * radPct;
			stage.style.perspective = ( W * 1.7 ) + 'px';
			orbit.style.transform = 'rotateX(' + tilt + 'deg)';
			cards.forEach( function ( c ) { c.style.width = cardW + 'px'; c.style.marginLeft = ( -cardW / 2 ) + 'px'; } );
			cards.forEach( function ( c ) { c.style.marginTop = ( -( c.offsetHeight || cardW ) / 2 ) + 'px'; } );
		}

		function applyFrame() {
			for ( var i = 0; i < N; i++ ) {
				var c = cards[ i ];
				var theta = ( i / N ) * TWO + S * TWO * dir * 0.5;
				var near = ( Math.sin( theta ) + 1 ) / 2;                 // front (bottom) = near
				var k = Math.pow( near, 5 );                              // ONLY the very front becomes the hero (sharp)
				var x0 = Math.cos( theta ) * R, y0 = Math.sin( theta ) * R;
				var x = x0 * ( 1 - k * heroAmt ), y = y0 * ( 1 - k * heroAmt ); // pull toward centre as it fronts
				var lz = k * heroAmt * cardW * 1.3;                       // lift the hero toward the camera (counter the disc tilt)
				var sc = ( 0.46 + 0.30 * near ) * ( 1 + k * heroAmt * 1.5 ); // satellites small; the hero enlarges strongly
				c.style.transform = 'translate3d(' + x.toFixed( 1 ) + 'px,' + y.toFixed( 1 ) + 'px,' + lz.toFixed( 1 ) + 'px) rotateX(' + ( -tilt ) + 'deg) scale(' + sc.toFixed( 3 ) + ')';
				var bright = ( 1 - dFade * ( 1 - near ) ) * ( 1 + ( glow - 1 ) * k );
				c.style.filter = 'brightness(' + bright.toFixed( 3 ) + ')';
				c.style.zIndex = String( 100 + Math.round( ( near + k ) * 100 ) );
			}
		}

		var S = 0, vel = 0;
		layout();
		applyFrame();
		window.addEventListener( 'resize', function () { layout(); applyFrame(); }, { passive: true } );
		var hoverF = hoverFactor( el );
		var allowDrag = num( el, 'data-tdg-allowdrag', 1 );
		var momentum = num( el, 'data-tdg-momentum', 1 );
		var dragging = false, px0 = 0, scrollTarget = null;
		var advance = function ( dt ) { S += ( 1 / Math.max( 1, speed ) ) * dt * hoverF(); };

		if ( allowDrag ) {
			el.style.cursor = 'grab';
			var down = function ( x, y ) { dragging = true; px0 = x; vel = 0; el.style.cursor = 'grabbing'; };
			var move = function ( x, y ) { if ( ! dragging ) { return; } var dx = x - px0; px0 = x; var dd = dx * 0.004; S += dd; vel = dd; };
			var up = function () { dragging = false; el.style.cursor = 'grab'; };
			attachDrag( el, down, move, up );
		}

		if ( drive === 'scroll' ) {
			var onScroll = function () { scrollTarget = scrollProgress( el, stage ) * num( el, 'data-tdg-rounds', 1 ); };
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
				if ( momentum && Math.abs( vel ) > 0.00002 ) { S += vel; vel *= 0.94; }
				else if ( drive === 'scroll' && scrollTarget !== null ) { S += ( scrollTarget - S ) * 0.14; if ( Math.abs( scrollTarget - S ) < 0.0004 ) { S = scrollTarget; } }
			}
			applyFrame();
			requestAnimationFrame( loop );
		};
		requestAnimationFrame( loop );
	}

	/* Shared drive for the newer Orbit designs (Dual Orbit, Comet Orbit, Orbit Swarm, Ring Tilt): the
	 * continuous / scroll-scrub / static drive, drag-to-spin with momentum and the hover slow-down — the
	 * same behaviour as Orbit Bloom's inline loop, in one place. `st.S` is the design's phase (1 unit =
	 * half a revolution); applyFrame() reads it. */
	function orbitDrive( el, stage, st, speed, drive, applyFrame ) {
		var hoverF = hoverFactor( el );
		var dsign = num( el, 'data-tdg-dragsign', 1 );                       // -1 flips a drag (a design whose phase runs opposite to the screen)
		var allowDrag = num( el, 'data-tdg-allowdrag', 1 );
		var momentum = num( el, 'data-tdg-momentum', 1 );
		var dragging = false, px0 = 0, vel = 0, scrollTarget = null;
		if ( allowDrag ) {
			el.style.cursor = 'grab';
			attachDrag( el,
				function ( x ) { dragging = true; px0 = x; vel = 0; el.style.cursor = 'grabbing'; },
				function ( x ) { if ( ! dragging ) { return; } var dd = ( x - px0 ) * 0.004 * dsign; px0 = x; st.S += dd; vel = dd; },
				function () { dragging = false; el.style.cursor = 'grab'; } );
		}
		if ( drive === 'scroll' ) {
			var onScroll = function () { scrollTarget = scrollProgress( el, stage ) * num( el, 'data-tdg-rounds', 1 ); };
			window.addEventListener( 'scroll', onScroll, { passive: true } );
			onScroll();
		}
		var autoRun = ( drive === 'continuous' && ! reduce );
		if ( ! ( autoRun || allowDrag || drive === 'scroll' ) ) { return; }
		var last = 0;
		var loop = function ( tm ) {
			if ( ! last ) { last = tm; }
			var dt = Math.min( 0.1, ( tm - last ) / 1000 ); last = tm;
			if ( ! dragging ) {
				if ( autoRun ) { st.S += ( 1 / Math.max( 1, speed ) ) * dt * hoverF(); }
				if ( momentum && Math.abs( vel ) > 0.00002 ) { st.S += vel; vel *= 0.94; }
				else if ( drive === 'scroll' && scrollTarget !== null ) { st.S += ( scrollTarget - st.S ) * 0.14; if ( Math.abs( scrollTarget - st.S ) < 0.0004 ) { st.S = scrollTarget; } }
			}
			applyFrame();
			requestAnimationFrame( loop );
		};
		requestAnimationFrame( loop );
	}

	/* Size + place an orbit-path line (`.tdg__track`, a bordered ellipse lying in the orbit plane) with
	 * semi-axes rx / ry, centred at x = cx. No-op when the design was rendered without paths. */
	function orbitTrack( t, rx, ry, cx ) {
		if ( ! t ) { return; }
		t.style.width = ( 2 * rx ) + 'px';
		t.style.height = ( 2 * ry ) + 'px';
		t.style.marginLeft = ( -rx + cx ) + 'px';
		t.style.marginTop = ( -ry ) + 'px';
	}

	/* Stable 0…1 pseudo-random per card index (same layout on every load / in the preview). */
	function orbitHash( n ) { var x = Math.sin( n * 127.1 + 311.7 ) * 43758.5453; return x - Math.floor( x ); }

	/* Dual Orbit (Orbit) — two concentric rings of billboarded cards on one tilted disc. Cards alternate
	 * outer / inner; the inner ring turns the other way (Counter-rotate) at its own speed (Inner Ring
	 * Speed), so the rings gear past each other. Front cards brighter, far arc dims. An original design. */
	function initDualOrbit( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var orbit = el.querySelector( '.tdg__orbit' );
		if ( ! stage || ! orbit ) { return; }
		var cards = Array.prototype.slice.call( orbit.querySelectorAll( '.tdg__card' ) );
		var N = cards.length;
		if ( ! N ) { return; }

		var drive   = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed   = num( el, 'data-tdg-speed', 20 );
		var dir     = num( el, 'data-tdg-dir', 1 );
		var radPct  = clamp( num( el, 'data-tdg-radius', 33 ), 15, 55 ) / 100;
		var inPct   = clamp( num( el, 'data-tdg-inner', 50 ), 25, 85 ) / 100;
		var tilt    = clamp( num( el, 'data-tdg-tilt', 62 ), 0, 80 );
		var counter = num( el, 'data-tdg-counter', 1 ) ? -1 : 1;
		var iSpeed  = clamp( num( el, 'data-tdg-ispeed', 150 ), 50, 300 ) / 100;
		var glow    = clamp( num( el, 'data-tdg-glow', 130 ), 100, 220 ) / 100;
		var dFade   = clamp( num( el, 'data-tdg-dfade', 45 ) / 100, 0, 1 );
		var cardPct = clamp( num( el, 'data-tdg-card', 11 ), 4, 60 ) / 100;
		var TWO = Math.PI * 2;

		// Every third card rides the inner ring, the rest the outer one (fewer than 5 → one ring), so
		// the outer ring stays full and the inner one reads as a smaller, separate orbit.
		var outer = [], inner = [];
		cards.forEach( function ( c, i ) { ( N >= 5 && i % 3 === 1 ) ? inner.push( c ) : outer.push( c ); } );
		var tracks = orbit.querySelectorAll( '.tdg__track' );

		var cardW = 0, R = 0, W = 1;
		function layout() {
			W = stage.clientWidth || el.clientWidth || 1;
			cardW = Math.max( 20, W * cardPct );
			R = W * radPct;
			stage.style.perspective = ( W * 1.7 ) + 'px';
			orbit.style.transform = 'rotateX(' + tilt + 'deg)';
			cards.forEach( function ( c ) { c.style.width = cardW + 'px'; c.style.marginLeft = ( -cardW / 2 ) + 'px'; } );
			cards.forEach( function ( c ) { c.style.marginTop = ( -( c.offsetHeight || cardW ) / 2 ) + 'px'; } );
			orbitTrack( tracks[ 0 ], R, R, 0 );
			orbitTrack( tracks[ 1 ], R * inPct, R * inPct, 0 );
		}
		function ring( list, Rr, ang, scl, off ) {
			var n = list.length;
			for ( var j = 0; j < n; j++ ) {
				var theta = ( j / n ) * TWO + off + ang;
				var x = Math.cos( theta ) * Rr, y = Math.sin( theta ) * Rr;
				var near = ( Math.sin( theta ) + 1 ) / 2;
				var sc = ( 0.72 + 0.28 * near ) * scl;
				var c = list[ j ];
				c.style.transform = 'translate3d(' + x.toFixed( 1 ) + 'px,' + y.toFixed( 1 ) + 'px,0) rotateX(' + ( -tilt ) + 'deg) scale(' + sc.toFixed( 3 ) + ')';
				c.style.filter = 'brightness(' + ( ( 1 - dFade * ( 1 - near ) ) * ( 1 + ( glow - 1 ) * near ) ).toFixed( 3 ) + ')';
				c.style.zIndex = String( Math.round( near * 100 ) );
			}
		}
		var st = { S: 0 };
		function applyFrame() {
			var a = st.S * TWO * dir * 0.5;
			ring( outer, R, a, 1, 0 );
			if ( inner.length ) { ring( inner, R * inPct, a * iSpeed * counter, 0.66, Math.PI / inner.length ); }
		}
		layout();
		applyFrame();
		window.addEventListener( 'resize', function () { layout(); applyFrame(); }, { passive: true } );
		orbitDrive( el, stage, st, speed, drive, applyFrame );
	}

	/* Comet Orbit (Orbit) — billboarded cards travel one elliptical orbit around a glowing focus with
	 * orbital (Kepler) timing: equal steps of "mean anomaly" become slow drifting at the far end and a fast
	 * whip past the focus, where the cards flare larger and brighter. Eccentricity 0 = an even circle. An
	 * original design. */
	function initCometOrbit( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var orbit = el.querySelector( '.tdg__orbit' );
		if ( ! stage || ! orbit ) { return; }
		var cards = Array.prototype.slice.call( orbit.querySelectorAll( '.tdg__card' ) );
		var N = cards.length;
		if ( ! N ) { return; }
		var focusEl = orbit.querySelector( '.tdg__focus' );

		var drive   = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed   = num( el, 'data-tdg-speed', 24 );
		var dir     = num( el, 'data-tdg-dir', 1 );
		var radPct  = clamp( num( el, 'data-tdg-radius', 33 ), 15, 55 ) / 100;
		var e       = clamp( num( el, 'data-tdg-ecc', 62 ), 0, 85 ) / 100;
		var tilt    = clamp( num( el, 'data-tdg-tilt', 45 ), 0, 80 );
		var flare   = clamp( num( el, 'data-tdg-flare', 70 ) / 100, 0, 1 );
		var dFade   = clamp( num( el, 'data-tdg-dfade', 40 ) / 100, 0, 1 );
		var cardPct = clamp( num( el, 'data-tdg-card', 12 ), 4, 60 ) / 100;
		var TWO = Math.PI * 2;
		var flareK = flare * ( 0.35 + 0.65 * Math.min( 1, e / 0.5 ) ); // a near-circle flares only a little

		var cardW = 0, A = 0, B = 0, shift = 0, W = 1;
		function layout() {
			W = stage.clientWidth || el.clientWidth || 1;
			cardW = Math.max( 20, W * cardPct );
			A = W * radPct;
			B = A * Math.sqrt( 1 - e * e );
			shift = -A * e * 0.15;                                         // balance the off-centre focus
			orbitTrack( orbit.querySelector( '.tdg__track' ), A, B, shift );
			stage.style.perspective = ( W * 1.7 ) + 'px';
			orbit.style.transform = 'rotateX(' + tilt + 'deg)';
			cards.forEach( function ( c ) { c.style.width = cardW + 'px'; c.style.marginLeft = ( -cardW / 2 ) + 'px'; } );
			cards.forEach( function ( c ) { c.style.marginTop = ( -( c.offsetHeight || cardW ) / 2 ) + 'px'; } );
			if ( focusEl ) {
				var fs = cardW * 1.7;
				focusEl.style.width = focusEl.style.height = fs + 'px';
				focusEl.style.marginLeft = focusEl.style.marginTop = ( -fs / 2 ) + 'px';
				focusEl.style.transform = 'translate3d(' + ( A * e + shift ).toFixed( 1 ) + 'px,0,0) rotateX(' + ( -tilt ) + 'deg)';
			}
		}
		var st = { S: 0 };
		function applyFrame() {
			for ( var i = 0; i < N; i++ ) {
				var M = ( i / N ) * TWO + st.S * TWO * dir * 0.5;          // mean anomaly — advances evenly
				var E = M;                                                  // solve Kepler: E − e·sin E = M
				for ( var it = 0; it < 6; it++ ) { E -= ( E - e * Math.sin( E ) - M ) / ( 1 - e * Math.cos( E ) ); }
				var x = A * Math.cos( E ) + shift, y = B * Math.sin( E );
				var near = ( Math.sin( E ) + 1 ) / 2;                       // front of the disc = near
				var prox = ( 1 + Math.cos( E ) ) / 2;                       // 1 at perihelion (by the focus)
				var kF = Math.pow( prox, 3 ) * flareK;
				var sc = ( 0.62 + 0.25 * near ) * ( 1 + kF * 1.1 );
				var c = cards[ i ];
				c.style.transform = 'translate3d(' + x.toFixed( 1 ) + 'px,' + y.toFixed( 1 ) + 'px,0) rotateX(' + ( -tilt ) + 'deg) scale(' + sc.toFixed( 3 ) + ')';
				c.style.filter = 'brightness(' + ( ( 1 - dFade * ( 1 - near ) ) * ( 1 + kF * 0.8 ) ).toFixed( 3 ) + ')';
				c.style.zIndex = String( 10 + Math.round( ( near + kF ) * 100 ) );
			}
		}
		layout();
		applyFrame();
		window.addEventListener( 'resize', function () { layout(); applyFrame(); }, { passive: true } );
		orbitDrive( el, stage, st, speed, drive, applyFrame );
	}

	/* Orbit Swarm (Orbit) — every billboarded card keeps its own orbit: its own radius (Spread), pace
	 * (Swirl — inner orbits run faster, so cards overtake each other) and rise / sink above the plane
	 * (Height Drift). Periodically the swarm gathers toward the centre and scatters back out (Gather).
	 * Per-card values come from a stable hash, so the swarm is the same on every load. An original design. */
	function initOrbitSwarm( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var orbit = el.querySelector( '.tdg__orbit' );
		if ( ! stage || ! orbit ) { return; }
		var cards = Array.prototype.slice.call( orbit.querySelectorAll( '.tdg__card' ) );
		var N = cards.length;
		if ( ! N ) { return; }

		var drive    = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed    = num( el, 'data-tdg-speed', 26 );
		var dir      = num( el, 'data-tdg-dir', 1 );
		var radPct   = clamp( num( el, 'data-tdg-radius', 31 ), 15, 55 ) / 100;
		var spread   = clamp( num( el, 'data-tdg-spread', 55 ), 0, 90 ) / 100;
		var swirl    = clamp( num( el, 'data-tdg-swirl', 60 ), 0, 100 ) / 100;
		var hAmt     = clamp( num( el, 'data-tdg-height', 30 ), 0, 100 ) / 100;
		var cohesion = clamp( num( el, 'data-tdg-cohesion', 45 ), 0, 100 ) / 100;
		var tilt     = clamp( num( el, 'data-tdg-tilt', 55 ), 0, 80 );
		var glow     = clamp( num( el, 'data-tdg-glow', 130 ), 100, 220 ) / 100;
		var dFade    = clamp( num( el, 'data-tdg-dfade', 45 ) / 100, 0, 1 );
		var cardPct  = clamp( num( el, 'data-tdg-card', 9 ), 4, 60 ) / 100;
		var TWO = Math.PI * 2;

		// Per-card orbit: radius fraction, start phase, angular speed, height phase. A "sunflower" layout
		// (golden-angle phases, area-even radii) starts the swarm evenly scattered with no pile-ups; the
		// faster inner orbits then shear it into drifting spiral arms.
		var rf = [], ph = [], om = [], hp = [];
		for ( var i = 0; i < N; i++ ) {
			rf[ i ] = 1 - spread + spread * Math.sqrt( ( i + 0.5 ) / N );
			ph[ i ] = i * 2.399963;
			om[ i ] = 1 + swirl * 1.0 * ( 1 - rf[ i ] );
			hp[ i ] = orbitHash( i + 100 ) * TWO;
		}

		var cardW = 0, R = 0, W = 1;
		function layout() {
			W = stage.clientWidth || el.clientWidth || 1;
			cardW = Math.max( 20, W * cardPct );
			R = W * radPct;
			stage.style.perspective = ( W * 1.7 ) + 'px';
			orbit.style.transform = 'rotateX(' + tilt + 'deg)';
			cards.forEach( function ( c ) { c.style.width = cardW + 'px'; c.style.marginLeft = ( -cardW / 2 ) + 'px'; } );
			cards.forEach( function ( c ) { c.style.marginTop = ( -( c.offsetHeight || cardW ) / 2 ) + 'px'; } );
		}
		var st = { S: 0 };
		function applyFrame() {
			var gp = ( 1 - Math.cos( st.S * TWO * 0.35 ) ) / 2;            // 0 scattered … 1 gathered
			var gather = cohesion * 0.5 * gp * gp;
			for ( var i = 0; i < N; i++ ) {
				var theta = ph[ i ] + st.S * TWO * dir * 0.5 * om[ i ];
				var r = R * rf[ i ] * ( 1 - gather );
				var x = Math.cos( theta ) * r, y = Math.sin( theta ) * r;
				var z = Math.sin( st.S * TWO * 0.7 + hp[ i ] ) * hAmt * R * 0.4;
				var near = ( Math.sin( theta ) + 1 ) / 2;
				var sc = 0.68 + 0.32 * near;
				var c = cards[ i ];
				c.style.transform = 'translate3d(' + x.toFixed( 1 ) + 'px,' + y.toFixed( 1 ) + 'px,' + z.toFixed( 1 ) + 'px) rotateX(' + ( -tilt ) + 'deg) scale(' + sc.toFixed( 3 ) + ')';
				c.style.filter = 'brightness(' + ( ( 1 - dFade * ( 1 - near ) ) * ( 1 + ( glow - 1 ) * near ) ).toFixed( 3 ) + ')';
				c.style.zIndex = String( Math.round( near * 100 ) );
			}
		}
		layout();
		applyFrame();
		window.addEventListener( 'resize', function () { layout(); applyFrame(); }, { passive: true } );
		orbitDrive( el, stage, st, speed, drive, applyFrame );
	}

	/* Ring Tilt (Orbit) — a steeply tilted flat ring, like a planet's rings. The cards are NOT billboarded:
	 * each lies in the ring plane with its top edge toward the centre (so the front of the ring reads
	 * upright) and stands up by Card Lift, facing outward. The ring turns around an optional shaded planet
	 * (a billboarded disc) and slowly rocks and precesses (Wobble). An original design. */
	function initRingTilt( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var orbit = el.querySelector( '.tdg__orbit' );
		if ( ! stage || ! orbit ) { return; }
		var cards = Array.prototype.slice.call( orbit.querySelectorAll( '.tdg__card' ) );
		var N = cards.length;
		if ( ! N ) { return; }
		var planet = orbit.querySelector( '.tdg__planet' );

		var drive   = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed   = num( el, 'data-tdg-speed', 30 );
		var dir     = num( el, 'data-tdg-dir', 1 );
		var radPct  = clamp( num( el, 'data-tdg-radius', 40 ), 15, 55 ) / 100;
		var tilt    = clamp( num( el, 'data-tdg-tilt', 70 ), 30, 85 );
		var lift    = clamp( num( el, 'data-tdg-lift', 20 ), 0, 90 );
		var wob     = clamp( num( el, 'data-tdg-wobble', 8 ), 0, 25 );
		var corePct = clamp( num( el, 'data-tdg-core', 34 ), 10, 70 ) / 100;
		var glow    = clamp( num( el, 'data-tdg-glow', 125 ), 100, 220 ) / 100;
		var dFade   = clamp( num( el, 'data-tdg-dfade', 50 ) / 100, 0, 1 );
		var cardPct = clamp( num( el, 'data-tdg-card', 12 ), 4, 60 ) / 100;
		var TWO = Math.PI * 2;
		var sinL = Math.sin( lift * Math.PI / 180 );

		var cardW = 0, cardH = 0, R = 0, W = 1;
		function layout() {
			W = stage.clientWidth || el.clientWidth || 1;
			cardW = Math.max( 20, W * cardPct );
			R = W * radPct;
			stage.style.perspective = ( W * 1.6 ) + 'px';
			cards.forEach( function ( c ) { c.style.width = cardW + 'px'; c.style.marginLeft = ( -cardW / 2 ) + 'px'; } );
			cardH = cards[ 0 ].offsetHeight || cardW;
			cards.forEach( function ( c ) { c.style.marginTop = ( -cardH / 2 ) + 'px'; } );
			if ( planet ) {
				var ps = 2 * R * corePct;
				planet.style.width = planet.style.height = ps + 'px';
				planet.style.marginLeft = planet.style.marginTop = ( -ps / 2 ) + 'px';
			}
		}
		var st = { S: 0 };
		function applyFrame() {
			var tNow = tilt + wob * Math.sin( st.S * TWO * 0.6 );          // rock
			var prec = wob * 1.2 * Math.sin( st.S * TWO * 0.3 + 1 );        // precess (sideways lean)
			orbit.style.transform = 'rotateZ(' + prec.toFixed( 2 ) + 'deg) rotateX(' + tNow.toFixed( 2 ) + 'deg)';
			if ( planet ) { planet.style.transform = 'rotateX(' + ( -tNow ).toFixed( 2 ) + 'deg) rotateZ(' + ( -prec ).toFixed( 2 ) + 'deg)'; }
			var lz = ( cardH / 2 ) * sinL;                                  // keep the bottom edge on the ring plane
			for ( var i = 0; i < N; i++ ) {
				var theta = ( i / N ) * TWO + st.S * TWO * dir * 0.5;
				var x = Math.cos( theta ) * R, y = Math.sin( theta ) * R;
				var near = ( Math.sin( theta ) + 1 ) / 2;
				var deg = theta * 180 / Math.PI - 90;                       // top edge points at the centre
				var c = cards[ i ];
				c.style.transform = 'translate3d(' + x.toFixed( 1 ) + 'px,' + y.toFixed( 1 ) + 'px,' + lz.toFixed( 1 ) + 'px) rotateZ(' + deg.toFixed( 2 ) + 'deg) rotateX(' + ( -lift ) + 'deg)';
				c.style.filter = 'brightness(' + ( ( 1 - dFade * ( 1 - near ) ) * ( 1 + ( glow - 1 ) * near ) ).toFixed( 3 ) + ')';
			}
		}
		layout();
		applyFrame();
		window.addEventListener( 'resize', function () { layout(); applyFrame(); }, { passive: true } );
		orbitDrive( el, stage, st, speed, drive, applyFrame );
	}

	/* Gyro Orbit (Orbit) — an atom / gyroscope: 2–4 rings of billboarded cards, each ring on its own
	 * plane (leaning by Ring Lean, fanned evenly around the vertical axis), neighbouring rings running
	 * opposite ways, around a glowing core; the whole gyro slowly turns (Spin). Optional ring paths are
	 * real 3D circles on the same planes. Seen slightly from above. An original design. */
	function initGyroOrbit( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var orbit = el.querySelector( '.tdg__orbit' );
		if ( ! stage || ! orbit ) { return; }
		var cards = Array.prototype.slice.call( orbit.querySelectorAll( '.tdg__card' ) );
		var N = cards.length;
		if ( ! N ) { return; }
		var tracks = orbit.querySelectorAll( '.tdg__track' );
		var core = orbit.querySelector( '.tdg__focus' );

		var drive   = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed   = num( el, 'data-tdg-speed', 22 );
		var dir     = num( el, 'data-tdg-dir', 1 );
		var radPct  = clamp( num( el, 'data-tdg-radius', 24 ), 15, 50 ) / 100;
		var K       = Math.round( clamp( num( el, 'data-tdg-rings', 3 ), 2, 4 ) );
		var incl    = clamp( num( el, 'data-tdg-lean', 62 ), 0, 85 );
		var spin    = clamp( num( el, 'data-tdg-spin', 35 ), 0, 100 ) / 100;
		var glow    = clamp( num( el, 'data-tdg-glow', 130 ), 100, 220 ) / 100;
		var dFade   = clamp( num( el, 'data-tdg-dfade', 50 ) / 100, 0, 1 );
		var cardPct = clamp( num( el, 'data-tdg-card', 9 ), 4, 60 ) / 100;
		var TWO = Math.PI * 2, DEG = Math.PI / 180;
		var VIEW = -14;                                                    // look slightly down on the gyro
		var ci = Math.cos( incl * DEG ), si = Math.sin( incl * DEG ), cv = Math.cos( VIEW * DEG ), sv = Math.sin( VIEW * DEG );

		// Deal the cards round-robin onto the K rings.
		var ringOf = [], idxIn = [], count = [];
		for ( var k = 0; k < K; k++ ) { count[ k ] = 0; }
		for ( var i = 0; i < N; i++ ) { ringOf[ i ] = i % K; idxIn[ i ] = count[ i % K ]++; }

		var cardW = 0, R = 0, W = 1;
		function layout() {
			W = stage.clientWidth || el.clientWidth || 1;
			cardW = Math.max( 20, W * cardPct );
			R = W * radPct;
			stage.style.perspective = ( W * 1.6 ) + 'px';
			orbit.style.transform = 'none';
			cards.forEach( function ( c ) { c.style.width = cardW + 'px'; c.style.marginLeft = ( -cardW / 2 ) + 'px'; } );
			cards.forEach( function ( c ) { c.style.marginTop = ( -( c.offsetHeight || cardW ) / 2 ) + 'px'; } );
			for ( var t = 0; t < tracks.length; t++ ) { orbitTrack( tracks[ t ], R, R, 0 ); tracks[ t ].style.display = t < K ? '' : 'none'; }
			if ( core ) { var cs = cardW * 1.6; core.style.width = core.style.height = cs + 'px'; core.style.marginLeft = core.style.marginTop = ( -cs / 2 ) + 'px'; }
		}
		var st = { S: 0 };
		function applyFrame() {
			var yawAll = st.S * Math.PI * spin * dir;
			for ( var t = 0; t < tracks.length && t < K; t++ ) {
				var yawT = ( t * Math.PI / K + yawAll ) / DEG;
				tracks[ t ].style.transform = 'rotateX(' + VIEW + 'deg) rotateY(' + yawT.toFixed( 2 ) + 'deg) rotateX(' + ( 90 + incl ) + 'deg)';
			}
			for ( var i = 0; i < N; i++ ) {
				var k = ringOf[ i ], n = count[ k ];
				var sgn = ( k % 2 ) ? -1 : 1;                                // neighbouring rings run opposite ways
				var th = ( idxIn[ i ] / n ) * TWO + ( k * 0.7 ) + st.S * TWO * dir * 0.5 * ( 1 + 0.18 * k ) * sgn;
				var yaw = k * Math.PI / K + yawAll;
				var x = R * Math.cos( th ), z = R * Math.sin( th );
				var y1 = -z * si, z1 = z * ci;                               // lean the ring (rotateX)
				var cy = Math.cos( yaw ), sy = Math.sin( yaw );
				var x2 = x * cy + z1 * sy, z2 = -x * sy + z1 * cy;           // fan it round the vertical (rotateY)
				var y3 = y1 * cv - z2 * sv, z3 = y1 * sv + z2 * cv;          // view from slightly above (rotateX)
				var near = clamp( ( z3 / R + 1 ) / 2, 0, 1 );
				var sc = 0.62 + 0.38 * near;
				var c = cards[ i ];
				c.style.transform = 'translate3d(' + x2.toFixed( 1 ) + 'px,' + y3.toFixed( 1 ) + 'px,' + z3.toFixed( 1 ) + 'px) scale(' + sc.toFixed( 3 ) + ')';
				c.style.filter = 'brightness(' + ( ( 1 - dFade * ( 1 - near ) ) * ( 1 + ( glow - 1 ) * near ) ).toFixed( 3 ) + ')';
			}
		}
		layout();
		applyFrame();
		window.addEventListener( 'resize', function () { layout(); applyFrame(); }, { passive: true } );
		orbitDrive( el, stage, st, speed, drive, applyFrame );
	}

	/* Infinity Orbit (Orbit) — billboarded cards loop a figure-eight (a lemniscate) on a tilted plane;
	 * where the loops cross, one pass rises over the other (Cross Height) so the ∞ reads in 3D. Optional
	 * path line (an SVG drawn in the plane). An original design. */
	function initInfinityOrbit( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var orbit = el.querySelector( '.tdg__orbit' );
		if ( ! stage || ! orbit ) { return; }
		var cards = Array.prototype.slice.call( orbit.querySelectorAll( '.tdg__card' ) );
		var N = cards.length;
		if ( ! N ) { return; }

		var drive   = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed   = num( el, 'data-tdg-speed', 26 );
		var dir     = num( el, 'data-tdg-dir', 1 );
		var radPct  = clamp( num( el, 'data-tdg-radius', 38 ), 15, 50 ) / 100;
		var loop    = clamp( num( el, 'data-tdg-loop', 100 ), 40, 160 ) / 100;
		var cross   = clamp( num( el, 'data-tdg-cross', 40 ), 0, 100 ) / 100;
		var tilt    = clamp( num( el, 'data-tdg-tilt', 50 ), 0, 80 );
		var paths   = num( el, 'data-tdg-paths', 1 );
		var glow    = clamp( num( el, 'data-tdg-glow', 130 ), 100, 220 ) / 100;
		var dFade   = clamp( num( el, 'data-tdg-dfade', 45 ) / 100, 0, 1 );
		var cardPct = clamp( num( el, 'data-tdg-card', 11 ), 4, 60 ) / 100;
		var TWO = Math.PI * 2;

		function pt( t, A ) {                                               // lemniscate of Bernoulli
			var s = Math.sin( t ), c = Math.cos( t ), d = 1 + s * s;
			return [ A * c / d, A * loop * s * c / d ];
		}

		var svg = null;
		if ( paths ) {
			svg = document.createElementNS( 'http://www.w3.org/2000/svg', 'svg' );
			svg.setAttribute( 'class', 'tdg__path' );
			svg.setAttribute( 'aria-hidden', 'true' );
			orbit.insertBefore( svg, orbit.firstChild );
		}

		var cardW = 0, A = 0, W = 1;
		function layout() {
			W = stage.clientWidth || el.clientWidth || 1;
			cardW = Math.max( 20, W * cardPct );
			A = W * radPct;
			stage.style.perspective = ( W * 1.7 ) + 'px';
			orbit.style.transform = 'rotateX(' + tilt + 'deg)';
			cards.forEach( function ( c ) { c.style.width = cardW + 'px'; c.style.marginLeft = ( -cardW / 2 ) + 'px'; } );
			cards.forEach( function ( c ) { c.style.marginTop = ( -( c.offsetHeight || cardW ) / 2 ) + 'px'; } );
			if ( svg ) {
				var h = A * loop * 0.8, d = '';
				for ( var s = 0; s <= 120; s++ ) { var p = pt( s / 120 * TWO, A ); d += ( s ? 'L' : 'M' ) + ( p[ 0 ] + A ).toFixed( 1 ) + ',' + ( p[ 1 ] + h / 2 ).toFixed( 1 ); }
				svg.setAttribute( 'width', 2 * A ); svg.setAttribute( 'height', h );
				svg.style.width = ( 2 * A ) + 'px'; svg.style.height = h + 'px';   // inline: beats a theme's svg { height: auto }
				svg.setAttribute( 'viewBox', '0 0 ' + ( 2 * A ).toFixed( 1 ) + ' ' + h.toFixed( 1 ) );
				svg.style.marginLeft = ( -A ) + 'px'; svg.style.marginTop = ( -h / 2 ) + 'px';
				svg.innerHTML = '<path d="' + d + 'Z" fill="none" vector-effect="non-scaling-stroke"/>';
			}
		}
		var st = { S: 0 };
		function applyFrame() {
			var yMax = A * loop * 0.36 || 1;
			for ( var i = 0; i < N; i++ ) {
				var t = ( i / N ) * TWO + st.S * TWO * dir * 0.5;
				var p = pt( t, A );
				var lift = cross * A * 0.32 * Math.sin( t );                 // one pass over, one under, at the crossing
				var near = clamp( ( p[ 1 ] / yMax + 1 ) / 2, 0, 1 );
				var sc = 0.7 + 0.3 * near;
				var c = cards[ i ];
				c.style.transform = 'translate3d(' + p[ 0 ].toFixed( 1 ) + 'px,' + p[ 1 ].toFixed( 1 ) + 'px,' + lift.toFixed( 1 ) + 'px) rotateX(' + ( -tilt ) + 'deg) scale(' + sc.toFixed( 3 ) + ')';
				c.style.filter = 'brightness(' + ( ( 1 - dFade * ( 1 - near ) ) * ( 1 + ( glow - 1 ) * near ) ).toFixed( 3 ) + ')';
				c.style.zIndex = String( Math.round( near * 100 ) );
			}
		}
		layout();
		applyFrame();
		window.addEventListener( 'resize', function () { layout(); applyFrame(); }, { passive: true } );
		orbitDrive( el, stage, st, speed, drive, applyFrame );
	}

	/* Vortex (Orbit) — a top-down whirlpool: billboarded cards spiral in from the rim (Twist = turns, its
	 * sign = the spin), sinking (Depth), shrinking and dimming as they near the eye, then fade out and
	 * re-enter at the rim. Flow Inward or Outward. Optional glowing eye. An original design. */
	function initVortex( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var orbit = el.querySelector( '.tdg__orbit' );
		if ( ! stage || ! orbit ) { return; }
		var cards = Array.prototype.slice.call( orbit.querySelectorAll( '.tdg__card' ) );
		var N = cards.length;
		if ( ! N ) { return; }
		var eye = orbit.querySelector( '.tdg__focus' );

		var drive   = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed   = num( el, 'data-tdg-speed', 30 );
		var dir     = num( el, 'data-tdg-dir', 1 );                          // 1 = inward, -1 = outward
		var radPct  = clamp( num( el, 'data-tdg-radius', 42 ), 15, 55 ) / 100;
		var twist   = clamp( num( el, 'data-tdg-twist', 0.8 ), -3, 3 );
		var arms    = Math.round( clamp( num( el, 'data-tdg-arms', 3 ), 1, 6 ) );
		var depth   = clamp( num( el, 'data-tdg-depth', 60 ), 0, 100 ) / 100;
		var tilt    = clamp( num( el, 'data-tdg-tilt', 52 ), 0, 80 );
		var dFade   = clamp( num( el, 'data-tdg-dfade', 55 ) / 100, 0, 1 );
		var cardPct = clamp( num( el, 'data-tdg-card', 11 ), 4, 60 ) / 100;
		var TWO = Math.PI * 2;

		// Deal the cards onto `arms` spiral arms (evenly spaced round the rim); along each arm they are
		// evenly spaced in flow phase, and each arm is staggered so the cards never line up radially.
		var off = [], th0 = [], perArm = Math.ceil( N / arms );
		for ( var i = 0; i < N; i++ ) {
			var a = i % arms;
			off[ i ] = ( Math.floor( i / arms ) + a / arms ) / perArm;
			th0[ i ] = ( a / arms ) * TWO;
		}

		var cardW = 0, R = 0, W = 1;
		function layout() {
			W = stage.clientWidth || el.clientWidth || 1;
			cardW = Math.max( 20, W * cardPct );
			R = W * radPct;
			stage.style.perspective = ( W * 1.6 ) + 'px';
			// Sinking toward the eye carries cards down the screen; lift the whole whirlpool by half that
			// drop so it stays centred in the stage.
			var drop = depth * R * 1.2 * Math.sin( tilt * Math.PI / 180 ) * 0.5;
			orbit.style.transform = 'translateY(' + ( -drop ).toFixed( 1 ) + 'px) rotateX(' + tilt + 'deg)';
			cards.forEach( function ( c ) { c.style.width = cardW + 'px'; c.style.marginLeft = ( -cardW / 2 ) + 'px'; } );
			cards.forEach( function ( c ) { c.style.marginTop = ( -( c.offsetHeight || cardW ) / 2 ) + 'px'; } );
			if ( eye ) {
				var es = cardW * 1.8;
				eye.style.width = eye.style.height = es + 'px';
				eye.style.marginLeft = eye.style.marginTop = ( -es / 2 ) + 'px';
				eye.style.transform = 'translate3d(0,0,' + ( -depth * R * 1.2 ).toFixed( 1 ) + 'px) rotateX(' + ( -tilt ) + 'deg)';
			}
		}
		var st = { S: 0 };
		function applyFrame() {
			for ( var i = 0; i < N; i++ ) {
				var u = ( off[ i ] + st.S * 0.5 * dir ) % 1; if ( u < 0 ) { u += 1; }   // 0 = rim … 1 = the eye
				var r = R * ( 0.05 + 0.95 * Math.pow( 1 - u, 0.85 ) );
				var th = th0[ i ] + u * twist * TWO;
				var x = Math.cos( th ) * r, y = Math.sin( th ) * r;
				var lz = -u * depth * R * 1.2;
				var near = ( Math.sin( th ) * ( r / R ) + 1 ) / 2;
				var sc = ( 1 - 0.62 * u ) * ( 0.86 + 0.14 * near );
				var op = Math.min( 1, u / 0.08 ) * Math.min( 1, ( 1 - u ) / 0.2 );
				var c = cards[ i ];
				c.style.transform = 'translate3d(' + x.toFixed( 1 ) + 'px,' + y.toFixed( 1 ) + 'px,' + lz.toFixed( 1 ) + 'px) rotateX(' + ( -tilt ) + 'deg) scale(' + sc.toFixed( 3 ) + ')';
				c.style.opacity = op.toFixed( 3 );
				c.style.filter = 'brightness(' + ( 1 - dFade * u ).toFixed( 3 ) + ')';
			}
		}
		layout();
		applyFrame();
		window.addEventListener( 'resize', function () { layout(); applyFrame(); }, { passive: true } );
		orbitDrive( el, stage, st, speed, drive, applyFrame );
	}

	/* Pendulum Orbit (Orbit) — billboarded cards spread along an ARC of a tilted orbit (Arc Span) that
	 * swings back and forth like a pendulum (Swing), easing to a stop at each end, instead of revolving.
	 * The card passing the front is the brightest and largest. An original design. */
	function initPendulumOrbit( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var orbit = el.querySelector( '.tdg__orbit' );
		if ( ! stage || ! orbit ) { return; }
		var cards = Array.prototype.slice.call( orbit.querySelectorAll( '.tdg__card' ) );
		var N = cards.length;
		if ( ! N ) { return; }

		var drive   = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed   = num( el, 'data-tdg-speed', 8 );
		var dir     = num( el, 'data-tdg-dir', 1 );
		var radPct  = clamp( num( el, 'data-tdg-radius', 36 ), 15, 55 ) / 100;
		var arc     = clamp( num( el, 'data-tdg-arc', 130 ), 40, 300 );
		var swing   = clamp( num( el, 'data-tdg-swing', 45 ), 0, 180 );
		var tilt    = clamp( num( el, 'data-tdg-tilt', 58 ), 0, 80 );
		var glow    = clamp( num( el, 'data-tdg-glow', 140 ), 100, 220 ) / 100;
		var dFade   = clamp( num( el, 'data-tdg-dfade', 45 ) / 100, 0, 1 );
		var cardPct = clamp( num( el, 'data-tdg-card', 8 ), 4, 60 ) / 100;
		var DEG = Math.PI / 180;

		var cardW = 0, R = 0, W = 1;
		function layout() {
			W = stage.clientWidth || el.clientWidth || 1;
			cardW = Math.max( 20, W * cardPct );
			R = W * radPct;
			stage.style.perspective = ( W * 1.7 ) + 'px';
			orbit.style.transform = 'translateY(' + ( -R * 0.6 * Math.cos( tilt * DEG ) ).toFixed( 1 ) + 'px) rotateX(' + tilt + 'deg)';   // the arc sits at the front: lift it to centre
			cards.forEach( function ( c ) { c.style.width = cardW + 'px'; c.style.marginLeft = ( -cardW / 2 ) + 'px'; } );
			cards.forEach( function ( c ) { c.style.marginTop = ( -( c.offsetHeight || cardW ) / 2 ) + 'px'; } );
		}
		var st = { S: 0 };
		function applyFrame() {
			var sw = Math.sin( st.S * Math.PI * 2 * dir ) * swing;            // degrees; one full swing per Loop Duration, easing at the ends
			for ( var i = 0; i < N; i++ ) {
				var f = N > 1 ? i / ( N - 1 ) - 0.5 : 0;
				var th = ( 90 + f * arc + sw ) * DEG;                         // 90° = the front of the disc
				var x = Math.cos( th ) * R, y = Math.sin( th ) * R;
				var near = ( Math.sin( th ) + 1 ) / 2;
				var k = Math.pow( near, 4 );
				var sc = ( 0.6 + 0.3 * near ) * ( 1 + 0.35 * k );
				var c = cards[ i ];
				c.style.transform = 'translate3d(' + x.toFixed( 1 ) + 'px,' + y.toFixed( 1 ) + 'px,0) rotateX(' + ( -tilt ) + 'deg) scale(' + sc.toFixed( 3 ) + ')';
				c.style.filter = 'brightness(' + ( ( 1 - dFade * ( 1 - near ) ) * ( 1 + ( glow - 1 ) * k ) ).toFixed( 3 ) + ')';
				c.style.zIndex = String( Math.round( near * 100 ) );
			}
		}
		layout();
		applyFrame();
		window.addEventListener( 'resize', function () { layout(); applyFrame(); }, { passive: true } );
		orbitDrive( el, stage, st, speed, drive, applyFrame );
	}

	/* Moon Orbit (Orbit) — a solar system of photos: a few large "planet" cards orbit the main ring of a
	 * tilted disc, and the rest are dealt to them as smaller "moons" that circle their planet faster
	 * (orbits within orbits). All billboarded. An original design. */
	function initMoonOrbit( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var orbit = el.querySelector( '.tdg__orbit' );
		if ( ! stage || ! orbit ) { return; }
		var cards = Array.prototype.slice.call( orbit.querySelectorAll( '.tdg__card' ) );
		var N = cards.length;
		if ( ! N ) { return; }

		var drive   = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed   = num( el, 'data-tdg-speed', 30 );
		var dir     = num( el, 'data-tdg-dir', 1 );
		var radPct  = clamp( num( el, 'data-tdg-radius', 33 ), 15, 50 ) / 100;
		var P       = Math.round( clamp( num( el, 'data-tdg-planets', 4 ), 2, 6 ) );
		var moonPct = clamp( num( el, 'data-tdg-moonr', 8 ), 3, 20 ) / 100;
		var mSpeed  = clamp( num( el, 'data-tdg-mspeed', 300 ), 100, 600 ) / 100;
		var mSize   = clamp( num( el, 'data-tdg-msize', 50 ), 25, 90 ) / 100;
		var tilt    = clamp( num( el, 'data-tdg-tilt', 55 ), 0, 80 );
		var glow    = clamp( num( el, 'data-tdg-glow', 130 ), 100, 220 ) / 100;
		var dFade   = clamp( num( el, 'data-tdg-dfade', 45 ) / 100, 0, 1 );
		var cardPct = clamp( num( el, 'data-tdg-card', 11 ), 4, 60 ) / 100;
		var TWO = Math.PI * 2;
		if ( P > N ) { P = N; }

		// The first P cards are planets; the rest are dealt round them as moons.
		var host = [], mIdx = [], mCount = [];
		for ( var p = 0; p < P; p++ ) { mCount[ p ] = 0; }
		for ( var i = P; i < N; i++ ) { var h = ( i - P ) % P; host[ i ] = h; mIdx[ i ] = mCount[ h ]++; }

		var cardW = 0, R = 0, rM = 0, W = 1;
		function layout() {
			W = stage.clientWidth || el.clientWidth || 1;
			cardW = Math.max( 20, W * cardPct );
			R = W * radPct;
			rM = W * moonPct;
			stage.style.perspective = ( W * 1.7 ) + 'px';
			orbit.style.transform = 'rotateX(' + tilt + 'deg)';
			cards.forEach( function ( c, j ) { var w = j < P ? cardW : cardW * mSize; c.style.width = w + 'px'; c.style.marginLeft = ( -w / 2 ) + 'px'; } );
			cards.forEach( function ( c ) { c.style.marginTop = ( -( c.offsetHeight || cardW ) / 2 ) + 'px'; } );
		}
		var px = [], py = [];
		function place( c, x, y, near, sc ) {
			c.style.transform = 'translate3d(' + x.toFixed( 1 ) + 'px,' + y.toFixed( 1 ) + 'px,0) rotateX(' + ( -tilt ) + 'deg) scale(' + sc.toFixed( 3 ) + ')';
			c.style.filter = 'brightness(' + ( ( 1 - dFade * ( 1 - near ) ) * ( 1 + ( glow - 1 ) * near ) ).toFixed( 3 ) + ')';
			c.style.zIndex = String( Math.round( near * 1000 ) );
		}
		var st = { S: 0 };
		function applyFrame() {
			for ( var p = 0; p < P; p++ ) {
				var th = ( p / P ) * TWO + st.S * TWO * dir * 0.5;
				px[ p ] = Math.cos( th ) * R; py[ p ] = Math.sin( th ) * R;
				var nr = clamp( ( py[ p ] / R + 1 ) / 2, 0, 1 );
				place( cards[ p ], px[ p ], py[ p ], nr, 0.7 + 0.3 * nr );
			}
			for ( var i = P; i < N; i++ ) {
				var h = host[ i ], n = mCount[ h ];
				var a = ( mIdx[ i ] / n ) * TWO + st.S * TWO * dir * 0.5 * mSpeed + h;
				var x = px[ h ] + Math.cos( a ) * rM, y = py[ h ] + Math.sin( a ) * rM;
				var near = clamp( ( y / ( R + rM ) + 1 ) / 2, 0, 1 );
				place( cards[ i ], x, y, near, 0.7 + 0.3 * near );
			}
		}
		layout();
		applyFrame();
		window.addEventListener( 'resize', function () { layout(); applyFrame(); }, { passive: true } );
		orbitDrive( el, stage, st, speed, drive, applyFrame );
	}

	/* Tiered Orbit (Orbit) — 2–5 horizontal rings of billboarded cards stacked at different heights (seen
	 * slightly from above), neighbouring tiers turning opposite ways. Taper narrows the top (a tree) or
	 * the bottom (a tornado). Seen from OUTSIDE — unlike Panorama Wall, which wraps around the viewer. An
	 * original design. */
	function initTieredOrbit( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var orbit = el.querySelector( '.tdg__orbit' );
		if ( ! stage || ! orbit ) { return; }
		var cards = Array.prototype.slice.call( orbit.querySelectorAll( '.tdg__card' ) );
		var N = cards.length;
		if ( ! N ) { return; }

		var drive   = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed   = num( el, 'data-tdg-speed', 26 );
		var dir     = num( el, 'data-tdg-dir', 1 );
		var radPct  = clamp( num( el, 'data-tdg-radius', 28 ), 15, 50 ) / 100;
		var T       = Math.round( clamp( num( el, 'data-tdg-tiers', 3 ), 2, 5 ) );
		var gapPct  = clamp( num( el, 'data-tdg-gap', 16 ), 5, 40 ) / 100;
		var taper   = clamp( num( el, 'data-tdg-taper', 35 ), -70, 70 ) / 100;
		var elev    = clamp( num( el, 'data-tdg-elev', 22 ), 0, 45 );
		var alt     = num( el, 'data-tdg-alt', 1 );
		var glow    = clamp( num( el, 'data-tdg-glow', 130 ), 100, 220 ) / 100;
		var dFade   = clamp( num( el, 'data-tdg-dfade', 50 ) / 100, 0, 1 );
		var cardPct = clamp( num( el, 'data-tdg-card', 8 ), 4, 60 ) / 100;
		var TWO = Math.PI * 2, DEG = Math.PI / 180;
		var cv = Math.cos( -elev * DEG ), sv = Math.sin( -elev * DEG );

		// Deal cards top tier first; each tier gets an even share.
		var tracks = orbit.querySelectorAll( '.tdg__track' );
		var tierOf = [], idx = [], cnt = [];
		for ( var t = 0; t < T; t++ ) { cnt[ t ] = 0; }
		for ( var i = 0; i < N; i++ ) { var tt = Math.min( T - 1, Math.floor( i * T / N ) ); tierOf[ i ] = tt; idx[ i ] = cnt[ tt ]++; }

		var cardW = 0, R = 0, W = 1, gap = 0;
		function layout() {
			W = stage.clientWidth || el.clientWidth || 1;
			cardW = Math.max( 20, W * cardPct );
			R = W * radPct;
			gap = ( stage.clientHeight || el.clientHeight || W * 0.5 ) * gapPct;   // % of the stage HEIGHT, so wide stages don't overflow
			stage.style.perspective = ( W * 1.6 ) + 'px';
			// The wider bottom tier (Taper > 0) sits lower and nearer; lift the stack so it stays centred.
			orbit.style.transform = 'translateY(' + ( -taper * R * 0.5 * Math.sin( elev * DEG ) - gap * 0.1 ).toFixed( 1 ) + 'px)';
			cards.forEach( function ( c ) { c.style.width = cardW + 'px'; c.style.marginLeft = ( -cardW / 2 ) + 'px'; } );
			cards.forEach( function ( c ) { c.style.marginTop = ( -( c.offsetHeight || cardW ) / 2 ) + 'px'; } );
			// One path per tier: a flat circle of that tier's radius, laid horizontal, dropped to the tier's
			// height and seen from the same elevation as the cards.
			for ( var k = 0; k < tracks.length; k++ ) {
				if ( k >= T ) { tracks[ k ].style.display = 'none'; continue; }
				var uk = T > 1 ? k / ( T - 1 ) : 0.5, rk = R * ( 1 + taper * ( uk - 0.5 ) ), yk = ( uk - 0.5 ) * gap * ( T - 1 );
				orbitTrack( tracks[ k ], rk, rk, 0 );
				tracks[ k ].style.transform = 'rotateX(' + ( -elev ) + 'deg) translate3d(0,' + yk.toFixed( 1 ) + 'px,0) rotateX(90deg)';
			}
		}
		var st = { S: 0 };
		function applyFrame() {
			for ( var i = 0; i < N; i++ ) {
				var t = tierOf[ i ], n = cnt[ t ];
				var u = T > 1 ? t / ( T - 1 ) : 0.5;                          // 0 = top tier … 1 = bottom tier
				var r = R * ( 1 + taper * ( u - 0.5 ) );                       // taper > 0: narrow top (a tree)
				var sgn = ( alt && t % 2 ) ? -1 : 1;
				var th = ( idx[ i ] / n ) * TWO + t * 0.6 + st.S * TWO * dir * 0.5 * sgn;
				var x = Math.cos( th ) * r, z = Math.sin( th ) * r;
				var y = ( u - 0.5 ) * gap * ( T - 1 );
				var y2 = y * cv - z * sv, z2 = y * sv + z * cv;               // seen from slightly above
				var near = clamp( ( z / R + 1 ) / 2, 0, 1 );
				var c = cards[ i ];
				c.style.transform = 'translate3d(' + x.toFixed( 1 ) + 'px,' + y2.toFixed( 1 ) + 'px,' + z2.toFixed( 1 ) + 'px) scale(' + ( 0.66 + 0.34 * near ).toFixed( 3 ) + ')';
				c.style.filter = 'brightness(' + ( ( 1 - dFade * ( 1 - near ) ) * ( 1 + ( glow - 1 ) * near ) ).toFixed( 3 ) + ')';
			}
		}
		layout();
		applyFrame();
		window.addEventListener( 'resize', function () { layout(); applyFrame(); }, { passive: true } );
		orbitDrive( el, stage, st, speed, drive, applyFrame );
	}

	/* ---- Grid family helpers ------------------------------------------------------------------- */
	// Lay `cards` out as a cols×rows grid centred on (0,0): sets each card's width/margins and returns
	// the per-card centre positions plus the pitch (card + gap) in px.
	function gridLayout( cards, cols, rows, cardW, gapPct ) {
		cards.forEach( function ( c ) { c.style.width = cardW + 'px'; c.style.marginLeft = ( -cardW / 2 ) + 'px'; } );
		var h = cards.length ? ( cards[ 0 ].offsetHeight || cardW ) : cardW;
		cards.forEach( function ( c ) { c.style.marginTop = ( -h / 2 ) + 'px'; } );
		var gap = cardW * gapPct, px = cardW + gap, py = h + gap, pos = [];
		for ( var i = 0; i < cards.length; i++ ) {
			var c = i % cols, r = Math.floor( i / cols );
			pos.push( { c: c, r: r, x: ( c - ( cols - 1 ) / 2 ) * px, y: ( r - ( rows - 1 ) / 2 ) * py } );
		}
		return { pos: pos, px: px, py: py, h: h };
	}
	function easeInOut( t ) { return t < 0.5 ? 4 * t * t * t : 1 - Math.pow( -2 * t + 2, 3 ) / 2; }

	/* Wave Grid (Grid) — a grid of cards lying flat on a tilted floor; a ripple travels across it (from
	 * the centre, the left edge or a corner) lifting each card on the crest and tipping it with the
	 * slope of the wave, like tiles on water. Crests catch the light. An original design. */
	function initWaveGrid( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var grid  = el.querySelector( '.tdg__grid' );
		if ( ! stage || ! grid ) { return; }
		var cards = Array.prototype.slice.call( grid.querySelectorAll( '.tdg__card' ) );
		var N = cards.length;
		if ( ! N ) { return; }

		var drive   = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed   = num( el, 'data-tdg-speed', 5 );
		var dir     = num( el, 'data-tdg-dir', 1 );
		var cols    = Math.round( clamp( num( el, 'data-tdg-cols', 6 ), 2, 12 ) );
		var rows    = Math.ceil( N / cols );
		var gapPct  = clamp( num( el, 'data-tdg-gap', 14 ), 0, 80 ) / 100;
		var tilt    = clamp( num( el, 'data-tdg-tilt', 55 ), 0, 80 );
		var amp     = clamp( num( el, 'data-tdg-amp', 35 ), 0, 120 ) / 100;
		var wl      = clamp( num( el, 'data-tdg-wl', 3.5 ), 1.5, 10 );
		var origin  = num( el, 'data-tdg-origin', 0 );                     // 0 centre · 1 left edge · 2 corner
		var glow    = clamp( num( el, 'data-tdg-glow', 130 ), 100, 220 ) / 100;
		var cardPct = clamp( num( el, 'data-tdg-card', 11 ), 4, 40 ) / 100;
		var TWO = Math.PI * 2, RAD = 180 / Math.PI;

		var cardW = 0, L = null, dist = [], ux = [], uy = [];
		function layout() {
			var W = stage.clientWidth || el.clientWidth || 1;
			cardW = Math.max( 16, W * cardPct );
			stage.style.perspective = ( W * 1.5 ) + 'px';
			grid.style.transform = 'rotateX(' + tilt + 'deg)';
			L = gridLayout( cards, cols, rows, cardW, gapPct );
			for ( var i = 0; i < N; i++ ) {
				var p = L.pos[ i ], dx, dy;
				if ( origin === 1 ) { dx = p.c; dy = 0; }
				else if ( origin === 2 ) { dx = p.c; dy = p.r; }
				else { dx = p.x / L.px; dy = p.y / L.py; }
				var d = Math.sqrt( dx * dx + dy * dy );
				dist[ i ] = origin === 2 ? ( p.c + p.r ) / Math.SQRT2 : d;
				ux[ i ] = d ? dx / d : 1; uy[ i ] = d ? dy / d : 0;
			}
		}
		var st = { S: 0 };
		function applyFrame() {
			var A = amp * cardW, k = TWO / ( wl * L.px );
			for ( var i = 0; i < N; i++ ) {
				var ph = dist[ i ] * TWO / wl - st.S * TWO * dir;
				var s = Math.sin( ph );
				var z = A * s;
				var ang = Math.atan( A * k * Math.cos( ph ) ) * RAD;          // tip with the wave's slope
				var p = L.pos[ i ], c = cards[ i ];
				c.style.transform = 'translate3d(' + p.x.toFixed( 1 ) + 'px,' + p.y.toFixed( 1 ) + 'px,' + z.toFixed( 1 ) + 'px) rotate3d(' + uy[ i ].toFixed( 3 ) + ',' + ( -ux[ i ] ).toFixed( 3 ) + ',0,' + ang.toFixed( 2 ) + 'deg)';
				c.style.filter = 'brightness(' + ( s > 0 ? 1 + ( glow - 1 ) * s : 1 + 0.18 * s ).toFixed( 3 ) + ')';
			}
		}
		layout();
		applyFrame();
		window.addEventListener( 'resize', function () { layout(); applyFrame(); }, { passive: true } );
		orbitDrive( el, stage, st, speed, drive, applyFrame );
	}

	/* Flip Grid (Grid) — a face-on grid of two-sided cards. Every Loop Duration a wave of 3D flips sweeps
	 * across it (a diagonal wave, at random, or row by row) and each card turns over to show the next
	 * photo, so a small grid cycles through the whole gallery. The photos come from a hidden pool (which
	 * also keeps every photo in the lightbox); a card's hidden face is refilled before it turns into
	 * view. An original design. */
	function initFlipGrid( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var grid  = el.querySelector( '.tdg__grid' );
		if ( ! stage || ! grid ) { return; }
		var cards = Array.prototype.slice.call( grid.querySelectorAll( '.tdg__card' ) );
		var pool  = Array.prototype.slice.call( el.querySelectorAll( '.tdg__pool > .tdg__poolitem' ) ).map( function ( n ) { return n.innerHTML; } );
		var N = cards.length, NP = pool.length;
		if ( ! N || ! NP ) { return; }

		var drive   = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed   = num( el, 'data-tdg-speed', 4 );
		var dir     = num( el, 'data-tdg-dir', 1 );
		var cols    = Math.round( clamp( num( el, 'data-tdg-cols', 4 ), 1, 10 ) );
		var rows    = Math.ceil( N / cols );
		var gapPct  = clamp( num( el, 'data-tdg-gap', 8 ), 0, 60 ) / 100;
		var axisX   = el.getAttribute( 'data-tdg-axis' ) === 'x';
		var order   = num( el, 'data-tdg-order', 0 );                      // 0 wave · 1 random · 2 rows
		var tilt    = clamp( num( el, 'data-tdg-tilt', 8 ), 0, 40 );
		var cardPct = clamp( num( el, 'data-tdg-card', 15 ), 4, 40 ) / 100;
		var ROT = axisX ? 'rotateX' : 'rotateY';

		var faces = cards.map( function ( c ) { return c.querySelectorAll( '.tdg__face' ); } );
		var shown = cards.map( function () { return [ -1, -1 ]; } );
		var entrance = el.classList.contains( 'tdg--entrance' );
		var tmp = document.createElement( 'div' );
		function imgFor( j, p ) { return ( ( j + p * ( N + 1 ) ) % NP + NP ) % NP; }
		// Refill a face IN PLACE: keep its .tdg__inner element (the built-in Entrance holds a reference to
		// it and writes its styles there) and swap only its class + contents.
		function setFace( face, a ) {
			var inr = face.firstElementChild;
			tmp.innerHTML = pool[ a ];
			var src = tmp.firstElementChild;
			if ( ! inr || ! src ) { face.innerHTML = pool[ a ]; return; }
			inr.className = src.className;
			inr.innerHTML = src.innerHTML;
		}
		function fill( p ) {                                                // face p%2 shows set p, the other set p+1
			for ( var j = 0; j < N; j++ ) {
				var vis = ( ( p % 2 ) + 2 ) % 2, hid = 1 - vis;
				var a = imgFor( j, p ), b = imgFor( j, p + 1 );
				if ( shown[ j ][ vis ] !== a ) { setFace( faces[ j ][ vis ], a ); shown[ j ][ vis ] = a; }
				if ( shown[ j ][ hid ] !== b ) { setFace( faces[ j ][ hid ], b ); shown[ j ][ hid ] = b; }
				faces[ j ][ vis ].removeAttribute( 'aria-hidden' ); faces[ j ][ vis ].inert = false;
				faces[ j ][ hid ].setAttribute( 'aria-hidden', 'true' ); faces[ j ][ hid ].inert = true;
			}
		}
		// Each card's start time within a page (0…1 of the flip window).
		var delay = [];
		for ( var j = 0; j < N; j++ ) {
			var c = j % cols, r = Math.floor( j / cols );
			delay[ j ] = order === 1 ? orbitHash( j + 7 ) : order === 2 ? ( N > 1 ? j / ( N - 1 ) : 0 ) : ( ( cols + rows - 2 ) ? ( c + r ) / ( cols + rows - 2 ) : 0 );
		}
		// Seed what PHP rendered: face A = photo j, face B = the next set.
		for ( j = 0; j < N; j++ ) { shown[ j ][ 0 ] = imgFor( j, 0 ); shown[ j ][ 1 ] = imgFor( j, 1 ); }

		var cardW = 0, L = null;
		function layout() {
			var W = stage.clientWidth || el.clientWidth || 1;
			cardW = Math.max( 16, W * cardPct );
			stage.style.perspective = ( W * 1.6 ) + 'px';
			grid.style.transform = 'rotateX(' + tilt + 'deg)';
			L = gridLayout( cards, cols, rows, cardW, gapPct );
		}
		var st = { S: 0 }, page = null;
		function applyFrame() {
			var S = st.S * dir, p = Math.floor( S ), f = S - p;
			if ( p !== page ) { page = p; fill( p ); }
			for ( var j = 0; j < N; j++ ) {
				var t = clamp( ( f - delay[ j ] * 0.35 ) / 0.22, 0, 1 ), e = easeInOut( t );
				var ang = 180 * ( p + e ) * ( axisX ? -1 : 1 );
				var z = Math.sin( t * Math.PI ) * cardW * 0.35;               // pop toward you mid-flip
				var pos = L.pos[ j ];
				cards[ j ].style.transform = 'translate3d(' + pos.x.toFixed( 1 ) + 'px,' + pos.y.toFixed( 1 ) + 'px,' + z.toFixed( 1 ) + 'px) ' + ROT + '(' + ang.toFixed( 2 ) + 'deg)';
				cards[ j ].style.zIndex = t > 0 && t < 1 ? '2' : '1';
				// The Entrance animates face A's inner only; mirror it onto face B so both fade together.
				if ( entrance && faces[ j ][ 0 ].firstElementChild && faces[ j ][ 1 ].firstElementChild ) {
					faces[ j ][ 1 ].firstElementChild.style.cssText = faces[ j ][ 0 ].firstElementChild.style.cssText;
				}
			}
		}
		layout();
		applyFrame();
		window.addEventListener( 'resize', function () { layout(); applyFrame(); }, { passive: true } );
		orbitDrive( el, stage, st, speed, drive, applyFrame );
	}

	/* Magnet Grid (Grid) — a face-on grid that follows the pointer: cards near it lift toward you and
	 * turn to face it, and the whole grid leans toward it. With no pointer (or on touch screens between
	 * touches) an invisible pointer drifts over the grid so it is never still. Scroll-scrub moves the
	 * drifting pointer with the page. An original design. */
	function initMagnetGrid( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var grid  = el.querySelector( '.tdg__grid' );
		if ( ! stage || ! grid ) { return; }
		var cards = Array.prototype.slice.call( grid.querySelectorAll( '.tdg__card' ) );
		var N = cards.length;
		if ( ! N ) { return; }

		var drive   = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed   = num( el, 'data-tdg-speed', 10 );
		var cols    = Math.round( clamp( num( el, 'data-tdg-cols', 5 ), 2, 12 ) );
		var rows    = Math.ceil( N / cols );
		var gapPct  = clamp( num( el, 'data-tdg-gap', 14 ), 0, 80 ) / 100;
		var reachP  = clamp( num( el, 'data-tdg-reach', 22 ), 5, 60 ) / 100;
		var lift    = clamp( num( el, 'data-tdg-lift', 90 ), 0, 150 ) / 100;
		var lean    = clamp( num( el, 'data-tdg-lean', 10 ), 0, 30 );
		var roam    = clamp( num( el, 'data-tdg-roam', 1 ), 0.2, 1 );            // how widely the idle pointer drifts
		var glow    = clamp( num( el, 'data-tdg-glow', 130 ), 100, 220 ) / 100;
		var cardPct = clamp( num( el, 'data-tdg-card', 12 ), 4, 40 ) / 100;
		var TWO = Math.PI * 2;

		var W = 1, cardW = 0, L = null, reach = 1, gw = 1, gh = 1;
		function layout() {
			W = stage.clientWidth || el.clientWidth || 1;
			cardW = Math.max( 16, W * cardPct );
			reach = W * reachP;
			stage.style.perspective = ( W * 0.95 ) + 'px';
			L = gridLayout( cards, cols, rows, cardW, gapPct );
			gw = Math.max( 1, ( cols - 1 ) * L.px / 2 + cardW / 2 ); gh = Math.max( 1, ( rows - 1 ) * L.py / 2 + L.h / 2 );
		}

		var hover = false, tx = 0, ty = 0, cx = 0, cy = 0, S = 0, scrollTarget = null;
		el.addEventListener( 'pointermove', function ( e ) {
			var r = grid.getBoundingClientRect();                            // the grid's centre on screen
			hover = true; tx = e.clientX - ( r.left + r.width / 2 ); ty = e.clientY - ( r.top + r.height / 2 );
		}, { passive: true } );
		el.addEventListener( 'pointerleave', function () { hover = false; }, { passive: true } );
		if ( drive === 'scroll' ) {
			var onScroll = function () { scrollTarget = scrollProgress( el, stage ) * num( el, 'data-tdg-rounds', 1 ); };
			window.addEventListener( 'scroll', onScroll, { passive: true } );
			onScroll();
		}
		var autoRun = ( drive === 'continuous' && ! reduce );

		function applyFrame() {
			if ( ! hover ) {                                                  // the drifting, invisible pointer
				tx = Math.sin( S * TWO * 0.5 ) * gw * 0.85 * roam;
				ty = Math.sin( S * TWO * 0.37 + 1.1 ) * gh * 0.8 * roam;
			}
			cx += ( tx - cx ) * 0.12; cy += ( ty - cy ) * 0.12;
			grid.style.transform = 'rotateX(' + ( -cy / gh * lean ).toFixed( 2 ) + 'deg) rotateY(' + ( cx / gw * lean ).toFixed( 2 ) + 'deg)';
			for ( var i = 0; i < N; i++ ) {
				var p = L.pos[ i ], dx = p.x - cx, dy = p.y - cy;
				var k = Math.exp( -( dx * dx + dy * dy ) / ( reach * reach ) );
				var rx = clamp( dy / reach, -1, 1 ) * k * 34, ry = clamp( -dx / reach, -1, 1 ) * k * 34;
				cards[ i ].style.transform = 'translate3d(' + p.x.toFixed( 1 ) + 'px,' + p.y.toFixed( 1 ) + 'px,' + ( lift * cardW * k ).toFixed( 1 ) + 'px) rotateX(' + rx.toFixed( 2 ) + 'deg) rotateY(' + ry.toFixed( 2 ) + 'deg) scale(' + ( 1 + 0.18 * k ).toFixed( 3 ) + ')';
				cards[ i ].style.filter = 'brightness(' + ( 1 + ( glow - 1 ) * k ).toFixed( 3 ) + ')';
				cards[ i ].style.zIndex = String( Math.round( k * 100 ) );
			}
		}
		layout();
		applyFrame();
		window.addEventListener( 'resize', function () { layout(); applyFrame(); }, { passive: true } );
		var last = 0;
		var loop = function ( tm ) {
			if ( ! last ) { last = tm; }
			var dt = Math.min( 0.1, ( tm - last ) / 1000 ); last = tm;
			if ( autoRun ) { S += dt / Math.max( 1, speed ); }
			else if ( scrollTarget !== null ) { S += ( scrollTarget * 2 - S ) * 0.14; }
			applyFrame();
			requestAnimationFrame( loop );
		};
		requestAnimationFrame( loop );
	}

	/* ---- Reveal & Wipe family helpers ---------------------------------------------------------- */
	// One large photo at a time in a `.tdg__frame`; the photos come from the hidden `.tdg__pool` (its
	// links also give click-to-lightbox / open-link and keep every photo in the lightbox). Returns the
	// photo URLs/alts, a sizer, and wires click + Enter on the frame to the current photo's link.
	function revealKit( el ) {
		var stage = el.querySelector( '.tdg__stage' );
		var frame = el.querySelector( '.tdg__frame' );
		var items = Array.prototype.slice.call( el.querySelectorAll( '.tdg__pool > .tdg__poolitem' ) );
		if ( ! stage || ! frame || ! items.length ) { return null; }
		var urls = [], alts = [], links = [];
		items.forEach( function ( it ) {
			var img = it.querySelector( 'img' );
			urls.push( img ? ( img.getAttribute( 'src' ) || '' ) : '' );
			alts.push( img ? ( img.getAttribute( 'alt' ) || '' ) : '' );
			links.push( it.querySelector( 'a.tdg__link' ) );
		} );
		var framePct = clamp( num( el, 'data-tdg-frame', 70 ), 20, 100 ) / 100;
		var kit = { stage: stage, frame: frame, urls: urls, alts: alts, n: urls.length, w: 0, h: 0, current: 0 };
		kit.size = function () {
			var W = stage.clientWidth || el.clientWidth || 1, H = stage.clientHeight || el.clientHeight || W;
			frame.style.width = ( W * framePct ) + 'px';
			var h = frame.offsetHeight || W * framePct;
			if ( h > H * 0.9 ) { frame.style.width = ( W * framePct * H * 0.9 / h ) + 'px'; h = frame.offsetHeight; }
			kit.w = frame.offsetWidth; kit.h = h;
			frame.style.marginLeft = ( -kit.w / 2 ) + 'px'; frame.style.marginTop = ( -kit.h / 2 ) + 'px';
			stage.style.perspective = ( Math.max( kit.w, 1 ) * 2.2 ) + 'px';
		};
		kit.idx = function ( p ) { return ( ( p % kit.n ) + kit.n ) % kit.n; };
		kit.bg = function ( node, i, x, y ) {                              // a slice of photo i at offset x,y
			node.style.backgroundImage = 'url("' + kit.urls[ i ].replace( /"/g, '%22' ) + '")';
			node.style.backgroundSize = kit.w + 'px ' + kit.h + 'px';
			node.style.backgroundPosition = ( -x ) + 'px ' + ( -y ) + 'px';
		};
		kit.setCurrent = function ( i ) {
			if ( kit.current === i && frame.getAttribute( 'aria-label' ) !== null ) { return; }
			kit.current = i;
			frame.setAttribute( 'aria-label', kit.alts[ i ] || '' );
			frame.style.cursor = links[ i ] ? 'pointer' : '';
		};
		frame.setAttribute( 'role', 'img' );
		var open = function () { var a = links[ kit.current ]; if ( a ) { a.click(); } };
		var down = null;
		frame.addEventListener( 'pointerdown', function ( e ) { down = [ e.clientX, e.clientY ]; } );
		frame.addEventListener( 'click', function ( e ) {
			if ( down && Math.abs( e.clientX - down[ 0 ] ) + Math.abs( e.clientY - down[ 1 ] ) > 6 ) { return; } // a drag, not a click
			open();
		} );
		if ( links.some( Boolean ) ) {
			frame.tabIndex = 0;
			frame.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Enter' || e.key === ' ' ) { e.preventDefault(); open(); } } );
		}
		return kit;
	}
	// Transition timing inside one page: 0 until `start`, eased 0…1 across `dur`, then 1 (hold).
	function revealT( f, start, dur ) { return easeInOut( clamp( ( f - start ) / dur, 0, 1 ) ); }

	/* Blind Reveal (Reveal & Wipe) — one large photo cut into slats (Slats) that turn over in 3D one after
	 * another, like Venetian blinds, each showing the next photo on its other side. Vertical slats turn
	 * sideways, horizontal ones head over heels. An original design. */
	function initBlindReveal( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var kit = revealKit( el );
		if ( ! kit ) { return; }
		var drive   = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed   = num( el, 'data-tdg-speed', 4 );
		var dir     = num( el, 'data-tdg-dir', 1 );
		var count   = Math.round( clamp( num( el, 'data-tdg-slats', 10 ), 2, 40 ) );
		var horiz   = el.getAttribute( 'data-tdg-orient' ) === 'h';
		var stagger = clamp( num( el, 'data-tdg-stagger', 50 ), 0, 100 ) / 100;
		var gapPx   = clamp( num( el, 'data-tdg-gap', 2 ), 0, 20 );
		var frame = kit.frame;
		frame.innerHTML = '';
		var slats = [];
		for ( var i = 0; i < count; i++ ) {
			var s = document.createElement( 'div' ); s.className = 'tdg__slat';
			var a = document.createElement( 'div' ); a.className = 'tdg__slatface';
			var b = document.createElement( 'div' ); b.className = 'tdg__slatface';
			s.appendChild( a ); s.appendChild( b ); frame.appendChild( s );
			slats.push( { el: s, a: a, b: b } );
		}
		var page = null;
		function layout() {
			kit.size();
			var L = horiz ? kit.h : kit.w, sz = L / count;
			for ( var i = 0; i < count; i++ ) {
				var o = slats[ i ], d = sz / 2;
				o.x = horiz ? 0 : i * sz; o.y = horiz ? i * sz : 0;
				var w = horiz ? kit.w : sz, h = horiz ? sz : kit.h;
				o.el.style.cssText = 'left:' + o.x + 'px;top:' + o.y + 'px;width:' + ( w - ( horiz ? 0 : gapPx ) ) + 'px;height:' + ( h - ( horiz ? gapPx : 0 ) ) + 'px';
				o.a.style.transform = 'translateZ(' + d + 'px)';
				o.b.style.transform = ( horiz ? 'rotateX(-90deg)' : 'rotateY(90deg)' ) + ' translateZ(' + d + 'px)';
				o.d = d;
			}
			page = null;
		}
		var st = { S: 0 };
		function applyFrame() {
			var S = st.S * dir, p = Math.floor( S ), f = S - p;
			if ( p !== page ) {
				page = p;
				var cur = kit.idx( p ), nxt = kit.idx( p + 1 );
				for ( var i = 0; i < count; i++ ) { kit.bg( slats[ i ].a, cur, slats[ i ].x, slats[ i ].y ); kit.bg( slats[ i ].b, nxt, slats[ i ].x, slats[ i ].y ); }
			}
			var done = 0;
			for ( var j = 0; j < count; j++ ) {
				var t = revealT( f, ( count > 1 ? j / ( count - 1 ) : 0 ) * 0.45 * stagger, 0.45 - 0.45 * stagger * 0.6 );
				var o = slats[ j ], ang = ( horiz ? 90 : -90 ) * t;
				o.el.style.transform = 'translateZ(' + ( -o.d ) + 'px) ' + ( horiz ? 'rotateX(' : 'rotateY(' ) + ang.toFixed( 2 ) + 'deg)';
				if ( t >= 1 ) { done++; }
			}
			kit.setCurrent( done > count / 2 ? kit.idx( p + 1 ) : kit.idx( p ) );
		}
		layout();
		applyFrame();
		window.addEventListener( 'resize', function () { layout(); applyFrame(); }, { passive: true } );
		orbitDrive( el, kit.stage, st, speed, drive, applyFrame );
	}

	/* Iris Wipe (Reveal & Wipe) — the next photo opens up over the current one through a growing shape
	 * (a circle from the centre, a diamond, a diagonal sweep or barn doors parting) while the outgoing photo
	 * pushes gently in and the incoming one settles from a slight zoom. An original design. */
	function initIrisWipe( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var kit = revealKit( el );
		if ( ! kit ) { return; }
		var drive = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed = num( el, 'data-tdg-speed', 4 );
		var dir   = num( el, 'data-tdg-dir', 1 );
		var shape = el.getAttribute( 'data-tdg-shape' ) || 'circle';
		var zoom  = clamp( num( el, 'data-tdg-zoom', 12 ), 0, 40 ) / 100;
		var dur   = clamp( num( el, 'data-tdg-dur', 45 ), 15, 90 ) / 100;
		var frame = kit.frame;
		frame.innerHTML = '<div class="tdg__layer"></div><div class="tdg__layer"></div>';
		var under = frame.children[ 0 ], over = frame.children[ 1 ];
		var page = null;
		function clip( t ) {
			var q;
			if ( shape === 'diamond' ) { q = ( t * 100 ).toFixed( 2 ); return 'polygon(50% ' + ( 50 - q ) + '%, ' + ( 50 + +q ) + '% 50%, 50% ' + ( 50 + +q ) + '%, ' + ( 50 - q ) + '% 50%)'; }
			if ( shape === 'diagonal' ) { q = t * 200; return 'polygon(0 0, ' + q.toFixed( 2 ) + '% 0, 0 ' + q.toFixed( 2 ) + '%)'; }
			if ( shape === 'doors' ) { q = ( 50 - t * 50 ).toFixed( 2 ); return 'inset(0 ' + q + '% 0 ' + q + '%)'; }
			return 'circle(' + ( t * 75 ).toFixed( 2 ) + '% at 50% 50%)';
		}
		function layout() { kit.size(); page = null; }
		var st = { S: 0 };
		function applyFrame() {
			var S = st.S * dir, p = Math.floor( S ), f = S - p;
			if ( p !== page ) {
				page = p;
				kit.bg( under, kit.idx( p ), 0, 0 ); kit.bg( over, kit.idx( p + 1 ), 0, 0 );
			}
			var t = revealT( f, 0, dur );
			over.style.clipPath = clip( t );
			over.style.webkitClipPath = over.style.clipPath;
			over.style.transform = 'scale(' + ( 1 + zoom * ( 1 - t ) ).toFixed( 4 ) + ')';
			under.style.transform = 'scale(' + ( 1 + zoom * 0.6 * t ).toFixed( 4 ) + ')';
			kit.setCurrent( t > 0.5 ? kit.idx( p + 1 ) : kit.idx( p ) );
		}
		layout();
		applyFrame();
		window.addEventListener( 'resize', function () { layout(); applyFrame(); }, { passive: true } );
		orbitDrive( el, kit.stage, st, speed, drive, applyFrame );
	}

	/* Tile Dissolve (Reveal & Wipe) — the photo is cut into a grid of tiles that break away in a wave (from
	 * a corner, at random or from the centre out): each tile flies toward you, spins and fades, uncovering
	 * the next photo underneath. An original design. */
	function initTileDissolve( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var kit = revealKit( el );
		if ( ! kit ) { return; }
		var drive   = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed   = num( el, 'data-tdg-speed', 4 );
		var dir     = num( el, 'data-tdg-dir', 1 );
		var cols    = Math.round( clamp( num( el, 'data-tdg-cols', 8 ), 2, 20 ) );
		var rows    = Math.round( clamp( num( el, 'data-tdg-rows', 5 ), 2, 14 ) );
		var pattern = num( el, 'data-tdg-pattern', 0 );                    // 0 corner · 1 random · 2 centre out
		var flyAmt  = clamp( num( el, 'data-tdg-fly', 60 ), 0, 100 ) / 100;
		var frame = kit.frame;
		frame.innerHTML = '<div class="tdg__layer"></div>';
		var under = frame.firstChild, tiles = [];
		for ( var r = 0; r < rows; r++ ) {
			for ( var c = 0; c < cols; c++ ) {
				var t = document.createElement( 'div' ); t.className = 'tdg__tile'; frame.appendChild( t );
				var dly;
				if ( pattern === 1 ) { dly = orbitHash( r * cols + c + 3 ); }
				else if ( pattern === 2 ) { var dx = ( c + 0.5 ) / cols - 0.5, dy = ( r + 0.5 ) / rows - 0.5; dly = Math.min( 1, Math.sqrt( dx * dx + dy * dy ) / 0.71 ); }
				else { dly = ( cols + rows - 2 ) ? ( c + r ) / ( cols + rows - 2 ) : 0; }
				tiles.push( { el: t, c: c, r: r, d: dly, spin: ( orbitHash( r * cols + c + 41 ) - 0.5 ) * 2 } );
			}
		}
		var page = null, tw = 0, th = 0;
		function layout() {
			kit.size();
			tw = kit.w / cols; th = kit.h / rows;
			tiles.forEach( function ( o ) { o.x = o.c * tw; o.y = o.r * th; o.el.style.cssText = 'left:' + o.x + 'px;top:' + o.y + 'px;width:' + ( tw + 1 ) + 'px;height:' + ( th + 1 ) + 'px'; } );
			page = null;
		}
		var st = { S: 0 };
		function applyFrame() {
			var S = st.S * dir, p = Math.floor( S ), f = S - p;
			if ( p !== page ) {
				page = p;
				kit.bg( under, kit.idx( p + 1 ), 0, 0 );
				tiles.forEach( function ( o ) { kit.bg( o.el, kit.idx( p ), o.x, o.y ); } );
			}
			var gone = 0;
			for ( var i = 0; i < tiles.length; i++ ) {
				var o = tiles[ i ], t = clamp( ( f - o.d * 0.35 ) / 0.22, 0, 1 ), e = t * t;
				var z = e * kit.w * 0.9 * flyAmt, sp = o.spin * 220 * e * flyAmt;
				o.el.style.transform = 'translate3d(' + ( ( o.c + 0.5 - cols / 2 ) * e * tw * 0.6 * flyAmt ).toFixed( 1 ) + 'px,' + ( ( o.r + 0.5 - rows / 2 ) * e * th * 0.6 * flyAmt ).toFixed( 1 ) + 'px,' + z.toFixed( 1 ) + 'px) rotateX(' + sp.toFixed( 1 ) + 'deg) rotateY(' + ( -sp * 0.7 ).toFixed( 1 ) + 'deg) scale(' + ( 1 - 0.6 * e * ( 1 - flyAmt ) ).toFixed( 3 ) + ')';
				o.el.style.opacity = ( 1 - Math.pow( t, 1.6 ) ).toFixed( 3 );
				if ( t >= 1 ) { gone++; }
			}
			kit.setCurrent( gone > tiles.length / 2 ? kit.idx( p + 1 ) : kit.idx( p ) );
		}
		layout();
		applyFrame();
		window.addEventListener( 'resize', function () { layout(); applyFrame(); }, { passive: true } );
		orbitDrive( el, kit.stage, st, speed, drive, applyFrame );
	}

	/* ---- Spotlight & Focus family helpers ------------------------------------------------------ */
	// Tracks the pointer over `el`, in px relative to the centre of `ref` (re-measured on every move).
	function pointerTracker( el, ref ) {
		var pt = { hover: false, x: 0, y: 0 };
		el.addEventListener( 'pointermove', function ( e ) {
			var r = ref.getBoundingClientRect();
			pt.hover = true; pt.x = e.clientX - ( r.left + r.width / 2 ); pt.y = e.clientY - ( r.top + r.height / 2 );
		}, { passive: true } );
		el.addEventListener( 'pointerleave', function () { pt.hover = false; }, { passive: true } );
		return pt;
	}

	/* Spotlight Wall (Spotlight & Focus) — a grid of photos in shadow (dimmed, greyed); a spotlight
	 * glides from photo to photo in a set or random order, holding on each, and the photos inside its
	 * pool come up to full colour and lift slightly. With Follow Pointer, hovering takes the spotlight.
	 * An original design. */
	function initSpotlightWall( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var grid  = el.querySelector( '.tdg__grid' );
		if ( ! stage || ! grid ) { return; }
		var cards = Array.prototype.slice.call( grid.querySelectorAll( '.tdg__card' ) );
		var N = cards.length;
		if ( ! N ) { return; }
		var drive   = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed   = num( el, 'data-tdg-speed', 3 );
		var cols    = Math.round( clamp( num( el, 'data-tdg-cols', 5 ), 1, 12 ) );
		var rows    = Math.ceil( N / cols );
		var gapPct  = clamp( num( el, 'data-tdg-gap', 10 ), 0, 80 ) / 100;
		var radius  = clamp( num( el, 'data-tdg-spot', 80 ), 30, 300 ) / 100;  // spotlight radius in card widths
		var dim     = clamp( num( el, 'data-tdg-dim', 35 ), 0, 100 ) / 100;    // brightness of the unlit photos
		var grey    = clamp( num( el, 'data-tdg-grey', 100 ), 0, 100 ) / 100;
		var lift    = clamp( num( el, 'data-tdg-lift', 8 ), 0, 30 ) / 100;
		var move    = clamp( num( el, 'data-tdg-move', 35 ), 10, 90 ) / 100;   // share of each stop spent travelling
		var random  = num( el, 'data-tdg-order', 1 ) === 1;
		var follow  = num( el, 'data-tdg-follow', 1 ) === 1;
		var dir     = num( el, 'data-tdg-dir', 1 );
		var cardPct = clamp( num( el, 'data-tdg-card', 13 ), 4, 40 ) / 100;

		// The visiting order: a stable shuffle (no photo twice in a row), or reading order.
		var order = [];
		for ( var i = 0; i < N; i++ ) { order.push( i ); }
		if ( random ) { order.sort( function ( a, b ) { return orbitHash( a + 17 ) - orbitHash( b + 17 ); } ); }

		var cardW = 0, L = null, sx = 0, sy = 0;
		function layout() {
			var W = stage.clientWidth || el.clientWidth || 1;
			cardW = Math.max( 16, W * cardPct );
			grid.style.transform = 'none';
			L = gridLayout( cards, cols, rows, cardW, gapPct );
		}
		var pt = follow ? pointerTracker( el, grid ) : { hover: false };
		var st = { S: 0 };
		function applyFrame() {
			var S = st.S * dir, p = Math.floor( S ), f = S - p, t = easeInOut( clamp( f / move, 0, 1 ) );
			var a = L.pos[ order[ ( ( p % N ) + N ) % N ] ], b = L.pos[ order[ ( ( ( p + 1 ) % N ) + N ) % N ] ];
			var tx = a.x + ( b.x - a.x ) * t, ty = a.y + ( b.y - a.y ) * t;
			if ( pt.hover ) { tx = pt.x; ty = pt.y; }
			sx += ( tx - sx ) * 0.2; sy += ( ty - sy ) * 0.2;   // always glide, so leaving the pointer never jumps
			var R = radius * cardW;
			for ( var i = 0; i < N; i++ ) {
				var dx = L.pos[ i ].x - sx, dy = L.pos[ i ].y - sy;
				var k = Math.exp( -( dx * dx + dy * dy ) / ( R * R ) );
				var c = cards[ i ];
				c.style.transform = 'translate3d(' + L.pos[ i ].x.toFixed( 1 ) + 'px,' + L.pos[ i ].y.toFixed( 1 ) + 'px,0) scale(' + ( 1 + lift * k ).toFixed( 3 ) + ')';
				c.style.filter = 'brightness(' + ( dim + ( 1 - dim ) * k ).toFixed( 3 ) + ') grayscale(' + ( grey * ( 1 - k ) ).toFixed( 3 ) + ')';
				c.style.zIndex = String( Math.round( k * 100 ) );
			}
		}
		layout();
		applyFrame();
		window.addEventListener( 'resize', function () { layout(); applyFrame(); }, { passive: true } );
		el.setAttribute( 'data-tdg-allowdrag', '0' );
		orbitDrive( el, stage, st, speed, drive, applyFrame );
		if ( follow && ( drive !== 'continuous' || reduce ) ) {               // keep reacting to the pointer when nothing else animates
			var tick = function () { if ( pt.hover ) { applyFrame(); } requestAnimationFrame( tick ); };
			requestAnimationFrame( tick );
		}
	}

	/* Focus Pull (Spotlight & Focus) — photos float at several depths (Layers) in a 3D field, like a scene
	 * through a camera lens; the focus racks from the front layer to the back and back again, so the layer
	 * in focus is sharp and bright while the others blur (Blur) and dim, and the field drifts gently
	 * sideways (Drift). An original design. */
	function initFocusPull( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var field = el.querySelector( '.tdg__grid' );
		if ( ! stage || ! field ) { return; }
		var cards = Array.prototype.slice.call( field.querySelectorAll( '.tdg__card' ) );
		var N = cards.length;
		if ( ! N ) { return; }
		var drive   = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed   = num( el, 'data-tdg-speed', 3 );
		var LAY     = Math.round( clamp( num( el, 'data-tdg-layers', 3 ), 2, 5 ) );
		var depthP  = clamp( num( el, 'data-tdg-depth', 45 ), 10, 120 ) / 100;
		var blurMax = clamp( num( el, 'data-tdg-blur', 7 ), 0, 20 );
		var dim     = clamp( num( el, 'data-tdg-dim', 30 ), 0, 80 ) / 100;
		var drift   = clamp( num( el, 'data-tdg-drift', 30 ), 0, 100 ) / 100;
		var move    = clamp( num( el, 'data-tdg-move', 40 ), 10, 90 ) / 100;
		var fromBack = num( el, 'data-tdg-dir', 1 ) === -1;
		var cardPct = clamp( num( el, 'data-tdg-card', 15 ), 4, 40 ) / 100;

		// All photos share ONE staggered grid on screen; neighbouring cells go to different layers, so the
		// layers interleave instead of stacking (a near, blurred layer never hides the sharp one behind).
		var gCols = Math.max( 1, Math.ceil( Math.sqrt( N * 1.8 ) ) ), gRows = Math.ceil( N / gCols );
		var layerOf = [], gc = [], gr = [];
		for ( var i = 0; i < N; i++ ) { gc[ i ] = i % gCols; gr[ i ] = Math.floor( i / gCols ); layerOf[ i ] = ( gc[ i ] + gr[ i ] * 2 ) % LAY; }
		var W = 1, H = 1, cardW = 0, D = 0, pos = [];
		function layout() {
			W = stage.clientWidth || el.clientWidth || 1; H = stage.clientHeight || W * 0.6;
			cardW = Math.max( 16, W * cardPct );
			D = W * depthP;
			stage.style.perspective = ( W * 1.2 ) + 'px';
			cards.forEach( function ( c ) { c.style.width = cardW + 'px'; c.style.marginLeft = ( -cardW / 2 ) + 'px'; } );
			cards.forEach( function ( c ) { c.style.marginTop = ( -( c.offsetHeight || cardW ) / 2 ) + 'px'; } );
			pos = [];
			var P = W * 1.2;
			for ( var i = 0; i < N; i++ ) {
				var z = -layerOf[ i ] * D, grow = ( P - z ) / P;                   // push back, then scale the position so it projects onto its cell
				var jx = ( orbitHash( i + 5 ) - 0.5 ) * 0.35, jy = ( orbitHash( i + 9 ) - 0.5 ) * 0.35;
				var xs = ( ( gc[ i ] + 0.5 + jx + ( gr[ i ] % 2 ? 0.25 : -0.25 ) ) / gCols - 0.5 ) * W * 0.82;
				var ys = ( ( gr[ i ] + 0.5 + jy ) / gRows - 0.5 ) * H * 0.72;
				pos.push( { x: xs * grow, y: ys * grow, z: z } );
			}
		}
		var st = { S: 0 };
		function applyFrame() {
			// Ping-pong through the layers: 0,1,…,L-1,…,1,0,… one stop per Loop Duration.
			var span = 2 * ( LAY - 1 ), p = Math.floor( st.S ), f = st.S - p;
			var at = function ( q ) { q = ( ( q % span ) + span ) % span; q = q < LAY ? q : span - q; return fromBack ? LAY - 1 - q : q; };
			var focus = at( p ) + ( at( p + 1 ) - at( p ) ) * easeInOut( clamp( f / move, 0, 1 ) );
			var dx = Math.sin( st.S * Math.PI * 0.35 ) * W * 0.06 * drift;
			for ( var i = 0; i < N; i++ ) {
				var d = Math.abs( layerOf[ i ] - focus ), q = pos[ i ];
				var c = cards[ i ];
				c.style.transform = 'translate3d(' + ( q.x + dx * ( 1 + layerOf[ i ] * 0.6 ) ).toFixed( 1 ) + 'px,' + q.y.toFixed( 1 ) + 'px,' + q.z.toFixed( 1 ) + 'px)';
				c.style.filter = 'blur(' + ( Math.min( 1, d ) * blurMax + Math.max( 0, d - 1 ) * blurMax * 0.5 ).toFixed( 2 ) + 'px) brightness(' + ( 1 - dim * Math.min( 1, d ) ).toFixed( 3 ) + ')';
				c.style.opacity = ( layerOf[ i ] < focus - 0.05 ? 1 - 0.45 * Math.min( 1, focus - layerOf[ i ] ) : 1 ).toFixed( 3 );
				c.style.zIndex = String( 10 - layerOf[ i ] );
			}
		}
		layout();
		applyFrame();
		window.addEventListener( 'resize', function () { layout(); applyFrame(); }, { passive: true } );
		el.setAttribute( 'data-tdg-allowdrag', '0' );
		orbitDrive( el, stage, st, speed, drive, applyFrame );
	}

	/* Loupe (Spotlight & Focus) — a mosaic of small photos with a magnifying lens gliding over it; inside
	 * the lens the same photos show enlarged (Zoom). With Follow Pointer, hovering moves the lens with the
	 * pointer. The lens holds plain copies of the cards (aria-hidden, not clickable — clicks reach the
	 * real cards beneath). An original design. */
	function initLoupe( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var grid  = el.querySelector( '.tdg__grid' );
		if ( ! stage || ! grid ) { return; }
		var cards = Array.prototype.slice.call( grid.querySelectorAll( '.tdg__card' ) );
		var N = cards.length;
		if ( ! N ) { return; }
		var drive   = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed   = num( el, 'data-tdg-speed', 12 );
		var cols    = Math.round( clamp( num( el, 'data-tdg-cols', 6 ), 2, 14 ) );
		var rows    = Math.ceil( N / cols );
		var gapPct  = clamp( num( el, 'data-tdg-gap', 8 ), 0, 60 ) / 100;
		var lensP   = clamp( num( el, 'data-tdg-lens', 24 ), 8, 60 ) / 100;
		var zoom    = clamp( num( el, 'data-tdg-zoom', 220 ), 120, 500 ) / 100;
		var follow  = num( el, 'data-tdg-follow', 1 ) === 1;
		var roam    = clamp( num( el, 'data-tdg-roam', 1 ), 0.2, 1 );
		var cardPct = clamp( num( el, 'data-tdg-card', 10 ), 3, 30 ) / 100;

		// The lens: a circle holding a copy of the mosaic, scaled up around the lens centre.
		var lens = document.createElement( 'div' ); lens.className = 'tdg__lens'; lens.setAttribute( 'aria-hidden', 'true' );
		var inner = document.createElement( 'div' ); inner.className = 'tdg__lensinner';
		var copies = cards.map( function ( c ) {
			var d = document.createElement( 'div' ); d.className = 'tdg__lenscard'; d.innerHTML = c.innerHTML; inner.appendChild( d ); return d;
		} );
		lens.appendChild( inner );
		stage.appendChild( lens );

		var W = 1, cardW = 0, L = null, R = 0, gw = 1, gh = 1, lx = 0, ly = 0;
		function layout() {
			W = stage.clientWidth || el.clientWidth || 1;
			cardW = Math.max( 12, W * cardPct );
			grid.style.transform = 'none';
			L = gridLayout( cards, cols, rows, cardW, gapPct );
			gw = ( cols - 1 ) * L.px / 2; gh = ( rows - 1 ) * L.py / 2;
			R = W * lensP / 2;
			lens.style.width = lens.style.height = ( 2 * R ) + 'px';
			lens.style.marginLeft = lens.style.marginTop = ( -R ) + 'px';
			copies.forEach( function ( d, i ) {
				d.style.width = cardW + 'px'; d.style.height = L.h + 'px';
				d.style.left = ( L.pos[ i ].x - cardW / 2 ) + 'px'; d.style.top = ( L.pos[ i ].y - L.h / 2 ) + 'px';
			} );
		}
		var pt = follow ? pointerTracker( el, grid ) : { hover: false };
		var st = { S: 0 };
		function applyFrame() {
			var tx = Math.sin( st.S * Math.PI * 2 * 0.5 ) * gw * 0.9 * roam, ty = Math.sin( st.S * Math.PI * 2 * 0.37 + 0.8 ) * gh * 0.9 * roam;
			if ( pt.hover ) { tx = pt.x; ty = pt.y; }
			lx += ( tx - lx ) * 0.25; ly += ( ty - ly ) * 0.25;
			lens.style.transform = 'translate3d(' + lx.toFixed( 1 ) + 'px,' + ly.toFixed( 1 ) + 'px,0)';
			// Inner origin = the stage centre; place it so the point under the lens centre sits at the lens centre.
			inner.style.transform = 'translate(' + ( R - lx * zoom ).toFixed( 1 ) + 'px,' + ( R - ly * zoom ).toFixed( 1 ) + 'px) scale(' + zoom + ')';
		}
		layout();
		cards.forEach( function ( c, i ) { c.style.transform = 'translate3d(' + L.pos[ i ].x.toFixed( 1 ) + 'px,' + L.pos[ i ].y.toFixed( 1 ) + 'px,0)'; } );
		applyFrame();
		window.addEventListener( 'resize', function () {
			layout();
			cards.forEach( function ( c, i ) { c.style.transform = 'translate3d(' + L.pos[ i ].x.toFixed( 1 ) + 'px,' + L.pos[ i ].y.toFixed( 1 ) + 'px,0)'; } );
			applyFrame();
		}, { passive: true } );
		el.setAttribute( 'data-tdg-allowdrag', '0' );
		orbitDrive( el, stage, st, speed, drive, applyFrame );
		if ( follow && ( drive !== 'continuous' || reduce ) ) {
			var tick = function () { if ( pt.hover ) { applyFrame(); } requestAnimationFrame( tick ); };
			requestAnimationFrame( tick );
		}
	}

	/* ---- Stack & Scatter additions (Card Fan, Shuffle Deck, Polaroid Drop) ----------------------- */
	// Shared: size the cards (centred on the 0×0 .tdg__grid) and return their rendered height.
	function stackSize( cards, cardW ) {
		cards.forEach( function ( c ) { c.style.width = cardW + 'px'; c.style.marginLeft = ( -cardW / 2 ) + 'px'; } );
		var h = cards.length ? ( cards[ 0 ].offsetHeight || cardW ) : cardW;
		cards.forEach( function ( c ) { c.style.marginTop = ( -h / 2 ) + 'px'; } );
		return h;
	}
	function modN( a, n ) { return ( ( a % n ) + n ) % n; }

	/* Card Fan (Stack & Scatter) — the photos are held like a hand of playing cards, fanned around a pivot
	 * below them. A highlight sweeps along the hand (and back): the card it reaches rises out of the fan,
	 * straightens and comes forward while the rest dim. With Follow Pointer, the visitor picks the card by
	 * pointing at it. An original design. */
	function initCardFan( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var grid  = el.querySelector( '.tdg__grid' );
		if ( ! stage || ! grid ) { return; }
		var cards = Array.prototype.slice.call( grid.querySelectorAll( '.tdg__card' ) );
		var N = cards.length;
		if ( ! N ) { return; }
		var drive    = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed    = num( el, 'data-tdg-speed', 2.5 );
		var dir      = num( el, 'data-tdg-dir', 1 );
		var spread   = clamp( num( el, 'data-tdg-spread', 70 ), 10, 200 );
		var pivot    = clamp( num( el, 'data-tdg-pivot', 120 ), 30, 400 ) / 100;
		var lift     = clamp( num( el, 'data-tdg-lift', 35 ), 0, 90 ) / 100;
		var straight = clamp( num( el, 'data-tdg-straight', 60 ), 0, 100 ) / 100;
		var dim      = clamp( num( el, 'data-tdg-dim', 25 ), 0, 90 ) / 100;
		var sway     = clamp( num( el, 'data-tdg-sway', 3 ), 0, 20 );
		var move     = clamp( num( el, 'data-tdg-move', 45 ), 10, 90 ) / 100;
		var follow   = num( el, 'data-tdg-follow', 1 ) === 1;
		var cardPct  = clamp( num( el, 'data-tdg-card', 20 ), 4, 50 ) / 100;
		var RAD = Math.PI / 180;
		var span = Math.max( 1, N - 1 ), L2 = 2 * span;
		function angleOf( i ) { return N === 1 ? 0 : -spread / 2 + spread * i / span; }
		// The highlight's position along the hand at step k: 0 → N-1 → 0 (ping-pong), reversed for Right first.
		function posAt( k ) { var m = modN( k, L2 ), q = m <= span ? m : L2 - m; return dir < 0 ? span - q : q; }

		var cardW = 0, h = 0, P = 0, offY = 0, Fs = null;
		function layout() {
			var W = stage.clientWidth || el.clientWidth || 1, H = stage.clientHeight || el.clientHeight || W;
			cardW = Math.max( 20, W * cardPct );
			var top = 0, bottom = 0;
			// Measure the whole hand (the raised card's top, the outer cards' lowest corner); everything
			// scales with the card size, so a hand taller than the stage is shrunk once to fit it.
			for ( var pass = 0; pass < 2; pass++ ) {
				h = stackSize( cards, cardW );
				P = h / 2 + pivot * h;
				top = -h / 2 - lift * h; bottom = h / 2;
				for ( var i = 0; i < N; i++ ) {
					var a = angleOf( i ) * RAD, cs = Math.cos( a ), sn = Math.sin( a );
					[ [ -1, -1 ], [ 1, -1 ], [ -1, 1 ], [ 1, 1 ] ].forEach( function ( q ) {
						var x = q[ 0 ] * cardW / 2, y = q[ 1 ] * h / 2 - P, yy = P + x * sn + y * cs;
						top = Math.min( top, yy ); bottom = Math.max( bottom, yy );
					} );
				}
				if ( pass || bottom - top <= H * 0.92 ) { break; }
				cardW = Math.max( 20, cardW * H * 0.92 / ( bottom - top ) );
			}
			offY = -( top + bottom ) / 2;
			grid.style.transform = 'translate3d(0,' + offY.toFixed( 1 ) + 'px,0)';
		}
		var pt = follow ? pointerTracker( el, grid ) : { hover: false };
		var st = { S: 0 };
		function applyFrame() {
			var p = Math.floor( st.S ), f = st.S - p, t = easeInOut( clamp( f / move, 0, 1 ) );
			var a0 = posAt( p ), a1 = posAt( p + 1 ), F = a0 + ( a1 - a0 ) * t;
			if ( pt.hover && N > 1 ) {
				var ang = Math.atan2( pt.x, P - pt.y ) / RAD;
				F = clamp( ( ang + spread / 2 ) / spread * span, 0, span );
			}
			Fs = Fs === null ? F : Fs + ( F - Fs ) * 0.2;
			var sw = sway * Math.sin( st.S * 1.1 );
			for ( var i = 0; i < N; i++ ) {
				var d = Math.abs( i - Fs ), k = d < 1 ? ( 1 + Math.cos( Math.PI * d ) ) / 2 : 0;
				var a = angleOf( i ) + sw, c = cards[ i ];
				c.style.transform = 'translate3d(0,' + P.toFixed( 1 ) + 'px,0) rotate(' + a.toFixed( 2 ) + 'deg) translate3d(0,' + ( -P - lift * h * k ).toFixed( 1 ) + 'px,0) rotate(' + ( -a * straight * k ).toFixed( 2 ) + 'deg) scale(' + ( 1 + 0.06 * k ).toFixed( 3 ) + ')';
				c.style.filter = dim ? 'brightness(' + ( 1 - dim * ( 1 - k ) ).toFixed( 3 ) + ')' : '';
				c.style.zIndex = String( i + Math.round( k * 100 ) );
			}
		}
		layout();
		applyFrame();
		window.addEventListener( 'resize', function () { layout(); applyFrame(); }, { passive: true } );
		el.setAttribute( 'data-tdg-allowdrag', '0' );
		orbitDrive( el, stage, st, speed, drive, applyFrame );
		if ( follow && ( drive !== 'continuous' || reduce ) ) {
			var tick = function () { if ( pt.hover || ( Fs !== null && Math.abs( Fs - posAt( Math.floor( st.S ) ) ) > 0.002 ) ) { applyFrame(); } requestAnimationFrame( tick ); };
			requestAnimationFrame( tick );
		}
	}

	/* Shuffle Deck (Stack & Scatter) — a loose deck of photos, the ones behind peeking out at slight
	 * angles. The top photo slides out to the side, then tucks back in at the bottom of the deck while the
	 * others move up one place, like a hand shuffling a deck. An original design. */
	function initShuffleDeck( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var grid  = el.querySelector( '.tdg__grid' );
		if ( ! stage || ! grid ) { return; }
		var cards = Array.prototype.slice.call( grid.querySelectorAll( '.tdg__card' ) );
		var N = cards.length;
		if ( ! N ) { return; }
		var drive   = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed   = num( el, 'data-tdg-speed', 3 );
		var side    = num( el, 'data-tdg-side', 0 );                          // 0 = alternate, 1 = right, -1 = left
		var behind  = Math.round( clamp( num( el, 'data-tdg-behind', 4 ), 1, 8 ) );
		var off     = clamp( num( el, 'data-tdg-offset', 4 ), 0, 20 ) / 100;
		var jitter  = clamp( num( el, 'data-tdg-jitter', 4 ), 0, 20 );
		var slide   = clamp( num( el, 'data-tdg-slide', 75 ), 30, 160 ) / 100;
		var tilt    = clamp( num( el, 'data-tdg-tilt', 10 ), 0, 45 );
		var move    = clamp( num( el, 'data-tdg-move', 55 ), 15, 95 ) / 100;
		var cardPct = clamp( num( el, 'data-tdg-card', 34 ), 6, 70 ) / 100;
		var deep = Math.min( behind, N - 1 );

		var cardW = 0, h = 0;
		function layout() {
			var W = stage.clientWidth || el.clientWidth || 1, H = stage.clientHeight || el.clientHeight || W;
			cardW = Math.max( 20, W * cardPct );
			h = stackSize( cards, cardW );
			var ext = h * 1.05 + deep * off * cardW;                             // the deck's height: top card + the steps behind it
			if ( ext > H * 0.88 ) { cardW = Math.max( 20, cardW * H * 0.88 / ext ); h = stackSize( cards, cardW ); }
			var shift = deep * off * cardW / 2;                                // centre the deck, not just the top card
			grid.style.transform = 'translate3d(' + ( -shift ).toFixed( 1 ) + 'px,' + shift.toFixed( 1 ) + 'px,0)';
		}
		// The resting pose of the card `i` in slot `s` (0 = top). Deeper slots step up-right, shrink a little
		// and lean by the card's own jitter; anything past the last visible slot waits there, hidden.
		function slotPose( s, i ) {
			var v = Math.min( s, deep );
			return { x: v * off * cardW, y: -v * off * cardW, r: jitter * ( orbitHash( i + 5 ) * 2 - 1 ) * Math.min( v, 1 ), s: 1 - v * 0.035, o: s > deep ? 0 : 1 };
		}
		function mix( a, b, t ) { return { x: a.x + ( b.x - a.x ) * t, y: a.y + ( b.y - a.y ) * t, r: a.r + ( b.r - a.r ) * t, s: a.s + ( b.s - a.s ) * t, o: a.o + ( b.o - a.o ) * t }; }
		function put( c, q, z ) {
			c.style.transform = 'translate3d(' + q.x.toFixed( 1 ) + 'px,' + q.y.toFixed( 1 ) + 'px,0) rotate(' + q.r.toFixed( 2 ) + 'deg) scale(' + q.s.toFixed( 3 ) + ')';
			c.style.opacity = q.o.toFixed( 3 );
			c.style.zIndex = String( z );
			c.style.visibility = q.o < 0.01 ? 'hidden' : '';
		}
		var st = { S: 0 };
		function applyFrame() {
			var p = Math.floor( st.S ), f = st.S - p, u = clamp( f / move, 0, 1 );
			var sd = side || ( modN( p, 2 ) ? -1 : 1 );
			var t2 = easeInOut( clamp( ( u - 0.5 ) * 2, 0, 1 ) );
			for ( var i = 0; i < N; i++ ) {
				var s0 = modN( i - p, N ), c = cards[ i ];
				if ( N === 1 ) { put( c, slotPose( 0, i ), 100 ); continue; }
				if ( s0 === 0 ) {
					var top = slotPose( 0, i ), out = { x: sd * slide * cardW, y: -0.05 * h, r: sd * tilt, s: 1.03, o: 1 };
					if ( u < 0.5 ) { put( c, mix( top, out, easeInOut( u * 2 ) ), 300 ); }
					else { put( c, mix( out, slotPose( N - 1, i ), t2 ), 1 ); }
				} else {
					var q = mix( slotPose( s0, i ), slotPose( s0 - 1, i ), t2 );
					put( c, q, 200 - Math.round( ( s0 - t2 ) * 10 ) );
				}
			}
		}
		layout();
		applyFrame();
		window.addEventListener( 'resize', function () { layout(); applyFrame(); }, { passive: true } );
		el.setAttribute( 'data-tdg-allowdrag', '0' );
		orbitDrive( el, stage, st, speed, drive, applyFrame );
	}

	/* Polaroid Drop (Stack & Scatter) — instant-photo prints drop one by one onto a growing pile, each
	 * landing at its own spot and angle with a small bounce (from above, or tossed in from the camera).
	 * When the pile is full the oldest print slides away as the next lands, or the whole pile is swept
	 * off the table and a new one starts. An original design. */
	function initPolaroidDrop( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var grid  = el.querySelector( '.tdg__grid' );
		if ( ! stage || ! grid ) { return; }
		var cards = Array.prototype.slice.call( grid.querySelectorAll( '.tdg__card' ) );
		var M = cards.length;
		if ( ! M ) { return; }
		var drive   = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed   = num( el, 'data-tdg-speed', 2.5 );
		var camera  = el.getAttribute( 'data-tdg-from' ) === 'camera';
		var pile    = Math.round( clamp( num( el, 'data-tdg-pile', 6 ), 1, 20 ) );
		var scatter = clamp( num( el, 'data-tdg-scatter', 45 ), 0, 200 ) / 100;
		var tilt    = clamp( num( el, 'data-tdg-tilt', 14 ), 0, 45 );
		var sweep   = num( el, 'data-tdg-sweep', 0 ) === 1;
		var move    = clamp( num( el, 'data-tdg-move', 45 ), 15, 95 ) / 100;
		var cardPct = clamp( num( el, 'data-tdg-card', 24 ), 6, 60 ) / 100;
		pile = Math.min( pile, sweep ? M : Math.max( 1, M - 1 ) );

		var cardW = 0, h = 0, H = 0;
		function layout() {
			var W = stage.clientWidth || el.clientWidth || 1;
			H = stage.clientHeight || el.clientHeight || W * 0.5;
			cardW = Math.max( 20, W * cardPct );
			h = stackSize( cards, cardW );
			var ext = h * 1.1 + scatter * cardW * 1.1;                           // a print plus how far the pile spreads up and down
			if ( ext > H * 0.9 ) { cardW = Math.max( 20, cardW * H * 0.9 / ext ); h = stackSize( cards, cardW ); }
		}
		// Where drop number `d` lands (stable per drop, so every pass lays a different-looking pile).
		function spot( d ) {
			return { x: ( orbitHash( d * 3 + 1 ) * 2 - 1 ) * scatter * cardW, y: ( orbitHash( d * 3 + 2 ) * 2 - 1 ) * scatter * cardW * 0.55, r: ( orbitHash( d * 3 + 3 ) * 2 - 1 ) * tilt };
		}
		function put( c, x, y, r, s, o, z ) {
			c.style.transform = 'translate3d(' + x.toFixed( 1 ) + 'px,' + y.toFixed( 1 ) + 'px,0) rotate(' + r.toFixed( 2 ) + 'deg) scale(' + s.toFixed( 3 ) + ')';
			c.style.opacity = o.toFixed( 3 );
			c.style.zIndex = String( z );
			c.style.visibility = o < 0.01 ? 'hidden' : '';
		}
		function hide( c ) { put( c, 0, 0, 0, 1, 0, 0 ); }
		// A print in the air (u 0→1): falls in (gravity), lands on its spot, then a small bounce settles it.
		function dropping( c, d, u, z ) {
			var g = spot( d ), fall = clamp( u / 0.8, 0, 1 ), e = fall * fall, bounce = u > 0.8 ? Math.sin( Math.PI * ( u - 0.8 ) / 0.2 ) : 0;
			var turn = ( orbitHash( d * 7 ) > 0.5 ? 1 : -1 ) * 28 * ( 1 - e );
			if ( camera ) {
				put( c, g.x * e, g.y * e, g.r + turn, 1 + 1.7 * ( 1 - e ) - 0.035 * bounce, Math.min( 1, u * 5 ), z );
			} else {
				var y0 = -H / 2 - h;
				put( c, g.x, y0 + ( g.y - y0 ) * e - 0.06 * h * bounce, g.r + turn, 1 + 0.12 * ( 1 - e ), Math.min( 1, u * 8 ), z );
			}
		}
		var st = { S: 0 };
		function applyFrame() {
			var S = st.S + pile - 1, p = Math.floor( S ), f = S - p, u = clamp( f / move, 0, 1 );
			for ( var j = 0; j < M; j++ ) {
				var c = cards[ j ], g;
				if ( ! sweep ) {
					var d = p - modN( p - j, M ), age = p - d;
					if ( d < 0 || age > pile ) { hide( c ); continue; }
					g = spot( d );
					if ( age === 0 ) { dropping( c, d, u, 1000 ); }
					else if ( age < pile ) { put( c, g.x, g.y, g.r, 1, 1, 1000 - age * 10 ); }
					else { var e = easeInOut( u ); put( c, g.x, g.y + e * 0.15 * h, g.r, 1 - 0.06 * e, 1 - e, 1 ); }   // the oldest leaves
				} else {
					var C = pile + 1, cyc = Math.floor( p / C ), k = modN( p, C ), m = modN( j - cyc * pile, M );
					if ( m >= pile ) { hide( c ); continue; }
					var dd = cyc * pile + m;
					g = spot( dd );
					if ( k < pile ) {
						if ( m < k ) { put( c, g.x, g.y, g.r, 1, 1, 10 + m * 10 ); }
						else if ( m === k ) { dropping( c, dd, u, 10 + m * 10 ); }
						else { hide( c ); }
					} else {                                                     // sweep the whole pile off the table
						var us = easeInOut( clamp( ( u - m * 0.03 ) / Math.max( 0.3, 1 - pile * 0.03 ), 0, 1 ) );
						put( c, g.x + us * ( stage.clientWidth || 800 ) * 0.8, g.y + us * 0.1 * h, g.r + us * 30, 1, 1 - clamp( ( us - 0.4 ) / 0.6, 0, 1 ), 10 + m * 10 );
					}
				}
			}
		}
		layout();
		applyFrame();
		window.addEventListener( 'resize', function () { layout(); applyFrame(); }, { passive: true } );
		el.setAttribute( 'data-tdg-allowdrag', '0' );
		orbitDrive( el, stage, st, speed, drive, applyFrame );
	}

	/* ---- Devices & Screens additions (Phone Lineup, Window Stack, Device Trio) ------------------- */
	/* Phone Lineup (Devices & Screens) — every photo on the screen of a phone; the phones stand in a shallow
	 * V (the centre one nearest, the others stepping back and turning toward it) and the lineup turns like a
	 * carousel, phones fading out at one end and back in at the other. Stop at Centre holds each phone in
	 * the middle; visitors can drag the lineup. An original design. */
	function initPhoneLineup( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var grid  = el.querySelector( '.tdg__grid' );
		if ( ! stage || ! grid ) { return; }
		var cards = Array.prototype.slice.call( grid.querySelectorAll( '.tdg__card' ) );
		var N = cards.length;
		if ( ! N ) { return; }
		var drive   = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed   = num( el, 'data-tdg-speed', 3 );
		var dir     = num( el, 'data-tdg-dir', 1 );
		var spacing = clamp( num( el, 'data-tdg-spacing', 70 ), 20, 200 ) / 100;
		var depth   = clamp( num( el, 'data-tdg-depth', 50 ), 0, 150 ) / 100;
		var turn    = clamp( num( el, 'data-tdg-turn', 25 ), 0, 80 );
		var bob     = clamp( num( el, 'data-tdg-bob', 3 ), 0, 12 ) / 100;
		var snap    = num( el, 'data-tdg-snap', 1 ) === 1;
		var move    = clamp( num( el, 'data-tdg-move', 45 ), 10, 95 ) / 100;
		var cardPct = clamp( num( el, 'data-tdg-card', 16 ), 4, 40 ) / 100;
		var half = N / 2;
		el.setAttribute( 'data-tdg-dragsign', String( -dir ) );                // dragging right always moves the phones right

		var thick   = clamp( num( el, 'data-tdg-thick', 8 ), 0, 20 ) / 100;
		var entr    = el.getAttribute( 'data-tdg-entrance' );
		var hasEnt  = !! entr && entr !== 'none' && ! reduce;
		var inners  = cards.map( function ( c ) { return c.querySelector( '.tdg__inner' ); } );

		// The phone's body: thin strips standing round the rounded outline (the metal side, following the
		// corners), the buttons, and the back. They hang off the card (a 3D parent), so they turn with it.
		var bodies = [], last = [];
		function buildBodies() {
			bodies.forEach( function ( list ) { list.forEach( function ( q ) { q.el.parentNode && q.el.parentNode.removeChild( q.el ); } ); } );
			bodies = []; last = [];
			if ( ! thick ) { return; }
			var w = cardW, T = Math.max( 2, w * thick ), R = Math.min( Math.max( 3, w * 0.045 ) * 3.6, w / 2, h / 2 ), segs = [];
			function seg( x1, y1, x2, y2, out, dz ) {
				segs.push( { x: ( x1 + x2 ) / 2 + out[ 0 ], y: ( y1 + y2 ) / 2 + out[ 1 ], L: Math.sqrt( ( x2 - x1 ) * ( x2 - x1 ) + ( y2 - y1 ) * ( y2 - y1 ) ) + 0.6,
					a: Math.atan2( y2 - y1, x2 - x1 ) * 180 / Math.PI, d: dz } );
			}
			seg( R, 0, w - R, 0, [ 0, 0 ], 1 ); seg( w, R, w, h - R, [ 0, 0 ], 1 ); seg( w - R, h, R, h, [ 0, 0 ], 1 ); seg( 0, h - R, 0, R, [ 0, 0 ], 1 );
			[ [ R, R, 180 ], [ w - R, R, 270 ], [ w - R, h - R, 0 ], [ R, h - R, 90 ] ].forEach( function ( cn ) {
				for ( var k = 0; k < 5; k++ ) {
					var a1 = ( cn[ 2 ] + 18 * k ) * Math.PI / 180, a2 = ( cn[ 2 ] + 18 * ( k + 1 ) ) * Math.PI / 180;
					seg( cn[ 0 ] + R * Math.cos( a1 ), cn[ 1 ] + R * Math.sin( a1 ), cn[ 0 ] + R * Math.cos( a2 ), cn[ 1 ] + R * Math.sin( a2 ), [ 0, 0 ], 1 );
				}
			} );
			// Buttons: action + volume on the left, power on the right, standing slightly proud of the side.
			var bo = Math.max( 1, T * 0.2 );
			[ [ 0.17, 0.21 ], [ 0.25, 0.33 ], [ 0.36, 0.44 ] ].forEach( function ( b ) { seg( 0, h * b[ 1 ], 0, h * b[ 0 ], [ -bo, 0 ], 0.5 ); } );
			seg( w, h * 0.27, w, h * 0.39, [ bo, 0 ], 0.5 );
			cards.forEach( function ( c ) {
				var list = [];
				segs.forEach( function ( s ) {
					var e = document.createElement( 'i' ), D = T * s.d;
					e.className = 'tdg__phside';
					e.setAttribute( 'aria-hidden', 'true' );
					e.style.width = s.L.toFixed( 1 ) + 'px'; e.style.height = D.toFixed( 1 ) + 'px';
					e.style.marginLeft = ( -s.L / 2 ).toFixed( 1 ) + 'px'; e.style.marginTop = ( -D / 2 ).toFixed( 1 ) + 'px';
					// Light from above: the top side brightest, the bottom darkest.
					e.style.filter = 'brightness(' + ( 0.78 - 0.32 * Math.sin( ( s.a - 90 ) * Math.PI / 180 ) ).toFixed( 3 ) + ')';
					c.insertBefore( e, c.firstChild );                          // before the screen: on a depth tie the screen wins
					list.push( { el: e, ox: w / 2, oy: h / 2, base: 'translate3d(' + s.x.toFixed( 1 ) + 'px,' + s.y.toFixed( 1 ) + 'px,' + ( -T / 2 ).toFixed( 1 ) + 'px) rotateZ(' + s.a.toFixed( 2 ) + 'deg) rotateX(90deg)' } );
				} );
				var bk = document.createElement( 'i' );
				bk.className = 'tdg__phback';
				bk.setAttribute( 'aria-hidden', 'true' );
				bk.style.borderRadius = R.toFixed( 1 ) + 'px';
				c.insertBefore( bk, c.firstChild );
				list.push( { el: bk, ox: 0, oy: 0, base: 'translate3d(0,0,' + ( -T ).toFixed( 1 ) + 'px) rotateY(180deg)' } );
				bodies.push( list );
			} );
			syncBodies();
		}
		// The per-card Entrance poses .tdg__inner (opacity + transform); the body copies it, so it appears with the screen.
		// The screen (.tdg__inner) is what the entrances animate: the Style-tab Entrance with inline styles,
		// the Animations tab's Entrance / Exit Animation with CSS classes (read back as computed values while
		// they run). The body copies the screen's opacity and transform, pivoting about the phone's centre
		// (ox/oy: the centre relative to each part's own origin), so the whole phone animates as one.
		function screenState( inr ) {
			if ( ! inr ) { return [ '', '' ]; }
			var cl = inr.classList;
			if ( cl.contains( 'sc-anim-pending' ) || cl.contains( 'sc-anim-gone' ) ) { return [ '0', '' ]; }
			if ( cl.contains( 'animate__animated' ) ) {
				var cs = window.getComputedStyle( inr ), t = cs.transform;
				return [ cs.opacity, t && t !== 'none' ? t : '' ];
			}
			if ( hasEnt ) { return [ inr.style.opacity !== '' ? inr.style.opacity : '0', inr.style.transform || '' ]; }
			return [ '', '' ];
		}
		function syncBodies() {
			for ( var i = 0; i < bodies.length; i++ ) {
				var ss = screenState( inners[ i ] ), op = ss[ 0 ], tr = ss[ 1 ];
				var key = op + '|' + tr;
				if ( last[ i ] === key ) { continue; }
				last[ i ] = key;
				for ( var j = 0; j < bodies[ i ].length; j++ ) {
					var q = bodies[ i ][ j ];
					q.el.style.opacity = op;
					q.el.style.transform = ( tr ? 'translate(' + q.ox.toFixed( 1 ) + 'px,' + q.oy.toFixed( 1 ) + 'px) ' + tr + ' translate(' + ( -q.ox ).toFixed( 1 ) + 'px,' + ( -q.oy ).toFixed( 1 ) + 'px) ' : '' ) + q.base;
				}
			}
		}

		var cardW = 0, h = 0;
		function layout() {
			var W = stage.clientWidth || el.clientWidth || 1, H = stage.clientHeight || el.clientHeight || W;
			cardW = Math.max( 20, W * cardPct );
			h = stackSize( cards, cardW );
			if ( h * ( 1 + 2 * bob ) > H * 0.9 ) { cardW = Math.max( 20, cardW * H * 0.9 / ( h * ( 1 + 2 * bob ) ) ); h = stackSize( cards, cardW ); }
			el.style.setProperty( '--tdg-bez', Math.max( 3, cardW * 0.045 ).toFixed( 1 ) + 'px' );
			stage.style.perspective = ( W * 1.4 ) + 'px';
			buildBodies();
		}
		var st = { S: 0 };
		function applyFrame() {
			var S = st.S * dir, p = Math.floor( S ), f = S - p;
			var Se = snap ? p + easeInOut( clamp( f / move, 0, 1 ) ) : S;
			var tb = st.S * 1.7;
			for ( var i = 0; i < N; i++ ) {
				var u = modN( i - Se + half, N ) - half, au = Math.abs( u ), c = cards[ i ];
				var x = u * spacing * cardW, z = -au * depth * cardW, ry = -clamp( u, -2.2, 2.2 ) * turn;
				var y = Math.sin( tb + i * 1.3 ) * bob * h;
				// Shrink away at one end and grow back at the other (not an opacity fade: opacity would flatten the 3D body).
				var sc = easeInOut( clamp( ( half - au ) / 0.9, 0, 1 ) );
				c.style.transform = 'translate3d(' + x.toFixed( 1 ) + 'px,' + y.toFixed( 1 ) + 'px,' + z.toFixed( 1 ) + 'px) rotateY(' + ry.toFixed( 2 ) + 'deg) scale(' + sc.toFixed( 3 ) + ')';
				c.style.visibility = sc < 0.02 ? 'hidden' : '';
			}
			syncBodies();
		}
		layout();
		applyFrame();
		window.addEventListener( 'resize', function () { layout(); applyFrame(); }, { passive: true } );
		orbitDrive( el, stage, st, speed, drive, applyFrame );
		if ( bodies.length ) {                                                   // keep following the screen when nothing else animates
			var sync = function () { syncBodies(); requestAnimationFrame( sync ); };
			requestAnimationFrame( sync );
		}
	}

	/* Window Stack (Devices & Screens) — browser windows stacked in a tilted 3D pile, each one set back and
	 * up from the one in front. The front window leaves (toward the visitor, or dropping away) and the
	 * others move forward one place while it reappears at the back. An original design. */
	function initWindowStack( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var grid  = el.querySelector( '.tdg__grid' );
		if ( ! stage || ! grid ) { return; }
		var cards = Array.prototype.slice.call( grid.querySelectorAll( '.tdg__card' ) );
		var N = cards.length;
		if ( ! N ) { return; }
		var drive   = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed   = num( el, 'data-tdg-speed', 3 );
		var toward  = el.getAttribute( 'data-tdg-exit' ) !== 'down';
		var behind  = Math.round( clamp( num( el, 'data-tdg-behind', 4 ), 1, 8 ) );
		var gap     = clamp( num( el, 'data-tdg-gap', 30 ), 5, 100 ) / 100;
		var off     = clamp( num( el, 'data-tdg-offset', 8 ), 0, 30 ) / 100;
		var tilt    = clamp( num( el, 'data-tdg-tilt', 16 ), 0, 50 );
		var yaw     = clamp( num( el, 'data-tdg-yaw', 22 ), -45, 45 );
		var dim     = clamp( num( el, 'data-tdg-dim', 30 ), 0, 80 ) / 100;
		var move    = clamp( num( el, 'data-tdg-move', 55 ), 15, 95 ) / 100;
		var cardPct = clamp( num( el, 'data-tdg-card', 44 ), 10, 80 ) / 100;
		var deep = Math.min( behind, N - 1 );

		var cardW = 0, h = 0;
		function layout() {
			var W = stage.clientWidth || el.clientWidth || 1, H = stage.clientHeight || el.clientHeight || W;
			cardW = Math.max( 40, W * cardPct );
			h = stackSize( cards, cardW );
			var ext = h + deep * off * cardW * 0.7;
			if ( ext > H * 0.8 ) { cardW = Math.max( 40, cardW * H * 0.8 / ext ); h = stackSize( cards, cardW ); }
			el.style.setProperty( '--tdg-bez', Math.max( 3, cardW * 0.022 ).toFixed( 1 ) + 'px' );
			stage.style.perspective = ( W * 1.6 ) + 'px';
			var sx = -deep * off * cardW / 2, sy = deep * off * cardW * 0.35;
			grid.style.transform = 'translate3d(' + sx.toFixed( 1 ) + 'px,' + sy.toFixed( 1 ) + 'px,0) rotateX(' + tilt + 'deg) rotateY(' + ( -yaw ) + 'deg)';
		}
		function slot( s ) {
			var v = Math.min( s, deep );
			return { x: v * off * cardW, y: -v * off * cardW * 0.7, z: -v * gap * cardW, b: 1 - dim * ( v / Math.max( 1, deep ) ), o: s > deep ? 0 : 1 };
		}
		function put( c, q ) {
			c.style.transform = 'translate3d(' + q.x.toFixed( 1 ) + 'px,' + q.y.toFixed( 1 ) + 'px,' + q.z.toFixed( 1 ) + 'px)';
			c.style.opacity = q.o.toFixed( 3 );
			c.style.filter = q.b < 0.999 ? 'brightness(' + q.b.toFixed( 3 ) + ')' : '';
			c.style.visibility = q.o < 0.01 ? 'hidden' : '';
		}
		function mix( a, b, t ) { return { x: a.x + ( b.x - a.x ) * t, y: a.y + ( b.y - a.y ) * t, z: a.z + ( b.z - a.z ) * t, b: a.b + ( b.b - a.b ) * t, o: a.o + ( b.o - a.o ) * t }; }
		var st = { S: 0 };
		function applyFrame() {
			var p = Math.floor( st.S ), f = st.S - p, u = clamp( f / move, 0, 1 ), t = easeInOut( u );
			for ( var i = 0; i < N; i++ ) {
				var s0 = modN( i - p, N ), c = cards[ i ];
				if ( N === 1 ) { put( c, slot( 0 ) ); continue; }
				if ( s0 === 0 ) {
					if ( u < 0.55 ) {                                              // the front window leaves
						var e = easeInOut( u / 0.55 ), a = slot( 0 );
						put( c, { x: a.x, y: a.y + ( toward ? 0.15 : 0.9 ) * h * e, z: a.z + ( toward ? 0.9 : 0.1 ) * cardW * e, b: 1, o: 1 - e } );
					} else {                                                       // … and fades back in at the back
						var g = slot( N - 1 > deep ? deep : N - 1 ), e2 = easeInOut( ( u - 0.55 ) / 0.45 );
						put( c, { x: g.x, y: g.y, z: g.z - gap * cardW * ( 1 - e2 ), b: g.b, o: N - 1 > deep ? 0 : e2 } );
					}
				} else {
					put( c, mix( slot( s0 ), slot( s0 - 1 ), t ) );
				}
			}
		}
		layout();
		applyFrame();
		window.addEventListener( 'resize', function () { layout(); applyFrame(); }, { passive: true } );
		el.setAttribute( 'data-tdg-allowdrag', '0' );
		orbitDrive( el, stage, st, speed, drive, applyFrame );
	}

	/* Device Trio (Devices & Screens) — a laptop with a tablet and a phone floating in front of it, each
	 * screen showing photos; the screens change one device at a time, the devices float gently, and the
	 * group turns slightly toward the visitor's pointer (or sways on its own). An original design. */
	function initDeviceTrio( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var trio  = el.querySelector( '.tdg__trio' );
		if ( ! stage || ! trio ) { return; }
		var devs = Array.prototype.slice.call( trio.querySelectorAll( '.tdg__device' ) );
		if ( devs.length < 3 ) { return; }
		var screens = devs.map( function ( d ) { return Array.prototype.slice.call( d.querySelectorAll( '.tdg__card' ) ); } );
		var drive  = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed  = num( el, 'data-tdg-speed', 3 );
		var sway   = el.getAttribute( 'data-tdg-sway' ) !== '0';
		var size   = clamp( num( el, 'data-tdg-card', 48 ), 15, 90 ) / 100;
		var float  = clamp( num( el, 'data-tdg-float', 2 ), 0, 8 ) / 100;
		var depth  = clamp( num( el, 'data-tdg-depth', 30 ), 0, 100 ) / 100;
		var tiltMx = clamp( num( el, 'data-tdg-tilt', 10 ), 0, 30 );
		var follow = num( el, 'data-tdg-follow', 1 ) === 1;
		// Device sizes and spots, as fractions of the laptop width: laptop centre-back, tablet front-left, phone front-right.
		var SPEC = [ { w: 1, x: 0, y: -0.06, z: 0 }, { w: 0.34, x: -0.5, y: 0.13, z: 1 }, { w: 0.17, x: 0.52, y: 0.15, z: 1.4 } ];

		var LW = 0;
		function layout() {
			var W = stage.clientWidth || el.clientWidth || 1, H = stage.clientHeight || el.clientHeight || W;
			LW = W * size;
			devs.forEach( function ( d, k ) { d.style.width = ( LW * SPEC[ k ].w ) + 'px'; d.style.fontSize = ( LW * SPEC[ k ].w / 100 ) + 'px'; } );
			var hh = devs[ 0 ].offsetHeight || LW * 0.66;                      // the laptop's height (screen + base) sets the group height
			if ( hh * 1.28 > H * 0.9 ) {
				LW = LW * H * 0.9 / ( hh * 1.28 );
				devs.forEach( function ( d, k ) { d.style.width = ( LW * SPEC[ k ].w ) + 'px'; d.style.fontSize = ( LW * SPEC[ k ].w / 100 ) + 'px'; } );
			}
			devs.forEach( function ( d ) { d.style.marginLeft = ( -d.offsetWidth / 2 ) + 'px'; d.style.marginTop = ( -d.offsetHeight / 2 ) + 'px'; } );
			stage.style.perspective = ( W * 1.5 ) + 'px';
		}
		var pt = follow ? pointerTracker( el, stage ) : { hover: false };
		var st = { S: 0 }, rx = 0, ry = 0, shown = [ -1, -1, -1 ];
		function applyFrame() {
			var p = Math.floor( st.S );
			// Screens change one device at a time: step 1 changes the laptop, step 2 the tablet, step 3 the phone, …
			for ( var k = 0; k < 3; k++ ) {
				var list = screens[ k ], n = list.length;
				if ( ! n ) { continue; }
				var changes = p > k ? Math.floor( ( p - k - 1 ) / 3 ) + 1 : 0, a = modN( changes, n );
				if ( a !== shown[ k ] ) { shown[ k ] = a; for ( var j = 0; j < n; j++ ) { list[ j ].classList.toggle( 'is-active', j === a ); } }
			}
			var tx = 0, ty = 0;
			if ( pt.hover ) {
				var r = stage.getBoundingClientRect();
				ty = clamp( pt.x / ( r.width / 2 ), -1, 1 ) * tiltMx; tx = -clamp( pt.y / ( r.height / 2 ), -1, 1 ) * tiltMx * 0.6;
			} else if ( sway ) { ty = Math.sin( st.S * 0.9 ) * tiltMx * 0.6; tx = Math.sin( st.S * 0.6 ) * tiltMx * 0.2; }
			rx += ( tx - rx ) * 0.08; ry += ( ty - ry ) * 0.08;
			trio.style.transform = 'rotateX(' + rx.toFixed( 2 ) + 'deg) rotateY(' + ry.toFixed( 2 ) + 'deg)';
			for ( var d = 0; d < 3; d++ ) {
				var s = SPEC[ d ], bobY = Math.sin( st.S * 2.1 + d * 2.2 ) * float * LW;
				devs[ d ].style.transform = 'translate3d(' + ( s.x * LW ).toFixed( 1 ) + 'px,' + ( s.y * LW + bobY ).toFixed( 1 ) + 'px,' + ( s.z * depth * LW ).toFixed( 1 ) + 'px)';
			}
		}
		layout();
		applyFrame();
		window.addEventListener( 'resize', function () { layout(); applyFrame(); }, { passive: true } );
		el.setAttribute( 'data-tdg-allowdrag', '0' );
		orbitDrive( el, stage, st, speed, drive, applyFrame );
		if ( follow && ( drive !== 'continuous' || reduce ) ) {
			var tick = function () { if ( pt.hover || Math.abs( rx ) + Math.abs( ry ) > 0.01 ) { applyFrame(); } requestAnimationFrame( tick ); };
			requestAnimationFrame( tick );
		}
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
		var dTheta = 0, R = 0, zSign = 1, cardW = 0, cardH = 0, pos = 0, vel = 0, stepPx = 0, flat = false;

		function layout() {
			var W = stage.clientWidth || el.clientWidth || 1;
			cardW = Math.max( 30, W * cardPct );
			cardH = ( cards[ 0 ] && cards[ 0 ].offsetHeight ) ? cards[ 0 ].offsetHeight : cardW;
			var dim = horiz ? cardW : cardH;                    // cards step along the reel's own axis
			var step = dim + dim * gap;
			stepPx = step;
			// 3D Curve exactly 0 → a TRULY flat strip: cards just translate along the axis at z=0 with no
			// rotation. Any curve was previously clamped to a 0.06 minimum, leaving a ~2° tilt that
			// foreshortened each card and made its left/right edges shimmer as it slid.
			flat = ( curve === 0 );
			if ( flat ) {
				dTheta = 0; R = 0; zSign = 1;
			} else {
				var amt = clamp( Math.abs( curve ) / 100, 0.06, 1 );
				dTheta = amt * 34;                                 // degrees between cards on the cylinder
				var half = dTheta / 2 * Math.PI / 180;
				R = step / ( 2 * Math.max( 0.02, Math.sin( half ) ) ); // radius so neighbours sit edge-to-edge
				zSign = curve >= 0 ? -1 : 1;                       // negative curve = edges bend BACK (the default look)
			}
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
				if ( flat ) {
					// Flat strip — pure translation along the axis (no z, no rotation → no edge wobble).
					var sf = p * stepPx;
					c.style.transform = horiz ? ( 'translate3d(' + sf.toFixed( 1 ) + 'px,0,0)' ) : ( 'translate3d(0,' + sf.toFixed( 1 ) + 'px,0)' );
					c.style.opacity = '1';
					c.style.zIndex = String( 1000 - Math.round( Math.abs( p ) ) );
					continue;
				}
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
			var onScroll = function () { scrollY = dir * scrollProgress( el, stage ) * N * num( el, 'data-tdg-rounds', 1 ); };
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
			var onScroll = function () { scrollY = dir * scrollProgress( el, stage ) * N * num( el, 'data-tdg-rounds', 1 ); };
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
			var onScroll = function () { scrollY = dir * scrollProgress( el, stage ) * N * num( el, 'data-tdg-rounds', 1 ); };
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
				else if ( drive === 'scroll' && scrollY !== null ) { pos += ( scrollY - pos ) * 0.14; }
			}
			applyFlow();
			requestAnimationFrame( loop );
		};
		requestAnimationFrame( loop );
	}

	/* Mosaic Marquee — a horizontal marquee of cards packed masonry-style (varied row/column spans) that
	 * scrolls sideways forever. The tiles fill a fixed number of rows (derived from Card Height) via CSS
	 * grid dense packing; two identical copies of the grid ride a track that translates by exactly one
	 * copy's width and wraps, so the scroll is seamless. Layout 1/2/3 are three seeded span patterns. A
	 * flat 2D design (no perspective). */
	function initMosaic( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var track = el.querySelector( '.tdg__mosaic-track' );
		if ( ! stage || ! track ) { return; }
		var grids = Array.prototype.slice.call( track.querySelectorAll( '.tdg__mosaic' ) );
		if ( ! grids.length ) { return; }

		var drive = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed = num( el, 'data-tdg-speed', 20 );
		var dir   = num( el, 'data-tdg-dir', 1 );                 // +1 scroll left, -1 scroll right
		var cardH = clamp( num( el, 'data-tdg-card', 30 ), 10, 90 ) / 100;
		var gap   = clamp( num( el, 'data-tdg-gap', 0.5 ), 0, 8 ) / 100;
		var layout = String( el.getAttribute( 'data-tdg-layout' ) || '1' );
		var ratioW = num( el, 'data-tdg-rw', 1 ), ratioH = num( el, 'data-tdg-rh', 1 );
		var corner = num( el, 'data-tdg-corner', 18 );
		var seed = ( layout === '2' ) ? 3.3 : ( layout === '3' ) ? 7.1 : 0.5;

		function rnd( i, s ) { var x = Math.sin( ( i + 1 ) * 63.7 + s * 17.3 + seed * 41.2 ) * 43758.5453; return x - Math.floor( x ); }
		// Airy BENTO masonry on an R-row square grid (R≈2 at the default card height). Each step drops a
		// group into the column flow: a BIG 2×2 tile, a TALL 1×2 tile, a STACK of two 1×1 squares, or a
		// SINGLE 1×1 square top- OR bottom-aligned with an empty cell (background showing) beside it — the
		// exact floating-card variety of the reference. Deterministic per step ⇒ every copy matches ⇒
		// seamless loop. (R>2 falls back to a tall/short/gap partition per single column.)
		var pool = Array.prototype.slice.call( grids[ 0 ].querySelectorAll( '.tdg__card' ) ).map( function ( c ) { return c.cloneNode( true ); } );
		if ( ! pool.length ) { return; }
		var R = 2;
		var cellH = 0, colW = 0, gapPx = 0, setW = 0;
		// Column KINDS split into two categories: FULL-height (big 2×2, tall 1×2) and PARTIAL (stack of two
		// squares, or a single square + empty cell). Rules: (1) two PARTIAL columns are never adjacent — a
		// full-height column always sits between any two halves, so the grid never reads as "too many
		// halves"; (2) never the same family twice in a row. Deterministic per column ⇒ copies match ⇒
		// seamless loop.
		var CAT = { big: 'full', tall: 'full', stack: 'partial', single: 'partial' };
		var FREE = [ [ 'big', 0.26 ], [ 'tall', 0.24 ], [ 'stack', 0.28 ], [ 'single', 0.22 ] ];
		var FULLS = [ [ 'big', 0.55 ], [ 'tall', 0.45 ] ];
		function pickW( list, ci ) {
			var tot = list.reduce( function ( a, f ) { return a + f[ 1 ]; }, 0 );
			var r = rnd( ci * 11 + 5, 1 ) * tot, acc = 0;
			for ( var i = 0; i < list.length; i++ ) { acc += list[ i ][ 1 ]; if ( r < acc ) { return list[ i ][ 0 ]; } }
			return list[ list.length - 1 ][ 0 ];
		}
		function nextFamily( ci, lastFam, lastCat ) {
			// The FIRST column of a set (lastCat '') and any column after a PARTIAL one MUST be full-height —
			// the former keeps the wrap seam (…partial | full…) clean, the latter guarantees a full-height
			// column between any two halves. Otherwise choose freely. Always exclude the previous family so
			// no style repeats back-to-back.
			var forceFull = ( lastCat === 'partial' || lastCat === '' );
			var list = ( forceFull ? FULLS : FREE ).filter( function ( f ) { return f[ 0 ] !== lastFam; } );
			return pickW( list, ci );
		}
		function buildSet( grid, hidden ) {
			grid.innerHTML = '';
			var idx = 0, ci = 0, lastFam = '', lastCat = '', lastSrc = '', singleAlt = false;
			var srcOf = function ( c ) { var im = c.querySelector( 'img' ); return im ? ( im.getAttribute( 'src' ) || '' ) : ''; };
			var addCard = function ( rs, cs ) {
				// Skip an image identical to the previously placed one (the pool may repeat to pad small sets).
				if ( pool.length > 1 && srcOf( pool[ idx % pool.length ] ) === lastSrc ) { idx++; }
				var src0 = pool[ idx % pool.length ];
				var c = src0.cloneNode( true );
				c.setAttribute( 'aria-hidden', hidden ? 'true' : 'false' );
				c.style.borderRadius = corner + 'px'; c.style.overflow = 'hidden';
				c.style.gridRow = 'span ' + rs; c.style.gridColumn = 'span ' + cs;
				// A wide 2×2 "full square" carries the --wide flag so CSS can halve its % frame (a %-padding
				// resolves against width, so a double-width tile would otherwise get a double-thick frame).
				if ( cs === 2 ) { c.className += ' tdg__card--wide'; }
				lastSrc = srcOf( src0 ); grid.appendChild( c ); idx++;
			};
			var addGap = function ( rs, cs ) {
				var s = document.createElement( 'div' ); s.setAttribute( 'aria-hidden', 'true' );
				s.style.gridRow = 'span ' + rs; s.style.gridColumn = 'span ' + cs; grid.appendChild( s );
			};
			do {
				if ( R === 2 ) {
					var fam = nextFamily( ci, lastFam, lastCat ); lastFam = fam; lastCat = CAT[ fam ];
					if ( fam === 'big' ) { addCard( 2, 2 ); }                              // BIG 2×2
					else if ( fam === 'tall' ) { addCard( 2, 1 ); }                        // TALL 1×2
					else if ( fam === 'stack' ) { addCard( 1, 1 ); addCard( 1, 1 ); }      // STACK two squares
					// SINGLE — alternate top-aligned / bottom-aligned each time so both variants reliably
					// appear (a lone square with space below, and a lone square with space above).
					else { if ( singleAlt ) { addCard( 1, 1 ); addGap( 1, 1 ); } else { addGap( 1, 1 ); addCard( 1, 1 ); } singleAlt = ! singleAlt; }
				} else {
					var sum = 0, k = ci * 13 + 4, has = false, seq = [];
					while ( sum < R ) { var rem = R - sum; var sp = ( rem >= 2 && rnd( k++, 1 ) < 0.45 ) ? 2 : 1; var em = ( sp === 1 && rnd( k++, 4 ) < 0.2 ); seq.push( [ sp, em ] ); if ( ! em ) { has = true; } sum += sp; }
					if ( ! has ) { seq[ 0 ][ 1 ] = false; }
					for ( var j = 0; j < seq.length; j++ ) { if ( seq[ j ][ 1 ] ) { addGap( seq[ j ][ 0 ], 1 ); } else { addCard( seq[ j ][ 0 ], 1 ); } }
				}
				ci++;
			} while ( idx < pool.length );
		}
		function styleGrid( g ) {
			g.style.gridTemplateRows = 'repeat(' + R + ',' + cellH.toFixed( 1 ) + 'px)';
			g.style.gridAutoColumns = colW.toFixed( 1 ) + 'px';
			g.style.gap = gapPx.toFixed( 1 ) + 'px';
		}
		var pad = clamp( num( el, 'data-tdg-pad', 6 ), 0, 20 ) / 100;
		function layoutFn() {
			var H0 = stage.clientHeight || el.clientHeight || 1;
			var W = stage.clientWidth || el.clientWidth || 1;
			var padPx = Math.round( pad * H0 );               // inset — background shows in this margin
			stage.style.padding = padPx + 'px';
			track.style.left = padPx + 'px';
			var H = Math.max( 1, H0 - padPx * 2 );            // grid height inside the inset
			R = Math.max( 2, Math.min( 3, Math.round( 1 / cardH ) ) ); // rows of square cells (2 at the default height)
			var g0 = ( H / R ) * gap;
			cellH = ( H - ( R - 1 ) * g0 ) / R;              // square cell, gaps subtracted so the grid fits the stage
			colW = cellH * ( ratioW / ratioH );              // 1:1 → square cell; a TALL/BIG tile reads accordingly
			gapPx = Math.max( 2, g0 );
			track.style.gap = gapPx.toFixed( 1 ) + 'px';
			track.innerHTML = '';
			// Build one real set, style + measure it, then clone identical copies to cover the viewport (+1)
			// so wrapping by exactly one set width is seamless.
			var first = document.createElement( 'div' ); first.className = 'tdg__mosaic';
			track.appendChild( first ); buildSet( first, false ); styleGrid( first );
			setW = first.getBoundingClientRect().width + gapPx;
			var need = Math.max( 2, Math.ceil( W / Math.max( 1, setW ) ) + 1 );
			for ( var c = 1; c < need; c++ ) { var cl = first.cloneNode( true ); cl.setAttribute( 'aria-hidden', 'true' ); track.appendChild( cl ); }
			grids = Array.prototype.slice.call( track.querySelectorAll( '.tdg__mosaic' ) );
		}

		var pos = 0, vel = 0;
		function apply() { track.style.transform = 'translateY(-50%) translateX(' + ( -pos ).toFixed( 1 ) + 'px)'; }
		layoutFn();
		apply();
		// The cards exist now — release the deferred Entrance sequencing (the wrapper waited for them)
		// and let per-card Hover bind the freshly built tiles.
		if ( el.getAttribute( 'data-sc-anim-defer' ) ) { el.removeAttribute( 'data-sc-anim-defer' ); if ( window.upwScAnimBind ) { window.upwScAnimBind( el ); } }
		if ( window.upwHoverRescan ) { window.upwHoverRescan(); }
		window.addEventListener( 'resize', function () { layoutFn(); apply(); }, { passive: true } );
		var hoverF = hoverFactor( el );
		var allowDrag = num( el, 'data-tdg-allowdrag', 1 );
		var momentum = num( el, 'data-tdg-momentum', 1 );
		var dragging = false, px = 0, scrollX = null;
		var wrap = function () { if ( setW > 0 ) { pos = ( ( pos % setW ) + setW ) % setW; } };
		var advance = function ( dt ) { pos += dir * ( setW / Math.max( 3, speed ) ) * dt * hoverF(); wrap(); }; // one loop = one copy in `speed`s

		if ( allowDrag ) {
			el.style.cursor = 'grab';
			var down = function ( x ) { dragging = true; px = x; vel = 0; el.style.cursor = 'grabbing'; };
			var move = function ( x ) { if ( ! dragging ) { return; } var dx = x - px; px = x; pos -= dx; vel = -dx; wrap(); };
			var up = function () { dragging = false; el.style.cursor = 'grab'; };
			attachDrag( el, down, move, up );
		}

		if ( drive === 'scroll' ) {
			var onScroll = function () { scrollX = dir * scrollProgress( el, stage ) * setW * 2 * num( el, 'data-tdg-rounds', 1 ); };
			window.addEventListener( 'scroll', onScroll, { passive: true } );
			onScroll();
		}

		var autoRun = ( drive === 'continuous' && ! reduce );
		if ( ! ( autoRun || allowDrag || drive === 'scroll' ) ) { return; }
		var last = 0;
		var loop = function ( t ) {
			if ( ! last ) { last = t; }
			var dt = Math.min( 0.05, ( t - last ) / 1000 ); last = t;
			if ( ! dragging ) {
				if ( autoRun ) { advance( dt ); }
				if ( momentum && Math.abs( vel ) > 0.3 ) { pos += vel; vel *= 0.94; wrap(); }
				else if ( drive === 'scroll' && scrollX !== null ) { pos += ( scrollX - pos ) * 0.14; wrap(); }
			}
			apply();
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

	/* Hero Reel — a horizontal reel of thumbnail cards over a full-bleed HERO background. The card
	 * stepping through the centre is "active": it scales up + lifts, and its image fills the stage as a
	 * dimmed, slowly-zooming (Ken Burns) hero behind the reel, cross-fading as the reel advances. Flat 2D. */
	function initHeroReel( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var reel  = el.querySelector( '.tdg__reel' );
		var hero  = el.querySelector( '.tdg__hero' );
		if ( ! stage || ! reel ) { return; }
		var cards = Array.prototype.slice.call( reel.querySelectorAll( '.tdg__card' ) );
		var N = cards.length;
		if ( ! N ) { return; }
		var layers = hero ? Array.prototype.slice.call( hero.querySelectorAll( '.tdg__hero-layer' ) ) : [];

		var drive  = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed  = num( el, 'data-tdg-speed', 24 );
		var dir    = num( el, 'data-tdg-dir', 1 );
		var cardPct = clamp( num( el, 'data-tdg-card', 18 ), 8, 40 ) / 100;
		var gap    = clamp( num( el, 'data-tdg-gap', 4 ), 0, 20 ) / 100;
		var ascale = clamp( num( el, 'data-tdg-ascale', 112 ), 100, 160 ) / 100;
		var alift  = clamp( num( el, 'data-tdg-alift', 8 ), 0, 30 ) / 100;
		var hop    = clamp( num( el, 'data-tdg-hop', 55 ), 0, 100 ) / 100;
		var kb     = clamp( num( el, 'data-tdg-kb', 60 ), 0, 100 ) / 100;
		var hold   = clamp( num( el, 'data-tdg-hold', 55 ), 0, 100 ) / 100;

		var srcOf = function ( c ) { var im = c.querySelector( 'img' ); return im ? ( im.getAttribute( 'src' ) || '' ) : ''; };

		var cardW = 0, cardH = 0, step = 0, pos = 0, vel = 0, target = 0, dwellT = 0, lastActive = -1, layerOn = 0;
		function layout() {
			var W = stage.clientWidth || el.clientWidth || 1;
			cardW = Math.max( 40, W * cardPct );
			cards.forEach( function ( c ) { c.style.width = cardW + 'px'; c.style.marginLeft = ( -cardW / 2 ) + 'px'; } );
			cardH = ( cards[ 0 ] && cards[ 0 ].offsetHeight ) ? cards[ 0 ].offsetHeight : cardW;
			cards.forEach( function ( c ) { c.style.marginTop = ( -( c.offsetHeight || cardW ) / 2 ) + 'px'; } );
			step = cardW + cardW * gap;
			if ( hero ) { hero.style.opacity = hop.toFixed( 3 ); }
		}
		function apply() {
			for ( var i = 0; i < N; i++ ) {
				var c = cards[ i ];
				var p = ( ( i - pos ) % N + N ) % N;
				if ( p > N / 2 ) { p -= N; }                    // wrap into [-N/2, N/2)
				var x = p * step;
				var d = Math.abs( p );
				var act = Math.max( 0, 1 - d );                  // 1 at centre → 0 one card away
				var sc = 1 + ( ascale - 1 ) * act;
				var ly = -alift * cardH * act;
				c.style.transform = 'translate3d(' + x.toFixed( 1 ) + 'px,' + ly.toFixed( 1 ) + 'px,0) scale(' + sc.toFixed( 3 ) + ')';
				c.style.zIndex = String( 1000 - Math.round( d * 10 ) );
				c.style.opacity = ( d > N / 2 - 0.6 ? Math.max( 0, ( N / 2 - d ) / 0.6 ) : 1 ).toFixed( 3 );
			}
		}
		function setHero( idx ) {
			if ( ! layers.length ) { return; }
			var src = srcOf( cards[ ( ( idx % N ) + N ) % N ] );
			if ( ! src ) { return; }
			layerOn = 1 - layerOn;
			var on = layers[ layerOn ], off = layers[ 1 - layerOn ];
			on.style.backgroundImage = 'url("' + src + '")';
			on.style.transition = 'none'; on.style.transform = 'scale(1)';
			void on.offsetWidth;                                 // restart the Ken Burns zoom
			on.style.transition = 'opacity .8s ease, transform ' + Math.max( 4, speed ) + 's linear';
			on.style.opacity = '1'; on.style.transform = 'scale(' + ( 1 + kb * 0.16 ).toFixed( 3 ) + ')';
			off.style.transition = 'opacity .8s ease'; off.style.opacity = '0';
		}
		layout();
		apply();
		setHero( 0 ); lastActive = 0;
		window.addEventListener( 'resize', function () { layout(); apply(); }, { passive: true } );
		var hoverF = hoverFactor( el );
		var allowDrag = num( el, 'data-tdg-allowdrag', 1 );
		var momentum = num( el, 'data-tdg-momentum', 1 );
		var dragging = false, px = 0;
		var perCard = Math.max( 0.7, speed / Math.max( 1, N ) ) * ( 0.6 + hold * 0.9 ); // Hold lengthens the dwell

		if ( allowDrag ) {
			el.style.cursor = 'grab';
			var down = function ( x ) { dragging = true; px = x; vel = 0; el.style.cursor = 'grabbing'; };
			var move = function ( x ) { if ( ! dragging ) { return; } var dx = x - px; px = x; pos -= dx / Math.max( 1, step ); vel = -dx / Math.max( 1, step ); };
			var up = function () { dragging = false; el.style.cursor = 'grab'; target = Math.round( pos ); };
			attachDrag( el, down, move, up );
		}

		var autoRun = ( drive === 'continuous' && ! reduce );
		if ( ! ( autoRun || allowDrag ) ) { return; }
		var last = 0;
		var loop = function ( t ) {
			if ( ! last ) { last = t; }
			var dt = Math.min( 0.05, ( t - last ) / 1000 ); last = t;
			if ( ! dragging ) {
				if ( autoRun ) { dwellT += dt * hoverF(); if ( dwellT >= perCard ) { dwellT = 0; target += dir; } }
				if ( momentum && Math.abs( vel ) > 0.002 ) { pos += vel; vel *= 0.9; }
				else { pos += ( target - pos ) * Math.min( 1, dt * 5 ); }
			}
			apply();
			var active = ( ( Math.round( pos ) % N ) + N ) % N;
			if ( active !== lastActive ) { setHero( active ); lastActive = active; }
			requestAnimationFrame( loop );
		};
		requestAnimationFrame( loop );
	}

	// Focus Slider — a horizontal (or vertical) auto-advancing slider where the card passing through
	// the CENTRE is "focused": it scales up (Center Scale, up to ~3×) and sits clean, while its
	// neighbours stay small. Optional Zigzag pushes off-centre cards alternately up/down (or left/right
	// in vertical mode); Slide Portion sets how far apart the cards sit (low = overlapping/cinematic,
	// high = spread). Padding / Corner Radius / Background frame each card. Flat 2D (Carousel & Flow).
	function initFocusSlider( el ) {
		if ( el.__tdg ) { return; }
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' );
		var reel  = el.querySelector( '.tdg__reel' );
		if ( ! stage || ! reel ) { return; }
		var cards = Array.prototype.slice.call( reel.querySelectorAll( '.tdg__card' ) );
		var N = cards.length;
		if ( ! N ) { return; }

		var drive    = el.getAttribute( 'data-tdg-drive' ) || 'continuous';
		var speed    = num( el, 'data-tdg-speed', 24 );
		var dir      = num( el, 'data-tdg-dir', 1 );
		var vertical = ( el.getAttribute( 'data-tdg-axis' ) === 'vertical' );
		var cardPct  = clamp( num( el, 'data-tdg-card', 78 ), 20, 100 ) / 100;
		var gap      = clamp( num( el, 'data-tdg-gap', 6 ), 0, 40 ) / 100;
		var cscale   = clamp( num( el, 'data-tdg-cscale', 200 ), 100, 300 ) / 100;
		var zig      = clamp( num( el, 'data-tdg-zig', 0 ), 0, 100 ) / 100;
		var zigFixed = ( el.getAttribute( 'data-tdg-zigmode' ) === 'fixed' );
		var slide    = clamp( num( el, 'data-tdg-slide', 60 ), 20, 100 ) / 100;
		var hold     = clamp( num( el, 'data-tdg-hold', 50 ), 0, 100 ) / 100;

		var cardW = 0, cardH = 0, step = 0, cross = 0, leadV = 0, gapPx = 0, pos = 0, vel = 0, target = 0, dwellT = 0;
		function layout() {
			var W = stage.clientWidth || el.clientWidth || 1;
			var H = stage.clientHeight || el.clientHeight || 1;
			// Size the card on the CROSS axis (height for horizontal, width for vertical) and let the
			// other side follow from the card's aspect-ratio — so a portrait card stays tall & narrow
			// and its neighbours peek past the enlarged centre card, instead of it filling the stage.
			if ( vertical ) {
				var cw = Math.max( 40, W * cardPct );
				cards.forEach( function ( c ) { c.style.width = cw + 'px'; c.style.height = ''; } );
			} else {
				var ch = Math.max( 40, H * cardPct );
				cards.forEach( function ( c ) { c.style.height = ch + 'px'; c.style.width = ''; } );
			}
			cardW = ( cards[ 0 ] && cards[ 0 ].offsetWidth ) || 100;
			cardH = ( cards[ 0 ] && cards[ 0 ].offsetHeight ) || 100;
			cards.forEach( function ( c ) { c.style.marginLeft = ( -cardW / 2 ) + 'px'; c.style.marginTop = ( -cardH / 2 ) + 'px'; } );
			// Each card occupies its own (scaled) extent plus a UNIFORM gap, so the gaps between cards
			// stay equal even though the centre card is much wider than its shrunk neighbours (apply()
			// positions them cumulatively via that gap). Slide Portion opens the gap up; low values pull
			// the cards into a deliberate overlap.
			leadV = vertical ? cardH : cardW;                // focused extent along the slide axis
			gapPx = leadV * ( slide * 0.4 - 0.12 ) + leadV * gap;   // the uniform gap between neighbours (may go negative = overlap)
			step  = leadV / cscale + gapPx;                  // one shrunk-card step — used for drag/snapping
			cross = vertical ? cardW : cardH;                // focused perpendicular extent (for zigzag)
		}
		function apply() {
			var inv = 1 / cscale;                            // a neighbour's scale relative to the focus
			// Cumulative, width-aware position: sum each card's own scaled extent + the uniform gap, so
			// the gaps stay equal across the much wider centre card. `area` is ∫ scale from centre to p.
			function xpos( q ) {
				var s = q < 0 ? -1 : 1, a = ( q < 0 ? -q : q ), area;
				if ( a <= 1 ) { area = a - ( 1 - inv ) * a * a / 2; }
				else { area = ( 1 + inv ) / 2 + inv * ( a - 1 ); }
				return s * ( leadV * area + gapPx * a );
			}
			for ( var i = 0; i < N; i++ ) {
				var c = cards[ i ];
				var p = ( ( i - pos ) % N + N ) % N;
				if ( p > N / 2 ) { p -= N; }                 // wrap into [-N/2, N/2)
				var main = xpos( p );
				var d = Math.abs( p );
				var act = Math.max( 0, 1 - d );              // 1 at centre → 0 one step away
				var sc = inv + ( 1 - inv ) * act;            // focused (centre) = full size; neighbours shrink to 1/cscale
				// Zigzag: off-centre cards shift perpendicular; the focused card stays clean (fade by 1-act).
				// Fixed ties the up/down to each card; Alternate is a slash (one side up, the other down)
				// that flips each step — so the neighbours sit on opposite sides, as in the reference.
				var sgn = zigFixed ? ( i % 2 ? -1 : 1 )
					: ( p < 0 ? -1 : 1 ) * ( ( ( Math.round( pos ) % 2 ) + 2 ) % 2 ? -1 : 1 );
				var off = sgn * zig * cross * 0.5 * ( 1 - act );
				var tx = vertical ? off : main;
				var ty = vertical ? main : off;
				c.style.transform = 'translate3d(' + tx.toFixed( 1 ) + 'px,' + ty.toFixed( 1 ) + 'px,0) scale(' + sc.toFixed( 3 ) + ')';
				c.style.zIndex = String( 1000 - Math.round( d * 10 ) );
				c.style.opacity = ( d > N / 2 - 0.6 ? Math.max( 0, ( N / 2 - d ) / 0.6 ) : 1 ).toFixed( 3 );
			}
		}
		layout();
		apply();
		window.addEventListener( 'resize', function () { layout(); apply(); }, { passive: true } );

		var hoverF    = hoverFactor( el );
		var allowDrag = num( el, 'data-tdg-allowdrag', 1 );
		var momentum  = num( el, 'data-tdg-momentum', 1 );
		var dragging = false, px = 0;
		var perCard = Math.max( 0.7, speed / Math.max( 1, N ) ) * ( 0.6 + hold * 0.9 );

		if ( allowDrag ) {
			el.style.cursor = 'grab';
			var down = function ( x, y ) { dragging = true; px = vertical ? y : x; vel = 0; el.style.cursor = 'grabbing'; };
			var move = function ( x, y ) { if ( ! dragging ) { return; } var cur = vertical ? y : x; var dx = cur - px; px = cur; pos -= dx / Math.max( 1, step ); vel = -dx / Math.max( 1, step ); };
			var up   = function () { dragging = false; el.style.cursor = 'grab'; target = Math.round( pos ); };
			attachDrag( el, down, move, up );
		}

		var autoRun = ( drive === 'continuous' && ! reduce );
		if ( ! ( autoRun || allowDrag ) ) { return; }
		var last = 0;
		var loop = function ( t ) {
			if ( ! last ) { last = t; }
			var dt = Math.min( 0.05, ( t - last ) / 1000 ); last = t;
			if ( ! dragging ) {
				if ( autoRun ) { dwellT += dt * hoverF(); if ( dwellT >= perCard ) { dwellT = 0; target += dir; } }
				if ( momentum && Math.abs( vel ) > 0.002 ) { pos += vel; vel *= 0.9; }
				else { pos += ( target - pos ) * Math.min( 1, dt * 5 ); }
			}
			apply();
			requestAnimationFrame( loop );
		};
		requestAnimationFrame( loop );
	}

	// ---- Scroll Choreography -----------------------------------------------------------------------
	// One gallery that morphs through an ordered list of FORMATIONS as you scroll a pinned stage: the
	// cards start as a stack, form into one arrangement, reform into another, then exit. Each formation
	// is a per-card pose { x%, y%, z(px depth), ry, rx, rot, s(scale), op }; the runtime lerps every
	// card between the two bounding formations at the current scroll progress. Generalizes
	// photo_scatter's scatter→tidy morph to N formations. Honours reduced motion (static first look).
	var CHOREO_PRESETS = {
		showcase:    [ 'stack', 'grid', 'ring', 'scatter' ],
		fan_wall:    [ 'stack', 'fan', 'wall', 'scatter' ],
		form_reform: [ 'stack', 'ring', 'grid', 'scatter' ]
	};
	var CHOREO_EASE = {
		linear: function ( t ) { return t; },
		smooth: function ( t ) { return t * t * ( 3 - 2 * t ); },
		in:     function ( t ) { return t * t; },
		out:    function ( t ) { return 1 - ( 1 - t ) * ( 1 - t ); },
		back:   function ( t ) { var s = 1.70158; return 1 + ( s + 1 ) * Math.pow( t - 1, 3 ) + s * Math.pow( t - 1, 2 ); }
	};

	// Registry of extra resting poses a design or child theme contributes. Two ways to add one, both
	// landing in the SAME shared object so a form registered either way is honoured:
	//   • before this script loads (e.g. a wp_head inline): window.upwGallery3dForms.myform = fn;
	//   • at runtime: window.upwGallery3d.registerChoreoForm('myform', fn).
	// fn(N) returns a pose array (same shape as the built-ins below). Seeding the global in the head makes
	// the form available the moment galleries first init — the robust path for child themes.
	var CHOREO_FORMS = ( window.upwGallery3dForms && typeof window.upwGallery3dForms === 'object' )
		? window.upwGallery3dForms : ( window.upwGallery3dForms = {} );

	// Build one formation's pose array for N cards. Deterministic (computed once per gallery). A
	// registered form wins, so a design can override or extend the built-in looks.
	function choreoForm( name, N ) {
		if ( CHOREO_FORMS[ name ] ) {
			try { var reg = CHOREO_FORMS[ name ]( N ); if ( reg && reg.length ) { return reg; } } catch ( e ) {}
		}
		var out = [], i, R = Math.min( 260, 70 + N * 15 );
		if ( name === 'stack' ) {
			// A tidy photo pile: the higher the index, the nearer the top — upright, a touch larger.
			for ( i = 0; i < N; i++ ) { var up = i / Math.max( 1, N - 1 ), sgn = ( i % 2 ? 1 : -1 );
				out.push( { x: 50 + sgn * ( 2 + up * 2 ), y: 50 - up * 4 + sgn * 1.5, z: i * 4, ry: 0, rx: 0, rot: sgn * ( 5 - up * 3 ), s: 0.86 + up * 0.06, op: 1 } ); }
		} else if ( name === 'grid' ) {
			var cols = Math.max( 2, Math.round( Math.sqrt( N ) * 1.35 ) ), rows = Math.ceil( N / cols );
			for ( i = 0; i < N; i++ ) { var c = i % cols, r = ( i / cols ) | 0, inRow = Math.min( cols, N - r * cols ), pad = ( cols - inRow ) / 2;
				out.push( { x: ( 0.08 + ( ( c + pad + 0.5 ) / cols ) * 0.84 ) * 100, y: ( 0.12 + ( ( r + 0.5 ) / rows ) * 0.76 ) * 100, z: 0, ry: 0, rx: 0, rot: 0, s: 1, op: 1 } ); }
		} else if ( name === 'ring' ) {
			for ( i = 0; i < N; i++ ) { var a = ( i / N ) * 360, cf = Math.cos( a * Math.PI / 180 );
				out.push( { x: 50, y: 50, z: R, ry: a, rx: -6, rot: 0, s: 1, op: 0.35 + 0.65 * ( ( cf + 1 ) / 2 ) } ); }
		} else if ( name === 'fan' ) {
			for ( i = 0; i < N; i++ ) { var d = i - ( N - 1 ) / 2, ang = d * ( 48 / Math.max( 1, N - 1 ) );
				out.push( { x: 50, y: 52, z: -Math.abs( d ) * 86, ry: ang, rx: 0, rot: 0, s: Math.max( 0.6, 1 - Math.abs( d ) * 0.08 ), op: Math.max( 0.45, 1 - Math.abs( d ) * 0.12 ) } ); }
		} else if ( name === 'wall' ) {
			// A gently concave wall — edge columns angle inward and recede, wrapping toward the viewer.
			var cw = Math.max( 3, Math.round( Math.sqrt( N ) * 1.5 ) ), wr = Math.ceil( N / cw );
			for ( i = 0; i < N; i++ ) { var wc = i % cw, wrr = ( i / cw ) | 0, cfrac = ( wc / ( cw - 1 || 1 ) ) - 0.5;
				out.push( { x: ( 0.06 + ( ( wc + 0.5 ) / cw ) * 0.88 ) * 100, y: ( 0.14 + ( ( wrr + 0.5 ) / wr ) * 0.72 ) * 100, z: -40 - Math.abs( cfrac ) * 70, ry: -cfrac * 32, rx: -8, rot: 0, s: 1, op: 1 } ); }
		} else if ( name === 'spiral' ) { // cards threaded along a vertical helix (two turns)
			for ( i = 0; i < N; i++ ) { var sa = ( i / Math.max( 1, N - 1 ) ) * 720, sr = 60 + ( i / Math.max( 1, N - 1 ) ) * 150;
				out.push( { x: 50, y: 26 + ( i / Math.max( 1, N - 1 ) ) * 48, z: sr, ry: sa, rx: 0, rot: 0, s: Math.max( 0.62, 1 - ( i / N ) * 0.3 ), op: 1 } ); }
		} else if ( name === 'wave' ) { // a tilted grid with a per-column sine ripple
			var ac = Math.max( 3, Math.round( Math.sqrt( N ) * 1.5 ) ), ar = Math.ceil( N / ac );
			for ( i = 0; i < N; i++ ) { var vc = i % ac, vr = ( i / ac ) | 0, wob = Math.sin( ( vc / ac ) * Math.PI * 2 ) * 8;
				out.push( { x: ( 0.06 + ( ( vc + 0.5 ) / ac ) * 0.88 ) * 100, y: ( 0.16 + ( ( vr + 0.5 ) / ar ) * 0.68 ) * 100 + wob, z: -20, ry: 0, rx: -10, rot: 0, s: 1, op: 1 } ); }
		} else if ( name === 'carousel' ) { // a flat focus row — centre card big, sides scaled + turned away
			for ( i = 0; i < N; i++ ) { var cd = N > 1 ? ( i / ( N - 1 ) - 0.5 ) : 0, dist = Math.abs( cd ) * 2, span = Math.min( 78, ( N - 1 ) * 13 );
				out.push( { x: 50 + cd * span, y: 50, z: -dist * 60, ry: -cd * 20, rx: 0, rot: 0, s: Math.max( 0.55, 1 - dist * 0.4 ), op: Math.max( 0.4, 1 - dist * 0.4 ) } ); }
		} else if ( name === 'column' ) { // a vertical column (a feed/list read top → bottom)
			for ( i = 0; i < N; i++ ) { var cy = N > 1 ? i / ( N - 1 ) : 0.5;
				out.push( { x: 50, y: 11 + cy * 78, z: 0, ry: 0, rx: 0, rot: 0, s: 0.62, op: 1 } ); }
		} else if ( name === 'sphere' ) { // cards wrapped over a 3D ball (fibonacci distribution)
			var gr = Math.PI * ( 3 - Math.sqrt( 5 ) ), Rs = Math.min( 240, 80 + N * 12 );
			for ( i = 0; i < N; i++ ) { var ph = Math.acos( 1 - 2 * ( i + 0.5 ) / N ), th = gr * i;
				out.push( { x: 50, y: 50, z: Rs, ry: th * 180 / Math.PI, rx: ph * 180 / Math.PI - 90, rot: 0, s: 0.9, op: 1 } ); }
		} else if ( name === 'tunnel' ) { // cards receding into a depth corridor (near → far)
			for ( i = 0; i < N; i++ ) { var tf = i / Math.max( 1, N - 1 ), ta = ( i * 137.5 ) * Math.PI / 180, trad = 18 + tf * 8;
				out.push( { x: 50 + Math.cos( ta ) * trad, y: 50 + Math.sin( ta ) * trad * 0.7, z: 200 - tf * 620, ry: 0, rx: 0, rot: 0, s: 1, op: ( tf > 0.85 ? 0.2 : 1 ) } ); }
		} else if ( name === 'coverflow' ) { // the Cover Flow design: one flat, large front card; neighbours steeply angled and receding
			var fc = Math.round( ( N - 1 ) / 2 ); // a real centre card (no mid gap on even counts)
			for ( i = 0; i < N; i++ ) { var fd = i - fc, fsgn = fd < 0 ? -1 : ( fd > 0 ? 1 : 0 ), fab = Math.abs( fd );
				out.push( {
					x: 50 + fsgn * Math.min( 46, 8 + ( fab - 1 ) * 7 ) * ( fab > 0 ? 1 : 0 ),
					y: 50, z: fab === 0 ? 60 : -fab * 48,
					ry: -fsgn * ( fab === 0 ? 0 : 62 ), rx: 0, rot: 0,
					s: fab === 0 ? 1.08 : Math.max( 0.74, 0.92 - ( fab - 1 ) * 0.05 ),
					op: fab > 4 ? 0.35 : 1
				} ); }
		} else if ( name === 'filmstrip' ) { // the Film Strip / reel design: one even horizontal row, read left → right
			for ( i = 0; i < N; i++ ) { var mt = N > 1 ? i / ( N - 1 ) : 0.5;
				out.push( { x: 8 + mt * 84, y: 50, z: 0, ry: 0, rx: 0, rot: 0, s: Math.max( 0.42, Math.min( 0.6, 7 / N ) ), op: 1 } ); }
		} else if ( name === 'orbit' ) { // the Orbit Globe design: a tilted elliptical ring (halo) of evenly-placed cards, front ones nearer
			for ( i = 0; i < N; i++ ) { var oa = ( i / N ) * Math.PI * 2, odepth = ( Math.sin( oa ) + 1 ) / 2; // 0 (far) .. 1 (near)
				out.push( { x: 50 + Math.cos( oa ) * 38, y: 50 + Math.sin( oa ) * 19, z: Math.sin( oa ) * 120, ry: 0, rx: 0, rot: 0, s: 0.58 + odepth * 0.22, op: 0.72 + odepth * 0.28 } ); }
		} else if ( name === 'hero' ) { // the Hero Reel design: one large hero card above a row of small thumbnails
			for ( i = 0; i < N; i++ ) {
				if ( i === 0 ) { out.push( { x: 50, y: 38, z: 40, ry: 0, rx: 0, rot: 0, s: 1.5, op: 1 } ); }
				else { var ht = N > 2 ? ( i - 1 ) / ( N - 2 ) : 0.5;
					out.push( { x: 10 + ht * 80, y: 80, z: 0, ry: 0, rx: 0, rot: 0, s: Math.max( 0.34, Math.min( 0.46, 6 / N ) ), op: 1 } ); }
			}
		} else if ( name === 'focus' ) { // the Focus Slider design: one large centre card, its two neighbours peeking at the edges
			var qc = Math.round( ( N - 1 ) / 2 );
			for ( i = 0; i < N; i++ ) { var qd = i - qc, qsg = qd < 0 ? -1 : ( qd > 0 ? 1 : 0 ), qab = Math.abs( qd );
				if ( qab === 0 ) { out.push( { x: 50, y: 50, z: 40, ry: 0, rx: 0, rot: 0, s: 1.25, op: 1 } ); }
				else if ( qab === 1 ) { out.push( { x: 50 + qsg * 46, y: 50, z: -40, ry: -qsg * 18, rx: 0, rot: 0, s: 0.86, op: 0.8 } ); }
				else { out.push( { x: 50 + qsg * 52, y: 50, z: -140, ry: 0, rx: 0, rot: 0, s: 0.7, op: 0 } ); } // the rest hide behind, ready to slide in
			}
		} else if ( name === 'depthstack' ) { // the Depth Stack design: cards receding straight back into depth, front → back
			for ( i = 0; i < N; i++ ) { var pt = N > 1 ? i / ( N - 1 ) : 0, psg = i % 2 ? 1 : -1;
				out.push( { x: 50 + psg * 1.5, y: 50 - pt * 3, z: 50 - pt * 520, ry: 0, rx: 0, rot: psg * ( 2.5 - pt * 1.5 ), s: 1, op: pt > 0.82 ? 0.45 : 1 } ); }
		} else if ( name === 'mosaic' ) { // the Mosaic Marquee design: a packed collage — a grid with alternating tile sizes
			var mc = Math.max( 3, Math.round( Math.sqrt( N ) * 1.4 ) ), mr = Math.ceil( N / mc );
			for ( i = 0; i < N; i++ ) { var mcc = i % mc, mrr = ( i / mc ) | 0, inr = Math.min( mc, N - mrr * mc ), mpad = ( mc - inr ) / 2, msz = ( i % 4 === 0 ) ? 1.18 : ( i % 4 === 2 ? 0.82 : 1 );
				out.push( { x: ( 0.07 + ( ( mcc + mpad + 0.5 ) / mc ) * 0.86 ) * 100, y: ( 0.1 + ( ( mrr + 0.5 ) / mr ) * 0.8 ) * 100, z: ( i % 4 === 0 ) ? 30 : 0, ry: 0, rx: 0, rot: 0, s: msz, op: 1 } ); }
		} else { // scatter (exit) — fling the cards out radially and fade them
			for ( i = 0; i < N; i++ ) { var aa = ( i / N ) * Math.PI * 2 + ( i % 2 ? 0.7 : 0 );
				out.push( { x: 50 + Math.cos( aa ) * 118, y: 50 + Math.sin( aa ) * 118, z: 120, ry: 0, rx: 0, rot: ( ( i * 71 ) % 42 ) - 21, s: 0.6, op: 0 } ); }
		}
		return out;
	}

	// Builds the pose engine for one choreography gallery: sizes its cards, reads its Steps / holds /
	// easing / cascade, and returns poseAt(progress) which lerps every card between formations. Shared
	// by the single-gallery driver (initChoreo) and the chain controller (initChoreoChain).
	function choreoPoser( el ) {
		var cards = el.querySelectorAll( '.tdg__card' );
		var N = cards.length;
		if ( ! N ) { return null; }
		// data-tdg-choreo is a comma-separated formation list (PHP resolves presets + Custom), with a
		// bare preset name as a back-compat fallback.
		var VALID_FORMS = { stack: 1, grid: 1, ring: 1, fan: 1, wall: 1, spiral: 1, wave: 1, tunnel: 1, carousel: 1, column: 1, sphere: 1, coverflow: 1, filmstrip: 1, orbit: 1, hero: 1, focus: 1, depthstack: 1, mosaic: 1, scatter: 1 };
		var raw = el.getAttribute( 'data-tdg-choreo' ) || 'showcase';
		var seq;
		if ( raw.indexOf( ',' ) >= 0 ) {
			seq = raw.split( ',' ).map( function ( s ) { return s.trim(); } ).filter( function ( s ) { return VALID_FORMS[ s ] || CHOREO_FORMS[ s ]; } );
		} else {
			seq = CHOREO_PRESETS[ raw ] || CHOREO_PRESETS.showcase;
		}
		if ( seq.length < 2 ) { seq = CHOREO_PRESETS.showcase; }
		// Per-step holds (how long each formation holds before morphing to the next), 0..1.
		var holds = ( el.getAttribute( 'data-tdg-holds' ) || '' ).split( ',' ).map( function ( s ) { return clamp( parseFloat( s ) || 0, 0, 80 ) / 100; } );
		var stagger = clamp( num( el, 'data-tdg-stagger', 25 ), 0, 60 ) / 100;
		var ease    = CHOREO_EASE[ el.getAttribute( 'data-tdg-easing' ) ] || CHOREO_EASE.smooth;
		var cardPct = clamp( num( el, 'data-tdg-card', 16 ), 8, 40 );
		var i;
		for ( i = 0; i < N; i++ ) {
			var cd = cards[ i ];
			cd.style.position = 'absolute'; cd.style.left = '50%'; cd.style.top = '50%'; cd.style.margin = '0';
			cd.style.width = cardPct + '%'; cd.style.transition = 'none'; cd.style.willChange = 'transform, opacity';
		}
		var forms = [], k;
		for ( i = 0; i < seq.length; i++ ) { forms.push( choreoForm( seq[ i ], N ) ); }
		k = forms.length;

		function applyPose( card, p ) {
			card.style.left = p.x + '%'; card.style.top = p.y + '%';
			card.style.opacity = p.op; card.style.zIndex = String( 1000 + Math.round( p.z ) );
			// Depth cue: cards pushed BEHIND the stage dim a little, so depth reads even where opacity is full.
			var bright = clamp( 1 + Math.min( 0, p.z ) / 900, 0.62, 1 );
			card.style.filter = bright < 0.999 ? 'brightness(' + bright.toFixed( 3 ) + ')' : '';
			// Rotate on both axes BEFORE translating out, so a card ends up oriented on the surface it's
			// placed on (correct for the sphere; a ring/fan/spiral still face outward as before).
			card.style.transform = 'translate(-50%,-50%) rotateY(' + p.ry.toFixed( 2 ) + 'deg) rotateX(' + p.rx.toFixed( 2 ) + 'deg) translateZ(' + p.z.toFixed( 1 ) + 'px) rotate(' + p.rot.toFixed( 2 ) + 'deg) scale(' + p.s.toFixed( 3 ) + ')';
		}
		function poseAt( gp ) {
			var seg = clamp( gp, 0, 1 ) * ( k - 1 ), a = Math.min( k - 2, Math.floor( seg ) ), tr = seg - a;
			// Hold the formation we're leaving (step a's own hold), then morph over the rest of the segment.
			var dw = ( a < holds.length ) ? holds[ a ] : 0.15;
			var local = dw > 0 ? clamp( ( tr - dw ) / ( 1 - dw ), 0, 1 ) : tr;
			var span = 1 - stagger, A = forms[ a ], B = forms[ a + 1 ], j;
			for ( j = 0; j < N; j++ ) {
				// Cascade: each card starts its morph a little later, so they move one after another.
				var start = ( N > 1 ? j / ( N - 1 ) : 0 ) * stagger;
				var tj = ease( clamp( ( local - start ) / span, 0, 1 ) );
				var pop = 1 + 0.10 * Math.sin( tj * Math.PI ); // a subtle lift at the midpoint of the morph
				var pa = A[ j ], pb = B[ j ];
				applyPose( cards[ j ], {
					x: pa.x + ( pb.x - pa.x ) * tj, y: pa.y + ( pb.y - pa.y ) * tj, z: pa.z + ( pb.z - pa.z ) * tj,
					ry: pa.ry + ( pb.ry - pa.ry ) * tj, rx: pa.rx + ( pb.rx - pa.rx ) * tj, rot: pa.rot + ( pb.rot - pa.rot ) * tj,
					s: ( pa.s + ( pb.s - pa.s ) * tj ) * pop, op: pa.op + ( pb.op - pa.op ) * tj
				} );
			}
		}
		return { poseAt: poseAt, N: N, k: k };
	}

	// Authoring sugar: a gallery with the "Chain" switch on carries data-tdg-chain. Walk element siblings
	// and wrap each run of 2+ ADJACENT chained galleries in a `.tdg-choreo-chain` container, so the author
	// just drops several Scroll-Choreography galleries next to each other and ticks Chain — no css_class.
	// The section-like block a chained gallery lives in — what we insert the wrapper before and hide once
	// the gallery is relocated out of it (so galleries stacked in one column AND galleries each in their
	// own section both chain).
	function tdgSectionOf( g ) {
		return ( g.closest && ( g.closest( 'section' ) || g.closest( '.upw-section' ) || g.closest( '.fw-main-row' ) ) ) || g.parentNode;
	}
	// A section is safe to hide once its gallery moved out only if nothing else of substance remains in it.
	function tdgSectionEmpty( sec ) {
		if ( ! sec || sec.classList.contains( 'tdg-choreo-chain' ) ) { return false; }
		if ( sec.querySelector( '.tdg--scroll-choreography' ) ) { return false; } // another gallery still lives here
		if ( ( sec.innerText || '' ).trim().length ) { return false; }            // visible text — keep it
		if ( sec.querySelector( 'img, svg, video, canvas, iframe, .wp-block-image, [style*="background-image"]' ) ) { return false; }
		return true;
	}
	function autoGroupChains() {
		// Runs of CONSECUTIVE chained galleries in document order (a non-chained gallery breaks a run).
		// Members may sit in different sections — they are relocated into one overlay wrapper. Any gallery
		// design can chain (not just Scroll Choreography): every `.tdg` wrapper, chained via `data-tdg-chain`.
		var all = Array.prototype.slice.call( document.querySelectorAll( '.tdg' ) );
		var runs = [], cur = [];
		all.forEach( function ( g ) {
			var chained = g.getAttribute( 'data-tdg-chain' ) && ! ( g.closest && g.closest( '.tdg-choreo-chain' ) );
			if ( chained ) { cur.push( g ); }
			else if ( cur.length ) { runs.push( cur ); cur = []; }
		} );
		if ( cur.length ) { runs.push( cur ); }
		runs.forEach( function ( run ) {
			if ( run.length < 2 ) { return; } // a lone chained gallery → initChoreo drives it on its own
			var anchor = tdgSectionOf( run[ 0 ] );
			var box = document.createElement( 'div' );
			box.className = 'tdg-choreo-chain tdg-choreo-chain--auto';
			anchor.parentNode.insertBefore( box, anchor );
			run.forEach( function ( g ) {
				var sec = tdgSectionOf( g );
				box.appendChild( g ); // relocate the gallery into the shared overlay wrapper
				if ( sec && sec !== box && tdgSectionEmpty( sec ) ) { sec.classList.add( 'tdg-choreo-vacated' ); } // hide the emptied section (non-destructive)
			} );
		} );
	}

	// Builds one chain container: overlays its galleries and drives ONE pinned, scrubbed master timeline
	// that plays each gallery's pose sequence and OVERLAPS each exit with the next entrance — gallery 1
	// flies out AS gallery 2 assembles in.
	function buildChain( box ) {
		if ( box.__tdgChain ) { return; }
		box.__tdgChain = true;
		// Every chained gallery in the box — NOT filtered by `__tdg`: a non-choreography design's own init
		// already ran (set `__tdg`) and started the loop that reads `__tdgChainProgress`, which is exactly
		// what we want; only Scroll Choreography is init-skipped when chained.
		var gals = Array.prototype.slice.call( box.querySelectorAll( '.tdg' ) ).filter( function ( g ) { return g.getAttribute( 'data-tdg-chain' ); } );
		if ( gals.length < 2 ) { return; } // one gallery → let it drive itself normally
		var stageH = '70vh', items = [];
		gals.forEach( function ( g ) {
			g.__tdg = true;
			g.classList.remove( 'tdg--pinned' ); // the master pins the chain; drop the gallery's own CSS sticky pin
			var h = ( getComputedStyle( g ).getPropertyValue( '--tdg-stage-h' ) || '70vh' ).trim();
			stageH = h || stageH;
			g.style.position = 'absolute'; g.style.left = '0'; g.style.right = '0'; g.style.top = '0'; g.style.height = h;
			// Scroll Choreography poses its cards directly (choreoPoser); EVERY OTHER design is driven by its
			// own already-running loop reading `__tdgChainProgress` (scrollProgress returns it) — so pose is
			// null for them. Gate on the design class: choreoPoser returns a (default) poser even without
			// formation data, which would wrongly hijack a real design's own layout.
			items.push( { g: g, pose: g.classList.contains( 'tdg--scroll-choreography' ) ? choreoPoser( g ) : null } );
		} );
		if ( items.length < 2 ) { return; }
		function poseIt( it, p ) { if ( it.pose ) { it.pose.poseAt( p ); } else { it.g.__tdgChainProgress = p; } }
		box.style.position = 'relative';
		box.style.height = stageH;
		var OVERLAP = 0.3, UNIT = 2.4; // step overlap fraction, and screen-heights of scroll per gallery
		var master = window.gsap.timeline();
		var total = 0;
		items.forEach( function ( it, idx ) {
			window.gsap.set( it.g, { opacity: idx === 0 ? 1 : 0 } );
			poseIt( it, 0 );
			var proxy = { p: 0 }, sub = window.gsap.timeline();
			sub.set( it.g, { opacity: 1 }, 0 );
			sub.to( proxy, { p: 1, duration: 1, ease: 'none', onUpdate: function () { poseIt( it, proxy.p ); } }, 0 );
			sub.set( it.g, { opacity: 0 }, 1 ); // after its turn (cards are already scattered/faded)
			var pos = idx * ( 1 - OVERLAP );
			master.add( sub, pos );
			total = pos + 1;
		} );
		window.ScrollTrigger.create( {
			trigger: box, start: 'top top', end: '+=' + Math.round( total * UNIT * window.innerHeight ),
			pin: true, pinSpacing: true, scrub: true, anticipatePin: 1, invalidateOnRefresh: true, animation: master
		} );
	}

	// Chain controller: auto-group adjacent chained galleries, then build every `.tdg-choreo-chain`
	// container (explicit css_class OR auto-grouped). Requires GSAP; without it the galleries fall back
	// to the single-gallery driver (sequential pins), still functional.
	function initChoreoChain() {
		if ( IN_EDITOR || ! window.gsap || ! window.ScrollTrigger ) { return; }
		window.gsap.registerPlugin( window.ScrollTrigger );
		autoGroupChains();
		Array.prototype.forEach.call( document.querySelectorAll( '.tdg-choreo-chain' ), buildChain );
	}

	function initChoreo( el ) {
		if ( el.__tdg ) { return; }
		if ( el.closest && el.closest( '.tdg-choreo-chain' ) ) { return; } // driven by the chain controller
		el.__tdg = true;
		var stage = el.querySelector( '.tdg__stage' ) || el;
		var pp = choreoPoser( el );
		if ( ! pp ) { return; }
		var poseAt = pp.poseAt, k = pp.k;

		if ( reduce || window.upwReduceMotion ) { poseAt( k > 1 ? 1 / ( k - 1 ) : 0 ); return; } // the first formed look, static

		// GSAP path: when gsap + ScrollTrigger are on the page, drive the choreography with a pinned,
		// scrubbed ScrollTrigger (robust pin + scrub) instead of the hand-rolled rAF. poseAt does the
		// posing either way (same formations, holds, cascade, easing). Falls back to rAF below if absent.
		if ( ! IN_EDITOR && window.gsap && window.ScrollTrigger ) {
			window.gsap.registerPlugin( window.ScrollTrigger );
			var len = clamp( num( el, 'data-tdg-len', 3 ), 1, 6 );
			el.classList.remove( 'tdg--pinned' );                       // drop the CSS sticky pin
			el.style.height = ( getComputedStyle( el ).getPropertyValue( '--tdg-stage-h' ) || '70vh' ).trim();
			poseAt( 0 );
			window.ScrollTrigger.create( {
				trigger: el, start: 'top top', end: '+=' + Math.round( len * window.innerHeight ),
				pin: true, pinSpacing: true, scrub: true, anticipatePin: 1, invalidateOnRefresh: true,
				onUpdate: function ( self ) { poseAt( self.progress ); },
				onRefresh: function ( self ) { poseAt( self.progress ); }
			} );
			return;
		}

		// Idle-skip: only re-pose when the scroll progress actually changed (no DOM writes while still).
		var lastGp = -1;
		( function loop() {
			var gp = clamp( scrollProgress( el, stage ), 0, 1 );
			if ( Math.abs( gp - lastGp ) > 0.0008 ) { lastGp = gp; poseAt( gp ); }
			requestAnimationFrame( loop );
		}() );
	}

	// Build the context object handed to a registered design's build(ctx). It exposes the same
	// primitives the built-in drivers use (stage, cards, progress, drag, rAF, resize) so a child-theme
	// design is a first-class driver, not a second-class bolt-on.
	function ctxFor( el ) {
		var stage = el.querySelector( '.tdg__stage' ) || el;
		var cards = Array.prototype.slice.call( el.querySelectorAll( '.tdg__card' ) );
		return {
			el: el,
			stage: stage,
			cards: cards,
			reduce: reduce,
			IN_EDITOR: IN_EDITOR,
			clamp: clamp,
			num: function ( attr, dflt ) { return num( el, attr, dflt ); },
			attr: function ( name, dflt ) { var v = el.getAttribute( name ); return v === null ? dflt : v; },
			progress: function () { return scrollProgress( el, stage ); },
			attachDrag: function ( down, move, up ) { attachDrag( el, down, move, up ); },
			raf: requestAnimationFrame,
			// A self-rescheduling rAF loop stamped with the current generation (freezes in the editor,
			// dies on a preview rebuild) — the pattern every built-in driver uses.
			loop: function ( fn ) {
				function tick( t ) { fn( t ); requestAnimationFrame( tick ); }
				requestAnimationFrame( tick );
			},
			onResize: function ( fn ) { window.addEventListener( 'resize', fn, { passive: true } ); }
		};
	}

	// Init one element whose design was registered from a child theme. Matches .tdg--<dashed key>,
	// respects the __tdg once-guard, and isolates a faulty build() so it can never break the page.
	function initCustom( el ) {
		if ( el.__tdg ) { return; }
		for ( var key in CUSTOM ) {
			if ( ! CUSTOM.hasOwnProperty( key ) ) { continue; }
			if ( el.classList.contains( 'tdg--' + dashed( key ) ) ) {
				el.__tdg = true;
				try { CUSTOM[ key ]( ctxFor( el ) ); }
				catch ( e ) { if ( window.console ) { window.console.error( 'Motion Gallery custom design "' + key + '" failed:', e ); } }
				return;
			}
		}
	}

	function scan() {
		var i, els;
		// Live Editor: un-pin every gallery so a "Pin while scrubbing" stage doesn't stick over the viewport
		// (which would keep other elements out of reach). It renders as a normal inline block, then freezes.
		if ( IN_EDITOR ) {
			var pinned = document.querySelectorAll( '.tdg--pinned' );
			for ( i = 0; i < pinned.length; i++ ) { pinned[ i ].classList.remove( 'tdg--pinned' ); }
		}
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
		els = document.querySelectorAll( '.tdg--tilt-deck' );
		for ( i = 0; i < els.length; i++ ) { initTiltDeck( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--iso-orbit' );
		for ( i = 0; i < els.length; i++ ) { initIsoOrbit( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--iso-stack' );
		for ( i = 0; i < els.length; i++ ) { initIsoStack( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--iso-cascade' );
		for ( i = 0; i < els.length; i++ ) { initIsoCascade( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--iso-fan' );
		for ( i = 0; i < els.length; i++ ) { initIsoFan( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--orbit-bloom' );
		for ( i = 0; i < els.length; i++ ) { initOrbitBloom( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--focus-orbit' );
		for ( i = 0; i < els.length; i++ ) { initFocusOrbit( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--dual-orbit' );
		for ( i = 0; i < els.length; i++ ) { initDualOrbit( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--comet-orbit' );
		for ( i = 0; i < els.length; i++ ) { initCometOrbit( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--orbit-swarm' );
		for ( i = 0; i < els.length; i++ ) { initOrbitSwarm( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--ring-tilt' );
		for ( i = 0; i < els.length; i++ ) { initRingTilt( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--gyro-orbit' );
		for ( i = 0; i < els.length; i++ ) { initGyroOrbit( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--infinity-orbit' );
		for ( i = 0; i < els.length; i++ ) { initInfinityOrbit( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--vortex' );
		for ( i = 0; i < els.length; i++ ) { initVortex( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--pendulum-orbit' );
		for ( i = 0; i < els.length; i++ ) { initPendulumOrbit( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--moon-orbit' );
		for ( i = 0; i < els.length; i++ ) { initMoonOrbit( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--tiered-orbit' );
		for ( i = 0; i < els.length; i++ ) { initTieredOrbit( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--wave-grid' );
		for ( i = 0; i < els.length; i++ ) { initWaveGrid( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--flip-grid' );
		for ( i = 0; i < els.length; i++ ) { initFlipGrid( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--magnet-grid' );
		for ( i = 0; i < els.length; i++ ) { initMagnetGrid( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--blind-reveal' );
		for ( i = 0; i < els.length; i++ ) { initBlindReveal( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--iris-wipe' );
		for ( i = 0; i < els.length; i++ ) { initIrisWipe( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--tile-dissolve' );
		for ( i = 0; i < els.length; i++ ) { initTileDissolve( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--spotlight-wall' );
		for ( i = 0; i < els.length; i++ ) { initSpotlightWall( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--focus-pull' );
		for ( i = 0; i < els.length; i++ ) { initFocusPull( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--loupe' );
		for ( i = 0; i < els.length; i++ ) { initLoupe( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--card-fan' );
		for ( i = 0; i < els.length; i++ ) { initCardFan( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--shuffle-deck' );
		for ( i = 0; i < els.length; i++ ) { initShuffleDeck( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--polaroid-drop' );
		for ( i = 0; i < els.length; i++ ) { initPolaroidDrop( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--phone-lineup' );
		for ( i = 0; i < els.length; i++ ) { initPhoneLineup( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--window-stack' );
		for ( i = 0; i < els.length; i++ ) { initWindowStack( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--device-trio' );
		for ( i = 0; i < els.length; i++ ) { initDeviceTrio( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--card-reel' );
		for ( i = 0; i < els.length; i++ ) { initReel( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--film-strip' );
		for ( i = 0; i < els.length; i++ ) { initReel( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--wheel-carousel' );
		for ( i = 0; i < els.length; i++ ) { initWheel( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--carousel-flow, .tdg--cover-flow' );
		for ( i = 0; i < els.length; i++ ) { initFlow( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--mosaic-marquee' );
		for ( i = 0; i < els.length; i++ ) { initMosaic( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--hero-reel' );
		for ( i = 0; i < els.length; i++ ) { initHeroReel( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--focus-slider' );
		for ( i = 0; i < els.length; i++ ) { initFocusSlider( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--photo-scatter' );
		for ( i = 0; i < els.length; i++ ) { initScatter( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--device-cycler' );
		for ( i = 0; i < els.length; i++ ) { initDeviceCycler( els[ i ] ); }
		els = document.querySelectorAll( '.tdg--card-stack' );
		for ( i = 0; i < els.length; i++ ) { initCardStack( els[ i ] ); }
		// Chained choreography FIRST: auto-group adjacent chained galleries into one overlap sequence and
		// claim them, so the single-gallery pass below skips the ones that belong to a chain. A lone
		// chained gallery (no adjacent partner) is left for initChoreo to drive on its own.
		initChoreoChain();
		els = document.querySelectorAll( '.tdg--scroll-choreography' );
		for ( i = 0; i < els.length; i++ ) { initChoreo( els[ i ] ); }
		// Child-theme-registered designs (one querySelectorAll per key).
		for ( var ck in CUSTOM ) {
			if ( ! CUSTOM.hasOwnProperty( ck ) ) { continue; }
			var cels = document.querySelectorAll( '.tdg--' + dashed( ck ) );
			for ( i = 0; i < cels.length; i++ ) { initCustom( cels[ i ] ); }
		}
		// Built-in sequential Entrance — independent of the design, so one pass covers them all.
		els = document.querySelectorAll( '.tdg--entrance' );
		for ( i = 0; i < els.length; i++ ) { initReveal( els[ i ] ); }
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
		else if ( el.classList.contains( 'tdg--tilt-deck' ) ) { initTiltDeck( el ); }
		else if ( el.classList.contains( 'tdg--iso-orbit' ) ) { initIsoOrbit( el ); }
		else if ( el.classList.contains( 'tdg--iso-stack' ) ) { initIsoStack( el ); }
		else if ( el.classList.contains( 'tdg--iso-cascade' ) ) { initIsoCascade( el ); }
		else if ( el.classList.contains( 'tdg--iso-fan' ) ) { initIsoFan( el ); }
		else if ( el.classList.contains( 'tdg--orbit-bloom' ) ) { initOrbitBloom( el ); }
		else if ( el.classList.contains( 'tdg--focus-orbit' ) ) { initFocusOrbit( el ); }
		else if ( el.classList.contains( 'tdg--dual-orbit' ) ) { initDualOrbit( el ); }
		else if ( el.classList.contains( 'tdg--comet-orbit' ) ) { initCometOrbit( el ); }
		else if ( el.classList.contains( 'tdg--orbit-swarm' ) ) { initOrbitSwarm( el ); }
		else if ( el.classList.contains( 'tdg--ring-tilt' ) ) { initRingTilt( el ); }
		else if ( el.classList.contains( 'tdg--gyro-orbit' ) ) { initGyroOrbit( el ); }
		else if ( el.classList.contains( 'tdg--infinity-orbit' ) ) { initInfinityOrbit( el ); }
		else if ( el.classList.contains( 'tdg--vortex' ) ) { initVortex( el ); }
		else if ( el.classList.contains( 'tdg--pendulum-orbit' ) ) { initPendulumOrbit( el ); }
		else if ( el.classList.contains( 'tdg--moon-orbit' ) ) { initMoonOrbit( el ); }
		else if ( el.classList.contains( 'tdg--tiered-orbit' ) ) { initTieredOrbit( el ); }
		else if ( el.classList.contains( 'tdg--wave-grid' ) ) { initWaveGrid( el ); }
		else if ( el.classList.contains( 'tdg--flip-grid' ) ) { initFlipGrid( el ); }
		else if ( el.classList.contains( 'tdg--magnet-grid' ) ) { initMagnetGrid( el ); }
		else if ( el.classList.contains( 'tdg--blind-reveal' ) ) { initBlindReveal( el ); }
		else if ( el.classList.contains( 'tdg--iris-wipe' ) ) { initIrisWipe( el ); }
		else if ( el.classList.contains( 'tdg--tile-dissolve' ) ) { initTileDissolve( el ); }
		else if ( el.classList.contains( 'tdg--spotlight-wall' ) ) { initSpotlightWall( el ); }
		else if ( el.classList.contains( 'tdg--focus-pull' ) ) { initFocusPull( el ); }
		else if ( el.classList.contains( 'tdg--loupe' ) ) { initLoupe( el ); }
		else if ( el.classList.contains( 'tdg--card-fan' ) ) { initCardFan( el ); }
		else if ( el.classList.contains( 'tdg--shuffle-deck' ) ) { initShuffleDeck( el ); }
		else if ( el.classList.contains( 'tdg--polaroid-drop' ) ) { initPolaroidDrop( el ); }
		else if ( el.classList.contains( 'tdg--phone-lineup' ) ) { initPhoneLineup( el ); }
		else if ( el.classList.contains( 'tdg--window-stack' ) ) { initWindowStack( el ); }
		else if ( el.classList.contains( 'tdg--device-trio' ) ) { initDeviceTrio( el ); }
		else if ( el.classList.contains( 'tdg--card-reel' ) ) { initReel( el ); }
		else if ( el.classList.contains( 'tdg--film-strip' ) ) { initReel( el ); }
		else if ( el.classList.contains( 'tdg--wheel-carousel' ) ) { initWheel( el ); }
		else if ( el.classList.contains( 'tdg--carousel-flow' ) || el.classList.contains( 'tdg--cover-flow' ) ) { initFlow( el ); }
		else if ( el.classList.contains( 'tdg--mosaic-marquee' ) ) { initMosaic( el ); }
		else if ( el.classList.contains( 'tdg--hero-reel' ) ) { initHeroReel( el ); }
		else if ( el.classList.contains( 'tdg--focus-slider' ) ) { initFocusSlider( el ); }
		else if ( el.classList.contains( 'tdg--photo-scatter' ) ) { initScatter( el ); }
		else if ( el.classList.contains( 'tdg--device-cycler' ) ) { initDeviceCycler( el ); }
		else if ( el.classList.contains( 'tdg--card-stack' ) ) { initCardStack( el ); }
		else if ( el.classList.contains( 'tdg--scroll-choreography' ) ) { initChoreo( el ); }
		initCustom( el ); // child-theme-registered design, if any matches
		initReveal( el ); // built-in sequential Entrance (no-op unless data-tdg-entrance is set)
	};

	// Public registration API for child themes. registerDesign(key, build) adds a driver and rescans
	// so an already-rendered host picks it up. Call it after this script loads — a registered design's
	// asset bundle enqueues with this script as a dependency (upw_gallery_3d_design_assets filter).
	window.upwGallery3d = window.upwGallery3d || {};
	window.upwGallery3d.registerDesign = function ( key, build ) {
		if ( ! key || typeof build !== 'function' ) { return; }
		CUSTOM[ String( key ) ] = build;
		if ( typeof scan === 'function' ) { scan(); }
	};
	// registerChoreoForm(name, fn) — contribute a Scroll-Choreography resting pose. fn(N) returns a pose
	// array [{x,y,z,ry,rx,rot,s,op}, …] (x/y in %, z in px, angles in deg). The name is then usable as a
	// choreography Step. Pair with the `fw_gallery_3d_choreo_formations` PHP filter so it shows in the
	// Steps picker and passes the view's whitelist. Rescans so an already-rendered gallery re-poses.
	window.upwGallery3d.registerChoreoForm = function ( name, fn ) {
		if ( ! name || typeof fn !== 'function' ) { return; }
		CHOREO_FORMS[ String( name ) ] = fn;
		if ( typeof scan === 'function' ) { scan(); }
	};

	/* Re-init hook for surfaces that insert this element's markup after page load (the
	   block editor's preview, the Elementor editor's re-renders): they call every
	   window.fwShortcodeInit entry. The scan skips elements it has already set up, so
	   repeated calls are safe. */
	window.fwShortcodeInit = window.fwShortcodeInit || [];
	window.fwShortcodeInit.push( function () { scan(); } );
})();
