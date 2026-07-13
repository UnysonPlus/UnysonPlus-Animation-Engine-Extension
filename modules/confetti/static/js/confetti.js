/**
 * Animation Engine — Confetti runtime.
 *
 * Fires a Canvas 2D particle burst from each .sc-confetti element on its trigger (scroll-into-view,
 * click, page load or hover). One shared full-viewport canvas + one rAF loop render every burst;
 * the loop sleeps when no particles are alive. Pure Canvas 2D, no library. Skips entirely under
 * "reduce motion". Data attrs are stamped by confetti.php:
 *   data-cf-style     confetti | stars | fireworks | streamers | hearts | snow
 *   data-cf-trigger   view | click | load | hover
 *   data-cf-count     20..400   particles per burst
 *   data-cf-spread    20..360   fan angle (deg)
 *   data-cf-power     15..100   launch velocity
 *   data-cf-duration  1..7      particle lifetime (s)
 *   data-cf-palette   brand | rainbow | gold | pastel | mono
 *   data-cf-replay    "1"       re-fire on every re-entry (view trigger)
 */
(function () {
	'use strict';

	var cfg    = window.upwConfettiCfg || {};
	var mql    = window.matchMedia || function () { return { matches: false }; };
	var reduce = cfg.reducedMotion !== false && mql( '(prefers-reduced-motion: reduce)' ).matches;

	var PALETTES = {
		brand:   [ '#2f74e6', '#00b295', '#f6b93b', '#ec4899', '#a855f7' ],
		rainbow: [ '#ff3b30', '#ff9500', '#ffcc00', '#34c759', '#00b8d4', '#5856d6', '#af52de' ],
		gold:    [ '#f6b93b', '#ffd700', '#fff3c4', '#e0a800', '#c9971b' ],
		pastel:  [ '#a7c7e7', '#c9e4de', '#f7d9c4', '#f2c6de', '#dbcdf0' ],
		mono:    [ '#2f74e6', '#5b8fe0', '#9dc0f0', '#1c56b8', '#bcd3f7' ]
	};

	var canvas, ctx, dpr = 1, particles = [], running = false;

	function clamp( v, a, b ) { return Math.max( a, Math.min( b, v ) ); }
	function rand( a, b ) { return a + Math.random() * ( b - a ); }
	function pick( arr ) { return arr[ ( Math.random() * arr.length ) | 0 ]; }

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

		var style   = el.getAttribute( 'data-cf-style' ) || 'confetti';
		var count   = clamp( parseInt( el.getAttribute( 'data-cf-count' ), 10 ) || 90, 20, 400 );
		var spread  = clamp( parseFloat( el.getAttribute( 'data-cf-spread' ) ) || 70, 20, 360 ) * Math.PI / 180;
		var power   = clamp( parseFloat( el.getAttribute( 'data-cf-power' ) ) || 45, 15, 100 );
		var life    = clamp( parseFloat( el.getAttribute( 'data-cf-duration' ) ) || 3, 1, 7 );
		var colors  = PALETTES[ el.getAttribute( 'data-cf-palette' ) ] || PALETTES.brand;

		var fall      = ( style === 'snow' );
		var baseAngle = fall ? Math.PI / 2 : -Math.PI / 2; // snow falls down, everything else launches up

		for ( var i = 0; i < count; i++ ) {
			var a = baseAngle + rand( -spread / 2, spread / 2 );
			var v = ( power / 10 ) * rand( 0.4, 1 ) * dpr;
			if ( style === 'fireworks' ) { a = rand( 0, Math.PI * 2 ); v = ( power / 10 ) * rand( 0.6, 1 ) * dpr; }
			particles.push( {
				x: ox, y: oy,
				vx: Math.cos( a ) * v,
				vy: Math.sin( a ) * v,
				g:  fall ? 0.03 * dpr : ( style === 'hearts' ? 0.05 * dpr : 0.14 * dpr ),
				drag: style === 'fireworks' ? 0.96 : 0.985,
				rot: rand( 0, Math.PI * 2 ),
				vr:  rand( -0.2, 0.2 ),
				size: ( style === 'streamers' ? rand( 2, 3 ) : rand( 4, 8 ) ) * dpr,
				len:  rand( 10, 20 ) * dpr,
				color: pick( colors ),
				style: style,
				life: life * 60 * rand( 0.7, 1 ),
				age: 0,
				wob: rand( 0, Math.PI * 2 ),
				sway: ( fall || style === 'hearts' ) ? rand( 0.4, 1.2 ) : 0
			} );
		}
		start();
	}

	function drawStar( s ) {
		ctx.beginPath();
		for ( var k = 0; k < 5; k++ ) {
			var a1 = -Math.PI / 2 + k * 2 * Math.PI / 5;
			var a2 = a1 + Math.PI / 5;
			ctx.lineTo( Math.cos( a1 ) * s, Math.sin( a1 ) * s );
			ctx.lineTo( Math.cos( a2 ) * s * 0.45, Math.sin( a2 ) * s * 0.45 );
		}
		ctx.closePath();
		ctx.fill();
	}
	function drawHeart( s ) {
		ctx.beginPath();
		ctx.moveTo( 0, s * 0.35 );
		ctx.bezierCurveTo( s, -s * 0.5, s * 0.55, -s, 0, -s * 0.35 );
		ctx.bezierCurveTo( -s * 0.55, -s, -s, -s * 0.5, 0, s * 0.35 );
		ctx.closePath();
		ctx.fill();
	}

	function draw( p, alpha ) {
		ctx.save();
		ctx.globalAlpha = alpha;
		ctx.translate( p.x, p.y );
		ctx.rotate( p.rot );
		ctx.fillStyle = p.color;
		ctx.strokeStyle = p.color;
		switch ( p.style ) {
			case 'stars':  drawStar( p.size ); break;
			case 'hearts': drawHeart( p.size ); break;
			case 'streamers':
				ctx.lineWidth = p.size; ctx.lineCap = 'round';
				ctx.beginPath();
				ctx.moveTo( 0, -p.len / 2 );
				ctx.quadraticCurveTo( p.size * 2.5, 0, 0, p.len / 2 );
				ctx.stroke();
				break;
			case 'snow':
			case 'fireworks':
				ctx.beginPath(); ctx.arc( 0, 0, p.size * 0.6, 0, Math.PI * 2 ); ctx.fill();
				break;
			default: // confetti rectangles
				ctx.fillRect( -p.size / 2, -p.size * 0.7, p.size, p.size * 1.4 );
		}
		ctx.restore();
	}

	function step() {
		if ( ! particles.length ) { running = false; if ( ctx ) { ctx.clearRect( 0, 0, canvas.width, canvas.height ); } return; }
		if ( document.hidden ) { requestAnimationFrame( step ); return; } // idle while tab is hidden
		ctx.clearRect( 0, 0, canvas.width, canvas.height );
		for ( var i = particles.length - 1; i >= 0; i-- ) {
			var p = particles[ i ];
			p.age++;
			p.vy += p.g;
			p.vx *= p.drag; p.vy *= p.drag;
			p.x += p.vx; p.y += p.vy;
			if ( p.sway ) { p.wob += 0.05; p.x += Math.sin( p.wob ) * p.sway * dpr; }
			p.rot += p.vr;
			var t = p.age / p.life;
			if ( t >= 1 || p.y > canvas.height + 40 * dpr ) { particles.splice( i, 1 ); continue; }
			draw( p, t < 0.85 ? 1 : Math.max( 0, 1 - ( t - 0.85 ) / 0.15 ) );
		}
		requestAnimationFrame( step );
	}

	function start() { if ( ! running ) { running = true; requestAnimationFrame( step ); } }

	function bind( el ) {
		if ( el.__cfBound ) { return; }
		el.__cfBound = true;
		if ( reduce ) { return; } // decorative motion — skip under reduce-motion

		var trigger = el.getAttribute( 'data-cf-trigger' ) || 'view';
		var replay  = el.getAttribute( 'data-cf-replay' ) === '1';

		if ( trigger === 'click' ) {
			el.addEventListener( 'click', function () { burst( el ); } );
		} else if ( trigger === 'hover' ) {
			el.addEventListener( 'mouseenter', function () { burst( el ); } );
		} else if ( trigger === 'load' ) {
			burst( el );
		} else { // view
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
