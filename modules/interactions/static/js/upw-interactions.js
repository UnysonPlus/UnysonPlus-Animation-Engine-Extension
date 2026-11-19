/*!
 * Unyson+ Animation Engine — Interactions (roadmap I1 · Targets).
 *
 * A trigger on an element ([data-upw-ix] = JSON list of interactions) plays steps that animate this element,
 * elements inside it, or elements on the page. Web Animations API on the individual transform properties
 * (translate / scale / rotate) + opacity + filter, so it layers on top of the target's own transforms; plus
 * (More properties, roadmap 6a) size, corner radius, colours, more filters, a clip, and skew / 3D tilt as an
 * additive transform. Easing = a CSS timing function from the shared easing set (springs as linear()).
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

	/** The keyframe values of a step / state (only the properties it changes). `a` (amount, default 1) scales how
	 *  far it goes: distance, rotation, blur, the scale and opacity changes, skew, tilt, the filters and the clip
	 *  (the per-device "Amount on tablet / phone", roadmap I5). Size, corner radius and colours are absolute. */
	function valuesOf( s, a ) {
		a = ( a === undefined || a === null ) ? 1 : a;
		var k = {}, r2 = function ( n ) { return Math.round( n * 1000 ) / 1000; };
		if ( s.x || s.y ) { k.translate = r2( ( s.x || 0 ) * a ) + 'px ' + r2( ( s.y || 0 ) * a ) + 'px'; }
		if ( s.sc !== undefined && s.sc !== 1 ) { k.scale = String( r2( 1 + ( s.sc - 1 ) * a ) ); }
		if ( s.r ) { k.rotate = r2( s.r * a ) + 'deg'; }
		if ( s.op !== undefined && s.op !== 1 ) { k.opacity = String( Math.max( 0, Math.min( 1, r2( 1 - ( 1 - s.op ) * a ) ) ) ); }
		// More properties (roadmap 6a).
		if ( s.w ) { k.width = s.w + 'px'; }
		if ( s.h ) { k.height = s.h + 'px'; }
		if ( s.rad ) { k.borderRadius = s.rad + 'px'; }
		if ( s.bg ) { k.backgroundColor = s.bg; }
		if ( s.fg ) { k.color = s.fg; }
		if ( s.bc ) { k.borderColor = s.bc; }
		var f = [], pct = function ( v ) { return r2( 1 + ( v / 100 - 1 ) * a ); };
		if ( s.bl ) { f.push( 'blur(' + r2( s.bl * a ) + 'px)' ); }
		if ( s.br !== undefined ) { f.push( 'brightness(' + pct( s.br ) + ')' ); }
		if ( s.ct !== undefined ) { f.push( 'contrast(' + pct( s.ct ) + ')' ); }
		if ( s.sa !== undefined ) { f.push( 'saturate(' + pct( s.sa ) + ')' ); }
		if ( s.gs ) { f.push( 'grayscale(' + r2( s.gs / 100 * a ) + ')' ); }
		if ( f.length ) { k.filter = f.join( ' ' ); }
		return k;
	}

	/** Skew and 3D tilt, as a transform ADDED to the target's own (its Entrance, Hover, Scroll Motion...), or ''. */
	function tiltOf( s, a ) {
		a = ( a === undefined || a === null ) ? 1 : a;
		var t = [], r = function ( n ) { return Math.round( n * a * 100 ) / 100; };
		if ( s.rx || s.ry ) { t.push( 'perspective(900px)' ); }
		if ( s.rx ) { t.push( 'rotateX(' + r( s.rx ) + 'deg)' ); }
		if ( s.ry ) { t.push( 'rotateY(' + r( s.ry ) + 'deg)' ); }
		if ( s.skx || s.sky ) { t.push( 'skew(' + r( s.skx || 0 ) + 'deg, ' + r( s.sky || 0 ) + 'deg)' ); }
		return t.join( ' ' );
	}

	/** The clip as [ open, clipped ] (a clip-path pair that interpolates), or null. The open shape reaches past
	 *  the box so shadows and outlines are not cut while nothing is hidden. */
	function clipOf( s, a ) {
		if ( ! s.cs || ! s.ca ) { return null; }
		var c = Math.max( 0, Math.min( 100, s.ca * ( ( a === undefined || a === null ) ? 1 : a ) ) );
		if ( s.cs === 'c' ) { return [ 'circle(100% at 50% 50%)', 'circle(' + ( Math.round( ( 100 - c ) * 100 ) / 100 ) + '% at 50% 50%)' ]; }
		var o = -30, e = Math.round( ( o + ( 100 - o ) * c / 100 ) * 100 ) / 100, v = [ o, o, o, o ];
		v[ { t: 0, r: 1, b: 2, l: 3 }[ s.cs ] ] = e;
		return [ 'inset(-30% -30% -30% -30%)', 'inset(' + v.join( '% ' ) + '%)' ];
	}

	// Springs and the Motion design curves are CSS linear() curves (Chrome 113, Firefox 112, Safari 17.2). An older
	// browser would reject them, so it gets the nearest cubic-bezier: an overshoot when the curve passes 1.
	var LINEAR_OK = !! ( window.CSS && CSS.supports && CSS.supports( 'transition-timing-function', 'linear(0, 1)' ) );
	function ez( e ) {
		e = e || 'cubic-bezier(.22,1,.36,1)';
		if ( LINEAR_OK || e.indexOf( 'linear(' ) !== 0 ) { return e; }
		return e.split( ',' ).some( function ( v ) { return parseFloat( v.replace( 'linear(', '' ) ) > 1.001; } ) ? 'cubic-bezier(.34,1.56,.64,1)' : 'cubic-bezier(.22,1,.36,1)';
	}

	/**
	 * The Web Animations for one target of a step / state: its values, its skew / tilt (additive, so it stacks on
	 * the target's own transform) and its clip (an explicit pair, since clip-path does not ease from "none").
	 * `from` = start at the values and return to the target's own look. Each animation comes back paused at 0.
	 */
	function animate( node, s, a, from, timing ) {
		var out = [], vals = valuesOf( s, a ), tilt = tiltOf( s, a ), clip = clipOf( s, a );
		var t = Object.assign( {}, timing, { easing: ez( timing.easing ) } );
		var one = function ( kf, extra ) {
			try {
				var x = node.animate( kf, extra ? Object.assign( {}, t, extra ) : t );
				x.pause();
				x.currentTime = 0;
				out.push( x );
			} catch ( e ) {}
		};
		if ( Object.keys( vals ).length ) { one( [ Object.assign( { offset: from ? 0 : 1 }, vals ) ] ); }
		if ( tilt ) { one( [ { offset: from ? 0 : 1, transform: tilt } ], { composite: 'add' } ); }
		if ( clip ) { one( from ? [ { clipPath: clip[ 1 ] }, { clipPath: clip[ 0 ] } ] : [ { clipPath: clip[ 0 ] }, { clipPath: clip[ 1 ] } ] ); }
		return out;
	}
	/** Does this step / state change anything? */
	function moves( s ) { return !! ( Object.keys( valuesOf( s ) ).length || tiltOf( s ) || clipOf( s ) ); }

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
		if ( moves( d ) ) {
			var dur = ( reduce || instant ) ? 0 : d.d * 1000;
			var list = st.anims[ name ];
			if ( ! list ) {
				list = st.anims[ name ] = animate( el, d, 1, false, { duration: dur, easing: d.e, fill: 'both' } );
			} else {
				list.forEach( function ( x ) { try { if ( x.effect && x.effect.updateTiming ) { x.effect.updateTiming( { duration: dur } ); } } catch ( e ) {} } );
			}
			list.forEach( function ( x ) {
				try {
					x.playbackRate = on ? 1 : -1;
					if ( ! on && ( x.currentTime || 0 ) <= 0 ) {
						x.pause(); // never got going: playing backwards from 0 would jump to the end first
					} else {
						x.play();
						if ( ! dur ) { x.finish(); }
					}
				} catch ( e ) {}
			} );
		}
		el.classList.toggle( 'upw-is-' + name, !! on );
		String( d.cls || '' ).split( /\s+/ ).filter( Boolean ).forEach( function ( c ) { el.classList.toggle( c, !! on ); } );
		if ( d.inert ) {
			if ( on ) { el.setAttribute( 'inert', '' ); el.setAttribute( 'aria-hidden', 'true' ); } else { el.removeAttribute( 'inert' ); el.removeAttribute( 'aria-hidden' ); }
		}
		el.setAttribute( 'data-upw-state', Object.keys( st.on ).filter( function ( k ) { return st.on[ k ]; } ).join( ' ' ) );
		try { el.dispatchEvent( new CustomEvent( 'upw:state', { bubbles: true, detail: { state: name, on: !! on } } ) ); } catch ( e ) {}
	}
	/* ---- Set, Lottie and Rive steps (roadmap 6c, 6b) -----------------------------------------------------
	 * Instant actions fired at their moment on the timeline, like a state step. Played backwards (hover out, a
	 * second click, scrolling back) each is undone: show ↔ hide, add ↔ remove a class, a z-index / pointer change
	 * or a Rive input goes back to what it was, a Lottie plays the other way. */
	function remember( n, key, val ) {
		n.__upwSet = n.__upwSet || {};
		if ( ! ( key in n.__upwSet ) ) { n.__upwSet[ key ] = val; }
		return n.__upwSet[ key ];
	}
	function restore( n, key ) {
		var v = n.__upwSet && key in n.__upwSet ? n.__upwSet[ key ] : null;
		if ( n.__upwSet ) { delete n.__upwSet[ key ]; }
		return v;
	}
	function doSet( n, s, rev ) {
		var k = s.sk, cls = String( s.sv || '' ).split( /\s+/ ).filter( Boolean );
		if ( rev ) { k = { show: 'hide', hide: 'show', class_add: 'class_remove', class_remove: 'class_add', pointer_off: 'pointer_back', pointer_on: 'pointer_back', z_index: 'z_back' }[ k ] || k; }
		switch ( k ) {
			case 'hide':
				remember( n, 'display', n.style.display );
				n.style.display = 'none';
				break;
			case 'show':
				var was = restore( n, 'display' );
				n.style.display = was && was !== 'none' ? was : '';
				if ( window.getComputedStyle( n ).display === 'none' ) { n.style.display = 'block'; } // hidden by its own CSS
				break;
			case 'class_add': cls.forEach( function ( c ) { n.classList.add( c ); } ); break;
			case 'class_remove': cls.forEach( function ( c ) { n.classList.remove( c ); } ); break;
			case 'class_toggle': cls.forEach( function ( c ) { n.classList.toggle( c ); } ); break;
			case 'z_index':
				remember( n, 'zIndex', n.style.zIndex );
				remember( n, 'position', n.style.position );
				if ( window.getComputedStyle( n ).position === 'static' ) { n.style.position = 'relative'; } // z-index needs a position
				n.style.zIndex = String( s.sv );
				break;
			case 'z_back':
				n.style.zIndex = restore( n, 'zIndex' ) || '';
				n.style.position = restore( n, 'position' ) || '';
				break;
			case 'pointer_off':
			case 'pointer_on':
				remember( n, 'pointerEvents', n.style.pointerEvents );
				n.style.pointerEvents = k === 'pointer_off' ? 'none' : 'auto';
				break;
			case 'pointer_back':
				n.style.pointerEvents = restore( n, 'pointerEvents' ) || '';
				break;
		}
	}
	/** The control API of the Lottie / Rive on (or inside) a node, once that element has loaded (it loads its file
	 *  asynchronously); calls fn( api ) now, or when it is ready (tries for about 5 s). */
	function withPlayer( n, prop, sel, fn ) {
		var tries = 0, find = function () {
			var host = n[ prop ] ? n : ( n.querySelector && n.querySelector( sel ) );
			return host && host[ prop ] ? host[ prop ] : null;
		};
		var go = function () {
			var api = find();
			if ( api ) { try { fn( api ); } catch ( e ) {} return; }
			if ( ++tries < 25 ) { window.setTimeout( go, 200 ); }
		};
		go();
	}
	function doLottie( n, s, rev ) {
		withPlayer( n, 'fwLottie', '[data-lottie]', function ( L ) {
			var op = s.lo;
			if ( reduce ) { // no playback: show where it would end
				if ( op === 'play' || op === 'reverse' || op === 'segment' ) {
					var end = op === 'segment' ? ( rev ? s.lf : s.lt ) : ( ( op === 'play' ) !== !! rev ? L.last() : 0 );
					L.goTo( end, false );
					return;
				}
			}
			switch ( op ) {
				case 'play': if ( rev ) { L.reverse(); } else { L.play(); } break;
				case 'reverse': if ( rev ) { L.play(); } else { L.reverse(); } break;
				case 'pause': if ( rev ) { L.play(); } else { L.pause(); } break;
				case 'stop': if ( ! rev ) { L.stop(); } break;
				case 'frame':
					if ( rev ) { var f = restore( n, 'lottieFrame' ); if ( f !== null ) { L.goTo( f, false ); } } else { remember( n, 'lottieFrame', L.frame() ); L.goTo( s.lf, false ); }
					break;
				case 'segment': if ( rev ) { L.segment( s.lt, s.lf ); } else { L.segment( s.lf, s.lt ); } break;
			}
		} );
	}
	function doRive( n, s, rev ) {
		withPlayer( n, 'fwRive', '.fw-rive', function ( R ) {
			switch ( s.ro ) {
				case 'play': if ( rev ) { R.pause(); } else { R.play(); } break;
				case 'pause': if ( rev ) { R.play(); } else { R.pause(); } break;
				case 'trigger': if ( ! rev ) { R.fireTrigger( s.rn ); } break; // a trigger has nothing to undo
				case 'input':
					if ( rev ) {
						var v = restore( n, 'riveInput:' + s.rn );
						if ( v !== null && v !== undefined ) { R.setInput( s.rn, v ); }
					} else {
						remember( n, 'riveInput:' + s.rn, R.getInput( s.rn ) );
						R.setInput( s.rn, s.rv );
					}
					break;
			}
		} );
	}

	/** A burst from the node (roadmap 5b), drawn by the Confetti module's runtime; nothing to undo. */
	function doBurst( n, s, rev ) {
		if ( rev || reduce ) { return; }
		var tries = 0, go = function () {
			if ( window.upwBurst ) { window.upwBurst( n, { k: s.bk, n: s.bn, r: s.br, d: 0.6, w: 3, c: s.bc ? [ s.bc ] : null } ); return; }
			if ( ++tries < 25 ) { window.setTimeout( go, 200 ); }
		};
		go();
	}

	/** Run one action step on a node; `rev` plays it backwards (state on ↔ off, toggle stays toggle; Set, Lottie and
	 *  Rive steps are undone, see above). */
	function act( p, rev ) {
		if ( p.act === 'set' ) { doSet( p.node, p.s, rev ); return; }
		if ( p.act === 'lottie' ) { doLottie( p.node, p.s, rev ); return; }
		if ( p.act === 'rive' ) { doRive( p.node, p.s, rev ); return; }
		if ( p.act === 'burst' ) { doBurst( p.node, p.s, rev ); return; }
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
					acts.push( { node: n, act: s.a, sn: s.sn, s: s, delay: at } );
					total = Math.max( total, at );
				} );
			} else {
				if ( ! moves( s ) ) { return; }
				nodes.forEach( function ( n, i ) {
					var at = start + i * stg;
					plan.push( { node: n, s: s, delay: at, dur: d, ease: s.e } );
					total = Math.max( total, at + d );
				} );
			}
			prevStart = start;
			t = Math.max( t, start + ( nodes.length - 1 ) * stg + ( s.a ? 0 : d ) );
		} );
		// "From" steps show their start values straight away (each animation is paused at 0: no flash before the trigger).
		var anims = [];
		plan.forEach( function ( p ) {
			anims = anims.concat( animate( p.node, p.s, amt, p.s.dir === 'from', { duration: p.dur, delay: p.delay, endDelay: Math.max( 0, total - p.delay - p.dur ), easing: p.ease, fill: 'both' } ) );
		} );
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
				// The browser counts an element's own clip-path, so one fully clipped by its own step (a clip reveal)
				// would never be "in view": watch its parent instead.
				var clipsSelf = ix.s.some( function ( st ) { return st.cs && st.tg === 'self'; } );
				io.observe( clipsSelf && el.parentElement ? el.parentElement : el );
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
