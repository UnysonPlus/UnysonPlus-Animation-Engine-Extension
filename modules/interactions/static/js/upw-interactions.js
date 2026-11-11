/*!
 * Unyson+ Animation Engine — Interactions (roadmap I1 · Targets).
 *
 * A trigger on an element ([data-upw-ix] = JSON list of interactions) plays steps that animate this element,
 * elements inside it, or elements on the page. Web Animations API on the individual transform properties
 * (translate / scale / rotate) + opacity + filter, so it layers on top of the target's own transforms.
 *
 * One interaction = one "group" of animations laid on a shared timeline: every animation gets the same end
 * time (endDelay pads the short ones), so reversing the whole group mirrors the sequence exactly, and an
 * interrupted hover reverses from wherever it got to. Keyframes are partial (one explicit end), so "To"
 * starts from the target's current look and "From" returns to it.
 */
( function () {
	'use strict';

	if ( typeof window === 'undefined' || typeof document === 'undefined' ) { return; }
	if ( ! window.Element || ! Element.prototype.animate ) { return; }
	// The Live Editor's Edit canvas holds every effect still (its Play / Preview run the real page).
	if ( window.upwEditCanvas ) { return; }

	var cfg = window.upwIxCfg || {};
	var reduce = cfg.reducedMotion !== false && !! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );

	function q( root, sel ) {
		try { return Array.prototype.slice.call( root.querySelectorAll( sel ) ); } catch ( e ) { return []; }
	}

	function targetsOf( el, s ) {
		if ( s.tg === 'self' ) { return [ el ]; }
		if ( s.tg === 'inside' ) { return s.q ? q( el, s.q ) : Array.prototype.slice.call( el.children ); }
		return s.q ? q( document, s.q ) : [];
	}

	/** The keyframe holding this step's values (only the properties it changes). */
	/** The keyframe values of a step / state. `a` (amount, default 1) scales how far it goes: distance, rotation
	 *  and blur, and the scale and opacity changes (the per-device "Amount on tablet / phone", roadmap I5). */
	function valuesOf( s, a ) {
		a = ( a === undefined || a === null ) ? 1 : a;
		var k = {}, r2 = function ( n ) { return Math.round( n * 1000 ) / 1000; };
		if ( s.x || s.y ) { k.translate = r2( ( s.x || 0 ) * a ) + 'px ' + r2( ( s.y || 0 ) * a ) + 'px'; }
		if ( s.sc !== 1 ) { k.scale = String( r2( 1 + ( s.sc - 1 ) * a ) ); }
		if ( s.r ) { k.rotate = r2( s.r * a ) + 'deg'; }
		if ( s.op !== 1 ) { k.opacity = String( Math.max( 0, Math.min( 1, r2( 1 - ( 1 - s.op ) * a ) ) ) ); }
		if ( s.bl ) { k.filter = 'blur(' + r2( s.bl * a ) + 'px)'; }
		return k;
	}

	/* ---- States (roadmap I3) ----------------------------------------------------------------------------
	 * [data-upw-states] = the element's named states: values (an animation to them, held while in the state),
	 * classes (upw-is-<name> + any extra), an exclusive group and "inactive". An Interactions step changes them;
	 * a state that the target does not define still toggles its upw-is-<name> class (CSS-only states). */
	function stOf( el ) {
		if ( el.__upwSt ) { return el.__upwSt; }
		var defs = {};
		try {
			( JSON.parse( el.getAttribute( 'data-upw-states' ) || '[]' ) || [] ).forEach( function ( d ) { if ( d && d.n ) { defs[ d.n ] = d; } } );
		} catch ( e ) {}
		el.__upwSt = { defs: defs, on: {}, anims: {} };
		return el.__upwSt;
	}
	function inState( el, name ) { return !! stOf( el ).on[ name ]; }
	function setState( el, name, on, instant ) {
		var st = stOf( el );
		if ( !! st.on[ name ] === !! on ) { return; }
		var d = st.defs[ name ] || { n: name, x: 0, y: 0, sc: 1, r: 0, op: 1, bl: 0, cls: '', d: 0, e: 'linear', g: '', inert: 0 };
		// An exclusive group: entering takes the state away from every other element of the group.
		if ( on && d.g ) {
			q( document, '[data-upw-states]' ).forEach( function ( o ) {
				if ( o === el ) { return; }
				var os = stOf( o );
				if ( os.on[ name ] && os.defs[ name ] && os.defs[ name ].g === d.g ) { setState( o, name, false, instant ); }
			} );
		}
		st.on[ name ] = !! on;
		var vals = valuesOf( d );
		if ( Object.keys( vals ).length ) {
			var dur = ( reduce || instant ) ? 0 : d.d * 1000;
			var a = st.anims[ name ];
			try {
				if ( ! a ) {
					a = el.animate( [ Object.assign( { offset: 1 }, vals ) ], { duration: dur, easing: d.e, fill: 'both' } );
					a.pause();
					a.currentTime = 0;
					st.anims[ name ] = a;
				} else if ( a.effect && a.effect.updateTiming ) {
					a.effect.updateTiming( { duration: dur } );
				}
				a.playbackRate = on ? 1 : -1;
				if ( ! on && ( a.currentTime || 0 ) <= 0 ) {
					a.pause(); // never got going: playing backwards from 0 would jump to the end first
				} else {
					a.play();
					if ( ! dur ) { a.finish(); }
				}
			} catch ( e ) {}
		}
		el.classList.toggle( 'upw-is-' + name, !! on );
		String( d.cls || '' ).split( /\s+/ ).filter( Boolean ).forEach( function ( c ) { el.classList.toggle( c, !! on ); } );
		if ( d.inert ) {
			if ( on ) { el.setAttribute( 'inert', '' ); el.setAttribute( 'aria-hidden', 'true' ); } else { el.removeAttribute( 'inert' ); el.removeAttribute( 'aria-hidden' ); }
		}
		el.setAttribute( 'data-upw-state', Object.keys( st.on ).filter( function ( k ) { return st.on[ k ]; } ).join( ' ' ) );
		try { el.dispatchEvent( new CustomEvent( 'upw:state', { bubbles: true, detail: { state: name, on: !! on } } ) ); } catch ( e ) {}
	}
	/** Run one state step on a node; `rev` plays it backwards (on ↔ off, toggle stays toggle). */
	function act( p, rev ) {
		var a = p.act;
		if ( rev ) { a = a === 'on' ? 'off' : ( a === 'off' ? 'on' : 'toggle' ); }
		setState( p.node, p.sn, a === 'toggle' ? ! inState( p.node, p.sn ) : a === 'on' );
	}

	function build( el, ix, amt ) {
		var plan = [], acts = [], t = 0, prevStart = 0, total = 0;
		ix.s.forEach( function ( s ) {
			var nodes = targetsOf( el, s );
			if ( ! nodes.length ) { return; }
			var d = reduce ? 0 : s.d * 1000, dl = reduce ? 0 : s.dl * 1000, stg = reduce ? 0 : s.st * 1000;
			var start = ( s.w ? prevStart : t ) + dl;
			if ( s.a ) {
				nodes.forEach( function ( n, i ) {
					var at = start + i * stg;
					acts.push( { node: n, act: s.a, sn: s.sn, delay: at } );
					total = Math.max( total, at );
				} );
			} else {
				var vals = valuesOf( s, amt );
				if ( ! Object.keys( vals ).length ) { return; }
				nodes.forEach( function ( n, i ) {
					var at = start + i * stg;
					plan.push( { node: n, kf: s.dir === 'from' ? [ Object.assign( { offset: 0 }, vals ) ] : [ Object.assign( { offset: 1 }, vals ) ], delay: at, dur: d, ease: s.e } );
					total = Math.max( total, at + d );
				} );
			}
			prevStart = start;
			t = Math.max( t, start + ( nodes.length - 1 ) * stg + ( s.a ? 0 : d ) );
		} );
		var anims = plan.map( function ( p ) {
			var a;
			try {
				a = p.node.animate( p.kf, { duration: p.dur, delay: p.delay, endDelay: Math.max( 0, total - p.delay - p.dur ), easing: p.ease, fill: 'both' } );
			} catch ( e ) { return null; }
			a.pause();
			a.currentTime = 0; // "From" steps show their start values straight away (no flash before the trigger)
			return a;
		} ).filter( Boolean );
		return { anims: anims, acts: acts, total: total, timers: [], state: 'idle' };
	}

	function run( g, rate ) {
		g.anims.forEach( function ( a ) {
			a.playbackRate = rate;
			if ( rate < 0 && ( a.currentTime || 0 ) <= 0 ) { a.pause(); return; } // backwards from 0 would jump to the end
			a.play();
		} );
		// State steps fire at their place on the timeline (mirrored when playing backwards).
		g.timers.forEach( function ( t ) { window.clearTimeout( t ); } );
		g.timers = [];
		g.acts.forEach( function ( p ) {
			var at = rate > 0 ? p.delay : Math.max( 0, g.total - p.delay );
			if ( at <= 0 ) { act( p, rate < 0 ); return; }
			g.timers.push( window.setTimeout( function () { act( p, rate < 0 ); }, at ) );
		} );
	}
	function play( g ) {
		if ( g.state === 'on' ) { return; } // already playing / played forwards (play() at the end would rewind)
		run( g, 1 );
		g.state = 'on';
	}
	function back( g ) {
		if ( g.state !== 'on' ) { return; } // never played: nothing to reverse
		run( g, -1 );
		g.state = 'off';
	}
	function replay( g ) {
		g.anims.forEach( function ( a ) { a.pause(); a.playbackRate = 1; a.currentTime = 0; } );
		g.state = 'off';
		play( g );
	}

	/* ---- Devices (roadmap I5) — the same breakpoints as the builder's "Hide on": phone < 768 px,
	 * tablet 768–991 px, desktop ≥ 992 px. An interaction limited to some devices is built only while the
	 * screen is one of them, and taken down (targets back to their own look) when it stops being one. */
	var DEVICE_MQ = { d: '(min-width: 992px)', t: '(min-width: 768px) and (max-width: 991.98px)', p: '(max-width: 767.98px)' };
	function deviceQueries( dev ) {
		if ( ! Array.isArray( dev ) || ! dev.length || dev.length >= 3 || ! window.matchMedia ) { return null; }
		return dev.filter( function ( k ) { return DEVICE_MQ[ k ]; } ).map( function ( k ) { return window.matchMedia( DEVICE_MQ[ k ] ); } );
	}
	function teardown( g ) {
		g.timers.forEach( function ( t ) { window.clearTimeout( t ); } );
		g.anims.forEach( function ( a ) { try { a.cancel(); } catch ( e ) {} } );
	}

	function wire( el, ix ) {
		var g = null, mqs = deviceQueries( ix.dev );
		var allowed = function () { return ! mqs || mqs.some( function ( m ) { return m.matches; } ); };
		// Per-device amount: tablet / phone scale how far the steps go (rebuilt when the device changes).
		var amq = ( ix.amt && window.matchMedia ) ? { t: window.matchMedia( DEVICE_MQ.t ), p: window.matchMedia( DEVICE_MQ.p ) } : null;
		var amount = function () {
			if ( ! amq ) { return 1; }
			if ( amq.t.matches && ix.amt.t !== undefined ) { return ix.amt.t; }
			if ( amq.p.matches && ix.amt.p !== undefined ) { return ix.amt.p; }
			return 1;
		};
		/** The interaction's animations while this device is allowed; null (and nothing applied) otherwise. */
		var G = function () {
			if ( allowed() ) {
				var k = amount();
				if ( g && g.k !== k ) { teardown( g ); g = null; }
				if ( ! g ) { g = build( el, ix, k ); g.k = k; }
				return ( g.anims.length || g.acts.length ) ? g : null;
			}
			if ( g ) { teardown( g ); g = null; }
			return null;
		};
		var on = function ( fn ) { return function ( e ) { var x = G(); if ( x ) { fn( x, e ); } }; };
		switch ( ix.t ) {
			case 'hover':
				el.addEventListener( 'mouseenter', on( play ) );
				el.addEventListener( 'mouseleave', on( back ) );
				el.addEventListener( 'focusin', on( play ) );
				el.addEventListener( 'focusout', on( function ( x, e ) { if ( ! el.contains( e.relatedTarget ) ) { back( x ); } } ) );
				break;
			case 'click':
				var toggles = ix.again === 'reverse' && ix.s.some( function ( st ) { return st.a; } );
				if ( toggles ) { el.setAttribute( 'aria-expanded', 'false' ); }
				el.addEventListener( 'click', on( function ( x ) {
					if ( x.state !== 'on' ) { play( x ); } else if ( ix.again === 'reverse' ) { back( x ); } else if ( ix.again === 'replay' ) { replay( x ); }
					if ( toggles ) { el.setAttribute( 'aria-expanded', x.state === 'on' ? 'true' : 'false' ); }
				} ) );
				if ( el.style.cursor === '' ) { el.style.cursor = 'pointer'; }
				break;
			case 'load':
				var go = function () { window.requestAnimationFrame( on( play ) ); };
				if ( document.readyState === 'complete' ) { go(); } else { window.addEventListener( 'load', go ); }
				break;
			case 'view':
				if ( ! ( 'IntersectionObserver' in window ) ) { var x0 = G(); if ( x0 ) { play( x0 ); } break; }
				var io = new IntersectionObserver( function ( entries ) {
					entries.forEach( function ( en ) {
						var x = G();
						if ( ! x ) { return; }
						if ( en.isIntersecting ) {
							if ( x.state !== 'on' ) { play( x ); }
							if ( ix.leave === 'keep' ) { io.disconnect(); }
						} else if ( ix.leave !== 'keep' ) {
							back( x );
						}
					} );
				}, { threshold: 0.2 } );
				io.observe( el );
				break;
			case 'past':
				var ticking = false;
				var check = function () {
					ticking = false;
					var x = G();
					if ( ! x ) { return; }
					var past = el.getBoundingClientRect().bottom < ( ix.offset || 0 );
					if ( past && x.state !== 'on' ) { play( x ); } else if ( ! past ) { back( x ); }
				};
				window.addEventListener( 'scroll', function () {
					if ( ! ticking ) { ticking = true; window.requestAnimationFrame( check ); }
				}, { passive: true } );
				check();
				break;
		}
		G(); // apply "From" start values now, on an allowed device
		// Crossing a breakpoint: build or take down; a load interaction that becomes allowed plays at once.
		( mqs || [] ).concat( amq ? [ amq.t, amq.p ] : [] ).forEach( function ( m ) {
			var change = function () {
				var had = !! g, x = G();
				if ( x && ! had && ix.t === 'load' ) { play( x ); }
				if ( x && ! had && ix.t === 'past' ) { window.requestAnimationFrame( function () { window.dispatchEvent( new Event( 'scroll' ) ); } ); }
			};
			if ( m.addEventListener ) { m.addEventListener( 'change', change ); } else if ( m.addListener ) { m.addListener( change ); }
		} );
	}

	/** Wire one interaction on its element — or, when it is set to run on every element that matches a selector
	 *  (roadmap I5, reuse), on each of those, so "this element" / "inside it" mean that element. */
	function wireAll( el, ix ) {
		if ( ! ix.on ) { wire( el, ix ); return; }
		var list = q( document, ix.on );
		if ( list.indexOf( el ) < 0 && ix.self ) { list.unshift( el ); }
		list.forEach( function ( m ) {
			m.__upwIxOn = m.__upwIxOn || [];
			if ( m.__upwIxOn.indexOf( ix ) >= 0 ) { return; }
			m.__upwIxOn.push( ix );
			wire( m, ix );
		} );
	}

	function init( root ) {
		q( root || document, '[data-upw-states]' ).forEach( function ( el ) {
			var st = stOf( el );
			Object.keys( st.defs ).forEach( function ( n ) { if ( st.defs[ n ].start ) { setState( el, n, true, true ); } } );
		} );
		// Site-wide interactions (made on another page, "Use on every page"): wired onto this page's matches.
		if ( ! root && Array.isArray( window.upwIxSite ) ) {
			window.upwIxSite.forEach( function ( ix ) { if ( ix && ix.on && Array.isArray( ix.s ) ) { wireAll( null, ix ); } } );
		}
		q( root || document, '[data-upw-ix]' ).forEach( function ( el ) {
			if ( el.__upwIx ) { return; }
			el.__upwIx = true;
			var list;
			try { list = JSON.parse( el.getAttribute( 'data-upw-ix' ) ); } catch ( e ) { return; }
			( Array.isArray( list ) ? list : [] ).forEach( function ( ix ) {
				if ( ix && Array.isArray( ix.s ) ) { wireAll( el, ix ); }
			} );
		} );
	}

	window.upwIx = {
		init: init,
		/** Put an element in a state, take it out (on = false), or read it: upwIx.state( el, 'open', true ). */
		state: function ( el, name, on ) { if ( on === undefined ) { return inState( el, name ); } setState( el, name, on ); return !! on; }
	};
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () { init(); } );
	} else {
		init();
	}
} )();
