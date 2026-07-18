/**
 * Animation Engine — Confetti runtime.
 *
 * Fires a Canvas 2D particle burst from each .sc-confetti element on its trigger (scroll-into-view,
 * click, page load or hover). One shared full-viewport canvas + one rAF loop render every burst; the
 * loop sleeps when no particles are alive. Pure Canvas 2D, no library. Skips under "reduce motion".
 *
 * Four render paths, chosen per style (STYLES registry):
 *   flat      — solid single-colour shapes (the original vector/cartoon look; great on flat/illustrated
 *               backgrounds): confetti, stars, fireworks, streamers, hearts, snow.
 *   paper     — 3D tumbling paper/foil: the piece foreshortens edge-on as it flips, a light→dark sheen
 *               gradient runs across it, and a soft drop-shadow gives depth — so it sits on PHOTO /
 *               realistic backgrounds instead of looking stuck on top.
 *   glow      — additive soft-light specks (bokeh, glitter, embers, fairy dust, fireflies).
 *   soft      — translucent soft-edged particles (realistic snow, bubbles, champagne).
 *
 * Data attrs stamped by confetti.php: data-cf-style, -trigger, -count, -spread, -power, -duration,
 * -palette, -replay.
 */
(function () {
	'use strict';

	var cfg    = window.upwConfettiCfg || {};
	var mql    = window.matchMedia || function () { return { matches: false }; };
	var reduce = cfg.reducedMotion !== false && mql( '(prefers-reduced-motion: reduce)' ).matches;

	// User-selectable palettes (data-cf-palette). Themed styles override with their own set (COLORS).
	var PALETTES = {
		brand:   [ '#2f74e6', '#00b295', '#f6b93b', '#ec4899', '#a855f7' ],
		rainbow: [ '#ff3b30', '#ff9500', '#ffcc00', '#34c759', '#00b8d4', '#5856d6', '#af52de' ],
		gold:    [ '#f6b93b', '#ffd700', '#fff3c4', '#e0a800', '#c9971b' ],
		pastel:  [ '#a7c7e7', '#c9e4de', '#f7d9c4', '#f2c6de', '#dbcdf0' ],
		mono:    [ '#2f74e6', '#5b8fe0', '#9dc0f0', '#1c56b8', '#bcd3f7' ],
		silver:  [ '#e8e8e8', '#c0c0c0', '#f5f5f5', '#a8a8a8', '#d9d9d9' ],
		natural: [ '#e8d5b7', '#c98a5e', '#a3b18a', '#dda15e', '#bc6c25' ]
	};
	// Style-specific colour sets (for themed styles — ignore the palette control).
	var COLORS = {
		gold:   [ '#ffe98a', '#ffd700', '#f6b93b', '#e0a800', '#fff3c4' ],
		silver: [ '#f5f5f5', '#e0e0e0', '#c0c0c0', '#a8a8a8', '#ffffff' ],
		rose:   [ '#f3d1c9', '#e6b8b0', '#d98c7a', '#c56f5c', '#e8a598' ],
		holo:   [ '#a0e9ff', '#ffb3f0', '#b3ffd9', '#fff2b3', '#c9b3ff', '#b3d9ff' ],
		pink:   [ '#ffe0e6', '#ffd1dc', '#ffb6c1', '#ff9eb5', '#ffc0cb' ],
		autumn: [ '#d2691e', '#b8860b', '#cd5c5c', '#8b4513', '#e9967a', '#daa520' ],
		ember:  [ '#fff0a0', '#ffcc33', '#ff8800', '#ff5500', '#ff6600' ],
		money:  [ '#4e8a5b', '#6aa06f', '#3d6b47', '#85b391', '#5c8a63' ],
		white:  [ '#ffffff', '#f4f8ff', '#e9f1ff' ],
		rain:   [ '#9dc0f0', '#bcd3f7', '#c9e4ff' ],
		champ:  [ '#ffe98a', '#fff3c4', '#f6e27a', '#ffffff' ]
	};

	// Per-style profile. rend: flat|paper|glow|soft. dir: up(default)|down|rise|radial. Optional:
	// shape, colors (COLORS key), grav, drag, sway, drift, life, sizeMul, blend, twinkle, flicker,
	// trail, big, small, sheen.
	var UP = -Math.PI / 2, DOWN = Math.PI / 2;
	var STYLES = {
		// — flat (original vector look) —
		confetti:      { rend: 'flat', shape: 'rect' },
		stars:         { rend: 'flat', shape: 'star' },
		fireworks:     { rend: 'flat', shape: 'dot',      dir: 'radial', drag: 0.96, glow: 1 },
		streamers:     { rend: 'flat', shape: 'streamer' },
		hearts:        { rend: 'flat', shape: 'heart',    grav: 0.05, sway: 0.8 },
		snow:          { rend: 'flat', shape: 'dot',      dir: 'down', grav: 0.03, sway: 1.0, drift: 1 },
		// — realistic paper & foil (3D tumble + sheen + shadow) —
		realistic:     { rend: 'paper', shape: 'rect',    flutter: 1 },
		foil_gold:     { rend: 'paper', shape: 'rect',    flutter: 1, colors: 'gold',   sheen: 1 },
		foil_silver:   { rend: 'paper', shape: 'rect',    flutter: 1, colors: 'silver', sheen: 1 },
		rose_gold:     { rend: 'paper', shape: 'rect',    flutter: 1, colors: 'rose',   sheen: 1 },
		holographic:   { rend: 'paper', shape: 'rect',    flutter: 1, colors: 'holo',   sheen: 1 },
		triangles:     { rend: 'paper', shape: 'tri',     flutter: 1 },
		hexagons:      { rend: 'paper', shape: 'hex',     flutter: 1 },
		money:         { rend: 'paper', shape: 'bill',    flutter: 0.5, colors: 'money', dir: 'down', grav: 0.06, sway: 1.2, sizeMul: 1.8 },
		serpentine:    { rend: 'paper', shape: 'ribbon',  flutter: 1, grav: 0.04, sway: 1.0, sizeMul: 1.3 },
		// — nature —
		sakura:        { rend: 'paper', shape: 'petal',   flutter: 1, colors: 'pink',   dir: 'down', grav: 0.02, sway: 1.5 },
		autumn_leaves: { rend: 'paper', shape: 'leaf',    flutter: 1, colors: 'autumn', dir: 'down', grav: 0.025, sway: 1.4, sizeMul: 1.25 },
		realistic_snow:{ rend: 'soft', shape: 'flake',    dir: 'down', grav: 0.02, sway: 1.3, drift: 1, colors: 'white' },
		rain:          { rend: 'flat', shape: 'rain',     dir: 'down', grav: 0.5, drag: 1, colors: 'rain', life: 0.5 },
		// — glow (additive) —
		glitter:       { rend: 'glow', blend: 'lighter', twinkle: 1, small: 1 },
		bokeh:         { rend: 'glow', blend: 'lighter', big: 1, dir: 'down', grav: 0.01, drift: 1, twinkle: 1 },
		fairy_dust:    { rend: 'glow', blend: 'lighter', small: 1, trail: 1, sway: 1, twinkle: 1 },
		fireflies:     { rend: 'glow', blend: 'lighter', twinkle: 1, dir: 'rise', grav: -0.008, drift: 1, colors: 'ember' },
		embers:        { rend: 'glow', blend: 'lighter', dir: 'rise', grav: -0.03, colors: 'ember', flicker: 1 },
		champagne:     { rend: 'soft', shape: 'bubble',  dir: 'rise', grav: -0.05, small: 1, colors: 'champ' },
		bubbles:       { rend: 'soft', shape: 'bubble',  dir: 'rise', grav: -0.02, drift: 1, sizeMul: 1.4 }
	};

	var canvas, ctx, dpr = 1, particles = [], running = false;

	function clamp( v, a, b ) { return Math.max( a, Math.min( b, v ) ); }
	function rand( a, b ) { return a + Math.random() * ( b - a ); }
	function pick( arr ) { return arr[ ( Math.random() * arr.length ) | 0 ]; }

	function hx( h ) {
		h = ( '' + h ).replace( '#', '' );
		if ( h.length === 3 ) { h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2]; }
		return [ parseInt( h.slice( 0, 2 ), 16 ), parseInt( h.slice( 2, 4 ), 16 ), parseInt( h.slice( 4, 6 ), 16 ) ];
	}
	function shade( h, amt ) { var c = hx( h ); return 'rgb(' + clamp( c[0] + amt, 0, 255 ) + ',' + clamp( c[1] + amt, 0, 255 ) + ',' + clamp( c[2] + amt, 0, 255 ) + ')'; }
	function rgba( h, a ) { var c = hx( h ); return 'rgba(' + c[0] + ',' + c[1] + ',' + c[2] + ',' + a + ')'; }

	function resize() {
		if ( ! canvas ) { return; }
		dpr = Math.min( window.devicePixelRatio || 1, 2 );
		canvas.width  = Math.floor( ( window.innerWidth  || document.documentElement.clientWidth )  * dpr );
		canvas.height = Math.floor( ( window.innerHeight || document.documentElement.clientHeight ) * dpr );
	}

	function ensureCanvas() {
		if ( canvas ) { return; }
		canvas = document.createElement( 'canvas' );
		canvas.className = 'sc-confetti-canvas';
		canvas.setAttribute( 'aria-hidden', 'true' );
		document.body.appendChild( canvas );
		ctx = canvas.getContext( '2d' );
		resize();
		window.addEventListener( 'resize', resize, { passive: true } );
	}

	function burst( el ) {
		if ( ! ctx ) { ensureCanvas(); }
		if ( ! ctx ) { return; }
		var r = el.getBoundingClientRect();
		var ox = ( r.left + r.width / 2 ) * dpr;
		var oy = ( r.top + r.height / 2 ) * dpr;

		var name    = el.getAttribute( 'data-cf-style' ) || 'confetti';
		var S       = STYLES[ name ] || STYLES.confetti;
		var count   = clamp( parseInt( el.getAttribute( 'data-cf-count' ), 10 ) || 90, 20, 400 );
		var spread  = clamp( parseFloat( el.getAttribute( 'data-cf-spread' ) ) || 70, 20, 360 ) * Math.PI / 180;
		var power   = clamp( parseFloat( el.getAttribute( 'data-cf-power' ) ) || 45, 15, 100 );
		var life    = clamp( parseFloat( el.getAttribute( 'data-cf-duration' ) ) || 3, 1, 7 ) * ( S.life || 1 );
		var colors  = S.colors ? COLORS[ S.colors ] : ( PALETTES[ el.getAttribute( 'data-cf-palette' ) ] || PALETTES.brand );

		var dir     = S.dir || 'up';
		var grav    = ( S.grav != null ? S.grav : 0.14 ) * dpr;
		var drag    = S.drag != null ? S.drag : 0.985;
		var base    = ( dir === 'down' ) ? DOWN : UP; // rise launches up too, but floats (negative grav)
		var sizeMul = ( S.sizeMul || 1 ) * ( S.small ? 0.5 : 1 ) * ( S.big ? 2.2 : 1 );

		for ( var i = 0; i < count; i++ ) {
			var a = base + rand( -spread / 2, spread / 2 );
			var v = ( power / 10 ) * rand( 0.4, 1 ) * dpr;
			if ( dir === 'radial' ) { a = rand( 0, Math.PI * 2 ); v = ( power / 10 ) * rand( 0.6, 1 ) * dpr; }
			if ( dir === 'rise' )   { v *= rand( 0.5, 1 ); }
			particles.push( {
				S: S, name: name,
				x: ox, y: oy,
				vx: Math.cos( a ) * v,
				vy: Math.sin( a ) * v,
				g: grav, drag: drag,
				rot: rand( 0, Math.PI * 2 ), vr: rand( -0.2, 0.2 ),
				flip: rand( 0, Math.PI * 2 ), vf: rand( 0.1, 0.28 ) * ( S.flutter || 0 ),
				size: rand( 4, 8 ) * sizeMul * dpr,
				len:  rand( 10, 22 ) * dpr,
				color: pick( colors ),
				life: life * 60 * rand( 0.7, 1 ), age: 0,
				wob: rand( 0, Math.PI * 2 ),
				sway: ( S.sway || 0 ) * rand( 0.6, 1.3 ),
				drift: S.drift ? rand( -0.4, 0.4 ) * dpr : 0,
				seed: rand( 0, 100 )
			} );
		}
		start();
	}

	/* ---- shape paths (centred at origin; caller has translated/rotated) ---- */
	function shapePath( shape, w, h ) {
		var k, ang;
		ctx.beginPath();
		switch ( shape ) {
			case 'tri':
				ctx.moveTo( 0, -h / 2 ); ctx.lineTo( w / 2, h / 2 ); ctx.lineTo( -w / 2, h / 2 ); ctx.closePath(); break;
			case 'hex':
				for ( k = 0; k < 6; k++ ) { ang = Math.PI / 6 + k * Math.PI / 3; ctx[ k ? 'lineTo' : 'moveTo' ]( Math.cos( ang ) * w / 2, Math.sin( ang ) * h / 2 ); }
				ctx.closePath(); break;
			case 'petal':
				ctx.moveTo( 0, h / 2 );
				ctx.bezierCurveTo( w, h * 0.1, w * 0.35, -h / 2, 0, -h / 2 );
				ctx.bezierCurveTo( -w * 0.35, -h / 2, -w, h * 0.1, 0, h / 2 );
				ctx.closePath(); break;
			case 'leaf':
				ctx.moveTo( 0, -h / 2 ); ctx.quadraticCurveTo( w / 2, 0, 0, h / 2 ); ctx.quadraticCurveTo( -w / 2, 0, 0, -h / 2 ); ctx.closePath(); break;
			case 'bill':
				var rw = w * 1.6, rh = h * 0.7, rr = Math.min( rw, rh ) * 0.18;
				roundRect( -rw / 2, -rh / 2, rw, rh, rr ); break;
			default: // rect
				ctx.rect( -w / 2, -h / 2, w, h );
		}
	}
	function roundRect( x, y, w, h, r ) {
		ctx.moveTo( x + r, y );
		ctx.arcTo( x + w, y, x + w, y + h, r ); ctx.arcTo( x + w, y + h, x, y + h, r );
		ctx.arcTo( x, y + h, x, y, r ); ctx.arcTo( x, y, x + w, y, r ); ctx.closePath();
	}
	function drawStar( s ) {
		ctx.beginPath();
		for ( var k = 0; k < 5; k++ ) {
			var a1 = -Math.PI / 2 + k * 2 * Math.PI / 5, a2 = a1 + Math.PI / 5;
			ctx.lineTo( Math.cos( a1 ) * s, Math.sin( a1 ) * s );
			ctx.lineTo( Math.cos( a2 ) * s * 0.45, Math.sin( a2 ) * s * 0.45 );
		}
		ctx.closePath(); ctx.fill();
	}
	function drawHeart( s ) {
		ctx.beginPath();
		ctx.moveTo( 0, s * 0.35 );
		ctx.bezierCurveTo( s, -s * 0.5, s * 0.55, -s, 0, -s * 0.35 );
		ctx.bezierCurveTo( -s * 0.55, -s, -s, -s * 0.5, 0, s * 0.35 );
		ctx.closePath(); ctx.fill();
	}

	/* ---- render paths ---- */
	function drawFlat( p, alpha ) {
		ctx.save();
		ctx.globalAlpha = alpha;
		if ( p.S.glow ) { ctx.shadowColor = p.color; ctx.shadowBlur = p.size * 2; }
		ctx.translate( p.x, p.y ); ctx.rotate( p.rot );
		ctx.fillStyle = p.color; ctx.strokeStyle = p.color;
		switch ( p.S.shape ) {
			case 'star':  drawStar( p.size ); break;
			case 'heart': drawHeart( p.size ); break;
			case 'streamer':
				ctx.lineWidth = p.size * 0.5; ctx.lineCap = 'round'; ctx.beginPath();
				ctx.moveTo( 0, -p.len / 2 ); ctx.quadraticCurveTo( p.size * 2.5, 0, 0, p.len / 2 ); ctx.stroke(); break;
			case 'rain':
				ctx.lineWidth = Math.max( 1, p.size * 0.35 ); ctx.lineCap = 'round'; ctx.globalAlpha = alpha * 0.6;
				ctx.beginPath(); ctx.moveTo( 0, -p.len ); ctx.lineTo( 0, p.len ); ctx.stroke(); break;
			case 'dot': ctx.beginPath(); ctx.arc( 0, 0, p.size * 0.6, 0, Math.PI * 2 ); ctx.fill(); break;
			default: ctx.fillRect( -p.size / 2, -p.size * 0.7, p.size, p.size * 1.4 );
		}
		ctx.restore();
	}

	function drawPaper( p, alpha ) {
		var fs   = Math.cos( p.flip );          // -1..1 flip state
		var face = fs >= 0;
		var w    = p.size * ( 0.18 + 0.82 * Math.abs( fs ) ); // foreshorten edge-on
		var h    = p.size * ( p.S.shape === 'bill' ? 1.0 : 1.5 );
		ctx.save();
		ctx.globalAlpha = alpha;
		ctx.translate( p.x, p.y );
		ctx.rotate( p.rot );
		// soft drop-shadow → depth on real backgrounds
		ctx.shadowColor = 'rgba(0,0,0,0.28)';
		ctx.shadowBlur  = 3 * dpr;
		ctx.shadowOffsetY = 1.5 * dpr;
		// sheen: light→dark across the flip so foil/paper catches light
		var g = ctx.createLinearGradient( -w / 2, 0, w / 2, 0 );
		var hi = p.S.sheen ? 55 : 34, lo = p.S.sheen ? -48 : -34;
		g.addColorStop( 0,   shade( p.color, face ? hi : lo ) );
		g.addColorStop( 0.5, p.color );
		g.addColorStop( 1,   shade( p.color, face ? lo : hi ) );
		ctx.fillStyle = g;
		if ( p.S.shape === 'ribbon' ) {
			ctx.shadowBlur = 2 * dpr; ctx.lineCap = 'round';
			ctx.strokeStyle = g; ctx.lineWidth = w;
			ctx.beginPath();
			ctx.moveTo( 0, -p.len ); ctx.quadraticCurveTo( p.len * 0.6, -p.len * 0.3, 0, 0 );
			ctx.quadraticCurveTo( -p.len * 0.6, p.len * 0.3, 0, p.len ); ctx.stroke();
		} else {
			shapePath( p.S.shape, w, h ); ctx.fill();
		}
		ctx.restore();
	}

	function drawGlow( p, alpha ) {
		var tw = 1;
		if ( p.S.twinkle ) { tw = 0.35 + 0.65 * Math.abs( Math.sin( p.age * 0.15 + p.seed ) ); }
		if ( p.S.flicker ) { tw = 0.55 + 0.45 * Math.sin( p.age * 0.5 + p.seed ); }
		ctx.save();
		ctx.globalCompositeOperation = p.S.blend || 'lighter';
		ctx.globalAlpha = clamp( alpha * tw, 0, 1 );
		var rad = p.size * ( p.S.big ? 3.2 : 1.6 );
		var g = ctx.createRadialGradient( p.x, p.y, 0, p.x, p.y, rad );
		g.addColorStop( 0,   rgba( p.color, 0.95 ) );
		g.addColorStop( 0.35, rgba( p.color, 0.55 ) );
		g.addColorStop( 1,   rgba( p.color, 0 ) );
		ctx.fillStyle = g;
		ctx.beginPath(); ctx.arc( p.x, p.y, rad, 0, Math.PI * 2 ); ctx.fill();
		ctx.restore();
	}

	function drawSoft( p, alpha ) {
		ctx.save();
		ctx.globalAlpha = alpha;
		if ( p.S.shape === 'bubble' ) {
			// translucent bubble: faint fill + rim + highlight dot
			ctx.beginPath(); ctx.arc( p.x, p.y, p.size, 0, Math.PI * 2 );
			ctx.fillStyle = rgba( p.color, 0.12 ); ctx.fill();
			ctx.lineWidth = Math.max( 1, p.size * 0.12 ); ctx.strokeStyle = rgba( p.color, 0.7 ); ctx.stroke();
			ctx.beginPath(); ctx.arc( p.x - p.size * 0.3, p.y - p.size * 0.3, p.size * 0.18, 0, Math.PI * 2 );
			ctx.fillStyle = 'rgba(255,255,255,0.85)'; ctx.fill();
		} else { // soft snowflake — blurred white core
			var g = ctx.createRadialGradient( p.x, p.y, 0, p.x, p.y, p.size );
			g.addColorStop( 0, rgba( p.color, 0.95 ) );
			g.addColorStop( 0.6, rgba( p.color, 0.5 ) );
			g.addColorStop( 1, rgba( p.color, 0 ) );
			ctx.fillStyle = g;
			ctx.beginPath(); ctx.arc( p.x, p.y, p.size, 0, Math.PI * 2 ); ctx.fill();
		}
		ctx.restore();
	}

	function draw( p, alpha ) {
		switch ( p.S.rend ) {
			case 'paper': drawPaper( p, alpha ); break;
			case 'glow':  drawGlow( p, alpha );  break;
			case 'soft':  drawSoft( p, alpha );  break;
			default:      drawFlat( p, alpha );
		}
	}

	function step() {
		if ( ! particles.length ) { running = false; if ( ctx ) { ctx.clearRect( 0, 0, canvas.width, canvas.height ); } return; }
		if ( document.hidden ) { requestAnimationFrame( step ); return; }
		ctx.clearRect( 0, 0, canvas.width, canvas.height );
		for ( var i = particles.length - 1; i >= 0; i-- ) {
			var p = particles[ i ];
			p.age++;
			p.vy += p.g;
			p.vx *= p.drag; p.vy *= p.drag;
			p.x += p.vx; p.y += p.vy;
			if ( p.sway ) { p.wob += 0.05; p.x += Math.sin( p.wob ) * p.sway * dpr; }
			if ( p.drift ) { p.x += p.drift; }
			p.rot += p.vr;
			p.flip += p.vf;
			var t = p.age / p.life;
			if ( t >= 1 || p.y > canvas.height + 60 * dpr ) { particles.splice( i, 1 ); continue; }
			draw( p, t < 0.82 ? 1 : Math.max( 0, 1 - ( t - 0.82 ) / 0.18 ) );
		}
		requestAnimationFrame( step );
	}

	function start() { if ( ! running ) { running = true; requestAnimationFrame( step ); } }

	function bind( el ) {
		if ( el.__cfBound ) { return; }
		el.__cfBound = true;
		if ( reduce ) { return; } // decorative motion — skip under reduce-motion

		var triggers = ( el.getAttribute( 'data-cf-trigger' ) || 'view' ).split( /\s+/ ).filter( Boolean );
		if ( ! triggers.length ) { triggers = [ 'view' ]; }
		var has    = function ( t ) { return triggers.indexOf( t ) >= 0; };
		var replay = el.getAttribute( 'data-cf-replay' ) === '1';

		if ( has( 'click' ) ) { el.addEventListener( 'click', function () { burst( el ); } ); }
		if ( has( 'hover' ) ) { el.addEventListener( 'mouseenter', function () { burst( el ); } ); }
		if ( has( 'load' ) )  { burst( el ); }
		if ( has( 'view' ) ) {
			if ( 'IntersectionObserver' in window ) {
				var io = new IntersectionObserver( function ( ents ) {
					ents.forEach( function ( en ) {
						if ( en.isIntersecting ) { burst( el ); if ( ! replay ) { io.unobserve( el ); } }
					} );
				}, { threshold: 0.3 } );
				io.observe( el );
			} else {
				burst( el );
			}
		}
	}

	function init() {
		var els = document.querySelectorAll( '.sc-confetti' );
		for ( var i = 0; i < els.length; i++ ) { bind( els[ i ] ); }
	}
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', init ); } else { init(); }

	window.upwConfettiRescan = init;
})();
